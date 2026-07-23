# Drive publishing and releases from the VERSION file

## Why

`docs/development-workflow.md` documents a release system that does not exist.
It tells the reader that pushing to `dev` publishes `bozodev/marquee:dev`, and
that merging to `main` with a bumped `VERSION` publishes `:latest` and
`:<version>` and creates a GitHub Release — "no manual tag, no second build".

`.github/workflows/docker-publish.yml` implements almost none of that. It
triggers only on `main` and on hand-pushed `v*` git tags, derives version tags
from the git tag rather than the `VERSION` file, and has `permissions:
contents: read`, so it cannot create a tag or a release even in principle.

Two consequences, one already felt and one waiting:

- **Pushing to `dev` publishes nothing.** The workflow never fires, so there is
  no `:dev` image to test against. This is the reason the documented
  build-on-dev, test, then promote loop cannot be followed today.
- **Releases silently stop if you trust the doc.** `v0.1.0` and `v0.1.1` exist
  only because they were tagged by hand. Follow the documented process on the
  next release and you get `:latest` and nothing else — no `:<version>`, no git
  tag, no GitHub Release. Because the in-app update check reads GitHub
  Releases, users would simply stop being told that updates exist, with no
  error anywhere to notice.

The documented behavior is the intended behavior. This change makes the
workflow match it.

## What Changes

Make the branch and the `VERSION` file the only inputs to publishing.

- **Push to `dev`** publishes `bozodev/marquee:dev`.
- **Push to `main`** publishes `bozodev/marquee:latest`.
- **Push to `main` carrying a `VERSION` that has not been released** also
  publishes `bozodev/marquee:<version>`, creates the `v<version>` git tag, and
  creates a GitHub Release. A push to `main` with an already-released `VERSION`
  publishes only `:latest` and leaves the existing release untouched.
- **Every build** keeps its immutable `sha-<short>` tag.
- **Remove the `v*` tag trigger and the `type=semver` rules.** Git tags become
  an output of the release, never an input to it.

Also correct `UPDATE_REPO`, which still defaults to `jeremehancock/Posteria-II`
after the repository was renamed. This is a one-line fix in different code, and
it is included here deliberately rather than separately: the whole point of
creating GitHub Releases is to feed the in-app update check, and that check
currently queries the wrong repository. Shipping the release automation without
it would deliver a feature that still does not reach users.

## Impact

- Affected specs: `release-publishing` (new capability). Publishing is not an
  application capability, but it is durable, testable behavior that has already
  drifted out of sync with its documentation once. Specifying it is what makes
  the next drift fail loudly instead of silently.
- Affected code:
  - `.github/workflows/docker-publish.yml` — triggers, permissions, tag
    derivation, release creation
  - `src/bootstrap.php` — `UPDATE_REPO` default
  - `docs/development-workflow.md` — drop the stale note about the `v*` trigger
- Risk: the workflow gains `contents: write` in order to create tags and
  releases. Scoped to the publish job. Tag creation uses `GITHUB_TOKEN`, whose
  pushes do not trigger workflow runs, so there is no build loop — and with the
  `v*` trigger removed there is no path for one regardless.
