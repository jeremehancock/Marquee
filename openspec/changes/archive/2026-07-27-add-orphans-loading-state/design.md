## Context

`OrphanController::show` calls `OrphanService::findOrphans()` inline and only
then renders `orphans.html.twig`. `findOrphans()` walks every Plex library's
items, seasons, and collections over the network, so the entire HTTP response
is held until that finishes. During navigation the browser keeps showing the
previous page with no indication anything is happening — the "stuck" feeling.

A loading state is impossible in the current shape: no HTML reaches the browser
until the slow work is already done, so a spinner in the template would only
appear after it is pointless. The fix requires the page shell to render first
and the scan to run as a separate request.

The codebase already has the pieces:
- Plex import (`templates/plex.html.twig`) uses an `.overlay` / `.overlay__box`
  / `.spinner` block toggled by Alpine (`x-show="importing"`) — the exact visual
  we want.
- Poster Wall (`PosterWallController`) already demonstrates a shell route
  (`/wall`) plus an async data route (`/wall/posters`).
- Gallery (`public/assets/gallery.js`) already fetches HTML and swaps a
  container's `innerHTML` via `DOMParser`.

## Goals / Non-Goals

**Goals:**
- The orphans page paints immediately with an import-style spinner while the
  scan runs, and swaps in the real result when it completes.
- Reuse the existing overlay/spinner markup and the established shell + async
  fragment pattern rather than inventing new UI or a new JSON contract.
- Keep all orphan card and delete-confirmation markup in Twig.

**Non-Goals:**
- Speeding up or caching the Plex scan itself. This change masks the wait; it
  does not shorten it.
- Changing detection logic, the delete-all flow, or the database.
- Progress reporting / percentage. A single indeterminate spinner is enough.

## Decisions

### Split the controller into a shell action and a results action
`show()` renders the shell only — it does **not** call `findOrphans()`. A new
`results()` action runs the scan and renders just the results portion.

- Route `GET /orphans` → `OrphanController::show` (shell).
- Route `GET /orphans/list` → `OrphanController::results` (runs scan, returns
  fragment). Placed under `/orphans` for a tidy route map.

The shell still computes `isConfigured()` cheaply so the not-configured state
renders instantly with no spinner and never triggers a fetch.

**Alternative considered — JSON endpoint (like `/wall/posters`):** the client
would rebuild the card grid in JS. Rejected: it duplicates card markup outside
Twig and gains nothing here, where the fragment is rendered once.

### Return a rendered HTML fragment, swapped into a container
`results()` renders the orphan grid / empty "in sync" panel / Plex error as a
fragment. The front-end fetches `/orphans/list`, and on success replaces the
content of a results container and hides the overlay. On fetch failure it hides
the overlay and shows a generic error so the page never sticks on the spinner.

Extract the found/empty/error branches of `orphans.html.twig` into a shared
partial (e.g. `orphans/_results.html.twig`) so both `results()` and any
non-JS/full-render path produce identical markup.

**Alternative considered — server-sent events / polling for progress.**
Rejected as overkill; the scan is a single blocking call with no sub-steps to
report.

### Show the spinner on load, not on a user action
Unlike import (spinner on submit), the orphans overlay starts visible when the
shell renders (Plex configured) and is hidden once the fetch resolves. Alpine
state initialized to `loading: true`, flipped to `false` in the fetch's
`finally`.

### Progressive enhancement
If JS is disabled the shell alone would show a permanent spinner. To avoid a
dead page, the fetch is fired from `app.js`/inline module on `orphans` pages,
and the shell includes a `<noscript>` fallback link (or the container renders a
plain "results unavailable without JavaScript" note). Given the app already
depends on Alpine + ES modules for core interactions, a lightweight `<noscript>`
note is acceptable.

## Risks / Trade-offs

- **Fetch never resolves / network error** → the `finally` branch always hides
  the overlay and the `catch` renders an inline error, so the page cannot hang
  on the spinner indefinitely.
- **Double work if the fetch is retried** (e.g. user refreshes mid-scan) → same
  cost as today's single synchronous scan; no new concurrency concern since the
  scan is read-only.
- **Fragment and full-page markup drift** → mitigated by extracting a single
  shared results partial used by both paths.
- **Flash messages after delete-all** — delete-all redirects to `/orphans`,
  which now shows the spinner again before re-scanning. The existing flash still
  renders in the shell (instant), above the results area, so the "Removed N…"
  message is not delayed by the re-scan.

## Migration Plan

Pure front-end/controller refactor with an additive route. Deploy as a normal
release; rollback is reverting the change. No data or config migration.

## Open Questions

- None blocking. If a future change adds caching to the scan, this loading state
  remains correct (it would simply resolve faster).
