## ADDED Requirements

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
