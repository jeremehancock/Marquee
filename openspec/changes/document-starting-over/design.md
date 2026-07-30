## Context

Marquee inherits a user expectation from Posteria, the monolithic app it
replaces: when the poster library goes wrong, you reset it and re-import. That
expectation made sense there, because Posteria kept no item→file mapping — a
wrong poster on disk could not be corrected in place, so nuking the directory was
the only lever.

Marquee's state is two stores under `/config`, read by different code paths:

```
  gallery  ──reads──▶  /config/posters/<category>/    (FilesystemPosterStorage)
  orphans  ──reads──▶  /config/data/marquee.sqlite    (plex_items, plex_libraries)
```

Between them, the existing remedies already cover the realistic failures:

| Symptom | Existing remedy |
| --- | --- |
| Wrong or stale poster image for an item | Import with **Re-download unchanged posters** (`Force a full re-import`) |
| Poster deleted in Marquee, want it back | Plain import |
| Media removed from Plex, poster left behind | **Orphans** |
| Library excluded after import | **Orphans** (already documented in the FAQ) |

What is left over is narrow: replacing the Plex server (every rating key
changes), a library deleted and recreated in Plex, on-disk filenames that drift
after a rename because imports replace in place, and poster files a user placed
into the volume by hand, which the gallery lists from disk but orphan detection —
which iterates database rows — cannot see.

None of that is documented anywhere, so a confused user's first instinct is to
delete things in `/config` and guess at which ones.

## Goals / Non-Goals

**Goals:**

- Point users at the in-app remedy that fits their symptom, before they consider
  destroying anything.
- Make the full reset safe to perform by hand, including the SQLite sidecar files
  a naive instruction leaves behind.
- State the recovery boundary — what a reset costs — so the choice is informed.
- Record the invariant that makes the documented reset safe, so a future change
  cannot silently invalidate the documentation.

**Non-Goals:**

- An in-app Reset button, route, service, or confirmation UI.
- Any migration, cleanup, or repair tooling for the leftover cases above.
- Changing import, orphan detection, or storage behavior in any way.

## Decisions

### Documentation instead of a Reset feature

Rejected: a menu entry that deletes every poster and clears both tables behind a
confirmation.

The Posteria-era justification does not survive the comparison table above. What
remains is a permanent, irreversible, one-click destroy button whose realistic
audience is someone replacing their Plex server — and even that case is served,
if awkwardly, by Orphans. Against that: a new route, service, confirmation UI,
spec, and tests, plus a standing risk that a misclick destroys locally-only
artwork that no import can restore.

The genuine gap is that users do not know `Force a full re-import` and Orphans
exist or which one to reach for. That is a documentation gap, and documentation
is where it gets fixed.

### FAQ entry, not a new README section

Rejected: a top-level "Resetting Marquee" section.

A dedicated section advertises the destructive path as the primary answer, which
inverts the intent — most readers arriving with this problem should leave having
run a force re-import. Placing it in the FAQ after **"I excluded a library I'd
already imported. What happens to its posters?"** keeps the orphan context
already established immediately above it, and frames the answer as
troubleshooting rather than as a documented workflow.

### `data/marquee.sqlite*` with the glob, not the bare filename

SQLite runs in WAL mode (`Database::pdo` sets `PRAGMA journal_mode = WAL`), so
`marquee.sqlite-wal` and `marquee.sqlite-shm` sit beside the database file.
Deleting only the named file leaves those behind, and the result is a partial
reset that is more confusing than the original problem. The glob removes all
three without spending a paragraph explaining WAL to a reader who does not care.

Rejected: naming all three files explicitly — accurate, but it reads as a warning
that this is a delicate operation, when in practice it is not.

Instructing the user to stop the container first is what makes this safe at all:
it guarantees no writer holds the database while its files are removed.

### A spec requirement on a documentation-only change

The FAQ makes a durable promise — delete these paths and Marquee comes back
clean. That promise rests on behavior nothing currently specifies: schema
migration is idempotent and runs on every boot, and a missing category directory
is treated as empty and recreated on write. Both are true today by construction,
not by contract.

`application-shell` is the right home; it already owns bootstrap and typed
configuration, which is where the data and posters directories are established.
The requirement is written to constrain future work as much as to describe
current behavior: it forbids persisting state that cannot be rebuilt from Plex,
which is precisely what would make the documented reset lossy.

## Risks / Trade-offs

**Users reach for the reset instructions before the in-app remedies** → The
entry is ordered so both remedies come first and the reset arrives as a last
resort, and it names what the reset costs. Ordering is the mitigation; nothing
enforces it.

**Locally-only artwork is destroyed by a documented procedure** → The entry
states the boundary explicitly: art sent to Plex returns on import, art that only
ever existed in Marquee does not. This is consistent with the existing FAQ answer
that Marquee is not a backup.

**Instructions drift if the `/config` layout changes** → The new
`application-shell` requirement ties the layout's disposability to a spec, so a
change touching it has to confront the documented promise rather than pass it by.

**The narrow cases stay unaddressed** — untracked files, filename drift after a
rename, a swapped Plex server → Accepted. Each is rare and has a manual path
(per-poster delete, Orphans, or the documented reset). Revisit only if real
reports accumulate; this change deliberately does not build for them in advance.
