## MODIFIED Requirements

### Requirement: Results ranked by match position
The system SHALL order matching posters by how early the query first matches in
the normalized title, so titles that begin with the query appear before titles
that merely contain it, breaking ties by the gallery's active sort order.

The tie-break SHALL apply the same ordering the gallery would use for an
unfiltered listing under the active sort order — including its direction — so
the sort control remains meaningful while a search is active. For the title
field this compares titles on the same digit-aware terms the gallery uses: a run
of digits within a title is ordered by its numeric value rather than character by
character. Without this, searching for a show would list its equally relevant
seasons as Season 1, Season 10, Season 11, Season 2 — the ordering defect the
gallery's own listing does not have.

Match position SHALL always lead, so changing the sort order rearranges results
within each group of equally relevant matches but never promotes a weaker match
above a stronger one.

#### Scenario: Earlier match ranks first
- **WHEN** a user searches "matrix" and both "Matrix Reloaded" and "The Matrix"
  match
- **THEN** the poster whose normalized title matches earliest is listed first

#### Scenario: Equally relevant results order numbers by value
- **WHEN** a user searches for a show whose season posters all match equally
  early and the gallery is ordered A–Z
- **THEN** the seasons are listed Season 1, Season 2, Season 3 and so on, with
  Season 10 after Season 9

#### Scenario: Ranking still leads
- **WHEN** results differ both in where the query matches and in the numbers
  their titles contain
- **THEN** match position determines the order, and the tie-break comparison
  applies only between results that match equally early

#### Scenario: Reversing the sort reverses equally relevant results
- **WHEN** a user searches with the gallery ordered A–Z, then switches to Z–A
- **THEN** results that match equally early are listed in the opposite order,
  while results that match earlier still precede results that match later

#### Scenario: Date order breaks ties while searching
- **WHEN** a user searches with the gallery ordered by date added, newest first
- **THEN** results that match equally early are listed with the most recently
  added poster first
