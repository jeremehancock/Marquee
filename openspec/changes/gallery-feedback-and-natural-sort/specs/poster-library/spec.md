## MODIFIED Requirements

### Requirement: Article-aware ordering
The system SHALL sort posters by title, ignoring a leading article ("a", "an",
"the") when `IGNORE_ARTICLES_IN_SORT` is enabled.

Title comparison SHALL be digit-aware: a run of digits within a title SHALL be
ordered by its numeric value rather than character by character, so that
"Season 2" precedes "Season 10" and "Ocean's 8" precedes "Ocean's 11". This
SHALL apply wherever the system orders posters by title.

Digit-awareness SHALL affect ordering only. It SHALL NOT change any title shown
to the user, any filename on disk, or any stored record.

A run of digits longer than the system can compare numerically SHALL fall back
to character-by-character comparison rather than being ordered incorrectly. The
supported length SHALL comfortably exceed any number occurring in real media
titles.

Two titles that differ only by leading zeros in a digit run (such as "Season 01"
and "Season 1") compare as equal under digit-aware ordering. The system SHALL
break such a tie deterministically, so that repeated listings of the same
posters produce the same order.

#### Scenario: Leading article ignored in sort
- **WHEN** `IGNORE_ARTICLES_IN_SORT` is true and a poster titled "The Matrix"
  is sorted among others
- **THEN** it is ordered as if titled "Matrix"

#### Scenario: Numbers order by value
- **WHEN** a show's season posters from Season 1 through Season 10 or beyond are
  listed
- **THEN** they are ordered Season 1, Season 2, Season 3 and so on, with
  Season 10 after Season 9 rather than after Season 1

#### Scenario: Numbers within a title order by value
- **WHEN** posters titled "Ocean's 8" and "Ocean's 11" are listed
- **THEN** "Ocean's 8" is ordered before "Ocean's 11"

#### Scenario: Digit-awareness composes with article stripping
- **WHEN** `IGNORE_ARTICLES_IN_SORT` is true and the posters being ordered have
  both leading articles and numbers in their titles
- **THEN** the leading article is ignored and the numbers still order by value

#### Scenario: Displayed titles are unaffected
- **WHEN** posters are ordered by a digit-aware title
- **THEN** every caption, tooltip, and alt text shows the poster's title exactly
  as it did before, with no padding or other ordering artefact visible

#### Scenario: Leading zeros tie deterministically
- **WHEN** two posters' titles differ only by a leading zero in a number, such as
  "Season 01" and "Season 1"
- **THEN** they are ordered deterministically, so listing the same posters again
  produces the same order

### Requirement: Aggregate view ordering
Within the All view the system SHALL order posters by title across all types
(mixed, not grouped by category), applying the same article-aware, digit-aware
ordering used elsewhere. When two posters have the same sort title, the system
SHALL break the tie by category in the order Movies, TV Shows, TV Seasons,
Collections, so the order is stable and deterministic.

#### Scenario: Titles are mixed across types
- **WHEN** the All view lists posters of different categories
- **THEN** they are ordered together by title rather than grouped into
  per-category blocks

#### Scenario: Equal titles break ties by category
- **WHEN** two posters in the All view share the same sort title but belong to
  different categories
- **THEN** they are ordered by category in the sequence Movies, TV Shows,
  TV Seasons, Collections

#### Scenario: Numbers order by value in the aggregate view
- **WHEN** the All view lists posters whose titles contain numbers
- **THEN** those numbers order by value, on the same terms as within a single
  category

### Requirement: Deferred loading indication for in-place view changes
The gallery replaces its results in place for a category tab switch, a search, a
cleared search, a pagination move, a history navigation, and a tray-triggered
refresh. While such a view change is in flight the gallery MAY dim its results
to indicate loading, but that indication SHALL be deferred: it SHALL NOT be
applied until the view change has been in flight for at least a grace period of
200 ms. A view change that completes within the grace period SHALL render no
loading indication at all — its results are replaced directly, with the gallery
never dimmed.

Once the indication has been applied it SHALL remain visible for a minimum of
300 ms, even if the results arrive sooner, so that a view change which only just
crosses the grace period does not produce a dim-and-restore flash of its own.
The replacement of the results SHALL NOT be delayed by this hold: new posters
are shown as soon as they are available, and only the dimming persists for the
remainder of the minimum.

A view change that is superseded, fails, or completes SHALL leave no loading
indication behind and SHALL NOT cause a later, unrelated view to dim.

This deferral governs view changes only. It SHALL NOT govern operations that
change stored data — importing from Plex, scanning for or deleting orphans, or
applying a found poster — whose progress is indicated immediately, because they
have no fast path that a deferral would protect against flickering.

#### Scenario: A fast tab switch never dims

- **WHEN** a user switches category tabs and the new view's results arrive
  within the grace period
- **THEN** the gallery is never dimmed at any point during the switch
- **AND** the new posters replace the old ones directly

#### Scenario: A slow view change dims

- **WHEN** a view change is still in flight after the grace period has elapsed
- **THEN** the gallery dims to indicate that it is loading

#### Scenario: A dimmed gallery is held long enough to be read

- **WHEN** the loading indication has been applied and the results arrive
  shortly afterwards
- **THEN** the dimming remains for the remainder of its minimum visible duration
  rather than clearing immediately
- **AND** the new posters are shown as soon as they arrive, without waiting for
  the dimming to clear

#### Scenario: Every in-place navigation behaves the same way

- **WHEN** a user searches, clears a search, moves to another page, navigates
  back or forward, or triggers a refresh from the import or orphans tray
- **THEN** the loading indication is deferred and held on exactly the same terms
  as a category tab switch

#### Scenario: A mutation is not deferred

- **WHEN** a user starts an operation that changes stored data, such as applying
  a found poster
- **THEN** its progress is indicated immediately rather than after the grace
  period that applies to view changes
