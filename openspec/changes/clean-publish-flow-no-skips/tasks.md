## 1. CI workflow — build each commit once

- [x] 1.1 In `.github/workflows/ci.yml`, remove the `pull_request:` trigger, leaving only `push: branches: ['**']`
- [x] 1.2 Update the workflow's header/comment (if any) to note CI is push-only and PR status attaches via the commit SHA

## 2. Publish workflow — never cancel automatically

- [x] 2.1 In `.github/workflows/docker-publish.yml`, set `concurrency.cancel-in-progress: false`
- [x] 2.2 Update the concurrency comment to reflect that a superseding publish queues behind the in-flight one instead of cancelling it (also refreshed the stale header note that said the pull_request CI is "ignored")

## 3. Verify

- [x] 3.1 Confirm both workflow files remain valid YAML (e.g., `actionlint` or a YAML parse)
- [ ] 3.2 On the next ship, confirm one green CI run and one green publish run per commit, with zero cancelled or skipped runs — *deferred: requires a live ship after this merges to `main`*
- [ ] 3.3 Confirm `:dev`, `:latest`, the pinned version image, the git tag/GitHub Release, and the `sha-<short>` tag are all still produced as before — *deferred: verify on the next ship*
