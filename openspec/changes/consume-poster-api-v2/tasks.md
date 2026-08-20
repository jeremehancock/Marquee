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
      real touch device to confirm it does not steal the cell's press.
