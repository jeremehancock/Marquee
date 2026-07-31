## MODIFIED Requirements

### Requirement: A changed poster is visible immediately
After any operation that replaces a poster's image, the system SHALL present the
new image on the next page render, without requiring the user to reload the page
or clear a cache. A success message SHALL NOT be shown alongside the previous
image.

Presenting the new image SHALL NOT disturb the gallery around it: the user's
scroll position, the extent of the grid they have already loaded, and every
other poster's card SHALL be exactly as they were before the operation. Only the
changed poster's own card may be re-rendered.

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

#### Scenario: The gallery holds its position across a change
- **WHEN** a user scrolls into the gallery and changes a poster
- **THEN** the gallery is at the same scroll position when the operation
  finishes, with the changed poster's card where it was

#### Scenario: A change does not discard posters already loaded
- **WHEN** a user has extended the grid past its first page and then changes a
  poster
- **THEN** every poster already in the grid stays in it, and none is fetched or
  rendered again

#### Scenario: A change to a poster outside the current grid still shows
- **WHEN** an operation replaces the image of a poster whose card is not present
  in the gallery currently on screen
- **THEN** the system falls back to re-rendering the gallery, so the view is
  never left showing a stale image

## ADDED Requirements

### Requirement: Operations that store no new image leave the gallery alone
An operation that does not replace the poster's stored image SHALL NOT re-render
the gallery. It SHALL report its outcome and leave the grid, its scroll
position, and every card untouched.

#### Scenario: Re-sending to Plex does not re-render the gallery
- **WHEN** a user sends a stored poster to Plex
- **THEN** the result is reported and no card, count, or scroll position in the
  gallery changes

#### Scenario: A failed change leaves the gallery untouched
- **WHEN** an operation that would have replaced a poster's image fails
- **THEN** the failure is reported and the poster's card keeps the image it was
  already showing
