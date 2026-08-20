## 1. Point the client at v2 and name the new service

These two belong in one commit. The path change alone would route every TVmaze
poster into the `Other` section (design Decision 4).

- [x] 1.1 Change `PosteriaApiPosterSource::PATH` to `/marquee/api/v2/posters`.
      Update the class docblock, which currently names the three services, to
      name four and to say the version lives in this constant and nowhere else.
- [x] 1.2 Add `case Tvmaze = 'tvmaze';` to `PosterProvider`, **declared last**
      (after `Fanart`) — `inSectionOrder()` returns `self::cases()`, so
      declaration order is section order. Add `self::Tvmaze => 'TVmaze'` to
      `label()`.
- [x] 1.3 Extend the `label()` docblock: TVmaze needs no shortening because
      upper-cased it is still one legible word, unlike `THETVDB`. This records
      why one provider is abbreviated and the other is not.
- [x] 1.4 Confirm no task is needed for a clock-sync call — Marquee never calls
      the endpoint's `time` route (design Decision 3). Nothing to change; this
      task is a check that closes the question.

## 2. Carry the link-back address to the browser

- [x] 2.1 Add `public readonly ?string $page = null` as the **last** constructor
      parameter of `PosterCandidate`. Extend its docblock: TVmaze supplies no
      `language` or `score`, and a TVmaze *season* poster supplies no `width` or
      `height` either, so "only `url` is guaranteed" is now load-bearing.
- [x] 2.2 Parse it in `PosteriaApiPosterSource::candidates()` with the existing
      `str()` helper — `page: $this->str($poster['page'] ?? null)`. No new
      validation; `str()` already yields `null` for a non-string or empty value.
- [x] 2.3 Add `'page' => $c->page` to the poster payload built in
      `ChangePosterController::findPosters()`. Do **not** add `source` — the
      browser deliberately never learns a provider name, and the link must key
      on the field's presence (design Decision 6). Note that in the comment
      already sitting above that mapping.

## 3. Render the attribution link

- [x] 3.1 Add the link to the grid cell in `templates/gallery.html.twig`, inside
      `figure.find-item`, as a sibling of the `<img>` rather than a wrapper:
      `x-show="poster.page"`, `:href="poster.page"`, `target="_blank"`,
      `rel="noopener"`, and `@click.stop` so activating it does not also fire
      the image's `openPreview`. Give it a real accessible name, not a bare
      glyph. It is **omitted** when there is no `page`, never disabled.
- [x] 3.2 Style it in `app.css` as a corner affordance over the frame — clear of
      the centre so it does not compete with the cell's tap target, with a tap
      target large enough to hit on touch while staying inside its corner.
- [x] 3.3 Add `page` to the preview state: give `openPreview()` a `page`
      parameter defaulting to `''`, set it on the state object, and clear it in
      `closePreview()` alongside the other fields. Pass `poster.page` from the
      Find Posters call site only; the URL, upload and Plex call sites keep
      their current arguments and take the default.
- [x] 3.4 Add the link to `.viewer__bar` in the preview, shown with
      `x-show="preview.page"`, opening in a new browsing context. Place it so it
      does not crowd the Use/Close pair or the confirm step that replaces them.
- [x] 3.5 Add a template comment at the grid link explaining that the trigger is
      the presence of the address and not the provider — this is the guard
      against a later "simplification" to `source === 'tvmaze'`.

## 4. Credit TVmaze in the footer

- [x] 4.1 Save `https://static.tvmaze.com/images/tvm-header-logo.png` to
      `public/assets/providers/tvmaze.png`. Serving it locally is a spec
      requirement — the credit must render without a third-party request.
- [x] 4.2 Add the TVmaze entry to `templates/partials/_attribution.html.twig`,
      **last**, linking to `https://www.tvmaze.com/` with
      `target="_blank" rel="noopener"`, class `attribution__logo--tvmaze`, and
      intrinsic `width="253" height="80"` so the row reserves its space before
      the image decodes.
- [x] 4.3 Add `.attribution__logo--tvmaze` to `app.css` with a height that makes
      the mark read at the same weight as the other three (ratio is 3.16, between
      fanart.tv's 4.97 and TheTVDB's 1.85). Add its narrow-screen height in the
      existing phone block.
- [x] 4.4 Rewrite the load-bearing `app.css` comment on the drawer row. It
      currently states a three-logo width budget that a fourth logo breaks; the
      decision is to let the row wrap to a centred 2×2, which the existing
      `flex-wrap: wrap` already does (design Decision 9). Leaving the old comment
      would be a false statement about live CSS.

