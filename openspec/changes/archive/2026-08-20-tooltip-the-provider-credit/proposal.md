## Why

The provider logos in the footer are the last place in Marquee that still hands
its hint to the browser. Hovering TMDB, TheTVDB, fanart.tv or TVmaze raises the
operating system's plain native tooltip — a different font, a different colour, a
different delay, drawn outside the app's surface — while every other hint on the
screen appears in Marquee's own themed bubble. It reads as a seam in an otherwise
consistent interface.

## What Changes

- The four provider links in the footer credit show the shared themed tooltip on
  hover and on keyboard focus, in place of the browser's native tooltip.
- Each link's hint continues to name its provider (`TMDB`, `TheTVDB`,
  `fanart.tv`, `TVmaze`), unchanged in wording.
- Each link keeps its accessible name, so nothing is lost for assistive
  technology or on touch, where no tooltip is shown at all.
- The change applies to both renderings of the credit — the page footer and the
  navigation drawer's footer — because both come from one partial.

## Capabilities

### New Capabilities

<!-- None. -->

### Modified Capabilities

- `application-shell`: the *Poster provider attribution* requirement gains a
  statement that each logo link presents its provider's name through the shared
  custom tooltip rather than a native `title`, and that removing the native
  tooltip does not remove the link's accessible name. This closes the last gap
  against the existing *Consistent custom tooltips* requirement, which already
  forbids the native tooltip application-wide.

## Impact

- `templates/partials/_attribution.html.twig` — the only file rendering the
  credit; feeds both footers.
- `tests/Functional/ApplicationShellTest.php` — the attribution assertions,
  extended to pin the tooltip hosts and the absence of `title`.
- No PHP, JavaScript or CSS changes: `app.js` already delegates `[data-tooltip]`
  from the document, and `.tooltip` already outranks the drawer on the z-index
  ladder.
