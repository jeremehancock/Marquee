## Why

Find Posters draws on three services, and none of them carries much season
artwork. TVmaze does — often a season image no other service has — and the
poster source now offers it. Taking it widens what a user can find for the
category the current three serve worst, and it costs one path change plus a
credit link.

## What Changes

- Find Posters searches the poster source's **v2** endpoint instead of v1.
  Same request, same header, same failure codes; v1 is frozen rather than
  deprecated, so nothing is racing a shutdown.
- A fourth service, **TVmaze**, appears as its own labelled section in Find
  Posters and as a fourth logo in the provider attribution — last in both, so
  the two orders continue to agree.
- TVmaze is **television only**. On a movie or a collection it reports that it
  has no data, and that is a normal outcome: the results stay clean, with no
  error and no warning, exactly as they read today.
- Every candidate now arrives with a **link to the supplying service's own page
  for the work**. That is provenance — useful, and ours to present as we see
  fit. It is offered in the full-screen preview, where a user is looking at one
  poster and may want to know where it came from.
- Separately, a candidate may arrive **marked as requiring attribution**. That
  marking is the supplying service saying its licence obliges us to render the
  link. TVmaze is licensed CC BY-SA and is the only source marked today. A
  marked candidate carries a link badge on the poster itself, everywhere the
  poster is shown.
- **The two are not the same thing and are not driven by the same field.** A
  link we choose to show is a product decision; a link we are required to show
  is not, and only the second may never be removed. The obligation is driven by
  the marking alone — never by the presence of an address, and never by which
  service supplied the candidate — so a future service under the same licence is
  credited without another release.
- Wording on an unmarked candidate's link reads as plain provenance ("View on
  TMDB"), never as a licence notice, so Marquee never asserts a licence
  condition about artwork that carries none.

Not breaking. No new setting, no new credential, no change to how Marquee signs
its requests, and no change to the Plex Posters tab.

## Capabilities

### New Capabilities

None. This extends existing behaviour.

### Modified Capabilities

- `poster-sources`: Find Posters groups candidates into **four** labelled
  sections rather than three, in a fixed order ending with TVmaze. Adds two
  separate requirements — one obliging a visible credit on any candidate the
  source marks as requiring attribution, and one permitting a neutrally worded
  provenance link on any candidate carrying a source address, which must not be
  relied on to satisfy the first. Records that a service reporting no data for a
  media type it does not cover is a normal result and is never surfaced as an
  error or a warning.
- `application-shell`: the provider attribution credits **four** services rather
  than three, adding TVmaze last, and states that crediting a service in the
  footer does not credit any particular poster it supplied.

## Impact

- **Poster source client** — `PosteriaApiPosterSource` (endpoint path, plus the
  source page and the attribution marking on a parsed candidate),
  `PosterCandidate`, `PosterProvider`. The provider enum is closed, so without
  its new case the v2 candidates would land under the existing `Other` heading
  rather than a named one.
- **Find Posters payload and UI** — `ChangePosterController::findPosters()`
  carries both fields through; `templates/gallery.html.twig` and
  `public/assets/gallery.js` render the obligation as a badge on the poster and
  the provenance as a link in the preview.
- **Attribution** — `templates/partials/_attribution.html.twig`, a new local
  logo asset under `public/assets/providers/`, and its sizing class in
  `app.css`.
- **External service** — the posteria.app Marquee API v2. Already deployed and
  answering; no work outside this repo.
- **Docs** — `README.md` (what each section is good for) and `docs/testing.md`
  (the pinned section order, and the live round-trip checks).
- **Unaffected** — authentication, the settings store, the Plex Posters tab,
  ranking, and the request parameters. Marquee makes no clock-sync call to the
  service, so the endpoint's `time` route is not in play.
