## MODIFIED Requirements

### Requirement: The tab explains itself when a poster has no Plex item
A poster with no linked Plex item has no posters to list. The Plex Posters tab
SHALL still be shown for such a poster — rather than being hidden — and SHALL be
openable, stating when opened that the poster is not linked to a Plex item.

Hiding it would make the tab strip change shape from one poster to the next, so
a user who learned where the tab sits would find it missing with no reason
given. A tab that says why is steadier and answers the question the absence
would raise.

The tab SHALL NOT be presented as unavailable. An unavailable control can only
state its reason through the affordances of the device in use, and the
application's hints are a pointer-device affordance, so a reason attached to a
switched-off control does not reach a touch user at all. This tab is not
unavailable — it is empty, in the same sense as a search that matched nothing —
and SHALL be treated as such, so that its reason is carried by what it opens to
and therefore reaches every device.

The explanation SHALL be given without contacting Plex. Whether the poster is
linked is already known when the dialog opens, and a request whose only possible
answer is already held could fail for unrelated reasons and report an unrelated
cause in place of the real one.

This SHALL NOT be counted among the outcomes of a request for an item's posters.
No request is made, so it is not an outcome of one; it SHALL nonetheless be
distinguishable from every outcome that is, and from the state of having asked
and not yet answered.

#### Scenario: Unlinked poster
- **WHEN** a user opens the change-poster dialog for a poster with no linked
  Plex item
- **THEN** the Plex Posters tab is present and available, and opening it states
  that the poster is not linked to a Plex item

#### Scenario: The reason does not depend on the input device
- **WHEN** a user on a touch device opens the Plex Posters tab for a poster with
  no linked Plex item
- **THEN** they are given the same reason as a user on a pointer device

#### Scenario: The tab strip keeps its shape
- **WHEN** a user opens the dialog for a linked poster and then for an unlinked
  one
- **THEN** both show the same four tabs in the same positions

#### Scenario: Opening the tab for an unlinked poster contacts nothing
- **WHEN** a user opens the Plex Posters tab for a poster with no linked Plex
  item
- **THEN** no request is made to Plex

#### Scenario: The dialog keeps its shape on the tab
- **WHEN** a user opens the Plex Posters tab for an unlinked poster and then for
  a linked one
- **THEN** the dialog is laid out the same way for both, rather than resizing
  according to whether the tab has candidates to show

#### Scenario: The explanation is distinct from having asked
- **WHEN** the Plex Posters tab is showing that a poster is not linked to a Plex
  item
- **THEN** it does not also indicate that posters are being retrieved, that Plex
  holds none, or that Plex could not be reached

#### Scenario: An unlinked poster is left untouched
- **WHEN** a user opens the Plex Posters tab for a poster with no linked Plex
  item
- **THEN** the user's existing poster is unchanged
