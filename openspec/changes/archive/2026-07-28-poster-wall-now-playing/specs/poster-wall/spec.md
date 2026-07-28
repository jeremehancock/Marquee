## ADDED Requirements

### Requirement: Now Playing takeover

The wall SHALL detect active Plex playback and, while one or more qualifying
sessions are streaming, take over the display: it SHALL stop showing random
posters and instead show the poster(s) of what is being watched. When every
qualifying session has stopped, the wall SHALL revert to the random rotating
wall. Detection SHALL degrade gracefully: if Plex is not configured or cannot
be reached, the wall SHALL continue showing random posters and SHALL NOT
present an error.

#### Scenario: A stream starts
- **WHEN** the wall is showing random posters and a qualifying Plex playback
  session becomes active
- **THEN** the wall switches to showing the poster of what is being watched

#### Scenario: All streams stop
- **WHEN** the wall is in now-playing mode and the last qualifying session ends
- **THEN** the wall reverts to showing random posters

#### Scenario: Paused playback still counts
- **WHEN** a qualifying session is paused rather than stopped
- **THEN** the wall keeps showing that stream's poster

#### Scenario: Plex unavailable
- **WHEN** Plex is not configured or cannot be reached
- **THEN** the wall continues showing random posters with no error shown

### Requirement: Streaming overlays

Each now-playing poster SHALL carry two overlay banners: a top banner reading
"Currently Streaming" and a bottom banner showing the media details and the
name of the Plex user who is streaming it. The media details SHALL identify the
title, and for an episode SHALL include its show and episode information.

#### Scenario: Overlays on a now-playing poster
- **WHEN** the wall shows a now-playing poster
- **THEN** a top banner reads "Currently Streaming" and a bottom banner shows
  the media details and the streaming user's name

#### Scenario: Episode details
- **WHEN** the now-playing item is a TV episode
- **THEN** the details identify the show and the episode being watched

### Requirement: Single vs. multiple streams

When exactly one qualifying session is active, the wall SHALL hold static on
that stream's poster. When more than one qualifying session is active, the wall
SHALL cycle through the now-playing posters, one at a time. The wall SHALL NOT
merge sessions: two sessions playing the same title SHALL appear as two
separate now-playing posters, each labeled with its own streaming user.

#### Scenario: One stream holds static
- **WHEN** exactly one qualifying session is active
- **THEN** the wall shows that stream's poster without cycling

#### Scenario: Multiple streams cycle
- **WHEN** more than one qualifying session is active
- **THEN** the wall cycles through the now-playing posters one at a time

#### Scenario: Same title is not deduplicated
- **WHEN** two sessions are playing the same title
- **THEN** the wall shows two separate now-playing posters, each with its own
  streaming user

### Requirement: Live TV placeholder

When a qualifying session is Live TV, the wall SHALL show a bundled placeholder
poster in place of library art, presented with the same streaming overlays. The
details SHALL include the program and channel when the session provides them,
and otherwise SHALL indicate that Live TV is playing.

#### Scenario: Live TV stream
- **WHEN** a qualifying session is Live TV
- **THEN** the wall shows a placeholder poster with the "Currently Streaming"
  and details overlays

#### Scenario: Live TV details when available
- **WHEN** a Live TV session provides a program and channel
- **THEN** the details show the program and channel

### Requirement: Music is excluded

Music playback SHALL NOT trigger now-playing mode or appear on the wall. A
session that is only music SHALL be ignored when deciding whether any stream is
active.

#### Scenario: Music does not take over
- **WHEN** the only active Plex session is music playback
- **THEN** the wall continues showing random posters and shows no now-playing
  poster for the music

### Requirement: Now-playing poster source

The wall SHALL obtain each now-playing poster from Plex for the item being
streamed rather than requiring the item to have been imported into the library.
A movie SHALL use its own poster and an episode SHALL use its show's poster.

#### Scenario: Poster for an un-imported item
- **WHEN** a streaming item has never been imported into the library
- **THEN** the wall still shows its poster, obtained from Plex

#### Scenario: Episode uses the show poster
- **WHEN** the now-playing item is a TV episode
- **THEN** the wall shows the show's poster

### Requirement: Wall is publicly accessible

The wall page and its supporting endpoints — random poster batches, active
streams, and now-playing posters — SHALL be reachable without authentication so
the wall can run on an unattended display without anyone signing in on the
device. These endpoints expose only poster art and now-playing details; they
SHALL NOT perform or expose any action that changes the library or the server.

#### Scenario: Wall opens without signing in
- **WHEN** an unauthenticated visitor opens the wall
- **THEN** the system serves the wall instead of redirecting to login

#### Scenario: Now-playing endpoints are reachable without signing in
- **WHEN** an unauthenticated visitor requests the active-streams data or a
  now-playing poster
- **THEN** the system serves it instead of redirecting to login

## REMOVED Requirements

### Requirement: Wall requires authentication

**Reason**: The wall is meant for an unattended display (a spare monitor or TV)
that should show posters and now-playing without anyone signing in on the
device; requiring authentication defeated that. The wall exposes only poster
art and now-playing details, never a management action.
**Migration**: None. The wall and its endpoints no longer redirect
unauthenticated visitors to login; no configuration change is required, and the
authentication-bypass option is no longer needed to run a kiosk.
