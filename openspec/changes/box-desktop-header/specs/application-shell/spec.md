## ADDED Requirements

### Requirement: Page header is boxed to the content column on desktop
On a pointer/desktop-width screen the shared layout's page header SHALL be
constrained to the same maximum width as the page's content column and centred
within the viewport, so that the header's brand and its navigation align with
the left and right edges of the content below it. The header SHALL be presented
as a self-contained box — bordered on every side, with rounded corners and a
gap above it — rather than as a bar whose background extends to the viewport
edges. On a narrow screen the header SHALL remain a full-width bar flush with
the top and side edges of the viewport, keeping its bottom border, square
corners, and existing spacing unchanged. Where the viewport is wider than a
narrow screen but narrower than the content column's maximum width, the header
SHALL span the available width, matching the content column.

#### Scenario: Header aligns with the content column on a wide screen
- **WHEN** a page that extends the shared layout is viewed on a screen wider
  than the content column's maximum width
- **THEN** the header is no wider than that maximum width and is centred in the
  viewport
- **AND** its brand and navigation start and end at the same horizontal
  positions as the page content below it

#### Scenario: Header is presented as a box on a wide screen
- **WHEN** a page that extends the shared layout is viewed on a pointer/desktop
  screen
- **THEN** the header is drawn as a bordered, rounded box separated from the top
  of the viewport
- **AND** the page background is visible on both sides of it

#### Scenario: Narrow screens keep the full-width bar
- **WHEN** a page that extends the shared layout is viewed on a narrow screen
- **THEN** the header spans the full viewport width, sits flush with the top and
  side edges, and keeps its bottom border and square corners
- **AND** its spacing and contents are unchanged from before this change

#### Scenario: Header and content agree at intermediate widths
- **WHEN** the viewport is wider than a narrow screen but narrower than the
  content column's maximum width
- **THEN** the header spans the available width, matching the content column

#### Scenario: Header contents are unaffected
- **WHEN** the boxed header is rendered on any page that extends the shared
  layout, including the login page which renders no navigation
- **THEN** the brand, the desktop Log out link, the menu control, and the
  navigation tray behave exactly as they did before
