/**
 * Gợi ý @mention (feed composer, bình luận, trả lời, sửa bài).
 * — Chỉ mở khi đoạn sau @ không có khoảng trắng (gõ tiếp nội dung sau mention → đóng).
 * — Chèn @username thật trong DB + khoảng trắng sau khi chọn.
 */
(function () {
	const DEBOUNCE_MS = 180;

	/**
	 * URL gợi ý: ưu tiên window.__MENTION_USERS_URL__ (PHP, cùng quy ước với api/post-detail.php).
	 * Fallback: {__APP_BASE__}/api/mention_users.php — file trong /api/ không cần rewrite Apache.
	 */
	function mentionUsersApiUrl(q, limit) {
		const qs = 'q=' + encodeURIComponent(q) + '&limit=' + String(limit);
		const fixed =
			typeof window.__MENTION_USERS_URL__ === 'string' && window.__MENTION_USERS_URL__ !== ''
				? window.__MENTION_USERS_URL__.replace(/\/?$/, '')
				: '';
		if (fixed !== '') {
			return fixed + '?' + qs;
		}
		const baseRaw =
			typeof window.__APP_BASE__ !== 'undefined' && window.__APP_BASE__ !== null
				? String(window.__APP_BASE__)
				: '';
		const base = baseRaw.replace(/\/?$/, '');
		const path = 'api/mention_users.php';
		if (base === '') {
			return '/' + path + '?' + qs;
		}
		return base + '/' + path + '?' + qs;
	}

	function isMentionField(el) {
		if (!el || (el.tagName !== 'INPUT' && el.tagName !== 'TEXTAREA')) {
			return false;
		}
		if (el.name !== 'content') {
			return false;
		}
		if (el.closest('#feedComposerForm, #createPostForm, #comment-box, form.reply-form, form.js-post-edit-form')) {
			return true;
		}
		const form = el.closest('form');
		const action = form ? form.getAttribute('action') || '' : '';
		return action.indexOf('/post/store') !== -1;
	}

	/**
	 * @returns {{ at: number, query: string, end: number } | null}
	 */
	function getMentionState(el) {
		const text = el.value;
		const end = typeof el.selectionStart === 'number' ? el.selectionStart : text.length;
		const slice = text.slice(0, end);
		const at = slice.lastIndexOf('@');
		if (at < 0) {
			return null;
		}
		if (at > 0) {
			const before = slice.charAt(at - 1);
			if (before && !/\s/.test(before)) {
				return null;
			}
		}
		const after = slice.slice(at + 1);
		const names = typeof window.__MENTION_USERNAMES__ !== 'undefined' && Array.isArray(window.__MENTION_USERNAMES__)
			? window.__MENTION_USERNAMES__
			: [];
		if (/\s/.test(after)) {
			let prefixOk = false;
			for (let ni = 0; ni < names.length; ni++) {
				const uname = names[ni];
				if (uname && uname.startsWith(after)) {
					prefixOk = true;
					break;
				}
			}
			if (!prefixOk) {
				return null;
			}
		}
		return { at, query: after, end };
	}

	let dropdownEl = null;
	let activeInput = null;
	let mentionState = null;
	let items = [];
	let selectedIndex = -1;
	let debounceTimer = null;
	let fetchController = null;

	function ensureDropdown() {
		if (dropdownEl) {
			return dropdownEl;
		}
		dropdownEl = document.createElement('div');
		dropdownEl.className = 'mention-autocomplete-dropdown shadow border rounded-3 bg-white';
		dropdownEl.setAttribute('role', 'listbox');
		dropdownEl.style.cssText = 'position:fixed;z-index:1080;max-height:220px;overflow-y:auto;display:none;min-width:260px;padding:4px 0;';
		document.body.appendChild(dropdownEl);
		dropdownEl.addEventListener('mousedown', function (e) {
			e.preventDefault();
		});
		return dropdownEl;
	}

	function hide() {
		if (dropdownEl) {
			dropdownEl.style.display = 'none';
			dropdownEl.innerHTML = '';
		}
		selectedIndex = -1;
		items = [];
		mentionState = null;
		if (fetchController) {
			try {
				fetchController.abort();
			} catch (e) { /* ignore */ }
			fetchController = null;
		}
	}

	function positionDropdown(inputEl) {
		const dd = ensureDropdown();
		const rect = inputEl.getBoundingClientRect();
		const margin = 8;
		const maxW = Math.min(340, Math.max(260, rect.width));
		dd.style.maxWidth = maxW + 'px';
		dd.style.display = 'block';
		const left = Math.min(Math.max(4, rect.left), window.innerWidth - maxW - 4);
		dd.style.left = left + 'px';
		let top = rect.bottom + margin;
		dd.style.top = top + 'px';
		requestAnimationFrame(function () {
			const h = dd.offsetHeight || 0;
			const spaceBelow = window.innerHeight - rect.bottom - margin;
			const spaceAbove = rect.top - margin;
			let t = rect.bottom + margin;
			if (h > spaceBelow && spaceAbove >= h + margin) {
				t = rect.top - h - margin;
			}
			if (t + h > window.innerHeight - 4) {
				t = Math.max(4, window.innerHeight - h - 4);
			}
			if (t < 4) {
				t = 4;
			}
			dd.style.top = t + 'px';
		});
	}

	function renderList(dataItems, query, errorKind) {
		const dd = ensureDropdown();
		dd.innerHTML = '';
		items = dataItems;
		selectedIndex = items.length > 0 ? 0 : -1;

		if (items.length === 0) {
			const empty = document.createElement('div');
			empty.className = 'px-3 py-2 small text-secondary';
			if (errorKind === 'http' || errorKind === 'network' || errorKind === 'parse') {
				empty.textContent = 'Không tải được danh sách. Kiểm tra đường dẫn app hoặc tải lại trang.';
			} else {
				empty.textContent = query ? 'Không tìm thấy người dùng' : 'Không có gợi ý';
			}
			dd.appendChild(empty);
			return;
		}

		items.forEach(function (it, idx) {
			const btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'mention-ac-item d-flex align-items-center gap-2 w-100 border-0 text-start py-2 px-3';
			btn.setAttribute('role', 'option');
			btn.dataset.index = String(idx);
			if (idx === 0) {
				btn.classList.add('mention-ac-highlight');
			}

			const av = document.createElement('span');
			av.className = 'mention-ac-avatar rounded-circle flex-shrink-0 d-inline-flex align-items-center justify-content-center';
			av.style.cssText = 'width:32px;height:32px;font-size:13px;font-weight:600;overflow:hidden;';
			const name = it.displayName || it.username || '';
			const initial = name.length ? name.charAt(0).toUpperCase() : '?';
			if (it.avatarSrc) {
				const img = document.createElement('img');
				img.src = it.avatarSrc;
				img.alt = '';
				img.width = 32;
				img.height = 32;
				img.className = 'rounded-circle';
				img.style.objectFit = 'cover';
				img.onerror = function () {
					img.style.display = 'none';
					av.textContent = initial;
					av.style.background = '#6c9eff';
					av.style.color = '#fff';
				};
				av.appendChild(img);
			} else {
				av.style.background = '#6c9eff';
				av.style.color = '#fff';
				av.textContent = initial;
			}

			const label = document.createElement('span');
			label.className = 'mention-ac-label text-truncate fw-semibold flex-grow-1 min-w-0';
			label.textContent = name;

			btn.appendChild(av);
			btn.appendChild(label);
			btn.addEventListener('mouseenter', function () {
				dd.querySelectorAll('.mention-ac-item').forEach(function (x) {
					x.classList.remove('mention-ac-highlight');
				});
				btn.classList.add('mention-ac-highlight');
				selectedIndex = idx;
			});
			btn.addEventListener('click', function () {
				selectCurrent();
			});
			dd.appendChild(btn);
		});
	}

	function highlightSelected() {
		if (!dropdownEl) {
			return;
		}
		const btns = dropdownEl.querySelectorAll('.mention-ac-item');
		btns.forEach(function (b, i) {
			if (i === selectedIndex) {
				b.classList.add('mention-ac-highlight');
				b.scrollIntoView({ block: 'nearest' });
			} else {
				b.classList.remove('mention-ac-highlight');
			}
		});
	}

	function selectCurrent() {
		if (!activeInput || !mentionState || selectedIndex < 0 || !items[selectedIndex]) {
			hide();
			return;
		}
		const it = items[selectedIndex];
		const username = it.username || '';
		if (!username) {
			hide();
			return;
		}
		const el = activeInput;
		const { at, end } = mentionState;
		const value = el.value;
		const before = value.slice(0, at);
		const after = value.slice(end);
		const insert = '@' + username + ' ';
		el.value = before + insert + after;
		const pos = before.length + insert.length;
		if (typeof el.setSelectionRange === 'function') {
			el.setSelectionRange(pos, pos);
		}
		hide();
		el.focus();
	}

	function scheduleFetch(inputEl, state) {
		if (debounceTimer) {
			clearTimeout(debounceTimer);
		}
		debounceTimer = setTimeout(function () {
			runFetch(inputEl, state);
		}, DEBOUNCE_MS);
	}

	function runFetch(inputEl, state) {
		const q = state.query;
		if (fetchController) {
			try {
				fetchController.abort();
			} catch (e) { /* ignore */ }
		}
		fetchController = typeof AbortController !== 'undefined' ? new AbortController() : null;
		const url = mentionUsersApiUrl(q, 15);
		const opts = {
			credentials: 'same-origin',
			headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
		};
		if (fetchController) {
			opts.signal = fetchController.signal;
		}
		fetch(url, opts)
			.then(function (res) {
				return res.text().then(function (text) {
					let data = {};
					try {
						data = text ? JSON.parse(text) : {};
					} catch (e) {
						data = { _parseError: true };
					}
					return { ok: res.ok, status: res.status, data: data };
				});
			})
			.then(function (result) {
				if (!mentionState || activeInput !== inputEl) {
					return;
				}
				if (mentionState.at !== state.at || mentionState.end !== state.end) {
					return;
				}
				if (!result.ok || result.data._parseError) {
					renderList([], q, result.ok ? 'parse' : 'http');
					positionDropdown(inputEl);
					return;
				}
				const list = result.data && Array.isArray(result.data.items) ? result.data.items : [];
				renderList(list, q);
				positionDropdown(inputEl);
			})
			.catch(function () {
				if (activeInput === inputEl && mentionState) {
					renderList([], q, 'network');
					positionDropdown(inputEl);
				}
			});
	}

	function onInput(e) {
		const el = e.target;
		if (!isMentionField(el)) {
			return;
		}
		const st = getMentionState(el);
		if (!st) {
			hide();
			return;
		}
		activeInput = el;
		mentionState = st;
		scheduleFetch(el, st);
	}

	function onKeydown(e) {
		const el = e.target;
		if (!isMentionField(el)) {
			return;
		}
		if (!dropdownEl || dropdownEl.style.display === 'none') {
			if (e.key === 'Escape') {
				hide();
			}
			return;
		}
		if (e.key === 'ArrowDown') {
			e.preventDefault();
			if (items.length) {
				selectedIndex = (selectedIndex + 1) % items.length;
				highlightSelected();
			}
		} else if (e.key === 'ArrowUp') {
			e.preventDefault();
			if (items.length) {
				selectedIndex = selectedIndex <= 0 ? items.length - 1 : selectedIndex - 1;
				highlightSelected();
			}
		} else if (e.key === 'Enter' && items.length && selectedIndex >= 0) {
			e.preventDefault();
			selectCurrent();
		} else if (e.key === 'Escape') {
			e.preventDefault();
			hide();
		} else if (e.key === 'Tab') {
			hide();
		}
	}

	function onFocusOut(e) {
		const el = e.target;
		if (!isMentionField(el)) {
			return;
		}
		setTimeout(function () {
			const ae = document.activeElement;
			if (ae && dropdownEl && dropdownEl.contains(ae)) {
				return;
			}
			if (activeInput === el) {
				hide();
			}
		}, 150);
	}

	document.addEventListener('input', onInput, true);
	document.addEventListener('keydown', onKeydown, true);
	document.addEventListener('focusout', onFocusOut, true);

	document.addEventListener('click', function (e) {
		if (!dropdownEl || dropdownEl.style.display === 'none') {
			return;
		}
		const t = e.target;
		if (dropdownEl.contains(t)) {
			return;
		}
		if (isMentionField(t)) {
			return;
		}
		hide();
	});

	window.addEventListener(
		'scroll',
		function () {
			if (dropdownEl && dropdownEl.style.display !== 'none' && activeInput) {
				positionDropdown(activeInput);
			}
		},
		true
	);
})();
