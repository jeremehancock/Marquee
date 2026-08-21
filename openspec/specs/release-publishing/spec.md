# Release Publishing Specification

## Purpose

How Marquee reaches its users: which Docker image a push produces, and when a
push becomes a release.

Two inputs decide everything — the branch, and the `VERSION` file. `dev` gets a
moving `:dev` image to test against; `main` gets `:latest`; and a `main` push
carrying a version that has not been released yet also cuts a pinned image, a
git tag, and a GitHub Release. Git tags are an output of that process, never an
input to it, so nothing is triggered by pushing one.

This is build and release behavior rather than an application capability, but
it is specified for the same reason the rest is: it had already drifted out of
sync with its own documentation once, silently. The failure mode is quiet —
users simply stop being offered updates — which is exactly the kind that needs
scenarios rather than prose.
## Requirements
### Requirement: The branch determines the published image tag
Pushing a branch SHALL publish a Docker image whose moving tag is determined by
the branch alone: `dev` for the development branch and `latest` for the default
branch. No other input SHALL affect which of these is published.

#### Scenario: Development branch publishes the dev tag
- **WHEN** a commit is pushed to `dev`
- **THEN** the image is published as `bozodev/marquee:dev`
- **AND** no git tag and no release are created

#### Scenario: Default branch publishes latest
- **WHEN** a commit is pushed to `main`
- **THEN** the image is published as `bozodev/marquee:latest`

### Requirement: A previously unreleased VERSION on the default branch cuts a release
When a push to the default branch carries a `VERSION` that has not yet been
released, the system SHALL additionally publish the image under that version,
create the matching `v<version>` git tag, and create a GitHub Release from it.
The `VERSION` file SHALL be the only source of the version number.

#### Scenario: New version is released
- **WHEN** a commit is pushed to `main` and `VERSION` names a version with no
  existing `v<version>` git tag
- **THEN** the image is published as `bozodev/marquee:<version>` in addition to
  `latest`
- **AND** a `v<version>` git tag is created
- **AND** a GitHub Release is created for that tag

#### Scenario: Already-released version publishes only latest
- **WHEN** a commit is pushed to `main` and `VERSION` names a version whose
  `v<version>` git tag already exists
- **THEN** only `latest` is refreshed
- **AND** no duplicate git tag or release is created, and the existing pinned
  version image is not overwritten

#### Scenario: Version bumps on the development branch do not release
- **WHEN** a commit is pushed to `dev` with a changed `VERSION`
- **THEN** only the `dev` image is published, and no version image, git tag, or
  release is created

### Requirement: A release is never advertised before its image exists
The system SHALL create the git tag and the GitHub Release only after the image
has been published successfully, so a release can never point at an image that
cannot be pulled.

#### Scenario: Failed image push creates no release
- **WHEN** the image build or push fails during a release
- **THEN** no git tag and no GitHub Release are created

### Requirement: Every build is retrievable by commit
The system SHALL publish an immutable `sha-<short>` tag for every build, on any
branch, so any specific commit's image can be pulled for testing or rollback.

#### Scenario: Commit-pinned image is published
- **WHEN** any build publishes an image
- **THEN** it is also tagged `sha-<short commit sha>`

### Requirement: Git tags are an output of releasing, never an input
The system SHALL NOT treat a pushed git tag as a trigger for publishing.
Publishing SHALL be driven only by the branch and the `VERSION` file.

#### Scenario: Pushing a git tag does not publish
- **WHEN** a `v*` git tag is pushed to the repository
- **THEN** no image build or publish is triggered by it

### Requirement: Publishing runs only after CI passes for the commit
The system SHALL publish an image for a commit only after that commit's CI run
has completed successfully. A CI run that fails, is cancelled, or does not
succeed SHALL result in no image being published, no git tag, and no GitHub
Release. This gate applies to every publish path — the moving branch tag, the
pinned version image, and the `sha-<short>` tag alike.

#### Scenario: Green CI publishes the branch image
- **WHEN** a commit is pushed to `dev` or `main` and its CI run succeeds
- **THEN** the image is published as usual for that branch

