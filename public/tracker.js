/* Statlite tracker — embed on any site:
   <script async src="https://stats.example.com/tracker.js"
           data-site-id="abcd1234efgh5678"
           data-endpoint="https://stats.example.com/api/track"></script>
*/
(function () {
    'use strict';

    if (window.__statlite_initialized) return;
    window.__statlite_initialized = true;

    var script = document.currentScript || (function () {
        var all = document.getElementsByTagName('script');
        for (var i = all.length - 1; i >= 0; i--) {
            if (all[i].getAttribute('data-site-id') && all[i].getAttribute('data-endpoint')) return all[i];
        }
        return null;
    })();
    if (!script) return;

    var siteId = script.getAttribute('data-site-id');
    var endpoint = script.getAttribute('data-endpoint');
    if (!siteId || !endpoint) return;

    var STORAGE_KEY = '__statlite_uid';
    var visitorUid = null;
    try {
        visitorUid = localStorage.getItem(STORAGE_KEY);
        if (!visitorUid) {
            visitorUid = generateUuid();
            if (visitorUid) localStorage.setItem(STORAGE_KEY, visitorUid);
        }
    } catch (_) {
        visitorUid = null;
    }

    send(endpoint, {
        public_id: siteId,
        visitor_uid: visitorUid,
        page_url: window.location.href,
        referrer: document.referrer || null,
    });

    function generateUuid() {
        if (window.crypto && typeof crypto.randomUUID === 'function') {
            return crypto.randomUUID();
        }
        return null;
    }

    function send(url, body) {
        var data = JSON.stringify(body);

        if (navigator.sendBeacon) {
            try {
                var blob = new Blob([data], { type: 'application/json' });
                if (navigator.sendBeacon(url, blob)) return;
            } catch (_) {}
        }

        try {
            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: data,
                keepalive: true,
                mode: 'cors',
                credentials: 'omit',
            });
        } catch (_) {}
    }
})();
