## ADDED Requirements

### Requirement: Publishing is driven only by push-triggered CI

The system SHALL trigger a publish only from a CI run that was itself triggered
by a `push`. A CI run triggered by a `pull_request` SHALL NOT trigger a publish,
even when its CI succeeds and its branch would otherwise be eligible. As a
result, a single pushed commit SHALL produce exactly one publish, never a
duplicate that races the first.

This narrows — but does not relax — the existing gate that publishing runs only
after CI passes for the commit: the commit's `push` CI must still succeed for a
publish to occur.

#### Scenario: Push CI drives the publish

- **WHEN** a commit is pushed to `dev` or `main` and its `push` CI run succeeds
- **THEN** exactly one image publish runs for that commit

#### Scenario: Pull-request CI does not trigger a publish

- **WHEN** a commit's CI run that was triggered by a `pull_request` completes
  successfully
- **THEN** no publish is triggered by that CI run

#### Scenario: A commit built for both a push and a pull request publishes once

- **WHEN** the same commit has both a `push` CI run and a `pull_request` CI run
  and both succeed
- **THEN** only the `push` CI run triggers a publish
- **AND** exactly one publish runs for that commit, with no cancelled or failed
  duplicate publish

#### Scenario: Manual dispatch still publishes

- **WHEN** the publish workflow is started manually via workflow dispatch
- **THEN** it runs regardless of any CI run's triggering event
