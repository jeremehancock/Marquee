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

### Requirement: Safe, unique filenames
When storing an imported poster the system SHALL derive a filename from the Plex
item, sanitize it to a safe character set, preserve a valid image extension, and
make it unique within the category so an import never overwrites an unrelated
poster.

#### Scenario: Colliding name is made unique
- **WHEN** an import stores a poster whose derived name matches an existing file
  in the category that belongs to a different item
- **THEN** the system stores it under a unique name without overwriting the
  existing poster

#### Scenario: Unsafe characters are removed
- **WHEN** an item's title contains path separators or other unsafe characters
- **THEN** the stored filename contains none of them and keeps a valid image
  extension

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

#### Scenario: Forced re-import ignores the skip check
- **WHEN** the user starts an import with the re-download option enabled
- **THEN** the system downloads every selected poster regardless of whether it
  changed

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

