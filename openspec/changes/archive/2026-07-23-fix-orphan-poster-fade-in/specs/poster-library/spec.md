## MODIFIED Requirements

### Requirement: Poster presentation
The gallery SHALL show each poster's title in a caption beneath the poster, size
posters large enough for the overlay action stack to fit, and lazy-load images
with a subtle placeholder animation that resolves when the image loads. The
placeholder animation and fade-in SHALL apply on every page that renders poster
cards, not only the gallery, and SHALL resolve whether the image loads or fails.

#### Scenario: Title beneath the poster
- **WHEN** the gallery renders a poster
- **THEN** its title appears in a caption below the image rather than inside the
  hover overlay

#### Scenario: Lazy-load animation
- **WHEN** a poster image has not yet loaded
- **THEN** a subtle placeholder animation is shown and the image fades in once
  loaded

#### Scenario: Poster cards outside the gallery
- **WHEN** a page other than the gallery renders poster cards, such as the
  orphans page
- **THEN** each poster image fades in once loaded, rather than staying invisible
  behind a placeholder that animates indefinitely

#### Scenario: Image that fails to load
- **WHEN** a poster image request fails
- **THEN** the placeholder animation stops rather than continuing to suggest the
  image is still loading
