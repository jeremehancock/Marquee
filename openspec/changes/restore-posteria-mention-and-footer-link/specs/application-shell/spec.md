## MODIFIED Requirements

### Requirement: Server-rendered pages with shared layout
The system SHALL render HTML pages with a templating engine using a shared base
layout, exposing both the configured site title and the fixed product name to
every page. The configured site title SHALL identify the site; the product name
SHALL identify the software. Wherever the layout presents the product name as
footer chrome — the page footer and the navigation drawer's footer — that name
SHALL link to the project website.

#### Scenario: Pages extend the base layout
- **WHEN** any HTML page is rendered
- **THEN** it extends the shared base layout and displays the configured
  `SITE_TITLE` as the brand in the page header

#### Scenario: Footer names the product
- **WHEN** any HTML page is rendered
- **THEN** its footer displays the product name and the current version,
  regardless of how `SITE_TITLE` is configured

#### Scenario: Page footer links to the project website
- **WHEN** a user activates the product name in the page footer
- **THEN** the project website at `https://marquee.dumbprojects.com` opens in a
  new browsing context
- **AND** the version text and any update note continue to be displayed
  alongside it

#### Scenario: Drawer footer links to the project website
- **WHEN** a user activates the product name in the navigation drawer's footer
- **THEN** the project website at `https://marquee.dumbprojects.com` opens in a
  new browsing context, leaving the drawer's page in place
- **AND** the version text and any update note continue to be displayed
  alongside it

#### Scenario: Product name is not configurable
- **WHEN** the application reads its configuration from the environment
- **THEN** the product name is a fixed value that no environment variable can
  override
- **AND** `SITE_TITLE` defaults to that same product name, so an install that
  does not set it presents the product name throughout
