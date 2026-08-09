## 1. Confirm what Plex actually returns

Everything downstream depends on this. Do it first and do not guess.

- [x] 1.1 Request `GET /library/metadata/{ratingKey}/posters` against the real
      Plex server for an item that has both uploaded posters and agent-supplied
      artwork; capture the raw response. → 40 posters, movie, rating key 78657.
- [x] 1.2 Pin the field names for: each poster's image path, its provenance,
      and the selected marker. → `key`, `thumb`, `ratingKey`, `selected`,
      `provider`. Table in `design.md` Decision 2.
- [x] 1.3 Confirm an uploaded poster is distinguishable from agent-supplied
      artwork. → Yes: `ratingKey` begins `upload://`, and `provider` is absent.
      Grouping stands.
- [x] 1.4 Check what the response looks like for an item with no posters, and
      for an item whose poster was never explicitly selected. → `selected="1"`
      on exactly one entry; absence of a `"1"` is the no-selection case.
- [x] 1.5 **Unplanned finding.** Only 14 of 40 were server-held; 26 were remote
      provider URLs duplicating Find Posters. Scope narrowed to server-held
      posters only — `design.md` Decision 6, and a new spec requirement.

## 2. Reading the poster list

- [x] 2.1 Add a poster-list method to `App\Plex\PlexClient` and implement it in
      `HttpPlexClient`, alongside the existing `uploadPoster` on the same path.
      → `PlexClient::itemPosters()`.
- [x] 2.2 Add value objects: a Plex poster candidate (image path, provenance,
      selected flag) and a two-case outcome (posters returned / Plex
      unreachable). Do **not** reuse `PosterSearchOutcome`.
      → `PlexPosterCandidate`, `PlexPosterOrigin`, `PlexPosterList`, `PlexPosterListing`, `PlexPosterOutcome`.
- [x] 2.3 Add the service that turns a poster's category and filename into a
      rating key via `PlexItemRepository` and returns its candidates, or the
      unreachable outcome on `PlexException`.
      → `PlexPosterService`.
- [x] 2.4 Wire the service into the container.
      → Autowired by PHP-DI; only `SignedImagePath` needed an explicit binding (3.6).

## 3. Serving candidate images without leaking the token

- [x] 3.1 Attempt extracting the HMAC sign/verify core out of
      `App\Poster\Wall\StreamToken` into a shared signer, leaving the Live TV
      sentinel behind. Run the existing wall tests — they must pass unmodified.
      → `App\Plex\SignedImagePath`. `StreamToken` composes it and keeps its
      `(string $secret)` constructor, so all 33 wall/token tests passed with no
      edits.
- [x] 3.2 If 3.1 disturbs the wall, back it out and add a separate signer for
      candidate images instead. Either way the proxy must refuse any path it did
      not sign. → Not needed; 3.1 held.
- [x] 3.3 Add the proxy route serving candidate image bytes via the client's
      existing "fetch any Plex image path" method, **inside the authenticated
      route group** — unlike the wall's public equivalent.
      → `GET /plex-poster-image/{token}` → `PlexPosterImageController`.
- [x] 3.4 Verify no Plex URL or token appears in any response body or page.
- [x] 3.5 Rename `PlexClient::sessionPoster()` to `imageAt()`. Caused by this
      change: the method is a generic "fetch any Plex image path" and now has a
      second, non-session caller, so the old name would misdescribe it at the
      candidate proxy's call site.
- [x] 3.6 Derive the candidate signing key from the connection store's secret
      rather than reusing it, so a candidate token cannot be replayed against
      the wall's **public** poster proxy by an unauthenticated caller.

## 4. Listing and applying

- [x] 4.1 Add the list action to `ChangePosterController`, returning grouped
      candidates as JSON with proxied image URLs, mirroring the JSON shape
      `findPosters` uses.
      → `ChangePosterController::plexPosters()`.
- [x] 4.2 Return the not-linked state for a poster with no `plex_items` record,
      distinctly from the empty and unreachable states.
      → `PlexPosterOutcome::NotLinked`, distinct from `None` and `Unavailable`.
- [x] 4.3 Add a method to `ChangePosterService` that applies a poster from a
      signed Plex image path — fetch bytes server-side, then through the same
      `replaceAndPush` the other tabs use, so it is stored, uploaded, and locked.
      → `ChangePosterService::changeFromPlexPath()`.
- [x] 4.5 **Reworked after the endpoint was verified.** Applying now *selects*
      the poster instead of uploading it: `PlexPosterWriter::selectPoster()`,
      `PlexExportService::selectInPlex()`, and a re-read of the item's posters
      at apply time to get Plex's own key and to catch a poster removed while
      the dialog sat open. The original rationale for uploading — that a
      selected poster would not be locked — was false; locking is its own call.
      See `design.md` Decision 4.
