(function () {
	function removeClasses(el, classNames) {
		if (!el) return;
		classNames.forEach(function (c) {
			el.classList.remove(c);
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
					var countEl = document.getElementById('like-count-' + data.postId);
					var btnEl = document.getElementById('like-btn-' + data.postId);
					var iconEl = document.getElementById('like-icon-' + data.postId);

					if (countEl) {
						countEl.textContent = data.like_count;
						removeClasses(countEl, ['text-danger', 'text-secondary']);
						countEl.classList.add(data.is_liked ? 'text-danger' : 'text-secondary');
					}

					if (btnEl) {
						removeClasses(btnEl, ['text-danger', 'text-secondary']);
						btnEl.classList.add(data.is_liked ? 'text-danger' : 'text-secondary');
					}

					if (iconEl) {
						removeClasses(iconEl, ['bi-heart', 'bi-heart-fill']);
						iconEl.classList.add(data.is_liked ? 'bi-heart-fill' : 'bi-heart');
					}
				}

				if (data.kind === 'save') {
					var countElS = document.getElementById('save-count-' + data.postId);
					var btnElS = document.getElementById('save-btn-' + data.postId);
					var iconElS = document.getElementById('save-icon-' + data.postId);

					if (countElS) {
						countElS.textContent = data.save_count;
						removeClasses(countElS, ['text-warning', 'text-secondary']);
						countElS.classList.add(data.is_saved ? 'text-warning' : 'text-secondary');
					}

					if (btnElS) {
						removeClasses(btnElS, ['text-warning', 'text-secondary']);
						btnElS.classList.add(data.is_saved ? 'text-warning' : 'text-secondary');
					}

					if (iconElS) {
						removeClasses(iconElS, ['bi-bookmark', 'bi-bookmark-fill']);
						iconElS.classList.add(data.is_saved ? 'bi-bookmark-fill' : 'bi-bookmark');
					}
				}

				if (data.kind === 'share') {
					var countElSh = document.getElementById('share-count-' + data.postId);
					if (countElSh) {
						countElSh.textContent = data.share_count;
					}
				}
			})
			.catch(function () {});
	});
})();
