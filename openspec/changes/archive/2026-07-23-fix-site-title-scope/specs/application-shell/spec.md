## MODIFIED Requirements

### Requirement: Server-rendered pages with shared layout
The system SHALL render HTML pages with a templating engine using a shared base
layout, exposing both the configured site title and the fixed product name to
every page. The configured site title SHALL identify the site; the product name
SHALL identify the software.

#### Scenario: Pages extend the base layout
- **WHEN** any HTML page is rendered
- **THEN** it extends the shared base layout and displays the configured
  `SITE_TITLE` as the brand in the page header

#### Scenario: Footer names the product
- **WHEN** any HTML page is rendered
- **THEN** its footer displays the product name and the current version,
  regardless of how `SITE_TITLE` is configured

#### Scenario: Product name is not configurable
- **WHEN** the application reads its configuration from the environment
- **THEN** the product name is a fixed value that no environment variable can
  override
- **AND** `SITE_TITLE` defaults to that same product name, so an install that
  does not set it presents the product name throughout
