## MODIFIED Requirements

### Requirement: The documented configuration surface is chosen by audience
Documentation of an environment variable SHALL follow from who is expected to set
it, not from whether the code happens to read it. Reading a variable is not by
itself a reason to publish it.

Variables an install is expected to set SHALL be listed in the README's
configuration table with their default. Variables that exist only for local
development SHALL be documented in `docs/development-workflow.md` instead, where
the toolchain they serve is described, so the user-facing table stays a list of
decisions a user actually has to make.

A variable that becomes a setting the store owns SHALL leave the README's
configuration table, because an install is no longer expected to set it. The rule
is unchanged by such a move; only which variables satisfy it changes. The test's
positive control SHALL therefore be a variable that cannot become a setting —
otherwise the control is retired by the next change that relocates one, and the
absence assertions it protects quietly lose their meaning.

The layout of the `/config` volume SHALL be presented as fixed. `DATA_DIR` and
`POSTERS_DIR` SHALL therefore remain absent from the README, even though the code
reads them: the README's promise is that backing up `/config` backs up
everything, and advertising the subpaths as movable invites installs that split
the volume and then discover the promise no longer holds for them. They remain
overridable for the operator who already knows they exist; they are not offered.

This exclusion SHALL extend to every user-facing document, not the README alone.
A variable withheld from the README because the volume layout is fixed is
withheld for a reason that a second user-facing page does not change, so
documenting it there would overturn the decision while leaving the test that
guards it passing.

This split SHALL be asserted by a test, for the same reason each default is: a
decision recorded only in prose is one a later edit reverses without anything
failing. The test SHALL assert both directions — that the variables meant to be
documented are present where they belong, and that the ones deliberately withheld
are absent — so that neither an accidental removal nor an accidental addition
passes unnoticed.

#### Scenario: A developer-only setting is kept out of the user-facing table
- **WHEN** a reader looks up `DISPLAY_ERRORS`
- **THEN** it is described in `docs/development-workflow.md`
- **AND** it does not appear in the README's configuration table

#### Scenario: The volume layout reads as fixed
- **WHEN** a reader consults the README for what `/config` contains
- **THEN** the posters, data, and session directories are described as its
  contents
- **AND** no environment variable is offered for relocating them individually

#### Scenario: A withheld variable stays withheld across every user-facing page
- **WHEN** a user-facing page other than the README documents configuration
- **THEN** `DATA_DIR` and `POSTERS_DIR` are absent from it too
- **AND** it refers the reader to `docs/development-workflow.md` rather than
  restating them

#### Scenario: An undocumented setting is still pinned by a test
- **WHEN** a variable is deliberately left out of the user-facing documentation
- **THEN** its default is still asserted by a test, so that omitting it from the
  documentation does not also omit it from the guarantees

#### Scenario: Adding a withheld variable to the README fails a test
- **WHEN** an edit names `DATA_DIR` or `POSTERS_DIR` in the README
- **THEN** a test fails, requiring the decision to be overturned deliberately
  rather than drifting

#### Scenario: Removing a documented variable from the README fails a test
- **WHEN** an edit removes a variable an install is expected to set, such as
  `SESSION_DIR`, from the README
- **THEN** a test fails, so the absence assertions cannot be satisfied by
  documentation that has gone missing entirely

#### Scenario: A relocated setting leaves the README without breaking the control
- **WHEN** a variable listed as the test's positive control becomes a setting the
  store owns
- **THEN** the control is replaced with a variable that cannot become a setting
- **AND** the relocated variable is removed from the README's configuration table
