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

	function eventTargetElement(e) {
		var t = e.target;
		if (!t) return null;
		return t.nodeType === 1 ? t : t.parentElement;
	}

	document.addEventListener(
		'pointerdown',
		function (e) {
			var el = eventTargetElement(e);
			if (el && el.closest('.js-post-card-menu, .post-action-menu')) {
				window.__suppressPostCardNavUntil = Date.now() + 550;
			}
		},
		true
	);

	document.addEventListener('click', function (e) {
		if (typeof window.__suppressPostCardNavUntil === 'number' && Date.now() < window.__suppressPostCardNavUntil) {
			return;
		}
		var el = eventTargetElement(e);
		if (!el) return;
		var card = el.closest('.js-post-card');
		if (!card) return;

		if (el.closest('a.js-post-card-author, a.post-hashtag-link')) return;
		if (el.closest('.feed-post-actions, .dropdown, .js-post-card-menu, .post-action-menu, .dropdown-menu, button, input, textarea, select, label, form, video, .js-open-post-modal-media, .js-comment-btn')) return;

		var link = el.closest('a[href]');
		if (link && !link.classList.contains('post-hashtag-link') && !link.classList.contains('js-post-card-author')) return;

		var postId = card.getAttribute('data-post-id');
		if (!postId) return;
		e.preventDefault();
		e.stopPropagation();
		if (typeof window.openPostDetail === 'function') {
			window.openPostDetail(postId);
		} else {
			var dest = card.getAttribute('data-post-url');
			if (dest) window.location.href = dest;
		}
	});
// --- HÀM TẠO GIAO DIỆN MODAL XÓA (Chỉ tạo 1 lần) ---
function ensureCustomDeleteModal() {
    if (document.getElementById('customDeletePostModal')) return;

    // HTML của Modal giống hệt hình bạn gửi
    const modalHtml = `
        <div class="modal fade" id="customDeletePostModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content border-0 shadow-lg text-center p-4 rounded-4" style="max-width: 320px; margin: auto;">
                    <div class="modal-body p-0">
                        <div class="mb-3">
                            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#dc3545" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                        </div>
                        <h4 class="fw-bold mb-2">Xóa bài viết?</h4>
                        <p class="text-secondary mb-4 small">Bạn có chắc chắn muốn xóa bài viết này không? Hành động này không thể khôi phục.</p>
                        <div class="d-flex justify-content-center gap-2">
                            <button type="button" class="btn btn-light rounded-pill px-4 fw-semibold" data-bs-dismiss="modal">Hủy</button>
                            <button type="button" class="btn btn-danger rounded-pill px-4 fw-semibold" id="confirmRealDeleteBtn" style="background-color: #dc3445; border-color: #df737b;">
                                Xóa bài viết
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

// --- XỬ LÝ SỰ KIỆN CLICK NÚT XÓA ---
document.addEventListener('click', function (e) {
    const deleteBtn = e.target.closest('.post-card-delete-btn');
    if (!deleteBtn) return;

    e.preventDefault();
    e.stopPropagation();

    const actionUrl = deleteBtn.getAttribute('data-delete-action');
    const csrf = deleteBtn.getAttribute('data-delete-csrf');
    if (!actionUrl || !csrf) return;

    // 1. Tạo modal nếu chưa có và hiển thị nó lên
    ensureCustomDeleteModal();
    const modalEl = document.getElementById('customDeletePostModal');
    const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const confirmBtn = document.getElementById('confirmRealDeleteBtn');

    // Reset lại trạng thái nút (Phòng trường hợp trước đó xóa lỗi)
    confirmBtn.disabled = false;
    confirmBtn.innerHTML = 'Xóa bài viết';

    // Hiển thị modal lên màn hình
    bsModal.show();

    // 2. Gán sự kiện khi bấm nút Xóa bên trong Modal
    // Dùng .onclick thay vì addEventListener để đảm bảo mỗi lần bật Modal chỉ giữ 1 hành động duy nhất
    confirmBtn.onclick = async function () {
        
        // Đổi UI sang trạng thái "Đang xóa..."
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Đang xóa...';

        try {
            const formData = new URLSearchParams();
            formData.append('_csrf', csrf);

            const res = await fetch(actionUrl, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            const data = await res.json();
            
            if (data.ok) {
                const postId = data.postId;

                // Xóa trên Grid Profile
                const profileTile = document.querySelector(`.profile-post-tile[data-post-id="${postId}"]`);
                if (profileTile) {
                    profileTile.remove();
                    const countEl = document.getElementById('postsCountBtn');
                    if (countEl) {
                        const bTag = countEl.querySelector('b');
                        if (bTag) bTag.innerText = Math.max(0, (parseInt(bTag.innerText) || 0) - 1);
                    }
                }

                // Xóa Card ngoài News Feed
                document.querySelectorAll(`.js-post-card[data-post-id="${postId}"]`).forEach(card => card.remove());

                // Đóng Detail Modal (nếu có)
                const detailModal = document.getElementById('postDetailModal');
                if (detailModal && typeof bootstrap !== 'undefined') {
                    const inst = bootstrap.Modal.getInstance(detailModal);
                    if (inst) inst.hide();
                }

                // Cập nhật thông báo nếu hết bài
                const grid = document.querySelector('.profile-post-grid');
                if (grid && grid.children.length === 0) {
                    grid.parentElement.innerHTML = '<p class="text-muted mb-0">Chưa có bài viết nào</p>';
                }

                // Tắt Custom Modal báo thành công
                bsModal.hide();

            } else {
                alert('Lỗi xóa bài viết: ' + (data.msg || 'Không rõ lỗi.'));
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = 'Xóa bài viết';
            }
        } catch (err) {
            console.error(err);
            alert('Không thể kết nối máy chủ để xóa bài viết.');
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = 'Xóa bài viết';
        }
    };
});
})();
