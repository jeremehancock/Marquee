## MODIFIED Requirements

### Requirement: Installable web app
The system SHALL provide a web app manifest and icons so the app can be installed
to a device home screen. The manifest SHALL name the app after the product name,
which is fixed and not configurable, so that renaming a site does not rename the
application a user installs.

#### Scenario: Manifest is available
- **WHEN** the browser requests the web app manifest
- **THEN** the system returns a manifest naming the app after the product name
  and listing its icons

#### Scenario: Install name ignores the configured site title
- **WHEN** `SITE_TITLE` is set to a value other than the product name and the
  manifest is requested
- **THEN** the manifest's `name` and `short_name` are still the product name

#### Scenario: Home-screen label ignores the configured site title
- **WHEN** `SITE_TITLE` is set to a value other than the product name and a page
  is rendered
- **THEN** the `apple-mobile-web-app-title` meta tag carries the product name,
  so an iOS home-screen install is labelled with the product rather than the
  site title

#### Scenario: Manifest does not require login
- **WHEN** the manifest is requested without an authenticated session
- **THEN** the system still returns it, so the browser can read install metadata
