## Context

Find Posters calls the posteria.app Marquee API at `/marquee/api/v1/posters`.
That endpoint aggregates TMDB, fanart.tv and TheTVDB. A `v2` endpoint now exists
at the same host, adding TVmaze as a fourth source. `v1` is **frozen, not
deprecated** — it keeps serving its current contract indefinitely — so nothing
here is racing a shutdown.

The request contract is unchanged. Verified live against the deployed v2
endpoint on 2026-08-20:

| Probe | Result |
| --- | --- |
| `?q=Breaking+Bad&type=show&year=2008` | 200, `providers: {tmdb: ok, fanart.tv: ok, thetvdb: ok, tvmaze: ok}`, 189 posters (tmdb 103, thetvdb 45, tvmaze 26, fanart.tv 15) |
| Same query on v1 | 163 posters, three providers, no tvmaze — v1 confirmed still serving |
| `type=season` (Breaking Bad S2) | tvmaze supplies exactly **1** poster, `page` → `…/seasons/754/breaking-bad-season-2` — the *season's* page |
| `type=movie` (Inception) | `tvmaze: no_data`, **no `code` field**, `success: true` |
| `type=collection` | `tvmaze: no_data`, alongside `fanart.tv: no_data` and `thetvdb: no_data` |
| No `X-Client-Info` | 401 `unauthorized` — identical to v1 |
| Unresolvable title | 404 `no_match` — identical to v1 |

Two additive response fields matter:

- **`tvmaze` as a `providers` key and a poster `source`.**
- **`page`** — an absolute URL to the supplying service's own page for the work,
  present only on posters from a service whose licence requires a link back.
  Today only TVmaze populates it.

TVmaze poster objects carry **no `language` and no `score`**, and a TVmaze
*season* poster additionally carries **no `width` or `height`**. Nothing may
assume an optional field is present.

The licence is the reason `page` exists. TVmaze data is CC BY-SA; attribution is
discharged by linking back from within the application. The field is designed so
a compliant client needs no hardcoded knowledge of any provider.

Current state in this repo, established by reading it:

- `PosteriaApiPosterSource::PATH` is a **private class constant**. The version
  lives there and nowhere else.
- `PosterProvider` is a **closed backed enum** whose `inSectionOrder()` returns
  `self::cases()` — declaration order *is* section order.
- `PosterSearchResult::sections()` routes an unrecognised `source` into a
  trailing `PosterSection::OTHER` bucket.
- `PosteriaApiPosterSource::interpret()` branches only on `success` and
  `code === 'partial'`. The `providers` map is read by `providers()` for logging
  and deliberately never enumerated.
- `ChangePosterController::findPosters()` maps a candidate to `{url, thumb}`
  only. `page` currently has no route to the browser.
- `templates/partials/_attribution.html.twig` is the single definition of the
  credited provider set, rendered into both footers.

## Goals / Non-Goals

**Goals:**

- Find Posters searches v2, gaining TVmaze artwork — most valuably for seasons,
  where TVmaze frequently holds the only image.
- TVmaze appears as a named, ordered section rather than under `Other`.
- A candidate carrying `page` is credited with a visible, activatable link
  wherever it is displayed, satisfying CC BY-SA.
- The crediting mechanism is **generic** — keyed on the field, not the service —
  so a future source owing a link back needs no Marquee release.
- Tolerance of unknown `providers` keys and unknown poster fields is preserved,
  not replaced.

**Non-Goals:**

- Authentication changes. `X-Client-Info` is untouched.
- Any new setting, credential, or API key. v2 introduces none.
- A source-selection UI. Marquee has never had one; `sources=` stays unsent so
  all four services run.
- Filtering or re-sorting by source. The endpoint already ranks.
- A v1 fallback path (see Decision 1).
- The Plex Posters tab, whose candidates carry no provider attribution by spec.

## Decisions

### Decision 1 — Cut over to v2 outright; no v1 fallback

Change `PATH` to `/marquee/api/v2/posters`. One path, no runtime negotiation.

*Alternative considered:* fall back to v1 on a transport error from v2. Cheap to
write, since the contracts are compatible. Rejected on three grounds:

1. It doubles the paths under test for a benefit that only materialises when the
   service is already broken.
2. Its failure mode is **silent loss of TVmaze artwork** — the one thing this
   change exists to gain — with nothing on screen saying so.
3. A transport error already maps to `PosterSearchOutcome::Unavailable`, which
   tells the user to retry. That is a truthful outcome; a quiet degradation is
   not.

v1 being frozen rather than deprecated removes any deadline pressure that might
otherwise justify hedging.

### Decision 2 — No migration is needed, because the version was never stored

The handover asked whether existing installs carry a stored v1 path. **They do
not.** `PATH` is a private class constant; `POSTER_SOURCE_URL` (environment-only,
default `https://posteria.app`, seeded at `src/bootstrap.php:140`) carries only
the **host**. A deployed install upgrading to this version picks up v2 from the
new constant with no settings-store write, no migration step, and no
`SettingKey`.

