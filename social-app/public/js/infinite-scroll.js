/**
 * Infinite scroll and load more posts functionality
 */

document.addEventListener('DOMContentLoaded', function() {
	const feedContainer = document.querySelector('.feed-post');
	if (!feedContainer) return;

	const loadingIndicator = document.querySelector('.feed-loading');
	const noMoreIndicator = document.querySelector('.feed-no-more');
	const mainColumn = document.querySelector('.feed-main-column');
	const useInternalFeedScroll = window.matchMedia('(min-width: 992px)').matches && !!mainColumn;
	const scrollContainer = useInternalFeedScroll ? mainColumn : window;
	const apiUrl =
		typeof window.__appUrl === 'function'
			? window.__appUrl('api/load_more_posts.php')
			: '/api/load_more_posts.php';
	const basePathForRender =
		typeof window.__appBasePath === 'function' ? window.__appBasePath() : '';

	let isLoading = false;
	let hasMore = feedContainer.dataset.hasMore === 'true';
	let currentOffset = parseInt(feedContainer.dataset.offset) || 5;
	const feedTab = feedContainer.dataset.feedTab || 'foryou';

	/**
	 * Load more posts via AJAX
	 */
	async function loadMorePosts() {
		if (isLoading || !hasMore) return;

		isLoading = true;
		if (loadingIndicator) {
			loadingIndicator.style.display = 'block';
		}

		try {
			// Step 1: Fetch posts JSON
			const response = await fetch(apiUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: `offset=${currentOffset}&tab=${encodeURIComponent(feedTab)}`
			});

			if (!response.ok) {
				throw new Error(`HTTP error! status: ${response.status}`);
			}

			const data = await response.json();

			if (!data.success || !data.posts || data.posts.length === 0) {

				hasMore = false;
				if (noMoreIndicator) {
					noMoreIndicator.style.display = 'block';
				}
				if (loadingIndicator) {
					loadingIndicator.style.display = 'none';
				}
				return;
			}

			// Step 2: Render posts HTML using the backend template
			const renderUrl =
				typeof window.__appUrl === 'function'
					? window.__appUrl('api/render_posts.php')
					: '/api/render_posts.php';
			const renderResponse = await fetch(renderUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: `posts_json=${encodeURIComponent(JSON.stringify(data.posts))}&base_url=${encodeURIComponent(basePathForRender)}`
			});

			if (!renderResponse.ok) {

			}

			const postsHtml = await renderResponse.text();
			
			if (!postsHtml || postsHtml.trim() === '') {
				hasMore = false;
				return;
			}

			// Step 3: Append rendered HTML to feed
			feedContainer.insertAdjacentHTML('beforeend', postsHtml);

			// Update offset and hasMore status
			currentOffset += data.count;
			hasMore = data.hasMore === true;
			feedContainer.dataset.offset = currentOffset;
			feedContainer.dataset.hasMore = hasMore ? 'true' : 'false';

			// Initialize handlers for new posts
			// AJAX forms use event delegation, so they should work automatically
			initializePostHandlers();

			if (!hasMore && noMoreIndicator) {
				noMoreIndicator.style.display = 'block';
			}
		} catch (error) {
			hasMore = false;
		} finally {
			isLoading = false;
			if (loadingIndicator) {
				loadingIndicator.style.display = 'none';
			}
		}
	}

	/**
	 * Render posts HTML from JSON data
	 */
	async function renderPosts(posts) {
		let html = '';
		for (const post of posts) {
			html += createPostCard(post);
		}
		return html;
	}

	/**
	 * Create post card HTML from post data
	 */
	function createPostCard(post) {
		const joinApp =
			typeof window.__appUrl === 'function'
				? function (rel) {
						return window.__appUrl(rel);
					}
				: function (rel) {
						rel = String(rel).replace(/^\/+/, '');
						const p = String(window.__APP_BASE__ || '')
							.replace(/\/+$/, '')
							.replace(/^https?:\/\/[^/]+/i, '');
						return p === '' ? '/' + rel : p.replace(/\/+$/, '') + '/' + rel;
					};
		const authorAvatarUrl =
			post.author_avatar_url || joinApp('public/images/default-avatar.png');
		const isLiked = post.is_liked ? 'true' : 'false';
		const isSaved = post.is_saved ? 'true' : 'false';

		let mediaHtml = '';
		if (post.media && post.media.length > 0) {
			mediaHtml = '<div class="post-media-container mt-3">';
			post.media.forEach((m, idx) => {
				const mediaUrl = m.media_url || '';
				const mediaType = m.media_type || 'image';
				if (mediaType === 'video') {
					mediaHtml += `<video class="post-media img-fluid rounded-3 me-2" style="max-width: 100%; margin-bottom: 8px;" controls>
						<source src="${htmlEscape(mediaUrl)}" type="video/mp4">
					</video>`;
				} else {
					mediaHtml += `<img class="post-media img-fluid rounded-3 js-open-post-modal-media" src="${htmlEscape(mediaUrl)}" alt="Post media" style="max-width: 100%; margin-bottom: 8px;">`;
				}
			});
			mediaHtml += '</div>';
		}

		let hashtagHtml = '';
		if (post.hashtag_names && post.hashtag_names.length > 0) {
			hashtagHtml = '<div class="post-hashtags mt-2">';
			post.hashtag_names.forEach(tag => {
				hashtagHtml += `<a href="${htmlEscape(joinApp('search?q=' + encodeURIComponent('#' + tag)))}" class="badge bg-secondary me-2">#${htmlEscape(tag)}</a>`;
			});
			hashtagHtml += '</div>';
		}

		const createdAt = new Date(post.created_at).toLocaleDateString('vi-VN', {
			year: 'numeric',
			month: 'long',
			day: 'numeric',
			hour: '2-digit',
			minute: '2-digit'
		});

		return `
			<div class="card border-0 shadow-sm rounded-4 mb-3 post-card js-post-card" data-post-id="${post.id}" data-post-url="${htmlEscape(joinApp('post/' + post.id))}">
				<div class="card-body p-3 p-md-4">
					<div class="d-flex align-items-center mb-3">
						<a href="${htmlEscape(joinApp('user/' + post.user_id))}" class="me-3">
							<img src="${htmlEscape(authorAvatarUrl)}" alt="${htmlEscape(post.author_name)}" class="rounded-circle" style="width: 48px; height: 48px; object-fit: cover;">
						</a>
						<div class="flex-grow-1">
							<a href="${htmlEscape(joinApp('user/' + post.user_id))}" class="text-decoration-none text-dark">
								<strong>${htmlEscape(post.author_name)}</strong>
							</a>
							<div class="text-secondary" style="font-size: 12px;">
								${createdAt}
							</div>
						</div>
					</div>
					<div class="post-content">
						${htmlEscape(post.content)}
					</div>
					${mediaHtml}
					${hashtagHtml}
					<div class="post-stats mt-3 pt-3 border-top d-flex justify-content-around">
						<small class="text-secondary">
							<span class="like-count">${post.like_count || 0}</span> thích
						</small>
						<small class="text-secondary">
							<span class="comment-count">${post.comment_count || 0}</span> bình luận
						</small>
						<small class="text-secondary">
							<span class="share-count">${post.share_count || 0}</span> chia sẻ
						</small>
					</div>
					<div class="post-actions mt-3 pt-3 border-top d-flex justify-content-around">
						<button class="btn btn-sm btn-light flex-grow-1 me-2 like-btn" data-post-id="${post.id}" data-is-liked="${isLiked}">
							<i class="bi bi-heart${isLiked === 'true' ? '-fill text-danger' : ''}"></i> Thích
						</button>
						<button type="button" class="btn btn-sm btn-light flex-grow-1 me-2 js-comment-btn comment-btn" data-post-id="${post.id}">
							<i class="bi bi-chat"></i> Bình luận
						</button>
						<button class="btn btn-sm btn-light flex-grow-1 me-2 share-btn" data-post-id="${post.id}">
							<i class="bi bi-share"></i> Chia sẻ
						</button>
						<button class="btn btn-sm btn-light flex-grow-1 save-btn" data-post-id="${post.id}" data-is-saved="${isSaved}">
							<i class="bi bi-bookmark${isSaved === 'true' ? '-fill text-warning' : ''}"></i> Lưu
						</button>
					</div>
				</div>
			</div>
		`;
	}

	/**
	 * Initialize event handlers for post cards
	 */
	function initializePostHandlers() {
		// Rebind AJAX handlers for forms in newly loaded posts
		if (window.initAjaxHandlers && typeof window.initAjaxHandlers === 'function') {
			window.initAjaxHandlers();
		}
	}

	/**
	 * Handle like button click
	 */
	function handleLikeClick(e) {
		e.preventDefault();
		const btn = e.currentTarget;
		const postId = btn.dataset.postId;
		const isLiked = btn.dataset.isLiked === 'true';

		// Update UI optimistically
		btn.dataset.isLiked = !isLiked ? 'true' : 'false';
		const icon = btn.querySelector('i');
		if (icon) {
			icon.classList.toggle('bi-heart');
			icon.classList.toggle('bi-heart-fill');
			icon.classList.toggle('text-danger');
		}

		// Send request to server
		const formData = new FormData();
		formData.append('post_id', postId);
		formData.append('action', isLiked ? 'unlike' : 'like');

		fetch('/api/like.php', {
			method: 'POST',
			body: formData
		}).catch(err => {});
	}

	/**
	 * Handle comment button click
	 */
	function handleCommentClick(e) {
		e.preventDefault();
		const btn = e.currentTarget;
		const postId = btn.dataset.postId;
		const postCard = document.querySelector(`[data-post-id="${postId}"]`);

		if (postCard) {
			// Scroll to post and show comment section
			postCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}
	}

	/**
	 * Escape HTML special characters
	 */
	function htmlEscape(text) {
		const map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return String(text).replace(/[&<>"']/g, m => map[m]);
	}

	/**
	 * Setup infinite scroll listener
	 */
	function setupInfiniteScroll() {
		let lastScrollCheck = 0;
		
		const handleScroll = () => {
			// Throttle scroll checks to every 500ms
			const now = Date.now();
			if (now - lastScrollCheck < 500) return;
			lastScrollCheck = now;

			let scrollPosition, threshold;
			
			// Check if scrolling on container or window
			if (scrollContainer === window) {
				scrollPosition = window.innerHeight + window.scrollY;
				threshold = document.documentElement.scrollHeight - 500;
			} else {
				scrollPosition = scrollContainer.scrollTop + scrollContainer.clientHeight;
				threshold = scrollContainer.scrollHeight - 500;
			}

		if (scrollPosition >= threshold && !isLoading && hasMore) {
			loadMorePosts();
		}
	};

	scrollContainer.addEventListener('scroll', handleScroll);
	}

	// Initialize
	setupInfiniteScroll();
	initializePostHandlers();

	// Force check if content is too short to trigger natural scroll
	setTimeout(() => {
		let currentHeight;
		let visibleHeight;

		if (scrollContainer === window) {
			currentHeight = document.documentElement.scrollHeight;
			visibleHeight = window.innerHeight;
		} else {
			currentHeight = scrollContainer.scrollHeight;
			visibleHeight = scrollContainer.clientHeight;
		}
		
		// If content is shorter than viewport/container and hasMore, load more to fill
		if (currentHeight <= visibleHeight && hasMore && !isLoading) {
			loadMorePosts();
		}
	}, 1000);
});
