## ADDED Requirements

### Requirement: Page header aligns with the content column on desktop
On a pointer/desktop-width screen the shared layout's page header SHALL place its
brand and its navigation at the same left and right edges as the page content
below them, so the header reads as part of the same column rather than as chrome
pinned to the viewport. The header itself SHALL continue to span the full
viewport width, and SHALL be presented as page-coloured chrome separated from the
content by a single rule along its bottom edge — matching the presentation of the
project's landing page — rather than as a raised or bordered panel. Where the
viewport is narrower than the content column's maximum width, the header's
contents SHALL fall back to the same edge spacing the content column uses at that
width. On a narrow screen the header SHALL be unchanged: a full-width bar flush
with the top and side edges of the viewport, keeping its existing surface,
bottom border, and spacing.

#### Scenario: Header contents align with the content column on a wide screen
- **WHEN** a page that extends the shared layout is viewed on a screen wider
  than the content column's maximum width
- **THEN** the brand starts at the same horizontal position as the content below
  it, and the navigation ends at that content's right edge

#### Scenario: Header reads as page-coloured chrome on a wide screen
- **WHEN** a page that extends the shared layout is viewed on a pointer/desktop
  screen
- **THEN** the header spans the full viewport width, is drawn in the page
  background rather than a raised surface, and is separated from the content by
  a single rule along its bottom edge

#### Scenario: Narrow screens are unchanged
- **WHEN** a page that extends the shared layout is viewed on a narrow screen
- **THEN** the header renders exactly as it did before this change: full-width,
  flush to the top and side edges, on its existing surface with its bottom
  border and spacing

#### Scenario: Header and content agree at intermediate widths
- **WHEN** the viewport is wider than a narrow screen but narrower than the
  content column's maximum width
- **THEN** the header's contents use the same edge spacing as the content
  column, so the two still line up

#### Scenario: Header contents are unaffected
- **WHEN** the header is rendered on any page that extends the shared layout,
  including the login page which renders no navigation
- **THEN** the brand, the desktop Log out link, the menu control, and the
  navigation tray behave exactly as they did before
