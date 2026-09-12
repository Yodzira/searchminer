/* SearchMiner — search results engagement tracker (no dependencies).
 * Sends exactly one beacon per search page view: 'c' if a result link was
 * clicked, 'n' if the visitor left without clicking. */
(function () {
	'use strict';
	if (typeof window.WPSM_Q === 'undefined' || typeof window.WPSM_URL === 'undefined') { return; }
	var q = String(window.WPSM_Q);
	if (!/^[0-9a-f]{32}$/.test(q)) { return; }

	var sent = false;
	var endpoint = String(window.WPSM_URL);

	function send(kind) {
		if (sent) { return; }
		sent = true;
		var body = 'q=' + encodeURIComponent(q) + '&a=' + kind;
		try {
			if (navigator.sendBeacon) {
				navigator.sendBeacon(endpoint, new Blob([body], { type: 'application/x-www-form-urlencoded' }));
				return;
			}
		} catch (e) { /* fall through */ }
		try {
			fetch(endpoint, {
				method: 'POST',
				keepalive: true,
				credentials: 'omit',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body
			});
		} catch (e) { /* nothing else to try */ }
	}

	document.addEventListener('click', function (ev) {
		var node = ev.target;
		while (node && node !== document && node.nodeName !== 'A') { node = node.parentNode; }
		if (!node || node === document || node.nodeName !== 'A') { return; }
		// Admin bar links are navigation, not result engagement.
		if (node.closest && node.closest('#wpadminbar')) { return; }
		var href = node.getAttribute('href') || '';
		if (!href || href.charAt(0) === '#' || /^(javascript|mailto|tel):/i.test(href)) { return; }
		send('c');
	}, true);

	window.addEventListener('pagehide', function () { if (!sent) { send('n'); } }, { once: true });
	window.addEventListener('beforeunload', function () { if (!sent) { send('n'); } });
}());