This also means `POSTER_SOURCE_URL` keeps working unchanged for local
development against a local API, which is its documented purpose.

### Decision 3 — There is no clock-sync call to repoint

The handover's scope named `/marquee/api/v1/time`. **Marquee never calls it.**
`clientInfo()` stamps its own `microtime()` and relies on the endpoint's
documented 24-hour skew tolerance; the class docblock says so explicitly. That
half of the scope is empty and gets no task. Recorded here so a later reader does
not go looking for the call that was supposedly missed.

### Decision 4 — `Tvmaze` is a new enum case, declared last

Add `case Tvmaze = 'tvmaze';` to `PosterProvider`, after `Fanart`, with
`label(): 'TVmaze'`.

Declaration order is load-bearing: `inSectionOrder()` returns `self::cases()`, so
declaring it last places its section last, and `PosterProviderTest` already
asserts `cases() === inSectionOrder()` so it cannot be forgotten.

**This is the change's main correctness risk if omitted.** Pointing `PATH` at v2
without touching this enum would route 26 TVmaze posters per show into a section
headed literally `Other`. That is the `OTHER` bucket working exactly as designed
— it is the safety net for a service added upstream — but it is not a shipping
state.

The label needs no shortening. Headings are upper-cased in CSS; `TVMAZE` reads as
one word, unlike `THETVDB`, which is why TheTVDB is shortened to `TVDB` and
TVmaze is not.

### Decision 5 — `page` is a nullable field on `PosterCandidate`, parsed with the existing helper

Add `public readonly ?string $page = null` as the last constructor parameter, and
parse it in `candidates()` with the existing `str()` helper — which already
returns `null` for a non-string or an empty string. No new validation.

The field is added **last** so the existing positional construction sites and
tests keep compiling; every call in the codebase uses named arguments anyway.

`PosterCandidate`'s docblock already states that only `url` is guaranteed. TVmaze
strengthens that: its posters carry no `language` or `score`, and its season
poster carries no `width` or `height` either. The existing `int()`/`float()`/
`str()` helpers already yield `null` for absent keys, so **no parsing change is
required for the missing fields** — this is verification work, not implementation
work, and the tasks treat it that way.

### Decision 6 — The link is keyed on the field, never on the source

`findPosters()` adds `page` to each poster in the section payload, and both
render sites test *the presence of the value*:

```
poster.page ? render the link : render nothing
```

Never `poster.source === 'tvmaze'` — indeed `source` is not in the payload at
all and must not be added, since the browser deliberately never learns a provider
name (the section label and order arrive pre-resolved from the server, which is
what lets a new provider ship as a server-side change).

This is what makes a future service owing a link back work with no client
release, and it is the specified behaviour, not an optimisation.

### Decision 7 — The link renders in both the grid cell and the preview

**Grid cell.** A small link affordance inside `figure.find-item`, over the
thumbnail. The constraint is that the cell's `<img>` carries
`@click="openPreview(poster.url, 'find')"`, so the link must not swallow that
press. It is a sibling `<a>` positioned over a corner of the frame, with
`target="_blank" rel="noopener"` and `@click.stop` so activating the link does
not also open the preview. It carries a real accessible name (not a bare glyph),
and is keyboard-reachable — an `<a href>` is in the tab order by construction.

**Preview.** A link in `.viewer__bar`, alongside the Use/Close actions. This
requires carrying `page` into the preview state: `openPreview()` gains a `page`
argument, defaulted to `''`, set on the state object and cleared by
`closePreview()` like every other preview field. The Find Posters call site
passes `poster.page`; the URL, upload and Plex call sites pass nothing and get
the default.

*Alternative considered:* preview only. Rejected — most TVmaze artwork is seen by
scrolling the grid, and a user who never opens a poster would see the image with
no credit, which is precisely the case the licence governs.

*Alternative considered:* one link in the section heading. Rejected on a fact
from the live endpoint: a season's `page` points at the **season's** page
(`/seasons/754/…`), not the show's (`/shows/169/…`), so one heading link cannot
stand for the posters beneath it. The field is per-poster by design.

**The link is omitted, never disabled.** A candidate with no `page` renders no
control at all. This sidesteps the `aria-disabled` rule in CLAUDE.md rather than
engaging it: there is no state in which the control exists but is unavailable, so
there is nothing to announce. The rule still applies if a later change introduces
such a state.

### Decision 8 — TVmaze joins the footer credit, preserving the order invariant

The `application-shell` spec requires the provider list be defined in exactly one
place and credited in the footer; the `poster-sources` spec requires the section
order and the credit order agree, and says the section order "SHALL be defined in
a way that records that it follows the attribution". Adding a fourth section
forces the credit to follow.

*Alternative considered:* leave the footer at three and relax the agreement rule,
relying on the per-poster `page` link for TVmaze credit. Rejected: it dissolves a
deliberate invariant to avoid adding one image, and leaves the footer asserting a
set of sources that is no longer the set Marquee uses.

