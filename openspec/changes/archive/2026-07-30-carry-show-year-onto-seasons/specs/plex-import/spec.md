## MODIFIED Requirements

### Requirement: Plex item mapping
The system SHALL record, for each stored poster, the Plex item it came from (by
rating key) together with the Plex library section that item belongs to, the
item's Plex "added at" timestamp, the item's release year where Plex reports one,
the item's TMDB identifier where Plex reports one, and — for seasons — the Plex
season number, so later operations can address the item for artwork, locking, and
label edits, so the gallery can order posters by when their media was added to
Plex, and so a poster search can be built from recorded facts rather than
re-parsed from a display title. On re-import the system SHALL overwrite the same
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

#### Scenario: A skipped item does not have a recorded year overwritten
- **WHEN** an import skips an item whose stored mapping already records a release
  year
- **THEN** the system leaves the recorded year as it is rather than rewriting it
  on every import

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
