## Context

Four changes moved Marquee's configuration out of the compose file and into a
settings store plus a Settings screen. The code landed; the front door did not
follow. `README.md` still opens with a compose block that sets seventeen
variables the store now owns, and follows it with a table of twenty-three
variables presented as *the* way to configure Marquee.

None of that is false — `SettingsSeeder` really does import all of it on the
first boot that finds no store. It is misleading, which is worse in a README
than being wrong, because the reader has no way to tell. Someone who follows the
Quick start literally gets an install that works and a Settings screen telling
them to go delete most of what they just wrote.

There is no `docker-compose.yml` in this repository. The "compose file with the
old settings in it" is the fenced example in the README, plus a smaller one in
`docs/development-workflow.md`. Editing the README *is* editing the compose file
users have.

Constraints:

- Seeding is real, supported, and specified. This change must not imply it was
  removed.
- `PLEX_SERVER_URL` stays in the compose example and stays out of the Settings
  screen. It is a security control, and the README already explains why at
  length; that explanation stays.
- Docs are a commit-time gate in this project, so the README audit is part of
  the same commit rather than a follow-up.

## Goals / Non-Goals

**Goals:**

- The Quick start compose block contains only what an install needs
  permanently, so copying it verbatim produces zero superseded-variable notices.
- The README's configuration story is "open Settings", with the environment as a
  footnote for the two or three cases that still need it.
- Seeding stays documented, in full, somewhere a reader who wants it will find
  it.
- Every remaining factual claim in the README matches the code and the specs.

**Non-Goals:**

- No code, template, or test changes. No variable is retired or deprecated.
- Not rewriting the README's voice, structure, or the sections unrelated to
  configuration (Features, poster-source FAQs, Security considerations).
- Not touching the superseded-variable reporting or its wording in the app.
- Not adding a `docker-compose.yml` to the repo. The README example stays the
  canonical one; a checked-in file would be a second thing to keep in sync.

## Decisions

### The seed-once table moves to `docs/configuration.md`, not into a `<details>` block

The table has two audiences with opposite needs. Almost every reader should
never see it; a small number pre-configuring a fleet want all of it. A collapsed
block in the README serves the second audience while still telling the first
that Marquee has twenty-three environment variables — the impression this change
exists to remove.

A separate page also gives the material room to explain *when* seeding applies,
which is the part people get wrong. The README links to it from one sentence.

Alternatives considered:

- **Collapsed `<details>` in the README.** One file, no new page. Rejected: the
  summary line still advertises the variables, and GitHub renders the block
  inline in the reading flow.
- **Delete the table.** Simplest. Rejected: seeding is a real supported path,
  and an undocumented supported path becomes an issue tracker question.

### The README keeps a short table, not zero tables

Six variables are never settings and never will be: `PUID`, `PGID`, `TZ`,
`PLEX_SERVER_URL`, `SESSION_DIR`, and the `/config` path overrides (`DATA_DIR`,
`POSTERS_DIR`). A reader needs these and should not be sent to another page for
`TZ`. Keeping a short table also makes the contrast do the explaining: this is
the environment's whole job now.

`DISPLAY_ERRORS`, `UPDATE_REPO`, and `POSTER_SOURCE_URL` are development
overrides rather than things an install is offered — they belong in
`docs/configuration.md`, not the README table. The README's current claim that
"four things are exempt" is wrong in both directions and gets rewritten rather
than patched.

### The spec delta is a documentation requirement on `settings`

A doc-only change with no spec delta would archive into nothing, and the README
would drift back the next time someone adds a `SettingKey` — which is exactly
how it got here. The `settings` capability already owns seeding and superseded
reporting; the documented install path is the user-facing half of that contract,
so the requirement belongs there.

The requirement is written so it can be checked by reading the README: the
compose example contains no variable that appears in `SettingKey`. That is a
grep, not a judgment call.

Alternative considered: put it under `release-publishing`. Rejected — that
capability is about branch-to-tag mechanics, not what an install is told.

### The audit is scoped by "does the code disagree", not by "could this be better"

The README is long and the temptation is to rewrite. The audit list is fixed in
`tasks.md` and each item names the file to check the claim against. Anything
that is merely wordy stays as it is; this change is about accuracy.

Known drift found while proposing:

| README says | Code / spec says |
| --- | --- |
| "Four things are exempt" then lists five, omitting the dev overrides | `SettingKey` doc block: `DATA_DIR`, `POSTERS_DIR`, `SESSION_DIR`, `DISPLAY_ERRORS`, `UPDATE_REPO`, `POSTER_SOURCE_URL`, plus `PLEX_SERVER_URL` for a different reason |
| Un-excluding a library means editing `EXCLUDED_LIBRARIES` and restarting | Settings screen, tick box, no restart |
| Updating section leans on `UPDATE_CHECK_ENABLED` being "on" in compose | It is a Settings toggle |
| `DEFAULT_SORT` takes two values | `SortOrder` has four slugs, including the two descending variants |

Corrected while implementing: an earlier draft of this table claimed the
README's "apply on the next page you load" contradicted the spec's "in effect
immediately". It does not — `settings.html.twig` says exactly what the README
says, and the two describe the same behavior from different ends (stored at
once, visible on the next render). That wording stays.

## Risks / Trade-offs

- **A reader with an existing compose file sees the trimmed example and thinks
  their variables broke.** → The README's superseded paragraph stays and gets
  clearer: existing variables are ignored, not fatal, and the Settings screen
  lists which to delete. `docs/configuration.md` says the same at the top.
- **Splitting docs across two files means two things to keep in sync.** →
  Mitigated by the split being by lifetime, not by topic: the README table is
  variables that can never move, the docs page is variables that already have.
  A new `SettingKey` only ever touches the docs page.
- **The audit turns up drift that is really a code bug, not a doc bug.** → Out
  of scope by design. Record it in the change's task list as a note and propose
  it separately rather than widening this change.
- **`docs/configuration.md` duplicates what the Settings screen already says.** →
  Accepted. The page is read before an install exists, when the screen is not
  reachable.
