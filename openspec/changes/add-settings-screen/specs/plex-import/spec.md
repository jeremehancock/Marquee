## MODIFIED Requirements

### Requirement: Excluded libraries are never reported
The system SHALL omit excluded libraries from the libraries it reports for a Plex
server, so that no screen, import, or scheduled run can observe an excluded
library.

There is exactly one exception, and it exists because exclusion would otherwise
be irreversible: the settings screen that manages exclusions SHALL be able to
list every library the server reports, excluded or not. A library that nothing
can observe is one nothing can offer to un-exclude. The exception SHALL be
confined to that screen — no import, no scheduled run, and no other screen SHALL
observe an excluded library through it.

#### Scenario: Excluded library is not reported
- **WHEN** the system lists the libraries on a Plex server and one of them is
  excluded
- **THEN** that library is absent from the result and the remaining libraries
  are reported as usual

#### Scenario: The exclusions editor sees every library
- **WHEN** the settings screen lists libraries so that exclusions can be chosen
- **THEN** excluded libraries are included in that list, marked as excluded

### Requirement: Excluded libraries are hidden from the import screen
The Import from Plex screen SHALL NOT offer an excluded library in its library
picker. When no library is available to import and exclusions are configured, the
screen SHALL state that excluded libraries are hidden, rather than reporting only
that no libraries were found on the server.

That statement SHALL point the user at the settings screen, where exclusions are
now changed. Naming an environment variable would send a user to edit a file that
no longer configures anything.

#### Scenario: Excluded library is not offered
- **WHEN** a user opens the Import from Plex screen and one of the server's
  libraries is excluded
- **THEN** that library does not appear in the library picker and the remaining
  libraries are listed as usual

#### Scenario: Every library is excluded
- **WHEN** a user opens the Import from Plex screen, the server reports
  libraries, and every one of them is excluded
- **THEN** the screen states that excluded libraries are hidden and offers no
  import
- **AND** it names the settings screen as where exclusions are changed
