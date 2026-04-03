/**
 * AJAX Comment System
 * - Submit top-level comments without page reload
 * - Submit nested replies without page reload
 */

(function () {
	// Helper to escape HTML
	function escapeHtml(text) {
		const map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return text.replace(/[&<>"']/g, m => map[m]);
	}

	// Helper to format content: escape HTML and convert @mention to links
	function formatCommentContent(text) {
		const escaped = escapeHtml(text);
		// Convert @username to mention links
		const baseUrl = window.location.origin;
		const formatted = escaped.replace(
			/@([a-zA-Z0-9_.]+)/g,
			'<a class="text-primary fw-semibold text-decoration-none mention-profile-link position-relative" style="z-index:3" href="/profile?u=$1">@$1</a>'
		);
		return formatted;
	}

	// Helper to generate avatar colors based on username
	function getAvatarColors(username) {
		const colors = [
			{ bg: '#FF6B6B', fg: '#FFFFFF' },
			{ bg: '#4ECDC4', fg: '#FFFFFF' },
			{ bg: '#45B7D1', fg: '#FFFFFF' },
			{ bg: '#FFA07A', fg: '#FFFFFF' },
			{ bg: '#98D8C8', fg: '#FFFFFF' },
			{ bg: '#F7DC6F', fg: '#000000' },
			{ bg: '#BB8FCE', fg: '#FFFFFF' },
			{ bg: '#85C1E2', fg: '#FFFFFF' }
		];
		const hash = username.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0);
		return colors[hash % colors.length];
	}

	function incrementCommentCount(postId, delta) {
		if (!postId) return;
		const nDelta = Number(delta || 0);
		if (!Number.isFinite(nDelta) || nDelta === 0) return;

		document.querySelectorAll('#comment-count-' + postId).forEach((el) => {
			const current = parseInt((el.textContent || '0').trim(), 10);
			const safeCurrent = Number.isFinite(current) ? current : 0;
			el.textContent = String(Math.max(0, safeCurrent + nDelta));
		});
	}

	/**
	 * Handle top-level comment form submission
	 */
	document.addEventListener('submit', function (e) {
		const form = e.target;
		
		// Check if it's a comment form (not reply)
		if (!form.id || form.id !== 'comment-box') {
			return;
		}

		e.preventDefault();

		const postId = form.action.match(/\/post\/(\d+)/)?.[1];
		const content = form.querySelector('input[name="content"]')?.value?.trim() || '';
		const csrf = form.querySelector('input[name="_csrf"]')?.value || '';

		if (!content) {
			alert('Vui lòng viết bình luận');
			return;
		}

		if (!postId) {
			return;
		}

		// Disable submit button
		const submitBtn = form.querySelector('button[type="submit"]');
		const originalText = submitBtn?.textContent;
		if (submitBtn) {
			submitBtn.disabled = true;
			submitBtn.textContent = 'Đang gửi...';
		}

		// Send via AJAX
		fetch(form.action, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
				'X-Requested-With': 'XMLHttpRequest'
			},
			body: new URLSearchParams({
				_csrf: csrf,
				content: content
			}),
			credentials: 'same-origin'
		})
			.then(res => res.text())
			.then(text => {
				try {
					return JSON.parse(text);
				} catch (err) {
					// Fallback to reload
					window.location.reload();
					return null;
				}
			})
			.then(data => {
				if (!data) {
					window.location.reload();
					return;
				}

				if (data.ok || data.status === 'success') {
					// Success - reset form
					form.reset();
					incrementCommentCount(postId, 1);

					// Create new comment element with proper tree structure
					const commentId = data.comment_id || Date.now();
					const username = document.querySelector('.currentUserName')?.textContent || 'Bạn';
					const avatarUrl = document.querySelector('.currentUserAvatar')?.getAttribute('data-avatar') || '';
					const colors = getAvatarColors(username);
					const csrf = form.querySelector('input[name="_csrf"]')?.value || '';
					
					// Top-level comment is level 1, no indent
					const newCommentHtml = `
						<div class="comment-node mb-2" id="comment-${commentId}" style="margin-left: 0px;" data-level="1">
							<div class="d-flex gap-2">
								<div class="flex-shrink-0">
									<a href="#" class="text-decoration-none">
										${avatarUrl ? `<img src="${avatarUrl}" class="rounded-circle" width="36" height="36" style="object-fit: cover; flex-shrink: 0;" alt="Avatar" loading="lazy" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">` : ''}
										<div class="avatar-sm" style="background: ${colors.bg}; color: ${colors.fg}; width: 36px; height: 36px; font-size: 14px; display: ${avatarUrl ? 'none' : 'flex'}; align-items: center; justify-content: center; border-radius: 50%; font-weight: bold;">${username.charAt(0).toUpperCase()}</div>
									</a>
								</div>
								<div class="flex-grow-1 min-width-0">
									<div class="border rounded-3 p-2 bg-light comment-bubble">
										<a href="#" class="small fw-semibold text-decoration-none text-dark">${escapeHtml(username)}</a>
										<div class="small text-break">${formatCommentContent(content)}</div>
									</div>
									<div class="d-flex align-items-center gap-2 mt-1 ms-1 small flex-wrap">
										<button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-secondary toggle-reply-form-btn" data-post-id="${postId}" data-comment-id="${commentId}" data-comment-author="${escapeHtml(username)}" data-csrf="${csrf}" data-level="1" style="font-size: 12px; cursor: pointer;">Trả lời</button>
										<span class="text-secondary" style="font-size: 12px;">vừa xong</span>
									</div>
								</div>
							</div>
						</div>
						<div id="replies-${commentId}" class="mt-2 d-none"></div>
					`;

					// Find comments container - traverse through siblings to find div.mt-2
					let commentsContainer = form.nextElementSibling;
					while (commentsContainer && !commentsContainer.classList.contains('mt-2')) {
						commentsContainer = commentsContainer.nextElementSibling;
					}
					
					if (commentsContainer) {
						commentsContainer.insertAdjacentHTML('afterbegin', newCommentHtml);
					} else {
						window.location.reload();
					}
				} else {
					alert('Lỗi: ' + (data.error || data.status || 'Không thể gửi'));
				}
			})
			.catch(err => {
				alert('Lỗi gửi bình luận');
			})
			.finally(() => {
				if (submitBtn) {
					submitBtn.disabled = false;
					submitBtn.textContent = originalText;
				}
			});
	});

	/**
	 * Handle reply form submission (nested comments)
	 */
	document.addEventListener('submit', function (e) {
		const form = e.target;

		// Check if it's a reply form
		if (!form.id || !form.id.startsWith('reply-form-')) {
			return;
		}

		e.preventDefault();

		const content = form.querySelector('input[name="content"]')?.value?.trim() || '';
		const csrf = form.querySelector('input[name="_csrf"]')?.value || '';

		if (!content) {
			alert('Vui lòng viết trả lời');
			return;
		}

		// Disable submit button
		const submitBtn = form.querySelector('button[type="submit"]');
		const originalText = submitBtn?.textContent;
		if (submitBtn) {
			submitBtn.disabled = true;
			submitBtn.textContent = 'Đang gửi...';
		}

		// Send via AJAX
		fetch(form.action, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
				'X-Requested-With': 'XMLHttpRequest'
			},
			body: new URLSearchParams({
				_csrf: csrf,
				content: content
			}),
			credentials: 'same-origin'
		})
			.then(res => res.text())
			.then(text => {
				try {
					return JSON.parse(text);
				} catch {
					// Fallback to reload
					window.location.reload();
					return null;
				}
			})
			.then(data => {
				if (!data) {
					window.location.reload();
					return;
				}

				if (data.ok || data.status === 'success') {
					// Success - reset and close form
					form.reset();
					form.classList.add('d-none');

					// Extract parent comment ID from form ID
					const parentCommentId = form.id.replace('reply-form-', '');
					const replyId = data.comment_id || data.reply_id || Date.now();
					const username = document.querySelector('.currentUserName')?.textContent || 'Bạn';
					const avatarUrl = document.querySelector('.currentUserAvatar')?.getAttribute('data-avatar') || '';
					const colors = getAvatarColors(username);
					const postId = form.action.match(/\/post\/(\d+)/)?.[1] || 0;
					incrementCommentCount(postId, 1);
					const csrf = form.querySelector('input[name="_csrf"]')?.value || '';
					
					// Get parent node to calculate correct indent
					const parentNode = document.getElementById('comment-' + parentCommentId);
					const parentLevel = parentNode ? parseInt(parentNode.getAttribute('data-level') || '1', 10) : 1;
					
					// Facebook-style: level 2 replies are indented 14px
					const nextLevel = 2;
					const nextIndent = 14;
					
					// New reply - Facebook style
					const newReplyHtml = `
						<div class="comment-node mb-2" id="comment-${replyId}" style="margin-left: ${nextIndent}px;" data-level="${nextLevel}">
							<div class="d-flex gap-2">
								<div class="flex-shrink-0">
									<a href="#" class="text-decoration-none">
										${avatarUrl ? `<img src="${avatarUrl}" class="rounded-circle" width="36" height="36" style="object-fit: cover; flex-shrink: 0;" alt="Avatar" loading="lazy" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">` : ''}
										<div class="avatar-sm" style="background: ${colors.bg}; color: ${colors.fg}; width: 36px; height: 36px; font-size: 14px; display: ${avatarUrl ? 'none' : 'flex'}; align-items: center; justify-content: center; border-radius: 50%; font-weight: bold;">${username.charAt(0).toUpperCase()}</div>
									</a>
								</div>
								<div class="flex-grow-1 min-width-0">
									<div class="border rounded-3 p-2 bg-light comment-bubble">
										<a href="#" class="small fw-semibold text-decoration-none text-dark">${escapeHtml(username)}</a>
										<div class="small text-break">${formatCommentContent(content)}</div>
									</div>
									<div class="d-flex align-items-center gap-2 mt-1 ms-1 small flex-wrap">
										<button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-secondary toggle-reply-form-btn" data-post-id="${postId}" data-comment-id="${replyId}" data-comment-author="${escapeHtml(username)}" data-csrf="${csrf}" data-level="2" style="font-size: 12px; cursor: pointer;">Trả lời</button>
										<span class="text-secondary" style="font-size: 12px;">vừa xong</span>
									</div>
								</div>
							</div>
						</div>
						<div id="replies-${replyId}" class="mt-2 d-none"></div>
					`;

					// Find or create replies container for this parent comment
					const repliesContainerId = `replies-${parentCommentId}`;
					let repliesContainer = document.getElementById(repliesContainerId);
					
					if (!repliesContainer) {
						// Container doesn't exist, create it after parent comment node
						const parentCommentEl = document.getElementById('comment-' + parentCommentId);
						if (parentCommentEl) {
							// Check if there's a form right after the comment node (recently created)
							let insertAfterEl = parentCommentEl;
							const nextEl = parentCommentEl.nextElementSibling;
							if (nextEl && nextEl.id && nextEl.id.startsWith('reply-form-')) {
								// There's a reply form, insert container after it
								insertAfterEl = nextEl;
							}
							
							const containerHtml = `<div id="${repliesContainerId}" class="mt-2"></div>`;
							insertAfterEl.insertAdjacentHTML('afterend', containerHtml);
							repliesContainer = document.getElementById(repliesContainerId);

						}
					}
					
					if (repliesContainer) {
						// Remove d-none class from container if it has it
						repliesContainer.classList.remove('d-none');
						// Append reply to container
						repliesContainer.insertAdjacentHTML('beforeend', newReplyHtml);
				} else {
					// Could not find or create replies container
					}
				} else if (data.status === 'error' || !data.ok) {
					const errorMsg = data.error || 'Không thể gửi trả lời';
					alert('Lỗi: ' + errorMsg);
				} else {
					alert('Lỗi: ' + (data.error || data.status || 'Không thể gửi'));
				}
			})
			.catch(err => {
				alert('Lỗi gửi trả lời: ' + err.message);
			})
			.finally(() => {
				if (submitBtn) {
					submitBtn.disabled = false;
					submitBtn.textContent = originalText;
				}
			});
	});

	/**
	 * Toggle reply form visibility - CREATE FORM DYNAMICALLY with @mention
	 */
	document.addEventListener('click', function (e) {
		const btn = e.target.closest('.toggle-reply-form-btn');
		if (!btn) {
			return;
		}

		e.preventDefault();

		const postId = btn.getAttribute('data-post-id');
		const commentId = btn.getAttribute('data-comment-id');
		const commentAuthor = btn.getAttribute('data-comment-author');
		const csrf = btn.getAttribute('data-csrf');
		const level = parseInt(btn.getAttribute('data-level') || '1', 10);
		const formId = 'reply-form-' + commentId;

		// Check if form already exists
		let form = document.getElementById(formId);
		if (form) {
			// Toggle existing form
			const isHidden = form.classList.contains('d-none');
			form.classList.toggle('d-none');
			if (isHidden) {
				form.querySelector('input[name="content"]')?.focus();
			}
			return;
		}

		// Create form dynamically with @mention placeholder
		const mentionText = commentAuthor ? `@${commentAuthor}` : '';
		const formHtml = `
			<form
				method="POST"
				action="/post/${postId}/comment/${commentId}/reply"
				class="mb-2 reply-form"
				id="${formId}"
			>
				<input type="hidden" name="_csrf" value="${csrf}">
				<div class="input-group input-group-sm rounded-4 p-1 bg-light ms-3">
					<input type="text" name="content" class="form-control border-0 bg-light" placeholder="Trả lời ${mentionText}..." value="${mentionText} " required>
					<button type="submit" class="btn btn-outline-secondary rounded-pill">
						Gửi
					</button>
				</div>
			</form>
		`;

		// Find the parent comment node (.comment-node)
		const commentNode = btn.closest('.comment-node');
		if (commentNode) {
			// Insert form after the comment node
			commentNode.insertAdjacentHTML('afterend', formHtml);
		} else {
			// Fallback: insert after button's parent if comment-node not found
			console.warn('Comment node not found, using fallback insertion');
			btn.parentElement.insertAdjacentHTML('afterend', formHtml);
		}
		
		form = document.getElementById(formId);
		const inputField = form?.querySelector('input[name="content"]');
		if (inputField) {
			inputField.focus();
			// Move cursor to end of text
			inputField.setSelectionRange(inputField.value.length, inputField.value.length);
		}
	});

	/**
	 * Toggle nested replies visibility
	 */
	document.addEventListener('click', function (e) {
		if (!e.target.classList.contains('toggle-replies-btn')) {
			return;
		}

		e.preventDefault();

		const targetId = e.target.dataset.target;
		if (!targetId) return;

		const repliesContainer = document.querySelector(targetId);
		if (!repliesContainer) return;

		const isHidden = repliesContainer.classList.contains('d-none');
		repliesContainer.classList.toggle('d-none');

		// Update button text
		const showText = e.target.dataset.showText || 'Xem câu trả lời';
		const hideText = e.target.dataset.hideText || 'Ẩn câu trả lời';
		e.target.textContent = isHidden ? hideText : showText;
	});

	/**
	 * Handle mention link clicks - auto-mention in reply form
	 */
	document.addEventListener('click', function (e) {
		const mentionLink = e.target.closest('.mention-profile-link');
		if (!mentionLink) {
			return;
		}

		// Don't navigate to profile
		e.preventDefault();

		// Extract username from link text (format: @username)
		const mentionText = mentionLink.textContent.trim();
		if (!mentionText.startsWith('@')) {
			return;
		}
		const username = mentionText.substring(1);

		// Find parent comment node
		const commentNode = mentionLink.closest('.comment-node');
		if (!commentNode) {
			console.warn('Could not find parent comment node');
			return;
		}

		const commentId = commentNode.id.replace('comment-', '');
		if (!commentId) {
			return;
		}

		// Trigger reply button if it exists
		const replyBtn = commentNode.querySelector('.toggle-reply-form-btn');
		if (replyBtn) {
			replyBtn.click();
		}

		// Get the reply form (should be created after click)
		setTimeout(() => {
			const formId = 'reply-form-' + commentId;
			const form = document.getElementById(formId);
			if (form) {
				const inputField = form.querySelector('input[name="content"]');
				if (inputField) {
					// Preserve existing mention if present, or add new one
					const currentValue = inputField.value.trim();
					const mentionPrefix = '@' + username + ' ';
					
					if (!currentValue.includes('@' + username)) {
						// Add mention to existing content
						inputField.value = mentionPrefix + currentValue;
					}
					
					inputField.focus();
					inputField.setSelectionRange(inputField.value.length, inputField.value.length);
				}
			}
		}, 50);
	});
	
	// Expose function for modal context (event delegation handles all comment interactions)
	window.reinitializeCommentHandlers = function() {
		// No re-initialization needed - all handlers use event delegation on document
	};
})();
