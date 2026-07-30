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

The system SHALL NOT record a correction produced by a search that carried nothing
to tell works sharing a title apart. Replacing a stale identifier is safe only
because the replacement is well-founded: a known-bad identifier cannot get worse.
When the search could not distinguish between candidate works, the identifier it
matched is a guess rather than a finding, and the two outcomes are not
symmetrical — a stale identifier fails to resolve on every search, so it stays
visible and repairable, while a wrong-but-valid identifier resolves cleanly
forever and no later mismatch can reveal it. The system SHALL therefore keep the
stale identifier, which costs one wrong search result the item was already
getting, rather than record a guess, which costs the ability to ever detect the
error.

Whether a search could disambiguate SHALL be determined from what the system
itself sent, not from the source reporting that it fell back to the title. A
correction only ever arises when the identifier sent was not recognised — which is
that fallback — so treating the fallback as disqualifying would suppress every
correction rather than only the unfounded ones. A search that sends no release
year has nothing to separate works that share a title, and is the case this rule
excludes.

#### Scenario: Stale identifier replaced
- **WHEN** a search sends a recorded TMDB identifier and a release year, and the
  source matches a different identifier
- **THEN** the system records the matched identifier against that item and logs
  that it did so

#### Scenario: A correction from a search that could not disambiguate is refused
- **WHEN** a search sends a recorded TMDB identifier but no release year, and the
  source matches a different identifier
- **THEN** the system leaves the recorded identifier as it is, because the matched
  identifier could have come from any work sharing the title, and records that it
  declined

#### Scenario: A refused correction leaves the item detectable
- **WHEN** a user searches again for an item whose correction was refused
- **THEN** the search sends the same recorded identifier as before and the
  mismatch is detected again, so the stale identifier remains repairable

#### Scenario: A refused correction does not change what the user sees
- **WHEN** a search's correction is refused
- **THEN** the candidates shown are the ones the source returned, and no message
  about the refusal is shown to the user

#### Scenario: Detection survives a source that echoes what it resolved
- **WHEN** the source's response reports, as the identifier for the search, the
  identifier it resolved rather than the one it was sent — so that every
  identifier in the response agrees with every other
- **THEN** the system still detects the stale identifier and corrects it, because
  it compares against the identifier it sent rather than against any identifier
  the response reports

#### Scenario: Falling back to the title is not on its own disqualifying
- **WHEN** a search that sent both a recorded TMDB identifier and a release year
  is resolved by the source through its title fallback, because the identifier was
  not one the source recognised
- **THEN** the system still records the correction, because the fallback is the
  condition every correction arises from rather than a sign that the match was
  unfounded

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
