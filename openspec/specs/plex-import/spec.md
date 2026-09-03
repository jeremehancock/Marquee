# Plex Import Specification

## Purpose

Pulling posters out of Plex and into the library: discovering the server's
libraries, letting the user pick what to import through a type-first flow,
downloading each item's current artwork into the matching category, and
recording the mapping that links every stored poster back to its Plex item.

Import is designed to be cheap to repeat. Re-importing overwrites in place
rather than duplicating, and posters whose artwork has not changed in Plex are
skipped entirely so a scheduled run costs the Plex server almost nothing.

The mapping this capability records is what makes `plex-export`,
`poster-editing`, `orphan-detection`, and `auto-import` possible.
## Requirements
### Requirement: Plex connection configuration
The system SHALL read the Plex server URL and token from the environment and
report whether Plex integration is configured. When it is not configured, the
system SHALL show guidance rather than attempting to connect.

#### Scenario: Plex not configured
- **WHEN** no Plex server URL or token is set and a user opens the Plex page
- **THEN** the system explains that Plex must be configured and offers no import

#### Scenario: Plex configured
- **WHEN** a Plex server URL and token are set
- **THEN** the system treats Plex as configured and lists its libraries

### Requirement: List Plex libraries
The system SHALL list the libraries (sections) available on the configured Plex
server, identifying each as a movie or show library. In the library picker each
library's type SHALL be presented parenthetically after its name (e.g.
`My Movies (Movies)`, `Shows (TV)`) rather than as a bare trailing word.

#### Scenario: Libraries are listed
- **WHEN** a user opens the Plex page with a reachable server
- **THEN** the system shows each library's name and type

#### Scenario: Library type shown in parentheses
- **WHEN** the library picker renders a library
- **THEN** its type appears in parentheses after the library name

#### Scenario: Server unreachable
- **WHEN** the Plex server cannot be reached
- **THEN** the system shows a connection error and does not crash

### Requirement: Type-first import selection
The import screen SHALL ask the user to choose a content type first and only then
present the libraries that can provide that content type.

#### Scenario: Choosing a content type reveals matching libraries
- **WHEN** a user selects a content type on the import screen
- **THEN** the screen shows only the libraries compatible with that type — movie
  libraries for Movies, TV libraries for TV Shows or TV Seasons, and all
  libraries for Collections

#### Scenario: No libraries offered before a type is chosen
- **WHEN** no content type is selected yet
- **THEN** no libraries are shown for selection

#### Scenario: Changing the content type resets library selection
- **WHEN** a user changes the selected content type
- **THEN** any previously selected libraries are cleared so only compatible
  libraries can be submitted

#### Scenario: Import is blocked until the selection is complete
- **WHEN** either no content type or no library is selected
- **THEN** the Import action is unavailable

#### Scenario: No matching libraries
- **WHEN** the selected content type has no compatible libraries on the server
- **THEN** the screen tells the user that no libraries provide that content type

### Requirement: Import posters from Plex
The system SHALL import the current Plex poster for each item in the selected
libraries and media types (movies, TV shows, TV seasons, collections), storing
each into its matching category.

#### Scenario: Movies imported into the Movies category
- **WHEN** a user imports a selected movie library
- **THEN** the system stores each movie's Plex poster in the Movies category

#### Scenario: Only selected media types are imported
- **WHEN** a user selects a show library but only the "TV Shows" media type
- **THEN** the system imports show posters and does not import season posters

#### Scenario: A failed item does not abort the import
- **WHEN** one item's poster cannot be downloaded during an import
- **THEN** the system skips that item, continues, and reports it as failed while
  still importing the others

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

### Requirement: Safe, unique filenames
When storing an imported poster the system SHALL derive a filename from the Plex
item, sanitize it to a safe character set, preserve a valid image extension, and
make it unique within the category so an import never overwrites an unrelated
poster.

A poster's filename is not merely a storage detail: the gallery orders posters by
it and a search matches against it. A filename that no longer describes its item
therefore hides the poster — it sorts under the wrong letter and matches no query
for the name it now displays. The system SHALL therefore keep a stored poster's
filename in step with the item it belongs to. On re-import, when a mapping's
recorded title or release year has changed, the system SHALL rename the stored
file to the name that item would be given today, apply the same sanitisation and
uniqueness rules a first import applies, and record the new filename with the
mapping in the same operation, so a poster is never left addressed by a name it
no longer has. The file's existing image extension SHALL be preserved: renaming
follows a metadata change, not a new download, and the stored image is unchanged.

A rename SHALL NOT be attempted when the derived name is unchanged, and a rename
that cannot be completed SHALL leave the poster reachable under its existing
name rather than failing the import.

Renaming a stored poster SHALL be the last step before its mapping is updated,
and nothing that can fail SHALL come between the two. A poster renamed by an
import that then fails is worse than one never renamed at all: the mapping
addresses a name that no longer exists, the file answers to a name no mapping
knows, and no later import can reconcile them — the poster is unlinked, showing
a filename-derived title and refusing every operation that needs its Plex item.
Whatever an import manages to do, the stored file and its mapping SHALL still
describe each other when it finishes.

#### Scenario: Colliding name is made unique
- **WHEN** an import stores a poster whose derived name matches an existing file
  in the category that belongs to a different item
- **THEN** the system stores it under a unique name without overwriting the
  existing poster

#### Scenario: Unsafe characters are removed
- **WHEN** an item's title contains path separators or other unsafe characters
- **THEN** the stored filename contains none of them and keeps a valid image
  extension

#### Scenario: A re-matched item's poster is renamed
- **WHEN** an item is re-imported and Plex now reports a different title than the
  mapping recorded
