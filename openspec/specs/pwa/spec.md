# PWA Specification

## Purpose

Making Marquee installable to a phone or desktop home screen, keeping its static
assets available offline, and telling the user which version they are running.

The update check is opt-in and strictly best-effort: it reaches out to a third
party, so a failure, a timeout, or a disabled setting must never delay or break
a page render.
## Requirements
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

### Requirement: Offline-tolerant assets
The system SHALL register a service worker that caches the app's static assets so
they load quickly and remain available when offline.

#### Scenario: Assets served from cache
- **WHEN** a cached static asset is requested and the network is unavailable
- **THEN** the service worker serves the cached copy

### Requirement: Version display
The system SHALL show the current application version in the interface.

#### Scenario: Version shown
- **WHEN** any page is rendered
- **THEN** the current version is displayed

### Requirement: Optional update check
The system SHALL, only when the update check is enabled, compare the current
version with the latest released version and indicate when a newer one is
available; failures and the disabled state SHALL never block the page.

#### Scenario: Newer version available
- **WHEN** the update check is enabled and the latest release is newer than the
  current version
- **THEN** the system indicates that an update is available

#### Scenario: Check disabled
- **WHEN** the update check is disabled
- **THEN** the system reports no update and makes no external request

