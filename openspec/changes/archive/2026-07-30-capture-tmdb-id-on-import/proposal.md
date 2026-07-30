## Why

Find Posters currently identifies a work by its title, and a title is not always
enough. A locally annotated title (`Spider-Noir B&W`), a distinct film sharing a
prefix (`Breaking Bad El Camino`), or a punctuation difference
(`Ready or Not 2 Hear I Come`) all fail to match, and the user is left with
"No match for this title." and nothing to do about it. Loosening the matching was
considered and rejected — it is the strictness that stops `The Matrix` returning
`The Matrix Reloaded` artwork.

Plex already knows the answer. For items matched by a modern agent it holds the
work's TMDB id, which identifies a work exactly and cannot resolve to the wrong
one. Marquee does not currently record it, so this change starts recording it —
ahead of the poster service being able to accept it — so that the id is already
in place on deployed installs when it becomes useful, rather than every install
falling back to title matching for a release while it re-imports.

## What Changes

- Import reads each Plex item's TMDB id, where the server reports one, and stores
  it with the item's poster mapping.
- Movies and shows take their own id. A season takes its **show's** id, since a
  season is addressed as a show plus a season number, and Marquee already records
  the season number.
- Collections never carry an id. A Plex collection is a local grouping with no
  upstream record, so it keeps no id and continues to be identified by title.
  This is a deliberate boundary, not a gap to close later.
- An item Plex never matched, or one in a library on a legacy agent, records no
  id. Missing is a normal outcome, not an error.
- A library imported by an earlier version of Marquee gains the id on its next
  import, without the user deleting or rebuilding anything.
- Nothing consumes the id yet. Find Posters is unchanged in this change and
  continues to search by title.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `plex-import`: the **Plex item mapping** requirement gains the item's TMDB id
  alongside the section key, added-at timestamp, release year and season number
  it already records, together with the rules for which item types carry one and
  what happens when the server reports none.

## Impact

- **Specs**: `openspec/specs/plex-import/spec.md`.
- **Storage**: a nullable `tmdb_id` column on the `plex_items` table, added by
  the existing idempotent column-migration path. No rebuild, no data loss, and
  older Marquee databases open unchanged.
- **Code**: the Plex client (reading the id from the library listing), the Plex
  item value object, the import service, and the item record and repository that
  persist it.
- **Plex server load**: unchanged. The id arrives in the library listing request
  that import already makes — one request per library, not one per item.
- **Compatibility**: a Plex server too old to report ids in a listing returns
  none, and every item simply records no id. No version detection, no failure.
- **Deliberately out of scope**: sending the id to the poster service. The
  posteria.app API does not accept it yet; threading it into the poster search is
  a separate change once it does.
