## MODIFIED Requirements

### Requirement: Streaming overlays

Each now-playing poster SHALL carry two overlay banners: a top banner reading
"Currently Streaming" and a bottom banner showing the media details and the
name of the Plex user who is streaming it. The media details SHALL identify the
title, and for an episode SHALL include its show and episode information.

The banners SHALL be sized against the poster they overlay rather than against
the display. Across the range of poster sizes where a fixed banner size fits,
the banners SHALL hold that fixed size. Above that range they SHALL grow in
proportion to the poster, so the overlays keep their visual weight on a large
display. Below that range they SHALL shrink in proportion to the poster,
because the fixed size no longer fits.

Banner text SHALL remain legible when the wall is embedded in a small frame,
such as a dashboard widget, where the frame shrinks but the viewer's distance
to it does not. The wall SHALL NOT require the embedding page to opt in for
this to hold.

Every dimension of the banners — type, padding, spacing, and decoration — SHALL
scale together from a single controlling size, so the banners keep their
internal proportions at every size and cannot drift out of proportion with
themselves. The label's decorative spacing, which the requirement above allows
to give way in a narrow frame, is the sole exception.

The "Currently Streaming" label SHALL render on a single line at every frame
size the wall supports, and its wording SHALL NOT change to achieve this. Where
the label's width and the banner type compete for a narrow frame, the label's
decorative spacing SHALL give way before the type does.

The banners SHALL NOT dominate the poster: they exist to label it, and the
poster SHALL remain the primary subject of the tile.

#### Scenario: Overlays on a now-playing poster
- **WHEN** the wall shows a now-playing poster
- **THEN** a top banner reads "Currently Streaming" and a bottom banner shows
  the media details and the streaming user's name

#### Scenario: Episode details
- **WHEN** the now-playing item is a TV episode
- **THEN** the details identify the show and the episode being watched

#### Scenario: Overlays hold one size across ordinary displays
- **WHEN** the wall is shown full-screen on displays ranging from a phone up to
  a 1080p monitor
- **THEN** the banner text renders at the same size on each, rather than
  shrinking as the display gets smaller

#### Scenario: Overlays grow on a large display
- **WHEN** the wall is shown full-screen on a display larger than 1080p
- **THEN** the banner text grows in proportion to the poster, so it keeps the
  visual weight it has at 1080p

#### Scenario: Overlays stay legible in a small embedded frame
- **WHEN** the wall is embedded in a frame too small for the banners to hold
  their fixed size, such as a 350px-square dashboard widget
- **THEN** the banner text renders larger than its share of the poster alone
  would give it, remaining legible at the distance the host display is viewed
  from

#### Scenario: The streaming label stays on one line
- **WHEN** the wall is embedded in a frame at the small end of the supported
  range
- **THEN** the "Currently Streaming" label fits on a single line within the
  poster's width, its decorative letter-spacing and padding reduced as needed to
  do so, and its wording unchanged

#### Scenario: Banners keep their internal proportions
- **WHEN** the banners change size for any reason
- **THEN** their type, padding, spacing, and decoration all change together
  from one controlling size, preserving the layout they have at every other
  size, save for the label's decorative spacing in a narrow frame
