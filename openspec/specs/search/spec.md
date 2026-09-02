# Search Specification

## Purpose

Filtering the gallery down to the posters a user is looking for. Matching is
deliberately specific rather than broadly fuzzy — a library of thousands of
posters makes loose matching worse than useless — and it applies live, within
the current category, without a page reload.
## Requirements
### Requirement: Specific poster search
The system SHALL filter the posters in a category by a search query, matching
only posters whose title contains every query term after normalization
(case-insensitive, diacritics and separators ignored). The match SHALL be
specific rather than broadly fuzzy.

The title a query is matched against SHALL be the title recorded for the poster's
Plex item — the same title the card caption shows — and SHALL fall back to the
filename-derived title only for a poster with no Plex record, or whose recorded
title is empty. This is the one title principle the caption already follows,
applied to search.

The filename SHALL NOT be matched where a recorded title exists. A filename is a
sanitised copy of the title: every character outside `A-Za-z0-9._-` is flattened
to a separator, and the source library is appended. Both losses make search
answer for text that appears nowhere on screen.

- A title carrying a character the filename cannot hold SHALL be findable by that
  title. Searching "Amélie" SHALL match a poster captioned "Amélie", which the
  filename records as `Am_lie` and which therefore could not be matched before.
- The appended source library SHALL NOT be matchable. Searching the name of a
  Plex library SHALL NOT, by that fact alone, match every poster imported from
  it.

The recorded title and the filename SHALL NOT both be matched. A poster matching
for reasons the user cannot see on the card is the confusion this removes, so
adding the filename back as a second haystack would defeat it.

Which title is matched SHALL NOT affect ordering. The sort key is derived
independently and is unchanged by this requirement.

#### Scenario: All query terms must match
- **WHEN** a user searches "star wars" in a category
- **THEN** the system shows posters whose title contains both "star" and "wars"
- **AND** hides posters that contain only one of the terms

#### Scenario: Case and accents ignored
- **WHEN** a user searches "amelie"
- **THEN** a poster titled "Amélie" is included in the results

#### Scenario: An accented title is found by its own name
- **WHEN** a user searches "Amélie" and the poster's stored filename is
  `Am_lie_2001_Movies.jpg`, its recorded Plex title being "Amélie"
- **THEN** that poster is included in the results

#### Scenario: The source library is not searchable
- **WHEN** a user searches the name of a Plex library, and posters imported from
  that library carry it in their filenames but not in their recorded titles
- **THEN** those posters are not matched for that reason alone

#### Scenario: A poster with no Plex record still matches on its filename
- **WHEN** a user searches for a poster that has no Plex mapping, or whose
  recorded title is empty
- **THEN** the query is matched against the poster's filename-derived title, as
  before

#### Scenario: Ordering is unaffected by the change of haystack
- **WHEN** a search is run under any sort order
- **THEN** the matching posters are ordered exactly as the same posters would be
  ordered unfiltered

#### Scenario: No matches
- **WHEN** a search matches no posters in the category
- **THEN** the system shows an empty gallery and reports zero results

### Requirement: Live search
The gallery SHALL filter posters as the user types in the search box, without
requiring the user to submit, and SHALL restore the full list when the box is
emptied.

When a search updates the results, the gallery SHALL return the user to the top
of the results, so a search started from part-way down the gallery presents its
matches from the first one rather than from wherever the previous list happened
to be scrolled to. The return SHALL be animated rather than instantaneous, and
SHALL honour a reduced-motion preference by jumping instead. This matches how
pagination and switching category already behave.

#### Scenario: Filtering as you type
- **WHEN** a user types text into the gallery search box
- **THEN** the grid updates to matching posters shortly after the user stops
  typing, without a full page reload

#### Scenario: Clearing the search
- **WHEN** the search box becomes empty
- **THEN** the gallery shows the full, unfiltered list again

