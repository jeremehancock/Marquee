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
- **A Find Posters candidate's `page` and its `attribution_required` are two
  different facts, and only the second compels anything.** `page` is where the
  poster came from — nearly every candidate has one, and showing it is a product
  decision. `attribution_required` marks the few whose licence obliges that link
  to be rendered wherever the poster is; TVmaze is CC BY-SA and is the only
  service marked today. The grid badge is shown for **either** reason — its
  condition is `poster.attributionRequired || poster.page`, in that order — and
  the two are drawn identically, because the licence asks that the link be shown,
  not that it be shown differently. **The first clause is not redundant.** It is
  what survives if the provenance badge is ever dropped as a product decision;
  reducing the condition to `poster.page` renders identically today and silently
  discards the one credit that may never go. Never key it on
  `source == "tvmaze"` either — that loses the credit the moment a second source
  is licensed the same way, which is why `source` is deliberately absent from the
  find-posters payload: publishing it is the first step of writing the check the
  wrong way. A marked badge also carries `data-attribution-required="true"`, so
  the distinction exists somewhere findable when the pixels are identical.
  `PosterCreditLinkTest` pins the condition's shape and clause order and asserts
  no provider name appears in the markup — but it cannot catch a *new* surface
  that shows a marked candidate without its credit. Same hazard shape as the
  bullet above, same reason it is here. Adding a third such surface means
  rendering the credit there too, not deciding it does not apply.
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
  - **A switched-off control never carries its reason in a tooltip.** Tooltips
    are hover-and-fine-pointer only by design, so a reason attached to one is a
    reason no touch user ever receives — a dimmed control and silence. This was
    the Plex Posters tab for a year: switched off for a poster with no Plex item,
    explaining itself in a `data-tooltip` and in a panel the off tab would not
    open. The fix was not a second channel but the realisation that the tab was
    never unavailable — it was **empty**, like a search that matched nothing, so
    it opens and says so. Prefer that reading before reaching for a way to
    explain a refusal: a control with nothing to offer and a reason to give is
    usually an empty destination, not a dead one. `DisabledStateTest` fails any
    control that binds both `aria-disabled` and `data-tooltip`, but it cannot
    catch a control that explains itself nowhere at all.
- **An overlay is managed for focus because it declares `role="dialog"` and
  `tabindex="-1"`, and nothing else makes it so.** The focus manager in
  `gallery.js` finds its subjects by that attribute — deliberately, so an overlay
  injected into a tray at runtime is managed and a registry never has to be kept.
  The cost is that an overlay added *without* the role is not managed and looks
  no different: it opens, and a keyboard user is left on the page behind the
  backdrop with no way in. `DialogFocusTest` fails a declared dialog missing its
  `tabindex`, and pins the manager against being rewritten around a list of panel
  classes — but it cannot catch a new overlay that declares nothing at all. Same
  hazard shape as the two bullets above, same reason it is written here.
  - The fullscreen poster viewer declares no role **on purpose** — it holds
    nothing focusable, so there is nowhere to put focus. A test pins that. It is
    not an invitation to leave the attributes off the next one.
  - The page behind an open overlay is not `inert`, and that is not an oversight
    either: every overlay but the teleported actions tray is a *descendant* of
    the content it covers, so there is nothing to mark. `aria-modal="true"`
    carries it. Adding `inert` means teleporting the overlays to `<body>` first —
    a change, not a wiring decision.
  - **A control that closes its own menu and opens an overlay has to move focus
    to something still on screen before the menu hides.** Alpine hides on the
    flush *after* the handler, and hiding a focused element hands its focus to
    `<body>` — which the manager reads a frame later to record where to put focus
    back, and an origin chain rooted at the body is the one case it declines to
    restore, that being what a touch tap leaves behind. So the overlay opens
    correctly, focus lands in it correctly, and dismissing it drops the keyboard
    user at the top of the page. Nothing errors, and the pointer path looks
    perfect. Both menus that do this today — the desktop ⋯ panel and the phone
    actions tray — call `$refs.<their>Trigger.focus()` first, and
    `DialogFocusTest` pins both along with the refs they name. It cannot catch a
    third menu wired without it, which is the same hazard shape as the bullet
    above and the same reason this is here.
