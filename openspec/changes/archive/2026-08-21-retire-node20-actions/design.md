## Context

GitHub deprecated the Node 20 actions runtime and currently force-runs Node 20
actions on Node 24, emitting a warning annotation naming each one. Verified on
2026-08-21 against the runs for PR #95 and the v2.11.1 release, seven of the nine
third-party actions across the two workflows are named.

The two workflows are not equally easy to change.

`ci.yml` is push-triggered, so an edit on `dev` is exercised by the very next push
to `dev`. It is the easy half.

`docker-publish.yml` is `workflow_run`-triggered, and **`workflow_run` reads the
workflow file from the default branch** — the file says so itself in a NOTE at
lines 22–24. Editing it on `dev` therefore changes nothing about how pushes to
`dev` behave; they keep executing `main`'s copy. The edit is unexercised right up
until it is merged, which is the one place it cannot afford to be wrong: this
workflow produces `:latest`, the pinned `:<version>` image, the `v<version>` git
tag, and the GitHub Release that powers the in-app update notice.

### The premise that turned out to be wrong

The change was scoped from the annotation on a publish run that listed six
actions and did not name `softprops/action-gh-release@v2`, which was read as
evidence that it was already Node 24-ready. It is not: its floating `v2` tag
resolves to `2.6.2` (April 2026), whose `action.yml` declares
`runs.using: "node20"`.

The annotation lists only the actions a run actually **ran**. Two publish runs
three minutes apart on `main` tell the whole story:

```
run 32502422384                    run 32502142228  (head 20fdf773 = v2.11.1)
 9. Create tag and GitHub Release   9. Create tag and GitHub Release
      -> skipped                          -> success
 annotation names SIX actions       annotation names SEVEN
                                    ...including softprops/action-gh-release@v2
```

The step that creates the release is the least-exercised step in the pipeline —
it runs only on a `main` push whose `VERSION` has no matching tag. So the action
guarding the outcome this capability exists to protect is the one least likely to
appear in any run being looked at. That asymmetry is durable, not a one-off
mistake, which is why it is written into the spec delta rather than only here.

## Goals / Non-Goals

**Goals:**

- Move every action that targets Node 20 onto a major that targets Node 24,
  before the platform withdraws Node 20.
- Change no publishing behavior: identical tags, identical release conditions,
  identical CI gate.
- Validate as much of `docker-publish.yml` as can be validated before merge,
  and state precisely what cannot be.

**Non-Goals:**

- **Pinning actions to commit SHAs.** Better supply-chain posture and nearly free
  while all seven pins are already being touched — but it needs Dependabot to
  avoid rotting, and it would roughly double the size of a diff that has to be
  validated through a dispatch path this repository has never used. Its own
  change, after this one lands.
- Touching `VERSION`. That belongs to `/ship`, and keeping this change
  version-neutral is deliberate — see the migration plan.
- Reworking the `workflow_run` trigger, the concurrency group, or the job guard.
- Adding CI for fork pull requests.

## Decisions

### Targets: the current major for each, verified against `action.yml`

Each target was confirmed by reading `runs.using` at the floating major tag, not
from release notes. Every input the two workflows pass was diffed across every
bump; **all of them survive.**

| Action | Now | Target | Removed inputs at target | Do we pass them? |
| --- | --- | --- | --- | --- |
| `actions/checkout` | v4 `node20` | **v7** | none | — |
| `docker/setup-qemu-action` | v3 `node20` | **v4** | none (*adds* `reset`) | — |
| `docker/setup-buildx-action` | v3 `node20` | **v4** | `config`, `config-inline`, `install` | No — we pass no inputs |
| `docker/login-action` | v3 `node20` | **v4** | none | — |
| `docker/metadata-action` | v5 `node20` | **v6** | none | — |
| `docker/build-push-action` | v6 `node20` | **v7** | `DOCKER_BUILD_NO_SUMMARY`, `DOCKER_BUILD_EXPORT_RETENTION_DAYS` (envs) | No — unset |
| `softprops/action-gh-release` | v2 `node20` | **v3** | none | — |
| `shivammathur/setup-php` | v2 **already `node24`** | unchanged | — | — |

Two changes came close to mattering and were checked rather than assumed:

- **`metadata-action` v6: "List inputs now preserve `#` inside values while still
  supporting full-line `#` comments."** Our `tags:` *is* a multi-line list input,
  so this is the one change in the set that could have silently altered the
  published tag set. No value in it contains `#` and it has no full-line
  comments, so the new parsing is a no-op for us.
