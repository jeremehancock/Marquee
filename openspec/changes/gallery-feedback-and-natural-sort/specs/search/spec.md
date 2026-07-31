## MODIFIED Requirements

### Requirement: Results ranked by match position
The system SHALL order matching posters by how early the query first matches in
the normalized title, so titles that begin with the query appear before titles
that merely contain it, breaking ties by title.

The tie-break SHALL compare titles on the same digit-aware terms the gallery
uses for its own ordering: a run of digits within a title is ordered by its
numeric value rather than character by character. Without this, searching for a
show would list its equally relevant seasons as Season 1, Season 10, Season 11,
Season 2 — the ordering defect the gallery's own listing does not have.

#### Scenario: Earlier match ranks first
- **WHEN** a user searches "matrix" and both "Matrix Reloaded" and "The Matrix"
  match
- **THEN** the poster whose normalized title matches earliest is listed first

#### Scenario: Equally relevant results order numbers by value
- **WHEN** a user searches for a show whose season posters all match equally
  early
- **THEN** the seasons are listed Season 1, Season 2, Season 3 and so on, with
  Season 10 after Season 9

#### Scenario: Ranking still leads
- **WHEN** results differ both in where the query matches and in the numbers
  their titles contain
- **THEN** match position determines the order, and the digit-aware comparison
  applies only between results that match equally early
