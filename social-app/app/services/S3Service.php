<?php

/**
 * Service chung cho AWS S3 — mọi upload/xóa/presign trong app nên đi qua class này.
 * Hướng dẫn nhóm: docs/S3_SERVICE_GUIDE.md
 *
 * Nếu thiếu AWS SDK (composer chưa cài đủ), service không throw fatal — API trả false/null,
 * feed và right_widgets vẫn render (ảnh S3 có thể trống tới khi chạy composer install).
 */
class S3Service
{
    /** @var \Aws\S3\S3Client|null */
    private $s3Client;

    private string $bucket;

    private string $region;

    public function __construct()
    {
        $this->bucket = self::envFirstNonEmpty(['AWS_S3_BUCKET', 'AWS_BUCKET']);
        $this->region = self::envFirstNonEmpty(['AWS_REGION', 'AWS_DEFAULT_REGION']) ?: 'ap-southeast-1';
        $this->s3Client = null;

        if ($this->bucket === '') {
            error_log('WARNING: AWS_S3_BUCKET (or AWS_BUCKET) not set');
        }

        $key = self::envFirstNonEmpty(['AWS_ACCESS_KEY_ID']);
        $secret = self::envFirstNonEmpty(['AWS_SECRET_ACCESS_KEY']);
        if ($key === '' || $secret === '') {
            error_log('WARNING: AWS_ACCESS_KEY_ID / AWS_SECRET_ACCESS_KEY not set');
        }

        if (!self::isSdkAvailable()) {
            error_log('S3: AWS SDK chưa đủ — chạy trong thư mục social-app: composer require aws/aws-sdk-php');

            return;
        }

        try {
            $this->s3Client = new \Aws\S3\S3Client([
                'version' => 'latest',
                'region' => $this->region,
                'credentials' => [
                    'key' => $key,
                    'secret' => $secret,
                ],
            ]);
        } catch (\Throwable $e) {
            error_log('S3: không khởi tạo S3Client — ' . $e->getMessage());
            $this->s3Client = null;
        }
    }

    /**
     * Kiểm tra vendor đủ dependency (tránh gọi autoload Aws khi thiếu Guzzle → fatal).
     */
    public static function isSdkAvailable(): bool
    {
        $v = dirname(__DIR__, 2) . '/vendor';

        return is_file($v . '/aws/aws-sdk-php/src/S3/S3Client.php')
            && is_file($v . '/guzzlehttp/guzzle/src/Client.php');
    }

    public function isReady(): bool
    {
        return $this->s3Client !== null && $this->bucket !== '';
    }

    /**
     * @param string[] $names
     */
    private static function envFirstNonEmpty(array $names): string
    {
        foreach ($names as $name) {
            $v = null;
            if (function_exists('env')) {
                $v = env($name);
            }
            if ($v === null || $v === '') {
                $v = getenv($name);
            }
            if ($v !== false && $v !== null && (string) $v !== '') {
                return (string) $v;
            }
        }

        return '';
    }

