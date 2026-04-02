<?php

/**
 * URL hiển thị cho media lưu trong DB — đồng bộ với S3Service (presign, chuẩn hóa URL cũ).
 * Chi tiết: docs/S3_SERVICE_GUIDE.md
 *
 * - Key S3: avatars/…, posts/…, chat/… → S3Service::getPresignedUrl (cache session 24h)
 * - Full URL S3 (legacy) → S3Service::extractKeyFromS3Url rồi presign
 * - Local: media/… hoặc tên file → BASE_URL + /public/media/…
 */
function media_public_src(string $mediaUrl): string {
	$mediaUrl = trim($mediaUrl);
	if ($mediaUrl === '') {
		return '';
	}

	// DB legacy: full S3 URL → chuẩn hóa thành key để presign (bucket private)
	if (stripos($mediaUrl, 'https://') === 0 && strpos($mediaUrl, '.s3.') !== false) {
		require_once __DIR__ . '/../services/S3Service.php';
		$extracted = S3Service::extractKeyFromS3Url($mediaUrl);
		if ($extracted !== null && $extracted !== '') {
			$mediaUrl = $extracted;
		}
	}

	// Check if it's S3 key (contains specific prefixes)
	if (strpos($mediaUrl, 'avatars/') === 0 || 
		strpos($mediaUrl, 'posts/') === 0 || 
		strpos($mediaUrl, 'chat/') === 0) {
		
		// Check cache trong session trước
		if (!isset($_SESSION['_media_cache'])) {
			$_SESSION['_media_cache'] = [];
		}
		
		// Nếu đã cache, return luôn
		if (isset($_SESSION['_media_cache'][$mediaUrl])) {
			return $_SESSION['_media_cache'][$mediaUrl];
		}
		
		// Nếu chưa cache, generate presigned URL
		try {
			require_once __DIR__ . '/../services/S3Service.php';
			$s3Service = new S3Service();
			// Presigned URL valid for 24 hours
			$presignedUrl = $s3Service->getPresignedUrl($mediaUrl, 86400);
			if (!$presignedUrl) {
				error_log('Presigned URL is empty for key: ' . $mediaUrl);
				return '';
			}
			
			// Cache vào session
			$_SESSION['_media_cache'][$mediaUrl] = $presignedUrl;
			return $presignedUrl;
		} catch (\Throwable $e) {
			error_log('Presigned URL error for key [' . $mediaUrl . ']: ' . $e->getMessage());
			return '';
		}
	}

	// URL S3 không parse được (giữ nguyên — ví dụ bucket public)
	if (strpos($mediaUrl, 'https://') === 0 && strpos($mediaUrl, '.s3.') !== false) {
		return $mediaUrl;
	}

	// Local path
	$mediaUrl = ltrim(str_replace('\\', '/', $mediaUrl), '/');
	if (strpos($mediaUrl, 'media/') === 0) {
		return BASE_URL . '/public/' . $mediaUrl;
	}
	return BASE_URL . '/public/media/' . $mediaUrl;
}

/**
 * Lưu ảnh đăng bài vào public/media/posts/{postId}/ khi S3 không dùng được hoặc upload S3 lỗi.
 *
 * @return string|null Đường dẫn lưu DB (media/posts/...) hoặc null
 */
function save_uploaded_post_image_local(int $postId, string $tmpPath, string $originalName): ?string {
	$allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
	$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
	if (!in_array($ext, $allowed, true)) {
		return null;
	}
	if (!is_uploaded_file($tmpPath)) {
		return null;
	}
	$root = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
	$dir = $root . 'public/media/posts/' . $postId;
	if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
		error_log('save_uploaded_post_image_local: cannot mkdir ' . $dir);

		return null;
	}
	$safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($originalName));
	$filename = time() . '_' . $safe;
	$dest = $dir . '/' . $filename;
	if (!move_uploaded_file($tmpPath, $dest)) {
		error_log('save_uploaded_post_image_local: move_uploaded_file failed');

		return null;
	}

	return 'media/posts/' . $postId . '/' . $filename;
}

/**
 * Xóa file đã lưu: S3 key (posts/, avatars/, chat/) hoặc file local (media/...).
 */
function delete_stored_media(string $path): void {
	$path = trim(str_replace('\\', '/', $path));
	if ($path === '') {
		return;
	}
	if (strpos($path, 'media/') === 0) {
		$root = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
		$full = $root . 'public/' . $path;
		if (is_file($full)) {
			@unlink($full);
		}

		return;
	}
	if (strpos($path, 'posts/') === 0 || strpos($path, 'avatars/') === 0 || strpos($path, 'chat/') === 0) {
		require_once __DIR__ . '/../services/S3Service.php';
		(new S3Service())->deleteFile($path);
	}
}