#### Scenario: Searching after scrolling shows matches from the first one
- **WHEN** a user scrolls part-way down the gallery and then searches
- **THEN** the gallery returns to the top of the results as they update
- **AND** the first match is what the user sees

#### Scenario: Return to the top is animated
- **WHEN** the gallery returns to the top after a search
- **THEN** it scrolls smoothly rather than jumping

#### Scenario: Reduced motion suppresses the scroll animation
- **WHEN** the user has asked for reduced motion
- **THEN** the gallery moves to the top of the results without animating

### Requirement: Search preserves category and pagination
The system SHALL apply search within the current view and paginate the filtered
results, keeping the query when navigating between pages AND when switching
between views. Switching views (tabs) SHALL carry the active query into the
newly selected view without a full page reload, and SHALL update the address so
the filtered view is shareable and restored by back/forward navigation. When the
search box is emptied, view switching SHALL return to showing full, unfiltered
views.

#### Scenario: Paging through search results keeps the query
- **WHEN** a user pages through search results
- **THEN** each page reflects the same query and view

#### Scenario: Switching views keeps the query
- **WHEN** a user has an active search in the All view and switches to the
  Movies view
- **THEN** the Movies view is shown filtered by the same query
- **AND** the grid updates without a full page reload
- **AND** the address reflects both the Movies view and the active query

#### Scenario: Clearing the search restores full views
- **WHEN** a user clears the search box and then switches views
- **THEN** the newly selected view shows its full, unfiltered list

### Requirement: Filtered view is clearly indicated
When a search query is active, the gallery SHALL make it visually clear that the
grid is a filtered subset of the current view rather than the full view. The
indication SHALL include the active query and a way to clear it back to the full
list, and SHALL persist as the user switches views while the query remains
active.

The gallery SHALL present exactly one clear control for an active query, and
that control SHALL be co-located with the filtered-state indication, so the
indication and its clear control appear, update, and disappear as one unit
whenever the results change. The browser's own in-field clear affordance for the
search input is not a gallery control and is not counted here.

#### Scenario: Result summary shown while filtering
- **WHEN** a search query is active in the current view
- **THEN** the gallery shows a summary identifying the active query and the
  number of matches in the current view
- **AND** provides an obvious control to clear the search back to the full list

#### Scenario: A single clear control is offered
- **WHEN** a search query is active in the current view, however the view was
  reached — typing in the search box, loading a filtered address directly, or
  switching views with the query still active
- **THEN** the gallery offers exactly one control for clearing the search
- **AND** that control is the one presented with the filtered-state summary

#### Scenario: The clear control disappears with the filtered state
- **WHEN** a user clears an active search
- **THEN** the gallery shows the full, unfiltered list
- **AND** no clear control remains anywhere in the gallery

#### Scenario: Filtered indication persists across a view switch
- **WHEN** a user with an active query switches views
- **THEN** the search box remains populated with the query
- **AND** the filtered-state indication is shown for the newly selected view

#### Scenario: Filtered empty state is distinguishable
- **WHEN** an active query matches no posters in the selected view
- **THEN** the empty grid indicates the view is filtered by the query, not that
  the view has no posters
- **AND** provides a way to clear the search

### Requirement: Search filters without reordering
The system SHALL treat a search as a filter over the current listing: it SHALL
decide which posters match the query and SHALL NOT influence the order in which
they are presented. The gallery's active sort order SHALL order the matching
posters exactly as it would order the same posters unfiltered.

Where a term appears within a title SHALL carry no weight — only whether it
appears at all.

#### Scenario: The sort order decides the order of results
- **WHEN** a user searches and the gallery is ordered by date added, newest first
- **THEN** the matching posters are listed newest first, including any poster
  whose title contains the query somewhere other than at its start

#### Scenario: Reversing the sort reverses the results
- **WHEN** a user searches with the gallery ordered A–Z and then switches to Z–A
- **THEN** the same matching posters are listed in the opposite order

