/* Statlite tracker — embed on any site:
   <script async src="https://stats.example.com/tracker.js"
           data-site-id="abcd1234efgh5678"
           data-endpoint="https://stats.example.com/api/track"></script>
*/
(function () {
    'use strict';

    if (window.__statlite_initialized) return;

    var script = document.currentScript || (function () {
        var all = document.getElementsByTagName('script');
        for (var i = all.length - 1; i >= 0; i--) {
            if (all[i].src && all[i].src.indexOf('tracker.js') !== -1) return all[i];
        }
        return null;
    })();
    if (!script) return;

    var siteId = script.getAttribute('data-site-id');
    var endpoint = script.getAttribute('data-endpoint');
    if (!siteId || !endpoint) return;

    window.__statlite_initialized = true;

    var STORAGE_KEY = '__statlite_uid';
    var visitorUid = null;
    try {
        visitorUid = localStorage.getItem(STORAGE_KEY);
        if (!visitorUid) {
            visitorUid = generateUuid();
            localStorage.setItem(STORAGE_KEY, visitorUid);
        }
    } catch (_) {
        visitorUid = null;
    }

    var payload = {
        public_id: siteId,
        visitor_uid: visitorUid,
        page_url: location.href,
        referrer: document.referrer || null,
    };

    send(endpoint, payload);

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
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: data,
                keepalive: true,
                mode: 'cors',
                credentials: 'omit',
            });
        } catch (_) {}
    }

    function generateUuid() {
        if (window.crypto && typeof crypto.randomUUID === 'function') {
            return crypto.randomUUID();
        }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = (Math.random() * 16) | 0;
            var v = c === 'x' ? r : (r & 0x3) | 0x8;
            return v.toString(16);
        });
    }
})();
