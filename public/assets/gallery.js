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

    // ---- Shared overlay behavior ----
    // The fullscreen viewer, confirm dialog, mobile action tray, and toast are
    // identical on every page that shows poster cards. This factory is spread
    // into each page's Alpine root (galleryUI, orphansPage) so they share one
    // implementation and can include the same overlay markup partial.
    function overlayComponent() {
        return {
            viewer: null,
            confirm: { open: false, title: '', message: '', label: 'Confirm' },
            sheet: { open: false, title: '', actions: '' },
            toast: { show: false, text: '' },
            _toastTimer: null,

            view: function (url) {
                if (url) { this.viewer = url; }
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
                this._toastTimer = setTimeout(function () { self.toast.show = false; }, 2400);
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
    // Every tray uses the .sheet markup and closes when its .sheet__backdrop is
    // clicked. A downward drag that starts on the grab handle or the head (not the
    // scrollable body) dismisses the sheet by reusing that same backdrop close, so
    // the gesture works for every tray — poster actions, menu, sort, import —
    // without knowing which Alpine scope owns it.
    // Applies to both trays (.sheet, dragged from the grab handle or head) and the
    // app-style mobile modals (.modal, dragged from the head above its handle),
    // each dismissed by clicking its own backdrop.
    (function () {
        var drag = null;
        document.addEventListener('touchstart', function (e) {
            var grip = e.target.closest('.sheet__grip, .sheet__head, .modal__head');
            var panel = grip ? grip.closest('.sheet__panel, .modal__panel') : null;
            // A modal draws its grab handle as a ::before on the panel, so also let
            // a drag start on the panel's own top area (a direct touch on it).
            if (!panel && e.target.classList && e.target.classList.contains('modal__panel')) {
                panel = e.target;
            }
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
                            var toolbar = target.querySelector('.toolbar');
                            self.count = toolbar ? (parseInt(toolbar.getAttribute('data-count'), 10) || 0) : 0;
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
                        // The form may live in the tray; close it either way.
                        self.closeSheet();
                        if (form.hasAttribute('data-confirm')) {
                            self._pendingForm = form;
                            self.askConfirm({
                                title: 'Delete orphan?',
                                message: form.getAttribute('data-confirm'),
                                label: 'Delete',
                            });
                            return;
                        }
                        self.submitDelete(form);
                    });

                    window.addEventListener('gallery:confirmed', function () {
                        if (self._pendingForm) {
                            var form = self._pendingForm;
                            self._pendingForm = null;
                            self.submitDelete(form);
                        }
                    });
                },

                // Delete one orphan. On success just drop its card and adjust the
                // count — the others are unaffected, so re-scanning Plex would only
                // stall the page for no gain; the next page open scans fresh.
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
                        headers: { 'X-Requested-With': 'fetch' },
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
                        headers: { 'X-Requested-With': 'fetch' },
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
                    var toolbar = target.querySelector('.toolbar');
                    if (toolbar) {
                        toolbar.setAttribute('data-count', String(this.count));
                        var stats = toolbar.querySelector('.stats');
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
        window.Alpine.data('galleryUI', function () {
            return Object.assign(overlayComponent(), {
                change: { open: false, tab: 'upload', filename: '', title: '', category: '' },
                finder: { loading: false, error: '', notice: '', results: [], preview: null, confirming: false },
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

                // Run the import in place: the form's @submit already shows its
                // spinner (contained to the tray); on completion close the tray,
                // report the summary, and refresh the gallery grid behind it.
                runImport: function (form) {
                    var self = this;
                    fetch('/plex/import', {
                        method: 'POST',
                        body: new FormData(form),
                        headers: { 'X-Requested-With': 'fetch' },
                        credentials: 'same-origin',
                    })
                        .then(function (r) { return r.text(); })
                        .then(function (html) {
                            var doc = new DOMParser().parseFromString(html, 'text/html');
                            var alert = doc.querySelector('.alert');
                            self.importOpen = false;
                            // Discard the cached form so reopening starts fresh.
                            self._resetImport();
                            self.notify(alert ? alert.textContent.trim() : 'Import complete.');
                            dispatch('gallery:refresh', {});
                        })
                        .catch(function () { self.notify('Import failed. Please try again.'); })
                        .finally(function () {
                            if (window.Alpine && self._importForm) {
                                try { window.Alpine.$data(self._importForm).importing = false; } catch (e) { /* form gone */ }
                            }
                        });
                },

                // Open the orphans tray. The whole orphans page (its scan/delete
                // component and progress overlays) is reused inside the tray.
                openOrphans: function () {
                    var self = this;
                    this.orphansOpen = true;
                    if (this.orphansLoaded || this.orphansLoading) { return; }
                    this.orphansLoading = true;
                    this._loadTray('/orphans', 'orphansBody')
                        .then(function () { self.orphansLoaded = true; })
                        .catch(function () {
                            self.$refs.orphansBody.innerHTML =
                                '<p class="alert" role="alert">Could not load orphans. Open the <a href="/orphans">Orphans page</a> instead.</p>';
                        })
                        .finally(function () { self.orphansLoading = false; });
                },
                closeOrphans: function () {
                    this.orphansOpen = false;
                    // Deleting orphans removes posters that may be shown in the
                    // gallery, so refresh the grid when the tray closes.
                    dispatch('gallery:refresh', {});
                },

                openChange: function (filename, title, category) {
                    this.change = { open: true, tab: 'upload', filename: filename, title: title, category: category || '' };
                    this.finder = { loading: false, error: '', notice: '', results: [], preview: null, confirming: false };
                },
                findPosters: function () {
                    var self = this;
                    this.finder = { loading: true, error: '', notice: '', results: [], preview: null, confirming: false };
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
                                preview: null,
                                confirming: false,
                            };
                        })
                        .catch(function () { self.finder = { loading: false, error: 'Search failed.', notice: '', results: [], preview: null, confirming: false }; });
                },

                // Find Posters preview: tap a candidate to see it full screen, then
                // choose to use it (with a confirm step) or close. Replaces the old
                // inline Select/View buttons; works on desktop and touch alike.
                openFinderPreview: function (url) {
                    this.finder.preview = url;
                    this.finder.confirming = false;
                },
                closeFinderPreview: function () {
                    this.finder.preview = null;
                    this.finder.confirming = false;
                },
                applyFinderSelection: function () {
                    var self = this;
                    var url = this.finder.preview;
                    if (!url) { return; }
                    var body = new FormData();
                    body.append('filename', this.change.filename);
                    body.append('url', url);
                    fetch('/library/' + this.change.category + '/change/url', {
                        method: 'POST',
                        body: body,
                        headers: { 'X-Requested-With': 'fetch' },
                        credentials: 'same-origin',
                    })
                        .then(function (r) { return r.text(); })
                        .then(function (html) {
                            var doc = new DOMParser().parseFromString(html, 'text/html');
                            var alert = doc.querySelector('.alert');
                            self.closeFinderPreview();
                            self.change.open = false;
                            self.notify(alert ? alert.textContent.trim() : 'Poster updated');
                            dispatch('gallery:refresh', {});
                        })
                        .catch(function () { self.notify('Could not update the poster.'); self.finder.confirming = false; });
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
            // Nests with the load() below: the counter keeps the indication up
            // until both have settled.
            beginBusy();
            fetch(action, {
                method: 'POST',
                body: data,
                headers: { 'X-Requested-With': 'fetch' },
                credentials: 'same-origin',
            })
                .then(function (r) { return r.text(); })
                .then(function (html) {
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    var flash = extractFlash(doc);
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
            // A form may live in the mobile sheet; close it either way.
            dispatch('gallery:sheet-close', {});
            if (form.hasAttribute('data-confirm')) {
                pendingForm = form;
                dispatch('gallery:confirm', {
                    title: 'Delete poster?',
                    message: form.getAttribute('data-confirm'),
                    label: 'Delete',
                });
                return;
            }
            submitForm(form);
        });

        window.addEventListener('gallery:confirmed', function () {
            if (pendingForm) {
                var form = pendingForm;
                pendingForm = null;
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
