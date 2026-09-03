## ADDED Requirements

### Requirement: A set's name is recorded with its membership
The system SHALL record the name Plex reported for each item that **names** a set
— a collection, and a show — keyed by that item's rating key, at the point the
import already learns the set's membership.

This SHALL cost no additional request. A movie import already asks each
collection for its members and holds that collection's title while doing so; a
show import already walks each show and holds its title. The name SHALL be
recorded there.

The name SHALL be recorded as a fact about the set itself, not copied onto every
poster that belongs to it. A film records the rating keys of the sets it belongs
to and nothing about their names, so a collection renamed in Plex is corrected in
one place on the next import rather than on every member's row.

A set's name SHALL be recorded whether or not that set's own poster was imported,
because a user who imports only movie posters still sees their films' collections
named when a set is opened. A recorded name SHALL be updated when Plex reports a
different one, so a renamed collection is not left under its old name.

A set whose name cannot be read SHALL be left without one rather than failing the
import. A name is presentation; losing it costs a set its name on screen until the
next import and costs no poster anything.

#### Scenario: A collection's name is recorded on a movie import
- **WHEN** movie posters are imported from a library holding collections
- **THEN** each collection's name is recorded against its rating key
- **AND** no request is made beyond the ones the membership walk already makes

#### Scenario: A show's name is recorded on a season import
- **WHEN** season posters are imported
- **THEN** each show's name is recorded against its rating key

#### Scenario: A collection with no imported poster is still named
- **WHEN** movie posters are imported without collection posters
- **THEN** each collection's name is recorded
- **AND** a set opened from one of its films can be named

#### Scenario: A renamed collection is corrected
- **WHEN** a collection is renamed in Plex and an import runs
- **THEN** the recorded name becomes the new one

#### Scenario: A name is recorded once, not per member
- **WHEN** a collection holding many films is imported
- **THEN** its name is recorded against the collection's rating key
- **AND** the films record the collection's rating key without its name

#### Scenario: An unreadable name does not fail the import
- **WHEN** a collection's members can be read but its name cannot
- **THEN** the import completes, the membership is recorded, and the set is left
  without a recorded name
