/**
 * Khởi tạo form chỉnh sửa bài (trang /post/edit/ hoặc modal).
 * Gọi window.initPostEditForm(rootElement) sau khi inject HTML vào modal.
 */
(function () {
	'use strict';

	window.postEditFormAutoResize = function (el) {
		el.style.height = 'auto';
		el.style.height = el.scrollHeight + 'px';
	};

	window.postEditSetPrivacy = function (value, icon, label) {
		var input = document.getElementById('editprivacyInput');
		var ic = document.getElementById('editprivacyIcon');
		var lb = document.getElementById('editprivacyLabel');
		if (input) input.value = value;
		if (ic) ic.className = 'bi ' + icon;
		if (lb) lb.innerText = label;
	};

	function initPrivacyFromValue() {
		var input = document.getElementById('editprivacyInput');
		if (!input) return;
		var value = input.value;
		if (value === 'followers') {
			window.postEditSetPrivacy('followers', 'bi-people', 'Người theo dõi');
		} else if (value === 'private') {
			window.postEditSetPrivacy('private', 'bi-lock', 'Chỉ mình tôi');
		} else {
			window.postEditSetPrivacy('public', 'bi-globe2', 'Công khai');
		}
	}

	window.postEditRemoveMedia = function (mediaId) {
		var mediaItem = document.getElementById('media-item-' + mediaId);
		if (!mediaItem) return;
		var form = mediaItem.closest('form');
		if (form) {
			var hidden = document.createElement('input');
			hidden.type = 'hidden';
			hidden.name = 'remove_media_ids[]';
			hidden.value = String(mediaId);
			form.appendChild(hidden);
		}
		mediaItem.remove();
	};

	function removeNewMediaAt(index) {
		var fileInput = document.getElementById('editfileInput');
		if (!fileInput) return;
		if (!Array.isArray(window.__editSelectedFiles)) {
			window.__editSelectedFiles = [];
		}
		window.__editSelectedFiles = window.__editSelectedFiles.filter(function (_, i) {
			return i !== index;
		});
		syncEditFileInputFromBuffer();
		renderNewMediaPreview();
	}

	function syncEditFileInputFromBuffer() {
		var fileInput = document.getElementById('editfileInput');
		if (!fileInput) return;
		try {
			var dt = new DataTransfer();
			(window.__editSelectedFiles || []).forEach(function (file) {
				dt.items.add(file);
			});
			fileInput.files = dt.files;
		} catch (e) {}
	}

	function renderNewMediaPreview() {
		var fileInput = document.getElementById('editfileInput');
		var previewContainer = document.getElementById('newPreviewContainer');
		var previewGrid = document.getElementById('newPreviewGrid');
		if (!previewContainer || !previewGrid) return;

		previewGrid.innerHTML = '';
		var files = Array.isArray(window.__editSelectedFiles) ? window.__editSelectedFiles : [];
		if (!files.length) {
			previewContainer.classList.add('d-none');
			return;
		}

		files.forEach(function (file, index) {
			var col = document.createElement('div');
			col.className = 'col-6 col-md-4';

			var wrapper = document.createElement('div');
			wrapper.className = 'preview-wrapper position-relative';

			if (file.type.startsWith('video/')) {
				var video = document.createElement('video');
				video.className = 'w-100 rounded-4';
				video.controls = true;
				video.playsInline = true;
				video.src = URL.createObjectURL(file);
				wrapper.appendChild(video);
			} else {
				var img = document.createElement('img');
				img.className = 'img-fluid rounded-4 w-100 post-media-tile';
				img.alt = '';
				img.src = URL.createObjectURL(file);
				wrapper.appendChild(img);
			}

			var removeBtn = document.createElement('button');
			removeBtn.type = 'button';
			removeBtn.className = 'bi bi-x text-white preview-remove';
			removeBtn.setAttribute('aria-label', 'Xóa preview');
			removeBtn.addEventListener('click', function () {
				removeNewMediaAt(index);
			});
			wrapper.appendChild(removeBtn);

			col.appendChild(wrapper);
			previewGrid.appendChild(col);
		});

		previewContainer.classList.remove('d-none');
	}

	/**
	 * Form HTML từ api/post-edit-form.php có thể có action sai (BASE_URL tính theo SCRIPT_NAME trong /api).
	 * Trang luôn có window.__APP_BASE__ đúng — ghi đè action trước khi fetch.
	 */
	function syncEditFormActionWithAppBase(form) {
		if (!form) return;
		var appBase = typeof window.__APP_BASE__ === 'string' ? window.__APP_BASE__.replace(/\/$/, '') : '';
		var pid = form.getAttribute('data-post-id');
		if (!pid) {
			var am = String(form.getAttribute('action') || '').match(/\/post\/update\/(\d+)/);
			if (am) pid = am[1];
		}
		if (!pid) return;
		var path = (appBase ? appBase : '') + '/post/update/' + pid;
		form.setAttribute('action', path);
	}

	/**
	 * Sau khi lưu: cập nhật thẻ bài trên trang, làm mới modal chi tiết nếu đang mở, đóng modal sửa.
	 * Trang /post/{id} hoặc form sửa trang riêng: dùng reload hoặc chuyển trang.
	 */
	function afterPostEditSaved(editForm, data) {
		var postId = data && data.postId;
		if (!postId) return;

		var appBase = typeof window.__APP_BASE__ === 'string' ? window.__APP_BASE__.replace(/\/$/, '') : '';
		var inEditModal = editForm.closest && editForm.closest('#postEditModal');

		var pathMatch = window.location.pathname.match(/\/post\/(\d+)\/?$/);
		if (pathMatch && pathMatch[1] === String(postId)) {
			window.location.reload();
			return;
		}

		if (!inEditModal) {
			window.location.href = (appBase ? appBase : '') + '/post/' + postId;
			return;
		}

		var cardUrl = (appBase ? appBase : '') + '/api/post-card.php?id=' + encodeURIComponent(postId);
		fetch(cardUrl, {
			credentials: 'same-origin',
			headers: {
				'X-Requested-With': 'XMLHttpRequest',
				Accept: 'application/json',
			},
		})
			.then(function (r) {
				return r.json();
			})
			.then(function (cardData) {
				if (cardData && cardData.success && cardData.html) {
					var wrap = document.createElement('div');
					wrap.innerHTML = cardData.html.trim();
					var tpl = wrap.querySelector('.js-post-card');
					if (tpl) {
						document.querySelectorAll('.js-post-card[data-post-id="' + postId + '"]').forEach(function (old) {
							old.replaceWith(tpl.cloneNode(true));
						});
					}
				}
				var dc = document.getElementById('postDetailContent');
				if (
					dc &&
					dc.getAttribute('data-modal-post-id') === String(postId) &&
					typeof window.openPostDetail === 'function'
				) {
					window.openPostDetail(String(postId));
				}
				var em = document.getElementById('postEditModal');
				if (em && window.bootstrap) {
					var inst = bootstrap.Modal.getInstance(em);
					if (inst) inst.hide();
				}
			})
			.catch(function () {
				var em = document.getElementById('postEditModal');
				if (em && window.bootstrap) {
					var inst = bootstrap.Modal.getInstance(em);
					if (inst) inst.hide();
				}
			});
	}

	function bindForm(root) {
		var editForm = root.querySelector('form.js-post-edit-form') || root.querySelector('form[action*="/post/update/"]');
		var saveMsg = document.getElementById('editSaveMsg');
		if (editForm) {
			syncEditFormActionWithAppBase(editForm);
			editForm.addEventListener('submit', async function (e) {
				e.preventDefault();
				var submitBtn = editForm.querySelector('button[type="submit"]');
				var oldText = submitBtn ? submitBtn.textContent : '';
				if (submitBtn) {
					submitBtn.disabled = true;
					submitBtn.textContent = 'Đang lưu...';
				}

				if (saveMsg) {
					saveMsg.className = 'alert py-2 mb-3 d-none';
					saveMsg.textContent = '';
				}

				try {
					syncEditFormActionWithAppBase(editForm);
					var res = await fetch(editForm.action, {
						method: 'POST',
						body: new FormData(editForm),
						credentials: 'same-origin',
						headers: {
							'X-Requested-With': 'XMLHttpRequest',
							Accept: 'application/json',
						},
					});

					var ct = (res.headers.get('content-type') || '').toLowerCase();
					var data = null;
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
						if (!data && text && text.trim().charAt(0) === '<') {
							if (saveMsg) {
								saveMsg.className = 'alert alert-warning py-2 mb-3';
								saveMsg.textContent =
									'Máy chủ trả về trang HTML thay vì JSON (có thể lỗi PHP hoặc chuyển hướng). Tải lại trang hoặc mở tab Mạng để xem chi tiết.';
							}
							return;
						}
					}

					function mapErr(payload) {
						var m = payload && payload.msg;
						if (m === 'empty') {
							return 'Bài viết phải có nội dung hoặc ít nhất 1 media.';
						}
						if (m === 'csrf_invalid') {
							return 'Hết hạn bảo mật. Tải lại trang rồi thử lưu lại.';
						}
						if (m === 'auth_required') {
							return 'Bạn cần đăng nhập lại để lưu bài.';
						}
						if (m === 'forbidden') {
							return 'Bạn không có quyền sửa bài này.';
						}
						if (m === 'not_found') {
							return 'Không tìm thấy bài viết.';
						}
						if (m === 'server_error') {
							return payload && payload.error
								? 'Lỗi máy chủ khi lưu: ' + payload.error
								: 'Lỗi máy chủ khi lưu. Vui lòng thử lại.';
						}
						return 'Không thể lưu chỉnh sửa. Vui lòng thử lại.';
					}

					if (!res.ok || !data || !data.ok) {
						var msg = mapErr(data);
						if (!data && !res.ok) {
							msg = 'Lỗi máy chủ (' + res.status + '). Vui lòng thử lại.';
						}
						if (saveMsg) {
							saveMsg.className = 'alert alert-warning py-2 mb-3';
							saveMsg.textContent = msg;
						}
						return;
					}

					if (saveMsg) {
						saveMsg.className = 'alert alert-success py-2 mb-3';
						saveMsg.textContent = 'Đã lưu chỉnh sửa bài viết.';
					}
					afterPostEditSaved(editForm, data);
				} catch (err) {
					if (saveMsg) {
						saveMsg.className = 'alert alert-danger py-2 mb-3';
						saveMsg.textContent = 'Lỗi kết nối khi lưu bài viết.';
					}
				} finally {
					if (submitBtn) {
						submitBtn.disabled = false;
						submitBtn.textContent = oldText;
					}
				}
			});
		}

		var fileInput = document.getElementById('editfileInput');
		if (fileInput) {
			window.__editSelectedFiles = [];
			fileInput.addEventListener('change', function () {
				var incoming = Array.from(fileInput.files || []);
				if (!incoming.length) return;
				if (!Array.isArray(window.__editSelectedFiles)) {
					window.__editSelectedFiles = [];
				}
				incoming.forEach(function (f) {
					window.__editSelectedFiles.push(f);
				});
				syncEditFileInputFromBuffer();
				renderNewMediaPreview();
			});
		}
	}

	window.initPostEditForm = function (root) {
		if (!root) return;
		initPrivacyFromValue();
		var textarea = root.querySelector("textarea[name='content']");
		if (textarea) window.postEditFormAutoResize(textarea);
		bindForm(root);
	};

	document.addEventListener('DOMContentLoaded', function () {
		var pageRoot = document.querySelector('.js-post-edit-page-root');
		if (pageRoot) {
			window.initPostEditForm(pageRoot);
		}
	});
})();
