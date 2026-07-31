## ADDED Requirements

### Requirement: Candidate images load progressively
The candidate grid SHALL present each candidate's image the way the poster
gallery presents a poster card: the cell SHALL reserve the candidate's space at
poster proportions before the image arrives, SHALL show the same subtle
placeholder animation while it has not resolved, and SHALL fade the image in once
it does. The placeholder SHALL stop whether the image loads or fails.

Candidate images SHALL be deferred until they are at or near the visible part of
the results, so opening Find Posters does not fetch every candidate's image at
once. Deferral SHALL be relative to the region the results actually scroll in,
not only the page as a whole, because the results scroll within their own
container.

The full-screen candidate preview SHALL show the placeholder while the
full-resolution image loads and fade the image in once it resolves, and its
action bar SHALL keep its position while the image is loading, so the controls do
not move when the poster appears. As with the library's full-screen view, one
preview is reused for every candidate, so previewing a different candidate SHALL
return the preview to its unresolved state rather than showing the previously
previewed candidate.

#### Scenario: Candidate grid before images arrive
- **WHEN** the found-poster results are shown and their images have not yet
  resolved
- **THEN** each candidate's cell holds its space at poster proportions and shows
  the placeholder animation, and each image fades in as it resolves

#### Scenario: Candidate image fails to load
- **WHEN** a candidate's grid image fails to load
- **THEN** that cell's placeholder animation stops rather than continuing to
  suggest the image is still loading

#### Scenario: Off-screen candidates are not fetched up front
- **WHEN** the results contain more candidates than fit in the visible results
  area
- **THEN** the images for candidates well outside that area are not fetched until
  scrolling brings them near it

#### Scenario: Preview before the full-resolution image arrives
- **WHEN** a user opens a candidate full screen and its full-resolution image has
  not yet resolved
- **THEN** the placeholder animation is shown where the poster will appear, the
  action bar stays in the position it will hold once the poster is shown, and the
  poster fades in once it resolves

#### Scenario: Previewing a second candidate
- **WHEN** a user previews one candidate, closes the preview, and previews a
  different candidate
- **THEN** the preview starts unresolved again and never shows the previously
  previewed candidate
