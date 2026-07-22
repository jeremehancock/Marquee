# Testing Marquee against Plex

Two Plex-facing behaviors are worth verifying by hand from time to time:

1. A poster is **locked** in Plex after you update it in Marquee.
2. The **Kometa "Overlay" label** feature (`PLEX_REMOVE_OVERLAY_LABEL`).

You can check both automatically with the included script
([`scripts/marquee-plex-test.py`](../scripts/marquee-plex-test.py)) or manually
against the Plex API. Everything below reads Plex's own metadata, because that's
the only place the truth actually lives.

> The unit/functional test suite (`composer test`) covers Marquee's internal
> logic. This page is about validating the *live* round-trip to a real Plex
> server — the part tests mock out.

---

## Automated: `scripts/marquee-plex-test.py`

A self-contained tester (Python 3.8+, **standard library only** — no
`pip install`). It logs into Marquee, triggers **Send to Plex** for one poster,
then verifies the result directly in Plex.

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
| `MARQUEE_USER` / `MARQUEE_PASS` | login; leave `MARQUEE_USER` empty if `AUTH_BYPASS=true` |
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
  MARQUEE_URL=http://localhost:1818 MARQUEE_USER=admin MARQUEE_PASS=secret \
  python3 scripts/marquee-plex-test.py
```

It prints a `PASS`/`FAIL` summary and exits non-zero if anything failed (handy in
scripts or a cron sanity check).

### What it does, step by step

- **Preflight** — confirms Plex is reachable, the token is valid, the item
  exists, and it can authenticate to Marquee.
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
