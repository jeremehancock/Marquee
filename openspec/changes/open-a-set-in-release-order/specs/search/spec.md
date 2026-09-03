## MODIFIED Requirements

### Requirement: Search filters without reordering
The system SHALL treat a search as a filter over the current listing: it SHALL
decide which posters match the query and SHALL NOT influence the order in which
they are presented. The gallery's active sort order SHALL order the matching
posters exactly as it would order the same posters unfiltered.

Where a term appears within a title SHALL carry no weight — only whether it
appears at all.

**A set is governed by the same rule, for the same reason.** A set is not a
search — it narrows the listing by recorded membership rather than by matching
text, and the two are never applied together — but it is a filter over a listing
just as a query is, and it does not reorder what it narrows either. Ordering a
set by release regardless of the active sort was tried and withdrawn; see "A set
is ordered by the active sort" in `poster-library`. Neither kind of filter
touches the order.

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

#### Scenario: A query never takes a set's order
- **WHEN** a user searches while the gallery is ordered by release
- **THEN** the matching posters are listed in release order, the query having
  changed which posters are shown and not their order

#### Scenario: A set does not reorder either
- **WHEN** the gallery is showing a set
- **THEN** the set's posters are listed in the active sort order
- **AND** opening the set changed neither the order nor the sort control

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
finds itself.

**A set MAY also be offered a broader search, on the same terms.** Membership
being exact does not make a collection complete: a Plex collection holds what
someone put in it, so a set of eight where the library holds nine is the ordinary
result of a film never added. Where a set was opened from a poster, the system
SHALL derive candidates from that poster's related title — the title the action
would have searched had there been no set — together with the shorter forms of it,
and SHALL offer the candidate finding the most posters, with its count, **only
when that count exceeds the number of posters the set holds**.

That one condition SHALL be the whole of the suppression, and it SHALL be applied
rather than any rule about the kind of set:

- a collection whose films share no words in their titles is offered nothing,
  because no title query finds more of it than it already holds;
- a show's set is offered nothing, its seasons already being gathered by the
  show's own title;
- a set holding every poster its title would find is offered nothing.

A set opened without an origin poster SHALL be offered nothing, there being no
title to derive a candidate from.

The offer for a set SHALL be worded as what it is — something the collection may
be missing — rather than in the words used for a narrow query, the two asking
different questions. Like the query's offer it SHALL carry its count and SHALL
NOT be applied on its own; the set shown SHALL be unchanged by the offer's
presence.

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

#### Scenario: An incomplete collection is offered its series
- **WHEN** a user opens the set of a Plex collection holding eight "Jackass"
  films while the library holds nine posters a search for "Jackass" would find
- **THEN** the gallery offers "Jackass" and reports that it would find nine
- **AND** the set shown still holds its eight members

#### Scenario: A complete set is offered nothing
- **WHEN** a user opens a set holding every poster its origin poster's title
  would find
- **THEN** no broader search is offered

#### Scenario: A set whose members share no words is offered nothing
- **WHEN** a user opens the set of a studio or franchise collection, from a film
  whose title finds fewer posters than the set holds
- **THEN** no broader search is offered, no title query finding more of that set
  than it already holds

#### Scenario: A show's set is offered nothing
- **WHEN** a user opens a show's set from one of its seasons, and a search for
  the show's title would find exactly the show and those seasons
- **THEN** no broader search is offered

#### Scenario: A set opened without an origin poster is offered nothing
- **WHEN** a user opens a set address naming no poster it was opened from
- **THEN** the set is shown and no broader search is offered

#### Scenario: The set's offer reads as its own question
- **WHEN** a broader search is offered for a set and again for a narrow query
- **THEN** each is worded for the question it answers, and both state the count
  the candidate would find