- **`setup-buildx-action` v4 removed deprecated inputs.** We invoke it bare, with
  no `with:` block at all.

`shivammathur/setup-php@v2` is deliberately excluded. Its floating major already
declares `node24` and it is absent from the annotation. Moving it because it sits
in the same file would be churn with its own risk and no benefit; the spec delta
records that currency is assessed per action.

### `actions/checkout` → v7 rather than v5 or v6

`v5`, `v6`, and `v7` all declare `node24`, so any of the three would clear the
warning. Two facts decide it.

**Staying on v4 is not an option.** The July 2026 backport wave shipped v4.4.0,
v5.1.0, v6.1.0 and v7.0.1 on the same day, but v4.4.0's `action.yml` still
declares `node20`. The `v4` float will not become Node 24-ready.

**v7's one behavioral change is already running here, and is inert.** v7.0.0
includes PR #2454, *"block checking out fork PR for `pull_request_target` and
`workflow_run`"* — and `docker-publish.yml` is a `workflow_run` workflow that
checks out an arbitrary sha, so this needed a real look rather than a shrug:

```
assertSafePrCheckout():
  allowUnsafePrCheckout?                     no
  eventName === 'workflow_run'?              yes    (does not early-return)
  wrEvent = workflow_run.event               'push'
  !wrEvent.startsWith('pull_request')  ──►   RETURN   ← never reaches the throw
```

Our CI is push-only, which the `release-publishing` requirement *"Publishing is
driven only by push-triggered CI"* already guarantees, so `workflow_run.event` is
always `push` and the guard short-circuits. The job's own `if:` blocks anything
else from starting in the first place, so this is belt-and-suspenders.

Stronger than the reasoning: **the guard was backported into v4.** The `v4` float
resolves to `11d5960a`, whose `action.yml` already lists the
`allow-unsafe-pr-checkout` input, and `src/unsafe-pr-checkout-helper.ts` at `v4`
differs from `v7` by exactly one character — `'./ref-helper'` versus
`'./ref-helper.js'`, the ESM extension. That guard has been running on every
publish this repository has done and they are all green. It is proven inert
empirically, not just by reading it.

Given that, going to v5 or v6 buys soak time against a risk that is already
retired, in exchange for owing this work again within months. v7 it is.

### One spec requirement, framed as a policy rather than a state

`release-publishing` has eight requirements and all eight are about behavior:
tag mapping, release gating, "a release is never advertised before its image
exists". A runtime bump changes none of them, so the honest first instinct was
that this change needs no delta at all.

That option does not exist. `openspec validate` raises
`Change must have at least one delta` at `level: 'ERROR'` — unconditionally, not
gated on `--strict`. Every one of the ~40 archived changes carries a delta,
including `2026-08-18-trim-readme-compose-example`, which changed only
documentation. So the question is not *whether* but *what*.

**A requirement stating that the flow runs on Node 24 would decay.** It is true
today and false in eighteen months with nobody having touched anything, and a
requirement that goes false without a code change is a bad requirement.

Written as a **policy** it does not decay, and that is a shape this project
already specs — `trim-readme-compose-example` added *"Documentation of an
environment variable SHALL follow from who is expected to set it"*, a constraint
on future edits rather than a fact about the present. The commitment here is
"actions are kept on a runtime the runner supports, and a reported deprecation is
a defect", which stays true across every future deprecation.

The durable, non-obvious half is the second clause: **a run that skipped a step
is not evidence about that step.** That is not a general observation, it is the
specific trap this change fell into, it is invisible from the run list, it
survives every future deprecation, and it is checkable by inspection.

**Considered and deferred:** specifying that a failed release leaves the version
releasable and is therefore retryable. That behavior exists — the
`git rev-parse -q --verify "refs/tags/v$version"` check makes release detection
idempotent — and it is currently unspecified, and it is the property the
`gh-release@v3` risk assessment below leans on. It was left out because it is
pre-existing behavior orthogonal to the runtime move, and folding it in would
make this change do two things. It is a real gap in `release-publishing` and
deserves its own small change.

## Risks / Trade-offs

### What pre-merge validation actually covers

