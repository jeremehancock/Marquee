# Design

## Context

The stale image is a cache-key problem, not a caching-policy problem. The
response is cacheable and *should* be — posters are large, rarely change, and a
gallery renders dozens at once. What's wrong is that the key doesn't change when
the bytes do.

```
change poster
     │
     ├─ file replaced on disk ......................... correct
     ├─ 302 back to /library/movies ................... correct
     ├─ gallery re-reads the directory ................ correct
     └─ <img src="/posters/movies/Solaris.png"> ....... same URL as before
                    │
                    └─ browser: cached, max-age=604800 → old image
```

## Decisions

### Cache-bust the URL rather than weaken caching

The alternative is to stop the browser caching posters — `no-cache`, or a short
`max-age`, so it revalidates. That fixes staleness by making every gallery view
issue a request per poster. With 24 images per page that is 24 conditional
requests on every navigation, to solve a problem that only occurs on the rare
occasion a poster actually changes. It trades a common cost for a rare one.

Appending the modification time inverts that: the URL changes exactly when the
content changes, so the cache is authoritative the rest of the time. Long
caching stops being a bug and starts being correct.

This is also the pattern already in the codebase. `bootstrap.php` registers an
`asset()` function that appends a file's mtime to CSS and JS URLs, with the
comment "a changed stylesheet or script is a new URL that defeats every cache
layer." Posters need the same guarantee and never got it.

### Put it in `Poster::url()`

Three surfaces render posters — the gallery grid, the poster wall, and the
gallery's JS actions (view, copy) which read `src` and `data-url` back out of the
DOM. All of them originate from `Poster::url()`. Changing that one method covers
every path, and no caller needs to know.

`Poster` already has `modifiedAt`, set from `filemtime()` in
`FilesystemPosterStorage::list()`. No extra `stat` calls; the value is already in
hand.

### Leave `Cache-Control` alone

Adding `immutable` was considered — it is technically accurate once the URL is
content-addressed, and would stop revalidation entirely. Rejected: a URL without
the parameter (an old bookmark, a previously copied link) would then be pinned
to a stale image with no way to recover short of clearing the cache. The current
header already expires, which is the safer failure mode for a marginal gain.

### `v` is advisory, not authoritative

`PosterImageController` routes on path alone and never reads the query string.
That means a bare URL still works, and a URL carrying an *old* `v` still serves
the current file rather than a stale one. The parameter exists solely to make
the browser's cache key change; the server has no opinion about it.

That is a deliberate property. Nothing needs to validate, sign, or clean up
these values.

## Consequences

- "Copy URL" would otherwise put `?v=1753280400` on the clipboard. Harmless —
  the server ignores it — but it is noise in a link a user shares, so the copy
  action strips it.
- Existing tests assert bare `/posters/...` strings. They need to match a prefix
  or tolerate the parameter, which is a genuine assertion change rather than a
  cosmetic one: the URL contract really did change.
- A poster restored from a backup with a preserved mtime keeps its old `v`, and
  a browser holding that exact URL would show the cached copy. This is
  vanishingly rare and self-corrects within the cache lifetime; not worth
  designing around.
