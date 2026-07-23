## Context

Posters already live on disk, served by the authenticated image route. The wall
is a presentation layer on top: a full-screen page that fetches batches of random
poster URLs and cross-fades between them on the client.

## Goals / Non-Goals

**Goals:**
- Keep the server side tiny and testable: one service that returns random posters
  and one JSON endpoint.
- Smooth, dependency-free client rotation that refills its queue automatically.
- Behave gracefully with an empty library.

**Non-Goals:**
- No now-playing/active-stream integration or Plex image proxy yet (follow-up).
- No configuration of interval/order in this change (sensible defaults).

## Decisions

- **Service.** `PosterWallService::randomPosters(int $count)` gathers posters
  from every category via the storage boundary, shuffles, and returns up to
  `count`. It reuses the same `Poster` value objects and URLs as the gallery.
- **Endpoints.** `GET /wall` renders the full-screen page; `GET /wall/posters`
  returns `{ "posters": [url, …] }` — a random batch (30). Both are behind auth
  like the rest of the app; a kiosk uses `AUTH_BYPASS`.
- **Client.** `wall.js` keeps a queue of URLs, preloads the next image, and
  cross-fades between two stacked layers (a blurred cover backdrop plus the
  contained poster). When the queue runs low it fetches another batch, so the
  wall runs indefinitely and stays fresh. No framework.
- **Standalone page.** `wall.html.twig` is its own full-bleed document rather
  than extending the app layout, so there is no chrome over the display.

## Risks / Trade-offs

- **Client-only animation** is not exercised by PHPUnit; the service and
  endpoints are tested, and the script is small and self-contained.
- **Random with replacement across batches** can occasionally repeat a poster;
  acceptable for an ambient display and far simpler than server-side dedup state.
