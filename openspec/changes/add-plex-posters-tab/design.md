## Context

Plex keeps every poster an item has ever had — uploads and agent-fetched
artwork alike — and never prunes them. Marquee already talks to that server on
every import and export, but has never read the list.

Three things in the codebase shape this change more than anything else:

| Existing thing | Where | What it gives us |
| --- | --- | --- |
| `POST /library/metadata/{id}/posters` | `HttpPlexClient::uploadPoster` | The same path answers `GET` with the item's poster list. Already reachable, already authenticated. |
| `StreamToken` + `streamPoster` | `src/Poster/Wall/` and `PosterWallController` | A worked solution to "show a Plex image in a page without leaking the token": HMAC-signed path in an opaque URL, proxied server-side. |
| `fetchFromPlex` | `ChangePosterService:73` | Pull bytes from Plex for an item, validate, replace on disk. Applying a chosen poster is this with an explicit path. |

`sessionPoster(string $thumb)` on the Plex client is already a generic "fetch
any Plex image path" — it is not wall-specific despite its name.

Constraints that are not negotiable:

- **The Plex token is never disclosed** (`application-shell`, "The stored Plex
  token is never disclosed"). This is what forces the proxy.
- Thin controllers → services → value objects; PHPStan level 10; strict types.
- The change-poster dialog's preview/confirm flow is shared by all tabs and must
  stay that way.

## Goals / Non-Goals

**Goals:**

- List an item's Plex-held posters in a fourth tab, grouped by provenance.
- Reuse the existing preview → use → confirm flow with no fork.
- Reuse the signed-token proxy pattern rather than inventing a second one.
- Keep Find Posters' code and behaviour untouched.

**Non-Goals:**

- Deleting or purging posters from Plex. Separate change; see the proposal.
- Selecting an existing Plex poster *in place* on the Plex side (see Decision 4).
- Caching the poster list. Every open re-reads it.
- Any change to how posters enter the library. This is still a mode of
  *changing* an existing poster.

## Decisions

### 1. A Plex poster source that does not implement `PosterSource`

`PosterSource::find(PosterQuery)` takes a title, year, media type, and TMDB id,
and returns a `PosterSearchResult` whose outcomes include `NoMatch`,
`RateLimited`, and `correctedTmdbId`. A Plex lookup takes a rating key and can
produce none of those.

Implementing the interface would mean accepting a query object the
implementation ignores while passing the rating key through some side channel,
and returning an enum where half the cases are unreachable. That is a worse lie
than two small interfaces.

So: a separate `PlexPosterSource` (working name) with its own value objects —
a candidate carrying a Plex image path, a provenance, and a selected flag; and
its own two-case outcome.

*Alternative considered:* generalise `PosterQuery` to carry an optional rating
key and widen `PosterSearchOutcome`. Rejected — it makes every existing Find
Posters call site handle states it can never see, to save one small class.

### 2. Provenance comes from Plex, not from inference

**Confirmed against a real server.** A 40-poster movie returned:

```xml
<Photo key="/library/metadata/78657/file?url=upload%3A%2F%2Fposters%2F..."
       ratingKey="upload://posters/5df82737..."
       thumb="/library/metadata/78657/file?url=upload%3A%2F%2F..."
       selected="1" />
<Photo key="https://image.tmdb.org/t/p/original/2Y44Ncb....jpg"
       ratingKey="https://image.tmdb.org/t/p/original/2Y44Ncb....jpg"
       thumb="https://images.plex.tv/photo?...&url=..."
       selected="0" provider="tmdb" />
```

| Attribute | Meaning |
| --- | --- |
| `key` | full-resolution image |
| `thumb` | grid image; a smaller preview for remote entries, identical to `key` for server-held ones |
| `selected` | `"1"` on exactly one entry |
| `ratingKey` | the provenance marker: `upload://`, `metadata://`, `media://`, or a remote URL |
| `provider` | `tmdb` / `imdb` / `tvdb` / `fanarttv` / `gracenote` / `local` — **absent** on `upload://` and `media://` |

Two findings shaped the rest:

**Uploads are distinguishable** — `ratingKey` begins `upload://`. The original
open question is answered and the grouping stands. Nine of forty on the item
tested, which is also a fair sample of how much history accumulates.

**Most of the list is not on the server at all.** Of forty, fourteen were
server-held (`upload://`, `metadata://`, `media://`, all with relative paths)
and twenty-six were absolute URLs to TMDB, fanart.tv, TheTVDB, IMDb, and
Gracenote — the providers Find Posters already aggregates. See Decision 6.

Grouping is a presentation concern, so the service returns candidates with their
provenance and the template groups them. Sorting uploads first is not the
service reordering results; it is two lists rendered in a fixed order.

Note the second group cannot be called "from Plex's metadata agent": it mixes
agent downloads (`metadata://posters/tv.plex.agents.movie_*`, `provider="tmdb"`),
a local poster file (`provider="local"`), and an image embedded in the media
(`media://…/Thumbnails/thumb1.jpg`).

### 3. Reuse `StreamToken`'s pattern, extract rather than copy

`StreamToken` signs a Plex image path into an opaque token and refuses anything
it did not sign. That is exactly what the candidate proxy needs, and its
`LIVE` sentinel and wall-specific naming are the only parts that do not apply.

Preferred approach: extract the sign/verify core into a shared signer that both
the wall and the poster proxy use, leaving `StreamToken`'s Live TV handling
where it is. The wall's behaviour must not change — its tests are the guard.

If extraction turns out to disturb the wall more than it is worth, a second
small signer with its own secret-derived key is acceptable. What is *not*
acceptable is a proxy that fetches unsigned paths.

**Resolved during implementation.** The extraction held: `App\Plex\
SignedImagePath` carries sign/verify, `StreamToken` composes it and keeps its
`(string $secret)` constructor, and the wall's tests passed with no edits.

**The two signers must not share a key.** The wall's poster proxy is public and
prints its tokens into a page anyone can read. With one shared key, a candidate
token would verify there too — letting an unauthenticated caller feed it to the
wall route and pull the image back. The candidate signer is therefore
constructed with a key *derived* from the connection store's secret
(`hash_hmac('sha256', 'plex-poster-candidate', $secret)`) rather than the secret
itself. Deriving rather than storing a second secret keeps the store unchanged
and keeps the wall's signing input byte-identical.

Routes. Nothing is added to the auth middleware's public allowlist, which is
deny-by-default, so all three are authenticated:

```
GET  /library/{category}/plex-posters       → JSON: grouped candidates
GET  /plex-poster-image/{token}             → proxied image bytes
POST /library/{category}/change/plex-poster → apply
```

The image proxy is deliberately *not* under `/library/{category}/`. The signed
token is self-contained, so a category segment there would be an unused
parameter — the kind that later reads as a bug.

### 4. Applying selects the poster in place; it does not re-upload

**Revised during implementation. The first version of this decision was wrong**
and is kept below, struck through, because the reasoning that replaced it is the
point.

Applying does four things: fetch the bytes, store them as Marquee's poster,
point the Plex item at the poster it already has, and lock it.

```
  fetch bytes ──▶ store locally ──▶ selectPoster(key) ──▶ lockPoster()
  (Marquee needs                    PUT …/poster?url=      PUT …?thumb.locked=1
   its own copy)                    <posterKey>
```

Not `replaceAndPush()`. Every other tab supplies an image Plex does not have, so
uploading is the only way to give it one. This poster is *already there*, and
Plex never prunes an item's posters — so uploading would leave a second,
byte-identical copy. Most absurdly when the poster being applied is the one Plex
already has selected, which the tab shows and marks "In use": one click, one
duplicate of itself.

The lock is unaffected. `lockPoster()` sets a flag on the item's thumb field and
is indifferent to how that thumb was set; `PlexExportService` has always called
it as a separate step after `uploadPoster()`. Selecting reaches exactly the same
end state.

> ~~*Applying re-uploads.* This adds another copy to Plex's list… accepted,
> because it uses zero new Plex API surface, and because a poster selected in
> place would not be locked and could be replaced by the next metadata
> refresh.~~
>
> The second half is false. Locking was never a consequence of uploading — it is
> its own call, made after it. The only true objection was the untested
> endpoint, and `PUT /library/metadata/{id}/poster?url={posterKey}` was then
> confirmed against a real server: `200`, and `selected="1"` moved to the chosen
> poster.

Two consequences of selecting rather than uploading:

- **The list is re-read at apply time**, and the chosen poster looked up again
  by its path. Selecting needs Plex's own key for the poster, which the signed
  path does not carry; re-reading gets it from the response rather than
  inferring it from the path's shape. It also catches a poster deleted from Plex
  while the dialog sat open, which now fails plainly instead of selecting a key
  that no longer resolves.
- **`PlexPosterCandidate` carries `posterKey`** read straight from the XML. The
  path and the key are related today (`…/file?url=<key>`), but a path is an
  address Plex may restyle and the key is the identity, so one is not derived
  from the other.

The fetch happens server-side rather than by pointing the existing `change/url`
endpoint at our own proxy URL. A loopback HTTP request from the app to itself
would have to carry the session cookie and would fail in exactly the deployments
where the container cannot resolve its own external URL.

### 5. Client state mirrors `finder`, and does not merge with it

`gallery.js` holds Find Posters state in a `finder` object with
`loading` / `error` / `notice` / `results`. The new tab gets a sibling object
with the same shape and its own fetch, loaded lazily on first tab activation —
the same trigger Find Posters uses at `gallery.html.twig:110-111`.

Separate state, not a shared "current source", because the two tabs must be able
to hold results simultaneously: a user comparing what Plex has against what a
search found should not lose one by looking at the other.

### 6. Everything Plex reports is listed, in three groups

**Revised during implementation.** The first version excluded the twenty-six
remote provider URLs as duplicating Find Posters. Two of the three reasons given
for that were weaker than they looked:

- ~~*They duplicate Find Posters.*~~ Mostly, but not entirely. Plex also offers
  **IMDb** (`m.media-amazon.com`) and **Gracenote** (`metadata-static.plex.tv`)
  artwork, and posteria.app carries neither. Plex's list is also bound to the
  rating key, with no title-matching step — the failure mode the stale-TMDB-id
  correction in `ChangePosterController` exists to repair.
- ~~*They would need a second apply path and a second image path.*~~ They need
  no new server code at all. An offered candidate is a URL, which is what the
  From URL tab already posts to `change/url`; the client calls the existing
  `openPreview(url, 'url')` and the rest is unchanged. Find Posters already
  loads remote thumbnails directly into the page, so that is no new precedent
  either.

The one reason that held is **burial**: twenty-six stock posters above nine
uploads is exactly the failure the grouping was meant to prevent. That is an
argument about *order*, not about inclusion — so offered artwork is listed last.

```
  Uploaded to Plex   9   ← the user's own history        held → proxy → select
  Found by Plex      5   ← what Plex had before them     held → proxy → select
  Offered by Plex   26   ← what Plex could fetch         URL → direct → upload
```

The classifier is `key` starts with `/`. That is the same property the proxy's
guard enforces, so a candidate classified as held is by construction one the
proxy can serve and an offered one is by construction one it would refuse — the
rule and the guard cannot drift apart. An offered address must additionally be
`http(s)`, since it goes into the page as an image source; anything else is
dropped rather than trusted.

The asymmetry in how the groups apply is real and worth stating plainly rather
than smoothing over: applying a held poster adds nothing to Plex, applying an
offered one uploads. That is the difference between Plex having the image and
Plex merely knowing where it is.

## Risks / Trade-offs

**Plex's response shape for the poster list is not yet confirmed** → First task
is to read a real response from the user's server and pin the field names. The
grouping and selected-marker requirements both depend on it. If provenance turns
out not to be reportable, the grouping requirement needs revisiting before the
UI is built — surface that immediately rather than shipping a guessed grouping.

**Extracting the signer could disturb the poster wall** → The wall's existing
tests must pass unchanged; run them before and after. Fall back to a separate
signer if extraction gets invasive.

**Applying duplicates the poster in Plex** → Accepted and documented above. It
compounds over repeated applies on one item, which makes the purge change more
valuable rather than less.

**A long list on a much-edited item** → The grid is already lazy-loaded per
candidate in Find Posters, and each thumbnail is a proxied request. Grouping
puts the useful ones first, which is the main mitigation. If lists prove long
enough to hurt, capping the agent-supplied group is a later refinement, not
something to build speculatively.

**The proxy is a new outbound path to Plex** → Signature verification is the
control, and it is the same control the wall proxy already relies on. The proxy
must refuse any path it did not sign, and must sit behind the authenticated
route group — the wall's equivalent is public, this one must not be.

## Open Questions

All resolved during implementation:

- ~~What does Plex return, and how is an uploaded poster distinguished?~~
  Answered in Decision 2. `ratingKey` begins `upload://`.
- ~~Does the response mark the selected poster?~~ Yes, `selected="1"` on exactly
  one entry. An item with no explicit selection simply has no `"1"`, which the
  spec already requires be rendered as no marker.
- ~~Extract the signer or add a second one?~~ Extracted; the wall's tests passed
  unmodified.

Raised and settled after the real response was seen:

- ~~Should remote provider URLs be listed?~~ Yes, as a third group, last.
  Decision 6 — revised after the first answer (no) turned out to rest on two
  claims that did not survive checking.
