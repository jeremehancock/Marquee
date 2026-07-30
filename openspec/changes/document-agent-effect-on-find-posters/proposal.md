## Why

Find Posters is far more accurate for an item whose Plex record carries a TMDB
identifier, and whether Plex reports one depends on which metadata agent the
user's library was built with — something the user chose years ago, may never
have revisited, and cannot see from inside Marquee. A user on an older library
gets title-only matching everywhere, sees thin or wrong results, and has no way
to know the cause is on the Plex side.

The specs already say the system behaves correctly here: an item Plex never
reported an identifier for is searched by title, and an entire server that
reports none imports cleanly. Nothing is broken. What is missing is that the
user is never told the one thing they could act on.

## What Changes

- Add a FAQ entry to `README.md`, alongside the existing "Where do Find Posters
  results come from?", explaining that Find Posters works best when Plex knows
  what a title is, that a library using one of Plex's older metadata agents
  often doesn't provide that, and that switching an agent is a deliberate
  decision because it re-matches the library and can discard manual matches and
  artwork choices.
- The entry is framed as troubleshooting to reach for **only** when Find Posters
  results look thin or wrong. It is not setup advice and must not read as a
  requirement for running Marquee — the overwhelming majority of users are on a
  modern agent already and never need it.

Deliberately **not** in scope:

- Naming Plex's menus, screens, or setting labels. Plex moves them, and a README
  that names them goes stale silently. The entry points at the library's
  metadata agent setting in general terms and leaves the navigation to Plex.
- Technical detail — no XML, no identifier formats, no mention of how Marquee
  reads the identifier from Plex's response. This entry is for a user deciding
  whether to touch their Plex settings, not for a developer.
- Any code, behaviour, or spec change. Marquee's handling of a missing
  identifier is already specified and already correct.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `poster-sources`: gains a documentation requirement, alongside the existing one
  that names posteria.app to users — that the dependence of search accuracy on
  what Plex knows about an item is explained as optional troubleshooting, with
  the constraints on that explanation (no Plex navigation, no implementation
  detail, the cost of switching stated with the suggestion) written down so a
  later edit cannot quietly turn it into setup advice.

No behavioural requirement changes. `poster-sources` already requires searching
by title when no identifier is recorded, and `plex-import` already requires
recording an identifier only where Plex reports one — including the case where a
server reports none for any item. Both stay exactly as they are.

## Impact

- `README.md` — one new FAQ entry. No other section changes.
- `openspec/specs/poster-sources/spec.md` — one added documentation requirement.
- No code, tests, or configuration.
- No user-visible change inside the app, and no upgrade or migration concern.
