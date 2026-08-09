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

    // ---- Page scroll lock while an overlay is open ----
    // Overlays are fixed layers over a document that is otherwise still live, so
    // without this the page scrolls behind them: a drag on a backdrop has nothing
    // of its own to scroll and chains straight to the document.
    //
    // There is no single "an overlay is open" flag to watch. Open state is spread
    // across galleryUI (sheet, confirm, change, sort, import, orphans, viewer),
    // orphansPage, and the standalone menuOpen scope on the topbar — and the
    // orphans tray injects further overlays at runtime. So rather than wiring each
    // one, watch the DOM for the inline `display` that x-show writes, the same way
    // the drag gesture above stays agnostic of which scope owns a tray.
    (function () {
        var body = document.body;
        var scrollY = 0;
        var locked = false;
        var queued = false;

        // An overlay that is transitioning out does not count. x-transition keeps
        // the element displayed for the length of the leave animation, so without
        // this the page stays pinned for an extra beat after every dismissal —
        // the user closes a dialog, flicks to scroll, and the first flick is
        // swallowed. The class is the one app.css uses to fade the overlay out,
        // and it is also what makes the dying overlay stop taking clicks.
        function anyOverlayOpen() {
            var overlays = document.querySelectorAll('.sheet, .modal, .viewer');
            for (var i = 0; i < overlays.length; i++) {
                if (overlays[i].style.display === 'none') { continue; }
                if (overlays[i].classList.contains('overlay-closing')) { continue; }
                return true;
            }
            return false;
        }

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
    // On a touch device the "Import from Plex" and "Orphans" links open in a tray
    // over the gallery instead of navigating, but only on a page that actually has
    // the gallery (and its trays). Elsewhere, and on pointer devices, they navigate
    // normally.
    document.addEventListener('click', function (e) {
        var link = e.target.closest('a[data-import], a[data-orphans]');
        if (!link) { return; }
        if (!isTouch() || !document.querySelector('[data-gallery]')) { return; }
        e.preventDefault();
        dispatch(link.hasAttribute('data-import') ? 'gallery:import' : 'gallery:orphans', {});
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
                finder: { loading: false, error: '', notice: '', results: [] },
                // The full-screen step every tab commits through: inspect the
                // image, offer to use it, then confirm. `source` says where the
                // image came from and so which request applying it makes;
                // `file` is the picked File for an upload, held here because the
                // blob URL in `src` is for display only and the input it came
                // from may have been cleared by the time it is posted.
                // `applying` is the in-flight flag for the final "Change poster"
                // confirm, driving the progress overlay and the disabled button.
                preview: { open: false, src: '', loaded: false, confirming: false, applying: false, source: '', file: null },
                sortOpen: false,
                importOpen: false,
                importLoading: false,
                importLoaded: false,
                orphansOpen: false,
                orphansLoading: false,
                orphansLoaded: false,

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
                        .then(function (html) {
                            var doc = new DOMParser().parseFromString(html, 'text/html');
                            var content = doc.querySelector('main.container');
                            var target = self.$refs[ref];
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
                        });
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

                openChange: function (filename, title, category) {
                    this.change = { open: true, tab: 'upload', filename: filename, title: title, category: category || '' };
                    this.finder = { loading: false, error: '', notice: '', results: [] };
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
                findPosters: function () {
                    var self = this;
                    this.finder = { loading: true, error: '', notice: '', results: [] };
                    fetch('/library/' + this.change.category + '/find-posters?filename=' + encodeURIComponent(this.change.filename),
                        { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                        .then(function (r) { return r.ok ? r.json() : { posters: [], error: 'Search failed.' }; })
                        .then(function (d) {
                            var message = d.error || '';
                            // A partial result is a success that also carries a
                            // warning: it has candidates to show, so its message
                            // goes on the notice line rather than the error line,
                            // which stands in place of the grid. Results are kept
                            // in the order the server ranked them.
                            self.finder = {
                                loading: false,
                                error: d.partial ? '' : message,
                                notice: d.partial ? message : '',
                                results: Array.isArray(d.posters) ? d.posters : [],
                            };
                        })
                        .catch(function () { self.finder = { loading: false, error: 'Search failed.', notice: '', results: [] }; });
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
                openPreview: function (src, source, file) {
                    this._revokePreviewSrc();
                    this.preview = {
                        open: true,
                        src: src,
                        loaded: false,
                        confirming: false,
                        applying: false,
                        source: source,
                        file: file || null,
                    };
                },
                closePreview: function () {
                    this._revokePreviewSrc();
                    this.preview = { open: false, src: '', loaded: false, confirming: false, applying: false, source: '', file: null };
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
                    var payload = upload ? this.preview.file : this.preview.src;
                    if (!payload) { return; }
                    if (this.preview.applying) { return; }
                    this.preview.applying = true;
                    // Captured before the tray is closed below, so the card
                    // update still knows which poster this applied to.
                    var category = this.change.category;
                    var filename = this.change.filename;
                    // The only thing the source changes is what is posted where.
                    // A found candidate and a pasted address are both a URL for
                    // the server to fetch; a picked file travels as the file.
                    var body = new FormData();
                    body.append('filename', filename);
                    body.append(upload ? 'poster' : 'url', payload);
                    fetch('/library/' + category + '/change/' + (upload ? 'upload' : 'url'), {
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

        function load(url, push) {
            beginBusy();
            return fetch(url, { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' })
                .then(function (r) { return r.text(); })
                .then(function (html) {
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    var inner = extractResults(doc);
                    if (inner !== null) { setResults(inner); }
                    // The server already renders the right title for the view
                    // being fetched, so carry it over rather than rebuilding it
                    // here. Set before the pushState branch: a back/forward or
                    // refresh load changes the view too, and its title must
                    // follow.
                    var title = extractTitle(doc);
                    if (title !== null) { document.title = title; }
                    if (push) { history.pushState({}, '', url); }
                })
                .catch(function () {})
                .finally(function () { endBusy(); });
        }

        function currentUrl() {
            return window.location.pathname + window.location.search;
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
                    load(window.location.pathname + (q ? '?q=' + encodeURIComponent(q) : ''), true);
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
            // Switching views (tabs) keeps the active search: rebuild the tab's
            // URL with the live query so the new view opens filtered, and load it
            // through the same no-reload path used for search and pagination.
            var tabLink = e.target.closest('.tabs a');
            if (tabLink && root.contains(tabLink)) {
                e.preventDefault();
                var tabQuery = search ? search.value.trim() : '';
                syncActiveTab(tabLink.pathname);
                window.scrollTo(0, 0);
                load(tabLink.pathname + (tabQuery ? '?q=' + encodeURIComponent(tabQuery) : ''), true);
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
            load(currentUrl(), false);
        });

        // A tray action (import, orphan delete) can change what belongs in the
        // gallery; refresh the current view's grid in place when asked.
        window.addEventListener('gallery:refresh', function () { load(currentUrl(), false); });

        // Wire infinite scroll for the server-rendered first page.
        setupInfinite();
    });
})();
