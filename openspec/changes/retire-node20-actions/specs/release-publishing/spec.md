## ADDED Requirements

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
