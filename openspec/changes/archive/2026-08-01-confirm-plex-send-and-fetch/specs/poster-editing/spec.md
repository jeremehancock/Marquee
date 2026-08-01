## MODIFIED Requirements

### Requirement: Re-send a stored poster to Plex
The system SHALL let a user push a linked poster's currently stored image to its
Plex item and lock it, without first changing the poster. This lets a user
re-apply Marquee's copy after Plex has drifted (for example, an agent refresh).

Because the operation overwrites whatever artwork the Plex item currently holds,
the system SHALL ask the user to confirm before making any request to Plex. The
confirmation SHALL name the poster with the same title the gallery caption shows
for it and SHALL state that the artwork on Plex will be replaced and locked. If
the user declines, the system SHALL make no Plex request and change nothing.

#### Scenario: Send the stored poster to Plex
- **WHEN** a user sends a linked poster to Plex and confirms
- **THEN** the system uploads the poster's currently stored image to Plex and
  locks it, leaving the local file unchanged

#### Scenario: Sending asks before it uploads
- **WHEN** a user chooses Send to Plex for a linked poster
- **THEN** the system asks for confirmation, naming that poster and stating that
  the artwork on Plex will be replaced, before any upload is attempted

#### Scenario: Declining a send changes nothing
- **WHEN** a user chooses Send to Plex and then dismisses or cancels the
  confirmation
- **THEN** no request is made to Plex, the Plex item keeps its current artwork,
  and no outcome is reported

### Requirement: Fetch a poster from Plex
The system SHALL let a user re-pull a linked poster's current image from Plex,
replacing the local file with what Plex currently has. The fetched image SHALL
be validated like any other replacement.

Because the operation overwrites the poster Marquee is holding — which may be a
custom image that exists nowhere else — the system SHALL ask the user to confirm
before making any request to Plex. The confirmation SHALL name the poster with
the same title the gallery caption shows for it and SHALL state that the stored
poster will be overwritten. If the user declines, the system SHALL make no Plex
request and leave the local file unchanged.

#### Scenario: Fetch replaces the local poster
- **WHEN** a user fetches a linked poster from Plex and confirms
- **THEN** the system downloads the item's current Plex poster and overwrites the
  local file

#### Scenario: Fetching asks before it overwrites
- **WHEN** a user chooses Fetch from Plex for a linked poster
- **THEN** the system asks for confirmation, naming that poster and stating that
  Marquee's stored poster will be overwritten, before any download is attempted

#### Scenario: Declining a fetch leaves the poster alone
- **WHEN** a user chooses Fetch from Plex and then dismisses or cancels the
  confirmation
- **THEN** no request is made to Plex, the local file is unchanged, and the
  poster's card keeps the image it was already showing

#### Scenario: The two confirmations are distinguishable
- **WHEN** a user is shown the confirmation for Send to Plex and, separately, the
  one for Fetch from Plex
- **THEN** each names its own action and states which copy of the poster is
  overwritten, so the two cannot be mistaken for one another

#### Scenario: Fetching an unlinked poster is refused
- **WHEN** a user fetches a poster that has no Plex mapping
- **THEN** the system reports that the poster is not linked to Plex and changes
  nothing