- **The category swipe refuses a touch by naming the surfaces it must not claim,
  and changes category through exactly one routine.** A horizontal drag on the
  gallery moves between adjacent categories; it pins both grids out of the
  document's scroller and suppresses the browser's own handling for the life of
  the touch. Two consequences are worth knowing before touching either area.
  - **The gesture listens on the whole `document`, and that is why the refusal
    list is the mechanism rather than a convenience.** Bound to the gallery root
    it only covered as much of the screen as the grid happened to fill, so a
    swipe in the blank space under a short search result did nothing. Listening
    page-wide fixes that and puts every bar under the same listener, so each one
    keeps its touches only by being named: `swipeRefused()` lists `.sheet`,
    `.modal`, `.viewer`, `.overlay`, `.tabs`, `.toolbar` and `.topbar`. A **new
    overlay or bar added without an entry there** is claimable — the drag starts
    under the viewer's finger while a tray is open, pins the page, and they get
    an overlay that will not scroll and a gallery sliding behind it. Nothing
    errors. `TabSwipeTest` pins the entries that exist, that the check runs at
    `touchstart`, and that the surface stays `document` — it cannot know about a
    class invented later. The refusal shares `anyOverlayOpen()` with the page
    scroll lock deliberately; a second reading of "is an overlay open" drifts,
    and the two gestures live on opposite axes of the same touches.
  - `switchCategory()` is the **only** way to change category, used by the tab
    tap and the swipe's commit alike. It owes seven things — the active tab, the
    results, the title, a history entry, the carried-over search, the scroll
    position, and infinite scroll re-armed — and two paths that must agree about
    seven things will stop agreeing. A test pins that the commit calls it and
    that only one definition exists, but not that a *third* caller is added
    correctly.
  - The neighbour cache is an optimisation and must stay one: the gesture is
    fully correct with it permanently empty. Never let a held copy become the
    only record of anything, and never add a `sort` key to it — sort changes do a
    full page load, which takes the whole cache with it. The **set** is a
    different case and is keyed: a set changes without a page load (a tab tap
    carries it), so a copy held from before one was opened is the whole
    unfiltered category — a wrong library that looks like a working one. The rule
    is not "never add keys", it is "key on what changes without a reload".
- **A surface the interface offers *by name* is Title Case everywhere it is
  named; everything else a user reads is sentence case.** The named surfaces are
  Poster Wall, Import from Plex, Plex Connection, Plex Posters, Find Posters and
  Support Development — one string each, in the nav entry, the tray or dialog
  title, the page heading, the document title and the accessible name alike.
  Everything else is sentence case: actions (Change poster, Send to Plex, Related
  posters, Save settings), confirmation titles (Delete poster?), form labels,
  section headings (Presentation, Auto-import), positional names (First page, More
  actions, Sort order). **A name and a description of the same destination are
  both correct and are not a divergence** — nav says "Orphans", the page it opens
  is headed "Orphaned posters", and each is used consistently in its own
  register. A name inside a sentence keeps its Title Case; this governs naming,
  not prose.
  - The reason this holds almost everywhere is structural, not careful: every
    label drawn at both widths is emitted once — nav entries from `item()`, sort
    buttons from `control()`, card actions from `action_body()`, whose markup the
    touch sheet *clones* rather than re-renders — so a mobile/desktop divergence
    there is unrepresentable. Keep it that way; that is the real defence.
  - Support Development is the one name stated in two files, because the overlay
    is `_support.html.twig` and the entry that opens it is a call in
    `_nav_macros.html.twig`. It drifted for exactly that reason — the overlay read
    "Support development" one gesture after the entry read "Support Development".
    `ApplicationShellTest` now compares the entry's label, the panel's
    `aria-label` and the `<h2>` **to each other**, locating the entry by
    `supportOpen = true` rather than by its name; asserting the literal on both
    sides is the arrangement that let them drift. It cannot catch a new surface
    named nothing like its opener, or a name cased in the wrong register to begin
    with — same hazard shape as the bullets above, same reason this is written
    down.

## Docker

**The PHP version comes from the base image tag, not the `php8N-*` extension
packages.** Editing the package names alone doesn't upgrade PHP — it breaks the
build. Any Dockerfile change needs a local build + `/health` smoke test before
pushing, because CI only exercises the image *after* you push.

Both traps, the full PHP-bump checklist, and the smoke-test commands are in
[docs/docker.md](docs/docker.md). Read it before touching the `Dockerfile`.
