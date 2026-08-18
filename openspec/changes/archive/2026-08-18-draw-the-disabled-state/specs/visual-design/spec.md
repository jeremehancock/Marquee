## ADDED Requirements

### Requirement: An unavailable control looks unavailable

Every interactive element the application switches off SHALL be visibly
distinguishable from the same element when it works. This completes the
interaction-state family: hover, focus, and press are already required of every
interactive element (see "Interactive elements respond to pointer and focus"),
and the unavailable state is the fourth.

The treatment SHALL be drawn from the design token contract, introducing no new
literal values, and SHALL be stated once for every caller rather than at each
control that happens to need it. An emphasised control SHALL surrender its
emphasis while unavailable — a control that keeps its accent fill is still
reading as the thing to press.

The state SHALL NOT be conveyed by transparency alone, since a lowered opacity is
equally readable as "behind something" or "fading in" and says nothing about
whether the control will respond.

An unavailable control SHALL NOT offer hover or press feedback. This is the
substance of the requirement rather than an aside: feedback is the application's
promise that an element will respond, so an element that brightens under the
pointer or moves under a press while doing nothing is worse than one with no
feedback at all. The pointer cursor SHALL likewise indicate that the control will
not respond.

An unavailable control SHALL remain legible. Its label states what the control
would do and is the only thing on screen that does, so the treatment SHALL keep
the label readable rather than reducing it toward the background.

#### Scenario: A switched-off button is distinguishable from a working one

- **WHEN** a button is switched off
- **THEN** it is visibly distinguishable from the same button when it works, and
  an emphasised button no longer carries its emphasis

#### Scenario: A switched-off control does not react to the pointer

- **WHEN** the pointer moves onto a switched-off control on a device that
  supports hovering
- **THEN** its appearance does not change, and the cursor indicates that the
  control will not respond

#### Scenario: A switched-off control does not react to being pressed

- **WHEN** a switched-off control is pressed, by pointer or by touch
- **THEN** it gives no press feedback

#### Scenario: The state survives being wrong about transparency

- **WHEN** a control is switched off
- **THEN** its state is carried by more than a reduction in opacity

#### Scenario: A switched-off control can still be read

- **WHEN** a control is switched off
- **THEN** its label remains legible against the surface it sits on

### Requirement: An unavailable control stays reachable and reports its state

An interactive element the application switches off SHALL remain reachable by
keyboard and SHALL report its unavailable state to assistive technology, rather
than being taken out of the reading and tab order.

A control removed from the tab order cannot be discovered, so a user navigating
by keyboard or screen reader is not told it is unavailable — they are not told it
exists. That is a worse account of the screen than the sighted user gets, and it
is the same failure as the untreated appearance, in another modality.

Switching a control off SHALL NOT move the user's focus. A control that becomes
unavailable in response to being operated is the common case — a button that
starts the work it then guards against being started twice — and a user operating
it by keyboard is focused on it at that moment. Focus SHALL stay where the user
put it.

Because a control that remains reachable also remains operable, the application
SHALL refuse the action at the point that performs it. The visible state SHALL
NOT be the only thing preventing an unavailable control from acting, and this
SHALL hold for every route to that action, including a form submitted without
its own control being operated.

#### Scenario: A switched-off control can still be reached

- **WHEN** a user navigates by keyboard through a screen containing a
  switched-off control
- **THEN** the control is reachable, and its unavailable state is reported rather
  than the control being absent

#### Scenario: Operating a control that switches itself off keeps focus

- **WHEN** a keyboard user operates a control that becomes unavailable as a
  result
- **THEN** focus remains on that control rather than returning to the start of
  the document

#### Scenario: A switched-off control refuses its action

- **WHEN** a switched-off control is operated anyway, by any means
- **THEN** the action does not run, refused where it is performed rather than
  only where it is offered

#### Scenario: A form refuses submission its control would have refused

- **WHEN** a form whose submitting control is switched off is submitted by some
  other route
- **THEN** the submission is refused on the same condition that switched the
  control off

### Requirement: Switching a control off and removing it are different decisions

The application SHALL choose between leaving an unavailable control in place and
omitting it from the screen by one rule rather than case by case.

A control that the user can bring to life by acting on the same screen SHALL be
left in place and shown as unavailable. Leaving it standing is what teaches the
sequence — it names the destination of a multi-step flow, and it keeps a control
in the position a returning user already learned. Removing it takes the
explanation away with it.

A control with nothing to act on in the current state SHALL be omitted instead.
There is no sequence to teach and no reason to give, so a permanently inert
control would be furniture.

A control that is not on screen SHALL NOT be able to affect the outcome of an
action taken on that screen. Where a hidden control's value would still be
submitted, the condition that hides it SHALL be no looser than the condition that
permits the submission, so that anything capable of changing what happens is
visible while it can.

#### Scenario: A control reachable from this screen stays put

- **WHEN** a control is unavailable but the user can make it available by acting
  on the same screen
- **THEN** it remains in place, shown as unavailable, rather than being removed

#### Scenario: A control with nothing to act on is omitted

- **WHEN** a control has nothing to act on in the current state and nothing on
  the screen would change that
- **THEN** it is omitted rather than shown as unavailable

#### Scenario: A hidden control cannot change the outcome

- **WHEN** a control is hidden but its value would still be submitted with the
  form it belongs to
- **THEN** it is hidden only under conditions in which that form cannot be
  submitted
