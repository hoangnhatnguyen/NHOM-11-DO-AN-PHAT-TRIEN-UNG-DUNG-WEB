(function () {
	'use strict';

	var pendingResolve = null;

	function getModalEl() {
		return document.getElementById('appConfirmModal');
	}

	window.showAppConfirm = function (opts) {
		opts = opts || {};
		return new Promise(function (resolve) {
			var modalEl = getModalEl();
			if (!modalEl || !window.bootstrap || !window.bootstrap.Modal) {
				resolve(window.confirm(String(opts.message || opts.title || 'Xác nhận?')));
				return;
			}
			pendingResolve = resolve;

			var titleEl = document.getElementById('appConfirmModalTitle');
			var msgEl = document.getElementById('appConfirmModalMessage');
			var okBtn = document.getElementById('appConfirmModalOk');
			var cancelBtn = document.getElementById('appConfirmModalCancel');
			if (titleEl) titleEl.textContent = opts.title || 'Xác nhận';
			if (msgEl) {
				msgEl.textContent = opts.message || '';
			}
			if (okBtn) {
				okBtn.textContent = opts.confirmText || 'Đồng ý';
				okBtn.classList.remove('btn-primary', 'btn-danger');
				okBtn.classList.add(opts.danger ? 'btn-danger' : 'btn-primary');
			}
			if (cancelBtn) cancelBtn.textContent = opts.cancelText || 'Hủy';

			try {
				var inst = window.bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: true, keyboard: true });
				inst.show();
			} catch (err) {
				pendingResolve = null;
				resolve(window.confirm(String(opts.message || opts.title || 'Xác nhận?')));
			}
		});
	};

	function clickTargetElement(e) {
		var t = e.target;
		if (!t) return null;
		return t.nodeType === 1 ? t : t.parentElement;
	}

	function resolveConfirmForm(btn) {
		var form = btn.closest('form.js-app-confirm-form');
		if (form) return form;
		var fid = btn.getAttribute('data-form-target');
		if (fid) {
			var el = document.getElementById(fid);
			if (el && el.tagName === 'FORM' && el.classList.contains('js-app-confirm-form')) return el;
		}
		return null;
	}

	function readConfirmAttrs(form, btn) {
		var src = form || btn;
		return {
			title: src.getAttribute('data-confirm-title') || 'Xác nhận',
			message: src.getAttribute('data-confirm-message') || '',
			danger: src.getAttribute('data-confirm-danger') === '1',
			okText: src.getAttribute('data-confirm-ok') || 'Đồng ý',
		};
	}

	function submitDeleteOrForm(form, btn) {
		if (form) {
			HTMLFormElement.prototype.submit.call(form);
			return;
		}
		var act = btn.getAttribute('data-delete-action');
		if (!act) return;
		var f = document.createElement('form');
		f.method = 'POST';
		f.action = act;
		var inp = document.createElement('input');
		inp.type = 'hidden';
		inp.name = '_csrf';
		inp.value = btn.getAttribute('data-delete-csrf') || '';
		f.appendChild(inp);
		document.body.appendChild(f);
		f.submit();
	}

	document.addEventListener(
		'click',
		function (e) {
			var el = clickTargetElement(e);
			if (!el) return;
			var btn = el.closest('.js-app-confirm-trigger');
			if (!btn) return;
			var form = resolveConfirmForm(btn);
			var hasInlineDelete = !!(btn.getAttribute('data-delete-action') || '').trim();
			if (!form && !hasInlineDelete) return;

			e.preventDefault();
			e.stopImmediatePropagation();

			var attrs = readConfirmAttrs(form, btn);
			window
				.showAppConfirm({
					title: attrs.title,
					message: attrs.message,
					danger: attrs.danger,
					confirmText: attrs.okText,
				})
				.then(function (ok) {
					if (ok) submitDeleteOrForm(form, btn);
				});
		},
		true
	);

	document.addEventListener('click', function (e) {
		var el = clickTargetElement(e);
		if (!el) return;
		var btn = el.closest('.js-saved-unsave-btn');
		if (!btn) return;
		e.preventDefault();
		e.stopPropagation();

		var postId = btn.getAttribute('data-post-id');
		var csrf = btn.getAttribute('data-csrf') || '';
		var base = (window.__APP_BASE__ || '').replace(/\/$/, '');

		window
			.showAppConfirm({
				title: 'Bỏ lưu trữ',
				message: 'Bỏ lưu bài viết này?',
				danger: true,
				confirmText: 'Bỏ lưu',
			})
			.then(function (ok) {
				if (!ok || !postId) return;

				var fd = new FormData();
				fd.append('_csrf', csrf);

				fetch(base + '/post/' + postId + '/save', {
					method: 'POST',
					body: fd,
					credentials: 'same-origin',
					headers: {
						'X-Requested-With': 'XMLHttpRequest',
					},
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
						if (!data || !data.ok) return;
						var row = btn.closest('.js-saved-post-row');
						if (row) row.remove();
						var empty = document.getElementById('saved-empty-state');
						var list = document.getElementById('saved-posts-list');
						if (list && !list.querySelector('.js-saved-post-row')) {
							if (empty) empty.classList.remove('d-none');
						}
					})
					.catch(function () {});
			});
	});

	document.addEventListener('DOMContentLoaded', function () {
		var modalEl = getModalEl();
		if (!modalEl) return;

		var closedByConfirm = false;

		var okBtn = document.getElementById('appConfirmModalOk');
		if (okBtn) {
			okBtn.addEventListener('click', function () {
				closedByConfirm = true;
				var cb = pendingResolve;
				pendingResolve = null;
				if (cb) cb(true);
				var inst = window.bootstrap && window.bootstrap.Modal && window.bootstrap.Modal.getInstance(modalEl);
				if (inst) inst.hide();
			});
		}

		modalEl.addEventListener('hidden.bs.modal', function () {
			if (closedByConfirm) {
				closedByConfirm = false;
				return;
			}
			if (pendingResolve) {
				pendingResolve(false);
				pendingResolve = null;
			}
		});
	});
})();