## 5. Tests

- [x] 5.1 `PosterProviderTest`: add `tvmaze` to the wire-slug assertions, `TVmaze`
      to the label assertions, and `Tvmaze` last in the fixed section order.
      Leave `testEveryProviderHasAPlaceInTheOrder` as-is — it already covers the
      whole set.
- [x] 5.2 `PosterSearchResultSectionsTest`: assert the four-way order
      `['TMDB', 'TVDB', 'fanart.tv', 'TVmaze']`, and that an unrecognised slug
      still lands in `Other` *after* TVmaze.
- [x] 5.3 `PosteriaApiPosterSourceTest`: assert the request URI now starts with
      the v2 path. Add a case parsing `page` off a poster, and one asserting a
      poster with no `page` yields `null`.
- [x] 5.4 `PosteriaApiPosterSourceTest`: add a case built from the **real TVmaze
      season response shape** — `url`, `thumb`, `source`, `page` and no `width`,
      `height`, `language` or `score` — asserting it parses to a candidate with
      those four nulls rather than throwing.
- [x] 5.5 Regression test for the silent `no_data` case: a `success: true`
      response with **no `code`** and `providers: {..., tvmaze: no_data}` yields
      `PosterSearchOutcome::Ok` and no user-facing error or partial flag. This
      pins behaviour that is already true by construction (design Decision 10) —
      do not add a suppression branch to make it pass.
- [x] 5.6 **The generality guard.** A test that puts `page` on a candidate whose
      `source` is *not* `tvmaze` and asserts the link still reaches the payload
      and renders. This is the test that fails if someone rewrites the condition
      as a provider check.
- [x] 5.7 `ChangePosterTest` (functional): assert the find-posters JSON carries
      `page` on a candidate that has one, omits or nulls it on one that does not,
      and still carries no `source` key.
- [x] 5.8 `ApplicationShellTest`: add TVmaze to the `PROVIDERS` constant and
      `tvmaze.png` to `testProviderLogoAssetsExist`. The existing per-provider
      logo count assertion then covers the fourth automatically.
- [x] 5.9 Add an assertion that the footer's credit order matches
      `PosterProvider::inSectionOrder()`, so the invariant the two specs share is
      enforced by a test rather than by prose in two files.

## 6. Docs

- [x] 6.1 `README.md` (~lines 427-437): the passage naming three sections and
      what each is good for becomes four. TVmaze's pitch is season artwork no
      other service carries.
- [x] 6.2 `docs/testing.md` (~line 206): update the pinned section order to the
      four-way one. Add the live round-trip checks from the design's migration
      plan — show, season, movie, collection — and state explicitly that a movie
      showing no TVmaze section and no warning is the **pass** condition, so a
      future tester does not report it as a bug.
- [x] 6.3 Check whether `openspec/config.yaml`'s capability map and external-
      dependency note, which both enumerate "TMDB / fanart.tv / TheTVDB", need
      the fourth service. Update if so.
- [x] 6.4 Check `CLAUDE.md` for staleness against this change. If nothing
      user-facing there changed, say so explicitly rather than inventing edits.

## 7. Gates

- [x] 7.1 `composer test` — PHPUnit 11, green.
- [x] 7.2 `composer stan` — PHPStan level 10 over `src/` and `tests/`, clean.
      Watch for the new nullable `page` needing no narrowing at its use sites.
- [x] 7.3 `composer cs` — PHP-CS-Fixer dry-run clean (`composer cs:fix` to
      apply).
- [x] 7.4 `openspec validate consume-poster-api-v2 --strict`.
- [ ] 7.5 Hand off to `/ship`. Do **not** archive until the `:dev` image has been
      validated against the live endpoint — the four searches in 6.2 plus the
      footer on both desktop and the phone drawer, and the grid link tapped on a
      real touch device to confirm it does not steal the cell's press. **Blocked
      on group 8:** the contract changed after groups 1-7 shipped, so validating
      the current `:dev` image would validate superseded behaviour.

## 8. Contract revision: split provenance from obligation

Groups 1-7 were built against a contract where `page` was sent only on the
licensed subset, which made its presence the obligation. The endpoint now sends
`page` on **every** poster and carries the obligation in a separate
`attribution_required` field. Groups 1-7 are left checked: that work happened and
most of it still stands.

The shipped code is **still licence-compliant** — it over-attributes rather than
under-attributes. What is wrong is the intent it expresses: a badge designed for
a minority now fires on all ~189 candidates of a show search, and nothing in the
code records which link may never be removed. No deadline pressure.

