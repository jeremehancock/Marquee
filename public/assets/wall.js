(function () {
    'use strict';

    var layers = Array.prototype.slice.call(document.querySelectorAll('.wall__layer'));
    var emptyMessage = document.querySelector('.wall__empty');
    var queue = [];
    var active = -1;
    var ROTATE_MS = 8000;
    var STREAM_POLL_MS = 10000;
    var REFILL_AT = 5;
    var fetching = false;

    // Now-playing state. `streams` is the latest poll result; `streamIndex`
    // tracks which stream is on screen when cycling; `shownKey` guards against
    // re-rendering the same tile (so a single stream holds static instead of
    // crossfading to itself every tick).
    var streams = [];
    var streamIndex = 0;
    var shownKey = null;

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

    // Reveal the given layer with the given poster, crossfading from whatever
    // was showing. `nowPlaying` toggles the overlay banners for that layer.
    function reveal(src, nowPlaying) {
        var next = (active + 1) % layers.length;
        var incoming = layers[next];
        incoming.querySelector('.wall__poster').src = src;
        incoming.querySelector('.wall__bg').style.backgroundImage = 'url("' + src + '")';
        incoming.classList.toggle('is-now-playing', !!nowPlaying);
        incoming.classList.add('is-active');
        if (active >= 0) {
            layers[active].classList.remove('is-active');
        }
        active = next;
    }

    function showStream(tile) {
        var next = (active + 1) % layers.length;
        var incoming = layers[next];
        incoming.querySelector('.wall__title').textContent = tile.title || '';
        incoming.querySelector('.wall__detail').textContent = tile.detail || '';
        incoming.querySelector('.wall__user').textContent = tile.user || '';
        reveal(tile.poster, true);
    }

    // Advance the random wall by one poster, refilling the queue as it drains.
    function rotateRandom() {
        var maybeRefill = queue.length <= REFILL_AT ? fetchBatch() : Promise.resolve();
        return maybeRefill.then(function () {
            if (queue.length === 0) {
                if (emptyMessage) { emptyMessage.hidden = false; }
                return;
            }
            if (emptyMessage) { emptyMessage.hidden = true; }
            var src = queue.shift();
            return preload(src).then(function () { reveal(src, false); });
        });
    }

    // Show (or advance through) the active streams. A single stream holds
    // static; multiple streams cycle one per tick. `force` re-renders even when
    // the on-screen tile has not changed (used right after a poll).
    function rotateStreams(force) {
        if (emptyMessage) { emptyMessage.hidden = true; }
        if (streamIndex >= streams.length) {
            streamIndex = 0;
        } else if (streams.length > 1 && !force) {
            streamIndex = (streamIndex + 1) % streams.length;
        }
        var tile = streams[streamIndex];
        var key = tile.id + '@' + streamIndex;
        if (key === shownKey && !force) {
            return Promise.resolve();
        }
        shownKey = key;
        return preload(tile.poster).then(function () { showStream(tile); });
    }

    function rotate() {
        if (streams.length > 0) {
            rotateStreams(false);
        } else {
            rotateRandom();
        }
    }

    function signature(list) {
        return list.map(function (t) { return t.id + '|' + t.user; }).join(',');
    }

    function pollStreams() {
        return fetch('/wall/streams', { headers: { Accept: 'application/json' } })
            .then(function (res) { return res.ok ? res.json() : { streams: [] }; })
            .then(function (data) {
                var next = (data && Array.isArray(data.streams)) ? data.streams : [];
                var changed = signature(next) !== signature(streams);
                streams = next;
                if (!changed) {
                    return;
                }
                // The set of streams changed: reset the cycle and reflect the
                // new state immediately rather than waiting for the next tick.
                streamIndex = 0;
                shownKey = null;
                if (streams.length > 0) {
                    rotateStreams(true);
                } else {
                    rotateRandom();
                }
            })
            .catch(function () { /* transient; keep current mode until next poll */ });
    }

    Promise.all([fetchBatch(), pollStreams()]).then(function () {
        rotate();
        setInterval(rotate, ROTATE_MS);
        setInterval(pollStreams, STREAM_POLL_MS);
    });
})();
