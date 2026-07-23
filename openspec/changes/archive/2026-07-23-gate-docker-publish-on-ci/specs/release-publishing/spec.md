## ADDED Requirements

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
