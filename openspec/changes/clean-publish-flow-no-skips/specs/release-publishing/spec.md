## MODIFIED Requirements

### Requirement: Publishing is driven only by push-triggered CI

The system SHALL trigger a publish only from a CI run that was triggered by a
`push`. The CI workflow SHALL be triggered only by `push`, not by
`pull_request`, so a single pushed commit produces exactly one CI run and
therefore exactly one publish — never a duplicate that races or cancels the
first.

This narrows — but does not relax — the existing gate that publishing runs only
after CI passes for the commit: the commit's `push` CI must still succeed for a
publish to occur. Opening or updating a pull request SHALL NOT, on its own,
start a CI run or a publish.

#### Scenario: Push CI drives the publish

- **WHEN** a commit is pushed to `dev` or `main` and its `push` CI run succeeds
- **THEN** exactly one image publish runs for that commit

#### Scenario: A pushed commit is built by CI exactly once

- **WHEN** a commit is pushed to a branch and a pull request containing that same
  commit is opened or updated
- **THEN** CI runs once for the commit, driven by the `push`
- **AND** no additional CI run is started by the pull request
- **AND** exactly one publish runs for that commit, with no cancelled, skipped,
  or failed duplicate publish

#### Scenario: Manual dispatch still publishes

- **WHEN** the publish workflow is started manually via workflow dispatch
- **THEN** it runs regardless of any CI run's triggering event

## ADDED Requirements

### Requirement: The automated flow produces no cancelled or skipped runs

In normal operation the system SHALL NOT automatically cancel or skip any CI or
publish run. When two publishes for the same branch would otherwise overlap, a
superseding publish SHALL queue behind the in-flight one and run after it,
rather than cancel it. A run SHALL be cancelled or skipped only as the result of
deliberate manual action (for example, a maintainer cancelling a run, or a
manual dispatch).

#### Scenario: Consecutive pushes to a branch queue instead of cancelling

- **WHEN** a second commit is pushed to the same branch while that branch's
  publish is still in flight
- **THEN** the second publish waits for the first to finish and then runs
- **AND** neither publish is cancelled

#### Scenario: A normal ship shows only successful runs

- **WHEN** a change is shipped through the normal `dev` → pull request → `main`
  flow without any manual intervention
- **THEN** every CI and publish run for that flow completes successfully
- **AND** no run is reported as cancelled or skipped

#### Scenario: Manual cancellation is still permitted

- **WHEN** a maintainer manually cancels an in-flight run
- **THEN** that run is cancelled
- **AND** this is the only way an automated run becomes cancelled