- [x] 8.1 Add `public readonly bool $attributionRequired = false` to
      `PosterCandidate`, after `$page`. **Not** nullable — the field is never
      sent present-and-false, so absence means false and there is no third state.
      Extend the docblock with the provenance/obligation split: `page` is where
      the poster came from, this is whether the licence compels the link.
- [x] 8.2 Parse it in `PosteriaApiPosterSource::candidates()` **strictly**, on
      identity with boolean `true`:
      `($poster['attribution_required'] ?? null) === true`. Not truthiness —
      this is the one flag in the client where a loose comparison could cause a
      compliance failure rather than a cosmetic one. Leave `page` parsing alone.
- [x] 8.3 Update the class docblock's note about `page`, which currently says it
      is sent only where a licence requires a link back. That is no longer true.
- [x] 8.4 Add `'attributionRequired' => $c->attributionRequired` to the payload in
      `ChangePosterController::findPosters()`, beside `page`. `source` still must
      **not** be published — keeping it out is the structural guard against
      someone re-keying the obligation onto a service name. Update the comment
      above the mapping, which currently explains the old single-field rule.
- [x] 8.5 Move the grid badge's binding in `templates/gallery.html.twig` from
      `x-show="poster.page"` to `x-show="poster.attributionRequired"`. Keep
      `:href="poster.page"`, `@click.stop`, `target`/`rel`, the accessible name,
      and omission-not-disabling. Rewrite the template comment: it currently
      states the superseded rule at length and would otherwise be the most
      misleading text in the file.
- [x] 8.6 Give `openPreview()` a source-label parameter alongside `page`, both
      defaulting to `''`, cleared in `closePreview()` with the rest. Pass
      `section.label` from the Find Posters call site. The label must be passed,
      never derived from a slug in the browser — deriving it would teach the page
      a provider name and undo 8.4.
- [x] 8.7 Change the preview credit's copy to **"View on \<source\>"**, bound to
      `preview.page` so it covers every candidate with an address. Note in the
      comment that a marked candidate is credited here as a *consequence* of
      carrying an address, not as the mechanism — the badge is the mechanism.
- [x] 8.8 Review `.find-item__credit` now that it is sparse again. It was sized
      and positioned for exactly this, so expect no change; confirm rather than
      assume. Check `.viewer__credit` still fits the longer "View on \<source\>"
      wording at narrow widths.

## 9. Contract revision tests

- [x] 9.1 `PosteriaApiPosterSourceTest`: `attribution_required: true` parses to
      true; the field absent parses to false; and a non-boolean truthy value
      (`"false"`, `1`, `"yes"`) does **not** turn it on. That last case is the
      point of parsing on identity.
- [x] 9.2 `PosteriaApiPosterSourceTest`: rewrite
      `testAPageIsParsedWhoeverSuppliedThePoster` for the new field — a candidate
      marked by a service this build does not know still parses as marked.
- [x] 9.3 `PosteriaApiPosterSourceTest`: assert a marked candidate always carries
      a `page`, so the obligation can never render as a link to nothing.
- [x] 9.4 `ChangePosterTest`: the payload carries `attributionRequired`, still
      carries no `source`, and a marked candidate with an unknown slug keeps both
      its marking and its place in the `Other` section.
- [x] 9.5 `PosterCreditLinkTest`: change the grid assertions from
      `x-show="poster.page"` to `x-show="poster.attributionRequired"`, and add
      the **negative** — the badge's binding must not name `page`. Collapsing the
      two conditions has to fail loudly.
- [x] 9.6 `PosterCreditLinkTest`: keep
      `testTheCreditIsNotConditionedOnWhichServiceSuppliedThePoster` as-is. It
      still guards the right thing and needs no edit.
- [x] 9.7 `PosterCreditLinkTest`: assert the preview link stays bound to
      `preview.page` and that the two surfaces read different conditions — the
      regression this whole group exists to prevent.

## 10. Contract revision docs

- [x] 10.1 **`CLAUDE.md` first.** The bullet added in 6.4 says the condition is
      `page`'s presence. That is now wrong, and it is the most misleading stale
      line in the repo because the file loads into every session. Rewrite it to
      key on `attribution_required` and state the provenance/obligation split.
- [x] 10.2 `docs/testing.md`: the credit-link checks assume a link only on TVmaze
      posters. Split them — the **badge** is TVmaze-only, the **preview link** is
      on every poster. The line "Posters from the other three services carry
      **no** link at all" is now wrong for the preview and must be corrected. Add
      the season check where fanart.tv links to the series while the others link
      to the season, so a tester does not report it as a bug.
