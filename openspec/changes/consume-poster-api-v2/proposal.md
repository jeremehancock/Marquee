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
- A candidate that arrives with a **link back to the supplying service's own
  page** now shows that link, both in the results grid and in the full-screen
  preview. This is a licence obligation, not an embellishment — TVmaze artwork
  is licensed CC BY-SA and attribution is discharged by linking back from where
  the image is shown.
- The link is driven by **the presence of the address on the candidate**, never
  by which service supplied it, so a future service with the same obligation is
  credited without another release.

Not breaking. No new setting, no new credential, no change to how Marquee signs
its requests, and no change to the Plex Posters tab.

## Capabilities

### New Capabilities

None. This extends existing behaviour.

### Modified Capabilities

- `poster-sources`: Find Posters groups candidates into **four** labelled
  sections rather than three, in a fixed order ending with TVmaze. Adds a
  requirement that a candidate carrying a link back to its service's page shows
  that link wherever the candidate is displayed. Records that a service
  reporting no data for a media type it does not cover is a normal result and
  is never surfaced as an error or a warning.
- `application-shell`: the provider attribution credits **four** services rather
  than three, adding TVmaze last.

## Impact

- **Poster source client** — `PosteriaApiPosterSource` (endpoint path, and the
  new link-back field on a parsed candidate), `PosterCandidate`,
  `PosterProvider`. The provider enum is closed, so without its new case the
  v2 candidates would land under the existing `Other` heading rather than a
  named one.
- **Find Posters payload and UI** — `ChangePosterController::findPosters()`
  carries the link through; `templates/gallery.html.twig` and
  `public/assets/gallery.js` render it in the grid cell and the preview.
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
