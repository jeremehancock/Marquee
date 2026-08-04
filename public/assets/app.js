// App-wide progressive enhancement: register the service worker and, if an
// update is available, surface a note in the footer.
(function () {
    'use strict';

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw.js').catch(function () { /* ignore */ });
        });
    }

    // The version note appears both in the desktop footer and in the mobile menu
    // tray (only one is visible per breakpoint), so update every instance.
    var notes = document.querySelectorAll('.js-update-note');
    if (!notes.length) {
        return;
    }

    fetch('/version', { headers: { Accept: 'application/json' } })
        .then(function (res) { return res.ok ? res.json() : null; })
        .then(function (data) {
            if (data && data.updateAvailable && data.latest) {
                notes.forEach(function (note) {
                    note.textContent = ' · Update available (v' + data.latest + ')';
                });
            }
        })
        .catch(function () { /* ignore */ });
})();

// Custom tooltips: one shared, themed bubble driven by [data-tooltip], replacing
// the browser's native title= tooltip so hints match the app and can show full
// poster titles that the caption truncates. Delegated from the document so it
// also covers AJAX- and Alpine-rendered content without any re-binding.
//
// Tooltips are a pointer-device affordance and never appear on touch: a tap has
// no hover to end, so a bubble raised by one just sits over the interface. Every
// trigger funnels through show(), which is gated on the device actually having a
// hovering, fine pointer — so a new [data-tooltip] host inherits this for free.
(function () {
    'use strict';

    var bubble = null;
    var current = null;

    // Live query, read at trigger time rather than cached at load, so a hybrid
    // device (touchscreen laptop, tablet that gains or loses a mouse) is judged
    // by its current input. `pointer: fine` also rules out a coarse pointer that
    // reports hover — a TV/console browser — where a bubble can't be dismissed.
    var pointerDevice = window.matchMedia('(hover: hover) and (pointer: fine)');

    // When the last touch happened. A tap moves focus to the control it hits,
    // and FocusEvent carries no pointerType to inspect, so focus tooltips are
    // suppressed briefly after a touch. A timestamp rather than a flag: there is
    // no reliable event to clear a flag on, and one left stuck on would kill
    // keyboard tooltips for the rest of the page's life.
    var touchedAt = 0;
    var TOUCH_GRACE_MS = 500;

    function allowed() {
        return pointerDevice.matches;
    }

    // A host whose tooltip only restates text already on screen opts in to being
    // conditional, and stays silent while that text is readable — a bubble
    // repeating what is visible is noise. Hosts carrying a real hint (pagination
    // steps, Sort) don't opt in and always show.
    //
    // Two ways to restate: [data-tooltip-truncated] for text cut off by an
    // ellipsis (a poster caption), and [data-tooltip-collapsed] for a label the
    // layout has dropped outright (a header nav action narrowed to its icon).
    function conditional(target) {
        return target.hasAttribute('data-tooltip-truncated')
            || target.hasAttribute('data-tooltip-collapsed');
    }

    // Whether a conditional host is currently restating something visible, in
    // which case the bubble is withheld.
    function redundant(target) {
        if (target.hasAttribute('data-tooltip-truncated')) {
            return !truncated(target);
        }
        return !collapsed(target);
    }

    // Measured at trigger time rather than tracked with an observer: a caption's
    // width moves with the viewport, the breakpoint and late-loading fonts, and
    // the gallery replaces its markup on every search, sort and page change. A
    // cached answer would go stale on all of those paths; a fresh one cannot.
    // Exact for a single-line, nowrap, ellipsised element, which is what these
    // hosts are.
    function truncated(target) {
        return target.scrollWidth > target.clientWidth;
    }

    // True when none of the host's labels renders a box — the narrow-header state
    // where a nav action is down to its icon and nothing on screen names it. Read
    // at trigger time for the same reason `truncated` is: which label shows is a
    // function of the viewport, and any cached answer goes stale on a resize.
    // `getClientRects()` rather than a class check, so it stays true to what is
    // actually painted regardless of which rule did the hiding.
    function collapsed(target) {
        var labels = target.querySelectorAll('.nav-label');
        for (var i = 0; i < labels.length; i++) {
            if (labels[i].getClientRects().length) { return false; }
        }
        return true;
    }

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
        if (!allowed()) { return; }
        if (conditional(target) && redundant(target)) { return; }
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

    // Capture phase, so the timestamp lands even if a handler stops propagation.
    document.addEventListener('pointerdown', function (e) {
        touchedAt = e.pointerType === 'touch' ? Date.now() : 0;
    }, true);

    document.addEventListener('pointerover', function (e) {
        if (e.pointerType === 'touch') { return; } // touch would leave it stuck
        var target = closest(e.target);
        if (!target) { return; }
        // The help cursor promises a tooltip, so it is driven by the same
        // measurement that decides whether one appears — the two can't disagree.
        if (conditional(target)) {
            target.classList.toggle('is-truncated', truncated(target));
        }
        if (target !== current) { show(target); }
    });
    document.addEventListener('pointerout', function (e) {
        if (current && closest(e.target) === current && !current.contains(e.relatedTarget)) {
            hide();
        }
    });
    document.addEventListener('focusin', function (e) {
        // Focus that a tap just placed on a control is not a request for a hint.
        if (Date.now() - touchedAt < TOUCH_GRACE_MS) { return; }
        var target = closest(e.target);
        if (target) { show(target); }
    });
    document.addEventListener('focusout', hide);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { hide(); } });
    window.addEventListener('scroll', hide, true);

    // Never strand a bubble on a device that has stopped qualifying mid-hover.
    if (pointerDevice.addEventListener) {
        pointerDevice.addEventListener('change', hide);
    } else if (pointerDevice.addListener) {
        pointerDevice.addListener(hide); // Safari < 14
    }
})();
