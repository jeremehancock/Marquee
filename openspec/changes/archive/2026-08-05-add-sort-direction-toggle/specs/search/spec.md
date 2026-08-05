## REMOVED Requirements

### Requirement: Results ranked by match position
**Reason**: Ranking by match position competed with the gallery's sort order, and
once each sort field gained a direction the competition read as a defect. A
poster whose title merely contained the query was held below every title
beginning with it, whatever order the user had asked for — so choosing "date
added, newest first" during a search could leave the newest poster at the bottom
of the page. The ranking appeared to work only when every match happened to score
equally, which made the sort control look intermittently broken rather than
deliberately overridden.

Relevance is also not something the user asked for, whereas the sort order is:
there is no state in which no sort is selected, so an implicit ranking could only
ever contradict an explicit choice.

**Migration**: None. Search behaves as a filter and the gallery's active sort
orders the results — see "Search filters without reordering" below. Users who
relied on titles beginning with the query appearing first can reach the same
grouping by sorting A–Z, which orders those titles together anyway.

## ADDED Requirements

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
