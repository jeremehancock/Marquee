## 1. Gate the publish job

- [x] 1.1 In `.github/workflows/docker-publish.yml`, add `github.event.workflow_run.event == 'push'` to the automatic clause of the `publish` job's `if`, so the automatic path requires both `workflow_run.conclusion == 'success'` and `workflow_run.event == 'push'`, while leaving the `workflow_dispatch` branch of the `if` unchanged.
- [x] 1.2 Update the explanatory comment above the `if` (and the header comment about the publish being gated on CI) to note that only `push`-triggered CI drives an automatic publish.

## 2. Verify

- [x] 2.1 Confirm the YAML is valid and the `if` expression parses (e.g. lint locally or push to a branch and check the Actions parse step).
- [ ] 2.2 On the next ship, confirm the dev tip commit produces exactly one publish and no cancelled or failed duplicate publish appears in the Actions tab. _(Deferred: runtime observation — verify on the next ship after this change is merged.)_
