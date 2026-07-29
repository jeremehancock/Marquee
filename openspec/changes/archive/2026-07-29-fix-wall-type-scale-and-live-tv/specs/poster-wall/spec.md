## ADDED Requirements

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

## MODIFIED Requirements

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
