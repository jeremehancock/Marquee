## 1. Fix the title update

- [x] 1.1 In `public/assets/gallery.js`, add a small `extractTitle(doc)` helper
  next to the existing `extractResults` / `extractFlash` helpers that returns
  the parsed document's title text, or `null` when there is no `<title>`
- [x] 1.2 In `load(url, push)`, after the response is parsed, assign the
  extracted title to `document.title` when it is non-null, placed before the
  `if (push) { history.pushState(...) }` branch so non-pushed loads
  (`popstate`, `gallery:refresh`) get it too
- [x] 1.3 Add a brief comment noting that the server already renders the
  correct per-view title, so the client just carries it over

## 2. Verify each navigation path

- [x] 2.0 Confirm every no-reload fetch target returns a full document carrying
  the correct per-view title — verified server-side for all five tabs plus a
  search and a paginated URL (`All ·`, `Movies ·`, `TV Shows ·`,
  `TV Seasons ·`, `Collections ·`, each with `#results` present)
- [x] 2.1 Switch between All, Movies, TV Shows, TV Seasons, and Collections and
  confirm the browser tab title changes to match each view with no refresh
- [x] 2.2 Type a search query, then clear it, and confirm the title still names
  the current view
- [x] 2.3 Move between pages with the pagination control and confirm the title
  stays correct
- [x] 2.4 Use browser back and forward across several tab switches and confirm
  the title, the active tab, and the search box all match the restored view
- [x] 2.5 Confirm a bookmark taken after a tab switch is labelled with the
  displayed view rather than the previous one

NOTE: 2.1–2.5 were verified by the maintainer against the `:dev` image; they
need a live browser and a real Plex-backed library, so they were not run in the
implementation session.

## 3. Checks

- [x] 3.1 Run the PHPUnit suite and PHPStan to confirm nothing server-side
  regressed
- [x] 3.2 Confirm the asset cache-buster picks up the changed `gallery.js` (the
  layout loads it through `asset()`), so returning users get the fix without a
  hard reload — `asset()` appends `filemtime`, so the edit yields a new URL
