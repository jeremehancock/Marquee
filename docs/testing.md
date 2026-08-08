# Testing Marquee against Plex

Four Plex-facing behaviors are worth verifying by hand from time to time:

1. A poster is **locked** in Plex after you update it in Marquee.
2. The **Kometa "Overlay" label** feature (`PLEX_REMOVE_OVERLAY_LABEL`).
3. **Orphan detection** — posters for media that no longer exists in Plex.
4. **A corrected match** — Plex's **Fix Match**, and what the next import does
   with it.

The first two can be checked automatically with the included script
([`scripts/marquee-plex-test.py`](../scripts/marquee-plex-test.py)) or manually
against the Plex API. The last two are real-world workflow tests (change
something in Plex, then check Marquee) — see the final sections.

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
  poster and read its image URL, `/posters/<CATEGORY>/<FILENAME>`.
  `CATEGORY` is one of `movies`, `tv-shows`, `tv-seasons`, `collections`.
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
| `EXPECT_LABEL_REMOVED` | set to match your `PLEX_REMOVE_OVERLAY_LABEL` (`true` = feature enabled) |
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
- The script can't read Marquee's environment, so `EXPECT_LABEL_REMOVED` is how
  you tell it what your `PLEX_REMOVE_OVERLAY_LABEL` is set to. A mismatch is
  reported as a failure with a hint.
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

Kometa tags overlaid items with a Plex label named `Overlay`. With
`PLEX_REMOVE_OVERLAY_LABEL=true`, Marquee removes that label when it sends a
poster, so Kometa re-applies its overlay to your new poster on the next run.

```bash
# before/after: is the Overlay label present?
curl -s "$PLEX/library/metadata/$RK?X-Plex-Token=$TOKEN" \
  | grep -o '<Label[^>]*tag="Overlay"[^>]*>'
```

| `PLEX_REMOVE_OVERLAY_LABEL` | `Overlay` label after updating in Marquee |
| --- | --- |
| `true` | Removed |
| `false` | Unchanged |

> Env changes require recreating the container (`docker compose up -d`), not just
> restarting it.

Plex library **type numbers** (used internally for label edits): movie = 1,
show = 2, season = 3, collection = 18.

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
- A library listed in `EXCLUDED_LIBRARIES` is invisible to Marquee, so posters
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
renaming the stored poster, because the gallery sorts by the filename and search
matches against it. A poster left under its old name reads, in a library of any
size, as the show having vanished.

This can't be exercised without a real Plex server, so it's a workflow test.

1. In Plex, pick a show or movie and use ⋯ → **Fix Match** → **Search options**
   to deliberately match it to the *wrong* title. (Choose something you don't
   mind re-matching — the media files are untouched either way.)
2. In Marquee, **Import from Plex** for that type. The poster arrives under the
   wrong title.
3. In Plex, **Fix Match** again and pick the correct title.
4. In Marquee, run an ordinary import — no need to tick **Re-download unchanged
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
