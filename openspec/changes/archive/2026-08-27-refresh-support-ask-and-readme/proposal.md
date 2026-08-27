## Why

The README still opens with an "Early Alpha — not ready for general use" warning
that no longer describes the project, and it is the first thing anyone evaluating
Marquee reads. Meanwhile the support ask — in the README and inside the app — is
easy to miss and hard to act on: the README section is prose with no visible way
to answer it, and the in-app button is labelled "Hard drive fund", an in-joke
that does not say what pressing it does.

## What Changes

- Remove the alpha warning blockquote from the top of `README.md`.
- Reword the one remaining alpha claim in the README (the `PLEX_TOKEN` upgrade
  note) so it states the fact — `PLEX_TOKEN` is no longer read, one way to
  connect replaced two — without resting the justification on alpha status.
- Add the Buy Me A Coffee badge image to the README's existing
  `## Support Development` section, so the section a reader lands on carries a
  visible, clickable way to answer it. The section keeps its name and its place
  at the end of the README.
- Rename the in-app support overlay's call-to-action from "Hard drive fund" to
  "Buy me a coffee", so the button names its destination. Its link, its new-tab
  behaviour, its dismiss-on-activate behaviour and the surrounding copy are all
  unchanged.

Not in scope: the wording of the support paragraph itself (its "another hard
drive for my Plex server" line is the ask, not the button, and still reads), and
the project site at `getmarquee.now`, which lives in a separate repository.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `application-shell`: the support overlay's required button label changes from
  "Hard drive fund" to "Buy me a coffee". Three places in the existing spec name
  the old label — the narrative requirement, the "holds the ask and one way to
  answer it" scenario, and the "Contributing dismisses the ask" scenario — and
  all three are restated. Nothing else about the overlay changes.

The README edits touch no requirement. `application-shell` already governs which
*configuration variables* the README may name (`ConfigurationSurfaceTest` enforces
it), but it says nothing about the alpha warning or the presence of a support
badge, and neither needs it to: the alpha notice was a temporary status statement,
and the badge is a link in a section the spec does not describe.

## Impact

- `README.md` — the alpha blockquote is deleted, the `PLEX_TOKEN` note is
  reworded, and the Buy Me A Coffee badge is added to `## Support Development`.
- `templates/partials/_support.html.twig` — the anchor's text changes; the
  neighbouring comment that explains the button stays accurate as written.
- `openspec/specs/application-shell/spec.md` (via the delta) — three occurrences
  of the old label.
- `tests/Functional/ApplicationShellTest.php` — two assertions naming the old
  label (the call-to-action presence check and the dismiss-on-activate message).
  No new test is needed; the existing ones already pin the shape.
- No PHP source, configuration, database, or Docker change. No user data or
  behaviour change beyond the button's text.
