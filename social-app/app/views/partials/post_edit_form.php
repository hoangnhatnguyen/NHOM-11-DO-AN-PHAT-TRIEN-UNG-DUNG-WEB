<?php
/**
 * Form chỉnh sửa bài (dùng trên trang /post/edit/{id} và trong modal).
 *
 * @var array<string, mixed> $post
 * @var array<int, array<string, mixed>> $media
 * @var string $editContent
 * @var array<string, mixed>|null $currentUser
 * @var string $csrfToken
 */
$editPostId = (int) ($post['id'] ?? 0);
$media = is_array($media ?? null) ? $media : [];
$__editActionBase = isset($formBaseUrl)
	? rtrim((string) $formBaseUrl, '/')
	: rtrim((string) BASE_URL, '/');
?>
<div class="js-post-edit-form-root">
<article class="card border-0 shadow-sm rounded-4">
	<div class="card-body p-3 p-md-4">
		<?php
		$editUsername = (string) ($currentUser['username'] ?? 'Người dùng');
		$editAvatarRaw = (string) ($currentUser['avatar_url'] ?? '');
		$editAvatarSrc = $editAvatarRaw !== '' && function_exists('media_public_src')
			? media_public_src($editAvatarRaw)
			: '';
		$editAvatarColor = class_exists('Avatar') ? Avatar::colors($editUsername) : ['bg' => '#8adfd7', 'fg' => '#0a3d3a'];
		?>
		<form method="POST" action="<?= htmlspecialchars($__editActionBase, ENT_QUOTES, 'UTF-8') ?>/post/update/<?= $editPostId ?>" data-post-id="<?= (int) $editPostId ?>" enctype="multipart/form-data" class="js-post-edit-form">
			<input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
			<div id="editSaveMsg" class="alert py-2 mb-3 d-none" role="alert"></div>
			<?php if (($_GET['error'] ?? '') === 'empty'): ?>
				<div class="alert alert-warning py-2 mb-3" role="alert">Bài viết phải có nội dung hoặc ít nhất 1 media.</div>
			<?php endif; ?>
			<?php if (($_GET['saved'] ?? '') === '1'): ?>
				<div class="alert alert-success py-2 mb-3" role="alert">Đã lưu chỉnh sửa bài viết.</div>
			<?php endif; ?>

			<div class="d-flex border-bottom pb-3 mb-3">
				<?php if ($editAvatarSrc !== ''): ?>
					<img src="<?= htmlspecialchars($editAvatarSrc, ENT_QUOTES, 'UTF-8') ?>"
						 class="rounded-circle me-2"
						 width="40" height="40"
						 style="object-fit: cover; flex-shrink: 0;"
						 alt="Avatar"
						 loading="lazy"
						 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
					<div class="avatar-sm me-2" style="background: <?= htmlspecialchars($editAvatarColor['bg'], ENT_QUOTES, 'UTF-8') ?>; color: <?= htmlspecialchars($editAvatarColor['fg'], ENT_QUOTES, 'UTF-8') ?>; display:none;">
						<?= htmlspecialchars(Avatar::initials($editUsername), ENT_QUOTES, 'UTF-8') ?>
					</div>
				<?php else: ?>
					<div class="avatar-sm me-2" style="background: <?= htmlspecialchars($editAvatarColor['bg'], ENT_QUOTES, 'UTF-8') ?>; color: <?= htmlspecialchars($editAvatarColor['fg'], ENT_QUOTES, 'UTF-8') ?>;">
						<?= htmlspecialchars(Avatar::initials($editUsername), ENT_QUOTES, 'UTF-8') ?>
					</div>
				<?php endif; ?>

				<div class="flex-grow-1">
					<div class="fw-semibold"><?= htmlspecialchars((string) ($currentUser['username'] ?? 'Người dùng')) ?></div>
					<textarea
						name="content"
						class="form-control bg-light rounded-4 composer-textarea mt-2"
						rows="3"
						placeholder="Chỉnh sửa nội dung..."
						oninput="window.postEditFormAutoResize && window.postEditFormAutoResize(this)"
					><?= htmlspecialchars((string) ($editContent ?? $post['content'] ?? '')) ?></textarea>
				</div>
			</div>

			<?php if (!empty($media)): ?>
				<div class="mb-3">
					<div class="row g-2" id="existingMediaGrid">
						<?php foreach ($media as $m): ?>
							<?php
							$mediaId = (int) ($m['id'] ?? 0);
							$src = media_public_src((string) ($m['media_url'] ?? ''));
							if ($src === '') {
								continue;
							}
							$isVideo = (($m['media_type'] ?? '') === 'video');
							?>
							<div class="col-6 col-md-4" id="media-item-<?= $mediaId ?>">
								<div class="preview-wrapper position-relative">
									<?php if ($isVideo): ?>
										<video src="<?= htmlspecialchars($src, ENT_QUOTES, 'UTF-8') ?>" controls class="w-100 rounded-4" playsinline></video>
									<?php else: ?>
										<img src="<?= htmlspecialchars($src, ENT_QUOTES, 'UTF-8') ?>" class="img-fluid rounded-4 w-100 post-media-tile" alt="">
									<?php endif; ?>

									<button type="button" class="bi bi-x text-white preview-remove" onclick="window.postEditRemoveMedia && window.postEditRemoveMedia(<?= $mediaId ?>)"></button>
									<input type="checkbox" hidden name="remove_media_ids[]" value="<?= $mediaId ?>" id="remove-media-<?= $mediaId ?>">
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<div class="mb-3 d-none" id="newPreviewContainer">
				<div class="row g-2" id="newPreviewGrid"></div>
			</div>

			<div class="d-flex justify-content-between align-items-center">
				<div class="dropdown">
					<button class="btn btn-light btn-sm rounded-pill dropdown-toggle d-flex align-items-center gap-1 mt-2"
							type="button"
							data-bs-toggle="dropdown">

						<i id="editprivacyIcon" class="bi bi-globe2"></i>
						<span id="editprivacyLabel">Công khai</span>
					</button>

					<ul class="dropdown-menu">
						<li>
							<a class="dropdown-item" href="#"
							onclick="window.postEditSetPrivacy && window.postEditSetPrivacy('public', 'bi-globe2', 'Công khai'); return false;">
								Công khai
							</a>
						</li>
						<li>
							<a class="dropdown-item" href="#"
							onclick="window.postEditSetPrivacy && window.postEditSetPrivacy('followers', 'bi-people', 'Người theo dõi'); return false;">
								Người theo dõi
							</a>
						</li>
						<li>
							<a class="dropdown-item" href="#"
							onclick="window.postEditSetPrivacy && window.postEditSetPrivacy('private', 'bi-lock', 'Chỉ mình tôi'); return false;">
								Chỉ mình tôi
							</a>
						</li>
					</ul>
				</div>

				<input type="hidden" name="privacy" id="editprivacyInput" value="<?= htmlspecialchars((string) ($post['visible'] ?? 'public')) ?>">

				<div>
					<label class="btn btn-light btn-sm rounded-pill">
						<i class="bi bi-image"></i>
						<input type="file" name="media[]" id="editfileInput" hidden multiple>
					</label>
					<button type="submit" class="btn btn-primary rounded-pill px-4">Lưu</button>
				</div>
			</div>
		</form>
	</div>
</article>
</div>