- [x] 4.4 Add the apply action and register all three routes in `src/Routes.php`.
      → `ChangePosterController::usePlexPoster()` + three routes.

## 5. The tab

- [x] 5.1 Add the Plex Posters tab button to the strip in
      `templates/gallery.html.twig`, between From URL and Find Posters.
- [x] 5.2 Add the tab panel: two labelled groups, uploaded first, each shown
      only when it has candidates.
      → `plex_group()` macro, one markup path for both groups.
- [x] 5.3 Mark the candidate Plex reports as selected; render no marker when
      Plex reports no selection.
      → `.find-item__badge` "In use".
- [x] 5.4 Add the sibling state object and lazy first-open fetch in
      `public/assets/gallery.js`, kept separate from `finder` so both tabs can
      hold results at once.
      → `plexPosters` state, sibling to `finder`.
- [x] 5.5 Wire candidate activation into the existing `openPreview` flow so
      preview → use → confirm is reused with no fork.
      → `openPlexPreview()` feeds the shared `openPreview`.
- [x] 5.6 Add loading, empty, and unreachable states using the same
      `.stats` / `.alert` treatment as Find Posters.
- [x] 5.7 Render the tab disabled with its reason when the poster has no Plex
      item, keeping the strip's shape.
      → `data-linked` on the card, so no round trip is needed to know.

## 6. Tests

- [x] 6.1 Client: parses a poster list, including provenance and the selected
      marker, from a captured real response.
      → `PlexPosterListTest`, fixture trimmed from the real 40-poster response.
- [x] 6.2 Service: rating key resolution; empty list; `PlexException` mapping to
      the unreachable outcome.
      → `PlexPostersTabTest`.
- [x] 6.3 Signer: a signed path round-trips; a tampered token is refused; a path
      that was never signed is refused. → `tests/Unit/Plex/SignedImagePathTest`,
      including that wall and candidate tokens are not interchangeable.
- [x] 6.4 Proxy route: requires a session; refuses an unsigned token; never
      emits a Plex URL. → `tests/Functional/PlexPosterImageTest`.
- [x] 6.5 Controller: grouped JSON shape; the not-linked, empty, and unreachable
      responses are distinct.
      → `PlexPostersTabTest`.
- [x] 6.6 Apply: stores the poster and pushes it to Plex locked; a failure
      leaves the existing poster unchanged.
      → `PlexPostersTabTest`, including that a forged token changes nothing.
- [x] 6.7 Confirm the existing wall and Find Posters tests still pass unedited.
      → `PosterWallTest`, `PosterWallServiceTest`, `StreamTokenTest`,
      `StreamTokenSecretTest`, and `ChangePosterTest` are all **unedited** and
      green. Three test files did change, none of them a behaviour test for the
      wall or for Find Posters:
      - `tests/Support/FakePlexClient` — the new `itemPosters()`, plus the 3.5
        rename.
      - `tests/Unit/Plex/HttpPlexClientTest` — one assertion, the 3.5 rename.
      - `tests/Unit/Asset/PreviewApplyProgressTest` — two shape tripwires that
        pinned the apply path's two-endpoint form. The path now has three
        endpoints, so they were widened to guard the new mapping just as
        strictly, and a third assertion was added: a Plex candidate must be
        applied by its token and never by its proxy URL.

## 7. Documentation

- [x] 7.1 Update `README.md` to name Plex Posters alongside Find Posters, and
      call out the recover-a-previously-used-poster case explicitly — it is the
      reason the feature exists and is not self-evident from the tab name.
      → Feature list, two new FAQ entries, and the agent-switch recovery advice,
      which this change materially improves.
- [x] 7.2 Check `docs/` and `CLAUDE.md` for staleness. If nothing there is
      affected, say so explicitly rather than inventing edits.
      → `docs/testing.md:265` mentions Find Posters under Fix Match and stays
      accurate; a rating-key lookup is unaffected by a match correction.
      `CLAUDE.md` needed nothing — it points at the capability map rather than
      restating it. The map itself in `openspec/config.yaml` **was** stale and
      is updated: the capability is no longer posteria.app alone.
- [ ] 7.3 At archive time, widen the `poster-sources` spec Purpose — it
      currently describes the capability as posteria.app only. Delta specs carry
      requirements, not the Purpose heading, so this will not happen on its own.

## 8. Gates

- [x] 8.1 `composer test` → 750 tests, green.
- [x] 8.2 `composer stan` → level 10, no errors.
- [x] 8.3 `composer cs` → 0 of 171 files to fix.
- [x] 8.4 Validate the change: `openspec validate add-plex-posters-tab --strict` → valid.
- [ ] 8.5 Verify against the real Plex server: list an item's posters, apply one
      from the uploaded group, confirm it lands in Plex locked.
