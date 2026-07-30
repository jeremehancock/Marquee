## MODIFIED Requirements

### Requirement: A stale recorded identifier is corrected by a search
When a search sends a recorded TMDB identifier and the poster source matches a
different work, the recorded identifier is stale — the source did not recognise it
and resolved the title instead. The system SHALL record the identifier the source
matched in place of the stale one, so the item is identified correctly on later
searches, and SHALL log the correction. The search result itself SHALL be
unaffected: the user sees the candidates the source returned.

The system SHALL detect this by comparing the identifier it **sent** against the
identifier the source matched. It SHALL NOT rely on the source reporting back the
identifier it was given, whether or not the source is documented as doing so.

The system SHALL NOT record an identifier for an item that had none. An item with
no recorded identifier is searched by its title every time, which is a path that
re-resolves on each search; recording one guess would pin the item to it
permanently, with no later mismatch to reveal the error.

#### Scenario: Stale identifier replaced
- **WHEN** a search sends a recorded TMDB identifier and the source matches a
  different identifier
- **THEN** the system records the matched identifier against that item and logs
  that it did so

#### Scenario: Detection survives a source that echoes what it resolved
- **WHEN** the source's response reports, as the identifier for the search, the
  identifier it resolved rather than the one it was sent — so that every
  identifier in the response agrees with every other
- **THEN** the system still detects the stale identifier and corrects it, because
  it compares against the identifier it sent rather than against any identifier
  the response reports

#### Scenario: Corrected item searches correctly afterwards
- **WHEN** a user searches again for an item whose identifier was corrected
- **THEN** the search sends the corrected identifier

#### Scenario: Matching identifier is left alone
- **WHEN** a search sends a recorded TMDB identifier and the source matches that
  same identifier
- **THEN** the stored record is not written to

#### Scenario: A missing identifier is not filled in
- **WHEN** a search sends no TMDB identifier because none is recorded, and the
  source reports the identifier of the work it matched by title
- **THEN** the system records no identifier for that item

#### Scenario: An identifier that was withheld is not a correction
- **WHEN** a search withholds a recorded TMDB identifier because the item is a
  collection, and the source reports the identifier of the work it matched by
  title
- **THEN** the system records no identifier for that item, because nothing was
  sent for the response to disagree with

#### Scenario: Correction does not change what the user sees
- **WHEN** a search corrects a stale identifier
- **THEN** the candidates shown are the ones the source returned, and no message
  about the correction is shown to the user

#### Scenario: Source reports no matched identifier
- **WHEN** the source's response carries no usable matched identifier
- **THEN** the system leaves the recorded identifier as it is and the search
  result is presented normally
