## Why

Opening the orphans page runs a full reconciliation against Plex — walking every
library's items, seasons, and collections — before any HTML is sent. For large
libraries this takes several seconds during which the browser shows the previous
page with no feedback, making Marquee look frozen or broken.

## What Changes

- The orphans page renders its shell immediately and shows an import-style
  spinner overlay while the scan runs, instead of blocking the whole response
  on the Plex reconciliation.
- The slow orphan scan moves to a separate request that the shell fetches after
  it paints; its result (the orphan grid, the "in sync" empty state, or a Plex
  error) is returned as a rendered fragment and swapped into the page.
- The not-configured state still renders instantly with no spinner, since it
  needs no scan.
- Deleting orphans is unchanged.

## Capabilities

### New Capabilities
<!-- none -->

### Modified Capabilities
- `orphan-detection`: The orphans page must present a visible loading state
  while detection runs, and detection results are delivered asynchronously
  after the page shell renders rather than blocking the initial page load.

## Impact

- `orphan-detection` spec: new requirement for a loading state; existing
  requirements about what the page explains and the Plex-required message are
  unchanged in intent but now satisfied via the async result fragment.
- Code: `OrphanController` (split into shell + async results action),
  `Routes.php` (new results route), `templates/orphans.html.twig` (shell +
  extracted results fragment), and front-end JS to fetch the fragment and toggle
  the spinner overlay. Reuses the existing `.overlay` / `.spinner` markup and
  Alpine already used by the Plex import page.
- No change to `OrphanService`, the delete-all flow, or the database.
