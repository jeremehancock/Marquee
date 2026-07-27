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

// Custom tooltips: one shared, themed bubble driven by [data-tooltip], replacing
// the browser's native title= tooltip so hints match the app and can show full
// poster titles that the caption truncates. Delegated from the document so it
// also covers AJAX- and Alpine-rendered content without any re-binding.
(function () {
    'use strict';

    var bubble = null;
    var current = null;

    function ensure() {
        if (!bubble) {
            bubble = document.createElement('div');
            bubble.className = 'tooltip';
            bubble.setAttribute('role', 'tooltip');
            document.body.appendChild(bubble);
        }
        return bubble;
    }

    function place(target) {
        var tip = ensure();
        var t = target.getBoundingClientRect();
        var b = tip.getBoundingClientRect();
        var margin = 8;

        var top = t.top - b.height - margin;
        if (top < margin) {
            top = t.bottom + margin; // no room above: flip below
        }

        var left = t.left + (t.width - b.width) / 2;
        var max = window.innerWidth - b.width - margin;
        if (left > max) { left = max; }
        if (left < margin) { left = margin; }

        tip.style.top = (top + window.scrollY) + 'px';
        tip.style.left = (left + window.scrollX) + 'px';
    }

    function show(target) {
        var text = target.getAttribute('data-tooltip');
        if (!text) { return; }
        current = target;
        var tip = ensure();
        tip.textContent = text;
        place(target);
        requestAnimationFrame(function () { tip.classList.add('tooltip--visible'); });
    }

    function hide() {
        current = null;
        if (bubble) { bubble.classList.remove('tooltip--visible'); }
    }

    function closest(node) {
        return node && node.closest ? node.closest('[data-tooltip]') : null;
    }

    document.addEventListener('pointerover', function (e) {
        if (e.pointerType === 'touch') { return; } // touch would leave it stuck
        var target = closest(e.target);
        if (target && target !== current) { show(target); }
    });
    document.addEventListener('pointerout', function (e) {
        if (current && closest(e.target) === current && !current.contains(e.relatedTarget)) {
            hide();
        }
    });
    document.addEventListener('focusin', function (e) {
        var target = closest(e.target);
        if (target) { show(target); }
    });
    document.addEventListener('focusout', hide);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { hide(); } });
    window.addEventListener('scroll', hide, true);
})();
