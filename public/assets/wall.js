(function () {
    'use strict';

    var layers = Array.prototype.slice.call(document.querySelectorAll('.wall__layer'));
    var emptyMessage = document.querySelector('.wall__empty');
    var queue = [];
    var active = -1;
    var ROTATE_MS = 8000;
    var REFILL_AT = 5;
    var fetching = false;

    function fetchBatch() {
        if (fetching) {
            return Promise.resolve();
        }
        fetching = true;
        return fetch('/wall/posters', { headers: { Accept: 'application/json' } })
            .then(function (res) { return res.ok ? res.json() : { posters: [] }; })
            .then(function (data) {
                if (data && Array.isArray(data.posters)) {
                    queue = queue.concat(data.posters);
                }
            })
            .catch(function () { /* transient; try again next cycle */ })
            .finally(function () { fetching = false; });
    }

    function preload(src) {
        return new Promise(function (resolve) {
            var img = new Image();
            img.onload = function () { resolve(true); };
            img.onerror = function () { resolve(false); };
            img.src = src;
        });
    }

    function show(src) {
        var next = (active + 1) % layers.length;
        var incoming = layers[next];
        incoming.querySelector('.wall__poster').src = src;
        incoming.querySelector('.wall__bg').style.backgroundImage = 'url("' + src + '")';
        incoming.classList.add('is-active');
        if (active >= 0) {
            layers[active].classList.remove('is-active');
        }
        active = next;
    }

    function rotate() {
        var maybeRefill = queue.length <= REFILL_AT ? fetchBatch() : Promise.resolve();
        maybeRefill.then(function () {
            if (queue.length === 0) {
                if (emptyMessage) { emptyMessage.hidden = false; }
                return;
            }
            if (emptyMessage) { emptyMessage.hidden = true; }
            var src = queue.shift();
            return preload(src).then(function () { show(src); });
        });
    }

    fetchBatch().then(function () {
        rotate();
        setInterval(rotate, ROTATE_MS);
    });
})();
