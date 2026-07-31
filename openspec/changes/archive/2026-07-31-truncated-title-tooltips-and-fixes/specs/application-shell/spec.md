## MODIFIED Requirements

### Requirement: Consistent custom tooltips
Tooltips across the application SHALL be rendered by a single themed custom
tooltip component rather than the browser's native `title=` tooltip, so hint text
matches the app's look and can display content the native tooltip cannot present
well (such as a poster's full title). Any element that offers a tooltip SHALL
declare its text through a `data-tooltip` attribute, and the shared tooltip SHALL
appear on pointer hover and on keyboard focus for focusable targets, including
targets added to the page after load (e.g. via AJAX or client-side rendering).
Removing a native `title` from an icon-only control SHALL NOT remove that
control's accessible name — the name SHALL be preserved by an `aria-label`.

Tooltips are a pointer-device affordance. The shared tooltip SHALL be shown only
on a device whose primary input can hover with a fine pointer (a mouse or
trackpad). On a touch device no tooltip SHALL be shown by any trigger — pointer
events, the focus a tap places on a control, or programmatic display — so a tap
never leaves a hover hint stranded over the interface. The device capability
SHALL be evaluated at the moment a tooltip would be shown rather than only once
at page load, so a device whose input situation changes (a touchscreen laptop, or
a tablet that gains or loses a mouse) is judged by its current capability; a
tooltip already on screen SHALL be dismissed if the device stops qualifying.

A tooltip whose only purpose is to reveal text the host element has visually
truncated SHALL be shown only while that host is actually truncated. Such a host
SHALL declare that its tooltip is conditional, and the system SHALL determine
truncation from the element's rendered size at the moment a tooltip would be
shown — not once at page load — so a host that changes width (a window resize, a
different breakpoint, a late-loading font) is judged by its current state. A host
whose text fits SHALL show no tooltip. Hosts that carry a genuine hint rather
than a repetition of their own visible text SHALL be unaffected and SHALL always
show their tooltip.

Suppressing tooltips SHALL NOT remove any information a touch user needs: every
tooltip host SHALL remain usable and SHALL retain its accessible name, which
assistive technology exposes on every device regardless of tooltip suppression.

A non-interactive element that carries a tooltip (such as a poster caption) SHALL
present a cursor that signals a tooltip — a `help` cursor — rather than the
text/I-beam cursor used for editable text, so hovering it reads as "more
information is available" rather than "edit this." That cursor SHALL follow the
same condition as the tooltip itself: a host whose tooltip is suppressed because
its text is not truncated SHALL NOT present the `help` cursor, so the cursor
never promises a tooltip that will not appear. Interactive tooltip hosts (links
and buttons) SHALL keep their normal pointer affordance.

Tooltip text SHALL be phrased for the pointer users who are the only audience
that can see it, and SHALL NOT instruct the reader to tap.

#### Scenario: Custom tooltip replaces the native tooltip
- **WHEN** a user on a hover-capable pointer device hovers an element that offers
  a tooltip
- **THEN** the themed custom tooltip is shown and no separate native browser
  tooltip appears

#### Scenario: Tooltip works on keyboard focus
- **WHEN** a user on a hover-capable pointer device moves keyboard focus to a
  focusable element that offers a tooltip
- **THEN** the custom tooltip is shown for that element

#### Scenario: Tooltip works on dynamically added content
- **WHEN** an element with a tooltip is added to the page after initial load
  (such as paginated results or client-rendered previews)
- **THEN** hovering it on a hover-capable pointer device shows the custom tooltip
  just as for elements present at load

#### Scenario: Icon-only control keeps its accessible name
- **WHEN** an icon-only control that previously relied on a native `title` is
  converted to the custom tooltip
- **THEN** the control still exposes an accessible name to assistive technology

#### Scenario: Truncated text shows its tooltip
- **WHEN** a user on a hover-capable pointer device hovers a host whose tooltip
  is conditional on truncation and whose text is too long to fit, so it is shown
  with an ellipsis
- **THEN** the custom tooltip is shown with the full text

#### Scenario: Text that fits shows no tooltip
- **WHEN** a user on a hover-capable pointer device hovers a host whose tooltip
  is conditional on truncation and whose text fits entirely within it
- **THEN** no tooltip is shown, because the tooltip would only repeat text that
  is already fully visible

#### Scenario: Truncation is re-evaluated, not cached
- **WHEN** a host that fits at one window size is narrowed until its text
  truncates (or a truncated host is widened until its text fits)
- **THEN** the next hover is judged by the host's current rendered size, showing
  the tooltip only if the text is truncated at that moment

#### Scenario: Hint tooltips are not conditional
- **WHEN** a user hovers a tooltip host whose text is a hint rather than a
  repetition of the host's own visible text (such as a pagination step or the
  Sort trigger)
- **THEN** the tooltip is shown regardless of any truncation

#### Scenario: Non-interactive tooltip host signals a tooltip
- **WHEN** a user hovers a non-interactive element that carries a tooltip (such
  as a truncated poster caption)
- **THEN** the cursor indicates a tooltip (a `help` cursor) rather than the
  text/I-beam cursor

#### Scenario: Host with no tooltip to show signals nothing
- **WHEN** a user hovers a non-interactive host whose conditional tooltip is
  suppressed because its text is not truncated
- **THEN** the cursor does not indicate a tooltip

#### Scenario: Touch device never shows a tooltip
- **WHEN** a user on a touch-only device touches, long-presses, or scrolls over
  an element that offers a tooltip
- **THEN** no tooltip is shown

#### Scenario: Tapping a control that offers a tooltip
- **WHEN** a user on a touch-only device taps a focusable control that offers a
  tooltip (such as the Sort trigger) and the tap moves focus to that control
- **THEN** no tooltip is shown, and the control performs its normal action

#### Scenario: Device capability is re-evaluated, not cached
- **WHEN** the device's pointer capability changes after page load
- **THEN** the next tooltip trigger is judged by the current capability, and a
  tooltip that is on screen when the device stops qualifying is dismissed

#### Scenario: Tooltip text addresses a pointer user
- **WHEN** a tooltip presents an instruction for interacting with its host
- **THEN** the wording refers to clicking rather than tapping