    /**
     * Trích S3 object key từ URL (virtual-hosted hoặc path-style).
     */
    public static function extractKeyFromS3Url(string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || stripos($url, 'http') !== 0) {
            return null;
        }
        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return null;
        }
        $host = strtolower($parts['host']);
        $rawPath = $parts['path'] ?? '';
        $path = rawurldecode(ltrim($rawPath, '/'));
        $path = explode('?', $path, 2)[0];
        if ($path === '') {
            return null;
        }
        if (preg_match('/^([^.]+)\.s3[.-][a-z0-9-]+\.amazonaws\.com$/', $host)) {
            return $path;
        }
        if (preg_match('/^([^.]+)\.s3\.amazonaws\.com$/', $host)) {
            return $path;
        }
        if (preg_match('/^s3[.-][a-z0-9-]+\.amazonaws\.com$/', $host) || $host === 's3.amazonaws.com') {
            $slash = strpos($path, '/');
            if ($slash !== false && $slash < strlen($path) - 1) {
                return substr($path, $slash + 1);
            }
        }

        return null;
    }

    public function normalizeStorageToKey(string $stored): string
    {
        $stored = trim($stored);
        if ($stored === '') {
            return '';
        }
        if (stripos($stored, 'http') === 0) {
            $key = self::extractKeyFromS3Url($stored);

            return $key !== null ? $key : '';
        }

        return $stored;
    }

    /**
     * @return string|false S3 URL or false on error
     */
    public function uploadFile($filePath, $key)
    {
        if (!$this->s3Client) {
            return false;
        }
        try {
            $key = $this->normalizeStorageToKey((string) $key);
            if ($key === '') {
                return false;
            }
            if (!file_exists($filePath)) {
                throw new \Exception("File not found: $filePath");
            }

            $fileStream = fopen($filePath, 'r');
            if (!$fileStream) {
                throw new \Exception("Cannot open file: $filePath");
            }

            $this->s3Client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
                'Body' => $fileStream,
                'CacheControl' => 'max-age=31536000',
            ]);

            @fclose($fileStream);

            return $this->getPublicUrl($key);
        } catch (\Throwable $e) {
            error_log('S3 Upload Error [' . __METHOD__ . ']: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * @return string|false
     */
    public function uploadStream($stream, $key)
    {
        if (!$this->s3Client) {
            return false;
        }
        try {
            $key = $this->normalizeStorageToKey((string) $key);
            if ($key === '') {
                return false;
            }
            $this->s3Client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
                'Body' => $stream,
                'CacheControl' => 'max-age=31536000',
            ]);

            return $this->getPublicUrl($key);
        } catch (\Throwable $e) {
            error_log('S3 Stream Upload Error: ' . $e->getMessage());

            return false;
        }
    }

    public function deleteFile($key): bool
    {
        if (!$this->s3Client) {
            return false;
        }
        try {
            $key = $this->normalizeStorageToKey((string) $key);
            if ($key === '') {
                return false;
            }
            $this->s3Client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);

            return true;
        } catch (\Throwable $e) {
            error_log('S3 Delete Error: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * URL “public” (không cần SDK). Bucket private thì trình duyệt vẫn có thể 403.
     */
    public function getPublicUrl($key): string
    {
        $key = $this->normalizeStorageToKey((string) $key);
        if ($key === '' || $this->bucket === '') {
            return '';
        }
        $pathEncoded = implode('/', array_map('rawurlencode', explode('/', $key)));

        return "https://{$this->bucket}.s3.{$this->region}.amazonaws.com/{$pathEncoded}";
    }

    /**
     * @return string|null Presigned URL hoặc null
     */
    public function getPresignedUrl($key, $expiration = 3600)
    {
        if (!$this->s3Client) {
            return null;
        }
        try {
            $key = $this->normalizeStorageToKey((string) $key);
            if ($key === '') {
                return null;
            }
            $cmd = $this->s3Client->getCommand('GetObject', [
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);

            $request = $this->s3Client->createPresignedRequest($cmd, "+{$expiration} seconds");
            $presignedUrl = (string) $request->getUri();
            if ($presignedUrl === '') {
                error_log('S3 Presigned URL is empty for key: ' . $key);

                return null;
            }

            return $presignedUrl;
        } catch (\Throwable $e) {
            error_log('S3 Presigned URL Error for key [' . $key . ']: ' . $e->getMessage());

            return null;
        }
    }

    public function generatePostMediaKey(int $postId, string $filename): string
    {
        $timestamp = time();
        $safeFilename = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($filename));

        return "posts/{$postId}/{$timestamp}_{$safeFilename}";
    }

    public function generateAvatarKey(int $userId, string $filename): string
    {
        $timestamp = time();
        $safeFilename = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($filename));

        return "avatars/{$userId}/{$timestamp}_{$safeFilename}";
    }

    public function generateChatKey(int $conversationId, int $userId, string $filename): string
    {
        $timestamp = time();
        $safeFilename = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($filename));

        return "chat/{$conversationId}/{$userId}/{$timestamp}_{$safeFilename}";
    }

    public function deleteFolder(string $prefix): bool
    {
        if (!$this->s3Client) {
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

                $objects = array_map(fn ($item) => ['Key' => $item['Key']], $page['Contents']);
                if (!empty($objects)) {
                    $this->s3Client->deleteObjects([
                        'Bucket' => $this->bucket,
                        'Delete' => ['Objects' => $objects],
                    ]);
                }
            }

            return true;
        } catch (\Throwable $e) {
            error_log('S3 Delete Folder Error: ' . $e->getMessage());

            return false;
        }
    }
}
