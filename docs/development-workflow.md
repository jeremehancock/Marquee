# Development workflow — VSCodium, Claude Code & OpenSpec

How to develop Marquee in VSCodium with the Claude Code extension and the
OpenSpec spec-driven workflow, using the **`dev` branch for feature work and
testing** and **`main` for releases**.

---

## Branches & Docker image tags

CI publishes to Docker Hub automatically (see
`.github/workflows/docker-publish.yml`):

| You push to… | Docker Hub tag | Use for |
| --- | --- | --- |
| `dev` | `bozodev/marquee:dev` | testing new work on a throwaway/staging instance |
| `main` | `bozodev/marquee:latest` | production (always the latest) |
| a `v*` git tag | `bozodev/marquee:<version>` **and** `:latest` | pinned releases |

On a tag, the **version string is read from the repo's `VERSION` file** — the tag
name is just the trigger. Every build also gets an immutable `sha-<short>` tag.

**The loop:** build a feature on a branch off `dev` → merge into `dev` (CI
publishes `:dev`) → test the `:dev` image → open a PR from `dev` into `main`
(publishes `:latest`) → cut a versioned release by bumping `VERSION` and pushing
a tag (publishes `:<version>` and refreshes `:latest`). See
[Cutting a release](#cutting-a-release).

### A separate instance for testing `:dev`

Run a second container from the `:dev` tag, on its own port and its own
`/config`, so testing never touches your real posters:

```yaml
services:
  marquee-dev:
    image: bozodev/marquee:dev
    container_name: marquee-dev
    ports:
      - "1819:80"
    environment:
      PUID: "1000"
      PGID: "1000"
      TZ: "Etc/UTC"
      AUTH_BYPASS: "true"          # convenient on a trusted LAN for testing
      PLEX_SERVER_URL: "http://192.168.1.10:32400"
      PLEX_TOKEN: "your-plex-token"
    volumes:
      - ./marquee-dev/config:/config
    restart: unless-stopped
```

Pull the newest dev build and restart with:

```bash
docker compose pull marquee-dev && docker compose up -d marquee-dev
```

---

## Part 1 — Set up VSCodium + Claude Code

### 1. Install the pieces

- **VSCodium** — https://vscodium.com/
- **Node.js 18+** — for the Claude Code and OpenSpec CLIs. https://nodejs.org/
- **Git**

### 2. Install the Claude Code CLI

```bash
npm install -g @anthropic-ai/claude-code
claude          # first run walks you through signing in
```

The CLI is the engine; everything below works from a terminal even if the editor
extension never installs.

### 3. Open the project and run Claude Code inside it

1. VSCodium → **File → Open Folder…** → your `Marquee` checkout.
2. **Terminal → New Terminal**.
3. Run `claude`.

Running `claude` from VSCodium's integrated terminal is the reliable path: it
picks up the repo, the `.claude/` commands, and OpenSpec context automatically.

### 4. (Optional) The Claude Code editor extension

The extension adds inline diffs and the ability to share your current
selection/file as context.

- When you run `claude` in a supported editor's integrated terminal, it offers to
  install the companion extension; accept it, then use `/ide` inside Claude to
  connect.
- **VSCodium caveat:** VSCodium uses the Open VSX registry, not Microsoft's
  Marketplace, so auto-install may be blocked. If it is: try installing "Claude
  Code" from Open VSX, install the bundled `.vsix` manually (Extensions →
  **⋯ → Install from VSIX…**), or just keep using the terminal — you lose only
  the inline-diff UI, not any capability. (Open VSX availability changes over
  time; the terminal workflow is the dependable fallback.)

### 5. Sanity-check the toolchain

```bash
composer install
composer test           # PHPUnit
composer stan           # PHPStan (level 8)
composer cs             # PHP-CS-Fixer (dry-run)
```

Keep these green — CI runs the same on every push.

---

## Part 2 — OpenSpec in this repo

### What's already set up

- **`openspec/`** — `config.yaml` holds the project context and per-artifact
  rules that guide AI-generated specs. `openspec/specs/` is the source of truth
  once changes are archived; `openspec/changes/` holds in-flight proposals.
- **`.claude/commands/opsx/`** — the OpenSpec slash commands, committed to the
  repo, so Claude Code has them the moment you open the folder: `/opsx:explore`,
  `/opsx:propose`, `/opsx:apply`, `/opsx:update`, `/opsx:sync`, `/opsx:archive`.

### Install the OpenSpec CLI

```bash
npm install -g @fission-ai/openspec
openspec --version      # this repo was built against 1.6.0
```

### The mental model

```
idea ─▶ /opsx:explore ─▶ /opsx:propose ─▶ (review) ─▶ /opsx:apply ─▶ commit/PR ─▶ /opsx:archive
             (think)        (write spec)      (you)      (build code)              (fold into specs)
```

A **change** lives under `openspec/changes/<name>/` and contains `proposal.md`
(why/what), `design.md` (how), `tasks.md` (checklist), and
`specs/<capability>/spec.md` (the delta — `## ADDED/MODIFIED/REMOVED
Requirements`, each with `### Requirement:` and `#### Scenario:` using **exactly
four** `#`). Archiving folds those deltas into `openspec/specs/`.

### The slash commands

