## MODIFIED Requirements

### Requirement: Force a full re-import
The import screen SHALL let the user force re-downloading posters that would
otherwise be skipped.

The option SHALL be offered on exactly the condition that makes the Import action
available, and SHALL NOT be offered before it. An import option that can be set
while no import can be started invites the user to configure a run that does not
exist, and the option carries no visible answer to what it applies to.

Because the option's setting survives the option leaving the screen, tying it to
the Import action's own condition is also what keeps it honest: while the form
cannot be submitted the option cannot influence anything, and the moment the form
can be submitted the option is on screen with its current setting shown.

#### Scenario: Forced re-import ignores the skip check
- **WHEN** the user starts an import with the re-download option enabled
- **THEN** the system downloads every selected poster regardless of whether it
  changed

#### Scenario: The option is not offered before an import can be started
- **WHEN** the import screen has no content type selected, or a content type
  selected and no library selected
- **THEN** the re-download option is not offered

#### Scenario: The option appears with the Import action
- **WHEN** the user's selection first becomes complete enough for the Import
  action to be available
- **THEN** the re-download option is offered at the same time

#### Scenario: A set option is never applied out of sight
- **WHEN** the user enables the re-download option and then reduces the selection
  so that no import can be started
- **THEN** no import can run with the option applied while it is off screen, and
  completing a selection again shows the option with its setting intact
