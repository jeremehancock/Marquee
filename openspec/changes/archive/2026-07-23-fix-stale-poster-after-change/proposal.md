# Show the new poster immediately after a change

## Why

Changing a poster reports success and then shows the old image. The user is told
"Poster updated." while looking at proof that it wasn't — so the natural reaction
is to try again, and the second attempt looks equally broken.

The write itself is fine. `ChangePosterService` replaces the file, and
`ChangePosterController` redirects back to the gallery, which re-renders and
re-reads the directory from disk. The server is serving correct HTML.

The image never gets re-requested. `Poster::url()` builds a URL from the
category and filename only:

```
/posters/movies/Solaris.png
```

Replacing a poster keeps the same filename, so the URL is byte-identical before
and after. `PosterImageController` serves it with `Cache-Control: private,
max-age=604800` — seven days — so the browser has a fresh cache entry for that
exact URL and satisfies the `<img>` from cache without contacting the server.

A manual refresh appears to fix it because an explicit reload revalidates,
which is a different request than the one the redirect makes. That makes the bug
look intermittent or like a server-side caching problem when it is neither.

Left alone, the stale image persists for up to a week per poster.

## What Changes

Make the URL change whenever the file changes, so the browser treats an updated
poster as a different resource.

- Append the poster's modification time to the URL as a `v` query parameter:
  `/posters/movies/Solaris.png?v=1753280400`.
- `Poster` already carries `modifiedAt`, populated from `filemtime()` when the
  storage layer lists a directory, so this adds no filesystem work.
- Strip the parameter from the URL that "Copy URL" puts on the clipboard, so
  shared links stay clean.

This mirrors the existing `asset()` Twig function, which already appends an
mtime to stylesheet and script URLs for exactly this reason. The bug is that
posters were never given the same treatment.

Every surface builds its URL through `Poster::url()` — the gallery grid, the
poster wall, and the gallery's client-side actions, which read the URL back out
of the rendered DOM. Fixing that one method fixes all of them.

Deliberately unchanged: `Cache-Control` stays at `max-age=604800`. Long caching
is correct once the URL tracks the content, and it keeps the gallery cheap on
repeat visits. The query parameter is ignored by routing, so an existing
bare URL still resolves.

## Impact

- Affected specs:
  - `poster-editing` — the guarantee that a changed poster is visible
    immediately is what the user actually expects and was never written down.
  - `poster-library` — the URL contract for serving a poster image.
- Affected code: `src/Poster/Poster.php`, `public/assets/gallery.js`
- Affected tests: `GalleryTest`, `PosterWallTest`, `PosterWallServiceTest` assert
  bare `/posters/...` URLs and will need to accommodate the parameter.
- Risk: low. No change to storage, routing, or what bytes are served. The worst
  case is a cosmetic URL change; the caching behavior only becomes more correct.
