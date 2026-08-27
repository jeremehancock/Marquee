## Why

The support ask is the one surface in Marquee that tells a user two different
names for itself. The navigation entry that opens it is **Support Development**;
the overlay it opens says **Support development** in its heading and in its
accessible name. A user who taps the entry lands on something that looks like it
was named by someone else, and a screen-reader user hears the second name
announced the instant focus enters the dialog — the two are one gesture apart, so
the disagreement is as visible as it could be.

Nothing else in the application disagrees with itself this way, and an audit of
every user-facing string confirms it. That is not luck: every other label a user
meets at both widths is emitted by one Twig macro or cloned from one node, so the
mobile and desktop copies cannot drift. The support ask is the exception because
the overlay is a separate partial from the navigation macro that names it, and no
rule and no test connect the two. Fixing the string alone would leave the next
overlay free to make the same mistake.

## What Changes

- The support overlay's heading and its dialog accessible name become
  **Support Development**, matching the navigation entry that opens it. The
  navigation entry, its short form, and the copy inside the overlay are unchanged.
- The application's naming rule is written down for the first time: a **named
  surface** — a destination or overlay the interface offers by name — is Title
  Case wherever it is named, and everything else a user reads is sentence case.
  The rule describes what the application already does everywhere except the
  support ask; it is recorded so a new label has something to conform to.
- A test pins the rule where it can actually be broken: the name a navigation
  entry uses and the name the surface it opens uses must be the same string.
  The existing tests that assert the old lowercase heading are updated to the new
  one.

No behaviour changes. No control moves, gains, or loses a binding. This is one
string, plus the rule and the test that keep it from drifting back.

## Capabilities

### New Capabilities

None. The rule belongs beside the existing "documented configuration surface is
chosen by audience" requirement — the same kind of rule, about which words reach
a user, in the same capability.

### Modified Capabilities

- `application-shell`: the **In-app support ask** requirement currently names the
  overlay's heading as "Support development"; it is restated as "Support
  Development" and tied to the navigation entry rather than quoted independently,
  so the two can no longer be edited apart. A new requirement states the naming
  rule for surfaces and requires a test that a named surface is spelled one way
  in every placement.

## Impact

- `templates/partials/_support.html.twig` — the `<h2>` and the panel's
  `aria-label`.
- `tests/Functional/ApplicationShellTest.php` — assertions on the support
  overlay's heading and accessible name.
- `tests/Unit/Asset/DialogFocusTest.php` — the dialog-name fixture listing
  "Support development".
- A new test asserting each navigation entry's label matches the name of the
  surface it opens.

No PHP source, no JavaScript, no CSS, no routes, no stored data. The rendered
change is one word's first letter.
