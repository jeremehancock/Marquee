# Poster Editing Specification

## Purpose

Marquee's central verb: taking one poster that already exists in the library and
doing something to it. Replacing its image from a file or URL, re-sending
Marquee's copy to Plex after Plex has drifted, pulling Plex's current artwork
back down, downloading it, or copying its URL.

Every operation acts on a single poster in place — the poster keeps its
identity, its filename, its category, and its Plex mapping. There is no path
that adds a new poster to the library; posters arrive only through
`plex-import`, and every replacement image is validated before it is allowed to
overwrite one.

The Plex write mechanism these operations rely on is `plex-export`; picking a
replacement image from an online search is `poster-sources`.
## Requirements
### Requirement: Change a poster in place
The system SHALL let a user replace an existing poster from a local file or a
URL, overwriting that poster's file. When the poster is linked to a Plex item and
Plex is configured, the system SHALL also upload the new image to Plex and lock
it.

#### Scenario: Change from a file replaces and pushes to Plex
- **WHEN** a user changes a Plex-linked poster by uploading a file
- **THEN** the system overwrites that poster's file and uploads it to Plex, then
  locks it

#### Scenario: Change from a URL replaces and pushes to Plex
- **WHEN** a user changes a Plex-linked poster by providing an image URL
- **THEN** the system fetches the image, overwrites that poster's file, uploads
  it to Plex, and locks it

#### Scenario: Change an unlinked poster updates only locally
- **WHEN** a user changes a poster that is not linked to Plex
- **THEN** the system overwrites the file and does not attempt to push to Plex

### Requirement: Replacement images are validated
The system SHALL accept a replacement image only when it is a JPEG, PNG, or WebP
— determined by inspecting the image data rather than trusting its name or
declared type — and is no larger than `MAX_FILE_SIZE`. A rejected image SHALL
leave the existing poster untouched.

#### Scenario: Disallowed type is rejected
- **WHEN** a user supplies a replacement whose image data is not JPEG, PNG, or
  WebP
- **THEN** the system rejects it with a clear message and leaves the poster
  unchanged

#### Scenario: Oversized image is rejected
- **WHEN** a user supplies a replacement larger than `MAX_FILE_SIZE`, by file or
  by URL
- **THEN** the system rejects it with a clear message and leaves the poster
  unchanged

#### Scenario: Unusable URL is rejected
- **WHEN** a user supplies a URL that is not a valid `http`/`https` address, or
  that cannot be fetched, or that returns nothing
- **THEN** the system rejects it with a clear message and leaves the poster
  unchanged

### Requirement: Re-send a stored poster to Plex
The system SHALL let a user push a linked poster's currently stored image to its
Plex item and lock it, without first changing the poster. This lets a user
re-apply Marquee's copy after Plex has drifted (for example, an agent refresh).

#### Scenario: Send the stored poster to Plex
- **WHEN** a user sends a linked poster to Plex
- **THEN** the system uploads the poster's currently stored image to Plex and
  locks it, leaving the local file unchanged

### Requirement: Fetch a poster from Plex
The system SHALL let a user re-pull a linked poster's current image from Plex,
replacing the local file with what Plex currently has. The fetched image SHALL
be validated like any other replacement.

#### Scenario: Fetch replaces the local poster
- **WHEN** a user fetches a linked poster from Plex
- **THEN** the system downloads the item's current Plex poster and overwrites the
  local file

#### Scenario: Fetching an unlinked poster is refused
- **WHEN** a user fetches a poster that has no Plex mapping
- **THEN** the system reports that the poster is not linked to Plex and changes
  nothing

### Requirement: Download and copy a poster
The system SHALL let a user download a poster's image and copy the poster's URL.

#### Scenario: Download a poster
- **WHEN** a user chooses to download a poster
- **THEN** the system provides the image file for download

#### Scenario: Copy a poster URL
- **WHEN** a user chooses to copy a poster's URL
- **THEN** the poster's URL is placed on the clipboard

### Requirement: A changed poster is visible immediately
After any operation that replaces a poster's image, the system SHALL present the
new image on the next page render, without requiring the user to reload the page
or clear a cache. A success message SHALL NOT be shown alongside the previous
image.

#### Scenario: Changed poster appears without a reload
- **WHEN** a user changes a poster and is returned to the gallery
- **THEN** the poster shown is the new image

#### Scenario: The image URL changes with the file
- **WHEN** a poster's file is replaced
- **THEN** the URL the system renders for that poster differs from the one it
  rendered before the replacement, so a cached copy of the previous image is
  not reused

#### Scenario: Unchanged posters keep their URL
- **WHEN** a gallery is rendered twice with no poster replaced in between
- **THEN** each poster's URL is identical in both renders, so cached images stay
  usable

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

