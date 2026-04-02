<?php

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class S3Service
{
    private $s3Client;
    private $bucket;
    private $region;

    public function __construct()
    {
        $this->bucket = getenv('AWS_S3_BUCKET');
        $this->region = getenv('AWS_REGION') ?: 'ap-southeast-1';

        // Debug logging
        error_log('S3Service Init: bucket=[' . ($this->bucket ?: 'EMPTY') . '], region=[' . $this->region . ']');

        if (!$this->bucket) {
            error_log('WARNING: AWS_S3_BUCKET not set in environment variables!');
        }

        $key = getenv('AWS_ACCESS_KEY_ID');
        $secret = getenv('AWS_SECRET_ACCESS_KEY');
        
        if (!$key || !$secret) {
            error_log('WARNING: AWS credentials not set properly!');
        }

        $this->s3Client = new S3Client([
            'version' => 'latest',
            'region'  => $this->region,
            'credentials' => [
                'key'    => $key,
                'secret' => $secret,
            ]
        ]);
    }

    /**
     * Upload file to S3
     * @param string $filePath - Local file path
     * @param string $key - S3 key (folder/filename)
     * @return string|false - S3 URL or false on error
     */
    public function uploadFile($filePath, $key)
    {
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
            error_log('S3 Upload Error [' . __METHOD__ . ']: ' . $e->getMessage());
            return false;
        } catch (\Exception $e) {
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
        try {
            $this->s3Client->putObject([
                'Bucket' => $this->bucket,
                'Key'    => $key,
                'Body'   => $stream,
                'CacheControl' => 'max-age=31536000',
            ]);

            return $this->getPublicUrl($key);
        } catch (AwsException $e) {
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