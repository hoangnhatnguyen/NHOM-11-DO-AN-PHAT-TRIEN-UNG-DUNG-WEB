<?php

/**
 * Generate public URL cho media từ media_url trong DB
 * 
 * - S3 keys: avatars/123/..., posts/456/..., chat/789/... → generate presigned S3 URL
 * - Local paths: /media/post_1.jpg hoặc tên file → /public/media/...
 * 
 * URL được cache trong session để tránh gọi S3 API lặp lại
 */
function media_public_src(string $mediaUrl): string {
	$mediaUrl = trim($mediaUrl);
	if ($mediaUrl === '') {
		return '';
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
		} catch (Exception $e) {
			error_log('Presigned URL error for key [' . $mediaUrl . ']: ' . $e->getMessage());
			return '';
		}
	}

	// Check if it's already an S3 URL (full URL)
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
