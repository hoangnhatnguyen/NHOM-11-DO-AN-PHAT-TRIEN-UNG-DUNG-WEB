<?php

/**
 * URL tĩnh cho file trong public/ từ cột post_media.media_url.
 *
 * - Dữ liệu seed (CONCAT 'media/post_1.jpg'): đường dẫn đã gồm thư mục → /public/media/post_1.jpg
 * - Upload ứng dụng (chỉ tên file): /public/media/<tên file>
 */
function media_public_src(string $mediaUrl): string {
	$mediaUrl = trim($mediaUrl);
	if ($mediaUrl === '') {
		return '';
	}

	$normalized = str_replace('\\', '/', $mediaUrl);
	if (
		strpos($normalized, 'http://') === 0 ||
		strpos($normalized, 'https://') === 0 ||
		strpos($normalized, '//') === 0 ||
		strpos($normalized, 'data:') === 0
	) {
		return $normalized;
	}

	$mediaUrl = ltrim($normalized, '/');
	if (strpos($mediaUrl, 'public/') === 0) {
		return BASE_URL . '/' . $mediaUrl;
	}
	if (strpos($mediaUrl, 'media/') === 0) {
		return BASE_URL . '/public/' . $mediaUrl;
	}
	return BASE_URL . '/public/media/' . $mediaUrl;
}
