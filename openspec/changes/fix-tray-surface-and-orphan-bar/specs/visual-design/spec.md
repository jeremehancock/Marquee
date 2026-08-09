## ADDED Requirements

### Requirement: Elevation is drawn only where a surface is drawn

A shadow SHALL describe the edge of a surface that is actually painted. Where a
rule removes a surface's background and border for a particular context, it
SHALL remove that surface's elevation in the same place: a shadow with no
surface to cast it draws a rectangle the user cannot see, which reads as a
rendering fault rather than as depth.

This governs every context where an existing surface is restyled rather than
newly authored — most sharply where a component written for a full page is
reused inside a tray, and its panel is flattened so the tray's own surface shows
through instead.

#### Scenario: A panel flattened inside a tray casts no shadow

- **WHEN** a panel authored for a full page is reused inside a tray and drawn
  without its own background and border, so the tray's surface shows through
- **THEN** it carries no elevation shadow either, and nothing outlines the
  rectangle it no longer occupies

#### Scenario: The same panel on its own page keeps its elevation

- **WHEN** that panel is shown on its own page, with its background and border
  drawn
- **THEN** it keeps the elevation of its tier, because there the shadow traces a
  real edge

### Requirement: Content reused inside an overlay sheds its page chrome

A component authored as pinned page chrome SHALL NOT carry its pinning,
translucent tint, gutter bleed, or stacking order with it when the same content
is reused inside a tray or dialog. Those values are solved against the page's
scroll container, background, and gutters, and none of the three is the same
inside an overlay.

An element inside a tray SHALL NOT declare a stacking order that places it above
that tray's own progress overlay. A tray's overlay is deliberately ranked below
the app-wide overlay scale so that it covers only the tray, and any element that
outranks it is drawn over the spinner rather than under it — receiving neither
the dim nor the blur that would otherwise apply to it, because a backdrop filter
affects only what is drawn behind.

Where the same markup serves both a full page and a tray, the safe resolution is
for the reused content to carry no positioning at all rather than to be
re-tuned per context.

#### Scenario: A reused bar spans the tray it is in

- **WHEN** a bar of controls authored for a full page is shown inside a tray
- **THEN** it is laid out against the tray's own padding, with no strip of the
  tray's surface left uncovered beside it and no band of the page's colour laid
  over the tray's

#### Scenario: A tray's progress overlay covers everything in the tray

- **WHEN** a progress overlay is shown inside a tray while previously loaded
  content is still on screen beneath it
- **THEN** every part of that content is drawn beneath the overlay, dimmed and
  blurred by it, including any bar of controls

#### Scenario: Reopening a tray does not expose stale controls

- **WHEN** a tray that has already loaded is reopened and refreshes its contents,
  leaving the previous result visible while the refresh runs
- **THEN** the whole previous result reads as being refreshed, with no element of
  it standing above the progress overlay as though it were still live
