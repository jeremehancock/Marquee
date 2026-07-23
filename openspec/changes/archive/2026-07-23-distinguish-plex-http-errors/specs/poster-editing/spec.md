## ADDED Requirements

### Requirement: A missing linked Plex item is reported distinctly
When a Re-send or Fetch operation targets a linked poster whose Plex item no
longer exists, the system SHALL report that the item is gone and that the poster
may be orphaned, guiding the user toward the Orphans page. The system SHALL NOT
report this case as a server-connection failure, and SHALL distinguish it from a
rejected Plex token and from a genuine transport failure (unreachable server,
timeout). No additional Plex request SHALL be made to determine this — the
classification comes from the status of the request the operation already made.

#### Scenario: Re-sending an orphaned poster reports it may be orphaned
- **WHEN** a user re-sends a linked poster whose Plex item no longer exists
- **THEN** the system reports that the item no longer exists in Plex and that the
  poster may be orphaned, directing the user to the Orphans page, and does not
  report a connection failure

#### Scenario: Fetching an orphaned poster reports it may be orphaned
- **WHEN** a user fetches a linked poster whose Plex item no longer exists
- **THEN** the system reports that the item no longer exists in Plex and that the
  poster may be orphaned, directing the user to the Orphans page, and leaves the
  local file unchanged

#### Scenario: A rejected token is reported as an authentication problem
- **WHEN** a Re-send or Fetch operation fails because Plex rejects the configured
  token
- **THEN** the system reports that the Plex token was rejected, distinct from
  both an orphaned item and a general connection failure

#### Scenario: A genuine connection failure still reports a connection problem
- **WHEN** a Re-send or Fetch operation fails because the Plex server cannot be
  reached (unreachable host, refused connection, or timeout)
- **THEN** the system reports that it could not connect to the Plex server, as
  before
