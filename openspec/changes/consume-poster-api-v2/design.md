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

Three additive response fields matter:

- **`tvmaze` as a `providers` key and a poster `source`.**
- **`page`** — an absolute URL to the supplying service's own page for the work.
- **`attribution_required`** — `true` on a poster whose source licence obliges
  the link to be rendered.

TVmaze poster objects carry **no `language` and no `score`**, and a TVmaze
*season* poster additionally carries **no `width` or `height`**. Nothing may
assume an optional field is present.

### The contract revision of 2026-08-20

`page` and `attribution_required` did not arrive together. The endpoint first
sent `page` on the licensed subset only, and this change was built against that:
the address's *presence* was the obligation. Both halves of that are now false,
and the correction is what the later decisions below record.

Re-probed live, same day:

| Probe | Result |
| --- | --- |
| Show, per source | `page` on **all** — tmdb 103/103, thetvdb 45/45, fanart.tv 15/15, tvmaze 26/26 |
| `attribution_required` | **tvmaze only**, 26/26; **absent** on all others — never present-and-false |
| Season | tmdb `/tv/1396/season/2`, thetvdb `/series/breaking-bad/seasons/official/2`, tvmaze `/seasons/754/…` |
| Season, fanart.tv | `/series/81189/` — **no season page, falls back to the series** |
| Movie | no tvmaze section; `attribution_required` absent from every poster |

So the two fields now carry genuinely different meanings:

- **`page` is provenance.** Where this poster came from. Showing it is good
  product and our decision to make.
- **`attribution_required` is an obligation.** The source's licence says the
  link must be rendered. TVmaze is CC BY-SA. Not our decision.

Everything else is unchanged: same path, header, parameters, failure codes,
`providers` vocabulary, and TVmaze behaviour on movies and collections.

Two posters in one response may legitimately carry **different** `page` URLs for
the same work — the fanart.tv season fallback above is exactly that. It is
correct, and must not be read as a defect or de-duplicated away.

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
- `ChangePosterController::findPosters()` mapped a candidate to `{url, thumb}`
  only; the first implementation added `page`, and this revision adds the
  marking beside it. `source` is not published and must not be.
- `templates/partials/_attribution.html.twig` is the single definition of the
  credited provider set, rendered into both footers.

The first implementation of this change is already committed (`3c1df2f`) and
green in CI, but not archived. It is **still licence-compliant** under the
revised contract — binding the badge to `page` over-attributes rather than
under-attributes, which is the safe direction to be wrong in. So this revision is
a correction of expressed intent under no time pressure, not an incident.

## Goals / Non-Goals

**Goals:**

- Find Posters searches v2, gaining TVmaze artwork — most valuably for seasons,
  where TVmaze frequently holds the only image.
- TVmaze appears as a named, ordered section rather than under `Other`.
- A candidate **marked as requiring attribution** is credited with a visible,
  activatable link wherever it is displayed, satisfying CC BY-SA.
- A candidate carrying a source page **may** be offered a provenance link, worded
  so it never reads as a licence claim.
- The crediting mechanism is **generic** — keyed on the marking, not the service
  — so a future source under the same licence needs no Marquee release.
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

### Decision 5 — `page` is nullable; `attributionRequired` is a plain bool

Add `public readonly ?string $page = null` as the last constructor parameter, and
parse it in `candidates()` with the existing `str()` helper — which already
returns `null` for a non-string or an empty string. No new validation.

**`page` stays optional even though every source populates it today.** The
contract says a source with no resolvable identifier omits it rather than
guessing, so a candidate without one is valid and the nullable type is not
defensive padding.

Add `public readonly bool $attributionRequired = false` alongside it — **not**
nullable. The field is never sent present-and-false: it is `true` or it is
absent, so absence means false and there is no third state to model. A nullable
bool would invite `?? true` somewhere and turn a missing field into an
obligation.

