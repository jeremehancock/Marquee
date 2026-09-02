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

The system SHALL additionally record, for each stored poster, the **set** its item
belongs to, identified by a Plex rating key: a show and a collection record their
own, a season records its show's, and a movie records that of the collection it
belongs to. This is what lets a poster be shown together with the rest of its set
without matching titles, which cannot express a collection whose films share no
words in their names.

A movie's collection is not reported by the library listing, so the system SHALL
read each collection's members and record membership on the movies' own rows. This
SHALL happen whenever movies are imported, whether or not collection posters were
among the requested types, because the membership is a fact about the movie rather
than about any collection's poster. A library that has no collections SHALL cost
no additional requests.

A movie in no collection SHALL record no set, and that SHALL NOT be treated as
missing information or as an error — most films belong to no collection.

A set SHALL be **removed** when the item no longer belongs to it. Unlike the other
recorded facts, a collection is a relationship a user takes away on purpose, and
Plex reports its removal only by omission; a mapping that held the old membership
would keep showing a film in a collection it has left.

The system SHALL therefore distinguish a membership read that listed every
collection from one that could not. Only a complete read SHALL remove a set: where
a collection could not be listed, the read SHALL be treated as concluding nothing
and recorded sets SHALL stand. A film in no collection and a film whose
collections could not be read are otherwise indistinguishable, and acting on that
emptiness without the distinction would take every film out of every set the first
time one request failed.

A movie MAY belong to more than one collection, and SHALL record **every**
collection holding it rather than one of them. Collections overlap in an ordinary
library — a film can sit in both a franchise collection and a wider one — and
recording a single set means the collection read first takes the film, leaving
every other collection sharing it holding nothing but its own poster.

An item that **names** a set SHALL record that set even when its own type was not
among the requested types, provided it has no set recorded already. A movie import
learns every collection in the library and a season import walks every show, so
the naming item's set is known in both cases; without recording it, a user who
imports only movies would leave the collection's own poster outside the set its
films point at, and one who imports only seasons would leave the show's poster
outside its seasons' — the set correct except for the poster it is named after,
which is the one a user is most likely to open it from. This SHALL only fill an
absent set and SHALL NOT replace a recorded one.

A mapping records what the Plex item was at the moment it was imported, and a
Plex item does not hold still. Correcting a bad match — Plex's "Fix Match" — keeps
the item's rating key but replaces the work behind it: a new title, a new release
year, a new TMDB identifier. The system SHALL therefore treat the recorded facts
as a cache to be reconciled rather than as a record written once. On re-import,
when a mapping already exists for an item's rating key, the system SHALL compare
the item's current title, release year, TMDB identifier, set, and — for a season —
show title against the recorded ones and SHALL update the mapping wherever they
differ. A recorded fact SHALL NOT
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

#### Scenario: A show and a collection record themselves as their own set
- **WHEN** a TV show or a collection is imported
- **THEN** the system records that item's own rating key as its set

#### Scenario: A season records its show as its set
- **WHEN** a TV season is imported
- **THEN** the system records its show's rating key as its set

#### Scenario: A movie records the collection it belongs to
- **WHEN** a movie that belongs to a Plex collection is imported
- **THEN** the system records that collection's rating key as the movie's set

#### Scenario: A movies-only import records the collection's own set
- **WHEN** a user imports only movies from a library whose collections already
  have imported posters recording no set
- **THEN** each collection's own poster records that collection as its set
- **AND** it is shown among the results when one of its films opens the set

#### Scenario: A seasons-only import records the show's own set
- **WHEN** a user imports only seasons from a library whose shows already have
  imported posters recording no set
- **THEN** each show's own poster records itself as its set

#### Scenario: An already-recorded set is never replaced by this
- **WHEN** an item that names a set already records one
- **THEN** it is left exactly as it is

#### Scenario: Membership is recorded even when collection posters were not requested
- **WHEN** a user imports only movies from a library whose movies belong to
  collections
- **THEN** each movie's collection is recorded as its set
- **AND** no collection poster is imported

#### Scenario: A movie taken off a collection loses that set
- **WHEN** a movie is removed from a Plex collection and the library is imported
  again, its poster unchanged
- **THEN** the mapping no longer records that collection
- **AND** the poster is not re-downloaded to notice

#### Scenario: A failed membership read removes nothing
- **WHEN** an import cannot list one of a library's collections
- **THEN** every mapping keeps the sets it already recorded
- **AND** no item is reported as failed

#### Scenario: A movie in two collections belongs to both
- **WHEN** a movie belongs to two Plex collections
- **THEN** the system records both as the movie's sets
- **AND** opening either collection gathers that movie

#### Scenario: A movie in no collection records no set
- **WHEN** a movie that belongs to no collection is imported
- **THEN** the system records no set for it and the import succeeds

#### Scenario: A library with no collections costs no extra requests
- **WHEN** a movie library that has no collections is imported
- **THEN** no collection membership requests are made

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

#### Scenario: A skipped item still gains a missing set
- **WHEN** an import skips an item because its poster is unchanged, and the stored
  mapping records no set while one is now known
- **THEN** the system records the set without downloading the poster, and still
  counts the item as skipped

#### Scenario: Existing mappings gain their sets without a rebuild
- **WHEN** a library imported by an earlier version of Marquee is imported again
  and none of its posters have changed
- **THEN** every mapping gains its set without the user deleting, rebuilding, or
  forcing a re-download of anything
- **AND** an item whose set already matches causes no write

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
