## 1. One definition per rule

- [x] 1.1 Expose each floor as a constant on the config object that owns it —
      `AuthConfig::MINIMUM_DURATION` (60), `PlexConfig::MINIMUM_TIMEOUT` (1),
      `PosterConfig::MINIMUM_PER_PAGE` (1), `PosterConfig::MINIMUM_FILE_SIZE`
      (1) — and have `resolve()` read the constant rather than a literal
- [x] 1.2 Add `SettingsStore::setMany(array $values)`: re-read once, apply every
      changed key, write once; reduce `set()` to a one-key call to it

## 2. Seeing every library

- [x] 2.1 Add `PlexClient::allLibraries()` — the `/library/sections` fetch and
      parse with no exclusion filter — and reduce `libraries()` to filtering its
      result; document in the interface that the exclusions editor is its only
      legitimate caller
- [x] 2.2 Implement it in `HttpPlexClient`, keeping the movie/show type filter in
      the shared path so both methods agree on what a library is

## 3. The form service

- [x] 3.1 Add a `SettingsForm` (or equivalent) service in `App\Settings` that
      renders the current stored values as form fields and validates a
      submission, applying the constants from 1.1
- [x] 3.2 Implement the unit conversions at that boundary: session duration in
      days (1–365), maximum upload size in MB (1–100), timeouts in seconds
      (1–300), posters per page (1–200); round a stored value to the nearest
      whole display unit, never to zero
- [x] 3.3 Clamp a stored value outside an offered range for display rather than
      refusing it, so a seeded value cannot make an unrelated field unsavable
- [x] 3.4 Validate the site title as required, trimmed, and bounded in length;
      validate the default sort against `SortOrder`, refusing an unrecognized
      slug rather than silently defaulting
- [x] 3.5 Implement the exclusions merge: replace exclusions only for libraries
      the server reported with the rendered form; preserve stored names it did
      not report, and expose the preserved names for display
- [x] 3.6 Return per-field messages and the submitted values on refusal, so the
      screen can re-render what was typed

## 4. Screen and routes

- [x] 4.1 Add `SettingsController` with `show()` and `save()`; fetch libraries
      via `allLibraries()`, catching `PlexException` and passing the
      `PlexFailureMessage` through, as the import screen does
- [x] 4.2 Register `GET /settings` and `POST /settings` in `Routes.php`, inside
      both gates; redirect with a flash on success
- [x] 4.3 Wire the controller and form service in `bootstrap.php`
- [x] 4.4 Build `templates/settings.html.twig`: labelled sections for
      Presentation, Plex, Session, Updates, and Libraries; `csrf_field()`; the
      per-field messages from 3.6
- [x] 4.5 Render the library checkboxes, marking currently excluded libraries,
      and list preserved exclusions the server did not report
- [x] 4.6 Render the Plex-unreachable state: the failure message, the stored
      exclusions as text, no checkboxes, every other section still savable
- [x] 4.7 Render the relocated-variables panel from
      `SupersededEnvironment::relocatedNames()`, saying they are managed here and
      can be deleted; do not list retired variables

## 5. Navigation and styling

- [x] 5.1 Add a `settings` glyph to `_icons.html.twig`, in the same
      24-viewbox stroke style as its neighbours
- [x] 5.2 Add the Settings item to `secondary_links()` in `_nav_macros.html.twig`
      — after Orphans, before Support Development — so the desktop header and the
      mobile tray both gain it from one source
- [x] 5.3 Add the shared form-control styles to `public/assets/app.css` from
      existing tokens: text, number, select, checkbox, section grouping, focus
      indicator, and the validation-message treatment

## 6. Pointing the rest of the app at the screen

- [x] 6.1 Update the import screen's "every library is excluded" message to name
      the settings screen rather than a variable

## 7. Tests

- [x] 7.1 `/settings` requires a session and a Plex connection; anonymous and
      unconnected requests are turned away as other protected routes are
- [x] 7.2 Saving each setting stores it and the next request resolves the new
      value — one test per group, asserting through the config objects
- [x] 7.3 A value below a floor is refused, nothing is stored, and the response
      carries the submitted values and a message for that field
- [x] 7.4 An unrecognized sort slug is refused rather than defaulted
- [x] 7.5 A seeded value outside an offered range renders clamped, and an
      unrelated field on the same form still saves
- [x] 7.6 Unit conversion round-trips: a stored 2592000 renders as 30 days and
      saving it back stores 2592000
- [x] 7.7 Exclusion checkboxes list excluded libraries; excluding a library hides
      it from the import screen; un-excluding restores it
- [x] 7.8 A stored exclusion for an unreported library survives a save and is
      listed on the screen
- [x] 7.9 With Plex unreachable, the screen renders, the library section explains
      the failure, and a save leaves stored exclusions untouched
- [x] 7.10 A submission without a CSRF token is refused and stores nothing
- [x] 7.11 The relocated panel lists a relocated variable set via `supersede()`
      and does not list `AUTH_PASSWORD`
- [x] 7.12 The settings screen offers no Plex server address field and no
      auto-import control
- [x] 7.13 Import and orphan detection still observe no excluded library, so
      `allLibraries()` has not widened exclusion's reach
- [x] 7.14 The Settings item is present in the header and the menu tray

## 8. Docs and gates

- [x] 8.1 Add a Settings section to `README.md` describing the screen and what it
      covers; point the "still in your compose file" note at it. Leave the
      configuration table's shape alone — phase 4 owns rewriting it
- [x] 8.2 Update the `openspec/config.yaml` context paragraph that still says all
      configuration comes from environment variables
- [x] 8.3 `composer test`, `composer stan`, `composer cs` all pass
- [x] 8.4 Tick phase 2 in `openspec/settings-in-app-plan.md` and record anything
      learned that phase 3 would otherwise rediscover