Parse it **strictly**, on identity with boolean `true`:

```php
attributionRequired: ($poster['attribution_required'] ?? null) === true,
```

Not truthiness. A JSON `"false"` and a `0` are both truthy and falsy in the wrong
directions in PHP, and this flag decides whether a licence condition is met — the
one place in this client where a loose comparison could produce a compliance
failure rather than a cosmetic one.

The field is added **last** so the existing positional construction sites and
tests keep compiling; every call in the codebase uses named arguments anyway.

`PosterCandidate`'s docblock already states that only `url` is guaranteed. TVmaze
strengthens that: its posters carry no `language` or `score`, and its season
poster carries no `width` or `height` either. The existing `int()`/`float()`/
`str()` helpers already yield `null` for absent keys, so **no parsing change is
required for the missing fields** — this is verification work, not implementation
work, and the tasks treat it that way.

### Decision 6 — The obligation is keyed on the marking, never on the address and never on the source

`findPosters()` adds **both** `page` and `attributionRequired` to each poster in
the section payload. The obligation tests the marking:

```
poster.attributionRequired ? render the required link : render nothing
```

Never `poster.page` — that is now every poster, so keying on it would assert a
licence condition about artwork that carries none, and would leave the real
obligation indistinguishable from decoration.

Never `poster.source === 'tvmaze'` either. `source` is still not in the payload
and must not be added: the browser deliberately never learns a provider name (the
section label and order arrive pre-resolved from the server, which is what lets a
new provider ship as a server-side change). Keeping it out is also the cheapest
structural guard against someone re-keying the obligation onto a service name,
since doing so would take a payload change first.

Both negatives matter and they fail differently. Keying on the address
over-attributes — safe, but it makes the obligation invisible in the code, so the
next change that thins the links has nothing to stop it dropping the one that
counts. Keying on the service under-attributes the moment a second source is
licensed the same way, and does so silently, because the posters still render.

### Decision 7 — The badge is the obligation; the preview link is the provenance

Two surfaces, two different conditions, and the split is the whole point.

**Grid cell badge — `attributionRequired`.** A small link affordance inside
`figure.find-item`, over a corner of the thumbnail. The cell's `<img>` carries
`@click="openPreview(...)"`, so the badge is a sibling `<a>` with `@click.stop`
and `target="_blank" rel="noopener"`, a real accessible name rather than a bare
glyph, and keyboard reach by construction. `:href` is still `poster.page` — the
marking says a link is owed, the address says where to.

This is the obligation surface, and binding it to the marking restores the
sparsity it was designed and styled for. Under the superseded contract it fired
on a minority; under the current one, bound to `page`, it would appear on **all
~189 candidates** of a show search — a 28px corner control on every cell, which
is both visual noise and a false licence claim on three sources out of four.

**Preview link — `page`.** A link in `.viewer__bar` reading **"View on
\<source\>"**. Shown for any candidate with an address, marked or not, because
this is where a user is looking at a single poster and asking where it came from.

`openPreview()` gains `page` **and** a source-label argument, both defaulted to
`''`, set on the preview state and cleared by `closePreview()` like every other
field. The label is passed from the section the candidate came from — it must not
be derived from a slug in the browser, which would teach the page a provider name
and undo Decision 6's structural guard. The URL, upload and Plex call sites pass
neither and take the defaults.

**A marked candidate is credited in the preview too, as a consequence and not as
the mechanism.** Every attribution-required poster observed carries a `page`, so
the provenance link covers it. That is a fact about the data, not a guarantee in
the code — which is exactly why the tests pin the obligation independently at the
parse level and on the badge binding. If the provenance link were ever removed as
a product decision, the badge must still be there, and nothing about that
decision may reach the badge.

*Alternative considered:* preview only, for both. Rejected — most artwork is seen
by scrolling the grid, and a user who never opens a poster would see marked
artwork with no credit, which is precisely the case the licence governs.

