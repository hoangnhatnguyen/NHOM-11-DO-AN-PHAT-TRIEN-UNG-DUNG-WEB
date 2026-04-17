<?php

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class S3Service
{
    /** @var S3Client|null */
    private $s3Client = null;
    private $bucket;
    private $region;
    private string $notReadyReason = '';
    private string $lastError = '';

    public function __construct()
    {
        $this->bucket = (string) (getenv('AWS_S3_BUCKET') ?: '');
        $this->region = (string) (getenv('AWS_REGION') ?: 'ap-southeast-1');

        // Debug logging
        error_log('S3Service Init: bucket=[' . ($this->bucket ?: 'EMPTY') . '], region=[' . $this->region . ']');

        if ($this->bucket === '') {
            $this->notReadyReason = 'AWS_S3_BUCKET chưa được cấu hình.';
            error_log('WARNING: AWS_S3_BUCKET not set in environment variables!');
            return;
        }

        $key = (string) (getenv('AWS_ACCESS_KEY_ID') ?: '');
        $secret = (string) (getenv('AWS_SECRET_ACCESS_KEY') ?: '');
        
        if ($key === '' || $secret === '') {
            $this->notReadyReason = 'Thiếu AWS_ACCESS_KEY_ID hoặc AWS_SECRET_ACCESS_KEY.';
            error_log('WARNING: AWS credentials not set properly!');
            return;
        }

        try {
            $this->s3Client = new S3Client([
                'version' => 'latest',
                'region'  => $this->region,
                'credentials' => [
                    'key'    => $key,
                    'secret' => $secret,
                ]
            ]);
        } catch (\Throwable $e) {
            $this->notReadyReason = $e->getMessage();
            error_log('S3Service: không khởi tạo S3Client — ' . $e->getMessage());
        }
    }

    public function isReady(): bool
    {
        return $this->s3Client !== null;
    }

    public function getNotReadyReason(): string
    {
        return $this->notReadyReason !== '' ? $this->notReadyReason : 'S3 client chưa khả dụng.';
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    /**
     * Get the AWS S3 client instance
     * @return S3Client|null
     */
    public function getClient(): ?S3Client
    {
        return $this->s3Client;
    }

    /**
     * Get the S3 bucket name
     * @return string
     */
    public function getBucket(): string
    {
        return $this->bucket;
    }

    public static function extractKeyFromS3Url(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        if (preg_match('#^https?://[^/]+\.s3[.-][^/]+\.amazonaws\.com/(.+)$#i', $url, $m)) {
            return rawurldecode($m[1]);
        }
        if (preg_match('#^https?://s3[.-][^/]+\.amazonaws\.com/[^/]+/(.+)$#i', $url, $m)) {
            return rawurldecode($m[1]);
        }
        return null;
    }

    /**
     * Upload file to S3
     * @param string $filePath - Local file path
     * @param string $key - S3 key (folder/filename)
     * @return string|false - S3 URL or false on error
     */
    public function uploadFile($filePath, $key)
    {
        $this->lastError = '';
        if ($this->s3Client === null) {
            $this->lastError = $this->getNotReadyReason();
            return false;
        }
        try {
            if (!file_exists($filePath)) {
                throw new \Exception("File not found: $filePath");
            }

            $fileStream = fopen($filePath, 'r');
            if (!$fileStream) {
                throw new \Exception("Cannot open file: $filePath");
            }

            $this->s3Client->putObject([
                'Bucket' => $this->bucket,
                'Key'    => $key,
                'Body'   => $fileStream,
                'CacheControl' => 'max-age=31536000',
            ]);

            @fclose($fileStream);
            return $this->getPublicUrl($key);
        } catch (AwsException $e) {
            $this->lastError = $e->getMessage();
            error_log('S3 Upload Error [' . __METHOD__ . ']: ' . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            error_log('Upload Error [' . __METHOD__ . ']: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Upload from stream
     * @param resource $stream - File stream
     * @param string $key - S3 key
     * @return string|false - S3 URL or false
     */
    public function uploadStream($stream, $key)
    {
        $this->lastError = '';
        if ($this->s3Client === null) {
            $this->lastError = $this->getNotReadyReason();
            return false;
        }
        try {
            $this->s3Client->putObject([
                'Bucket' => $this->bucket,
                'Key'    => $key,
                'Body'   => $stream,
                'CacheControl' => 'max-age=31536000',
            ]);

            return $this->getPublicUrl($key);
        } catch (AwsException $e) {
            $this->lastError = $e->getMessage();
            error_log('S3 Stream Upload Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete file from S3
     * @param string $key - S3 key
     * @return bool
     */
    public function deleteFile($key)
    {
        if ($this->s3Client === null) {
            return false;
        }
        try {
            $this->s3Client->deleteObject([
                'Bucket' => $this->bucket,
                'Key'    => $key,
            ]);
            return true;
        } catch (AwsException $e) {
            error_log('S3 Delete Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get public URL for S3 object
     * @param string $key - S3 key
     * @return string - Public URL
     */
    public function getPublicUrl($key)
    {
        return "https://{$this->bucket}.s3.{$this->region}.amazonaws.com/{$key}";
    }

    /**
     * Get presigned URL (for temporary access)
     * @param string $key - S3 key
     * @param int $expiration - Expiration time in seconds (default: 3600)
     * @return string - Presigned URL
     */
    public function getPresignedUrl($key, $expiration = 3600)
    {
        if ($this->s3Client === null) {
            return null;
        }
        try {
            $cmd = $this->s3Client->getCommand('GetObject', [
                'Bucket' => $this->bucket,
                'Key'    => $key
            ]);

            $request = $this->s3Client->createPresignedRequest($cmd, "+{$expiration} seconds");
            $presignedUrl = (string)$request->getUri();
            if (!$presignedUrl) {
                error_log('S3 Presigned URL is empty for key: ' . $key);
                return null;
            }
            return $presignedUrl;
        } catch (AwsException $e) {
            error_log('S3 Presigned URL Error for key [' . $key . ']: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate S3 key for post media
     * @param int $postId
     * @param string $filename
     * @return string
     */
    public function generatePostMediaKey(int $postId, string $filename): string
    {
        $timestamp = time();
        $safeFilename = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($filename));
        return "posts/{$postId}/{$timestamp}_{$safeFilename}";
    }

    /**
     * Generate S3 key for user avatar
     * @param int $userId
     * @param string $filename
     * @return string
     */
    public function generateAvatarKey(int $userId, string $filename): string
    {
        $timestamp = time();
        $safeFilename = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($filename));
        return "avatars/{$userId}/{$timestamp}_{$safeFilename}";
    }

    /**
     * Generate S3 key for chat attachment
     * @param int $conversationId
     * @param int $userId
     * @param string $filename
     * @return string
     */
    public function generateChatKey(int $conversationId, int $userId, string $filename): string
    {
        $timestamp = time();
        $safeFilename = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($filename));
        return "chat/{$conversationId}/{$userId}/{$timestamp}_{$safeFilename}";
    }

    /**
     * Delete folder and all objects inside
     * @param string $prefix - S3 prefix (folder path)
     * @return bool
     */
    public function deleteFolder(string $prefix): bool
    {
        if ($this->s3Client === null) {
            return false;
        }
        try {
            $paginator = $this->s3Client->getPaginator('ListObjects', [
                'Bucket' => $this->bucket,
                'Prefix' => rtrim($prefix, '/') . '/',
            ]);

            foreach ($paginator as $page) {
                if (!isset($page['Contents'])) {
                    continue;
                }

                $objects = array_map(fn($item) => ['Key' => $item['Key']], $page['Contents']);
                if (!empty($objects)) {
                    $this->s3Client->deleteObjects([
                        'Bucket'  => $this->bucket,
                        'Delete'  => ['Objects' => $objects],
                    ]);
                }
            }

            return true;
        } catch (AwsException $e) {
            error_log('S3 Delete Folder Error: ' . $e->getMessage());
            return false;
        }
    }
}