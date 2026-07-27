// Gallery enhancement: live search, background grid refresh, no-reload poster
// mutations, lazy-load fade-in, and the Alpine-driven overlays (change modal,
// fullscreen viewer, confirm dialog, toast). Cards render as plain HTML with
// data-* hooks so the grid can be swapped freely and handled by delegation.
// Loaded on every page: the lazy-load fade-in applies wherever poster cards
// render, while the rest activates only on a page with a [data-gallery] root.
(function () {
    'use strict';

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
    // caption.
    function sheetDetailFor(frame) {
        var actions = frame.querySelector('.card__actions');
        var card = frame.closest('.card');
        var caption = card ? card.querySelector('.card__caption') : null;
        return {
            title: caption ? caption.textContent.trim() : '',
            actions: actions ? actions.outerHTML : '',
        };
    }

    // ---- Alpine component: the orphans page ----
    // The shell renders instantly with the spinner up (loading), then fetches
    // the slow orphan scan and swaps the result in. The delete-all overlay
    // lives in the shell; the fetched fragment is plain HTML, so its "Delete
    // all" button and count are wired here rather than through injected Alpine.
    document.addEventListener('alpine:init', function () {
        window.Alpine.data('orphansPage', function (configured) {
            return Object.assign(overlayComponent(), {
                loading: !!configured,
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
                        .catch(function () { self.notify('That orphan could not be deleted.'); });
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
                        target.innerHTML = '<div class="panel"><p>No orphaned posters found. Your library is in sync with Plex.</p></div>';
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
                finder: { loading: false, error: '', results: [] },

                openChange: function (filename, title, category) {
                    this.change = { open: true, tab: 'upload', filename: filename, title: title, category: category || '' };
                    this.finder = { loading: false, error: '', results: [] };
                },
                findPosters: function () {
                    var self = this;
                    this.finder = { loading: true, error: '', results: [] };
                    fetch('/library/' + this.change.category + '/find-posters?filename=' + encodeURIComponent(this.change.filename),
                        { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                        .then(function (r) { return r.ok ? r.json() : { posters: [], error: 'Search failed.' }; })
                        .then(function (d) {
                            self.finder = {
                                loading: false,
                                error: d.error || '',
                                results: Array.isArray(d.posters) ? d.posters : [],
                            };
                        })
                        .catch(function () { self.finder = { loading: false, error: 'Search failed.', results: [] }; });
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
        }

        function extractResults(doc) {
            var el = doc.querySelector('#results');
            return el ? el.innerHTML : null;
        }
        function extractFlash(doc) {
            var el = doc.querySelector('.alert');
            return el ? el.textContent.trim() : '';
        }

        function load(url, push) {
            root.classList.add('is-loading');
            return fetch(url, { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' })
                .then(function (r) { return r.text(); })
                .then(function (html) {
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    var inner = extractResults(doc);
                    if (inner !== null) { setResults(inner); }
                    if (push) { history.pushState({}, '', url); }
                })
                .catch(function () {})
                .finally(function () { root.classList.remove('is-loading'); });
        }

        function currentUrl() {
            return window.location.pathname + window.location.search;
        }

        function submitForm(form) {
            var action = form.getAttribute('action');
            var data = new FormData(form);
            root.classList.add('is-loading');
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
                    root.classList.remove('is-loading');
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
                    load(base + (q ? '?q=' + encodeURIComponent(q) : ''), true);
                }, 250);
            });
        }

        // Delegated clicks for card + finder actions and pagination.
        root.addEventListener('click', function (e) {
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
            // Switching views (tabs) keeps the active search: rebuild the tab's
            // URL with the live query so the new view opens filtered, and load it
            // through the same no-reload path used for search and pagination.
            var tabLink = e.target.closest('.tabs a');
            if (tabLink && root.contains(tabLink)) {
                e.preventDefault();
                var tabQuery = search ? search.value.trim() : '';
                syncActiveTab(tabLink.pathname);
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
    });
})();
