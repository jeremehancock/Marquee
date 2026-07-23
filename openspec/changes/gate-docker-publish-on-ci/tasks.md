## 1. Retrigger publish on CI success

- [x] 1.1 In `.github/workflows/docker-publish.yml`, replace the `push:
      branches: [main, dev]` trigger with a `workflow_run` trigger on
      `workflows: ["CI"]`, `types: [completed]`, `branches: [main, dev]`,
      keeping `workflow_dispatch`.
- [x] 1.2 Confirm `.github/workflows/ci.yml` `name:` is exactly `CI` so the
      `workflow_run.workflows` reference matches.
- [x] 1.3 Add the success gate to the `publish` job `if:`, keeping the existing
      `github.repository_owner == 'jeremehancock'` fork skip and allowing
      `workflow_dispatch` to bypass the CI-success check.

## 2. Fix workflow_run context

- [x] 2.1 Set the `actions/checkout` step to check out
      `github.event.workflow_run.head_sha` (retaining `fetch-depth: 0` and
      `fetch-tags: true`).
- [x] 2.2 Introduce a single resolved `branch` value derived from
      `github.event.workflow_run.head_branch` (auto) / `github.ref_name`
      (manual dispatch), and use it in the version/release-detection step in
      place of `github.ref == 'refs/heads/main'`.
- [x] 2.3 Update the `docker/metadata-action` tag rules to select `:dev` vs
      `:latest` from the resolved branch instead of `github.ref` /
      `is_default_branch`.
- [x] 2.4 Re-key the `concurrency.group` to the resolved head branch so
      same-branch publishes still supersede each other.

## 3. Verify

- [x] 3.1 Run `openspec validate gate-docker-publish-on-ci` and confirm the YAML
      parses (e.g. `actionlint` or a YAML lint) with no errors.
- [ ] 3.2 Merge to `main` so the new `workflow_run` trigger is active, then push
      a commit to `dev` and confirm the image publishes only after CI is green.
- [ ] 3.3 Confirm a failing CI run publishes no image, git tag, or GitHub
      Release.
