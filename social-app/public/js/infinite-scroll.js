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
	const baseUrl = window.__APP_BASE__ || '/';
	const apiUrl = baseUrl + 'api/load_more_posts.php';

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
			const response = await fetch(apiUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
					Accept: 'text/html',
				},
				body: `offset=${currentOffset}&tab=${encodeURIComponent(feedTab)}`,
			});

			if (!response.ok) {
				throw new Error(`HTTP error! status: ${response.status}`);
			}

			const html = await response.text();
			if (!html.trim()) {
				hasMore = false;
				if (noMoreIndicator) {
					noMoreIndicator.style.display = 'block';
				}
				if (loadingIndicator) {
					loadingIndicator.style.display = 'none';
				}
				return;
			}

			const beforeArticleCount = feedContainer.querySelectorAll('article.js-post-card').length;
			const anchor = feedContainer.querySelector('.feed-loading');
			if (anchor) {
				anchor.insertAdjacentHTML('beforebegin', html);
			} else {
				feedContainer.insertAdjacentHTML('beforeend', html);
			}

			const articles = feedContainer.querySelectorAll('article.js-post-card');
			const added = articles.length - beforeArticleCount;
			for (let i = beforeArticleCount; i < articles.length; i++) {
				articles[i].querySelectorAll('img.js-open-post-modal-media').forEach(function (img) {
					img.loading = 'eager';
					const src = img.getAttribute('src');
					if (src) {
						img.removeAttribute('src');
						img.setAttribute('src', src);
					}
				});
			}

			if (added <= 0) {
				hasMore = false;
				if (noMoreIndicator) {
					noMoreIndicator.style.display = 'block';
				}
				if (loadingIndicator) {
					loadingIndicator.style.display = 'none';
				}
				return;
			}

			let batchCount = parseInt(response.headers.get('X-Load-More-Count') || '0', 10);
			if (batchCount <= 0) {
				batchCount = added;
			}
			let hasMoreHeader = response.headers.get('X-Load-More-Has-More') === '1';
			if (response.headers.get('X-Load-More-Has-More') === null) {
				hasMoreHeader = added >= 5;
			}

			currentOffset += batchCount;
			hasMore = hasMoreHeader;
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
	 * Initialize event handlers for post cards
	 */
	function initializePostHandlers() {
		// Rebind AJAX handlers for forms in newly loaded posts
		if (window.initAjaxHandlers && typeof window.initAjaxHandlers === 'function') {
			window.initAjaxHandlers();
		}
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
