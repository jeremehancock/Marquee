## MODIFIED Requirements

### Requirement: Fullscreen poster view
The system SHALL let a user view any gallery poster full screen.

While the full-screen image has not yet resolved, the system SHALL show the same
subtle placeholder animation used by poster cards, sized and positioned where the
poster will appear, and SHALL fade the poster in once it resolves. The placeholder
SHALL stop whether the image loads or fails, so a poster that cannot be fetched
never leaves an animation running indefinitely.

Because one full-screen view is reused for every poster rather than being created
per poster, opening a different poster SHALL return the view to its unresolved
state — placeholder showing, poster hidden — rather than displaying the previous
poster, or treating the new poster as already loaded because the view had
resolved once before.

#### Scenario: Open a poster full screen
- **WHEN** a user activates a poster in the gallery
- **THEN** the system displays that poster in a full-screen view

#### Scenario: Full-screen image has not loaded yet
- **WHEN** a poster is opened full screen and its image has not yet resolved
- **THEN** the placeholder animation is shown where the poster will appear,
  rather than an empty backdrop, and the poster fades in once it resolves

#### Scenario: Full-screen image fails to load
- **WHEN** a poster opened full screen fails to load
- **THEN** the placeholder animation stops rather than continuing to suggest the
  image is still loading

#### Scenario: Reopening the view on a different poster
- **WHEN** a user opens one poster full screen, closes it, and opens a different
  poster
- **THEN** the view starts unresolved again — showing the placeholder until the
  newly opened poster resolves — and never shows the previously viewed poster
