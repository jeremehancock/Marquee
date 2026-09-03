# Testing Marquee against Plex

Five Plex-facing behaviors are worth verifying by hand from time to time:

1. A poster is **locked** in Plex after you update it in Marquee.
2. The **Kometa "Overlay" label** feature (**Settings → Plex**).
3. **Plex Posters** — applying one selects it rather than uploading a copy.
4. **Orphan detection** — posters for media that no longer exists in Plex.
5. **A corrected match** — Plex's **Fix Match**, and what the next import does
   with it.

The first two can be checked automatically with the included script
([`scripts/marquee-plex-test.py`](../scripts/marquee-plex-test.py)) or manually
against the Plex API. The third is a Plex API check below. The last two are
real-world workflow tests (change something in Plex, then check Marquee) — see
the final sections.

> The unit/functional test suite (`composer test`) covers Marquee's internal
> logic. This page is about validating the *live* round-trip to a real Plex
> server — the part tests mock out.

---

## Automated: `scripts/marquee-plex-test.py`

A self-contained tester (Python 3.8+, **standard library only** — no
`pip install`). It borrows a signed-in Marquee session, triggers **Send to Plex**
for one poster, then verifies the result directly in Plex.

### 1. Pick a test item and gather its identifiers

- **`CATEGORY` + `FILENAME`** — the poster in Marquee. Easiest source: hover the
  poster and read the **Download** action's link target,
  `/posters/<CATEGORY>/<FILENAME>`. `CATEGORY` is one of `movies`, `tv-shows`,
  `tv-seasons`, `collections`.
- **`RATING_KEY`** — the Plex item. In Plex Web: item → **⋯ → Get Info → View
  XML**; the number in the URL (`…/library/metadata/<RATING_KEY>?…`).

### 2. Configure it

Either edit the `CONFIG` block at the top of the script, **or** set the same
names as environment variables (env wins, so you never have to commit real
values):

| Variable | Meaning |
| --- | --- |
| `MARQUEE_URL` | e.g. `http://localhost:1818` (or your `:dev` instance) |
| `MARQUEE_SESSION` | the `PHPSESSID` cookie from a browser already signed in to Marquee (DevTools → Application → Cookies). Signing in is a Plex browser flow the script cannot drive, so it borrows a session rather than making one. |
| `PLEX_URL` / `PLEX_TOKEN` | your Plex server + token |
| `CATEGORY` / `FILENAME` / `RATING_KEY` | the test item (above) |
| `RUN_LOCK_TEST` / `RUN_KOMETA_TEST` | `true`/`false` to toggle each test |
| `EXPECT_LABEL_REMOVED` | set to match the Kometa overlay toggle under **Settings → Plex** (`true` = feature enabled) |
| `INSECURE` | `true` to skip TLS verification (self-signed certs) |

### 3. Run it

```bash
# after editing CONFIG:
python3 scripts/marquee-plex-test.py

# or without editing the file (env overrides CONFIG):
RATING_KEY=45678 CATEGORY=movies FILENAME="Dune (2021) [Movies].jpg" \
  PLEX_URL=http://10.0.0.5:32400 PLEX_TOKEN=xxxx \
  MARQUEE_URL=http://localhost:1818 MARQUEE_SESSION=abc123sessionid \
  python3 scripts/marquee-plex-test.py
```

It prints a `PASS`/`FAIL` summary and exits non-zero if anything failed (handy in
scripts or a cron sanity check).

### What it does, step by step

- **Preflight** — confirms Plex is reachable, the token is valid, the item
  exists, and the borrowed Marquee session is still live.
- **Lock test** — records the current lock state, calls Marquee's
  `POST /library/<category>/send-to-plex`, then asserts the item's `thumb` field
  is locked in Plex.
- **Kometa test** — ensures the item has an `Overlay` label (adds one if
  missing), triggers Send to Plex, then checks whether the label was removed,
  comparing against `EXPECT_LABEL_REMOVED`.

### Side effects & cautions

- **Send to Plex re-applies and locks** the poster Marquee already stores — the
  item stays locked afterward (that's the app's normal behavior).
- The Kometa test **temporarily adds** an `Overlay` label if the item lacks one
  and **removes it again** at the end if Marquee didn't. It won't leave a stray
  label on a non-Kometa item.
