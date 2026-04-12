/**
 * Post Detail Modal Handler
 * Opens post detail in a modal instead of navigating to separate page
 */

document.addEventListener('DOMContentLoaded', function() {
	const baseUrl = window.__APP_BASE__ || '/';
	const modal = document.getElementById('postDetailModal');
	const modalContent = document.getElementById('postDetailContent');
	
	if (!modal) return;

	const bsModal = new bootstrap.Modal(modal);
	let lastFocusedElement = null;

	/**
	 * Open post detail in modal
	 */
	function openPostDetail(postId) {
		// Show loading state
		modalContent.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>';

		// Fetch post detail
		fetch(`${baseUrl}api/post-detail.php?id=${postId}`)
			.then(res => res.json())
			.then(data => {
				if (data.success && data.html) {
					modalContent.innerHTML = data.html;
					modalContent.setAttribute('data-modal-post-id', String(postId));

					// Fix form action URLs - remove /api prefix if present
					const forms = modalContent.querySelectorAll('form');
					forms.forEach(form => {
						let action = form.getAttribute('action');
						if (action && action.includes('/api/')) {
							action = action.replace(/\/api\//, '/');
							form.setAttribute('action', action);
						}
					});
					
					// Show modal
					bsModal.show();
					
					// Reinitialize AJAX handlers for post actions (like, save, share)
					// Comment handlers use event delegation on document, so they work automatically
					if (window.initAjaxHandlers && typeof window.initAjaxHandlers === 'function') {
						window.initAjaxHandlers();
					}
				} else {
					modalContent.innerHTML = '<div class="alert alert-danger">Không thể tải bài viết</div>';
					bsModal.show();
				}
			})
			.catch(err => {
				modalContent.innerHTML = '<div class="alert alert-danger">Lỗi tải dữ liệu</div>';
				bsModal.show();
			});
	}

	function extractPostIdFromHref(href) {
		if (!href) return null;
		try {
			const u = new URL(href, window.location.origin);
			const m = u.pathname.match(/\/post\/(\d+)\/?$/);
			return m ? m[1] : null;
		} catch (err) {
			return null;
		}
	}

	/**
	 * Handle comment button/link clicks to open modal
	 * Only open modal when clicking the comment button
	 */
	document.addEventListener('click', function(e) {
		const commentBtn = e.target.closest('.js-comment-btn, .comment-btn');
		if (!commentBtn) return;

		e.preventDefault();
		e.stopPropagation();
		
		const postCard = commentBtn.closest('.js-post-card, [data-post-id]');
		const postId = postCard?.dataset.postId || postCard?.getAttribute('data-post-id');
		if (postId) {
			openPostDetail(postId);
		}
	}, true);

	document.addEventListener('click', function(e) {
		const mediaTrigger = e.target.closest('.js-open-post-modal-media');
		if (!mediaTrigger) return;

		e.preventDefault();
		e.stopPropagation();

		const postCard = mediaTrigger.closest('.js-post-card, [data-post-id]');
		const postId = postCard?.dataset.postId || postCard?.getAttribute('data-post-id');
		if (postId) {
			openPostDetail(postId);
		}
	}, true);

	/**
	 * Handle direct links to /post/{id} and explicit modal links
	 */
	document.addEventListener('click', function(e) {
		const link = e.target.closest('a.js-open-post-modal, a[href*="/post/"]');
		if (!link) return;
		if (e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;

		const postId = link.dataset.postId || extractPostIdFromHref(link.getAttribute('href'));
		if (!postId) return;

		e.preventDefault();
		openPostDetail(postId);
	});

	/**
	 * Close modal on escape or outside click
	 */
	modal.addEventListener('hidden.bs.modal', function() {
		modalContent.innerHTML = '';
		modalContent.removeAttribute('data-modal-post-id');
	});

	// Expose function globally for other scripts
	window.openPostDetail = openPostDetail;
});