/**
 * Mở form chỉnh sửa bài trong modal (từ post_card / detail).
 */
(function () {
	'use strict';

	function loadEditFormIntoModal(contentEl, bsModal, postId) {
		contentEl.innerHTML =
			'<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
		bsModal.show();

		var formUrl =
			typeof window.__appUrl === 'function'
				? window.__appUrl('api/post-edit-form.php?id=' + encodeURIComponent(postId))
				: '/api/post-edit-form.php?id=' + encodeURIComponent(postId);

		fetch(formUrl)
			.then(function (res) {
				return res.json();
			})
			.then(function (data) {
				if (data.success && data.html) {
					contentEl.innerHTML = data.html;
					var root = contentEl.querySelector('.js-post-edit-form-root');
					if (root && typeof window.initPostEditForm === 'function') {
						window.initPostEditForm(root);
					}
				} else {
					contentEl.innerHTML =
						'<div class="alert alert-danger mb-0">' +
						(data.msg ? String(data.msg) : 'Không thể tải form chỉnh sửa.') +
						'</div>';
				}
			})
			.catch(function () {
				contentEl.innerHTML = '<div class="alert alert-danger mb-0">Lỗi kết nối.</div>';
			});
	}

	document.addEventListener('DOMContentLoaded', function () {
		var modalEl = document.getElementById('postEditModal');
		var contentEl = document.getElementById('postEditModalContent');
		if (!modalEl || !contentEl) return;

		var bsModal = new bootstrap.Modal(modalEl);

		document.addEventListener(
			'click',
			function (e) {
				var trigger = e.target.closest('.js-open-post-edit');
				if (!trigger) return;
				e.preventDefault();
				e.stopPropagation();

				var postId = trigger.getAttribute('data-post-id');
				if (!postId) return;

				var detailModal = document.getElementById('postDetailModal');
				var dInst = detailModal ? bootstrap.Modal.getInstance(detailModal) : null;
				if (dInst && detailModal.classList.contains('show')) {
					detailModal.addEventListener(
						'hidden.bs.modal',
						function onDetailClosed() {
							detailModal.removeEventListener('hidden.bs.modal', onDetailClosed);
							loadEditFormIntoModal(contentEl, bsModal, postId);
						}
					);
					dInst.hide();
					return;
				}

				loadEditFormIntoModal(contentEl, bsModal, postId);
			},
			true
		);

		modalEl.addEventListener('hidden.bs.modal', function () {
			contentEl.innerHTML = '';
			window.__editSelectedFiles = [];
		});
	});
})();
