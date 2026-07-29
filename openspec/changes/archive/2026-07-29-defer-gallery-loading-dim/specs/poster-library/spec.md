## ADDED Requirements

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

#### Scenario: A superseded view change leaves nothing dimmed

- **WHEN** a user starts one view change and then starts another before the
  first has finished
- **THEN** the pending indication for the abandoned view change does not dim the
  gallery afterwards
- **AND** the gallery ends in an undimmed state once the surviving view change
  has settled

#### Scenario: A failed view change clears the indication

- **WHEN** a view change fails
- **THEN** any loading indication it applied is cleared rather than left in place
