# Marquee

Self-hosted web app for managing custom Plex media posters. PHP 8.4 / Slim 4 /
Twig / SQLite, shipped as a single Docker image.

Detail lives elsewhere — this file is only what's expensive to get wrong:

| For… | Read |
| --- | --- |
| Branch/release flow, OpenSpec commands, toolchain | [docs/development-workflow.md](docs/development-workflow.md) |
| The Docker image, PHP version, local smoke test | [docs/docker.md](docs/docker.md) |
| Live Plex round-trip validation | [docs/testing.md](docs/testing.md) |
| Every seeded variable, its default, and what supersedes it | [docs/configuration.md](docs/configuration.md) |
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
- Configuration comes from the **settings store** (`/config/data/settings.json`),
  read once into typed config objects at bootstrap. The environment seeds that
  store on first start and is never consulted again — a variable still set
  afterwards is reported to the user as superseded, never obeyed. Never call
  `getenv()` deep in the code, and never add a second source for a setting.
  - Three exceptions. The Plex token comes from the connection store written by
    signing in to Plex. `DATA_DIR`, `POSTERS_DIR`, `SESSION_DIR`,
    `DISPLAY_ERRORS`, `UPDATE_REPO`, and `POSTER_SOURCE_URL` stay on the
    environment — the first locates the store itself, and the rest have to work
    when the store does not.
  - **`PLEX_SERVER_URL` is the third, and it is a security control, not a
    structural quirk.** Marquee admits the Plex account that owns the configured
    server, so the address decides who gets in; setting it takes host access,
    and that access is the assertion stopping a stranger claiming a publicly
    reachable install. It is read from the environment on *every* boot — never
    seeded, never a `SettingKey`, never a field on the settings screen, and never
    reported as superseded. Moving it into the store was tried: replacing the
    property cost a first-run claim code, a rate limiter, a probe, and a
    middleware, and was reverted. Don't re-add the case for consistency.
  - Any other new setting means a new `SettingKey` case. Its default lives there;
    its floor or fallback stays in the config object that owns the meaning.
  - **Which variables the docs name is itself under test.** `DATA_DIR` and
    `POSTERS_DIR` are deliberately absent from every user-facing page so that
    "back up `/config`" stays unconditional, and `DISPLAY_ERRORS` lives only in
    `docs/development-workflow.md`. `ConfigurationSurfaceTest` fails both
    directions. Overturn the decision in the `application-shell` spec before
    documenting one of them.
- Posters enter the library **only** through `plex-import`. There is no
  add-a-poster path; uploading a file or URL is a mode of *changing* an
  existing poster (`poster-editing`).
- **An address a user supplied is fetched through `PosterUrlFetcher`, never
  through the shared HTTP client.** That client is deliberately unrestricted
  because `PlexClient` needs it — `PLEX_SERVER_URL` is normally a private
  address — so fetching user input with it would let a stolen session probe the
  LAN from inside. `PosterFetchWiringTest` guards the existing caller, but no
  test can catch a *new* service reaching for the shared client, which is why
  this is written down. There is one such caller today, and adding a second is a
  spec change to `poster-editing`, not a wiring decision.
- **A control is switched off with `aria-disabled`, never the `disabled`
  attribute, and every such binding needs a guard at the action.** The attribute
  drops a control out of the tab order, so a keyboard user is not told it is
  unavailable — they are not told it exists; and disabling a *focused* element
  hands its focus to the document body, which is the common case here, because
  these controls are switched off by the very press that starts their work.
  `aria-disabled` announces and does not enforce: the click still fires,
  `:active` still matches, and a form still submits on Enter. `DisabledStateTest`
  pins the bindings that exist and the CSS that draws them, but it cannot catch a
  *new* control added without its guard — the same shape of hazard as the bullet
  above, and the same reason it is written here. The appearance is stated once on
  `.btn`; don't restyle it per control.

## Docker

**The PHP version comes from the base image tag, not the `php8N-*` extension
packages.** Editing the package names alone doesn't upgrade PHP — it breaks the
build. Any Dockerfile change needs a local build + `/health` smoke test before
pushing, because CI only exercises the image *after* you push.

Both traps, the full PHP-bump checklist, and the smoke-test commands are in
[docs/docker.md](docs/docker.md). Read it before touching the `Dockerfile`.
