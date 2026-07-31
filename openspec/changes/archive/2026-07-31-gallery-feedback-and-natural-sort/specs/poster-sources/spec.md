## ADDED Requirements

### Requirement: Applying a found poster indicates progress and runs once
Applying a found poster is never fast: the system fetches the full-resolution
image from a third-party source and, when the poster is linked and Plex is
configured, uploads it to Plex and locks it. From the moment the user confirms
the change until it succeeds or fails, the system SHALL indicate that the change
is running, and SHALL prevent a second change from being started from the same
confirmation.

The indication SHALL be shown immediately on confirmation, without the grace
period that defers the gallery's loading indication for in-place view changes.
That deferral exists so a view change which may resolve from cache does not
produce a visible flicker; applying a found poster has no comparable fast path,
so deferring would only prolong the silence this requirement exists to remove.

The indication SHALL cover the full-screen candidate preview, because that is
what the user is looking at when they confirm and it sits above the
change-poster dialog.

The confirmation control SHALL be disabled while the change is in flight, and
a repeated activation SHALL be ignored even if it is registered before the
indication is displayed, so that the disabled state communicates and does not
have to be relied upon to enforce.

The indication SHALL be cleared when the change fails as well as when it
succeeds, leaving the preview usable rather than stranded, and a failure SHALL
be reported to the user.

#### Scenario: Progress is shown while the change runs
- **WHEN** a user confirms applying a found poster and the change has not yet
  completed
- **THEN** the system indicates that the change is running, over the full-screen
  candidate preview

#### Scenario: Progress is shown without delay
- **WHEN** a user confirms applying a found poster
- **THEN** the indication appears immediately rather than after a grace period

#### Scenario: The change cannot be started twice
- **WHEN** a user activates the confirmation control a second time while a
  change is already in flight
- **THEN** no second change is started, and the poster is fetched and uploaded
  to Plex only once

#### Scenario: Progress clears when the change succeeds
- **WHEN** an applied change completes successfully
- **THEN** the indication is cleared, the preview and the change-poster dialog
  are closed, the result is reported, and the gallery is refreshed

#### Scenario: Progress clears when the change fails
- **WHEN** an applied change fails
- **THEN** the indication is cleared, the failure is reported to the user, and
  the preview remains usable so the user can retry or choose another candidate

#### Scenario: A failed response is reported as a failure
- **WHEN** the request to apply a candidate returns an unsuccessful response
- **THEN** the system reports the change as having failed rather than treating
  the response as a successful change

## MODIFIED Requirements

### Requirement: Preview and apply a found poster
The system SHALL let a user open any candidate full screen to inspect it before
committing, and SHALL apply a candidate only through an explicit confirmation
taken from that preview, which replaces the poster in place and, when linked and
configured, pushes it to Plex and locks it. Where the source supplies a
reduced-size preview image for a candidate, the system SHALL use it for the
candidate grid, and SHALL use the full-resolution image when inspecting a
candidate full screen and when applying it.

Applying is a two-step commitment rather than a single action on the grid: a
candidate in the grid is activated to inspect it full screen, the preview offers
to use that candidate, and using it asks for a final confirmation before the
poster is changed. The user SHALL be able to abandon the change at either step
without the poster being altered.

#### Scenario: Preview then apply
- **WHEN** a user views the found-poster results
- **THEN** they can open a candidate full screen to inspect it, and apply it
  only from that full-screen preview

#### Scenario: Found-poster action label
- **WHEN** a user is previewing a candidate full screen
- **THEN** the action that applies it is labelled "Use this poster", and the
  confirmation it asks for is labelled "Change poster"

#### Scenario: Applying requires a confirmation
- **WHEN** a user chooses to use the candidate they are previewing
- **THEN** the system asks for a final confirmation, and the poster is changed
  only once that confirmation is given

#### Scenario: Abandoning the preview leaves the poster unchanged
- **WHEN** a user closes the preview, or declines the final confirmation
- **THEN** the poster is not changed and the user is returned to the results

#### Scenario: Apply a candidate
- **WHEN** a user confirms applying a candidate poster from the results
- **THEN** the system fetches that image, overwrites the poster's file, and (when
  linked) uploads it to Plex and locks it

#### Scenario: Grid uses reduced-size images where available
- **WHEN** the results contain candidates for which the source supplied a
  reduced-size image
- **THEN** the candidate grid loads those images rather than the full-resolution
  originals

#### Scenario: Applying uses the full-resolution image
- **WHEN** a user applies a candidate whose grid image was a reduced-size preview
- **THEN** the system fetches and stores the full-resolution image, not the
  preview