*Alternative considered:* keep the badge on every poster and restyle it lighter.
Rejected: the obligation and the decoration become visually and structurally
identical, which is the state Decision 6 exists to prevent.

*Alternative considered:* one link in the section heading. Rejected on a fact
from the live endpoint: within a single season result the sources disagree about
where they point — tmdb and thetvdb and tvmaze each link to the season, fanart.tv
falls back to its series page. One heading link cannot stand for the posters
beneath it. The field is per-poster by design.

**Both links are omitted, never disabled.** A candidate with nothing to link to
renders no control at all. This sidesteps the `aria-disabled` rule in CLAUDE.md
rather than engaging it: there is no state in which the control exists but is
unavailable, so there is nothing to announce. The rule still applies if a later
change introduces such a state.

### Decision 8 — The shipped copy was already neutral, so there is no copy fix

Worth recording because the obvious hazard of this contract revision did not
land. When `page` became universal, any attribution-flavoured wording on the link
would have started asserting a licence condition on TMDB, fanart.tv and TheTVDB
posters, where it is simply false.

The shipped markup reads `'View on ' + section.label`, `'Open this poster's page
on ' + section.label`, and "View this poster's source page". No "CC BY-SA", no
"Attribution required", no "Required by licence" anywhere. Nothing needs
correcting — the preview wording changes to "View on \<source\>" only for
consistency with the badge, not to fix a claim.

The constraint is now written into the spec so it holds for wording added later:
an unmarked candidate's link reads as provenance, never as a licence notice.

### Decision 9 — TVmaze joins the footer credit, preserving the order invariant

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

### Decision 10 — The narrow-screen credit row is allowed to wrap

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

### Decision 11 — `no_data` needs no new suppression logic, only a test

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

- **The generic marking is harder to review than a TVmaze-specific one** —
  nothing on screen says "this exists for TVmaze". Mitigated by stating the
  reasoning in the spec requirement and in the template comment, and by a test
  that marks a **non-TVmaze** candidate and asserts the badge renders. That test
  is the guard against someone "simplifying" it to a source check.

- **The badge and the preview link look related but are not**, and the next
  person to touch them will reasonably assume one condition governs both.
  Mitigated by pinning the badge's binding by name in
  `PosterCreditLinkTest` — asserting it reads `attributionRequired` and
  specifically does **not** read `page` — so collapsing the two fails loudly
  rather than quietly relaxing a licence condition.

- **A marked candidate arriving with no `page` would be an obligation with
  nowhere to point.** Not observed, and arguably incoherent on the service's
  part, but the client must not render a dead or empty link. Mitigated by
  asserting the pairing at the parse level, so the failure surfaces in a test
  rather than as a broken anchor.

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

- **The footer credit row grows to two lines on a small phone** (Decision 10),
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
6. **The badge is sparse.** On a show, only the TVmaze section's posters carry
   the corner badge; TMDB, TheTVDB and fanart.tv candidates carry none. A badge
   on every cell means the obligation is still bound to the address.
7. **The preview credits every poster.** Opening a TMDB candidate offers "View on
   TMDB"; opening a TVmaze one offers its link and the badge was there too.
8. **A season's links disagree, correctly.** In one season result, the fanart.tv
   candidate's link goes to the series page while the others go to the season.

Rollback: revert the commit. `PATH` returns to v1, which is still serving. There
is no persisted state to unwind.

## Open Questions

None blocking. The three questions raised in the handover are settled above:

- v1 fallback → Decision 1, cut over outright.
- Stored base path / migration → Decision 2, nothing is stored.
- Where the link belongs → Decision 7, badge for the obligation, preview link for
  provenance.

One item is deferred rather than open: `MEMORY.md` records an existing finding
that no dialog in the app manages focus. The preview gains a link in its action
bar here, which is one more thing in an unmanaged focus order. This change does
not make that worse and does not fix it; it stays a `visual-design` proposal.
