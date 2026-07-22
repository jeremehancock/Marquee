// Gallery enhancement: live search, background grid refresh, no-reload poster
// mutations, lazy-load fade-in, and the Alpine-driven overlays (change modal,
// fullscreen viewer, confirm dialog, toast). Cards render as plain HTML with
// data-* hooks so the grid can be swapped freely and handled by delegation.
(function () {
    'use strict';

    function dispatch(name, detail) {
        window.dispatchEvent(new CustomEvent(name, { detail: detail || {} }));
    }

    // ---- Alpine component: the overlays ----
    document.addEventListener('alpine:init', function () {
        window.Alpine.data('galleryUI', function () {
            return {
                base: '',
                viewer: null,
                change: { open: false, tab: 'upload', filename: '', title: '' },
                finder: { loading: false, error: '', results: [] },
                confirm: { open: false, title: '', message: '', label: 'Confirm' },
                toast: { show: false, text: '' },
                _toastTimer: null,

                init: function () {
                    this.base = this.$root.getAttribute('data-base') || '';
                },
                view: function (url) {
                    if (url) { this.viewer = url; }
                },
                openChange: function (filename, title) {
                    this.change = { open: true, tab: 'upload', filename: filename, title: title };
                    this.finder = { loading: false, error: '', results: [] };
                },
                findPosters: function () {
                    var self = this;
                    this.finder = { loading: true, error: '', results: [] };
                    fetch(this.base + '/find-posters?filename=' + encodeURIComponent(this.change.filename),
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
                    var full = window.location.origin + url;
                    navigator.clipboard.writeText(full)
                        .then(function () { self.notify('URL copied to clipboard'); })
                        .catch(function () {});
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
        });
    });

    // ---- Vanilla enhancement: grid, search, mutations ----
    document.addEventListener('DOMContentLoaded', function () {
        var root = document.querySelector('[data-gallery]');
        if (!root) { return; }
        var base = root.getAttribute('data-base');
        var results = root.querySelector('#results');
        var pendingForm = null;

        function markLoaded(img) {
            if (img.complete && img.naturalWidth > 0) {
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
        initImages(document);

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
            var actionEl = e.target.closest('[data-action]');
            if (actionEl && root.contains(actionEl)) {
                var action = actionEl.getAttribute('data-action');
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
                    });
                    return;
                }
            }
            var pageLink = e.target.closest('.pagination a');
            if (pageLink && root.contains(pageLink)) {
                e.preventDefault();
                load(pageLink.getAttribute('href'), true);
                return;
            }
            // Tap a poster to reveal/hide its action overlay (touch-friendly;
            // desktop still reveals on hover).
            var frame = e.target.closest('.card__frame');
            if (frame && root.contains(frame)) {
                if (e.target.closest('.card__actions')) { return; }
                var wasOpen = frame.classList.contains('is-open');
                closeOverlays();
                if (!wasOpen) { frame.classList.add('is-open'); }
                return;
            }
            closeOverlays();
        });

        function closeOverlays() {
            root.querySelectorAll('.card__frame.is-open').forEach(function (f) {
                f.classList.remove('is-open');
            });
        }

        // Delegated submit for every AJAX mutation form.
        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (!(form instanceof HTMLFormElement) || !form.classList.contains('js-mutate')) { return; }
            e.preventDefault();
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
            load(currentUrl(), false);
        });
    });
})();
