# Design

## Inputs

Publishing is a function of exactly two things: which branch was pushed, and
what the `VERSION` file contains. Nothing else. Git tags, which used to be the
trigger, become a product of the release.

```
  push dev   ─────────────────────────────▶  :dev        + :sha-xxxxxxx
  push main  ─────────────────────────────▶  :latest     + :sha-xxxxxxx
       └─ and VERSION not yet released ───▶  :<version>  + tag v<version> + Release
```

## Deciding whether a version is "new"

The workflow needs to answer "has this `VERSION` already been released?" so that
an ordinary merge to `main` — a docs fix, a refactor — refreshes `:latest`
without minting a duplicate release.

Three ways to answer it:

| Approach | Verdict |
| --- | --- |
| Diff `VERSION` against the previous commit | Rejected. Breaks on re-runs, squashes, and force-pushes; a merge commit may not show the file as changed even when the version is genuinely new. |
| Ask the GitHub API whether the Release exists | Workable, but an extra authenticated call and it conflates "release exists" with "tag exists". |
| **Check whether the `v<version>` git tag exists** | **Chosen.** The tag is the durable record, it is what the Release attaches to, and the check is a local string comparison after fetching tags. |

This makes the whole job idempotent: re-running a workflow for an
already-released commit republishes the same image tags and creates nothing new.

Checkout therefore needs tag history — `fetch-depth: 0` — which the current
workflow does not request.

## Ordering

Create the tag and Release **after** the image push succeeds:

```
  build + push image  ──▶  create tag v<version>  ──▶  create GitHub Release
         │
         └── fails ──▶ no tag, no release. Fix and re-run; nothing to unwind.
```

The reverse order can leave a Release advertising an image that was never
published — and since the in-app update check reads Releases, that would prompt
every user to update to something they cannot pull.

The residual failure mode is the image pushing and tag creation then failing.
That is recoverable by re-running: the image push is idempotent and the tag
check will still report the version as unreleased.

## Tag naming

`v<version>` — matching the existing `v0.1.0` and `v0.1.1`, and the convention
GitHub Releases expect. The Docker tag stays bare (`0.1.1`, not `v0.1.1`), which
is what the current `type=semver,pattern={{version}}` rule already produces, so
existing pulls keep working.

## Permissions

The job needs `contents: write` to create a tag and a Release; it currently has
`contents: read`. Scoped to this workflow only.

Pushes made with `GITHUB_TOKEN` do not trigger workflow runs, so tag creation
cannot cause a second build. Removing the `v*` trigger makes that structural
rather than a property we rely on.

## Resulting workflow

```yaml
on:
  push:
    branches: [main, dev]
  workflow_dispatch:

permissions:
  contents: write

jobs:
  publish:
    if: github.repository_owner == 'jeremehancock'
    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0          # need tags to test whether VERSION is released

      - id: ver
        run: echo "version=$(tr -d '[:space:]' < VERSION)" >> "$GITHUB_OUTPUT"

      - id: rel
        # "new release" = on main, and no v<version> tag exists yet
        run: |
          if [ "${{ github.ref }}" = "refs/heads/main" ] \
             && ! git rev-parse -q --verify "refs/tags/v${{ steps.ver.outputs.version }}" >/dev/null; then
            echo "new=true" >> "$GITHUB_OUTPUT"
          else
            echo "new=false" >> "$GITHUB_OUTPUT"
          fi

      - id: meta
        uses: docker/metadata-action@v5
        with:
          images: bozodev/marquee
          tags: |
            type=raw,value=latest,enable={{is_default_branch}}
            type=raw,value=dev,enable=${{ github.ref == 'refs/heads/dev' }}
            type=raw,value=${{ steps.ver.outputs.version }},enable=${{ steps.rel.outputs.new == 'true' }}
            type=sha,format=short

      # ... build and push ...

      - if: steps.rel.outputs.new == 'true'
        # create tag v<version> + GitHub Release, after the push has succeeded
```

## Out of scope

Gating the publish on CI passing. `docker-publish.yml` and `ci.yml` run in
parallel today, so a red build still publishes. For `:dev` that is arguably
correct — pulling a broken image to investigate it is a normal thing to want —
but it means `:latest` carries no green-build guarantee either. Worth deciding
separately; changing it here would couple a scheduling question to a
tag-derivation change.
