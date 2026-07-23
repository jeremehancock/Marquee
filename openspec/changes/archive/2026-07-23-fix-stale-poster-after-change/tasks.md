## 1. Make the URL track the file

- [x] 1.1 In `src/Poster/Poster.php`, append the modification time to the URL
      built by `url()` as a `v` query parameter, e.g.
      `/posters/movies/Solaris.png?v=1753280400`. Use the existing
      `$this->modifiedAt` property — it is already populated from `filemtime()`
      by `FilesystemPosterStorage::list()`, so no new filesystem call is needed.
- [x] 1.2 Note in the method's docblock that the parameter exists to change the
      browser's cache key and is ignored by the server, so a later reader does
      not mistake it for something the request handler depends on.
- [x] 1.3 Guard the degenerate case: when `modifiedAt` is `0` (the fallback in
      `list()` when `filemtime()` fails), omit the parameter rather than emit
      `?v=0`, which would be a misleading constant that never busts anything.

## 2. Keep copied URLs clean

- [x] 2.1 In `public/assets/gallery.js`, strip the query string in `copyUrl`
      (line 56) before writing to the clipboard, so a shared link is the bare
      poster URL. The server ignores the parameter, so the stripped URL resolves
      identically.

## 3. Confirm the fix reaches every surface

- [x] 3.1 Confirm `templates/partials/gallery_results.html.twig` renders
      `poster.url` and so picks the change up with no template edit.
- [x] 3.2 Confirm `src/Controller/PosterWallController.php` maps posters through
      `$poster->url()` and so picks it up too.
- [x] 3.3 Confirm the gallery's view and copy actions read their URLs back out of
      the rendered DOM (`src` / `data-url`) rather than rebuilding them, so no
      further JS changes are needed.

## 4. Verify

- [x] 4.1 Unit: a `Poster` with a known `modifiedAt` renders a URL carrying that
      value, and two `Poster` instances differing only in `modifiedAt` produce
      different URLs.
- [x] 4.2 Unit: a `Poster` with `modifiedAt` of `0` renders a URL with no query
      string.
- [x] 4.3 Functional: requesting a poster image with a stale `?v=`, with no
      `?v=`, and with a non-numeric `?v=` all return HTTP 200 and the current
      bytes — the server must ignore the marker entirely.
- [x] 4.4 Functional: after changing a poster, the re-rendered gallery contains a
      URL for that poster different from the one rendered beforehand. This is the
      regression guard for the reported bug; assert on the changed URL rather
      than on the flash message, which was already correct.
- [x] 4.5 Update the existing assertions that expect bare URLs —
      `GalleryTest`, `PosterWallTest`, `PosterWallServiceTest` — to tolerate the
      parameter. Prefer matching the path prefix over hardcoding an mtime, which
      would be fixture-dependent and brittle.
- [x] 4.6 Confirm path-traversal requests still return 404 with the query
      parameter present, so the marker cannot be used to slip past validation.
- [x] 4.7 `composer test`, `composer stan`, `composer cs` green.
- [x] 4.8 `openspec validate fix-stale-poster-after-change --strict` passes.