| Command | Use it to… |
| --- | --- |
| `/opsx:explore` | Think through an idea before committing to a change. No code. |
| `/opsx:propose` | Create a new change and generate all its artifacts. |
| `/opsx:apply` | Implement the tasks from a change (writes code). |
| `/opsx:update` | Revise an existing change's artifacts and keep them coherent. |
| `/opsx:sync` | Sync a change's delta specs into the main specs without archiving. |
| `/opsx:archive` | Finalize a completed change and fold its deltas into the specs. |

### The raw CLI (alongside the slash commands)

```bash
openspec list                       # active changes + task progress
openspec show <change>              # view a change
openspec validate <change> --strict # verify structure (spec deltas, scenarios)
openspec archive <change>           # archive an implemented change
openspec --help
```

Always run `openspec validate <change> --strict` before coding — it catches
malformed spec deltas (most commonly scenarios that don't use exactly four `#`).

---

## Part 3 — End-to-end: a feature on the `dev` branch

Say you want to add "export/import a backup of all posters."

1. **Start from `dev`:**

   ```bash
   git checkout dev && git pull
   git checkout -b feat/poster-backup
   ```

2. **Explore** (optional, for anything non-trivial):

   ```
   /opsx:explore  I want to back up and restore all posters and their Plex mappings
   ```

3. **Propose** the change and review the generated artifacts (tweak with
   `/opsx:update`):

   ```
   /opsx:propose  poster-backup
   ```

4. **Validate:**

   ```bash
   openspec validate poster-backup --strict
   ```

5. **Apply** (write the code), then run the gates:

   ```
   /opsx:apply  poster-backup
   ```
   ```bash
   composer test && composer stan && composer cs
   ```

6. **Commit and open a PR into `dev`:**

   ```bash
   git add -A && git commit -m "Add poster backup/restore"
   git push -u origin feat/poster-backup
   # open PR: base = dev
   ```

7. **Test the `:dev` image.** Once merged into `dev`, CI publishes
   `bozodev/marquee:dev`; pull it on your test instance and try it for real.

8. **Release:** when it's solid, open a PR from `dev` → `main`. Merging publishes
   `bozodev/marquee:latest`.

9. **Archive** the change so the specs reflect reality:

   ```
   /opsx:archive  poster-backup
   ```

> If you prefer, you can commit directly to `dev` instead of using per-feature
> branches — the important boundary is `dev` (testing, `:dev`) vs `main`
> (release, `:latest`).

---

## Cutting a release

`main` is always `:latest`. To also publish a **versioned** image, bump the
`VERSION` file and push a git tag — the workflow reads the version from that file
and publishes `bozodev/marquee:<version>` **and** refreshes `:latest`.

1. Make sure the code you want to ship is merged into `main` (via a `dev → main`
   PR).
2. On `main`, set the new version in the `VERSION` file (this is also what the
   app shows in its footer). Commit it:

   ```bash
   git checkout main && git pull
   echo "1.0.0" > VERSION
   git commit -am "Release 1.0.0"
   git push
   ```

3. Publish a **GitHub Release** for that commit (Releases → Draft a new release →
   create tag `v1.0.0` → Publish). This both triggers the Docker build and powers
   Marquee's in-app "Update available" notice (which checks GitHub Releases).

   Or just push a tag from the CLI (builds the image, but no in-app notice):

   ```bash
   git tag v1.0.0
   git push origin v1.0.0
   ```

The workflow then pushes `bozodev/marquee:1.0.0` and `bozodev/marquee:latest`.

Notes:
- The **`VERSION` file is the source of truth** for the version number; the tag
  name is only the trigger. Keep them in sync (tag `v1.0.0` ↔ `VERSION` `1.0.0`).
- The in-app update check compares against `UPDATE_REPO` (default
  `jeremehancock/Marquee`); enable it with `UPDATE_CHECK_ENABLED=true`.

---

## Cheat sheet

```bash
# Claude Code
claude                     # launch in the integrated terminal
/ide                       # (inside Claude) connect to the editor

# Quality gates
composer test              # PHPUnit
composer stan              # PHPStan level 8
composer cs                # PHP-CS-Fixer (dry-run)
composer cs:fix            # PHP-CS-Fixer (apply)

# OpenSpec CLI
openspec list
openspec show <change>
openspec validate <change> --strict
openspec archive <change>

# OpenSpec via Claude Code
/opsx:explore   <idea>
/opsx:propose   <name-or-description>
/opsx:apply     <change>
/opsx:update    <change>
/opsx:sync      <change>
/opsx:archive   <change>

# Branch → image tag
#   dev     → bozodev/marquee:dev                (test)
#   main    → bozodev/marquee:latest             (release)
#   v* tag  → bozodev/marquee:<VERSION> + latest  (versioned release)

# Cut a versioned release
echo "1.0.0" > VERSION && git commit -am "Release 1.0.0" && git push
git tag v1.0.0 && git push origin v1.0.0   # or publish a GitHub Release
```

### Repo layout

```
Marquee/
├─ public/            # web root (index.php, assets, sw.js)
├─ src/               # PHP: controllers, services, Plex client, config, DB
├─ templates/         # Twig views + partials/
├─ tests/             # PHPUnit (Unit, Functional)
├─ docker/            # s6 services, nginx conf, auto-import cron
├─ scripts/           # marquee-plex-test.py (live Plex round-trip tester)
├─ docs/              # this file + testing.md
├─ openspec/          # config.yaml, specs/, changes/
└─ .claude/commands/  # the /opsx:* slash commands
```