- The script can't read Marquee's settings, so `EXPECT_LABEL_REMOVED` is how you
  tell it how the overlay toggle is set. A mismatch is reported as a failure with
  a hint.
- **Never commit real tokens.** The script ships placeholders; pass real values
  via env vars or a local copy you don't check in.

Point `MARQUEE_URL` at your **`:dev`** instance to validate changes before they
reach production.

---

## Manual validation (Plex API)

Useful when you want to see the raw truth or the script isn't handy.

### Setup

```bash
PLEX="http://192.168.1.10:32400"     # the internal URL Marquee uses
TOKEN="xxxxxxxxxxxxxxxxxxxx"          # Plex Web → item → ⋯ → Get Info → View XML
RK="12345"                            # the item's ratingKey
```

Dump an item's metadata (look for `<Field>` and `<Label>` children):

```bash
curl -s "$PLEX/library/metadata/$RK?X-Plex-Token=$TOKEN" | xmllint --format -
```

### Poster lock

When you **Change poster** or **Send to Plex**, Marquee uploads the image and
sets `thumb.locked=1`. The lock is what stops a Plex agent refresh from replacing
your poster.

1. In Marquee, change the poster (upload an obvious placeholder).
2. Confirm the lock:

   ```bash
   curl -s "$PLEX/library/metadata/$RK?X-Plex-Token=$TOKEN" \
     | grep -o '<Field[^>]*name="thumb"[^>]*>'
   ```

   **Pass:** you see `locked="1"` on the `thumb` field.
3. **Prove it holds:** Plex Web → item → **⋯ → Refresh Metadata**, wait, reload.
   The poster should not revert.

### Kometa "Overlay" label

Kometa tags overlaid items with a Plex label named `Overlay`. With the overlay
toggle on under **Settings → Plex**, Marquee removes that label when it sends a
poster, so Kometa re-applies its overlay to your new poster on the next run.

```bash
# before/after: is the Overlay label present?
curl -s "$PLEX/library/metadata/$RK?X-Plex-Token=$TOKEN" \
  | grep -o '<Label[^>]*tag="Overlay"[^>]*>'
```

| Overlay toggle (**Settings → Plex**) | `Overlay` label after updating in Marquee |
| --- | --- |
| On | Removed |
| Off | Unchanged |

> Env changes require recreating the container (`docker compose up -d`), not just
> restarting it.

Plex library **type numbers** (used internally for label edits): movie = 1,
show = 2, season = 3, collection = 18.

### Plex Posters selects rather than uploads

The **Plex Posters** tab lists posters your server already holds for an item.
Applying one points Plex at it (`PUT …/poster?url=<key>`) and then locks it — it
does **not** upload the image back. Plex never prunes an item's posters, so an
upload here would leave a duplicate behind, and applying the poster already in
use would duplicate it against itself.

The count is the whole test: it must not grow.

```bash
# how many posters does the item have now?
curl -s "$PLEX/library/metadata/$RK/posters?X-Plex-Token=$TOKEN" \
  | grep -c '<Photo'
```

1. Note the count.
2. In Marquee, open **Change poster → Plex Posters** and apply a candidate from
   **Uploaded to Plex** — ideally the one marked **In use**, which is the case
   an upload would handle worst.
3. Re-run the count.