#### Scenario: Filtering does not disturb the surviving order
- **WHEN** a user searches within a view
- **THEN** the matching posters appear in the same relative order they hold in
  the unfiltered listing under that sort order

#### Scenario: Match position does not promote a result
- **WHEN** one matching title begins with the query and another contains it later
- **THEN** neither is favoured for that reason, and the active sort order alone
  decides which is listed first

### Requirement: A narrow search may be offered a broader one
When a search finds fewer posters than a shorter form of the same query would,
the system SHALL offer that shorter query alongside the results, together with
the number of posters it would find.

The offer SHALL be a suggestion the user follows, and the system SHALL NOT widen
a search on its own. The shorter query is produced by cutting a title — at a
subtitle separator, or by dropping a trailing instalment number — and no such rule
is reliable enough to apply unasked. Showing the count with the offer is what
makes it safe to suggest: a cut that would gather far too much says so before
anyone follows it.

The offer SHALL be made only where it would help. A query with nothing to cut, or
whose shorter forms find no more than the query already did, SHALL be offered
nothing.

This exists because a poster's set is what Plex says it is, and a library that
keeps no collections has no set for its films. The action then falls back to
searching the poster's own title, which reaches the rest of a series only from the
shortest title in it — "The Matrix" finds its sequels while "The Matrix Reloaded"
finds itself. A set the system was told about SHALL NOT be offered a broader
search, because it is exact and there is nothing to widen.

#### Scenario: A subtitled film offers its series
- **WHEN** a user searches "Jackass: Best and Last" and the library holds five
  films whose titles begin "Jackass"
- **THEN** the gallery offers "Jackass" and reports that it would find five
- **AND** the results shown remain those of the original query

#### Scenario: A numbered sequel offers its series
- **WHEN** a user searches "Lethal Weapon 3" and the library holds three films
  whose titles begin "Lethal Weapon"
- **THEN** the gallery offers "Lethal Weapon" and reports that it would find three

#### Scenario: Nothing is offered when nothing broader exists
- **WHEN** a user searches a title with nothing to cut, or whose shorter forms
  match no more posters
- **THEN** no broader search is offered

#### Scenario: The broader query is never applied on its own
- **WHEN** a broader search is offered
- **THEN** the gallery still shows the results of the query the user made
- **AND** the broader one is applied only once the user follows it

#### Scenario: An exact set is offered nothing
- **WHEN** the gallery is showing the set a poster belongs to
- **THEN** no broader search is offered

### Requirement: A search can be started from a poster
The system SHALL let a user start a search from a poster rather than by typing,
by offering that poster an action that searches for the title it shares with its
related posters.

The result SHALL be an ordinary filtered view and SHALL be indistinguishable from
the same search typed by hand: the search box SHALL hold the query, the
filtered-state summary SHALL name it, exactly one clear control SHALL be offered,
and the address SHALL be shareable and restored by back/forward navigation.

The system SHALL NOT present this result as a set, a group, or a relation. The
query that produced it SHALL be visible and editable, so a query that gathers more
than the user meant — two different works sharing a title — can be seen and
corrected rather than silently deciding the result.

The search SHALL be applied in the aggregate All view, because a work's related
posters need not share its category: a season's sibling seasons and its show sit
in different categories, as do a film and its collection.

#### Scenario: Searching from a poster fills the search box
- **WHEN** a user starts a search from a poster
- **THEN** the search box holds the query that was searched for
- **AND** the filtered-state summary names that query and its match count

#### Scenario: The result is the aggregate view
- **WHEN** a user starts a search from a poster in any category
- **THEN** the results are shown in the All view
- **AND** posters from every category may appear among them

#### Scenario: The query can be corrected
- **WHEN** a search started from a poster gathers more posters than the user
  wanted
- **THEN** the query is shown in the search box and can be edited or cleared like
  any other search

#### Scenario: The address is shareable
- **WHEN** a user starts a search from a poster
- **THEN** the address identifies the All view and the query
- **AND** navigating back returns to the view the user came from

