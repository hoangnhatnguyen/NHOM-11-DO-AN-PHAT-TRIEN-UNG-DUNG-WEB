/**
 * Đăng bài qua fetch + FormData — giống form sửa bài, đảm bảo file gán bằng DataTransfer được gửi lên server.
 * Submit HTML thuần có thể bỏ qua file đã gán programmatically trên một số trình duyệt.
 */
(function () {
	'use strict';

	function submitTrigger(form) {
		return (
			form.querySelector('#feedComposerSubmit') ||
			form.querySelector('#btnSubmit') ||
			form.querySelector('button[type="submit"]')
		);
	}

	function bindPostStoreForm(form) {
		if (!form) return;
		var action = form.getAttribute('action') || '';
		if (action.indexOf('/post/store') === -1) return;

		form.addEventListener(
			'submit',
			async function (evt) {
				evt.preventDefault();

				if (form.id === 'feedComposerForm' && typeof window.syncFeedComposerFilesToInput === 'function') {
					window.syncFeedComposerFilesToInput();
				}
				if (form.id === 'createPostForm' && typeof window.syncCreatePostFilesToInput === 'function') {
					window.syncCreatePostFilesToInput();
				}

				var btn = submitTrigger(form);
				var prevText = btn ? btn.textContent : '';
				var prevDisabled = btn ? btn.disabled : false;
				if (btn) {
					btn.disabled = true;
					if (btn.id === 'feedComposerSubmit' || btn.id === 'btnSubmit') {
						btn.textContent = 'Đang đăng...';
					}
				}

				try {
					var res = await fetch(form.action, {
						method: 'POST',
						body: new FormData(form),
						credentials: 'same-origin',
						headers: {
							'X-Requested-With': 'XMLHttpRequest',
							Accept: 'application/json',
						},
					});

					var data = null;
					var ct = (res.headers.get('content-type') || '').toLowerCase();
					if (ct.indexOf('application/json') !== -1) {
						try {
							data = await res.json();
						} catch (_) {
							data = null;
						}
					} else {
						var text = await res.text();
						try {
							data = JSON.parse(text);
						} catch (_) {
							data = null;
						}
					}

					var msg = 'Không thể đăng bài. Vui lòng thử lại.';
					if (data && data.msg === 'empty') {
						msg = 'Bạn cần nhập nội dung hoặc chọn ít nhất 1 ảnh trước khi đăng.';
					} else if (data && data.msg === 'csrf_invalid') {
						msg = 'Hết hạn bảo mật. Tải lại trang rồi thử lại.';
					}

					if (!res.ok || !data || !data.ok) {
						window.alert(msg);
						return;
					}

					window.location.reload();
				} catch (e) {
					window.alert('Lỗi kết nối khi đăng bài.');
				} finally {
					if (btn) {
						btn.disabled = prevDisabled;
						btn.textContent = prevText;
					}
				}
			},
			false
		);
	}

	document.addEventListener('DOMContentLoaded', function () {
		bindPostStoreForm(document.getElementById('feedComposerForm'));
		bindPostStoreForm(document.getElementById('createPostForm'));
	});
})();
