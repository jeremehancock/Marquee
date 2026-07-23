# Scope SITE_TITLE to the site brand only

## Why

`SITE_TITLE` is documented as "Title shown in the UI", and it is currently used
everywhere that name appears. That conflates two different things:

- **The site's name** — what this particular install is called. The user chooses
  it. It belongs in the brand in the top left.
- **The product's name** — what the software is. It is "Marquee" regardless of
  how any install is configured.

Because the two are the same variable, renaming a site also renames the product.
A user who sets `SITE_TITLE="My Wall"` gets a footer reading "My Wall · v0.1.2"
and, more consequentially, a PWA that installs to the home screen as "My Wall".

The install name is the part that matters most. It is written into the device's
home screen and app switcher at install time, it is not re-read on later page
loads, and the user has no obvious way to correct it short of uninstalling and
reinstalling. A configuration value meant to label a page ends up naming an
installed application.

The default hides this: `SITE_TITLE` defaults to `"Marquee"`, so an unconfigured
install looks correct. The bug only appears once someone actually sets it.

## What Changes

Introduce a compile-time product name and use it everywhere the *product* is
named, leaving `SITE_TITLE` for the *site* brand.

- Add `AppConfig::APP_NAME` — a class constant, deliberately not read from the
  environment, so it cannot be configured.
- Have `SITE_TITLE`'s default reference `APP_NAME` rather than repeat the
  literal, so an unconfigured install stays byte-identical.
- Expose it to templates as an `app_name` Twig global alongside `site_title`.
- Switch to `app_name`: the footer, the `apple-mobile-web-app-title` meta tag,
  and the manifest's `name` and `short_name`.

Unchanged, deliberately:

- The top-left brand keeps `site_title`. This is the one place it belongs.
- Browser tab titles keep `site_title`. The tab is per-install chrome rather
  than product identity, and hardcoding `apple-mobile-web-app-title` means the
  document title no longer feeds iOS install naming, so this is a free choice
  rather than a constraint.
- `SITE_TITLE` remains configurable, with the same name and the same default.

## Impact

- Affected specs:
  - `pwa` — the "Installable web app" requirement currently states the manifest
    name follows `SITE_TITLE`. That sentence is the bug, written down.
  - `application-shell` — the shared-layout requirement says pages display "the
    configured `SITE_TITLE`" without saying where; narrow it to the brand and
    state that the footer names the product.
- Affected code: `src/Config/AppConfig.php`, `src/bootstrap.php`,
  `src/Controller/ManifestController.php`, `templates/layout.html.twig`
- Affected tests: `tests/Functional/PwaTest.php` currently asserts the manifest
  is named `"My Wall"` when `SITE_TITLE="My Wall"` — it enforces the bug, and
  inverting it makes it a regression guard.
- Affected docs: `README.md` config table
- Risk: low. No data, routing, or auth paths touched. Installs that never set
  `SITE_TITLE` see no change at all. Installs that did set it will see the
  footer and meta tag correct themselves on next load; an already-installed PWA
  keeps its old home-screen label until reinstalled, which is inherent to how
  install metadata is captured and not something this change can reach.