Asset: `https://static.tvmaze.com/images/tvm-header-logo.png` — the header logo
TVmaze publishes and uses on its own site — saved locally to
`public/assets/providers/tvmaze.png`. Serving it locally is a spec requirement:
the credit must render without a request to a third party. Intrinsic dimensions
are **253×80** (ratio 3.16), which the partial must state so the footer reserves
the row's space before the image decodes, as the other three do.

Link target: `https://www.tvmaze.com/`.

### Decision 9 — The narrow-screen credit row is allowed to wrap

`app.css` carries a load-bearing comment on the drawer footer: at 320px the three
logos plus gaps come to ~278px against the ~288px the tray leaves, and "sizing up
from here wraps fanart.tv onto a second line". A fourth logo breaks that budget
outright — TVmaze at a comparable 18px height is ~57px wide, plus a 14px gap,
taking the row to ~347px.

**Accept the wrap.** `.attribution__logos` already sets `flex-wrap: wrap` and
`justify-content: center`, so four logos land as a centred 2×2 with no new CSS
beyond the `--tvmaze` height class. The cost is some vertical space in the drawer
footer.

*Alternative considered:* shrink heights and the gap to force one line. Rejected
— the existing comment establishes that fanart.tv already sets the legibility
floor at that width, so a fourth mark would be squeezed below it.

**That comment becomes false and must be rewritten in the same commit**, not left
to mislead the next reader. It is exactly the kind of silent doc drift CLAUDE.md
calls out.

### Decision 10 — `no_data` needs no new suppression logic, only a test

`interpret()` already branches solely on `success` and `code === 'partial'`, and
`providers()` reads the map without enumerating it. A movie search returning
`tvmaze: no_data` arrives as `success: true` with no `code`, so it is already an
ordinary `Ok` result today.

So the specified behaviour — a television-only service is silent on a movie — is
**already true by construction**. The work is to pin it with a regression test
against a captured v2 movie response, not to write a suppression branch. Adding
one would be worse than useless: it would be the first place in the client that
enumerates provider names, undoing Decision 6's generality.

The one visible side effect is the existing warning log on a no-artwork or
partial result, which includes the `providers` map. It will now show a `tvmaze`
key. That is a log line, not a user-facing surface, and its value is diagnostic.

## Risks / Trade-offs

- **Shipping the path change without the enum case** → 26 posters per show land
  under an `Other` heading. Mitigated by ordering the tasks so the enum case and
  the path change are in one commit, and by `PosterProviderTest`'s existing
  `cases() === inSectionOrder()` assertion.

- **The generic `page` link is harder to review than a TVmaze-specific one** —
  nothing on screen says "this exists for TVmaze". Mitigated by stating the
  reasoning in the spec requirement and in the template comment, and by a test
  that feeds a `page` on a **non-TVmaze** candidate and asserts the link renders.
  That test is the guard against someone "simplifying" it to a source check.

- **The grid link competes with the cell's click-to-preview target**, especially
  on touch. Mitigated by `@click.stop`, a corner placement clear of the centre,
  and a tap target that stays within its corner. Needs checking on a real phone
  during `:dev` validation, not only in a unit test.

- **TVmaze contributes a 26-poster section to every show**, lengthening the Find
  Posters tab. Accepted: sections are collapsible by scroll position with sticky
  headings, and the whole point of the grouping feature is that a user can skip
  to the service they want.

- **A TVmaze season poster has no dimensions**, so anything that assumes
  `width`/`height` on a candidate breaks. Mitigated by the existing nullable
  helpers, plus an explicit test using the real season response shape.

- **The footer credit row grows to two lines on a small phone** (Decision 9),
  taking vertical space in the drawer. Accepted as the better of two bad options.

- **The logo is a third-party trademark committed to the repo.** Same basis as
  the three already there: it is the mark TVmaze publishes for identifying
  TVmaze, used to credit TVmaze. No new category of risk, but worth naming.

## Migration Plan

No data migration. Nothing is stored, so nothing needs converting (Decision 2).

Deployment is the ordinary flow: `/opsx:apply` → `/ship` → validate the `:dev`
image → archive. The `:dev` validation must include a live round trip, since the
whole change is about an external contract:

1. A **show** — a TVmaze section appears last, with a plausible count.
2. A **season** — the TVmaze section holds one poster, and its link opens the
   *season's* TVmaze page, not the show's.
3. A **movie** — no TVmaze section, and **no error or warning banner**.
4. A **collection** — same as the movie case.
5. The footer, on desktop and in the phone drawer, credits four providers.

Rollback: revert the commit. `PATH` returns to v1, which is still serving. There
is no persisted state to unwind.

## Open Questions

None blocking. The three questions raised in the handover are settled above:

- v1 fallback → Decision 1, cut over outright.
- Stored base path / migration → Decision 2, nothing is stored.
- Where the link belongs → Decision 7, grid cell and preview.

One item is deferred rather than open: `MEMORY.md` records an existing finding
that no dialog in the app manages focus. The preview gains a link in its action
bar here, which is one more thing in an unmanaged focus order. This change does
not make that worse and does not fix it; it stays a `visual-design` proposal.
