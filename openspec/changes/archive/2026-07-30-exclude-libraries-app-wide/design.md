## Context

`EXCLUDED_LIBRARIES` is read by `AutoImportConfig::fromEnv()` and consumed in
exactly one place: `AutoImportService::run()` filters libraries through
`AutoImportConfig::isExcluded()` before handing section keys to `ImportService`.
Nothing else in the app knows the setting exists.

That makes the exclusion a property of the *scheduled run* rather than of the
library. `PlexImportController::show()` renders every library
`PlexClient::libraries()` returns, `ImportService::import()` imports any library
whose key was posted, and `OrphanService::collectCurrentRatingKeys()` treats
every library as a live source — all three ignore the setting.

The intended meaning is stronger than "auto-import skips it": an excluded
library does not exist as far as Marquee is concerned.

## Goals / Non-Goals

**Goals:**

- One filter, applied where libraries enter the application, so no current or
  future consumer can forget it.
- Excluded libraries are not offered in any UI and not imported by any path.
- Posters left behind by a library that is excluded after import are surfaced as
  orphans, so the user has a way to clear them.
- Matching by library name is a stated, tested requirement, not an accident of
  the implementation.

**Non-Goals:**

- No per-library UI for managing exclusions; `EXCLUDED_LIBRARIES` stays an
  environment variable, consistent with all other configuration.
- No automatic deletion of posters from a newly excluded library. They become
  orphans; deleting them stays a deliberate user action through the existing
  orphans flow.
- No "reset Marquee" / start-fresh action. Worth having, but it is a destructive
  capability of its own and belongs in its own change.

## Decisions

### Filter in `HttpPlexClient::libraries()`

`HttpPlexClient` takes `LibraryExclusions` and omits excluded libraries from
`libraries()`. `PlexClient::libraries()` documents that excluded libraries are
never returned.

*Why:* every consumer — the import screen, `ImportService`, `AutoImportService`,
`OrphanService` — asks the client which libraries exist. Filtering there makes
"excluded means invisible" true by construction instead of by four separate
call-site checks, three of which are easy to forget when new code is added.

*Alternative rejected — filter at each consumer:* three or four edits now and an
open invitation to miss the fifth. It also leaves the import endpoint permissive
unless `ImportService` is edited too.

*Alternative rejected — a decorator implementing `PlexClient`:* cleaner
separation of policy from transport, but `PlexClient` has eight methods and
seven would be pure delegation. Not worth the boilerplate for one filtered
method against a single implementation.

### Posters from an excluded library become orphans

This falls out of the decision above: `OrphanService` compares stored mappings
against the rating keys Plex currently reports, and an excluded library
contributes none. Posters imported before the exclusion therefore have no live
counterpart and are listed as orphans.

That is the intended behavior, not a side effect to be suppressed. Excluding a
library is the user saying "this is not mine to manage", and the orphans screen
is the existing, confirmed, reversible-by-re-import way to clear what it left
behind. Nothing is deleted without the user asking.

Two consequences this change must handle rather than ignore:

- The orphans page currently defines an orphan as a poster "whose media no
  longer exists" in Plex. With exclusions that is incomplete and misleading, so
  both the copy and the `orphan-detection` spec must name the second cause.
- The README needs an FAQ entry for the exclude-after-import case, because the
  posters appearing under Orphans is otherwise surprising.

### Move the exclusion list into its own config object

Extract `excludedLibraries` and `isExcluded()` from `AutoImportConfig` into an
`App\Config\LibraryExclusions` value object with `fromEnv()`, registered once in
the container. `AutoImportConfig` keeps `enabled` and the media-type toggles.

*Why:* the setting is no longer about auto-import, and `HttpPlexClient` should
not depend on an auto-import config object to learn whether a library is
excluded. Reading env once into a typed config object at bootstrap is the
project's standing convention.

**Breaking for callers, not for users:** `AutoImportConfig`'s constructor loses a
parameter, which touches its tests. The env var, its name, and its semantics are
unchanged, so no deployment changes.

### `AutoImportService` loses its exclusion filter

With the client filtering, its loop over `libraries()` already sees only
importable libraries. Its "no libraries to import" log path still fires — the
list is simply empty — so nothing is lost.

### The import screen's empty state

The controller cannot distinguish "server reported nothing" from "everything is
excluded" once the client filters, and adding a raw-library accessor just to
tell those apart would reopen the hole this design closes. Instead the
controller injects `LibraryExclusions` and, when the library list is empty and
exclusions are configured, the template says libraries listed in
`EXCLUDED_LIBRARIES` are hidden — accurate in both cases, and the case that
actually confuses people (all libraries excluded) reads correctly.

### Name matching stays exactly as it is

Comparison remains `mb_strtolower(trim(...))` on the Plex library title on both
sides. The code moves classes; the behavior does not. What is new is that the
spec states it normatively and tests cover case, whitespace, and the
"a section key in `EXCLUDED_LIBRARIES` matches nothing" case.

## Risks / Trade-offs

- **A user excludes a library and is startled to find its posters under
  Orphans** → This is the designed flow; the README FAQ entry and the corrected
  orphans-page copy exist specifically to make it expected rather than alarming.
- **Deleting those orphans is not reversible from the UI** → It is recoverable
  in practice: remove the exclusion and re-import. `OrphanService::delete()` only
  removes the local poster file and its mapping; it never touches Plex.
- **An excluded name that matches no library silently excludes nothing** → Same
  as today, and now visible: the library they meant to exclude is still listed
  on the Import from Plex screen.
- **A typo'd or removed exclusion makes posters flip between live and orphaned**
  → No data is lost by that flip alone; nothing is deleted without the user
  confirming a delete on the orphans screen.
