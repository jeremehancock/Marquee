## ADDED Requirements

### Requirement: The Plex server address is withheld from the screen
The system SHALL NOT offer the Plex server address on the settings screen.

The address is not merely a setting: it is an assertion only someone with host
access can make, and it is what stops the first stranger who reaches an
unconfigured install from claiming it as their own. Ownership is verified against
*that* address, so an address chosen in the browser verifies nothing. It SHALL
remain outside the screen until the property it provides has been replaced.

#### Scenario: The server address is not editable on the settings screen
- **WHEN** a user opens the settings screen
- **THEN** no field offers the Plex server address

### Requirement: Auto-import is configured on the settings screen
The system SHALL offer auto-import on the settings screen: whether it is enabled,
which media types it imports, and how often it runs.

The controls SHALL take effect without restarting or recreating the container,
like every other setting on the screen. They differ in one way that SHALL be
stated rather than left to be discovered: the rest of the screen applies to the
next request, while these apply to the next scheduled tick, because that is when
anything reads them.

The interval SHALL be offered as the same set of choices the container's schedule
used to encode, so that an install upgrading into this screen finds the schedule
it already had.

#### Scenario: Auto-import is enabled from the browser
- **WHEN** a user enables auto-import and saves
- **THEN** scheduled imports begin without the container being restarted

#### Scenario: Auto-import is disabled from the browser
- **WHEN** a user disables auto-import and saves
- **THEN** no further scheduled import runs, without the container being
  restarted

#### Scenario: The interval is changed
- **WHEN** a user changes how often auto-import runs and saves
- **THEN** the new interval governs from the next scheduled tick

#### Scenario: The screen does not promise the wrong timing
- **WHEN** a user saves an auto-import setting
- **THEN** the screen describes it as taking effect on the next scheduled run
  rather than on the next page load

## REMOVED Requirements

### Requirement: Settings deliberately withheld from the screen
**Reason**: Half of it no longer holds. The requirement withheld the Plex server
address and the auto-import settings together, for unrelated reasons — the first
because it is a trust anchor, the second because the schedule was fixed into the
container at boot and a control would have lied. This change makes the schedule
the application's, so the auto-import half is overturned by design; that it had
to be overturned rather than merely filled in is why it was written as a
requirement.

**Migration**: The server-address half is carried forward unchanged as "The Plex
server address is withheld from the screen" above, and is retired in its turn
when the claim code replaces the property it provides. The auto-import half is
replaced by "Auto-import is configured on the settings screen".
