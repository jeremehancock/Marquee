## Why

Switching category tabs — on both phone and desktop — makes the whole poster
grid visibly dim for an instant before the new posters appear. Because a tab
switch is almost always fast, the dim never reads as "loading"; it reads as a
flicker, and it makes an otherwise instant navigation feel unsettled. The
feedback that was meant to reassure during a slow load is firing on every fast
one, where nothing needed reassuring.

## What Changes

- The gallery's dimmed "loading" treatment becomes **deferred**: it appears only
  when a view actually takes long enough to feel slow. Navigations that resolve
  quickly — the normal case for a tab switch, a page link, or clearing a search
  — swap their content with no dim at all.
- Once the dim has been shown, it stays up long enough to be legible rather than
  vanishing the instant the response lands, so a borderline-slow load does not
  produce its own flash.
- This applies uniformly to every no-reload gallery navigation: category tabs,
  live search, pagination, clearing a search, back/forward, and refreshes
  triggered by the import and orphans trays.
- No change to what is shown while loading, only to *when* it is shown. Slow
  views still dim exactly as they do today.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `poster-library`: adds a requirement governing when the in-place loading
  indication for a gallery view change is shown — deferred past a short grace
  period, and held for a minimum once visible — so fast view changes render no
  loading treatment at all.

## Impact

- `public/assets/gallery.js` — the shared `load()` helper and `submitForm()`,
  which today toggle `is-loading` on the gallery root for the exact duration of
  the fetch.
- `public/assets/app.css` — the `[data-gallery].is-loading #results` rule; may
  need no change beyond a comment, depending on where the timing lives.
- No PHP, no templates, no routes, no database. Behavior is presentation-only
  and carries no server-side surface.
- Docs: user-facing behavior is unchanged in kind (a slow load still dims), so
  `README.md` likely needs no edit; confirm during implementation.
