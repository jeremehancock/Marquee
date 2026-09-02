## MODIFIED Requirements

### Requirement: Plex item mapping
The system SHALL record, for each stored poster, the Plex item it came from (by
rating key) together with the Plex library section that item belongs to, the
item's Plex "added at" timestamp, the item's release year where Plex reports one,
the item's TMDB identifier where Plex reports one, and — for seasons — the Plex
season number and the title of the show the season belongs to, so later operations
can address the item for artwork, locking, and label edits, so the gallery can
order posters by when their media was added to Plex, and so a poster search can be
built from recorded facts rather than re-parsed from a display title. On re-import the system SHALL overwrite the same
poster file rather than creating a duplicate.

A TMDB identifier is recorded only where Plex reports one. Movies and shows carry
their own; a season carries its **show's** identifier, because a season is
addressed as a show together with a season number; a collection carries none,
because a Plex collection is a local grouping with no upstream record. An item
Plex never matched carries none. A missing identifier SHALL be recorded as
unknown and SHALL NOT be treated as an error.

A season SHALL likewise carry its **show's** release year, for the same reason it
carries the show's identifier: a season search resolves the show and then the
season within it, so the year that identifies the work is the show's. A season has
no release year of its own, and the show's is known at the moment the season is
imported. Recording it is what lets a search tell two shows that share a title
apart when the recorded identifier is not one the source recognises — without it
those shows are indistinguishable and the search resolves to whichever is more
popular.