- **THEN** the system renames the stored poster to the name derived from the new
  title and records that filename with the mapping, so the poster sorts and is
  found under its current name

#### Scenario: A re-matched item's poster is renamed even when its artwork is unchanged
- **WHEN** an import skips an item because its artwork is unchanged and Plex now
  reports a different title than the mapping recorded
- **THEN** the system renames the stored poster without downloading it and still
  counts the item as skipped

#### Scenario: A rename does not overwrite an unrelated poster
- **WHEN** a rename's derived name matches an existing file in the category that
  belongs to a different item
- **THEN** the system renames it to a unique name instead, and the other item's
  poster is left untouched

#### Scenario: A rename preserves the stored image
- **WHEN** a stored poster is renamed because its item's title changed
- **THEN** the image itself is unchanged, keeps its existing extension, and the
  poster is not re-downloaded from Plex

#### Scenario: An unchanged name is not renamed
- **WHEN** an import processes an item whose derived filename matches the stored
  one
- **THEN** the system leaves the file where it is

#### Scenario: A failed rename does not lose the poster
- **WHEN** a stored poster cannot be renamed
- **THEN** the mapping continues to address the poster by its existing filename
  and the import reports the item without failing the run

#### Scenario: A failed download leaves the file and its mapping agreeing
- **WHEN** an item's title has changed and fetching its poster from Plex fails
- **THEN** the mapping still addresses a file that exists, and a later import
  once Plex is reachable renames the poster and corrects its facts as usual

### Requirement: Library tracking
The system SHALL record the Plex libraries seen during import so later features
can reconcile the local library against Plex.

#### Scenario: Libraries recorded on import
- **WHEN** an import runs
- **THEN** the system stores the name and type of each library it imported from

### Requirement: Skip unchanged posters on import
An import SHALL avoid downloading a poster from Plex when the item's artwork has
not changed since the last import and the local poster file still exists,
reducing load on the Plex server.

#### Scenario: Unchanged poster is skipped
- **WHEN** an import processes an item whose Plex poster version matches the one
  stored from a previous import and whose local file is present
- **THEN** the system does not download the poster and counts it as skipped

#### Scenario: Changed poster is re-imported
- **WHEN** an import processes an item whose Plex poster version differs from the
  stored one
- **THEN** the system downloads the new poster and overwrites the local file

#### Scenario: Missing local file is re-imported
- **WHEN** an import processes an item whose Plex poster version is unchanged but
  whose local file is missing
- **THEN** the system downloads the poster again

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

### Requirement: Report skipped posters
The import summary SHALL report how many posters were skipped as unchanged.

#### Scenario: Summary includes the skipped count
- **WHEN** an import finishes having skipped one or more unchanged posters
- **THEN** the summary states how many were skipped

### Requirement: Import progress indication
The system SHALL indicate that an import is running once it is started, and
SHALL prevent it from being started again until it finishes, so the user knows
it is in progress.

#### Scenario: Running import is indicated
- **WHEN** a user starts an import
- **THEN** the interface shows that the import is in progress and disables
  starting another until it completes

### Requirement: Import content-type controls presentation
On the import screen the Step 1 content-type controls (the pill-style
selectors) SHALL present their label text horizontally centered within each pill.

#### Scenario: Pill label is centered
- **WHEN** the import screen renders the Step 1 content-type pills
- **THEN** each pill's label text is horizontally centered within the pill

### Requirement: Excluded libraries are never reported
The system SHALL omit excluded libraries from the libraries it reports for a Plex
server, so that no screen, import, or scheduled run can observe an excluded
library.

There is exactly one exception, and it exists because exclusion would otherwise
be irreversible: the settings screen that manages exclusions SHALL be able to
list every library the server reports, excluded or not. A library that nothing
can observe is one nothing can offer to un-exclude. The exception SHALL be
confined to that screen — no import, no scheduled run, and no other screen SHALL
observe an excluded library through it.

#### Scenario: Excluded library is not reported
- **WHEN** the system lists the libraries on a Plex server and one of them is
  excluded
- **THEN** that library is absent from the result and the remaining libraries
  are reported as usual

#### Scenario: The exclusions editor sees every library
- **WHEN** the settings screen lists libraries so that exclusions can be chosen
- **THEN** excluded libraries are included in that list, marked as excluded

### Requirement: Excluded libraries are hidden from the import screen
The Import from Plex screen SHALL NOT offer an excluded library in its library
picker. When no library is available to import and exclusions are configured, the
screen SHALL state that excluded libraries are hidden, rather than reporting only
that no libraries were found on the server.

That statement SHALL point the user at the settings screen, where exclusions are
now changed. Naming an environment variable would send a user to edit a file that
no longer configures anything.

#### Scenario: Excluded library is not offered
- **WHEN** a user opens the Import from Plex screen and one of the server's
  libraries is excluded
- **THEN** that library does not appear in the library picker and the remaining
  libraries are listed as usual

#### Scenario: Every library is excluded
- **WHEN** a user opens the Import from Plex screen, the server reports
  libraries, and every one of them is excluded
- **THEN** the screen states that excluded libraries are hidden and offers no
  import
- **AND** it names the settings screen as where exclusions are changed

### Requirement: Imports skip excluded libraries
An import SHALL import nothing from a library excluded by `EXCLUDED_LIBRARIES`,
regardless of how the import was started, including when that library's section
key is submitted directly to the import endpoint.

#### Scenario: Submitted section key for an excluded library
- **WHEN** an import request selects the section key of an excluded library
- **THEN** nothing is imported from that library

#### Scenario: Mixed selection
- **WHEN** an import request selects both an excluded and a non-excluded library
- **THEN** the non-excluded library is imported and the excluded one is skipped

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

