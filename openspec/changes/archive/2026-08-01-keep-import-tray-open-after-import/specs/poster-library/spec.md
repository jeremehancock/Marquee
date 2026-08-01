## ADDED Requirements

### Requirement: A completed import leaves the import tray open and reset

When an import run from inside the import tray finishes, the tray SHALL remain
open and SHALL present the import form returned to its initial state: no content
type selected, no libraries selected, the "re-download unchanged posters" option
cleared, and the tray's progress indicator dismissed. The user SHALL be able to
begin another import immediately, starting from step 1, without reopening the
tray.

Importing is inherently repetitive — the form imports one content type at a
time, so populating a library means running it once per type — and closing the
tray after each run charges the user a reopen for every repeat.

The result of the completed import SHALL still be reported, and the gallery
behind the tray SHALL still reflect the newly imported posters, exactly as when
the tray closed.

An import that fails SHALL also leave the tray open, but SHALL NOT reset the
form: the user's selections are what they would need to re-enter to retry, so
they are preserved and only the failure is reported.

This requirement applies to the import tray alone. The behavior of every other
tray on a small screen — orphans, a poster's action tray, sort, the app menu,
and confirmations — SHALL be unchanged by it.

#### Scenario: The tray stays open after an import

- **WHEN** a user runs an import from inside the import tray and it completes
- **THEN** the tray is still open, the progress indicator is gone, the result is
  reported, and the gallery behind the tray reflects the newly imported posters

#### Scenario: The form is back at step 1

- **WHEN** an import completes inside the tray
- **THEN** the form in the still-open tray shows no content type selected, no
  libraries selected, and the re-download option cleared, so only step 1 is
  presented

#### Scenario: Running a second import without reopening the tray

- **WHEN** a user completes an import, then selects a different content type and
  its libraries in the same open tray and submits again
- **THEN** the second import runs in place and reports its own result, without
  the tray having been reopened

#### Scenario: A failed import keeps the user's selections

- **WHEN** an import run from inside the tray fails
- **THEN** the tray stays open, the failure is reported, and the content type and
  libraries the user chose are still selected so the import can be retried

#### Scenario: Dismissing the tray still discards the form

- **WHEN** a user closes the import tray by dragging it down, tapping the
  backdrop, or pressing Escape, and then reopens it
- **THEN** the tray opens on a freshly loaded form at step 1, as before

#### Scenario: Other trays are unaffected

- **WHEN** a user deletes orphans in the orphans tray, or confirms an action from
  a poster's action tray
- **THEN** those trays behave exactly as they did before this change
