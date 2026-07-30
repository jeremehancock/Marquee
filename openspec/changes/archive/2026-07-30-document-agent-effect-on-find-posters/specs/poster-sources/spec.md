## ADDED Requirements

### Requirement: What Plex knows about an item is explained to users
Search accuracy depends on whether Plex identified the work behind an item, which
is decided by the metadata agent the user's Plex library was built with and is
not visible from inside Marquee. The product documentation SHALL explain this to
users, and SHALL do so as optional troubleshooting for poor Find Posters results
— never as a setup step, a prerequisite, or a recommended configuration.

The explanation SHALL state that title-only matching is expected and correct for
items that have no upstream work to identify, so that a user whose only affected
items are of that kind is told to change nothing. It SHALL scope the suggestion
to switch a library's metadata agent to a whole library performing badly, and
SHALL state the cost of switching together with the suggestion rather than after
it.

That cost SHALL be stated in terms of which posters are protected and which are
not, and SHALL NOT be reduced to a general warning that artwork may change. A
poster the system has uploaded to Plex is locked and survives a metadata
refresh; a poster the system only imported was never uploaded, so it was never
locked, and Plex may replace it. A user who has read that the system locks
posters will otherwise conclude the whole library is protected, which is false
for every item they have not applied a poster to.

The explanation SHALL also give the recovery path — the stored poster can be
sent to Plex again — together with its ordering constraint: that this must
happen before the next import, because importing replaces the system's stored
poster with the artwork Plex holds at that moment. Import is what a user reaches
for when posters look wrong, so omitting the ordering turns a recoverable loss
into a permanent one.

The explanation SHALL describe the metadata agent setting in general terms only.
It SHALL NOT name Plex's menus, screens, or setting labels, or give a click path,
because those change without anything here failing. It SHALL NOT describe how
Marquee obtains a work's identifier from Plex, in what form Plex reports it, or
which agents report it — that is implementation detail the reader cannot act on.

#### Scenario: README explains poor results
- **WHEN** a reader whose Find Posters results are thin or wrong consults the
  README
- **THEN** they find that accuracy depends on Plex having identified the item,
  and what they can do about it

#### Scenario: Framed as optional
- **WHEN** a reader follows the README's setup or usage instructions
- **THEN** nothing there asks them to inspect or change a Plex metadata agent,
  and the explanation is reachable only as troubleshooting

#### Scenario: A satisfied user is told to do nothing
- **WHEN** a reader whose Find Posters results are already good reads the
  explanation
- **THEN** it tells them there is nothing they need to do

#### Scenario: Expected cases are separated from fixable ones
- **WHEN** the documentation describes why a search fell back to the title
- **THEN** it states that collections, personal media, and items Plex has not
  matched are expected to search by title and are not a fault to fix

#### Scenario: The cost of switching agents is stated with the suggestion
- **WHEN** the documentation suggests switching a library's metadata agent
- **THEN** the re-scan it triggers, and what that re-scan can change, are stated
  in the same place as the suggestion

#### Scenario: Locked and unlocked posters are distinguished
- **WHEN** the documentation warns that a metadata refresh can change artwork
- **THEN** it states that posters applied through the system are locked in Plex
  and stay, and that a poster the system only imported is not locked and can be
  replaced
- **AND** it does not describe the risk only in general terms that leave a user
  who has read about poster locking believing every item is protected

#### Scenario: The recovery path and its ordering are given
- **WHEN** the documentation warns that a poster may be replaced in Plex
- **THEN** it states that the system's stored poster can be sent to Plex again
- **AND** that this must be done before the next import, because importing
  replaces the stored poster with the artwork Plex holds at that moment

#### Scenario: No Plex navigation is published
- **WHEN** the documentation refers to the metadata agent setting
- **THEN** it identifies the setting in general terms without naming Plex's
  menus or setting labels and without giving a click path

#### Scenario: No implementation detail is published
- **WHEN** the documentation explains why some items search by title
- **THEN** it does not describe how Marquee obtains an identifier from Plex, the
  form Plex reports it in, or which agents report it
