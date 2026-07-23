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

