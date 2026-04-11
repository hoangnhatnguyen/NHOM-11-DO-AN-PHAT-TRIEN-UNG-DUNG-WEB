(function () {
	function ensureShareModal() {
		if (document.getElementById('shareLinkModal')) return;
		var modalHtml = '' +
			'<div class="modal fade" id="shareLinkModal" tabindex="-1" aria-hidden="true">' +
			'  <div class="modal-dialog modal-dialog-centered">' +
			'    <div class="modal-content">' +
			'      <div class="modal-header">' +
			'        <h5 class="modal-title">Chia sẻ bài viết</h5>' +
			'        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>' +
			'      </div>' +
			'      <div class="modal-body">' +
			'        <label class="form-label">Link bài viết</label>' +
			'        <div class="input-group">' +
			'          <input id="shareLinkInput" type="text" class="form-control" readonly>' +
			'          <button id="copyShareLinkBtn" class="btn btn-primary" type="button">Sao chép</button>' +
			'        </div>' +
			'        <div id="copyShareLinkMsg" class="small text-success mt-2 d-none">Đã sao chép liên kết.</div>' +
			'      </div>' +
			'    </div>' +
			'  </div>' +
			'</div>';
		document.body.insertAdjacentHTML('beforeend', modalHtml);
		document.getElementById('copyShareLinkBtn').addEventListener('click', function () {
			var input = document.getElementById('shareLinkInput');
			var msg = document.getElementById('copyShareLinkMsg');
			if (!input) return;
			input.select();
			input.setSelectionRange(0, 99999);
			navigator.clipboard.writeText(input.value).then(function () {
				if (msg) msg.classList.remove('d-none');
			});
		});
	}

	function openShareModal(url) {
		ensureShareModal();
		var input = document.getElementById('shareLinkInput');
		var msg = document.getElementById('copyShareLinkMsg');
		if (input) input.value = url;
		if (msg) msg.classList.add('d-none');
		if (window.bootstrap && window.bootstrap.Modal) {
			var modal = new window.bootstrap.Modal(document.getElementById('shareLinkModal'));
			modal.show();
		}
	}

	function removeClasses(el, classNames) {
		if (!el) return;
		classNames.forEach(function (c) {
			el.classList.remove(c);
		});
	}

	function updateAll(selector, updater, root) {
		var scope = root && root.querySelectorAll ? root : document;
		var targets = scope.querySelectorAll(selector);
		targets.forEach(function (el) {
			updater(el);
		});
	}
window.showCustomDeleteConfirm = function(event, formElement, postId) {
    event.preventDefault(); // Ngăn form tự reload trang

    // 1. Khởi tạo UI cho Modal nếu chưa có
    let deleteModal = document.getElementById('customDeleteModal');
    if (!deleteModal) {
        // Có thêm thẻ <div id="deleteModalError"> để chứa thông báo lỗi đẹp
        const modalHtml = `
        <div class="modal fade" id="customDeleteModal" tabindex="-1" style="z-index: 1060;">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content rounded-4 border-0 shadow">
                    <div class="modal-body text-center p-4">
                        <div class="mb-3 text-danger">
                            <i class="bi bi-exclamation-circle" style="font-size: 3rem;"></i>
                        </div>
                        <h5 class="mb-3 fw-bold">Xóa bài viết?</h5>
                        <p class="text-muted mb-3 small">Bạn có chắc chắn muốn xóa bài viết này không? Hành động này không thể khôi phục.</p>
                        
                        <!-- Nơi hiển thị lỗi chuẩn Bootstrap thay vì dùng Alert xấu xí -->
                        <div id="deleteModalError" class="alert alert-danger d-none small p-2 mb-3 text-start" role="alert"></div>

                        <div class="d-flex gap-2 justify-content-center">
                            <button type="button" class="btn btn-light rounded-pill px-4 fw-medium" data-bs-dismiss="modal">Hủy</button>
                            <button type="button" class="btn btn-danger rounded-pill px-4 fw-medium" id="confirmDeleteBtn">Xóa</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        deleteModal = document.getElementById('customDeleteModal');
    }

    // Hiển thị modal và ẩn thông báo lỗi cũ (nếu có)
    const bsModal = new bootstrap.Modal(deleteModal);
    const errorDiv = document.getElementById('deleteModalError');
    if (errorDiv) errorDiv.classList.add('d-none');
    bsModal.show();

    // Reset lại sự kiện click nút "Xóa"
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    const newConfirmBtn = confirmBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

   // 2. GỌI AJAX KHI BẤM XÁC NHẬN
    newConfirmBtn.onclick = function() {
        const originalText = this.innerHTML;
        this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Đang xóa...';
        this.disabled = true;

        // VÁ LỖI NEWSFEED: KHÔNG LẤY URL TỪ FORM NỮA MÀ TỰ GHÉP LINK CHUẨN 100%
        const url = '/post/' + postId + '/delete'; 
        
        const formData = new FormData(formElement);

        fetch(url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(async response => {
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error("Lỗi từ server:", text); // Dòng này in ra lỗi thật sự ở F12
                throw new Error('Máy chủ phản hồi sai định dạng.');
            }
            return response.json();
        })
        .then(data => {
            if (data.ok) {
                // Thành công
                bootstrap.Modal.getInstance(deleteModal).hide();

                const postDetailModal = document.getElementById('postDetailModal');
                if (postDetailModal && postDetailModal.classList.contains('show')) {
                    bootstrap.Modal.getInstance(postDetailModal).hide();
                }

                const feedCard = document.querySelector(`[data-post-id="${postId}"]`) 
                              || formElement.closest('.card') 
                              || formElement.closest('article');
                              
                if (feedCard) {
                    feedCard.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                    feedCard.style.opacity = '0';
                    feedCard.style.transform = 'scale(0.9)';
                    setTimeout(() => feedCard.remove(), 300);
                }

                if (window.location.pathname.includes('/post/')) {
                    setTimeout(() => { window.location.href = '/'; }, 500);
                }
            } else {
                showError(data.msg || 'Không thể xóa bài viết, vui lòng thử lại.');
            }
        })
        .catch(err => {
            console.error('AJAX Error:', err);
            showError('Lỗi kết nối máy chủ. Vui lòng thử lại!');
        });

        // Hàm hiện lỗi ngay trong popup
        function showError(msg) {
            newConfirmBtn.innerHTML = originalText;
            newConfirmBtn.disabled = false;
            const errorDiv = document.getElementById('deleteModalError');
            if (errorDiv) {
                errorDiv.innerHTML = `<i class="bi bi-info-circle-fill me-1"></i> ${msg}`;
                errorDiv.classList.remove('d-none');
            } else {
                alert(msg); // Dự phòng nếu không có thẻ errorDiv
            }
        }
    };
};
	document.addEventListener('submit', function (e) {
		var form = e.target;
		if (!form || !form.matches('form.ajax-post-like, form.ajax-post-save, form.ajax-post-share')) {
			return;
		}

		e.preventDefault();

		var action = form.getAttribute('action') || '';
		if (!action) return;

		var formData = new FormData(form);

		fetch(action, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin',
			headers: {
				'X-Requested-With': 'XMLHttpRequest'
			}
		})
			.then(function (res) {
				return res.text().then(function (text) {
					try {
						return JSON.parse(text);
					} catch (err) {
						return null;
					}
				});
			})
			.then(function (data) {
				if (data && data.msg === 'not login') {
					var loginU = new URL(action, window.location.href);
					if (/\/api\/like\.php$/i.test(loginU.pathname)) {
						loginU.pathname = loginU.pathname.replace(/\/api\/like\.php$/i, '/login');
					} else {
						loginU.pathname = loginU.pathname.replace(/\/post\/\d+\/(like|save|share)$/i, '/login');
					}
					window.location.href = loginU.pathname + loginU.search + loginU.hash;
					return;
				}
				if (!data || !data.ok) return;

				if (data.kind === 'like') {
					updateAll('#like-count-' + data.postId, function (countEl) {
						countEl.textContent = data.like_count;
						removeClasses(countEl, ['text-danger', 'text-secondary']);
						countEl.classList.add(data.is_liked ? 'text-danger' : 'text-secondary');
					});

					updateAll('#like-btn-' + data.postId, function (btnEl) {
						removeClasses(btnEl, ['text-danger', 'text-secondary']);
						btnEl.classList.add(data.is_liked ? 'text-danger' : 'text-secondary');
					});

					updateAll('#like-icon-' + data.postId, function (iconEl) {
						removeClasses(iconEl, ['bi-heart', 'bi-heart-fill']);
						iconEl.classList.add(data.is_liked ? 'bi-heart-fill' : 'bi-heart');
					});
				}

				if (data.kind === 'save') {
					updateAll('#save-count-' + data.postId, function (countElS) {
						countElS.textContent = data.save_count;
						removeClasses(countElS, ['text-warning', 'text-secondary']);
						countElS.classList.add(data.is_saved ? 'text-warning' : 'text-secondary');
					});

					updateAll('#save-btn-' + data.postId, function (btnElS) {
						removeClasses(btnElS, ['text-warning', 'text-secondary']);
						btnElS.classList.add(data.is_saved ? 'text-warning' : 'text-secondary');
					});

					updateAll('#save-icon-' + data.postId, function (iconElS) {
						removeClasses(iconElS, ['bi-bookmark', 'bi-bookmark-fill']);
						iconElS.classList.add(data.is_saved ? 'bi-bookmark-fill' : 'bi-bookmark');
					});
				}

				if (data.kind === 'share') {
					updateAll('#share-count-' + data.postId, function (countElSh) {
						countElSh.textContent = data.share_count;
					});
					var postUrl = form.getAttribute('data-post-url');
					if (postUrl) {
						openShareModal(new URL(postUrl, window.location.origin).toString());
					}
				}
			})
			.catch(function () {});
	});

	// Intentionally disable click-to-open on post card background.
	// Only explicit actions (comment button, media click, links/buttons/forms) should trigger behavior.
})();
