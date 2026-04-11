<?php

declare(strict_types=1);

require_once __DIR__ . '/media.php';
require_once __DIR__ . '/../services/S3Service.php';

/**
 * Xử lý $_FILES['media'] cho bài viết (user post/update và admin sửa bài).
 * Giống logic cũ trong PostController::processUploadedPostMedia.
 */
function process_post_uploaded_media_files(int $postId, PostMedia $mediaModel): void {
	if (!has_post_media_upload()) {
		return;
	}
	$s3Service = new S3Service();
	$s3SkipLogged = false;

	foreach ($_FILES['media']['tmp_name'] as $i => $tmpFile) {
		$err = (int) ($_FILES['media']['error'][$i] ?? UPLOAD_ERR_OK);
		if ($err !== UPLOAD_ERR_OK) {
			error_log("Post media upload PHP error #{$err} for post {$postId} index {$i}");
			continue;
		}
		$name = (string) ($_FILES['media']['name'][$i] ?? '');
		if ($name === '' || !is_uploaded_file($tmpFile)) {
			continue;
		}

		$savedPath = null;
		if ($s3Service->isReady()) {
			$key = $s3Service->generatePostMediaKey($postId, $name);
			if ($s3Service->uploadFile($tmpFile, $key)) {
				$savedPath = $key;
			} elseif ($s3Service->getLastError() !== '') {
				error_log("Post {$postId} S3 upload failed: " . $s3Service->getLastError());
			}
		} elseif (!$s3SkipLogged) {
			$s3SkipLogged = true;
			error_log('Post media: bỏ qua S3 — ' . $s3Service->getNotReadyReason());
		}
		if ($savedPath === null) {
			$savedPath = save_uploaded_post_image_local($postId, $tmpFile, $name);
		}
		if ($savedPath !== null) {
			$mediaModel->addMedia($postId, $savedPath);
		}
	}
}
