## ADDED Requirements

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

#### Scenario: Invalid image is rejected
- **WHEN** a user submits a file or URL that is not a supported image within the
  size limit
- **THEN** the system rejects it with a clear message and leaves the poster
  unchanged

### Requirement: Fetch a poster from Plex
The system SHALL let a user re-pull a linked poster's current image from Plex,
replacing the local file with what Plex currently has.

#### Scenario: Fetch replaces the local poster
- **WHEN** a user fetches a linked poster from Plex
- **THEN** the system downloads the item's current Plex poster and overwrites the
  local file

### Requirement: Download and copy a poster
The system SHALL let a user download a poster's image and copy the poster's URL.

#### Scenario: Download a poster
- **WHEN** a user chooses to download a poster
- **THEN** the system provides the image file for download

#### Scenario: Copy a poster URL
- **WHEN** a user chooses to copy a poster's URL
- **THEN** the poster's URL is placed on the clipboard