A season SHALL also record its **show's title**, as a fact in its own right rather
than as part of the season's display title. The display title a season is stored
under is the show's title and the season's joined together ("Breaking Bad -
Season 5"), and that joining cannot be undone by inspecting the result: splitting
at the first separator misreads a show whose own name contains one ("Cowboy Bebop
- Remastered"), and splitting at the last misreads a season whose name does ("Part
2 - Finale"). Plex reports the two separately at the moment the season is
imported, so nothing has to be guessed. Recording it is what lets an action
started from a season find the show and its sibling seasons rather than only the
season it started from.

An item that is not a season SHALL record no show title, and that SHALL NOT be
treated as missing information — a movie, show, or collection has no parent to
name.

A mapping records what the Plex item was at the moment it was imported, and a
Plex item does not hold still. Correcting a bad match — Plex's "Fix Match" — keeps
the item's rating key but replaces the work behind it: a new title, a new release
year, a new TMDB identifier. The system SHALL therefore treat the recorded facts
as a cache to be reconciled rather than as a record written once. On re-import,
when a mapping already exists for an item's rating key, the system SHALL compare
the item's current title, release year, TMDB identifier and — for a season — show
title against the recorded ones and SHALL update the mapping wherever they differ. A recorded fact SHALL NOT
be replaced with an unknown one: where Plex now reports nothing, what is already
recorded stands, because losing a known fact is worse than holding a stale one.

This reconciliation SHALL run whether or not the poster itself is downloaded. The
item most in need of it is the one whose artwork did not change — a poster the
user has customised and locked in Plex keeps its artwork across a re-match, so
the download is skipped and the skip path is the only place the correction can
happen. An item whose recorded facts all still match SHALL cause no write, so a
scheduled import over an unchanged library costs no more than it does today.

Reconciliation SHALL NOT depend on the poster download succeeding. An item's
identity is reported by the library listing, not carried by its artwork, so it is
already known before any image is fetched. A poster that cannot be downloaded
SHALL still be reported as failed, but the item's recorded facts SHALL be
corrected regardless. The two failures coincide rather than being independent:
Plex regenerates artwork immediately after a corrected match, so the artwork path
read from the listing can be momentarily unfetchable for exactly the item whose
identity most needs correcting. Left coupled, such an item would keep describing
the wrong work for as long as the fetch kept failing. The recorded artwork
version SHALL NOT be advanced when the download fails, so a later import still
recognises the poster as outstanding and fetches it again.

#### Scenario: Re-import overwrites, not duplicates
- **WHEN** a user imports a library and later imports it again
- **THEN** each item's poster is updated in place and no duplicate poster is
  created

#### Scenario: Section recorded on import
- **WHEN** an item is imported from a Plex library
- **THEN** the system stores that library's section identifier with the item's
  poster mapping

#### Scenario: Added-at recorded on import
- **WHEN** an item that reports a Plex "added at" timestamp is imported
- **THEN** the system stores that timestamp with the item's poster mapping so it
  can order the gallery by date added

#### Scenario: Missing added-at does not fail import
- **WHEN** an item does not report a Plex "added at" timestamp
- **THEN** the import still records the mapping and the poster remains browsable

#### Scenario: Release year recorded on import
- **WHEN** a movie or show that reports a release year is imported
- **THEN** the system stores that year with the item's poster mapping

#### Scenario: A season records its show's release year
- **WHEN** a TV season is imported and Plex reports a release year for its show
- **THEN** the system stores the show's release year with the season's poster
  mapping, so a search for that season can distinguish shows that share a title

#### Scenario: A season of a show with no release year still imports
- **WHEN** a TV season is imported and Plex reports no release year for its show
- **THEN** the system records the mapping with the year left unknown and the
  poster remains browsable

#### Scenario: Season number recorded on import
- **WHEN** a TV season is imported
- **THEN** the system stores the Plex season number for that season with the
  item's poster mapping, including zero for a Specials season

#### Scenario: A season records its show's title
- **WHEN** a TV season is imported
- **THEN** the system stores the title of the show it belongs to with the season's
  poster mapping, as a separate fact from the season's own display title

#### Scenario: An item that is not a season records no show title
- **WHEN** a movie, show, or collection is imported
- **THEN** the system records no show title for it and the import succeeds

#### Scenario: TMDB identifier recorded for a movie or show
- **WHEN** a movie or show for which Plex reports a TMDB identifier is imported
- **THEN** the system stores that identifier with the item's poster mapping

#### Scenario: A season records its show's TMDB identifier
- **WHEN** a TV season is imported and Plex reports a TMDB identifier for its show
- **THEN** the system stores the show's identifier with the season's poster
  mapping, alongside the season number already recorded for it

#### Scenario: Collections record no TMDB identifier
- **WHEN** a collection is imported
- **THEN** the system records no TMDB identifier for it and the import succeeds

#### Scenario: An unmatched item records no TMDB identifier
- **WHEN** an item for which Plex reports no TMDB identifier is imported
- **THEN** the system records the mapping with the identifier left unknown and
  the poster remains browsable

#### Scenario: A server that reports no identifiers does not fail the import
- **WHEN** an import runs against a Plex server that reports no TMDB identifiers
  for any item
- **THEN** every item is imported with its identifier left unknown, and no item
  is reported as failed

#### Scenario: A skipped item still gains a missing TMDB identifier
- **WHEN** an import skips an item because its poster is unchanged, and the
  stored mapping has no TMDB identifier while Plex now reports one
- **THEN** the system records the identifier without downloading the poster, and
  still counts the item as skipped

#### Scenario: A skipped item still gains a missing release year
- **WHEN** an import skips an item because its poster is unchanged, and the
  stored mapping has no release year while Plex now reports one
- **THEN** the system records the year without downloading the poster, and still
  counts the item as skipped

#### Scenario: A skipped season still gains a missing show title
- **WHEN** an import skips a season because its poster is unchanged, and the
  stored mapping has no show title while Plex reports one
- **THEN** the system records the show title without downloading the poster, and
  still counts the item as skipped

#### Scenario: A re-matched item's recorded facts are corrected
- **WHEN** an item is re-imported and Plex now reports a different title, release
  year or TMDB identifier than the mapping recorded
- **THEN** the system replaces each differing fact with the one Plex now reports

#### Scenario: A re-matched item is corrected even when its poster is unchanged
- **WHEN** an import skips an item because its artwork is unchanged, and Plex now
  reports a different title, release year or TMDB identifier than the mapping
  recorded
- **THEN** the system corrects those facts without downloading the poster, and
  still counts the item as skipped

#### Scenario: A re-matched item is corrected even when its poster cannot be fetched
- **WHEN** an item is re-imported with a corrected title, year or TMDB identifier
  and fetching its poster from Plex fails
- **THEN** the system records the corrected facts, reports the item as failed,
  and leaves the recorded artwork version untouched so a later import fetches
  the poster again

#### Scenario: A skipped item does not have a recorded year overwritten
- **WHEN** an import skips an item whose stored mapping already records a release
  year and Plex reports that same year
- **THEN** the system leaves the recorded year as it is rather than rewriting it
  on every import

#### Scenario: An item whose facts are unchanged is not rewritten
- **WHEN** an import processes an item whose recorded title, release year and
  TMDB identifier all still match what Plex reports
- **THEN** the system leaves the mapping untouched rather than rewriting it on
  every import

#### Scenario: A recorded fact is not replaced by an unknown one
- **WHEN** an item is re-imported and Plex now reports no release year or no TMDB
  identifier while the mapping records one
- **THEN** the system keeps the recorded value rather than clearing it

#### Scenario: Missing year or season number does not fail import
- **WHEN** an item does not report a release year, or is not a season and so has
  no season number
- **THEN** the import still records the mapping and the poster remains browsable

#### Scenario: Existing mappings gain the new facts on re-import
- **WHEN** a library imported by an earlier version of Marquee is imported again
- **THEN** the stored mappings are updated with the release year, season number
  and TMDB identifier without requiring the user to delete or rebuild anything

#### Scenario: Existing season mappings gain their show's year without a rebuild
- **WHEN** a library whose seasons were imported without a release year is
  imported again and the seasons' posters are unchanged
- **THEN** those season mappings gain their show's release year without the user
  deleting, rebuilding, or forcing a re-download of anything

#### Scenario: Existing season mappings gain their show's title without a rebuild
- **WHEN** a library whose seasons were imported before show titles were recorded
  is imported again and the seasons' posters are unchanged
- **THEN** those season mappings gain their show's title without the user
  deleting, rebuilding, or forcing a re-download of anything
- **AND** a season whose show title already matches causes no write
