## MODIFIED Requirements

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

## ADDED Requirements

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
