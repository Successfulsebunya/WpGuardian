(function () {
	'use strict';

	document.addEventListener('click', function (event) {
		var button = event.target.closest('button');
		if (!button) {
			return;
		}

		if (button.textContent && button.textContent.toLowerCase().indexOf('restore') !== -1) {
			if (!window.confirm('Are you sure you want to restore this backup?')) {
				event.preventDefault();
			}
		}
	});
})();
