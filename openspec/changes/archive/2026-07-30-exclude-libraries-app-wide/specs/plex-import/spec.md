## ADDED Requirements

### Requirement: Excluded libraries are never reported
The system SHALL omit libraries excluded by `EXCLUDED_LIBRARIES` from the
libraries it reports for a Plex server, so that no screen, import, or scheduled
run can observe an excluded library.

#### Scenario: Excluded library is not reported
- **WHEN** the system lists the libraries on a Plex server and one of them is
  excluded by `EXCLUDED_LIBRARIES`
- **THEN** that library is absent from the result and the remaining libraries
  are reported as usual

### Requirement: Excluded libraries are hidden from the import screen
The Import from Plex screen SHALL NOT offer a library excluded by
`EXCLUDED_LIBRARIES` in its library picker. When no library is available to
import and exclusions are configured, the screen SHALL state that libraries
listed in `EXCLUDED_LIBRARIES` are hidden, rather than reporting only that no
libraries were found on the server.

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

### Requirement: Imports skip excluded libraries
An import SHALL import nothing from a library excluded by `EXCLUDED_LIBRARIES`,
regardless of how the import was started, including when that library's section
key is submitted directly to the import endpoint.

#### Scenario: Submitted section key for an excluded library
- **WHEN** an import request selects the section key of an excluded library
- **THEN** nothing is imported from that library

#### Scenario: Mixed selection
- **WHEN** an import request selects both an excluded and a non-excluded library
- **THEN** the non-excluded library is imported and the excluded one is skipped
