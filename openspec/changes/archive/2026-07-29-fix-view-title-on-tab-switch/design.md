## Context

The gallery does no-reload navigation through a single function,
`load(url, push)` in `public/assets/gallery.js` (around line 663). It fetches
the target URL with `X-Requested-With: fetch`, parses the response with
`DOMParser`, pulls out `#results`, swaps that into the page, and — when `push`
is set — calls `history.pushState`. It never touches `document.title`.

Every caller funnels through it: the tab-click handler, the live-search debounce,
the search-clear link, the pagination handler, the `popstate` listener, and the
`gallery:refresh` event. So the stale title affects all of them, and one fix in
`load()` covers all of them.

The server side is already correct. `templates/gallery.html.twig` sets
`{% block title %}{{ view.label }} · {{ site_title }}{% endblock %}`, and the
fetch response is a full HTML document, so the parsed `doc` already carries the
right `<title>` for the requested view. The data is being fetched and thrown
away.

## Goals / Non-Goals

**Goals:**

- The browser title names the view on screen after any no-reload navigation.
- One change point, so no navigation path can be missed.

**Non-Goals:**

- Building a client-side title template or deriving the title from the URL in
  JavaScript. The server already knows the correct title.
- Changing what the title says. `{{ view.label }} · {{ site_title }}` stays.
- Touching the poster wall, login, orphans, or Plex import pages — they are
  ordinary full page loads and were never affected.

## Decisions

**Read the title out of the already-parsed response document.** Inside `load()`,
after `DOMParser` produces `doc`, take `doc.querySelector('title')` and assign
its text to `document.title`. This is one line at the point where the response
is already in hand — no extra request, no extra parse.

Alternatives considered:

- *Derive the title in JS from the tab's label.* Duplicates the server's
  formatting in a second place, and would not help pagination or search, where
  no tab is involved. Rejected.
- *Fetch a JSON sidecar carrying the title.* A second round trip and a new
  endpoint for a string that is already in the response. Rejected.

**Guard against a missing title.** If a response somehow has no `<title>`,
leave `document.title` alone rather than assigning an empty string — a blank
tab label is worse than a slightly stale one. This is the same defensive shape
`extractResults` already uses (it returns `null` and the caller skips the swap).

**Apply it on both pushed and non-pushed loads.** `popstate` and
`gallery:refresh` call `load(url, false)`; a back-navigation still changes the
view, so the title must follow. Putting the assignment before the `if (push)`
branch handles this without a special case.

## Risks / Trade-offs

- **A response that is a redirect to the login page would set the title to the
  login page's title →** acceptable and arguably correct: the user really is
  looking at a session-expired state, and the existing code already swaps
  whatever `#results` it finds under the same conditions.
- **Title updates are not covered by the PHP functional tests →** the change is
  client-side, and the repo has no JS test harness. Verification is manual, and
  the tasks call it out explicitly per navigation path so nothing is assumed.
