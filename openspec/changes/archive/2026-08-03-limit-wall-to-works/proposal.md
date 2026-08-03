## Why

The Poster Wall is meant to show the titles in the library — the things someone
would name if asked what they own. Its random rotation currently draws from all
four poster categories, so season posters and collection art appear alongside
them. Seasons in particular dominate: a library holds several seasons per show,
so on a show-heavy library most of the TV art the wall displays is season art
rather than the show it belongs to.

## What Changes

- The wall's random rotation draws only from Movies and TV Shows.
- TV Seasons and Collections no longer appear on the wall. They remain fully
  present everywhere else — the gallery, search, import, and export are
  untouched.
- The rule is stated positively (the wall shows works) rather than as a list of
  excluded categories, so a category added later is off the wall until someone
  argues it is a work.
- The now-playing takeover is unchanged. It already resolves an episode to its
  show's poster and never reaches season art, and collections cannot stream.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `poster-wall`: the random rotation's source is narrowed from every poster
  category to works only — Movies and TV Shows. Three existing statements say
  the opposite today (the Purpose, the "Full-screen rotating wall" requirement,
  and the "Random poster batches" requirement) and move together with a new
  requirement defining the pool.

## Impact

- `src/Poster/Wall/PosterWallService.php` — the only code change: the category
  loop becomes an allow-list.
- `tests/Unit/Poster/PosterWallServiceTest.php` — seeds no `tv-seasons` or
  `collections` fixtures today, so it would pass against either behavior;
  it gains both and asserts they never appear.
- No change to the controller, the batch endpoint, the wall page, or its
  JavaScript. No configuration, database, or on-disk change.
- Visible consequence: on a library with seasons and collections imported, the
  wall's pool shrinks — potentially by more than half. That is the intent.
- `README.md` describes the wall without enumerating categories, so it does not
  go stale. No documentation change is required.