**Pass:** the count is unchanged, `selected="1"` has moved to the poster you
picked, and the `thumb` field is still `locked="1"` (check it the same way as
[Poster lock](#poster-lock) above).

**Fail:** the count grew by one — applying uploaded instead of selecting.

This holds for anything Plex already has the image for: the whole **Uploaded to
Plex** group, and the candidates at the *start* of **Offered by Plex** (held
ones are ordered first). Further down that group are posters Plex has not
downloaded — applying one of those *does* upload, and the count *should* grow by
one. Test against **Uploaded to Plex** for an unambiguous result.

---

### Find Posters sections

The **Find Posters** tab groups its candidates by the service that supplied
each one. Nothing here talks to Plex, so this is a read-only check — but it
needs a live search, because the sections come from the poster source's own
response and no fixture can prove the slugs still match.

Open **Change poster → Find Posters** on two or three items with good coverage
(a well-known film, a long-running show).

| Check | Expected |
| --- | --- |
| Section order | **TMDB**, then **TVDB**, then **fanart.tv**, then **TVmaze** — the same order every time, and the same order as the provider logos in the footer |
| Section headings | Each names its service and carries a count of its own candidates |
| Total | There is none, by design — only per-section counts |
| A service with no artwork for the item | No heading, no empty gap |
| Scrolling a long section | The heading stays pinned while its own posters pass behind it, and leaves with them |
| An **Other** section | Should never appear. If it does, the source has added a provider — see below |
| Applying a candidate | Works identically from any section |

### The v2 endpoint and TVmaze

Marquee calls the poster source's **v2** endpoint, which adds TVmaze as a fourth
service. TVmaze covers **television only**, so the four media types are four
different checks — and two of them pass by showing nothing.

| Search | Expected |
| --- | --- |
| A **show** | A **TVmaze** section, last, typically 14-45 candidates |
| A **season** | A **TVmaze** section holding **one** poster — often artwork no other service has, which is the main reason the service is here |
| A **movie** | **No TVmaze section, and no error or warning.** This is the pass condition |
| A **collection** | Same as a movie — absent and silent |

**A movie showing no TVmaze section is correct, not a bug.** The service reports
`no_data` for every movie and collection, and the endpoint does not mark those
responses partial. If a movie search ever shows a warning line mentioning
missing results, *that* is the finding — it means something started reading the
providers map.

**Every candidate links back to where it came from**, in two places: a corner
badge on the poster in the results grid, and a line under the full-screen
preview. Both open the supplying service's own page for that show, season or
film, in a new tab.

Check all of these:

- Every poster with a source page carries the corner badge — TMDB, TheTVDB,
  fanart.tv and TVmaze alike. **TVmaze posters must never be the only ones
  without it.**
- The badge opens that service's page in a new tab.
- **The badge is plainly visible against every poster**, including bright and
  busy artwork — it reads as a control sitting on top of the image, with its own
  edge, not as part of it. It shipped once too faint to see, which made it a link
  out of the application that users could not deliberately avoid.
- It should not overwhelm the grid either. At ~189 badges in a show search they
  should read as a control on each poster, not as the page's dominant texture.
- Tapping the poster itself still opens the preview — the badge must not swallow
  that press, which is worth checking on a real phone and not only a mouse.
- Opening **any** poster full screen offers a link naming its service. A TMDB
  poster reads "View on TMDB". It must **not** read as a licence notice —
  wording like "attribution required" on a TMDB poster is simply false.
- On a **season**, the TVmaze badge goes to the *season's* page, not the show's.
- Also on a **season**: the fanart.tv poster's link goes to the **series** page
  while TMDB's and TheTVDB's go to the season. fanart.tv has no season page.
  **That disagreement is correct — do not report it as a bug.**

**Not every one of these links is optional, even though they look alike.** TVmaze
artwork is CC BY-SA, and its link back is how the attribution is met — a TVmaze
poster shown without one is the failure here worth blocking a release for. The
rest are provenance, shown because it is useful. The two are told apart in the
code, not on screen: the badge's condition names the marking, and a marked
badge carries `data-attribution-required="true"` in the DOM. So if a future
change makes these links optional or moves them, **the TVmaze ones still have to
be somewhere the poster is shown.**

**An `Other` section is the finding worth reporting.** It means the poster
source returned a `source` slug this build does not recognise, which is the
designed-for outcome of posteria.app adding a provider: the posters still work,
but they sit under a vague heading instead of the service's own name. The fix is
one case added to `App\Poster\Source\PosterProvider` — no client change.

**TVDB depends on service-side configuration.** The **TVDB** section is TheTVDB
— shortened because headings are uppercased and "THETVDB" is unreadable. It only
appears when `TVDB_API_KEY` is set on posteria.app; without it the service
reports that provider as `skipped` and simply returns fewer candidates. An absent
TVDB section is not, on its own, a Marquee bug. **TVmaze** is not shortened, and
the contrast is deliberate: "TVMAZE" is still one readable word uppercased.

---

## The category swipe (real device only)

On a touch device a horizontal drag on the gallery moves between adjacent
categories. **None of this is observable in CI.** `composer test` has no browser,
so the suite can only pin the shape of the source — that a listener is
non-passive, that the tracking loop reads no layout, that the pinned rule
declares no width. Whether a panel actually follows a thumb has to be looked at.

Desktop browsers' touch emulation is **not** a substitute for the first two
checks below. The failure they are aimed at is one that only appears on real iOS.

Order matters here — start with the platform check.

1. **On a real iPhone.** Drag the grid sideways. The grid must start moving
   within the first few millimetres, and the page must not scroll vertically at
   all for the rest of that touch. If the grid does not move but the page does,
   the gesture was claimed too late and the scroller already owns the touch —
   the exact failure that is invisible in every desktop emulator and silent in
   the console.
2. **On a real Android phone.** The same.
3. **Nothing moves vertically.** Watch the top row of posters as you start a
   drag. It must not drop, jump, or twitch — the gesture is horizontal and the
   grid should only ever travel sideways. Check this **both at the top of the
   page and scrolled well down**, because the two causes are different: an
   out-of-flow panel regains a margin its children's margins used to collapse
   out of, and a document that collapses when the panel leaves the flow gets its
   scroll clamped, which drops the sticky toolbar. Both shipped once and both
   were reported as "the grid drops down when you drag".
4. **The pinned chrome stays pinned.** Scroll well down, then start a drag and
   hold your thumb still. The search/sort toolbar must stay stuck to the top of
   the screen and the tab bar to the bottom, exactly as before the drag. A
   toolbar that vanishes upward has stopped sticking — which is what any
   `overflow` on the root element does to `position: sticky`, silently.
5. **The blank space swipes too.** Search for something that matches only two or
   three posters, then swipe in the empty area *below* the grid rather than
   across the posters. It must work exactly the same. That space is not the
   gallery element — it is the page around it — so a gesture bound to the grid
   alone silently does nothing there, which is what shipped first.
6. **Both directions, from every category.** All → Movies → Shows → Seasons →
   Collections and back.
7. **The two ends.** Drag right on All, and left on Collections. Each should move
   a short damped distance and spring back. *Nothing happening at all is a bug* —
   the resistance is what distinguishes "there is nothing there" from "the app
   did not notice your gesture".
8. **Commit, abandon, and change your mind.** Past a third of the screen and
   release: it commits. A short drag: it springs back and the category is
   unchanged. Drag well past a third, drag back below it, then release: it must
   **abandon**. A gesture that commits because it once crossed the line has
   latched, which is the thing this design specifically avoids.
9. **A flick.** A short, fast drag should commit even though it never travelled a
   third of the screen.
10. **With a search active.** Type a search, then swipe. The destination must open
   filtered by the same search — not unfiltered, and not showing the previous
   category's matches.
11. **Straight after a change.** Delete a poster, or run an import, then swipe.
   The destination must reflect what just happened. If it shows the poster you
   deleted, a stale held copy was trusted.
12. **With a tray open.** Open the actions tray, then try to drag sideways over
   it. Nothing must move. Then drag the tray *down* by its handle — it must still
   dismiss normally. The two gestures share the same touches on opposite axes.
13. **Interrupt it.** Start a drag and lock the phone, or switch apps, or rotate
    the device mid-drag. When you come back the page must scroll normally. A page
    that has stopped scrolling with nothing on screen to explain it means the
    gesture was left pinned.
14. **Back and forward.** Swipe through several categories, then use the browser's
    back gesture. It must walk back through them.
15. **Deep scroll.** Scroll several pages into a category, swipe away, swipe back.
    It shows that category's **first page from the top** — that is correct and
    deliberate, not a lost position. Scrolling must still append more.
16. **Reduced motion.** Turn it on at the system level. The grid must **still
    follow your finger** — you are moving it, so it is not motion being done to
    you. Only the travel after you let go should become instant.
17. **On a desktop with a mouse.** Nothing should have changed: no drag, and tab
    clicks still cut instantly.

---

## Orphan detection (real-world test)

An **orphan** is a poster that Marquee imported from Plex whose Plex item no
longer exists — for example, a movie you deleted from your library. This one
isn't in the test script on purpose: it's a workflow test that involves actually
removing something from Plex.

**How Marquee decides:** when you open the **Orphans** page, Marquee asks Plex
for the current items across your imported libraries and flags any *imported*
poster whose Plex item is missing. Three things to know:

- **No poster is exempt.** Every poster arrives through an import and keeps its
  Plex mapping, so a poster you replaced with your own upload can be flagged too
  — changing a poster changes the artwork, not the mapping.
- A library excluded under **Settings → Libraries** is invisible to Marquee, so posters
  imported from it before it was excluded are flagged as orphans too. That is
  another way to reach this screen without deleting anything from Plex.
- Detection is **live** — the Orphans page checks Plex every time you open it, so
  you do **not** need to re-import to see an orphan. (Re-importing won't remove
  orphans either; import only adds/updates items that currently exist in Plex, it
  never prunes. The Orphans page is what removes them.)

### Recommended: a non-destructive test with a Collection

Deleting a Plex **collection** removes no media files, so it's the safe way to
create an orphan on purpose:

1. In Plex, create a temporary collection (e.g. "Orphan Test") with a couple of
   movies in it.
2. In Marquee, **Import from Plex** → choose **Collections** → your movie
   library. Confirm the collection's poster now appears on the **Collections**
   tab.
3. In Plex, **delete that collection** (Plex → the collection → ⋯ → Delete
   Collection). No media is deleted.
4. In Marquee, open **Orphans**. The collection's poster should be listed as an
   orphan. *(No re-import needed.)*
5. Click **Delete all orphans**, confirm the modal, and verify the poster
   disappears from both the Orphans page and the Collections tab.

You can also remove orphans one at a time: each orphan card carries its own
**Download** and **Delete** actions — shown in the hover overlay on a pointer
device, or in the tap-to-open action tray on touch — and deleting one removes
just that orphan while leaving the rest in place.

### With a movie (your example — destructive)

Same flow, but be aware deleting a movie in Plex removes its media files:

1. Import **Movies** so Marquee is tracking the test movie; confirm its poster
   is in the gallery.
2. Remove the movie from Plex so the library no longer contains it — either
   Plex → item → ⋯ → **Delete** (deletes the files), or move the file out of the
   library folder and **Refresh** the library so Plex drops the item. Use a
   throwaway file you don't mind losing.
3. In Marquee, open **Orphans** → the movie's poster is listed.
4. **Delete all orphans** to remove it.

### Expected results

| Step | Expected |
| --- | --- |
| After removing the item from Plex, open Orphans | The item's poster is listed as an orphan |
| A manually-uploaded poster (no Plex link) | Never listed as an orphan |
| Click "Delete all orphans" | Poster removed from disk and gallery; flash "Removed N orphaned poster(s)." |
| Re-import instead of using Orphans | Orphan is **not** removed (import doesn't prune) |

### Troubleshooting

- **Item still not shown as an orphan** → give Plex a moment to finish removing
  it, then reload the Orphans page. Confirm the item is truly gone from Plex
  (search for it). Emptying the Plex library's trash may be required if Plex
  keeps deleted items until trash is emptied.
- **"Marquee must be connected to Plex to detect orphans."** → set
  `PLEX_SERVER_URL`, recreate the container, and sign in to Plex on the
  **Plex Connection** page.

---

## A corrected match (real-world test)

Plex's **Fix Match** keeps an item's rating key but replaces the work behind it:
new title, new year, new external ids, usually new artwork. Marquee's mapping
records what the item *was*, so the next import has to reconcile it — including
renaming the stored poster, because the gallery still *sorts* by the filename.
(Search no longer matches it: a query is matched against the title Plex recorded,
which the same reconciliation corrects.) A poster left under its old name sorts,
in a library of any size, as though the show had vanished.

This can't be exercised without a real Plex server, so it's a workflow test.

1. In Plex, pick a show or movie and use ⋯ → **Fix Match** → **Search options**
   to deliberately match it to the *wrong* title. (Choose something you don't
   mind re-matching — the media files are untouched either way.)
2. In Marquee, **Import from Plex** for that type. The poster arrives under the
   wrong title.
3. In Plex, **Fix Match** again and pick the correct title.
4. In Marquee, run an ordinary import — no need to select **Re-download unchanged
   posters**.

### Expected results

| Step | Expected |
| --- | --- |
| After the corrected import | The poster's caption is the correct title |
| Sort position | Filed under the correct title, not the old one |
| Search for the correct title | Finds the poster |
| Search for the old, wrong title | Finds nothing |
| Poster count | Unchanged — the file is renamed, not duplicated |
| **Find Posters** on that item | Offers the correct work's artwork |

### Worth testing separately: the sets Related posters opens

Each poster records the **set** it belongs to — a season records its show, a film
records its Plex collection — and **Related posters** shows everything sharing it.
Mappings written before that was recorded hold nothing, and they fill in on the
*skip* path, so an established library that downloads no posters at all is
exactly the case that has to work.

A film's collection is not in the library listing, so an import reads each
collection's members. That is one request per collection, and it happens on a
movie import **whether or not you asked for collection posters**.

An **ordinary import is enough** — no "Re-download unchanged posters", nothing to
delete. Sets are recorded on the skip path, so an import that downloads nothing
still fills them in. Import the types you care about: movies gain their sets when
movies are imported, seasons when seasons are. The poster a set is *named* after —
the collection's or the show's — is filled in either way, so a movies-only import
still leaves the collection's poster inside the set its films point at.

On a library imported by an older build, run an ordinary import (no re-download),
then check:

| Check | Expected |
| --- | --- |
| **Related posters** on any season | The show's own poster and every sibling season |
| **Related posters** on a film in a collection | Every other film in that collection, and the collection's poster if it was imported |
| The same, from a different member | The identical set — it must not matter which member you start from |
| A collection whose films share no words (MCU, A24, Ghibli) | Still gathered; this is the case a title search cannot reach |
| **Related posters** on a film in no collection | Falls back to searching that film's title |
| Order of any set | Whatever sort the gallery is in — opening a set must not change the toolbar |
| Change the sort inside a set | The set stays, re-ordered, and the choice is remembered afterwards like any other |
| Switch tab inside a set | The set is carried, exactly as an active search is |
| A film in two collections | The set view names the film and the other collection, and links to it; following the link names the film again |
| A collection whose own poster was never imported | Named on screen rather than "this set" |
| A collection missing a film the library holds | Offers a shorter query with its count — only when the film's title has a subtitle or an instalment number to cut |
| Sort by **Release** | Leads with the newest, and its arrow rests the same way as Date added's — both time fields mean the same thing by a down arrow. Press it again for oldest first, which is how a trilogy reads |
| Import summary | Unchanged — the membership read imports no posters and fails no items |

Before that import, films and seasons fall back to a title search, which is the
expected narrow state and not a failure.

**If it is still searching, do not guess — ask.** A poster with no set and a
poster whose set could not be read look identical from the gallery. This reports
which:

```bash
docker exec -it <container> php /app/www/bin/diagnose-sets.php
docker exec -it <container> php /app/www/bin/diagnose-sets.php "Jackass"
```

It prints the collections Plex reports and how many members each one lists, then
how many stored posters record a set, then the **shape of the library** — how
many rows in each category carry no release year, whether collections carry one
at all, how many posters sit in more than one set, and how many sets have no
naming poster imported — then, with an argument, each matching poster and the set
it holds. It reads only: it imports nothing and changes no poster. A collection
listing `0 member(s)` is the finding; so is a film that Plex puts in no
collection, which is not a fault and is why the title search remains.

The shape section answers the two questions release order and the "Also in" line
were designed around: whether Plex reports a year on a collection, and how many
films sit in several. Both rules are meant to hold either way, so this confirms an
assumption rather than settling one — but it is the cheapest way to find out that
a real library disagrees.

### What a gallery render costs

How many times rendering a view reads the poster mapping, and how long it takes:

```bash
docker exec -it <container> php /app/www/bin/bench-gallery.php
docker exec -it <container> php /app/www/bin/bench-gallery.php 20
```

The argument is the number of iterations to average over. It drives the real
gallery controller rather than a copy of what it does, and reads only.

A view should cost **one read per category it holds** — four for All, one for a
single category — whatever the sort order and whether or not a query or a set is
active. A set view adds one more: the keyed lookup that gives the set its name.
Anything above that is the number to chase, not the milliseconds: wall time
varies by ~10% between runs on the same machine, so only differences larger than
that mean anything.

### Worth testing separately: a locked poster

The case that used to be missed entirely. Before step 3, change the poster in
Marquee (upload anything) and **Send to Plex** so the artwork is locked. Then fix
the match and re-import. Because the artwork didn't change, the import *skips*
the download — but the rename and the corrected details must still happen, and
the import summary should still count the item as **skipped**, not imported. Your
uploaded image must survive unchanged.

### Troubleshooting

- **Poster still under the old title** → confirm Plex actually shows the new
  title (the agent may still be refreshing), then import again.
- **A leftover poster under the old title on the TV Seasons tab** → expected.
  Plex recreates season items on a re-match, so they arrive with new rating keys
  and the old ones become genuine orphans. Clear them from **Orphans**.
