// App-wide progressive enhancement: register the service worker and, if an
// update is available, surface a note in the footer.
(function () {
    'use strict';

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw.js').catch(function () { /* ignore */ });
        });
    }

    var note = document.getElementById('update-note');
    if (!note) {
        return;
    }

    fetch('/version', { headers: { Accept: 'application/json' } })
        .then(function (res) { return res.ok ? res.json() : null; })
        .then(function (data) {
            if (data && data.updateAvailable && data.latest) {
                note.textContent = ' · Update available (v' + data.latest + ')';
            }
        })
        .catch(function () { /* ignore */ });
})();
