// Gallery enhancement: live search, background grid refresh, no-reload poster
// mutations, lazy-load fade-in, and the Alpine-driven overlays (change modal,
// fullscreen viewer, confirm dialog, toast). Cards render as plain HTML with
// data-* hooks so the grid can be swapped freely and handled by delegation.
// Loaded on every page: the lazy-load fade-in applies wherever poster cards
// render, while the rest activates only on a page with a [data-gallery] root.
(function () {
    'use strict';

    // The gallery's dimmed loading state is deliberately NOT tied to the
    // lifetime of its fetch. Most view changes resolve in tens of milliseconds,
    // and dimming for that long reads as a flicker rather than as loading — the
    // feedback ends up creating the impression of slowness it was meant to
    // soften. So the dim waits out a grace period before appearing at all, and
    // once up it stays up long enough to be read. See beginBusy/endBusy below.
    var LOADING_GRACE_MS = 200;
    var LOADING_MIN_MS = 300;

    // ---- Category swipe constants ----
    // SWIPE_AXIS_LOCK_PX and SWIPE_COMMIT_FRACTION measure different things and
    // must stay separate. The first is how far a finger travels before the
    // gesture decides which axis it is on — it has to be small, because nothing
    // can move until the gesture is claimed and a touch left uncancelled too long
    // has already been given to the scroller. The second is how far the drag has
    // to get before releasing it changes category. The sibling app this comes
    // from once had these as one 100px number, which made the panels unable to
    // move until the gesture was nearly decided anyway.
    var SWIPE_AXIS_LOCK_PX = 8;
    var SWIPE_COMMIT_FRACTION = 1 / 3;
    // px/ms. A short drag thrown at speed commits even below the distance.
    var SWIPE_FLICK_VELOCITY = 0.5;
    // Velocity is read from the END of the gesture over this window, not from
    // its average: a slow drag that finishes in a flick is a flick.
    var SWIPE_VELOCITY_WINDOW_MS = 100;
    // How much of the travel a drag off the end of the strip actually moves.
    // Enough to say "there is nothing there" and not enough to look committable.
    var SWIPE_RESIST_DAMPING = 0.28;
    var SWIPE_SETTLE_MIN_MS = 120;

    // The settle's cap comes from the stylesheet's motion scale rather than a
    // number here, so a retune of --dur-slow carries and the two cannot drift.
    // Read once and cached: this is a getComputedStyle call, which is a layout
    // read, and the gesture's own rule is that it reads nothing once a finger is
    // down. The fallback is for a stylesheet that has not parsed yet.
    var swipeSettleCap = null;
    function swipeSettleMaxMs() {
        if (swipeSettleCap !== null) { return swipeSettleCap; }
        var raw = '';
        try {
            raw = getComputedStyle(document.documentElement)
                .getPropertyValue('--dur-slow').trim();
        } catch (e) { raw = ''; }
        var ms = raw.slice(-2) === 'ms' ? parseFloat(raw) : parseFloat(raw) * 1000;
        swipeSettleCap = isFinite(ms) && ms > 0 ? ms : 300;
        return swipeSettleCap;
    }

    function dispatch(name, detail) {
        window.dispatchEvent(new CustomEvent(name, { detail: detail || {} }));
    }

    // ---- CSRF ----
    // Every state-changing request has to prove it came from a page Marquee
    // rendered. Forms carry the token as a hidden field, which also covers the
    // fetches below that post `new FormData(form)`; the ones that build their
    // own body, or send none at all, have no form to draw from and send this
    // header instead. Read once — the token lasts as long as the session, and
    // the meta tag is in the layout on every page.
    var CSRF_HEADER = 'X-CSRF-Token';

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    // Adds the token to a fetch headers object, leaving whatever is already
    // there alone.
    function withCsrf(headers) {
        var out = headers || {};
        out[CSRF_HEADER] = csrfToken();
        return out;
    }

    // ---- Lazy-load fade-in ----
    // Poster cards ship transparent and are revealed by `is-loaded`, so this has
    // to run on every page that renders them (the gallery, the orphans page),
    // not just the one with a gallery root. An image that errors counts as
    // resolved: a broken poster is a truer signal than an endless placeholder.
    function markLoaded(img) {
        // `complete` covers both outcomes — decoded, or fetched and failed — and
        // a failed fetch is already complete by the time this runs, so its error
        // event will never fire again. Checking naturalWidth here would leave a
        // broken poster waiting on an event that has passed.
        if (img.complete) {
            img.classList.add('is-loaded');
            return;
        }
        var done = function () { img.classList.add('is-loaded'); };
        img.addEventListener('load', done, { once: true });
        img.addEventListener('error', done, { once: true });
    }
    function initImages(scope) {
        (scope || document).querySelectorAll('.card__image').forEach(markLoaded);
    }

    // ---- Card-local refresh after a poster change ----
    // Replacing a poster changes exactly one thing on screen: that card's image.
    // It cannot reorder the grid (both sort orders read the title or the Plex
    // added-at date, never the file's mtime) and it cannot change which posters
    // exist (posters arrive only through import), so the counts, the pagination
    // and every other card are still correct afterwards.
    //
    // Re-rendering the whole grid to show it is what threw the user's place
    // away. On a phone the grid grows by infinite scroll without touching the
    // URL, so re-fetching the current URL replaces an N-page grid with a
    // one-page one: the document collapses, the browser clamps the scroll
    // offset to the shorter page, and the sentinel starts appending pages all
    // over again. Rewriting the one card moves nothing.
    //
    // Scoped to #results and matched on the card's own change button, whose
    // data-category/data-filename pair is the only identity a card has (a
    // filename is unique within a category, not across them). Compared by
    // attribute rather than by selector because a filename is arbitrary text
    // and would need escaping to be safe in one.
    function cardFor(category, filename) {
        var scope = document.querySelector('#results');
        if (!scope || !category || !filename) { return null; }
        var cards = scope.querySelectorAll('.card');
        for (var i = 0; i < cards.length; i++) {
            var button = cards[i].querySelector('[data-action="change"]');
            if (button
                && button.getAttribute('data-category') === category
                && button.getAttribute('data-filename') === filename) {
                return cards[i];
            }
        }
        return null;
    }

    // Rewrite everything on the card that carries the poster's URL, and report
    // whether the card was there to rewrite — a caller that gets false has to
    // fall back to re-rendering the grid rather than leave a stale image up.
    //
    // The cache-buster is the client's own, not the server's `?v=<mtime>`: the
    // new mtime is only knowable by asking for the grid, which is the request
    // being avoided. The poster route ignores unknown query parameters (copyUrl
    // already relies on that), and the next full render restores the canonical
    // URL, so the two only ever disagree about the spelling.
    function refreshCard(category, filename) {
        var card = cardFor(category, filename);
        var image = card ? card.querySelector('.card__image') : null;
        if (!image) { return false; }

        var url = String(image.getAttribute('src')).split('?')[0] + '?v=' + Date.now();
        // Cleared before the src so the replacement fades in on decode the way a
        // freshly rendered card does, instead of showing through at whatever
        // opacity the outgoing image had reached.
        image.classList.remove('is-loaded');
        image.setAttribute('src', url);
        markLoaded(image);

        var download = card.querySelector('a[download]');
        if (download) { download.setAttribute('href', url); }
        card.querySelectorAll('[data-action="copy"], [data-action="view"]').forEach(function (el) {
            el.setAttribute('data-url', url);
        });
        return true;
    }

    // Every mutation endpoint answers 302 -> the gallery page whether it worked
    // or not, so the HTTP status says nothing about whether the poster actually
    // changed; the flash's level is the only signal. Reads the same `.alert`
    // extractFlash does, so the two cannot disagree about which element is the
    // flash.
    //
    // The question is "is there a new image on disk?", not "did everything go
    // well?" — those come apart for an orphan. A change writes the file first
    // and pushes to Plex second, so a poster whose Plex item is gone ends up
    // stored locally and rejected remotely. That is the warning level, and it
    // must re-render the card: treating it as a failure left the gallery
    // showing the old image under a message about the new one, until the user
    // reloaded the page by hand.
    function posterStored(doc) {
        var el = doc.querySelector('.alert');
        if (!el) { return false; }

        return el.classList.contains('alert--success') || el.classList.contains('alert--warning');
    }

    // How long a toast stays up. A fixed dwell has to be set for the shortest
    // message it will ever carry, which leaves the longest ones unreadable — and
    // the longest are the ones that matter most, since a bare "Poster updated."
    // needs no reading at all while "…could not be sent to Plex. This item no
    // longer exists…" is the whole reason the toast was worth showing.
    //
    // The floor is the dwell every toast used to get, and the slope adds reading
    // time at roughly 25 characters a second. The two meet exactly at the length
    // of "Poster updated.", so short messages behave as they always have and only
    // longer ones gain time. The ceiling stops an unexpectedly long message from
    // parking itself over the grid.
    function toastMs(text) {
        return Math.max(2400, Math.min(9000, 1800 + String(text).length * 40));
    }

    // ---- Shared overlay behavior ----
    // The fullscreen viewer, confirm dialog, mobile action tray, and toast are
    // identical on every page that shows poster cards. This factory is spread
    // into each page's Alpine root (galleryUI, orphansPage) so they share one
    // implementation and can include the same overlay markup partial.
    function overlayComponent() {
        return {
            viewer: null,
            viewerLoaded: false,
            // `tone` styles the confirming button. It defaults to `danger`
            // here and in askConfirm below, so a caller that says nothing —
            // orphansPage, which confirms only deletions — keeps the red
            // button it has always had without being touched.
            confirm: { open: false, title: '', message: '', label: 'Confirm', tone: 'danger' },
            sheet: { open: false, title: '', actions: '' },
            toast: { show: false, text: '' },
            _toastTimer: null,

            // One <img> serves every poster ever opened full screen, so its
            // resolved state has to be cleared here rather than tracked on the
            // element: markLoaded's `is-loaded` would stay on from the previous
            // poster and reveal the new one before it had loaded. Clearing on
            // open (not on close) also absorbs the stray `error` that closing
            // fires when `viewer` goes null and leaves src="".
            view: function (url) {
                if (url) {
                    this.viewerLoaded = false;
                    this.viewer = url;
                }
            },
            openSheet: function (detail) {
                this.sheet = { open: true, title: detail.title || '', actions: detail.actions || '' };
            },
            closeSheet: function () {
                this.sheet.open = false;
            },
            askConfirm: function (detail) {
                this.confirm = {
                    open: true,
                    title: detail.title || 'Are you sure?',
                    message: detail.message || '',
                    label: detail.label || 'Confirm',
                    tone: detail.tone || 'danger',
                };
            },
            doConfirm: function () {
                this.confirm.open = false;
                dispatch('gallery:confirmed', {});
            },
            notify: function (text) {
                var self = this;
                this.toast = { show: true, text: text };
                clearTimeout(this._toastTimer);
                this._toastTimer = setTimeout(function () { self.toast.show = false; }, toastMs(text));
            },
        };
    }

    // A touch device has no hover, so there is no room for a card overlay; taps
    // open the action tray instead.
    function isTouch() {
        return !!(window.matchMedia && window.matchMedia('(hover: none)').matches);
    }

    // The tray shows a tapped card's own actions at full size, titled by its
    // caption. The tray heading and the caption are the same string — no library
    // appended — so the visible caption text is the whole source.
    function sheetDetailFor(frame) {
        var actions = frame.querySelector('.card__actions');
        var card = frame.closest('.card');
        var caption = card ? card.querySelector('.card__caption') : null;
        return {
            title: caption ? caption.textContent.trim() : '',
            actions: actions ? actions.outerHTML : '',
        };
    }

    // ---- App-style sheet gestures ----
    // Every tray closes when its backdrop is clicked. A downward drag that starts
    // on the grab handle or the head (never on the scrollable body) dismisses the
    // tray by reusing that same backdrop close, so the gesture works for every one
    // of them — poster actions, menu, sort, import, orphans, change poster,
    // confirmations — without knowing which Alpine scope owns it.
    //
    // This relies on the markup keeping the drag region and the scrolling region
    // as separate elements: the grip and head carry `touch-action: none` so the
    // browser cannot claim the gesture as a scroll, which it can only do if they
    // are not themselves the scroller. Both tray presentations (.sheet, and .modal
    // once the mobile block restyles it) provide that split, so neither needs a
    // special case here.
    (function () {
        var drag = null;
        document.addEventListener('touchstart', function (e) {
            var grip = e.target.closest('.sheet__grip, .sheet__head, .modal__head');
            var panel = grip ? grip.closest('.sheet__panel, .modal__panel') : null;
            if (!panel) { return; }
            drag = { panel: panel, startY: e.touches[0].clientY, dy: 0, h: panel.offsetHeight };
            panel.style.transition = 'none';
        }, { passive: true });

        document.addEventListener('touchmove', function (e) {
            if (!drag) { return; }
            var dy = e.touches[0].clientY - drag.startY;
            drag.dy = dy > 0 ? dy : 0;
            drag.panel.style.transform = 'translateY(' + drag.dy + 'px)';
        }, { passive: true });

        function endDrag() {
            if (!drag) { return; }
            var panel = drag.panel;
            var dismissed = drag.dy > Math.min(120, drag.h * 0.3);
            // Both inline styles are cleared before the dismissal below, and the
            // order matters now that x-transition drives the exit. The leave
            // transition animates the panel out through a class, and an inline
            // transform set during the drag would outrank it — the backdrop would
            // fade while the panel stayed frozen wherever the finger left it.
            // Clearing first hands the panel back to the stylesheet, so a released
            // drag settles and the exit then runs from there.
            panel.style.transition = '';
            panel.style.transform = '';
            if (dismissed) {
                var overlay = panel.closest('.sheet, .modal');
                var backdrop = overlay && overlay.querySelector('.sheet__backdrop, .modal__backdrop');
                if (backdrop) { backdrop.click(); }
            }
            drag = null;
        }
        document.addEventListener('touchend', endDrag);
        document.addEventListener('touchcancel', endDrag);
    }());

    // ---- Is any overlay open? ----
    // There is no single flag to watch. Open state is spread across galleryUI
    // (sheet, confirm, change, sort, import, orphans, viewer), orphansPage, and
    // the standalone menuOpen scope on the topbar — and the orphans tray injects
    // further overlays at runtime. So rather than wiring each one, watch the DOM
    // for the inline `display` that x-show writes, the same way the tray drag
    // gesture above stays agnostic of which scope owns a tray.
    //
    // An overlay that is transitioning out does not count. x-transition keeps the
    // element displayed for the length of the leave animation, so without this
    // the page stays pinned for an extra beat after every dismissal — the user
    // closes a dialog, flicks to scroll, and the first flick is swallowed. The
    // class is the one app.css uses to fade the overlay out, and it is also what
    // makes the dying overlay stop taking clicks.
    //
    // MODULE SCOPE, AND DELIBERATELY SO. This began inside the scroll lock below,
    // which was its only caller. The category swipe is the second: it refuses to
    // start while an overlay is open, and it has to refuse using the SAME answer
    // the lock uses. Two independent readings of "is an overlay open" will drift,
    // and the cost of them disagreeing is a gesture that fights a tray — the tray
    // dismissal drag lives on the other axis of the very same touches. Anything
    // else needing this must call it rather than re-deriving it.
    function anyOverlayOpen() {
        var overlays = document.querySelectorAll('.sheet, .modal, .viewer');
        for (var i = 0; i < overlays.length; i++) {
            if (overlays[i].style.display === 'none') { continue; }
            if (overlays[i].classList.contains('overlay-closing')) { continue; }
            return true;
        }
        return false;
    }

    // ---- Page scroll lock while an overlay is open ----
    // Overlays are fixed layers over a document that is otherwise still live, so
    // without this the page scrolls behind them: a drag on a backdrop has nothing
    // of its own to scroll and chains straight to the document.
    (function () {
        var body = document.body;
        var scrollY = 0;
        var locked = false;
        var queued = false;

        // Pinning the body is the only technique that holds on iOS Safari:
        // `overflow: hidden` on the body is unreliable there, and
        // `overscroll-behavior` is not honoured on the document at all.
        function sync() {
            queued = false;
            var open = anyOverlayOpen();
            if (open === locked) { return; }
            if (open) {
                scrollY = window.scrollY;
                // Pinning the body collapses the document's scroll height, which
                // takes a classic desktop scrollbar with it. Hold its width back
                // as padding so the page underneath does not jump sideways the
                // moment an overlay opens. Touch scrollbars overlay, so this is
                // zero there — exactly where the pinning matters most.
                var gutter = window.innerWidth - document.documentElement.clientWidth;
                if (gutter > 0) { body.style.paddingRight = gutter + 'px'; }
                body.style.top = '-' + scrollY + 'px';
                document.documentElement.classList.add('is-overlay-open');
                locked = true;
                return;
            }
            document.documentElement.classList.remove('is-overlay-open');
            body.style.paddingRight = '';
            body.style.top = '';
            // Restore the exact offset that was captured, not an approximation.
            // The gallery appends the next page when the sentinel nears the
            // viewport, so a restore that drifts would load posters the user never
            // scrolled to.
            window.scrollTo(0, scrollY);
            locked = false;
        }

        function schedule() {
            if (queued) { return; }
            queued = true;
            window.requestAnimationFrame(sync);
        }

        // `style` catches x-show writing `display`, which is what opens the lock
        // and — before x-transition existed — was also what closed it. `class` is
        // what closes it now: an overlay stays displayed for the length of its
        // leave transition, so the release has to be driven by `overlay-closing`
        // arriving rather than by `display: none` arriving several frames later.
        // Both are needed; watching `style` alone re-delays the release, and
        // watching `class` alone never notices an overlay opening.
        //
        // Watching `class` across the whole subtree does mean every unrelated
        // class change schedules a pass — `is-loaded` on each lazily-revealed
        // poster is the loudest of them. That is affordable because `schedule()`
        // coalesces a whole frame's mutations into one rAF and `sync()` returns
        // immediately when the open state has not actually changed, so a hundred
        // images landing together cost one comparison, not a hundred.
        new MutationObserver(schedule).observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['style', 'class'],
            childList: true,
            subtree: true,
        });
        schedule();
    }());

    // ---- Keyboard focus while an overlay is open ----
    // An overlay that opens without taking focus is unusable by keyboard: focus
    // stays on the page behind the backdrop, where it cannot be seen, so reaching
    // the overlay's first control means tabbing the whole page first and tabbing
    // again leaves by the far side. This moves focus in on open, keeps it inside
    // while open, and hands it back on close.
    //
    // The second consumer of the same signal the scroll lock above uses, and for
    // the same reason: open state is spread across galleryUI, orphansPage and the
    // standalone menuOpen scope, and the trays inject further overlays at runtime,
    // so there is nothing to watch but the DOM. Read that block's comments first —
    // they explain why both `style` and `class` are watched and why the cost of
    // watching the whole subtree is affordable.
    //
    // A separate observer rather than a shared one. The two consumers want
    // different answers — the lock wants a boolean, this wants a stack — and the
    // lock's block is correct, subtle, and the thing that makes the page scroll
    // properly on iOS. Doubling a cost that block already argues is one comparison
    // per frame is the better trade against editing it to serve two callers.
    //
    // What this deliberately does not do is mark the page behind an overlay
    // `inert`. Every overlay except the teleported actions tray is a *descendant*
    // of the content it covers — there is no "page minus overlays" element to
    // inert — so having one means teleporting every overlay to <body> first. That
    // is a DOM reshuffle worth its own change; `aria-modal="true"`, already on
    // every managed panel, carries the assistive-technology side until then.
    (function () {
        // The same roots the scroll lock watches. What makes one of them a
        // *dialog* is the role it declares, not its class — see nextDialog().
        var OVERLAY = '.sheet, .modal, .viewer';

        // Everything that can hold focus. Filtered further in focusablesIn():
        // this selector is about kind, not about whether a given element is
        // currently available.
        var FOCUSABLE = 'a[href], area[href], button, input, select, textarea,'
            + ' iframe, audio[controls], video[controls], [contenteditable="true"], [tabindex]';

        // Open overlays, innermost last. Each entry is the dialog panel and the
        // ancestor chain that held focus when it opened. Push on appearing, pop on
        // disappearing: arrival order is the only thing that describes which
        // overlay the user reached last. Markup order does not — the shared
        // confirm is declared before the tray that raises it — and neither does
        // z-index, which is a static value per class.
        var stack = [];
        var queued = false;

        function top() {
            return stack.length ? stack[stack.length - 1] : null;
        }

        // The dialog an open overlay root stands for, or null if it is not one.
        // Descendant-or-self because the preview overlay declares the role on its
        // own root: it has no inner panel, its stage and action bar being
        // siblings. Everything else declares it on the panel inside.
        //
        // Keyed on the attribute rather than a list held here, so an overlay added
        // later is managed by being marked up correctly rather than by being
        // registered — including one injected into a tray at runtime, which is how
        // the orphans confirmation arrives. The fullscreen poster viewer declares
        // no role and is skipped by the same rule, which is deliberate: it holds
        // no focusable content, so there is nowhere to put focus. See the note in
        // _overlays.html.twig.
        function nextDialog(root) {
            if (root.style.display === 'none') { return null; }
            // A closing overlay is not open. x-transition keeps it displayed for
            // the length of its leave animation, and waiting that out would hold
            // focus inside a dialog the user has already dismissed — the same
            // reason the scroll lock releases on this class rather than on
            // `display: none` arriving several frames later.
            if (root.classList.contains('overlay-closing')) { return null; }
            if (root.matches('[role="dialog"]')) { return root; }
            return root.querySelector('[role="dialog"]');
        }

        function openDialogs() {
            var roots = document.querySelectorAll(OVERLAY);
            var found = [];
            for (var i = 0; i < roots.length; i++) {
                var dialog = nextDialog(roots[i]);
                if (dialog) { found.push(dialog); }
            }
            return found;
        }

        function held(dialog) {
            for (var i = 0; i < stack.length; i++) {
                if (stack[i].dialog === dialog) { return true; }
            }
            return false;
        }

        // Where focus was when an overlay opened, remembered as a chain rather
        // than a single element: the origin is often gone by the time it is
        // wanted. Deleting a poster removes the card whose button opened the tray,
        // deleting an orphan removes its row. Restoring then walks up to the
        // nearest ancestor still in the document, so the user resumes near where
        // they were instead of at the top of the page.
        //
        // Stops short of <body>. An origin of <body> is what a touch tap leaves
        // behind, and "restore to the body" is the failure this whole block
        // exists to end, so an empty chain restores nothing and leaves focus
        // alone.
        function chainFor(node) {
            var chain = [];
            while (node && node !== document.body && node !== document.documentElement) {
                chain.push(node);
                node = node.parentElement;
            }
            return chain;
        }

        // Focus without moving the page. While an overlay is open the body is
        // pinned by the scroll lock above, so an unguarded focus scroll would
        // fight it.
        //
        // A surviving ancestor is not necessarily focusable — #results is a plain
        // div — so an element that refuses focus is given a negative tabindex and
        // asked again. Negative, so it never joins the tab order. `tabindex` is
        // not in the observer's attribute filter, so writing it here cannot
        // schedule a pass.
        //
        // The attribute is taken back if it did not help, and that is not
        // tidiness. An element also refuses focus while it is hidden, and a
        // chain walks past plenty of those; a negative tabindex left behind on a
        // button that was merely hidden at the time would drop it out of the tab
        // order for good, once the page showed it again. That is precisely the
        // failure this block exists to end, and it would be self-inflicted.
        function focus(node) {
            node.focus({ preventScroll: true });
            if (document.activeElement === node) { return true; }

            var had = node.hasAttribute('tabindex');
            if (!had) { node.setAttribute('tabindex', '-1'); }
            node.focus({ preventScroll: true });
            if (document.activeElement === node) { return true; }

            if (!had) { node.removeAttribute('tabindex'); }
            return false;
        }

        function restore(chain) {
            for (var i = 0; i < chain.length; i++) {
                if (chain[i].isConnected && focus(chain[i])) { return; }
            }
        }

        // What Tab can reach inside a dialog, recomputed on every press rather
        // than cached: tray bodies arrive over the network long after the tray
        // opens, and Alpine builds and tears down the Find Posters and Plex
        // Posters grids while the dialog stands.
        //
        // `getClientRects()` rather than a class check, matching the tooltip's
        // test in app.js — it is true to what is actually painted, so a control
        // hidden by x-show, by x-cloak, or by a media query is excluded whichever
        // rule did the hiding. That is also what keeps a tray's own injected
        // confirmation out of the tray's tab ring while it is shut.
        //
        // `disabled` is excluded; `aria-disabled` is not, and must not be. A
        // switched-off control staying reachable is the whole point of how this
        // application marks them — see the note on the change dialog's buttons.
        function focusablesIn(dialog) {
            var all = dialog.querySelectorAll(FOCUSABLE);
            var out = [];
            for (var i = 0; i < all.length; i++) {
                var el = all[i];
                if (el.disabled) { continue; }
                if (el.getAttribute('tabindex') === '-1') { continue; }
                if (!el.getClientRects().length) { continue; }
                out.push(el);
            }
            return out;
        }

        function sync() {
            queued = false;
            var open = openDialogs();

            // Close before open, topmost first, so a dismissal hands focus back
            // before anything else claims it.
            for (var i = stack.length - 1; i >= 0; i--) {
                if (open.indexOf(stack[i].dialog) === -1) {
                    restore(stack.splice(i, 1)[0].origin);
                }
            }

            for (var j = 0; j < open.length; j++) {
                if (held(open[j])) { continue; }
                stack.push({ dialog: open[j], origin: chainFor(document.activeElement) });
                // The panel itself, not the first control inside it. Three of the
                // trays fetch their body after opening and are still showing
                // "Loading…" at this moment, so there is no first control to
                // move to; and the panel's own aria-label is what names the
                // overlay to a screen reader. Tabbing forward from here reaches
                // the contents in order, including the ones that arrive later.
                focus(open[j]);
            }
        }

        function schedule() {
            if (queued) { return; }
            queued = true;
            window.requestAnimationFrame(sync);
        }

        // Keep focus inside the topmost overlay. Capture phase, for the reason
        // app.js gives its pointerdown listener: the interception has to happen
        // whether or not something downstream stops propagation.
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Tab') { return; }
            var entry = top();
            if (!entry) { return; }

            var dialog = entry.dialog;
            var items = focusablesIn(dialog);
            var active = document.activeElement;

            // A dialog with nothing to tab to — a tray still loading its body.
            // Hold focus on the panel rather than letting Tab walk out of it.
            if (!items.length) {
                e.preventDefault();
                focus(dialog);
                return;
            }

            var first = items[0];
            var last = items[items.length - 1];

            if (!dialog.contains(active)) {
                e.preventDefault();
                focus(e.shiftKey ? last : first);
            } else if (e.shiftKey && (active === first || active === dialog)) {
                // Backwards off the front, or backwards off the panel, which
                // sits ahead of everything inside it.
                e.preventDefault();
                focus(last);
            } else if (!e.shiftKey && active === last) {
                e.preventDefault();
                focus(first);
            }
            // Anything else is a move between two controls inside the dialog,
            // which the browser already gets right.
        }, true);

        // Catch focus that leaves without anyone pressing Tab. This is not a
        // theoretical case: x-show hides the preview's action row the moment
        // `preview.confirming` flips, and hiding a *focused* element hands its
        // focus to the document — so a keyboard user pressing "Use this poster"
        // is standing on exactly the element that disappears. From the document,
        // their next Tab would resume at the top of the page, outside the
        // overlay. Same failure `draw-the-disabled-state` fixed for the
        // `disabled` attribute, reappearing through another door.
        //
        // Driven from focusout rather than focusin because there is no focusin to
        // read in that case — nothing received the focus that was dropped. Read
        // on the next tick, by which time the browser has settled on the new
        // activeElement.
        document.addEventListener('focusout', function () {
            if (!stack.length) { return; }
            window.setTimeout(recover, 0);
        });

        function recover() {
            var entry = top();
            if (!entry) { return; }
            // Focus that left the page entirely — the address bar, another
            // window — is the user's to move, not this block's to take back.
            if (!document.hasFocus()) { return; }
            var active = document.activeElement;
            if (active && active !== document.body && entry.dialog.contains(active)) { return; }
            focus(entry.dialog);
        }

        new MutationObserver(schedule).observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['style', 'class'],
            childList: true,
            subtree: true,
        });
        schedule();
    }());

    // ---- Return to the top on a page change ----
    // Paging swaps the grid in place, so the browser never performs a navigation
    // and never resets the scroll position. The pagination control sits below the
    // grid, so the user is always at the bottom when they use it — and stays there
    // while an entirely new page of posters renders above them.
    //
    // The animation is a per-call `behavior` rather than `scroll-behavior: smooth`
    // on the document, which would animate EVERY programmatic scroll — including
    // the scroll-lock restore above, showing the page slide back into place each
    // time a tray is dismissed. Reduced motion is read per call, not cached, so
    // changing the system setting mid-session takes effect without a reload; it
    // drops the animation only, since arriving at the top is the point.
    function scrollToTopOfGallery() {
        var reduced = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
        window.scrollTo({ top: 0, behavior: reduced ? 'auto' : 'smooth' });
    }

    // ---- Secondary destinations -> trays (phone) ----
    // On a touch device the "Import from Plex", "Orphans" and "Settings" links and
    // the Plex connection status open in a tray over the gallery instead of
    // navigating, but only on a page that actually has the gallery (and its trays).
    // Elsewhere, and on pointer devices, they navigate normally.
    //
    // The fallback is the anchor's own href, which is what makes this cheap: an
    // intercepted link that finds no tray to open is just a link.
    var TRAY_LINKS = {
        'data-import': 'gallery:import',
        'data-orphans': 'gallery:orphans',
        'data-settings': 'gallery:settings',
        'data-connect': 'gallery:connect',
    };

    document.addEventListener('click', function (e) {
        var link = e.target.closest('a[data-import], a[data-orphans], a[data-settings], a[data-connect]');
        if (!link) { return; }
        if (!isTouch() || !document.querySelector('[data-gallery]')) { return; }
        for (var attr in TRAY_LINKS) {
            if (link.hasAttribute(attr)) {
                e.preventDefault();
                dispatch(TRAY_LINKS[attr], {});
                return;
            }
        }
    });

    // ---- Alpine component: the orphans page ----
    // The shell renders instantly with the spinner up (loading), then fetches
    // the slow orphan scan and swaps the result in. The delete-all overlay
    // lives in the shell; the fetched fragment is plain HTML, so its "Delete
    // all" button and count are wired here rather than through injected Alpine.
    document.addEventListener('alpine:init', function () {
        window.Alpine.data('orphansPage', function (configured) {
            return Object.assign(overlayComponent(), {
                loading: !!configured,
                deleting: false,
                confirmOpen: false,
                count: 0,
                _pendingForm: null,

                init: function () {
                    this.bindInteractions();
                    if (!configured) { return; }
                    var self = this;
                    this.reload()
                        .catch(function () {
                            self.$refs.results.innerHTML = '<p class="alert" role="alert">Could not check for orphans. Please reload the page.</p>';
                        })
                        .finally(function () { self.loading = false; });
                },

                // Fetch the scan fragment and (re)wire it: image fade-in, the
                // orphan count, and the delete-all button. Used for the initial
                // load and after a single orphan is deleted.
                reload: function () {
                    var self = this;
                    var target = this.$refs.results;
                    return fetch('/orphans/list', { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' })
                        .then(function (r) { return r.text(); })
                        .then(function (html) {
                            target.innerHTML = html;
                            initImages(target);
                            var bar = target.querySelector('.orphans__bar');
                            self.count = bar ? (parseInt(bar.getAttribute('data-count'), 10) || 0) : 0;
                            var del = target.querySelector('[data-action="delete-all"]');
                            if (del) { del.addEventListener('click', function () { self.confirmOpen = true; }); }
                        });
                },

                // Per-orphan card interactions: the same overlay-on-pointer,
                // tray-on-touch pattern the library uses, plus in-place delete.
                bindInteractions: function () {
                    var self = this;
                    var root = this.$el;

                    root.addEventListener('click', function (e) {
                        // Re-run the scan (offered only from the in-sync empty state).
                        if (e.target.closest('[data-action="recheck"]')) {
                            self.loading = true;
                            self.reload().finally(function () { self.loading = false; });
                            return;
                        }
                        // Tapping Download inside the tray: let it download, close.
                        if (e.target.closest('.sheet__body a[download]')) { self.closeSheet(); return; }
                        // Tapping a card: touch opens the tray (no room for a
                        // hover overlay on a phone); pointer opens it full screen.
                        var frame = e.target.closest('.card__frame');
                        if (!frame || !root.contains(frame)) { return; }
                        if (e.target.closest('.card__actions')) { return; }
                        if (isTouch()) {
                            self.openSheet(sheetDetailFor(frame));
                        } else {
                            var image = frame.querySelector('.card__image');
                            if (image) { self.view(image.getAttribute('src')); }
                        }
                    });

                    root.addEventListener('submit', function (e) {
                        var form = e.target;
                        if (!(form instanceof HTMLFormElement) || !form.classList.contains('js-mutate')) { return; }
                        e.preventDefault();
                        // Declining leaves the tray standing, as on the gallery:
                        // the confirmation is raised above it and answering "no"
                        // should return the user to the actions, not close them.
                        if (form.hasAttribute('data-confirm')) {
                            self._pendingForm = form;
                            self.askConfirm({
                                title: 'Delete orphan?',
                                message: form.getAttribute('data-confirm'),
                                label: 'Delete',
                            });
                            return;
                        }
                        // The form may live in the tray; close it either way.
                        self.closeSheet();
                        self.submitDelete(form);
                    });

                    window.addEventListener('gallery:confirmed', function () {
                        if (self._pendingForm) {
                            var form = self._pendingForm;
                            self._pendingForm = null;
                            self.closeSheet();
                            self.submitDelete(form);
                        }
                    });
                },

                // Delete one orphan. On success just drop its card and adjust the
                // count — the others are unaffected, so re-scanning Plex would only
                // stall for no gain; the next open scans fresh, on the page and in
                // the tray alike.
                submitDelete: function (form) {
                    var self = this;
                    var field = function (name) {
                        var el = form.querySelector('input[name="' + name + '"]');
                        return el ? el.value : '';
                    };
                    var category = field('category');
                    var filename = field('filename');
                    // Verifying the orphan against Plex happens server-side and can
                    // take a few seconds on a large library, so show the progress
                    // overlay until the delete resolves.
                    this.deleting = true;
                    fetch(form.getAttribute('action'), {
                        method: 'POST',
                        body: new FormData(form),
                        headers: withCsrf({ 'X-Requested-With': 'fetch' }),
                        credentials: 'same-origin',
                    })
                        .then(function (r) { return r.text(); })
                        .then(function (html) {
                            var doc = new DOMParser().parseFromString(html, 'text/html');
                            if (doc.querySelector('.alert--success')) {
                                self.removeOrphanCard(category, filename);
                                self.notify('Orphan deleted');
                            } else {
                                var alert = doc.querySelector('.alert');
                                self.notify(alert ? alert.textContent.trim() : 'That orphan could not be deleted.');
                            }
                        })
                        .catch(function () { self.notify('That orphan could not be deleted.'); })
                        .finally(function () { self.deleting = false; });
                },

                // Delete every orphan at once, in place (no navigation), so this
                // works both on the standalone page and inside the gallery tray.
                deleteAllNow: function () {
                    var self = this;
                    this.confirmOpen = false;
                    this.deleting = true;
                    fetch('/orphans/delete-all', {
                        method: 'POST',
                        headers: withCsrf({ 'X-Requested-With': 'fetch' }),
                        credentials: 'same-origin',
                    })
                        .then(function (r) { return r.text(); })
                        .then(function (html) {
                            var doc = new DOMParser().parseFromString(html, 'text/html');
                            if (doc.querySelector('.alert--success')) {
                                self.$refs.results.innerHTML =
                                    '<div class="panel"><p>No orphaned posters found.</p>' +
                                    '<button type="button" class="btn" data-action="recheck">Re-check for orphans</button></div>';
                                self.count = 0;
                                self.notify('Orphans deleted');
                            } else {
                                var alert = doc.querySelector('.alert');
                                self.notify(alert ? alert.textContent.trim() : 'Could not delete the orphans.');
                            }
                        })
                        .catch(function () { self.notify('Could not delete the orphans.'); })
                        .finally(function () { self.deleting = false; });
                },

                // Drop one orphan's card and reflect the new count, swapping in the
                // in-sync message once the last orphan is gone.
                removeOrphanCard: function (category, filename) {
                    var target = this.$refs.results;
                    var card = target.querySelector(
                        '.card[data-category="' + CSS.escape(category) + '"][data-filename="' + CSS.escape(filename) + '"]'
                    );
                    if (card) { card.remove(); }

                    this.count = Math.max(0, this.count - 1);
                    var bar = target.querySelector('.orphans__bar');
                    if (bar) {
                        bar.setAttribute('data-count', String(this.count));
                        var stats = bar.querySelector('.stats');
                        if (stats) {
                            stats.textContent = this.count + ' orphaned poster' + (this.count === 1 ? '' : 's') + '.';
                        }
                    }
                    if (this.count === 0) {
                        target.innerHTML = '<div class="panel"><p>No orphaned posters found.</p>' +
                            '<button type="button" class="btn" data-action="recheck">Re-check for orphans</button></div>';
                    }
                },
            });
        });
    });

    // ---- Alpine component: the overlays ----
    document.addEventListener('alpine:init', function () {
        // ---- Alpine component: the Plex connection panel ----
        // Signing in runs Plex's PIN flow: the user approves Marquee in a Plex
        // window, and this polls our own origin until the token lands.
        //
        // Two details are load-bearing. The window is opened synchronously in
        // the click handler and its location set afterwards, because opening it
        // once the request resolves is what popup blockers stop. And polling is
        // ordinary short requests rather than a held-open connection, which a
        // reverse proxy or CDN in front of Marquee would buffer or cut.
        window.Alpine.data('plexConnection', function () {
            var POLL_MS = 2000;
            var GIVE_UP_MS = 15 * 60 * 1000;
            // Outside the returned object on purpose — see signIn().
            var plexWindowRef = null;

            return {
                busy: false,
                message: '',
                fallbackUrl: '',
                _timer: null,

                // A sized popup rather than a bare _blank, which browsers open
                // as a full tab. Plex's sign-in page is a small form, and a
                // popup keeps Marquee visible behind it — the sign-in reads as
                // a step in the page you are on rather than a trip somewhere
                // else. Naming the window means a second click reuses it
                // instead of stacking another.
                _open: function () {
                    var w = 620;
                    var h = 720;
                    var baseLeft = window.screenLeft !== undefined ? window.screenLeft : window.screenX;
                    var baseTop = window.screenTop !== undefined ? window.screenTop : window.screenY;
                    // Centre on the window Marquee is in, not the primary
                    // display, so it lands on the right monitor.
                    var left = Math.round(baseLeft + Math.max(0, (window.outerWidth - w) / 2));
                    var top = Math.round(baseTop + Math.max(0, (window.outerHeight - h) / 2));

                    return window.open(
                        '',
                        'marquee-plex-signin',
                        'popup=yes,width=' + w + ',height=' + h + ',left=' + left + ',top=' + top
                    );
                },

                signIn: function () {
                    if (this.busy) { return; }
                    this.busy = true;
                    this.message = '';
                    this.fallbackUrl = '';

                    // Must happen now, inside the gesture, not in the .then().
                    //
                    // Held in a closure, deliberately not on the component:
                    // Alpine makes component state deeply reactive, and wrapping
                    // a cross-origin Window in a proxy is what stops us being
                    // able to close it again.
                    var plexWindow = this._open();
                    plexWindowRef = plexWindow;
                    var self = this;

                    fetch('/plex/connection/sign-in', {
                        method: 'POST',
                        headers: withCsrf({ Accept: 'application/json' }),
                        credentials: 'same-origin'
                    })
                        .then(function (res) { return res.json().then(function (d) { return { ok: res.ok, data: d }; }); })
                        .then(function (result) {
                            if (!result.ok || !result.data || !result.data.authUrl) {
                                if (plexWindow) { plexWindow.close(); }
                                throw new Error((result.data && result.data.error) || 'Could not start sign-in.');
                            }
                            if (plexWindow) {
                                plexWindow.location = result.data.authUrl;
                            } else {
                                // Blocked. Offer the link so the flow still completes.
                                self.fallbackUrl = result.data.authUrl;
                            }
                            self._watch(Date.now() + GIVE_UP_MS);
                        })
                        .catch(function (e) {
                            self.busy = false;
                            self.message = e.message || 'Could not start sign-in.';
                        });
                },

                _watch: function (deadline) {
                    var self = this;
                    this._timer = window.setTimeout(function () {
                        if (Date.now() > deadline) {
                            self._stop('Sign-in timed out. Try again.');
                            return;
                        }

                        // Read before the poll, acted on after it. Closing the
                        // Plex window is how a user abandons a sign-in, and
                        // without noticing it the button sat on "Waiting for
                        // Plex…" for the full fifteen minutes — the request is
                        // still pending at Plex, so nothing in the status ever
                        // says the user walked away.
                        //
                        // The ordering is what makes it safe. Plex's own page
                        // invites you to close the window once you have
                        // approved, so a close can arrive a moment *after* a
                        // successful approval; taking the reading first and
                        // still letting the poll answer means an approved
                        // sign-in completes normally and only a genuinely
                        // abandoned one stops here.
                        var abandoned = self._windowGone();

                        fetch('/plex/connection/status', {
                            headers: { Accept: 'application/json' },
                            credentials: 'same-origin'
                        })
                            .then(function (res) { return res.json(); })
                            .then(function (data) {
                                if (!data) { throw new Error('Sign-in failed.'); }
                                if (data.status === 'completed') {
                                    // Plex leaves its own "you can close this"
                                    // page up; closing it ourselves finishes the
                                    // flow where it started.
                                    self._closeWindow();
                                    // Straight to the gallery, not back to this
                                    // screen. Connecting is a step on the way to
                                    // using Marquee, and for anyone the gate
                                    // sent here it is the last thing between
                                    // them and their posters. The server's flash
                                    // confirms it on arrival.
                                    window.location.href = '/';
                                    return;
                                }
                                if (data.status === 'expired') {
                                    self._stop('The Plex sign-in expired. Try again.');
                                    return;
                                }
                                if (data.status === 'not_owner') {
                                    // Deliberately does not name the owner:
                                    // whoever is reading this is, by
                                    // definition, not them.
                                    self._stop(
                                        'That Plex account does not own this server. '
                                        + 'Sign in with the account that owns it.'
                                    );
                                    return;
                                }
                                if (data.status === 'unreachable') {
                                    // The account was fine. Saying otherwise
                                    // sends the owner to audit the one part of
                                    // this that is working, so this names the
                                    // address instead.
                                    self._stop(
                                        'Marquee could not reach your Plex server. '
                                        + 'Check PLEX_SERVER_URL and that the Plex '
                                        + 'server is running.'
                                    );
                                    return;
                                }
                                if (data.status === 'not_started') {
                                    self._stop('Sign-in was not started.');
                                    return;
                                }
                                if (data.error) { throw new Error(data.error); }
                                // Still pending, and the window the user was
                                // approving in has gone. Nothing further can
                                // happen, so say so instead of waiting it out.
                                if (abandoned) {
                                    self._stop('Sign-in was cancelled. Try again.');
                                    return;
                                }
                                // A sign-in that never completes is otherwise
                                // silent for fifteen minutes; this is what makes
                                // a stalled approval reportable.
                                if (window.console) {
                                    window.console.debug('[marquee] plex sign-in:', data.status);
                                }
                                self._watch(deadline);
                            })
                            .catch(function (e) { self._stop(e.message || 'Sign-in failed.'); });
                    }, POLL_MS);
                },

                // Whether the Plex window we opened has since been closed.
                //
                // `closed` is one of the few things readable on a cross-origin
                // window, which is what makes this possible at all — nothing
                // else about app.plex.tv is legible from here.
                //
                // False when there is no reference to begin with. A blocked
                // popup leaves the user following the fallback link in a tab we
                // never opened and cannot see, and treating that as abandonment
                // would cancel the sign-in they are in the middle of.
                _windowGone: function () {
                    try {
                        return plexWindowRef !== null && plexWindowRef.closed;
                    } catch (e) {
                        return false;
                    }
                },

                _closeWindow: function () {
                    // Only ours, and only if it is still open.
                    try {
                        if (plexWindowRef && !plexWindowRef.closed) { plexWindowRef.close(); }
                    } catch (e) { /* already gone; nothing to do */ }
                    plexWindowRef = null;
                },

                _stop: function (message) {
                    if (this._timer) { window.clearTimeout(this._timer); this._timer = null; }
                    this._closeWindow();
                    this.busy = false;
                    this.fallbackUrl = '';
                    this.message = message;
                }
            };
        });

        window.Alpine.data('galleryUI', function () {
            return Object.assign(overlayComponent(), {
                change: { open: false, tab: 'upload', filename: '', title: '', category: '' },
                // The Find Posters search only. The full-screen step it used to
                // own now lives in `preview` below, shared with the other two
                // tabs — a search that is `applying` made no sense on a dialog
                // the user never opened Find Posters on.
                finder: { loading: false, error: '', notice: '', sections: [] },
                // The Plex Posters tab's own state, deliberately not merged with
                // `finder` into one "current source". The two tabs must be able
                // to hold results at the same time: someone comparing what their
                // server already has against what a search turned up should not
                // lose one by looking at the other.
                plexPosters: { loading: false, error: '', uploaded: [], available: [] },
                // The full-screen step every tab commits through: inspect the
                // image, offer to use it, then confirm. `source` says where the
                // image came from and so which request applying it makes;
                // `file` is the picked File for an upload, held here because the
                // blob URL in `src` is for display only and the input it came
                // from may have been cleared by the time it is posted.
                // `applying` is the in-flight flag for the final "Change poster"
                // confirm, driving the progress overlay and the disabled button.
                preview: { open: false, src: '', loaded: false, confirming: false, applying: false, source: '', file: null, token: '', page: '', service: '' },
                sortOpen: false,
                importOpen: false,
                importLoading: false,
                importLoaded: false,
                orphansOpen: false,
                orphansLoading: false,
                orphansLoaded: false,
                // No `settingsLoaded`: the settings tray is fetched on every open,
                // so there is no "already have it" state to record. See openSettings.
                settingsOpen: false,
                settingsLoading: false,
                settingsSaving: false,
                // No `connectLoaded` either, and for a sharper reason than the
                // settings tray's: /connect asks the Plex server its name when it
                // renders, and the connection can be gone since the gallery behind
                // the tray was drawn. Adding the flag by analogy with the import
                // tray — the one tray that is genuinely fetched once — would pin
                // both to whatever they were the first time the tray was opened.
                connectOpen: false,
                connectLoading: false,

                // Fetch a page's content and drop it into a tray, re-initialising
                // Alpine on the fragment so its own wiring (the import stepper, the
                // orphans scan/delete component) works inside the tray. Progress
                // overlays inside the fragment are contained to the tray by CSS.
                //
                // Whether a tray is worth loading more than once is the caller's
                // business, not this helper's: the import tray holds a
                // configuration form, whose correctness does not decay, so it is
                // fetched once; the orphans tray holds the result of a scan, which
                // does, so it re-scans on every open. Calling this again is also
                // not free of consequence — it re-runs Alpine.initTree over the
                // fragment, re-binding whatever the fragment's own component binds
                // on init. See openOrphans.
                _loadTray: function (url, ref) {
                    var self = this;
                    return fetch(url, { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' })
                        .then(function (r) { return r.text(); })
                        .then(function (html) { return self._injectTray(html, ref); });
                },

                // Take a fetched page's content into a tray. Split out of _loadTray
                // because a settings save that fails validation answers with the same
                // page re-rendered, and it has to arrive in the tray on exactly the
                // terms the first load did — same region, same strip, same re-init.
                // Two copies of that would drift.
                _injectTray: function (html, ref) {
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    var content = doc.querySelector('main.container');
                    var target = this.$refs[ref];
                    // Drop the "Back to gallery" link and page heading — the
                    // tray has its own title and dismisses by swipe/backdrop.
                    if (content) {
                        var back = content.querySelector('.search__clear');
                        if (back && back.closest('p')) { back.closest('p').remove(); }
                        var h1 = content.querySelector('h1');
                        if (h1) { h1.remove(); }
                    }
                    target.innerHTML = content ? content.innerHTML : '';
                    if (window.Alpine && window.Alpine.initTree) { window.Alpine.initTree(target); }
                    initImages(target);
                    return target;
                },

                // Open the import tray. The Plex import form (libraries + stepper) is
                // server-rendered at /plex; fetch it once, then intercept its submit
                // so the import runs and reports inside the tray instead of navigating
                // away, reusing the form's own (now tray-contained) progress overlay.
                openImport: function () {
                    var self = this;
                    this.importOpen = true;
                    if (this.importLoaded || this.importLoading) { return; }
                    this.importLoading = true;
                    this._loadTray('/plex', 'importBody')
                        .then(function (target) {
                            var form = target.querySelector('form[action="/plex/import"]');
                            if (form) {
                                self._importForm = form;
                                form.addEventListener('submit', function (e) { e.preventDefault(); self.runImport(form); });
                            }
                            self.importLoaded = true;
                        })
                        .catch(function () {
                            self.$refs.importBody.innerHTML =
                                '<p class="alert" role="alert">Could not load import. Open the <a href="/plex">Import page</a> instead.</p>';
                        })
                        .finally(function () { self.importLoading = false; });
                },
                // Closing the import tray discards the loaded form so reopening it
                // starts fresh from step one rather than the previous selection.
                _resetImport: function () {
                    this.importLoaded = false;
                    this._importForm = null;
                    if (this.$refs.importBody) { this.$refs.importBody.innerHTML = ''; }
                },
                closeImport: function () {
                    this.importOpen = false;
                    this._resetImport();
                },
                // The loaded form's own Alpine component, or null when it cannot
                // be reached — a failed load leaves an error message where the
                // form would have been. Guarded like _rescanOrphans, so a tray
                // that never loaded degrades to doing nothing rather than
                // throwing inside a completed import.
                _importData: function () {
                    if (!this._importForm || !window.Alpine || !window.Alpine.$data) { return null; }
                    try { return window.Alpine.$data(this._importForm); } catch (e) { return null; }
                },
                // Put the loaded form back to step one, in place.
                //
                // Rewound rather than reloaded: the import tray is fetch-once
                // because a configuration form does not decay (see openImport), so
                // refetching would blank the tray to a spinner and back for content
                // that did not change — and re-running Alpine.initTree over a fresh
                // fragment re-binds whatever that fragment binds on init, which is
                // the hazard openOrphans goes out of its way to avoid.
                //
                // `force` is cleared by hand because it is the one control the
                // form's component does not own — no x-model reaches it. For the
                // same reason form.reset() is not an option here: it would clear
                // the DOM, and x-model would write the stale type and sections
                // straight back over it.
                _rewindImportForm: function () {
                    var data = this._importData();
                    if (data) {
                        data.type = '';
                        // Clearing the type alone is not enough: the radios clear
                        // `sections` on change, so a stale library selection would
                        // reappear the moment the same type was picked again.
                        data.sections = [];
                        // Lifts the tray-contained progress overlay and re-enables
                        // the submit button.
                        data.importing = false;
                    }
                    if (this._importForm) {
                        var force = this._importForm.querySelector('input[name="force"]');
                        if (force) { force.checked = false; }
                    }
                    // Rewinding collapses steps two and three, so a tray still
                    // scrolled down to the Import button would land on whitespace.
                    // The scroller is .sheet__body, not the ref inside it — the
                    // ref is a plain div and setting scrollTop on it does nothing.
                    var body = this.$refs.importBody && this.$refs.importBody.closest('.sheet__body');
                    if (body) { body.scrollTop = 0; }
                },

                // Run the import in place: the form's @submit already shows its
                // spinner (contained to the tray). On completion the tray stays
                // open with the form rewound to step one, because importing is
                // repetitive — the form takes one content type per run, so
                // populating a library means running it once per type, and closing
                // the tray would charge a reopen for every repeat. Then report the
                // summary and refresh the gallery grid behind it.
                runImport: function (form) {
                    var self = this;
                    // The second of the two guards standing where `disabled` used
                    // to stand on its own. The Import button is aria-disabled now,
                    // so it announces rather than enforces, and the form is
                    // submittable by routes that never touch the button — Enter on
                    // a radio being the obvious one, which a disabled default
                    // button used to swallow for free.
                    //
                    // The form's own @submit refuses an incomplete selection too,
                    // but it cannot refuse on this path: preventDefault() does not
                    // stop the listener openImport attached to the same element, so
                    // without this the tray would post a selection the page would
                    // have rejected. `ready` is the getter the button binds, so the
                    // two cannot drift.
                    var data = this._importData();
                    if (data && !data.ready) { return; }
                    fetch('/plex/import', {
                        method: 'POST',
                        body: new FormData(form),
                        headers: withCsrf({ 'X-Requested-With': 'fetch' }),
                        credentials: 'same-origin',
                    })
                        .then(function (r) { return r.text(); })
                        .then(function (html) {
                            var doc = new DOMParser().parseFromString(html, 'text/html');
                            var alert = doc.querySelector('.alert');
                            // The tray stays open; the form goes back to step one
                            // so the next import starts from a clean choice.
                            self._rewindImportForm();
                            self.notify(alert ? alert.textContent.trim() : 'Import complete.');
                            dispatch('gallery:refresh', {});
                        })
                        // A failure leaves the tray standing *and* leaves the
                        // selections alone — they are exactly what a retry would
                        // otherwise have to re-enter — so only the progress overlay
                        // is lifted. Each outcome clears `importing` itself rather
                        // than sharing a `finally`, which is what let the flag go
                        // uncleared on success back when this path discarded the
                        // form the `finally` was reaching for.
                        .catch(function () {
                            var data = self._importData();
                            if (data) { data.importing = false; }
                            self.notify('Import failed. Please try again.');
                        });
                },

                // Open the orphans tray. The whole orphans page (its scan/delete
                // component and progress overlays) is reused inside the tray.
                //
                // Every open scans again. An orphan list is a statement about what
                // Plex holds right now, so a reopened tray showing the previous
                // scan reads as current while being stale — which invites deleting
                // a poster that has since stopped being an orphan. Reopening is the
                // refresh gesture; there is no separate refresh control.
                openOrphans: function () {
                    var self = this;
                    this.orphansOpen = true;
                    if (this.orphansLoading) { return; }
                    // Re-scan inside the loaded tray rather than loading it again.
                    // Loading it again would re-run Alpine.initTree over the
                    // fragment, and the orphans component binds a window listener
                    // on init that nothing removes — every reopen would leave
                    // another live listener holding a discarded component, and one
                    // that still had a pending delete would fire it on a later,
                    // unrelated confirmation. Re-running the scan touches only what
                    // actually went stale.
                    if (this.orphansLoaded && this._rescanOrphans()) { return; }
                    this.orphansLoading = true;
                    this._loadTray('/orphans', 'orphansBody')
                        .then(function () { self.orphansLoaded = true; })
                        .catch(function () {
                            self.$refs.orphansBody.innerHTML =
                                '<p class="alert" role="alert">Could not load orphans. Open the <a href="/orphans">Orphans page</a> instead.</p>';
                        })
                        .finally(function () { self.orphansLoading = false; });
                },
                // Re-run the scan in the already-loaded orphans tray, driving the
                // component's own loading flag around the call exactly as its
                // init() does so the tray shows its spinner. Returns false when the
                // component cannot be reached — a failed first load leaves an error
                // message where it would have been — so the caller falls back to a
                // full load.
                _rescanOrphans: function () {
                    var root = this.$refs.orphansBody.querySelector('[x-data]');
                    if (!root || !window.Alpine || !window.Alpine.$data) { return false; }
                    var page = window.Alpine.$data(root);
                    if (!page || typeof page.reload !== 'function') { return false; }
                    // A scan already running is the state we want; leave it alone
                    // rather than racing a second one onto the same results node.
                    if (page.loading) { return true; }
                    page.loading = true;
                    page.reload()
                        .catch(function () {
                            page.$refs.results.innerHTML = '<p class="alert" role="alert">Could not check for orphans. Please reload the page.</p>';
                        })
                        .finally(function () { page.loading = false; });
                    return true;
                },

                closeOrphans: function () {
                    this.orphansOpen = false;
                    // Deleting orphans removes posters that may be shown in the
                    // gallery, so refresh the grid when the tray closes.
                    dispatch('gallery:refresh', {});
                },

                // Open the settings tray. The whole /settings page is reused inside
                // it, the same way the import and orphans trays reuse theirs.
                //
                // Fetched on every open, and with none of the care openOrphans takes
                // over re-running Alpine.initTree: settings.html.twig and its form
                // partial carry no Alpine component at all — no x-data, x-model or
                // x-init — so there is nothing on the fragment to re-bind. Check that
                // still holds if the settings form ever gains one.
                //
                // Re-fetched rather than kept because this form does decay, unlike
                // the import form: the library list comes from Plex and can change or
                // start failing, and the superseded-variable notice is read from the
                // environment. A reopen is how you get the current answer.
                openSettings: function () {
                    var self = this;
                    this.settingsOpen = true;
                    if (this.settingsLoading) { return; }
                    this.settingsLoading = true;
                    this._loadTray('/settings', 'settingsBody')
                        .then(function (target) { self._bindSettingsForm(target); })
                        .catch(function () {
                            self.$refs.settingsBody.innerHTML =
                                '<p class="alert" role="alert">Could not load settings. Open the <a href="/settings">Settings page</a> instead.</p>';
                        })
                        .finally(function () { self.settingsLoading = false; });
                },

                // Closing discards the loaded form. A tray reopened after a rejected
                // save should ask the server what the settings are, not redisplay the
                // submission that was refused.
                closeSettings: function () {
                    this.settingsOpen = false;
                    this.settingsSaving = false;
                    if (this.$refs.settingsBody) { this.$refs.settingsBody.innerHTML = ''; }
                },

                // Open the connection tray. The whole /connect page is reused inside
                // it, the same way the import, orphans and settings trays reuse
                // theirs — the connection status was the last entry in the navigation
                // that charged a page load and a "Back to gallery" link for a glance.
                //
                // Fetched on every open. That screen calls the connection status's
                // refresh, which asks the Plex server what it is called, and the
                // connection can have been forgotten in another tab since this page
                // was drawn. See the note on `connectLoaded` above for why there is
                // no caching flag to be tidied in here later.
                //
                // Nothing is bound on the fetched fragment, and that is not an
                // oversight. Its one action — the Disconnect form — is a plain POST
                // that must navigate (see the tray's markup in gallery.html.twig),
                // and its other possible state, the signed-out sign-in panel, carries
                // its own `plexConnection()` component that _injectTray's
                // Alpine.initTree binds like any other fragment. That component ends
                // a completed sign-in by setting window.location, so it needs nothing
                // from the tray either.
                openConnect: function () {
                    var self = this;
                    this.connectOpen = true;
                    if (this.connectLoading) { return; }
                    this.connectLoading = true;
                    this._loadTray('/connect', 'connectBody')
                        .catch(function () {
                            self.$refs.connectBody.innerHTML =
                                '<p class="alert" role="alert">Could not load the Plex connection. Open the <a href="/connect">connection page</a> instead.</p>';
                        })
                        .finally(function () { self.connectLoading = false; });
                },

                // Closing discards the loaded body, as the settings tray does. What
                // it reports is stale the moment it is dismissed, and leaving it in
                // the DOM means a reopen shows the previous connection state for as
                // long as the new fetch takes.
                closeConnect: function () {
                    this.connectOpen = false;
                    if (this.$refs.connectBody) { this.$refs.connectBody.innerHTML = ''; }
                },

                // Route the loaded form's submit through saveSettings. Called again
                // after an invalid save swaps a fresh copy of the form in, because the
                // handler went with the markup it was bound to.
                _bindSettingsForm: function (target) {
                    var self = this;
                    var form = target.querySelector('form[action="/settings"]');
                    if (!form) { return; }
                    form.addEventListener('submit', function (e) {
                        e.preventDefault();
                        self.saveSettings(form);
                    });
                },

                // Save, reading the outcome off the response status.
                //
                // `redirect: 'manual'` is doing real work here, and the branch it
                // produces reads backwards: an OPAQUE REDIRECT IS THE SUCCESS CASE.
                // The controller already answers 302-to-/settings on a valid save and
                // 200-re-rendering-the-form on an invalid one, so the status alone
                // says which happened and no JSON branch had to be added to it.
                //
                // Not following the redirect is the other half. A followed GET
                // /settings would pull the "Settings saved." flash out of the session
                // and throw the response away, so the reload below would land on a
                // gallery with nothing to say. Left unconsumed, the flash is rendered
                // by the reloaded gallery — under the new title and sort, which is
                // where a user should see it.
                //
                // Reload rather than close-and-refresh: these settings change the
                // header (site title) as well as the grid (page size, default sort,
                // article-aware sorting, library exclusions), and gallery:refresh
                // redraws the grid but not the header. A partial update would leave
                // the page half-describing the configuration that was just replaced.
                //
                // An expired session's auth redirect is also a 302 and is read here as
                // a save. The reload then lands on the sign-in screen, which is the
                // right place to be — the same thing submitting the page would do.
                saveSettings: function (form) {
                    var self = this;
                    if (this.settingsSaving) { return; }
                    this.settingsSaving = true;
                    fetch('/settings', {
                        method: 'POST',
                        body: new FormData(form),
                        headers: { 'X-Requested-With': 'fetch' },
                        credentials: 'same-origin',
                        redirect: 'manual',
                    })
                        .then(function (r) {
                            if (r.type === 'opaqueredirect') {
                                self.closeSettings();
                                window.location.reload();
                                return null;
                            }
                            return r.text();
                        })
                        .then(function (html) {
                            // Null is the reload path above; the page is going away.
                            if (html === null) { return; }
                            self._swapSettingsForm(html);
                            self.settingsSaving = false;
                        })
                        .catch(function () {
                            self.settingsSaving = false;
                            self.notify('Could not save settings.');
                        });
                },

                // An invalid save came back as the whole page re-rendered with its
                // errors. It goes into the tray through the same injector the first
                // load used, so the errors land against the fields they belong to and
                // the tray stays open where it was. Scrolled back to the top because
                // the summary alert the form re-renders with sits there.
                _swapSettingsForm: function (html) {
                    if (!this.$refs.settingsBody) { return; }
                    var target = this._injectTray(html, 'settingsBody');
                    this._bindSettingsForm(target);
                    target.scrollTop = 0;
                },

                openChange: function (filename, title, category, linked) {
                    this.change = {
                        open: true,
                        tab: 'upload',
                        filename: filename,
                        title: title,
                        category: category || '',
                        // Known from the card, so the Plex Posters tab can be
                        // disabled without a round trip that could only come
                        // back saying the same thing.
                        linked: !!linked,
                    };
                    this.finder = { loading: false, error: '', notice: '', sections: [] };
                    this.plexPosters = { loading: false, error: '', uploaded: [], available: [] };
                    this.closePreview();
                    // The file and URL inputs are DOM state that no Alpine
                    // binding owns, so dismissing the dialog leaves whatever was
                    // picked or typed sitting in them — and this dialog is one
                    // instance reused for every poster, so the next one opens
                    // holding the last one's input. Clearing on open covers
                    // every way it can be dismissed (backdrop, close, Escape,
                    // drag, a change that failed) with one call.
                    //
                    // The two inputs are cleared by hand rather than by
                    // form.reset(): reset() restores the *default* value of every
                    // field, which would blank the hidden filename that Alpine
                    // binds as a property with no attribute behind it.
                    if (this.$refs.changeFile) { this.$refs.changeFile.value = ''; }
                    if (this.$refs.changeUrl) { this.$refs.changeUrl.value = ''; }
                },
                // The posters the Plex server already holds for this item.
                // Nothing like findPosters()'s outcome handling is needed: a
                // rating key cannot fail to match, be rate limited, or come back
                // partial, so there is one message line and no notice line.
                loadPlexPosters: function () {
                    var self = this;
                    // A poster with no Plex item has no item to ask about, and the
                    // dialog already knows the answer — so asking could only spend a
                    // round trip to be told what it started with, or fail for an
                    // unrelated reason and report "Could not reach Plex" in place of
                    // the real one.
                    //
                    // The tab's own click expression checks this too, and the
                    // duplication is deliberate rather than untidy. What must not
                    // happen is a request, so the refusal belongs where the request
                    // is made; the inline expression is the fragile half, one edit
                    // away from dropping its half of the condition while looking
                    // correct. This one cannot be bypassed by a new caller.
                    if (!this.change.linked) { return; }
                    this.plexPosters = { loading: true, error: '', uploaded: [], available: [] };
                    fetch('/library/' + this.change.category + '/plex-posters?filename=' + encodeURIComponent(this.change.filename),
                        { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                        .then(function (r) { return r.ok ? r.json() : { uploaded: [], available: [], error: 'Could not reach Plex.' }; })
                        .then(function (d) {
                            self.plexPosters = {
                                loading: false,
                                error: d.error || '',
                                uploaded: d.uploaded || [],
                                available: d.available || [],
                            };
                        })
                        .catch(function () {
                            self.plexPosters = { loading: false, error: 'Could not reach Plex.', uploaded: [], available: [] };
                        });
                },
                // A Plex candidate previews at full resolution through the same
                // proxy its thumbnail came through, and carries its signed token
                // so applying can name the image without ever holding a Plex
                // path client-side.
                // One entry point, because the grid mixes both kinds and the
                // user is choosing a poster, not a mechanism.
                //
                // A poster Plex holds previews at full resolution through the
                // proxy and carries its signed token, so applying can name the
                // image without a Plex path ever existing client-side, and
                // reaches Plex by selection — no upload.
                //
                // One Plex has not downloaded is just a URL, so it takes the
                // route a pasted address takes and *is* uploaded, because Plex
                // does not have the image. That difference is real but it is not
                // the user's to think about.
                openPlexPreview: function (candidate) {
                    if (candidate.held) {
                        this.openPreview('/plex-poster-image/' + candidate.ref, 'plex', null, candidate.ref);
                        return;
                    }
                    this.openPreview(candidate.ref, 'url', null);
                },
                findPosters: function () {
                    var self = this;
                    this.finder = { loading: true, error: '', notice: '', sections: [] };
                    fetch('/library/' + this.change.category + '/find-posters?filename=' + encodeURIComponent(this.change.filename),
                        { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                        .then(function (r) { return r.ok ? r.json() : { sections: [], error: 'Search failed.' }; })
                        .then(function (d) {
                            var message = d.error || '';
                            // A partial result is a success that also carries a
                            // warning: it has candidates to show, so its message
                            // goes on the notice line rather than the error line,
                            // which stands in place of the grid.
                            //
                            // Sections are rendered exactly as given: the server
                            // decides both their order and their labels, so this
                            // never learns a provider name and a new one needs no
                            // change here. Within a section the server's order is
                            // the poster source's own ranking.
                            self.finder = {
                                loading: false,
                                error: d.partial ? '' : message,
                                notice: d.partial ? message : '',
                                sections: Array.isArray(d.sections) ? d.sections : [],
                            };
                        })
                        .catch(function () { self.finder = { loading: false, error: 'Search failed.', notice: '', sections: [] }; });
                },

                // The full-screen step, shared by all three tabs: see the image
                // full size, then choose to use it (with a confirm step) or
                // close. Works on desktop and touch alike.
                //
                // Clearing `loaded` before the new src is what keeps the preview
                // from flashing the previous image: one <img> serves every one of
                // them, so a resolved flag left over from the last would reveal
                // this one before it had loaded. Same reasoning as view() in
                // overlayComponent.
                //
                // `file` is kept for an upload because the blob URL is a handle
                // for display, not something that can be posted; the bytes have
                // to travel as the File itself.
                // `token` is the Plex tab's equivalent of `file`: the value that
                // actually gets posted, kept apart from `src` because `src` is a
                // proxy address for display and names nothing the server can act
                // on directly.
                // `page` is the supplying service's own page for the work, and
                // `service` is that service's display name — both passed only by
                // the Find Posters call site. The other three tabs preview an
                // address the user supplied or artwork Plex holds, neither of
                // which has a service to name, so they pass nothing and take the
                // defaults.
                //
                // The name is passed in rather than looked up. The candidate's
                // provider slug is deliberately not in the payload (see
                // ChangePosterController), and resolving one here would be the
                // first place the browser learned a provider — undoing the reason
                // it is withheld. What is passed is the section's own label,
                // which the server already resolved.
                openPreview: function (src, source, file, token, page, service) {
                    this._revokePreviewSrc();
                    this.preview = {
                        open: true,
                        src: src,
                        loaded: false,
                        confirming: false,
                        applying: false,
                        source: source,
                        file: file || null,
                        token: token || '',
                        page: page || '',
                        service: service || '',
                    };
                },
                closePreview: function () {
                    this._revokePreviewSrc();
                    this.preview = { open: false, src: '', loaded: false, confirming: false, applying: false, source: '', file: null, token: '', page: '', service: '' };
                },
                // A blob URL holds its Blob alive until it is revoked, so an
                // upload preview that is merely replaced or closed would leak the
                // whole image. Only the two lifecycle points revoke — never the
                // `finally` of applyPreview, which runs while a failed change is
                // still on screen and would blank the image the user is looking
                // at. Guarded on the source: the other two tabs preview a real
                // URL, and revoking one of those does nothing but would be a lie
                // about what this holds.
                _revokePreviewSrc: function () {
                    if (this.preview && this.preview.source === 'upload' && this.preview.src) {
                        URL.revokeObjectURL(this.preview.src);
                    }
                },
                // Upload tab: preview the picked file from the user's own device.
                // Nothing is sent anywhere until the change is confirmed. Reached
                // only through the form's submit, so `required` has already
                // rejected an empty picker — the guard is for the case where the
                // ref is missing entirely.
                openUploadPreview: function () {
                    var input = this.$refs.changeFile;
                    var file = input && input.files ? input.files[0] : null;
                    if (!file) { return; }
                    this.openPreview(URL.createObjectURL(file), 'upload', file);
                },
                // From URL tab: preview the image at the address the user gave,
                // loaded from its own source. Whether the browser can display it
                // says nothing about whether the server can fetch it, so a
                // failure here does not stand in the way of confirming.
                openUrlPreview: function () {
                    var input = this.$refs.changeUrl;
                    var url = input ? input.value.trim() : '';
                    if (!url) { return; }
                    this.openPreview(url, 'url', null);
                },
                // Applying is never quick, whichever tab it came from: the server
                // either downloads the image at full resolution from its source or
                // takes it up from the browser, and then uploads it to Plex and
                // locks it. Round trips on a multi-megabyte image either way, so
                // the wait is seconds and the user needs to see it.
                //
                // The overlay is raised immediately, with none of the grace
                // period that defers the gallery's dim for view changes. That
                // deferral exists so a tab switch which may resolve from cache
                // does not flicker; this has no fast path to protect, so
                // deferring would only prolong the silence being fixed here.
                //
                // The overlay also physically blocks a second tap, but only
                // once it has painted — hence the guard below, which does not
                // depend on paint timing. Both are kept: the disabled button
                // and the overlay communicate, the guard enforces.
                applyPreview: function () {
                    var self = this;
                    var upload = this.preview.source === 'upload';
                    var plex = this.preview.source === 'plex';
                    var payload = upload ? this.preview.file : (plex ? this.preview.token : this.preview.src);
                    if (!payload) { return; }
                    if (this.preview.applying) { return; }
                    this.preview.applying = true;
                    // Captured before the tray is closed below, so the card
                    // update still knows which poster this applied to.
                    var category = this.change.category;
                    var filename = this.change.filename;
                    // The only thing the source changes is what is posted where.
                    // A found candidate and a pasted address are both a URL for
                    // the server to fetch; a picked file travels as the file; a
                    // Plex candidate travels as its signed token, which the
                    // server resolves back to a path it verified it had signed.
                    var field = upload ? 'poster' : (plex ? 'token' : 'url');
                    var endpoint = upload ? 'upload' : (plex ? 'plex-poster' : 'url');
                    var body = new FormData();
                    body.append('filename', filename);
                    body.append(field, payload);
                    fetch('/library/' + category + '/change/' + endpoint, {
                        method: 'POST',
                        body: body,
                        headers: withCsrf({ 'X-Requested-With': 'fetch' }),
                        credentials: 'same-origin',
                    })
                        .then(function (r) {
                            // Without this the error page is parsed as if it
                            // were a success page and scraped for its alert, so
                            // a change that failed reports as one that worked.
                            if (!r.ok) { throw new Error('Change failed'); }
                            return r.text();
                        })
                        .then(function (html) {
                            var doc = new DOMParser().parseFromString(html, 'text/html');
                            var alert = doc.querySelector('.alert');
                            self.closePreview();
                            self.change.open = false;
                            self.notify(alert ? alert.textContent.trim() : 'Poster updated');
                            // Only a poster that really was replaced needs
                            // showing, and only its own card shows it. The grid
                            // is re-rendered solely when that card is not on
                            // screen to update — a change made from a card the
                            // user can see never disturbs the view.
                            if (posterStored(doc) && !refreshCard(category, filename)) {
                                dispatch('gallery:refresh', {});
                            }
                        })
                        .catch(function () { self.notify('Could not update the poster.'); self.preview.confirming = false; })
                        // Clears on success and failure alike; without it a
                        // failed change would leave the preview stranded behind
                        // an overlay that never lifts.
                        .finally(function () { self.preview.applying = false; });
                },
                copyUrl: function (url) {
                    var self = this;
                    // Drop the cache-busting ?v= — the server ignores it, so a
                    // shared link is cleaner and no less correct without it.
                    var full = window.location.origin + String(url).split('?')[0];
                    navigator.clipboard.writeText(full)
                        .then(function () { self.notify('URL copied to clipboard'); })
                        .catch(function () {});
                },
            });
        });
    });

    // ---- Vanilla enhancement: grid, search, mutations ----
    document.addEventListener('DOMContentLoaded', function () {
        // Before the gallery guard: every page with poster cards needs these
        // revealed, and everything below depends on the gallery root.
        initImages(document);

        var root = document.querySelector('[data-gallery]');
        if (!root) { return; }
        var base = root.getAttribute('data-base');
        var results = root.querySelector('#results');
        var pendingForm = null;

        // On a narrow screen the tab strip scrolls horizontally; center the
        // active tab so it is never left off-screen (e.g. Collections on a phone).
        var tabsEl = root.querySelector('.tabs');
        var activeTab = tabsEl ? tabsEl.querySelector('.tab--active') : null;
        if (tabsEl && activeTab && tabsEl.scrollWidth > tabsEl.clientWidth) {
            tabsEl.scrollLeft = Math.max(0, activeTab.offsetLeft - (tabsEl.clientWidth - activeTab.clientWidth) / 2);
        }

        // Mark the tab whose path matches as active (used on tab switch and when
        // back/forward navigation lands on a different view).
        function syncActiveTab(pathname) {
            if (!tabsEl) { return; }
            var tabLinks = tabsEl.querySelectorAll('.tab');
            for (var i = 0; i < tabLinks.length; i++) {
                tabLinks[i].classList.toggle('tab--active', tabLinks[i].pathname === pathname);
            }
        }

        // The categories in the order they are presented, read from the rendered
        // tabs rather than written out here. The swipe traverses this order, so a
        // hardcoded list would be a second statement of something the template
        // already makes — and the two would disagree the first time a category
        // was added, reordered, or renamed, with the gesture moving to a
        // neighbour the tab bar does not show beside it.
        //
        // Computed once: a no-reload category change toggles the active class but
        // never re-renders the strip, so these paths are fixed for the page's
        // life.
        var categoryPaths = (function () {
            var paths = [];
            if (!tabsEl) { return paths; }
            var links = tabsEl.querySelectorAll('.tab');
            for (var i = 0; i < links.length; i++) {
                if (links[i].pathname) { paths.push(links[i].pathname); }
            }
            return paths;
        }());

        function categoryIndex(pathname) {
            for (var i = 0; i < categoryPaths.length; i++) {
                if (categoryPaths[i] === pathname) { return i; }
            }
            return -1;
        }

        // The category `step` places away, or null when there is none — which is
        // what the ends of the strip are, and what makes a drag there resist
        // rather than commit.
        function neighbourPath(pathname, step) {
            var at = categoryIndex(pathname);
            if (at < 0) { return null; }
            var to = at + step;
            return to >= 0 && to < categoryPaths.length ? categoryPaths[to] : null;
        }

        function setResults(html) {
            results.innerHTML = html;
            initImages(results);
            setupInfinite();
        }

        // ---- Infinite scroll (phone) ----
        // On a narrow screen, pagination is replaced by appending the next page as
        // a sentinel below the grid nears the viewport, so posters keep loading in
        // batches as the user scrolls rather than all at once or a page at a time.
        var infinite = null;
        function isPhone() {
            return !!(window.matchMedia && window.matchMedia('(max-width: 640px)').matches);
        }
        function teardownInfinite() {
            if (infinite && infinite.observer) { infinite.observer.disconnect(); }
            if (infinite && infinite.sentinel && infinite.sentinel.parentNode) { infinite.sentinel.remove(); }
            infinite = null;
        }
        function setupInfinite() {
            teardownInfinite();
            if (!isPhone() || typeof IntersectionObserver === 'undefined') { return; }
            var grid = results.querySelector('.grid');
            if (!grid) { return; }
            var page = parseInt(grid.getAttribute('data-page'), 10) || 1;
            var totalPages = parseInt(grid.getAttribute('data-total-pages'), 10) || 1;
            if (totalPages <= page) { return; }
            var sentinel = document.createElement('div');
            sentinel.className = 'scroll-sentinel';
            sentinel.innerHTML = '<div class="spinner"></div>';
            grid.parentNode.insertBefore(sentinel, grid.nextSibling);
            infinite = { page: page, totalPages: totalPages, grid: grid, sentinel: sentinel, loading: false };
            infinite.observer = new IntersectionObserver(function (entries) {
                if (entries[0].isIntersecting) { loadMore(); }
            }, { rootMargin: '600px 0px' });
            infinite.observer.observe(sentinel);
        }
        function loadMore() {
            if (!infinite || infinite.loading || infinite.page >= infinite.totalPages) { return; }
            infinite.loading = true;
            infinite.sentinel.classList.add('is-busy');
            var next = infinite.page + 1;
            var params = new URLSearchParams(window.location.search);
            params.set('page', next);
            fetch(window.location.pathname + '?' + params.toString(), { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' })
                .then(function (r) { return r.text(); })
                .then(function (html) {
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    var newGrid = doc.querySelector('.grid');
                    if (newGrid) {
                        var frag = document.createDocumentFragment();
                        newGrid.querySelectorAll('.card').forEach(function (card) { frag.appendChild(card); });
                        infinite.grid.appendChild(frag);
                        initImages(infinite.grid);
                    }
                    infinite.page = next;
                })
                .catch(function () {})
                .finally(function () {
                    if (!infinite) { return; }
                    infinite.loading = false;
                    infinite.sentinel.classList.remove('is-busy');
                    if (infinite.page >= infinite.totalPages) {
                        teardownInfinite();
                        return;
                    }
                    // If the appended page did not push the sentinel past the
                    // viewport (a short page, or a tall screen), keep loading so the
                    // grid always fills the screen without a manual scroll.
                    var rect = infinite.sentinel.getBoundingClientRect();
                    if (rect.top < window.innerHeight + 600) { loadMore(); }
                });
        }

        // ---- Deferred loading indication ----
        // The single owner of `is-loading`; nothing else may add or remove it.
        // Counts in-flight view changes rather than tracking a boolean, because
        // submitForm() marks itself busy and then calls load(), which marks
        // itself busy too — with a flag, the inner load() finishing would clear
        // the dim while the outer submit was still settling. The counter also
        // makes overlapping navigations behave: the dim means "something is in
        // flight", not "the last thing started".
        var busy = 0;
        var graceTimer = null;
        var hideTimer = null;
        var shownAt = 0;

        function beginBusy() {
            busy++;
            // A navigation arriving during the minimum-hold window keeps the
            // existing dim; cancel the pending removal so it isn't cleared out
            // from under the new one.
            if (hideTimer) { clearTimeout(hideTimer); hideTimer = null; }
            if (busy > 1 || graceTimer || root.classList.contains('is-loading')) { return; }
            graceTimer = setTimeout(function () {
                graceTimer = null;
                shownAt = Date.now();
                root.classList.add('is-loading');
            }, LOADING_GRACE_MS);
        }

        function endBusy() {
            busy = Math.max(0, busy - 1);
            if (busy > 0) { return; }
            if (graceTimer) {
                // The fast path: resolved inside the grace period, so nothing
                // was ever shown and nothing needs clearing.
                clearTimeout(graceTimer);
                graceTimer = null;
                return;
            }
            if (!root.classList.contains('is-loading')) { return; }
            var remaining = LOADING_MIN_MS - (Date.now() - shownAt);
            if (remaining <= 0) {
                root.classList.remove('is-loading');
                return;
            }
            // Hold the dim out to its minimum so a view change that only just
            // crossed the grace period doesn't flash. Only the dim waits — the
            // results themselves were already swapped in by the caller.
            hideTimer = setTimeout(function () {
                hideTimer = null;
                if (busy === 0) { root.classList.remove('is-loading'); }
            }, remaining);
        }

        function extractResults(doc) {
            var el = doc.querySelector('#results');
            return el ? el.innerHTML : null;
        }
        function extractFlash(doc) {
            var el = doc.querySelector('.alert');
            return el ? el.textContent.trim() : '';
        }
        function extractTitle(doc) {
            var el = doc.querySelector('title');
            var text = el ? el.textContent.trim() : '';
            return text || null;
        }

        // The one place a view is applied: results in, title across, history
        // pushed. Split from the fetch so the same three writes serve a response
        // that has just arrived and one the swipe prefetched minutes ago.
        function applyView(url, push, view) {
            if (view.results !== null) { setResults(view.results); }
            // The server already renders the right title for the view being
            // fetched, so carry it over rather than rebuilding it here. Set
            // before the pushState branch: a back/forward or refresh load changes
            // the view too, and its title must follow.
            if (view.title !== null) { document.title = view.title; }
            if (push) { history.pushState({}, '', url); }
        }

        // Everything a view needs, pulled out of a parsed response in one place
        // so the prefetch and the live fetch cannot extract it differently.
        function viewFrom(html) {
            var doc = new DOMParser().parseFromString(html, 'text/html');
            return { results: extractResults(doc), title: extractTitle(doc) };
        }

        // `prefetched` is a { results, title } already pulled from a response —
        // what the swipe's neighbour cache holds. Given one, there is nothing to
        // wait for, so the view is applied synchronously and no busy indication
        // is raised: the deferred dim reports outstanding work, and there is
        // none. The promise is still returned so every caller keeps one contract.
        function load(url, push, prefetched) {
            if (prefetched) {
                applyView(url, push, prefetched);
                return Promise.resolve();
            }
            beginBusy();
            return fetch(url, { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' })
                .then(function (r) { return r.text(); })
                .then(function (html) { applyView(url, push, viewFrom(html)); })
                .catch(function () {})
                .finally(function () { endBusy(); });
        }

        function currentUrl() {
            return window.location.pathname + window.location.search;
        }

        // A category's URL for the live search box.
        //
        // `sort` is deliberately absent. The server remembers the selected sort
        // in the session (SortPreference::resolve), and the one control that
        // changes it does a full page load — so a category fetched without it
        // comes back in the order the user chose. Adding it here would be a
        // second source for something already answered.
        function categoryUrl(pathname) {
            var q = search ? search.value.trim() : '';
            return pathname + (q ? '?q=' + encodeURIComponent(q) : '');
        }

        // THE ONE WAY TO CHANGE CATEGORY. Both the tab tap and the swipe's commit
        // come through here, and that is the point rather than tidiness: a
        // category change has to leave seven things right — the active tab, the
        // results, the title, a history entry, the carried-over search, the
        // scroll position, and infinite scroll re-armed for the new grid (the
        // last of these inside setResults). Two paths that must agree about seven
        // things will stop agreeing. Anything that changes category calls this.
        //
        // `options.prefetched` is a { results, title } the swipe already holds,
        // which turns the load into three synchronous writes. Absent, this is
        // exactly what tapping a tab has always done.
        function switchCategory(pathname, options) {
            var opts = options || {};
            syncActiveTab(pathname);
            window.scrollTo(0, 0);
            return load(categoryUrl(pathname), true, opts.prefetched || null)
                .then(function () { primeNeighbours(); });
        }

        // ---- The neighbour cache (phone swipe) ----
        // The swipe needs the next category's grid in the frame the gesture is
        // claimed, and that grid comes from the server. So both neighbours are
        // fetched while nothing is happening and held here.
        //
        // AN OPTIMISATION, NEVER A SOURCE OF TRUTH. The gesture is fully correct
        // with this map permanently empty — a miss costs a placeholder for the
        // length of one fetch and nothing else. Nothing here may become the only
        // record of anything.
        //
        // What makes a held copy stale:
        //
        //   the search term   compared directly, below
        //   the library       via `libraryMutations`, bumped on every mutation
        //   the sort order    NOTHING TO DO — see below
        //
        // Sort needs no key. There is exactly one control that changes it and it
        // does a full `window.location.assign()` (the `a[data-sort]` branch of
        // the click handler), so changing sort reloads the page and takes this
        // whole map with it. Adding a sort key would be a second answer to a
        // question the page reload has already settled; don't.
        //
        // The comparison errs toward discarding. Wrongly dropping a good copy
        // costs one fetch nobody needed. Wrongly trusting a stale one shows a
        // grid that does not match the viewer's search — a wrong library that
        // looks like a working one.
        var libraryMutations = 0;
        var neighbourCache = {};

        function liveQuery() {
            return search ? search.value.trim() : '';
        }

        // Bumped rather than reasoned about. A mutation form was submitted, so
        // assume the library moved — including for a `card` refresh, which looks
        // local but is not: the All view holds every category's posters, so
        // changing one movie's poster stales the All copy too.
        function noteLibraryMutation() {
            libraryMutations++;
            neighbourCache = {};
        }

        function cachedView(pathname) {
            var held = neighbourCache[pathname];
            if (!held) { return null; }
            if (held.query !== liveQuery() || held.mutation !== libraryMutations) {
                delete neighbourCache[pathname];
                return null;
            }
            return held.view;
        }

        // Fetch both neighbours of the active category, skipping any already
        // held and current. Called only after the active category has settled,
        // so it never competes with the load the viewer is waiting for.
        function primeNeighbours() {
            if (!isTouch() || categoryPaths.length < 2) { return; }
            var here = window.location.pathname;
            var query = liveQuery();
            var mutation = libraryMutations;
            [-1, 1].forEach(function (step) {
                var path = neighbourPath(here, step);
                if (!path || cachedView(path)) { return; }
                fetch(categoryUrl(path), {
                    headers: { 'X-Requested-With': 'fetch' },
                    credentials: 'same-origin',
                })
                    .then(function (r) { return r.text(); })
                    .then(function (html) {
                        // Stamped with the state it was fetched UNDER, not the
                        // state at the time it lands. A search typed while this
                        // was in flight must not be able to bless a copy that
                        // predates it.
                        neighbourCache[path] = {
                            view: viewFrom(html),
                            query: query,
                            mutation: mutation,
                        };
                    })
                    .catch(function () {});
            });
        }

        function submitForm(form) {
            var action = form.getAttribute('action');
            var data = new FormData(form);
            // How much of the grid this mutation invalidates, declared by the
            // form itself. The filename comes from what is actually being
            // posted rather than a second copy in an attribute, so the card
            // that gets updated is by definition the poster that was acted on.
            var refresh = form.getAttribute('data-refresh');
            var category = form.getAttribute('data-category');
            var filename = data.get('filename');
            // Nests with the load() below: the counter keeps the indication up
            // until both have settled.
            beginBusy();
            fetch(action, {
                method: 'POST',
                body: data,
                headers: withCsrf({ 'X-Requested-With': 'fetch' }),
                credentials: 'same-origin',
            })
                .then(function (r) { return r.text(); })
                .then(function (html) {
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    var flash = extractFlash(doc);
                    // `none` stored nothing, and a `card` mutation that stored
                    // nothing has nothing to re-render either. A `card` mutation
                    // that did store an image rewrites just that card, and falls
                    // through to the grid only when the card is not on screen to
                    // rewrite. Everything else — Delete, and any form that
                    // declares nothing — changes which posters exist, so the
                    // counts and pagination need the full re-render.
                    var handled = refresh === 'none'
                        || (refresh === 'card'
                            && (!posterStored(doc) || refreshCard(category, filename)));
                    if (handled) {
                        if (flash) { dispatch('gallery:toast', { text: flash }); }
                        return null;
                    }
                    // Refresh the grid for the current search/page, then report.
                    return load(currentUrl(), false).then(function () {
                        if (flash) { dispatch('gallery:toast', { text: flash }); }
                    });
                })
                .catch(function () {})
                .finally(function () {
                    endBusy();
                    // Every mutation stales the held neighbours, whatever this
                    // form declared about its own grid. Re-primed below so the
                    // next swipe is fast again rather than merely correct.
                    noteLibraryMutation();
                    primeNeighbours();
                    dispatch('gallery:done', {});
                });
            if (form.reset) { form.reset(); }
        }

        // Live search.
        var search = root.querySelector('input[name="q"]');
        if (search) {
            var searchForm = search.closest('form');
            if (searchForm) { searchForm.addEventListener('submit', function (e) { e.preventDefault(); }); }
            var timer;
            search.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(function () {
                    var q = search.value.trim();
                    // A new result set is a new list, so it is read from the top —
                    // the same reason paging and switching category reset the
                    // scroll. This only became reachable once the toolbar was
                    // pinned: before that, searching meant scrolling up to the
                    // toolbar first, which reset the scroll as a side effect.
                    // Debounced, so it runs once when typing settles rather than
                    // per keystroke, and it is a no-op at the top of the page.
                    scrollToTopOfGallery();
                    // Use the live pathname, not the page-load base: a no-reload tab
                    // switch changes the view without replacing the toolbar.
                    // Re-primed after: the held neighbours are already refused by
                    // the query comparison, but a swipe made straight after a
                    // search should still be quick rather than merely correct.
                    load(window.location.pathname + (q ? '?q=' + encodeURIComponent(q) : ''), true)
                        .then(function () { primeNeighbours(); });
                }, 250);
            });
        }

        // Delegated clicks for card + finder actions and pagination.
        root.addEventListener('click', function (e) {
            // A tray with its own component (orphans, import) handles its own
            // clicks; don't let the gallery's delegation double-handle them.
            if (e.target.closest('[data-nested-scope]')) { return; }
            // Tapping the download link inside the sheet: let it download, close.
            if (e.target.closest('.sheet__body a[download]')) {
                dispatch('gallery:sheet-close', {});
                return;
            }
            var actionEl = e.target.closest('[data-action]');
            if (actionEl && root.contains(actionEl)) {
                var action = actionEl.getAttribute('data-action');
                // Actions can be triggered from the mobile sheet; close it after.
                dispatch('gallery:sheet-close', {});
                if (action === 'view') {
                    e.preventDefault();
                    dispatch('gallery:view', { url: actionEl.getAttribute('data-url') });
                    return;
                }
                if (action === 'copy') {
                    e.preventDefault();
                    dispatch('gallery:copy', { url: actionEl.getAttribute('data-url') });
                    return;
                }
                if (action === 'change') {
                    e.preventDefault();
                    dispatch('gallery:change', {
                        filename: actionEl.getAttribute('data-filename'),
                        title: actionEl.getAttribute('data-title'),
                        category: actionEl.getAttribute('data-category'),
                        linked: actionEl.getAttribute('data-linked') === '1',
                    });
                    return;
                }
            }
            // Sorting must stay on the current view. The sort links are rendered
            // once and go stale after a no-reload tab switch (the toolbar is not
            // re-rendered), so rebuild the URL from the live pathname rather than
            // trusting the link's href, which could still point at the old view.
            var sortLink = e.target.closest('a[data-sort]');
            if (sortLink && root.contains(sortLink)) {
                e.preventDefault();
                var sortQ = search ? search.value.trim() : '';
                // Full navigation so the sort control's active state (in the
                // toolbar, outside #results) re-renders correctly too.
                window.location.assign(
                    window.location.pathname + '?sort=' + encodeURIComponent(sortLink.getAttribute('data-sort'))
                    + (sortQ ? '&q=' + encodeURIComponent(sortQ) : '')
                );
                return;
            }
            // Switching views (tabs) keeps the active search: switchCategory()
            // rebuilds the URL with the live query so the new view opens
            // filtered, and loads it through the same no-reload path used by
            // search, pagination and the swipe.
            var tabLink = e.target.closest('.tabs a');
            if (tabLink && root.contains(tabLink)) {
                e.preventDefault();
                switchCategory(tabLink.pathname);
                return;
            }
            // Clearing the search returns to the full, unfiltered view.
            var clearLink = e.target.closest('.search__clear');
            if (clearLink && root.contains(clearLink)) {
                e.preventDefault();
                if (search) { search.value = ''; }
                load(clearLink.pathname, true);
                return;
            }
            var pageLink = e.target.closest('.pagination a');
            if (pageLink && root.contains(pageLink)) {
                e.preventDefault();
                // Before the fetch, not after the swap: the scroll is immediate
                // feedback that the click landed, and it runs alongside the load
                // rather than after it, so the new grid is usually already in
                // place by the time it settles. Offset 0 is always reachable, so
                // a shorter destination page cannot strand the animation.
                scrollToTopOfGallery();
                load(pageLink.getAttribute('href'), true);
                return;
            }
            // Tapping a poster: on touch, open the action sheet (there is no room
            // for an overlay on a phone); on desktop, open it full screen (the
            // hover overlay already provides the actions).
            var frame = e.target.closest('.card__frame');
            if (frame && root.contains(frame)) {
                if (e.target.closest('.card__actions')) { return; }
                if (isTouch()) {
                    dispatch('gallery:sheet', sheetDetailFor(frame));
                } else {
                    var image = frame.querySelector('.card__image');
                    if (image) { dispatch('gallery:view', { url: image.getAttribute('src') }); }
                }
            }
        });

        // Delegated submit for every AJAX mutation form.
        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (!(form instanceof HTMLFormElement) || !form.classList.contains('js-mutate')) { return; }
            // Forms inside a tray with its own component (orphans) are handled by
            // that component; skip them here.
            if (form.closest('[data-nested-scope]')) { return; }
            e.preventDefault();
            // The form owns its own wording. Every confirmed action states what
            // it is about to do — the two Plex actions move the same image in
            // opposite directions, so a shared "Are you sure?" would not tell a
            // user which button they hit. The fallbacks are Delete's, which is
            // why the Delete form needs no attributes beyond data-confirm.
            //
            // A confirmed action leaves the tray it was raised from standing.
            // The stylesheet already ranks a dialog above a tray for exactly
            // this reason; closing the tray here anyway meant declining a
            // confirmation dismissed the actions behind it too, so a user who
            // answered "no" had to reopen the poster to do anything else.
            if (form.hasAttribute('data-confirm')) {
                pendingForm = form;
                dispatch('gallery:confirm', {
                    title: form.getAttribute('data-confirm-title') || 'Delete poster?',
                    message: form.getAttribute('data-confirm'),
                    label: form.getAttribute('data-confirm-label') || 'Delete',
                    tone: form.getAttribute('data-confirm-tone') || 'danger',
                });
                return;
            }
            // A form may live in the mobile sheet; close it either way.
            dispatch('gallery:sheet-close', {});
            submitForm(form);
        });

        window.addEventListener('gallery:confirmed', function () {
            if (pendingForm) {
                var form = pendingForm;
                pendingForm = null;
                // Now the action is really happening, so the tray that offered
                // it goes — same as an unconfirmed submit.
                dispatch('gallery:sheet-close', {});
                submitForm(form);
            }
        });

        window.addEventListener('popstate', function () {
            // A history entry can be a different view and/or query, so restore
            // the active tab and the search box to match before reloading.
            syncActiveTab(window.location.pathname);
            if (search) {
                var params = new URLSearchParams(window.location.search);
                search.value = params.get('q') || '';
            }
            // Held copies are dropped rather than re-checked. The entry being
            // restored can predate mutations the counter has already moved past,
            // and its query is whatever the URL says rather than what was typed —
            // so the two things that bless a copy are both unreliable here.
            neighbourCache = {};
            // Any gesture still settling belongs to the view being navigated away
            // from, and its panels are pinned out of the scroller.
            endSwipe();
            load(currentUrl(), false).then(function () { primeNeighbours(); });
        });

        // A tray action (import, orphan delete) can change what belongs in the
        // gallery; refresh the current view's grid in place when asked.
        window.addEventListener('gallery:refresh', function () {
            noteLibraryMutation();
            load(currentUrl(), false).then(function () { primeNeighbours(); });
        });

        // ---- The category swipe (touch only) ----
        //
        // A horizontal drag on the gallery moves between adjacent categories.
        // The gesture IS the transition: the panels move because a thumb is
        // moving them, not as an animation played back after the thumb lifts.
        // That distinction is the whole point — a swipe evaluated only at
        // touchend produces the same first frame whether it is going to work or
        // not, so the viewer cannot see that it was recognised, cannot see how
        // far is left, and cannot change their mind.
        //
        // Four phases, and only the second does any real work:
        //
        //   touchstart   record the origin, claim nothing
        //   axis lock    decide the axis once, then set up (measure, pin, fill)
        //   track        two style writes per frame, READ NOTHING
        //   release      commit, abandon, or spring back from a resisted end

        // The live gesture, or null. One object so teardown has one thing to
        // clear and cannot half-finish.
        var swipe = null;
        var swipeAxis = null;
        var swipeOriginX = 0;
        var swipeOriginY = 0;

        function reducedMotion() {
            return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
        }

        // A touch that belongs to something else. Checked at touchstart and never
        // again: refusing at touchend is only adequate while nothing has moved,
        // and a gesture that discovers the conflict later has already suppressed
        // the browser's handling and taken both grids out of the scroller.
        //
        // anyOverlayOpen() is the page scroll lock's own function, deliberately.
        // A second reading of "is an overlay open" would drift from it, and the
        // cost of them disagreeing is this gesture fighting a tray — the tray
        // dismissal drag lives on the other axis of these very touches.
        function swipeRefused(event) {
            if (anyOverlayOpen()) { return true; }
            var target = event.target;
            if (!target || !target.closest) { return false; }
            // A touch on the bottom tab bar belongs to the bar, which is how you
            // tap a category. Not an overflow concern: the phone bar is five
            // equal columns and never scrolls.
            return !!target.closest('.sheet, .modal, .viewer, .overlay, .tabs');
        }

        // The incoming panel, built and parked a viewport away on the side it
        // comes from. Takes the OUTGOING panel's measured box rather than reading
        // its own: it is not in the document yet, so it has no box — and
        // measuring it after the insert would be a forced layout on the
        // gesture's opening frame, which is the frame the viewer is judging.
        function buildIncomingPane(box, held) {
            var pane = document.createElement('div');
            pane.className = 'swipe-pane swipe-shift swipe-pinned';
            pane.setAttribute('data-swipe-pane', '');
            if (held) {
                pane.innerHTML = held.results;
                // Same reveal every other grid gets. Without it a cached panel
                // slides in holding cards whose images never un-hide — the one
                // path where a HIT looks worse than a miss.
                initImages(pane);
            } else {
                var tpl = document.getElementById('swipe-placeholder');
                if (tpl && tpl.content) { pane.appendChild(tpl.content.cloneNode(true)); }
            }
            pinPanel(pane, box.top, box.left, box.width);
            return pane;
        }

        function pinPanel(panel, top, left, width) {
            panel.style.top = top + 'px';
            panel.style.left = left + 'px';
            panel.style.width = width + 'px';
        }

        function setShift(panel, px) {
            if (panel) { panel.style.setProperty('--swipe-x', px + 'px'); }
        }

        // Claim the gesture: measure, pin both panels, fill the incoming one,
        // collapse the page, and mark the destination in the tab bar.
        function beginSwipe(direction) {
            // A gesture that committed and is still settling has a category
            // change owed to it. Land that BEFORE tearing anything down, or a
            // quick second flick discards it — see flushCommit.
            if (swipe) { flushCommit(swipe); }
            endSwipe();

            var here = window.location.pathname;
            var target = neighbourPath(here, direction);
            var scrollY = window.scrollY;

            // THE ONE MEASUREMENT, AND IT MUST STAY ABOVE EVERY WRITE BELOW.
            // Layout is clean at this instant — the browser laid the page out for
            // the last frame and nothing here has written a style yet — so this
            // read is free. The same read after a write forces a synchronous
            // re-layout, and it would land on the gesture's first frame.
            var rect = results.getBoundingClientRect();
            var box = { top: rect.top, left: rect.left, width: rect.width };

            var width = window.innerWidth;
            var held = target ? cachedView(target) : null;

            swipe = {
                from: here,
                to: target,
                direction: direction,
                width: width,
                offset: 0,
                scrollY: scrollY,
                resisted: !target,
                pane: null,
                held: held,
                samples: [{ x: swipeOriginX, t: now() }],
                frame: null,
                timer: null,
                onEnd: null,
                fetching: false,
                committed: false,
                applied: false,
            };

            // On the root element, like the scroll lock's own flag: the panels
            // are `position: fixed`, so an `overflow-x` on a mid-page ancestor
            // does not clip them. Only the viewport's own scroller can.
            document.documentElement.classList.add('is-swiping');
            // HOLD THE DOCUMENT'S HEIGHT. Pinning #results takes the tallest
            // thing on the page out of the flow, and a document that collapses
            // below the viewer's scroll offset is clamped to 0 by the browser —
            // which drops the sticky toolbar from the viewport top down to its
            // resting place under the header. Vertical movement, in a horizontal
            // gesture, on every drag begun below the fold.
            //
            // The flow loses exactly the panel's height, so putting that height
            // back as padding preserves it. Costs no extra measurement: the rect
            // this reads was taken above, in the same clean-layout window.
            root.style.paddingBottom = rect.height + 'px';
            results.classList.add('swipe-shift', 'swipe-pinned');
            // The outgoing panel renders exactly where it already was. Its box was
            // measured relative to the viewport, so pinning it at that top holds
            // it still at the instant it leaves the scroller.
            pinPanel(results, box.top, box.left, box.width);
            setShift(results, 0);

            if (!target) {
                // A drag off the end of the strip. Nothing arrives, so nothing is
                // built — but the outgoing panel still moves, damped, because a
                // gesture that did nothing at all is indistinguishable from one
                // the application failed to recognise.
                return;
            }

            // The incoming panel shows its OWN top — it is a fresh category,
            // opening at its first page. That is `box.top + scrollY`: the
            // document offset of the results region, which is where it would sit
            // with the page at the top.
            //
            // Both panels are `position: fixed`, so these are viewport
            // coordinates and the page's actual scroll offset does not enter into
            // either of them. The outgoing panel holds the position it already
            // occupied; the incoming one shows its beginning. Nothing has to be
            // scrolled for that to be true, which is why the page is left exactly
            // where the viewer had it.
            var pane = buildIncomingPane(
                { top: box.top + scrollY, left: box.left, width: box.width },
                held
            );
            setShift(pane, parkOffset(direction, width));
            results.parentNode.insertBefore(pane, results.nextSibling);
            swipe.pane = pane;

            // The bar marks the destination NOW, not at release. It is the
            // application's acknowledgement that the gesture was recognised, and
            // that is owed at the start of a gesture rather than the end of it.
            syncActiveTab(target);

            if (!held) { fetchIncoming(target); }
        }

        // Where the incoming panel rests before it starts arriving: a FULL
        // viewport out, on the side it comes from.
        //
        // A full viewport and not a fraction of one, so the two panels are edge
        // to edge and never overlap. Parking it closer and moving it slower — the
        // familiar platform parallax — puts the two grids on top of each other
        // for the whole gesture, and which one is drawn above the other is then
        // decided by document order rather than by the direction of travel. It
        // reads as one direction winning every time.
        function parkOffset(direction, width) {
            return direction > 0 ? width : -width;
        }

        // The cache missed, so the panel is showing a placeholder. Replace it in
        // place when the results land, without touching the panel's offset —
        // the gesture is still running and the finger is still down.
        function fetchIncoming(path) {
            var live = swipe;
            live.fetching = true;
            fetch(categoryUrl(path), {
                headers: { 'X-Requested-With': 'fetch' },
                credentials: 'same-origin',
            })
                .then(function (r) { return r.text(); })
                .then(function (html) {
                    var view = viewFrom(html);
                    neighbourCache[path] = {
                        view: view,
                        query: liveQuery(),
                        mutation: libraryMutations,
                    };
                    // The gesture may have ended, or a second one may have
                    // replaced this record, while this was in flight.
                    if (swipe !== live) { return; }
                    live.held = view;
                    if (live.pane && view.results !== null) {
                        live.pane.innerHTML = view.results;
                        initImages(live.pane);
                    }
                })
                .catch(function () {})
                .finally(function () { if (swipe === live) { live.fetching = false; } });
        }

        function now() {
            return window.performance && window.performance.now
                ? window.performance.now() : Date.now();
        }

        // One write pass per frame, however many moves arrived. touchmove fires
        // at the digitiser's rate, which on a 120Hz panel is twice the frame
        // rate, so writing per move schedules style work the browser discards.
        //
        // READS NOTHING. Every value it needs — the origin, the width, the park
        // offset — was captured when the gesture was claimed. A layout read here
        // would be paid on every frame of the drag rather than once.
        function trackSwipe(x) {
            if (!swipe) { return; }
            var live = swipe;
            live.offset = x - swipeOriginX;

            var t = now();
            live.samples.push({ x: x, t: t });
            while (live.samples.length > 2 && t - live.samples[0].t > SWIPE_VELOCITY_WINDOW_MS) {
                live.samples.shift();
            }

            if (live.frame !== null) { return; }
            live.frame = window.requestAnimationFrame(function () {
                live.frame = null;
                if (swipe !== live) { return; }
                if (live.resisted) {
                    setShift(results, live.offset * SWIPE_RESIST_DAMPING);
                    return;
                }
                // The pair moves as one strip: the outgoing panel's trailing edge
                // and the incoming panel's leading edge stay exactly a viewport
                // apart, so one is always replacing the other rather than
                // covering it.
                var park = parkOffset(live.direction, live.width);
                setShift(results, live.offset);
                setShift(live.pane, park + live.offset);
            });
        }

        function swipeVelocity(live) {
            var s = live.samples;
            if (s.length < 2) { return 0; }
            var first = s[0];
            var last = s[s.length - 1];
            var elapsed = last.t - first.t;
            return elapsed > 0 ? (last.x - first.x) / elapsed : 0;
        }

        // Distance OR velocity, and NEVER LATCHED. A drag that passed the
        // threshold and came back is an abandon — latching would mean the panels
        // keep following a finger whose gesture has already been decided, which
        // is the thing a drag exists to avoid.
        function swipeCommits(live) {
            if (live.resisted) { return false; }
            // `direction` is +1 for the neighbour to the right, which arrives when
            // the finger moves LEFT. A drag the other way is not toward it.
            var toward = live.direction > 0 ? live.offset < 0 : live.offset > 0;
            if (!toward) { return false; }
            if (Math.abs(live.offset) >= live.width * SWIPE_COMMIT_FRACTION) { return true; }
            var v = swipeVelocity(live);
            var flicking = live.direction > 0 ? v < 0 : v > 0;
            return flicking && Math.abs(v) >= SWIPE_FLICK_VELOCITY;
        }

        // Timed from what is left to travel, not fixed. A panel released at 95%
        // would otherwise spend the full duration crossing the last sliver, and
        // one released at 5% would cover nearly the whole viewport in it.
        function settleDuration(remaining, width) {
            if (reducedMotion()) { return 0; }
            var fraction = width > 0 ? Math.min(1, Math.abs(remaining) / width) : 1;
            return Math.max(SWIPE_SETTLE_MIN_MS, Math.round(swipeSettleMaxMs() * fraction));
        }

        function settleSwipe() {
            if (!swipe) { return; }
            var live = swipe;

            if (live.frame !== null) {
                window.cancelAnimationFrame(live.frame);
                live.frame = null;
            }

            var commit = swipeCommits(live);
            var target = commit ? (live.direction > 0 ? -live.width : live.width) : 0;
            // Timed from where the panel actually IS, which for a resisted drag
            // is the damped fraction of the travel rather than the travel itself.
            // Measuring the undamped distance would spend a long settle crossing
            // a short gap.
            var at = live.resisted ? live.offset * SWIPE_RESIST_DAMPING : live.offset;
            var duration = settleDuration(target - at, live.width);

            // The finger is off the glass, so the panels stop tracking it and
            // start animating. Under reduced motion this duration is 0: the
            // viewer is no longer moving them, so the app-wide suppression
            // applies to the part the app performs on its own.
            [results, live.pane].forEach(function (panel) {
                if (!panel) { return; }
                // Overrides the cap in .swipe-settling. Inline, so the app-wide
                // reduced-motion rule — which is !important — still beats it.
                panel.style.transitionDuration = duration + 'ms';
                panel.classList.add('swipe-settling');
            });

            if (commit) {
                setShift(results, target);
                setShift(live.pane, 0);
                finishSwipe(live, duration);
                return;
            }

            // Abandoned. Both panels go home, the bar marks the category the
            // viewer never left, and the scroll position captured at setup is
            // restored by the teardown.
            setShift(results, 0);
            if (live.pane) {
                setShift(live.pane, parkOffset(live.direction, live.width));
                syncActiveTab(live.from);
            }
            armSwipeEnd(live, duration);
        }

        // Wait out the settle, then tear down. Both a transitionend and a timer,
        // because a transition that never starts — a zero duration under reduced
        // motion, or a panel already at its target — fires no event at all.
        function armSwipeEnd(live, duration) {
            var done = function () {
                if (swipe === live) { endSwipe(); }
            };
            if (duration <= 0) {
                live.timer = window.setTimeout(done, 0);
                return;
            }
            live.onEnd = function (e) {
                if (e.target === results && e.propertyName === 'transform') { done(); }
            };
            results.addEventListener('transitionend', live.onEnd);
            live.timer = window.setTimeout(done, duration + 120);
        }

        // The commit. The incoming HTML is handed to switchCategory() as a
        // pre-fetched body, so the swipe and the tab tap change category through
        // ONE routine rather than two that must agree about seven things.
        function finishSwipe(live, duration) {
            // Marked before the teardown reads it: a committed gesture must NOT
            // have its old scroll position restored — switchCategory is about to
            // put the new category at its top, which is where a category change
            // has always left the viewer.
            live.committed = true;
            // Deferred to the end of the settle so the swap lands once the
            // panels have stopped moving; #results is the panel sliding off, and
            // rewriting it mid-slide is visible.
            window.setTimeout(function () { flushCommit(live); }, Math.max(duration, 0));
        }

        // Apply a committed gesture's category change, exactly once.
        //
        // Idempotent, and called from TWO places, which is the point. The settle
        // timer is the ordinary one. The other is a second drag starting while
        // this one is still settling — a real case at three interior categories,
        // where flicking through the strip is the natural way to travel. Without
        // this the new gesture's teardown would drop the record, the pending
        // timer would find `swipe !== live` and bail, and the commit would be
        // lost silently: the panels have animated, the tab bar is already marking
        // the destination, and #results still holds the category before it. The
        // bar would name a category that was never loaded.
        function flushCommit(live) {
            if (!live.committed || live.applied || swipe !== live) { return; }
            live.applied = true;
            // Read now rather than at release: a cache miss whose fetch landed
            // during the settle commits as instantly as a hit.
            var to = live.to;
            var held = live.held;
            // Teardown first — switchCategory writes into #results, which has to
            // be back in the document's flow before it does. It also re-primes
            // the neighbours, so a miss needs nothing extra here.
            endSwipe();
            switchCategory(to, { prefetched: held });
        }

        // ONE teardown, safe to call repeatedly, clearing everything the gesture
        // set. A drag takes both grids out of the document's scroller; leaving
        // that in place because a touch was cancelled by an incoming call hands
        // the viewer a page that will not scroll, with nothing on screen to
        // explain it. Every exit runs through here.
        function endSwipe() {
            var live = swipe;
            if (!live) { return; }
            swipe = null;

            if (live.frame !== null) { window.cancelAnimationFrame(live.frame); }
            if (live.timer !== null) { window.clearTimeout(live.timer); }
            if (live.onEnd) { results.removeEventListener('transitionend', live.onEnd); }
            if (live.pane && live.pane.parentNode) { live.pane.parentNode.removeChild(live.pane); }

            results.classList.remove('swipe-shift', 'swipe-pinned', 'swipe-settling');
            results.style.removeProperty('--swipe-x');
            results.style.transitionDuration = '';
            results.style.top = '';
            results.style.left = '';
            results.style.width = '';
            root.style.paddingBottom = '';
            document.documentElement.classList.remove('is-swiping');

            // NO SCROLL RESTORE, and its absence is the point rather than an
            // omission. The gesture holds the document's height while the panels
            // are pinned and never scrolls the page, so an abandoned drag returns
            // the viewer to a position they were never moved from. A restore here
            // would be a write with nothing to correct — and the moment one
            // exists, the height that makes it unnecessary becomes safe-looking
            // to remove.
            //
            // A committed drag is scrolled to the top by switchCategory, exactly
            // as tapping a tab always has been.

            // The grid that is on screen may not be the one infinite scroll was
            // last wired for.
            setupInfinite();
        }

        if (isTouch()) {
            root.addEventListener('touchstart', function (e) {
                swipeAxis = null;
                // More than one contact point is a pinch or a zoom, and belongs to
                // the browser.
                if (e.touches.length !== 1 || swipeRefused(e)) {
                    swipeAxis = 'y';
                    return;
                }
                swipeOriginX = e.touches[0].clientX;
                swipeOriginY = e.touches[0].clientY;
                // Nothing else. A tap and a vertical scroll must both stay free.
            }, { passive: true });

            // NON-PASSIVE FROM THE OUTSET, and that is load-bearing rather than
            // cautious. preventDefault() has to be available on the FIRST move
            // that crosses the lock distance: a listener registered passive cannot
            // call it at all, and on iOS a touch sequence whose early moves went
            // uncancelled has already been given to the scroller, where later
            // attempts to cancel it are ignored SILENTLY. The gesture then works
            // everywhere except the platform it was written for, with nothing in
            // the console to say so. Do not "optimise" this to passive.
            root.addEventListener('touchmove', function (e) {
                if (swipeAxis === 'y' || e.touches.length !== 1) { return; }
                var x = e.touches[0].clientX;
                var y = e.touches[0].clientY;

                if (swipeAxis === null) {
                    var dx = x - swipeOriginX;
                    var dy = y - swipeOriginY;
                    if (Math.abs(dx) < SWIPE_AXIS_LOCK_PX && Math.abs(dy) < SWIPE_AXIS_LOCK_PX) {
                        return;
                    }
                    // DECIDED ONCE, then held for the life of the touch. A gesture
                    // that re-arbitrates mid-drag can hand a moving page back to
                    // the scroller halfway through.
                    if (Math.abs(dy) >= Math.abs(dx)) {
                        swipeAxis = 'y';
                        return;
                    }
                    swipeAxis = 'x';
                    // Finger moving left reveals the category to the RIGHT.
                    beginSwipe(dx < 0 ? 1 : -1);
                }

                e.preventDefault();
                trackSwipe(x);
            }, { passive: false });

            root.addEventListener('touchend', function () {
                if (swipeAxis === 'x') { settleSwipe(); }
                swipeAxis = null;
            }, { passive: true });

            // A system gesture, an incoming call or an app switch ends a touch
            // without a touchend. Without this the panels stay pinned out of the
            // scroller and the page simply stops scrolling.
            root.addEventListener('touchcancel', function () {
                if (swipeAxis === 'x') { settleSwipe(); }
                swipeAxis = null;
            }, { passive: true });

            // A rotation or a resize invalidates every measurement the gesture is
            // running on — the viewport width it parks against most of all — so
            // the drag is resolved rather than continued against stale numbers.
            window.addEventListener('resize', function () {
                if (swipe) { swipeAxis = null; endSwipe(); }
            });
            window.addEventListener('orientationchange', function () {
                if (swipe) { swipeAxis = null; endSwipe(); }
            });
        }

        // Wire infinite scroll for the server-rendered first page.
        setupInfinite();
        // Warm both neighbours for the server-rendered view, so the first swipe
        // of a session is as quick as every later one.
        primeNeighbours();
    });
})();
