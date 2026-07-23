## Approach

Keep the server as the single source of truth for card markup and enhance the
gallery with a small vanilla-JS layer (`gallery.js`) plus the existing Alpine
overlays. No SPA framework, no build step.

### Grid is framework-agnostic HTML

Cards render as plain HTML with `data-*` attributes (`data-action`,
`data-filename`, `data-title`, `data-url`) and carry no Alpine directives. This
lets `gallery.js` swap the grid's `innerHTML` freely and use event delegation on
a stable parent, so handlers keep working after a swap. The overlays (change
modal, fullscreen viewer, confirm dialog, toast) live outside the grid, stay
static, and are driven by Alpine.

### Background refresh via fetch-and-swap

- **Search / pagination**: `gallery.js` fetches the normal gallery URL, parses
  the returned HTML with `DOMParser`, and swaps in the `.grid`, `.stats`, and
  `.pagination` regions. It updates the address bar with `history.pushState`.
- **Mutations** (change / send / fetch / delete): the existing endpoints already
  redirect back to the gallery and render a flash. `gallery.js` posts the form,
  follows that redirect, and reuses the same parse-and-swap to refresh the grid,
  surfacing the flash text as a toast. No JSON endpoints are needed and the
  endpoints keep working for non-JS clients.

### Cross-talk between vanilla grid and Alpine overlays

Grid handlers dispatch `CustomEvent`s on `window` (`gallery:change`,
`gallery:view`, `gallery:confirm`, `gallery:toast`); the Alpine root listens with
`@gallery:*.window` and opens the matching overlay. Confirmed destructive actions
call back into the shared mutation helper.

### Remembered section

`GalleryController::show` records the viewed category in the session. Orphan and
Import controllers read it to build the "Back to library" link, defaulting to the
default category when unset. This avoids threading query parameters through every
link and redirect.

### Lazy-load presentation

Images keep `loading="lazy"`. Each sits over a CSS shimmer and starts at
`opacity: 0`; `gallery.js` adds `is-loaded` on the image's `load` event (and
immediately for images already `complete` from cache) to fade it in.

## Trade-offs

- Parsing full HTML responses for mutations is slightly heavier than a dedicated
  JSON API, but it keeps one rendering path and needs no server changes.
- Alpine will not initialise directives inside swapped `innerHTML`, which is why
  cards use delegation instead of inline directives.
