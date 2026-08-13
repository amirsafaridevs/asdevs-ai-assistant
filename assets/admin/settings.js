/**
 * AI Assistant admin — auto-save connector preference and test connections.
 */
(function () {
	'use strict';

	var config = window.asdevsAiAdmin;
	if (!config || !config.restUrl) {
		return;
	}

	var root = document.querySelector('[data-asdevs-providers]');
	var toastHost = document.getElementById('asdevs-ai-admin-toast-host');
	if (!root) {
		return;
	}

	var i18n = config.i18n || {};
	var overlay = root.querySelector('.asdevs-ai-admin__providers-overlay');
	var busy = false;
	var current = root.getAttribute('data-current') || '';

	function escapeHtml(value) {
		return String(value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function setBusy(next) {
		busy = next;
		root.classList.toggle('is-busy', next);
		root.setAttribute('aria-busy', next ? 'true' : 'false');
		if (overlay) {
			overlay.hidden = !next;
		}

		root.querySelectorAll('input[type="radio"], .asdevs-ai-admin__test').forEach(function (el) {
			if (el.classList.contains('asdevs-ai-admin__test')) {
				var wasDisabled = el.getAttribute('data-was-disabled') === '1';
				if (next) {
					if (el.disabled) {
						el.setAttribute('data-was-disabled', '1');
					}
					el.disabled = true;
				} else {
					el.disabled = wasDisabled;
					el.removeAttribute('data-was-disabled');
				}
			} else {
				el.disabled = next;
			}
		});
	}

	function iconSvg(ok) {
		if (ok) {
			return (
				'<svg viewBox="0 0 24 24" fill="none">' +
				'<path d="M20 7 10.5 16.5 5 11" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>' +
				'</svg>'
			);
		}

		return (
			'<svg viewBox="0 0 24 24" fill="none">' +
			'<path d="M12 8v5.25M12 16.5h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>' +
			'<path d="M10.29 4.86 2.82 17.5A1.8 1.8 0 0 0 4.36 20.2h15.28a1.8 1.8 0 0 0 1.54-2.7L13.71 4.86a1.8 1.8 0 0 0-3.42 0Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>' +
			'</svg>'
		);
	}

	function showToast(opts) {
		if (!toastHost) {
			return;
		}

		var ok = !!opts.ok;
		var title = escapeHtml(opts.title || '');
		var text = opts.html ? opts.html : escapeHtml(opts.text || '');
		var variant = ok ? 'success' : 'warning';

		toastHost.innerHTML =
			'<div class="asdevs-ai-admin__toast asdevs-ai-admin__toast--' +
			variant +
			'" role="status">' +
			'<span class="asdevs-ai-admin__toast-icon" aria-hidden="true">' +
			iconSvg(ok) +
			'</span>' +
			'<div class="asdevs-ai-admin__toast-body">' +
			'<p class="asdevs-ai-admin__toast-title">' +
			title +
			'</p>' +
			'<p class="asdevs-ai-admin__toast-text">' +
			text +
			'</p>' +
			'</div></div>';
	}

	function request(path, body) {
		return fetch(config.restUrl.replace(/\/$/, '') + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce,
			},
			body: JSON.stringify(body || {}),
		}).then(function (response) {
			return response.json().then(function (data) {
				if (!response.ok) {
					var err = new Error(
						(data && (data.message || data.error)) || i18n.errorGeneric || 'Request failed'
					);
					err.status = response.status;
					err.data = data;
					throw err;
				}
				return data;
			});
		});
	}

	function selectRadio(providerId) {
		var safe =
			window.CSS && typeof CSS.escape === 'function'
				? CSS.escape(providerId)
				: String(providerId).replace(/"/g, '\\"');
		var input = root.querySelector('input[type="radio"][value="' + safe + '"]');
		if (input) {
			input.checked = true;
		}
	}

	function saveProvider(providerId) {
		if (busy || !providerId || providerId === current) {
			return;
		}

		var previous = current;
		current = providerId;
		root.setAttribute('data-current', providerId);
		setBusy(true);

		request('/settings', { provider: providerId })
			.then(function (data) {
				current = data.provider || providerId;
				root.setAttribute('data-current', current);
				selectRadio(current);

				if (data.ready) {
					showToast({
						ok: true,
						title: i18n.savedTitle,
						text: i18n.savedReady,
					});
				} else {
					showToast({
						ok: false,
						title: i18n.savedPending,
						html: i18n.savedNeedsKey,
					});
				}
			})
			.catch(function (error) {
				current = previous;
				root.setAttribute('data-current', previous);
				selectRadio(previous);
				showToast({
					ok: false,
					title: i18n.errorTitle,
					text: error.message || i18n.errorGeneric,
				});
			})
			.finally(function () {
				setBusy(false);
			});
	}

	function testProvider(providerId, button) {
		if (busy || !providerId) {
			return;
		}

		var label = button.textContent;
		setBusy(true);
		button.textContent = i18n.testing || 'Testing…';

		request('/settings/test', { provider: providerId })
			.then(function (data) {
				showToast({
					ok: !!data.ok,
					title: data.ok ? i18n.testOkTitle : i18n.testFailTitle,
					text: data.message || (data.ok ? i18n.testOkTitle : i18n.testFailTitle),
				});
			})
			.catch(function (error) {
				showToast({
					ok: false,
					title: i18n.testFailTitle,
					text: error.message || i18n.errorGeneric,
				});
			})
			.finally(function () {
				button.textContent = label || i18n.testConnection || 'Test connection';
				setBusy(false);
			});
	}

	root.addEventListener('change', function (event) {
		var target = event.target;
		if (!target || target.name !== 'provider') {
			return;
		}
		saveProvider(target.value);
	});

	root.addEventListener('click', function (event) {
		var button = event.target.closest('.asdevs-ai-admin__test');
		if (!button || !root.contains(button)) {
			return;
		}
		event.preventDefault();
		event.stopPropagation();
		testProvider(button.getAttribute('data-test-provider') || '', button);
	});
})();
