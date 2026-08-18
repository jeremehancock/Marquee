## MODIFIED Requirements

### Requirement: Persisted state is recreatable
Everything Marquee persists SHALL be recreatable from Plex: the poster files
under the posters directory and the SQLite database under the data directory
together form a cache of Plex's artwork and of the mapping back to the Plex items
it came from. Removing either SHALL return Marquee to its first-run state rather
than a broken one — the system SHALL recreate the database schema and any missing
directory on demand, without manual repair and without a reinstall.

The one thing this invariant does not preserve is artwork that never left
Marquee. A poster the user applied to an item was uploaded to Plex and locked
there, so a later import brings it back; a poster only ever stored locally has no
upstream copy and is gone. That boundary is what makes a hand-run reset safe to
document, so the system MUST NOT begin persisting state that only exists locally
and cannot be rebuilt from Plex, except where an exception below applies.

Connection credentials are the first exception, and they do not weaken the
invariant. A token obtained by signing in cannot be rebuilt from Plex, because
it is what reaches Plex in the first place. Losing it SHALL return the user to
the sign-in prompt — which is first-run state, not a broken one — and the system
SHALL keep it outside the SQLite database so that deleting the database remains
a safe reset that costs only the cache.

Scheduling state is the second, and it is admitted under a stated bound rather
than by precedent. Which scheduled run last completed cannot be rebuilt from
Plex, so the system MAY persist it **only** because losing it costs at most one
redundant run of work that is idempotent — an import that finds nothing changed
in Plex downloads nothing. State that fails that bound SHALL NOT be added under
this exception, and the bound SHALL be restated wherever it is relied on rather
than assumed to generalise.

Losing this state SHALL therefore behave like first-run state: the next
scheduled run treats its slot as unrun and performs one import. It SHALL NOT
raise an error, and SHALL NOT leave scheduling disabled. Deleting the database
remains a safe reset, now costing the cache and one import rather than the cache
alone.

#### Scenario: Database removed
- **WHEN** the SQLite database file is deleted while the container is stopped and
  Marquee is started again
- **THEN** the system recreates the schema on first use and serves pages normally
- **AND** a subsequent import rebuilds the Plex item mappings from scratch

#### Scenario: Posters directory removed
- **WHEN** the posters directory or one of its category directories is missing
- **THEN** the gallery reports that category as empty rather than failing
- **AND** the next import recreates the directory and stores posters into it

#### Scenario: Reset returns art that Plex holds
- **WHEN** a user removes both the posters directory and the database, restarts,
  and runs an import
- **THEN** every poster the user had previously sent to Plex is imported back,
  because Plex holds and locks that artwork

#### Scenario: Deleting the database keeps the connection
- **WHEN** the SQLite database is deleted and Marquee is restarted
- **THEN** a previously stored Plex token still authenticates requests, because
  it is not held in the database

#### Scenario: Losing the stored credential returns to first-run
- **WHEN** the stored Plex token is removed
- **THEN** the system presents the sign-in prompt rather than an error state

#### Scenario: Deleting the database costs one scheduled run
- **WHEN** the SQLite database is deleted and a scheduled run is next due
- **THEN** that run performs one import rather than reporting an error
- **AND** scheduling continues normally afterwards