#### Scenario: Failing CI publishes nothing
- **WHEN** a commit is pushed and its CI run fails or is cancelled
- **THEN** no image is published for that commit
- **AND** no git tag and no GitHub Release are created

#### Scenario: A release is gated on CI as well
- **WHEN** a commit pushed to `main` carries a previously unreleased `VERSION`
  but its CI run does not succeed
- **THEN** no pinned version image, git tag, or GitHub Release is created

#### Scenario: Manual dispatch bypasses the automatic gate
- **WHEN** the publish workflow is started manually via workflow dispatch
- **THEN** it runs without waiting on a CI run, so a maintainer can deliberately
  re-publish

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

#### Scenario: Pull-request CI does not trigger a publish

- **WHEN** a pull request is opened or updated for a commit
- **THEN** it does not start a CI run at all
- **AND** it therefore triggers no publish

#### Scenario: A commit built for both a push and a pull request publishes once

- **WHEN** a commit is pushed to a branch and that same commit also appears in an
  open pull request
- **THEN** only the `push` builds it in CI, so exactly one publish runs for that
  commit
- **AND** there is no cancelled, skipped, or failed duplicate publish

#### Scenario: Manual dispatch still publishes

- **WHEN** the publish workflow is started manually via workflow dispatch
- **THEN** it runs regardless of any CI run's triggering event

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

### Requirement: Deprecation in the automated flow is a defect, not noise

The workflows that build and publish Marquee SHALL be kept on third-party action
versions that target a runtime the runner still supports. When the platform
reports that an action targets a deprecated runtime, that report SHALL be treated
as a defect to fix rather than as output to tolerate, and SHALL be resolved before
the platform withdraws the runtime rather than after.

The reason is the failure mode this specification already exists to guard. A
deprecation that is left to expire does not announce itself on the day it breaks:
the publish workflow simply stops producing a release, and the first party to
notice is a user who is never offered an update again. A warning is the only
notice that arrives while there is still time to act, so tolerating it converts a
scheduled, visible problem into an unscheduled, invisible one.

Currency SHALL be assessed per action rather than per workflow. An action whose
pinned major already targets a supported runtime SHALL be left alone; being named
in the same file as an action that must move is not a reason to move it.

**A run in which a step did not execute SHALL NOT be taken as evidence about that
step.** The platform reports deprecation only for the actions a run actually ran,
so a step that was skipped is reported as clean and one that never ran at all is
reported not at all. This is not a hypothetical reading error: the step that
creates the git tag and the GitHub Release runs only on a default-branch push
carrying a version that has not been released yet, which means the overwhelming
majority of publish runs skip it and report nothing about it. The step guarding
the outcome this capability exists to protect is the step least likely to appear
in any run being examined.

It follows that an assessment of the publish flow SHALL cover the release path on
a run that took it. Where no such run is available, the release path's status
SHALL be reported as unknown rather than as clean.

#### Scenario: A skipped step is not cleared by the run that skipped it

- **WHEN** a publish run completes without executing the step that creates the git
  tag and the GitHub Release
- **THEN** that run is not treated as evidence that the step's action is on a
  supported runtime
- **AND** the step's status is reported as unknown rather than as clean

#### Scenario: The release path is assessed on a run that released

- **WHEN** the publish flow is assessed for deprecated runtime tooling
- **THEN** the assessment includes a run in which the tag-and-release step
  actually executed

#### Scenario: A reported deprecation is resolved before the runtime is withdrawn

- **WHEN** the platform reports that an action used by the CI or publish workflow
  targets a deprecated runtime
- **THEN** that action is moved to a version targeting a supported runtime while
  the deprecated runtime still works
- **AND** the move is not deferred until a run fails

#### Scenario: An action already on a supported runtime is left alone

- **WHEN** actions in a workflow are moved off a deprecated runtime
- **THEN** an action in that same workflow whose pinned version already targets a
  supported runtime is not moved
- **AND** its version is unchanged by the change that moves the others

#### Scenario: Publishing behavior is unchanged by a tooling move

- **WHEN** the workflows' action versions are moved to target a supported runtime
- **THEN** the branch-to-tag mapping, the release conditions, the CI gate, and the
  commit-pinned tag all behave exactly as specified before the move

