(function () {
	var back = document.getElementById('back-to-post');
	if (!back) return;
	back.addEventListener('click', function (e) {
		e.preventDefault();
		if (window.history.length > 1) {
			window.history.back();
		} else {
			window.location.href = this.getAttribute('href') || '/';
		}
	});
})();