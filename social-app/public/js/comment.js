document.querySelectorAll('.toggle-reply-form-btn').forEach(function (btn) {
	btn.addEventListener('click', function () {
		var target = document.querySelector(btn.getAttribute('data-target'));
		if (!target) return;
		target.classList.toggle('d-none');
		if (!target.classList.contains('d-none')) {
			var input = target.querySelector('input[name="content"]');
			if (input) input.focus();
		}
	});
});

document.querySelectorAll('.toggle-replies-btn').forEach(function (btn) {
	btn.addEventListener('click', function () {
		var target = document.querySelector(btn.getAttribute('data-target'));
		if (!target) return;
		var isHidden = target.classList.contains('d-none');
		target.classList.toggle('d-none');
		btn.textContent = isHidden ? (btn.getAttribute('data-hide-text') || 'Ẩn câu trả lời') : (btn.getAttribute('data-show-text') || 'Xem câu trả lời');
	});
});