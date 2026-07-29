# Poster Wall Specification

## Purpose

A full-screen, slideshow-style view of the library — posters drawn at random
from every category, cross-fading indefinitely — that becomes a live now-playing
board while Plex is streaming. Intended for a spare display or a TV, which is why
it opens in its own tab and is reachable without signing in, so an unattended
device needs no login and no authentication-bypass.
## Requirements
### Requirement: Full-screen rotating wall
The system SHALL provide a full-screen page that continuously displays posters
drawn at random from the library, transitioning between them automatically. The
wall SHALL open in a separate browser tab so the gallery stays open behind it.
The wall is intended for unattended display on a monitor, so it SHALL present the
posters without on-screen navigational chrome such as an exit control; a viewer
leaves by closing the tab.

#### Scenario: Wall displays posters
- **WHEN** a visitor opens the wall and the library has posters
- **THEN** the system presents a full-screen display that rotates through random
  posters

#### Scenario: Open the wall
- **WHEN** a user opens the Poster Wall from the gallery
- **THEN** it opens in a new tab

#### Scenario: No on-screen exit control
- **WHEN** the wall is displayed
- **THEN** it shows no exit or navigation control overlaid on the posters

#### Scenario: Empty library
- **WHEN** the library has no posters
- **THEN** the wall shows a message that there is nothing to display yet

### Requirement: Random poster batches
The system SHALL expose an endpoint that returns a fresh batch of random poster
references so the wall can keep refreshing without a full reload.

#### Scenario: Batch of random posters
- **WHEN** the wall requests more posters
- **THEN** the system returns a batch of poster references selected at random
  from across the library's categories

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

The banners SHALL be sized in proportion to the poster they overlay rather than
to the display, so that the overlays keep the same visual weight against the
poster at any display size or aspect ratio. The banners SHALL NOT dominate the
poster: they exist to label it, and the poster SHALL remain the primary subject
of the tile.

#### Scenario: Overlays on a now-playing poster
- **WHEN** the wall shows a now-playing poster
- **THEN** a top banner reads "Currently Streaming" and a bottom banner shows
  the media details and the streaming user's name

#### Scenario: Episode details
- **WHEN** the now-playing item is a TV episode
- **THEN** the details identify the show and the episode being watched

#### Scenario: Overlays keep their proportions across display sizes
- **WHEN** the wall is shown on displays of differing size or aspect ratio
- **THEN** the banner text occupies the same proportion of the poster on each,
  rather than growing or shrinking relative to it

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

A session SHALL be treated as Live TV whenever Plex marks it as live, regardless
of the media type Plex reports for it. Live television delivered through a DVR
tuner is commonly reported as a movie or an episode rather than as a clip, and
such a session SHALL receive the Live TV placeholder and Live TV details rather
than being presented as a library item.

#### Scenario: Live TV stream
- **WHEN** a qualifying session is Live TV
- **THEN** the wall shows a placeholder poster with the "Currently Streaming"
  and details overlays

#### Scenario: Live TV details when available
- **WHEN** a Live TV session provides a program and channel
- **THEN** the details show the program and channel

#### Scenario: DVR session reported as a movie or episode
- **WHEN** Plex reports a live session with a media type of movie or episode
- **THEN** the wall treats it as Live TV, showing the placeholder poster and Live
  TV details rather than resolving library art for it

### Requirement: Music is excluded

Music playback SHALL NOT trigger now-playing mode or appear on the wall. A
session that is only music SHALL be ignored when deciding whether any stream is
active. This SHALL hold even when Plex marks the music session as live, so live
radio does not take over the display.

#### Scenario: Music does not take over
- **WHEN** the only active Plex session is music playback
- **THEN** the wall continues showing random posters and shows no now-playing
  poster for the music

#### Scenario: Live music does not take over
- **WHEN** the only active Plex session is music that Plex marks as live
- **THEN** the wall continues showing random posters and shows no now-playing
  poster for it

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

### Requirement: A now-playing tile always shows a poster

A now-playing tile SHALL always render a poster image. When the poster for a
streaming item cannot be obtained — because the session carries no usable poster
reference, because the reference cannot be resolved, or because the image fails
to load — the wall SHALL show the bundled placeholder in its place rather than
an empty frame. The overlays SHALL remain visible in every such case, so the
viewer still sees what is streaming and who is streaming it.

#### Scenario: Poster reference cannot be resolved
- **WHEN** the wall requests a now-playing poster whose reference the system
  cannot resolve to a Plex image
- **THEN** the system serves the placeholder poster instead of reporting an
  error

#### Scenario: Poster image fails to load
- **WHEN** a now-playing poster image fails to load in the wall page
- **THEN** the wall shows the placeholder poster in that tile with its overlays
  intact, and never shows an empty frame

#### Scenario: Session carries no poster reference
- **WHEN** a qualifying session provides no poster reference, or one that is not
  a Plex library image
- **THEN** the wall shows the placeholder poster for that session

#### Scenario: A stand-in placeholder does not outlast the failure
- **WHEN** a now-playing poster is temporarily unavailable, the wall shows the
  placeholder in its stead, and the poster then becomes available again
- **THEN** the wall shows the real poster on a subsequent rotation rather than
  continuing to show the placeholder

#### Scenario: The Live TV placeholder is not treated as a failure
- **WHEN** the wall shows the placeholder because a session is Live TV rather
  than because a poster could not be obtained
- **THEN** the system may serve it from cache, since it is the intended art for
  that session rather than a stand-in for a missing one

