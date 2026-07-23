# Fix the orphans page copy

## Why

The orphans page tells the user:

> Orphans are posters imported from Plex whose media no longer exists there.
> **Posters you uploaded yourself are never treated as orphans.**

The second sentence describes a capability Marquee does not have. There is no
path that adds a poster to the library by hand — uploading a file or a URL is a
mode of *changing* a poster that is already there, and every poster in the
library arrives through Plex import and carries a Plex item mapping.

So the reassurance is empty at best. At worst it actively misleads: a user who
reads it may believe some of their posters are protected from "Delete all
orphans" when no poster is, and that belief is most likely to matter at exactly
the moment they are about to bulk-delete.

This surfaced while consolidating the specs — the claim traces back to an early
`poster-upload` capability that was specified but never built.

## What Changes

Replace the false sentence on the orphans page with copy that describes what
deleting orphans actually does.

- Remove the "Posters you uploaded yourself are never treated as orphans"
  sentence from `templates/orphans.html.twig`.
- Replace it with a sentence stating that deleting an orphan removes both the
  stored poster file and its Plex mapping.

No behavior changes. `OrphanService` is already correct — this aligns the page's
explanation with what the code does.

## Impact

- Affected specs: `orphan-detection` (adds a requirement covering the page's
  explanatory copy, which was previously unspecified)
- Affected code: `templates/orphans.html.twig`
- Risk: none — presentational copy only, no logic touched
