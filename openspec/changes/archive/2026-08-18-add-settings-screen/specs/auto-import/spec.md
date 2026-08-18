## MODIFIED Requirements

### Requirement: Excluded libraries are skipped
The system SHALL skip any Plex library that is excluded by the excluded-libraries
setting. A library is excluded when its **name** — the library title as it
appears in Plex — matches an entry in that setting, compared case-insensitively
and ignoring leading and trailing whitespace on both sides. Libraries SHALL NOT
be matched by section key or any other identifier, so an entry that is not a
library name excludes nothing.

The setting is chosen in the application, from the libraries the connected server
reports (see `settings`), so an entry that matches no library is now a leftover
rather than something a user can newly type. The matching rule still holds for
one, because a stored entry outlives the library it named.

The exclusion is app-wide rather than specific to auto-import: an excluded
library is invisible to the whole application, including the manual import
screen and orphan detection (see `plex-import` and `orphan-detection`).

#### Scenario: Excluded library is not imported
- **WHEN** a library's name is listed in the excluded-libraries setting
- **THEN** auto-import imports nothing from that library

#### Scenario: Name matching ignores case and surrounding whitespace
- **WHEN** an entry in the excluded-libraries setting differs from a library's
  name only in letter case or surrounding whitespace
- **THEN** that library is excluded

#### Scenario: A non-name entry excludes nothing
- **WHEN** an entry in the excluded-libraries setting matches no library name —
  for example a section key
- **THEN** no library is excluded on account of that entry
