## ADDED Requirements

### Requirement: Every tray presents its title the same distance below its grab handle

Trays that dock to the bottom edge SHALL present their title at one distance
below the grab handle, and that distance SHALL NOT vary with which family of
overlay the tray is built from. A user who opens one tray after another SHALL
see the title land in the same place each time.

Marquee builds bottom trays from two lineages — a tray that only ever exists as
a tray, and a dialog that becomes one at phone width — and both wear the same
grab handle. Because they wear the same handle they read as one component, so
any difference in how they space themselves below it reads as misalignment
rather than as variety.

The distance SHALL be produced by the same declared spacing **and** the same
title type in both lineages. Equal spacing alone does not satisfy this
requirement: a title's leading contributes to the distance the eye measures, so
two heads that declare identical spacing but set their titles at different
sizes or line heights still present the title at different distances.

Where a normalization changes this distance, the value adopted SHALL be the
largest of those already in use, so that no tray's title moves closer to its
handle and no tray's title is set smaller than it was.

#### Scenario: Trays from different lineages agree

- **WHEN** a user opens a tray built as a tray (Sort, Import from Plex,
  Orphaned posters, Settings, Actions, Poster actions) and then one built as a
  dialog docked to the bottom edge (Change poster, a confirmation, Support
  development)
- **THEN** the distance from the bottom of the grab handle to the tray's title
  is the same in both

#### Scenario: The titles are set the same way

- **WHEN** the title of any two trays is compared
- **THEN** both are set at the same size and the same line height, so that the
  space above the title's glyphs contributes equally in each

#### Scenario: Normalizing never tightens a tray

- **WHEN** the shared distance is established from trays that previously
  differed
- **THEN** every tray's handle-to-title distance is greater than or equal to
  what it was, and no tray's title is set smaller than it was

#### Scenario: A new tray inherits the spacing

- **WHEN** a tray is added
- **THEN** it presents its title at the shared distance without declaring
  spacing of its own, and no per-tray exception is needed to place it correctly

### Requirement: A tray head is sized by its title, not by what else it holds

A tray head SHALL take its height from the line its title occupies. Anything
else the head holds — an icon, a mark, a badge, a close control — SHALL be
placed against that line without enlarging it.

A head that lets its tallest occupant set its height moves the title whenever
that occupant changes, which silently breaks the shared distance the previous
requirement establishes. The failure is invisible in the head that causes it:
the head looks balanced on its own, and only reveals itself when the tray is
opened next to another one.

An adornment taller than the title's line SHALL extend equally above and below
that line rather than pushing the title down, and SHALL remain clear of the
grab handle above it.

#### Scenario: An adorned head places its title like any other

- **WHEN** a tray head holds an icon or mark alongside its title, as Support
  development holds its heart
- **THEN** the title sits at the same distance below the grab handle as the
  title of a tray head holding nothing but a title

#### Scenario: An adornment stays level with the title it accompanies

- **WHEN** a head holds an adornment taller than its title's line
- **THEN** the adornment is centred on that line, extending equally above and
  below it, rather than sitting above or below the title

#### Scenario: An adornment does not crowd the handle

- **WHEN** a head holds an adornment that extends above its title's line
- **THEN** a visible gap remains between the bottom of the grab handle and the
  top of the adornment, so the handle reads as a separate affordance

#### Scenario: Changing an adornment does not move the title

- **WHEN** an adornment in a tray head is resized, replaced, or removed
- **THEN** the title stays where it was, and every other tray's title is
  unaffected
