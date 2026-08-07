# Marquee

Self-hosted web app for managing custom Plex media posters. PHP 8.4 / Slim 4 /
Twig / SQLite, shipped as a single Docker image.

Detail lives elsewhere — this file is only what's expensive to get wrong:

| For… | Read |
| --- | --- |
| Branch/release flow, OpenSpec commands, toolchain | [docs/development-workflow.md](docs/development-workflow.md) |
| The Docker image, PHP version, local smoke test | [docs/docker.md](docs/docker.md) |
| Live Plex round-trip validation | [docs/testing.md](docs/testing.md) |
| Project context + the capability map | `openspec/config.yaml` |
| The release state machine and its guardrails | `.claude/commands/ship.md` |

## Workflow: spec-driven, not test-driven

Every capability is defined by an OpenSpec spec **before** it is built. Don't
start writing code from a bare description.

```
/opsx:explore → /opsx:propose → (you review) → /opsx:apply → /ship → /opsx:archive
```

- A change MUST target an existing capability from the map in
  `openspec/config.yaml` — never invent a capability named after the change.
- Run `openspec validate <change> --strict` before coding. The usual failure is
  a scenario not using exactly four `#` (`#### Scenario:`).
- Tests are written alongside the implementation and verified at the end of a
  change, not first. Implementation tasks precede their test tasks.

## `/ship` owns everything after `/opsx:apply`

Commit, push, VERSION bump, archive, PR, resync. **Use it — don't hand-roll
those steps.** It detects the current state and does the next right thing, so
it's safe to run at any point and again later. Running the steps manually
bypasses its guardrails, which is the main way this project gets broken.

`/ship` does not write code — if tasks are incomplete it stops and sends you
back to `/opsx:apply`. It never merges a PR, and never bumps `VERSION` without
asking. `ship.md` is the authority on all of that; don't second-guess it from
here.

Two rules that hold **even when you aren't running `/ship`**:

- **Never archive before the user has validated the `:dev` image.** Archiving
  rewrites `openspec/specs/`, the source of truth.
- **Code and specs ship together** — the archive commit belongs in the same PR,
  or `main` gets the code while its specs describe the old behavior.

## Gates — both run before any commit

**Toolchain.** All three must pass; never commit around a failure.

```bash
composer test     # PHPUnit 11
composer stan     # PHPStan level 10 (max) over src/ and tests/
composer cs       # PHP-CS-Fixer, dry-run  (composer cs:fix to apply)
```

CI runs exactly these on every push. A red CI publishes nothing.

**Docs.** Check whether the change makes `README.md`, `docs/`, or this file
stale, and fix it in the **same** commit. Docs drift silently — nothing fails
when they fall out of sync, so checking every time is the only defense. If
nothing user-facing changed, say so explicitly rather than inventing edits.

## Branches

Work on **`dev`**. Never commit directly to `main` — it's release-only, and PRs
are merged on GitHub, so the local `main` ref is usually stale (use
`git fetch origin main:main` before comparing).

`VERSION` is load-bearing: it drives the pinned image tag, the git tag, and the
GitHub Release that powers the in-app update notice. Don't edit it outside
`/ship`.

## Code conventions

- `declare(strict_types=1);` in every PHP file. PSR-12, enforced by
  PHP-CS-Fixer (short arrays, single quotes, alphabetised imports, no unused
  imports, trailing commas in multiline).
- Thin controllers → service classes → value objects. No business logic in
  Twig templates or the front controller.
- All configuration comes from environment variables, read once into typed
  config objects at bootstrap. Never call `getenv()` deep in the code. The one
  exception is the Plex token, which comes from the connection store written by
  signing in to Plex and is **never** read from the environment — `PLEX_TOKEN`
  is read only to tell the user it is obsolete. Resolution still happens once at
  bootstrap.
- Posters enter the library **only** through `plex-import`. There is no
  add-a-poster path; uploading a file or URL is a mode of *changing* an
  existing poster (`poster-editing`).

## Docker

**The PHP version comes from the base image tag, not the `php8N-*` extension
packages.** Editing the package names alone doesn't upgrade PHP — it breaks the
build. Any Dockerfile change needs a local build + `/health` smoke test before
pushing, because CI only exercises the image *after* you push.

Both traps, the full PHP-bump checklist, and the smoke-test commands are in
[docs/docker.md](docs/docker.md). Read it before touching the `Dockerfile`.
