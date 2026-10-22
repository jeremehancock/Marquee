## MODIFIED Requirements

### Requirement: A release is never advertised before its image exists
The system SHALL create the git tag and the GitHub Release only after the image
has been published successfully for every architecture it supports, so a release
can never point at an image that cannot be pulled, or that cannot be pulled on
some of the hardware it claims to support.

#### Scenario: Failed image push creates no release
- **WHEN** the image build or push fails during a release
- **THEN** no git tag and no GitHub Release are created

#### Scenario: One architecture failing creates no release
- **WHEN** a release is being published and the image for one architecture
  builds and pushes while the other fails
- **THEN** no git tag and no GitHub Release are created

## ADDED Requirements

### Requirement: A published tag always names the complete multi-architecture image
Every tag the system publishes (`dev`, `latest`, `<version>` and `sha-<short>`)
SHALL name one image that contains every supported architecture, currently
`linux/amd64` and `linux/arm64`. A tag SHALL be moved only once every
architecture has been built and pushed. When any architecture fails, no tag SHALL
move, and each keeps naming the previous complete image.

The reason is that a user pulls a tag, not an architecture. A tag that briefly
named an image missing one architecture would fail to start for every install on
that hardware, while looking published to everyone else.

#### Scenario: A complete publish tags both architectures
- **WHEN** a publish succeeds
- **THEN** each tag it applies names an image that lists both `linux/amd64` and
  `linux/arm64`

#### Scenario: A failed architecture moves no tag
- **WHEN** a publish builds and pushes one architecture and the other fails
- **THEN** none of the tags that publish would have applied are moved
- **AND** each still names the image it named before the publish started

### Requirement: Each architecture is built on hardware of that architecture
The system SHALL build each architecture's image on a runner of that
architecture, and SHALL NOT build an architecture by emulating it.

The reason is how releases reach users. The release that carries a new PHP
security patch is the one that recompiles PHP extensions, and compiling under
emulation turned that release's publish from about a minute into about thirteen,
delaying exactly the updates that should be fastest.

#### Scenario: Each architecture is built natively
- **WHEN** a publish runs
- **THEN** the `linux/amd64` image is built on an amd64 runner and the
  `linux/arm64` image on an arm64 runner
- **AND** no CPU emulation is set up for the build

#### Scenario: Users pull what they pulled before
- **WHEN** a user pulls any published tag on amd64 or arm64 hardware
- **THEN** they receive the image for their architecture under the same name and
  tag as before this change

### Requirement: CI smoke-tests the image on every architecture
The CI run for a commit SHALL build the image and run the same smoke test on each
supported architecture, each on a runner of that architecture. A smoke test that
fails on any architecture SHALL fail the CI run, and so, under the requirement
that publishing runs only after CI passes, SHALL prevent the commit from being
published.

The reason is that an architecture nobody tests is one whose faults reach users
first. Every architecture Marquee publishes is one a user installs, and a fault
that only appears on arm64, such as an extension that fails to load or a library
missing for that platform, is invisible to an amd64-only test.

#### Scenario: Both architectures are smoke-tested
- **WHEN** CI runs for a pushed commit
- **THEN** the image is built and smoke-tested on an amd64 runner and on an arm64
  runner

#### Scenario: An arm64-only fault blocks publishing
- **WHEN** the smoke test passes on amd64 and fails on arm64
- **THEN** the CI run fails
- **AND** no image is published for that commit