`workflow_dispatch` runs the workflow file **from the ref you select**, which is
the escape hatch for the `workflow_run` trap. The trigger is present on `main`,
so the ref picker is offered; dispatched against `dev` it sets
`BRANCH = github.ref_name = dev`, `release=false`, and checks out `github.sha`,
publishing `bozodev/marquee:dev` plus `sha-<short>`. Harmless.

It is worth knowing that **this repository has never once dispatched this
workflow** — zero `workflow_dispatch` runs in the last 100. The escape hatch
itself is untested here.

| Step | Covered by a `dev` dispatch? |
| --- | --- |
| `actions/checkout@v7` | Partial — runs, but under `workflow_dispatch`, not the `workflow_run` context the fork guard keys on |
| Read version (shell) | Partial — only the `BRANCH=dev` path; the `main` tag-lookup branch never runs |
| `docker/setup-qemu-action@v4` | Yes |
| `docker/setup-buildx-action@v4` | Yes |
| `docker/login-action@v4` | Yes |
| `docker/metadata-action@v6` | Partial — only the `dev` and `sha` rows enable; `latest` and `<version>` evaluate `enable=false` |
| `docker/build-push-action@v7` | Yes — full multi-arch build and push |
| `softprops/action-gh-release@v3` | **No — never runs** |

**[The dispatch runs checkout under the wrong event, so v7's fork guard is not
exercised]** → Retired by evidence rather than by the dispatch: the identical
guard is already live in `v4` and green on every `workflow_run` publish this
repository has performed.

**[`metadata-action` v6 could alter the tag set, and the dispatch only enables two
of four rows]** → Read `steps.meta.outputs.tags` in the dispatch run and confirm
the `dev` and `sha-` rows are exactly right. The `latest` and `<version>` rows
differ from them *only* in an `enable=` expression computed by our own shell
step, which v6 does not touch, so a correct `dev` row is strong evidence for all
four. Step 4 of the migration plan then exercises the `latest` row for real
before any release depends on it.

**[`gh-release@v3` cannot be exercised before merge]** → Cannot be eliminated; it
is bounded three ways. Its input list is byte-identical to v2's. Its release
notes describe a pure runtime move with no other change. And the failure is
**loud and recoverable, not silent**: if the step fails the job goes red, and
because no `v<version>` tag is written, `VERSION` stays unreleased, so the next
`main` push or a manual dispatch re-cuts the release. The tag-existence check
makes it idempotent.

**[The genuinely silent failure is an unparseable workflow file]** → If
`docker-publish.yml` on `main` will not parse, `workflow_run` never fires and
nothing goes red anywhere — this, not a bad action version, is the failure mode
that matches "a future release quietly doesn't happen". A green dispatch on `dev`
proves the file parses and its job-level `if:` and expressions evaluate, which is
the highest-value thing the dispatch buys and the reason it is not optional.

**[v7 of `actions/checkout` is roughly a month old]** → Accepted deliberately. All
inputs verified present, the only behavioral change verified inert, and the
alternative is repeating this work within months.

## Migration Plan

Ordered so that each step is exercised by the cheapest mechanism that can
exercise it, and so the release path is the last thing standing.

1. **Edit both workflows on `dev` and push.** The push exercises `ci.yml`'s two
   `checkout@v7` pins immediately via push-CI. `docker-publish.yml` is *not*
   exercised — `main`'s copy still runs.
2. **Dispatch `docker-publish.yml` against `dev`** from the Actions tab. Proves
   the edited file parses, its `if:` evaluates, six of seven actions run, and the
   multi-arch build and push works. Check `steps.meta.outputs.tags`.
3. **Pull `bozodev/marquee:dev`** and confirm the `sha-<short>` tag matches `dev`
   HEAD.
4. **Merge without a `VERSION` bump.** The first `main` push then exercises the
   `latest` tag row and the `main` branch of the version shell step, with the
   release step still skipped — so the action bumps are proven on `main` against
   an otherwise known-good state before any release rides on them.
5. **The next release exercises `gh-release@v3`.** Loud and retryable if wrong,
   per the risk above.

**Rollback:** revert the workflow files on `main` and push. The revert is picked
up on the next `workflow_run`, since `workflow_run` reads from the default
branch. No image, tag, or release needs to be undone — a failed publish leaves
`VERSION` unreleased, so re-running simply re-cuts it.

## Open Questions

None blocking. One item is deliberately deferred: the unspecified
failed-release-is-retryable behavior described under Decisions, which should
become its own change against `release-publishing`.
