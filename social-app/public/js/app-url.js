/**
 * Chuẩn hóa đường dẫn app cho fetch: luôn cùng origin với trang hiện tại,
 * bỏ scheme+host nếu __APP_BASE__ bị cấu hình nhầm thành URL tuyệt đối.
 */
(function () {
	'use strict';

	function rawBase() {
		var b = window.__APP_BASE__;
		if (b === undefined || b === null) {
			return '';
		}
		return String(b).trim();
	}

	function pathPrefix() {
		var r = rawBase();
		if (r === '') {
			return '';
		}
		if (/^https?:\/\//i.test(r)) {
			try {
				r = new URL(r).pathname || '';
			} catch (e) {
				r = '';
			}
		}
		return r.replace(/\/+$/, '');
	}

	window.__appBasePath = pathPrefix;

	/**
	 * @param {string} rel Ví dụ "api/post-detail.php?id=1" (không bắt buộc dấu / đầu)
	 */
	window.__appUrl = function (rel) {
		rel = String(rel || '').replace(/^\/+/, '');
		var base = pathPrefix();
		if (base === '') {
			return '/' + rel;
		}
		return base + '/' + rel;
	};
})();
