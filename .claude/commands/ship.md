---
name: "Ship"
description: Carry a change from implemented to released - commit, PR, archive, resync - without skipping a step
allowed-tools: Bash(git:*), Bash(gh:*), Bash(openspec:*), Bash(composer:*), Bash(./vendor/bin/*), Read, Edit, Grep, Glob
category: Workflow
tags: [workflow, release, git, openspec]
---

Carry a change the rest of the way: commit, push, archive, PR, and resync `dev`.

This exists so the steps after `/opsx:apply` don't have to be remembered. Run
`/ship` at any point and it works out where things stand and does the next right
thing. Run it again later and it picks up where it left off.

**Do not follow these as a fixed sequence.** Detect the current state first, then
act. A change may arrive here half-done — tasks left, or committed but not
archived, or a PR already merged — and starting from step 1 every time would
redo work or skip what actually matters.

## Step 1 — Establish state

Run these together and read the answers before doing anything:

```bash
openspec list --json                          # active changes + progress
git status --porcelain                        # uncommitted work
git branch --show-current                     # expect: dev
git fetch origin
git log --oneline origin/main..origin/dev     # unpushed/unmerged commits
git log --oneline origin/dev..origin/main     # dev behind main?
gh pr list --state open --json number,title   # PR already open?
cat VERSION && gh release view --json tagName --jq .tagName
```

## Step 2 — Pick the next action from the state

| State | Do this |
| --- | --- |
| Active change, tasks incomplete | Stop. Tell the user to run `/opsx:apply` first — `/ship` does not write code. |
| Uncommitted changes | Run the toolchain gate, then the docs gate (both below), then commit. |
| Commits on `dev` not pushed | Push to `dev`. CI publishes `bozodev/marquee:dev`. |
| Change complete, not archived, `:dev` not yet validated | **Ask the user to test the `:dev` image.** Do not archive before they confirm. |
| `:dev` validated, `VERSION` equals the latest release tag | **Bump `VERSION` (below).** Do this before archiving, so the release ships as one PR. |
| `:dev` validated and `VERSION` bumped, change still active | `openspec archive <change> --yes`, then commit and push the archive. |
| No active change, `dev` ahead of `main`, no open PR | Create the PR (below). |
| PR open, not merged | Stop. Report the URL and wait — merging is the user's call. |
| PR merged, `dev` behind `main` | Resync (below). |
| All refs aligned, no active change | Done. Report the final state. |

## The toolchain gate

Before any commit:

```bash
composer test && composer stan && composer cs
```

All three must pass. If `composer` is missing, fall back to `./vendor/bin/`.
If PHPStan dies on memory, that is a local config problem, not a code problem —
see `docs/development-workflow.md`. Report failures; do not commit around them.

## The docs gate

Before committing, check whether the change makes anything in `README.md` or
`docs/` stale, and fix it in the **same** commit. Docs drift silently: nothing
fails when they fall out of sync, so the only defense is checking every time.

Look at what actually changed across the whole change, not just the last edit:

```bash
git diff --stat origin/main..HEAD; git diff --stat   # committed + uncommitted
```

Then, for each changed area, read the doc that describes it and confirm it still
matches. Common triggers — treat as prompts to go read, not a whitelist:

| A change touching… | Re-read and reconcile… |
| --- | --- |
| `.github/workflows/*` (CI/publish behavior, tags, release flow) | `docs/development-workflow.md` (Branches & tags, Promoting & releasing, Notes) and the README "Docker images" section |
| Environment variables / config surface (`src/**Config**`, bootstrap, compose examples) | README config/env tables and any `docs/` setup steps |
| `composer.json` scripts / quality gates | the toolchain commands in `docs/development-workflow.md` (Part 1, cheat sheet) and README |
| `.claude/commands/*` or the OpenSpec flow | the command tables and mental-model in `docs/development-workflow.md` |
| A new top-level directory or moved layout | the "Repo layout" tree in `docs/development-workflow.md` |
| User-facing features, routes, or behavior | README feature list / usage and any relevant `docs/` page |

Rules:

- If a doc is stale, **update it now** and include it in the commit. Prefer a
  minimal, accurate edit over a rewrite; match the surrounding voice.
- If the docs were already updated as part of the work, confirm they cover the
  final state and move on.
- If nothing user-facing or documented changed (internal refactor, tests only),
  say so explicitly — "docs gate: no user-facing surface changed" — and proceed.
  Do not invent doc changes to look thorough.
- When it is genuinely unclear whether something is worth documenting, ask the
  user rather than guessing either way.

Run this gate again before creating the PR, as a final sweep over the full
`origin/main..dev` diff — later commits (the VERSION bump, the archive) can
themselves change what the docs should say.

## Bumping VERSION

Do this **after** the user has validated `:dev` and **before** archiving, so the
bump, the code, and the archived specs all reach `main` in one PR.

Compare `VERSION` against the latest release tag. If they are equal and `dev`
carries commits `main` does not, the work is unreleased and needs a bump:

```bash
cat VERSION
gh release view --json tagName --jq .tagName
git log --oneline origin/main..dev
```

**Never bump silently, and never pick the number alone.** Use the
**AskUserQuestion tool** to offer patch / minor / major with the current value
and what each would become. Recommend one based on what actually changed — a bug
fix is a patch; a new capability is a minor — but the call is the user's.

Then write the file, run the toolchain, and commit it on its own:

```bash
git commit -m "Version bump"
```

**Do not skip this because the change looks small.** If `VERSION` still matches
the latest tag when the PR merges, CI publishes `:latest` and stops — no pinned
image, no `v<version>` tag, and no GitHub Release. The in-app update check reads
GitHub Releases, so existing users are simply never offered the update. Nothing
errors; the release just quietly doesn't happen.

If the user says the change should not be released at all — internal tooling,
docs, a follow-up to something already shipped — that is a legitimate answer.
Skip the bump, and say plainly in the PR that merging refreshes `:latest` only.

## Creating the PR

Never open a PR while an OpenSpec change is still active — the archive commit
belongs in the same PR, or `main` ends up with the code while its specs still
describe the old behavior.

```bash
gh pr create --base main --head dev --title "<subject>" --body-file <file>
```

Write the body to a scratchpad file rather than inline. Cover: why (the problem,
not the patch), what changed, how it was verified, and the release impact.

**State the release impact explicitly.** Compare `VERSION` against the latest
release tag:

- `VERSION` newer than the latest tag → merging publishes `:latest`, the pinned
  `:<version>`, a `v<version>` tag, and a GitHub Release.
- `VERSION` equals the latest tag → merging refreshes `:latest` only.

## Resyncing after a merge

```bash
git checkout dev && git fetch origin && git merge --ff-only origin/main && git push
git fetch origin main:main    # refresh the local main ref without checking it out
```

Merge `origin/main`, not `main` — the local branch is stale because this workflow
never checks it out, and merging it reports "Already up to date." while syncing
nothing.

If `--ff-only` aborts, `dev` has commits `main` lacks. Do not force it. Find out
what they are and say so.

## Guardrails

- **Never merge a PR.** Open it, report the URL, stop. Merging is the user's.
- **Never archive before the user has validated `:dev`.** Archiving rewrites
  `openspec/specs/`, which is the source of truth.
- **Never commit with a failing toolchain.** Report and stop.
- **Never commit a documented behavior change with stale docs.** Run the docs
  gate; fix `README.md`/`docs/` in the same commit, or state plainly that no
  documented surface changed.
- **Never skip the archive** to get a PR out faster. Code and specs ship together.
- **Never open a release PR without checking `VERSION`.** Equal to the latest tag
  means no release will be cut. Say so explicitly, and confirm that is intended.
- If the state is ambiguous, say what is ambiguous and ask. Do not guess.

## Finish

Report: what was done, what is still outstanding, and the one thing the user
needs to do next. If everything is aligned, show the final state — refs, VERSION
versus latest release, spec validation, open PRs.