- [x] 10.3 `README.md`: the FAQ answer "What's the small link on some posters?"
      describes the sparse case only. Cover both — a source link available on
      posters generally, and the always-present credit on sources that require it.
- [x] 10.4 `openspec/config.yaml`: check the poster-sources capability line, which
      mentions "the per-candidate link back a source's licence may require", still
      reads correctly against the split.

## 11. Contract revision gates

- [x] 11.1 `composer test`, `composer stan`, `composer cs` — all three green.
- [x] 11.2 `openspec validate consume-poster-api-v2 --strict`.
- [ ] 11.3 Back to `/ship`, which will commit this revision on top of `3c1df2f`
      and rebuild `:dev` before the validation in 7.5 can mean anything.

## 12. The badge could not be seen

Reported from the `:dev` image: the credit badge is hard to see, and a control
that cannot be seen cannot be deliberately avoided — which for a link that leaves
the application is the wrong way round. Two causes, one of them a plain defect.

- [x] 12.1 **Root cause: `color: var(--text)` — a token this project has never
      had.** The badge was the only reference to it in the whole stylesheet, so
      the declaration was dropped and the glyph fell back to an inherited colour.
      Replace with `var(--ink)`.
- [x] 12.2 Second cause: the badge was translucent (`color-mix` of `--bg` at 72%
      plus a blur) over artwork of unknown colour. Draw it opaque on `--surface-2`
      with the `#4b515b` border and `--elev-1`, which is the treatment
      `.card__actions` already gives its buttons over the hover overlay for
      exactly this reason. This is also what the `visual-design` spec requires —
      legibility SHALL NOT depend on the blur.
- [x] 12.3 Strengthen the glyph: 15px and weight 600, up from a 13px regular `↗`
      that was thin enough to lose against a busy poster. Keep the 28px target and
      the corner position — the aim is a control that is unmistakable, not one
      that competes with the artwork.
- [x] 12.4 Carry the border into the hover/focus state so the shape does not
      change on interaction.
- [x] 12.5 **Close the gap that let 12.1 through.** Add
      `testEveryTokenReadIsATokenDeclared` to `DesignTokenContractTest`: every
      `var(--x)` in `app.css` must name a token declared somewhere in it. Scoped
      component properties (the alert variants' own `--alert-hue` and friends)
      are legitimate, so the check is stylesheet-wide rather than `:root`-only.
      Verify it fails against the original typo before trusting it.

## 13. The badge goes on every poster

Decided on the `:dev` image, once the badge was visible enough to judge: the
link back is useful on every candidate, not only the licensed ones. That is a
product call and it reverses the split in group 8 — but only for *where the
badge appears*, never for what the licence requires.

- [x] 13.1 Change the badge's condition to
      `poster.attributionRequired || poster.page`. **The first clause is not
      redundant** — `poster.page` alone renders identically today, and the whole
      point of group 8 was that the obligation must survive a later decision to
      drop the provenance badge. Named first so it reads as "always when
      required, also when we have somewhere to point".
- [x] 13.2 Add `:data-attribution-required` to the badge. The two are drawn
      identically — the licence asks that the link be shown, not that it be shown
      differently — so this is where the distinction lives when the pixels
      cannot carry it.
- [x] 13.3 Rewrite the template comment, which argued at length for the badge
      being bound to the marking alone.
- [x] 13.4 Rewrite the `.find-item__credit` CSS comment. It justified an
      assertive control on the grounds that it was rare; it is now on nearly
      every candidate, and the justification is the opposite one — quiet at rest,
      accent only on hover, so ~189 of them read as controls rather than as the
      grid's texture.
- [x] 13.5 `PosterCreditLinkTest`: replace the "not bound to the address"
      assertion, which is now false by design, with one that the condition still
      *names* the marking and names it first. Add the DOM-marker assertion. Keep
      the provider-name guard untouched.
- [x] 13.6 Docs: `README.md` and `docs/testing.md` both described a badge that
      appears only on TVmaze. Rewrite both around one badge on everything, with
      the licence point kept as the reason some of them are not optional.
- [x] 13.7 `CLAUDE.md`: the bullet said the credit is keyed on the marking and
      nothing else. Restate it as the two-clause condition and say why the first
      clause cannot be dropped.
- [x] 13.8 Spec: the scenario asserting an unmarked candidate shows no control is
      now false. Restate it as the real invariant — an unmarked candidate's link
      is one Marquee could remove and stay in conformance, a marked one's is not
      — and add that the two may look identical.
