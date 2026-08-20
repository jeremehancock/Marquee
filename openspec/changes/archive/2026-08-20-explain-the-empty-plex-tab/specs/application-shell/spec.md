## ADDED Requirements

### Requirement: A tooltip is never the only place a reason is given
A tooltip SHALL NOT be the sole carrier of the reason a control is refusing, is
unavailable, or is behaving other than a user would expect. Such a reason SHALL
be available through something that does not depend on the input device — the
content the control opens to, the text beside it, or the message the action
returns.

This follows from the existing tooltip requirement rather than qualifying it.
That requirement already states that suppressing tooltips must not remove any
information a touch user needs, and discharges the obligation by requiring the
host to remain usable and to retain its accessible name. That discharge is
correct for the two kinds of content it was written for: a tooltip that repeats
text the host has truncated, and a hint about what a control does. It is not
sufficient for a third kind. **A reason is not a hint.** A hint elaborates on
what a control's own label already conveys, so a user who never sees it is
merely less informed; a reason supplies the only account of why an action did
not happen, so a user who never sees it is told nothing at all. Retaining an
accessible name does not discharge the obligation, because the name states what
the control is, not why it will not act.

Where a reason exists and the interface has no device-independent place to put
it, that is a defect in the interface rather than a case for a second,
touch-specific hint mechanism. Adding one would reintroduce the device
detection the tooltip requirement deliberately settled, and would leave the
reason stated twice, in two places that can disagree.

#### Scenario: A refusing control does not explain itself only on hover
- **WHEN** a control refuses an action, is unavailable, or behaves unexpectedly,
  and a reason for that is offered anywhere in the interface
- **THEN** that reason is reachable without a hovering pointer

#### Scenario: A touch user is given the reason
- **WHEN** a user on a touch-only device encounters a control whose reason a
  pointer user would be shown
- **THEN** they are given the same reason through a channel their device
  supports

#### Scenario: An accessible name is not a reason
- **WHEN** a control's only device-independent text is its accessible name
- **THEN** that alone does not satisfy this requirement, because a name states
  what the control is rather than why it will not act

#### Scenario: Hints are unaffected
- **WHEN** a tooltip carries a hint or repeats text its host has visually
  truncated, rather than the reason a control is refusing
- **THEN** it remains a tooltip and requires no device-independent duplicate
