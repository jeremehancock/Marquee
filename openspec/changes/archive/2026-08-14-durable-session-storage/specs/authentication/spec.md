## ADDED Requirements

### Requirement: A session outlives the container that created it
The system SHALL store server-side sessions on persistent storage, so that a
session survives restarting or recreating the container that issued it.

Retention duration and retention *medium* are different promises, and only the
first was made. A thirty-day window kept in a location the container discards on
every recreation is not a thirty-day window; it lasts until the next update. On
a self-hosted install that accepts image updates automatically, that can be days,
and each one requires plex.tv to get back in — a third-party dependency imposed
on a user who did nothing but take a new version.

The storage location SHALL be Marquee's decision rather than the runtime's
default, applied before the session is started, for the same reason the cookie's
attributes and the retention period are: a location the application depends on
but does not set can be changed by a base-image rebuild with nothing failing.

The location SHALL be configurable by `SESSION_DIR`, defaulting to a directory
on the persistent volume. The default is what every install should want; the
setting exists because the persistent volume is frequently a network mount, and
the file session handler holds an exclusive lock across each request that reads
a session. Where that locking misbehaves, an install MUST be able to return to
local storage without giving up any other part of the session's behaviour —
accepting the loss of durability, and nothing else.

The system SHALL create the configured directory if it does not exist, so that a
first run and an upgrade both work without the user preparing anything.

#### Scenario: A session survives recreating the container
- **WHEN** an authenticated user's container is recreated, as an image update
  does, and the persistent volume is retained
- **THEN** the user is still authenticated on the next request, without signing
  in to Plex again

#### Scenario: Sessions are stored where Marquee decides
- **WHEN** a session is started
- **THEN** it is written to the configured location rather than the runtime's
  default location

#### Scenario: The location can be moved off the persistent volume
- **WHEN** `SESSION_DIR` names a directory outside the persistent volume
- **THEN** sessions are stored there
- **AND** every other property of the session — its duration, its sliding
  renewal, and its cookie's attributes — is unchanged

#### Scenario: A missing directory is created rather than fatal
- **WHEN** the configured session directory does not exist as the application
  starts a session
- **THEN** the directory is created and the session is stored
