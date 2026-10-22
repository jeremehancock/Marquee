## ADDED Requirements

### Requirement: A published image runs a current PHP patch release
The image SHALL take its PHP from a source that publishes every upstream PHP
patch release for the version line Marquee targets, so that rebuilding the image
picks up the newest patch without any change to the repository. The PHP version
SHALL NOT depend on a choice that silently stops receiving PHP updates while
builds keep succeeding.

The reason is the failure this requirement exists to prevent. The image once
took PHP from an operating system release that had stopped updating it, so every
build reinstalled the same ten-month-old PHP and reported success while a
critical remote code execution flaw went unpatched. Nothing errored, so nothing
was noticed.

#### Scenario: An upstream patch reaches the next build
- **WHEN** a PHP patch release is published upstream for the image's version line
  and the PHP source has published it
- **THEN** the next build of Marquee runs that patch
- **AND** no change to the repository was needed for it to do so

#### Scenario: The running version is visible
- **WHEN** a maintainer or user asks a running container for its PHP version
- **THEN** it reports the patch release the image was built with

### Requirement: An image that falls behind upstream PHP is reported
The system SHALL check, on a schedule of at least once a week, the PHP version in
the published `latest` image against the newest upstream PHP patch release for
the same version line. When the image is behind and that upstream release has
been available for longer than seven days, the check SHALL fail visibly, naming
the version the image runs and the version it should run. The grace period
exists because the PHP source needs time to publish a release; a failure inside
it would only report a delay nobody can act on.

The check SHALL NOT publish anything itself. Its remedy is a release through the
normal flow, because a release is what tells existing users through the in-app
update notice, and a silently refreshed `latest` tells nobody.

A check that cannot determine either version SHALL fail rather than pass. An
unknown result is not a clean one.

#### Scenario: An image behind beyond the grace period fails the check
- **WHEN** the scheduled check runs, the published `latest` image runs an older
  PHP patch than the newest upstream release for its version line, and that
  release is more than seven days old
- **THEN** the check fails
- **AND** its output names both versions

#### Scenario: A recent upstream release is given time
- **WHEN** the published `latest` image is behind an upstream release that is
  seven days old or less
- **THEN** the check passes

#### Scenario: A current image passes
- **WHEN** the published `latest` image runs the newest upstream patch for its
  version line
- **THEN** the check passes

#### Scenario: An unknown version fails the check
- **WHEN** the check cannot read the image's PHP version or the upstream release
  list
- **THEN** the check fails rather than passing

#### Scenario: The check publishes nothing
- **WHEN** the scheduled check runs, whether it passes or fails
- **THEN** no image, git tag or GitHub Release is published by it

### Requirement: Changing how the image is built preserves an existing install
A change to how the image is built SHALL let an existing install upgrade in place
by pulling the new image and recreating the container, with nothing else to do.
After the upgrade the install SHALL keep its data, posters and sessions in
`/config`, keep their ownership by `PUID` and `PGID`, apply `TZ` to both the
application and the scheduled auto-import tick, serve on port 80, answer
`/health`, and run the hourly auto-import tick.

The web server configuration SHALL belong to the image rather than being copied
into `/config` once on first start, so every upgrade delivers the configuration
that version ships. A new image SHALL NOT delete files an earlier image left in
`/config`; files it no longer reads are left for the user to remove.

#### Scenario: An existing install upgrades in place
- **WHEN** a container created by an earlier image is recreated from the new one
  with the same `/config` volume and environment
- **THEN** it starts, answers `/health`, and serves the sign-in page on port 80
- **AND** the settings, posters and sessions it had are still present and in use

#### Scenario: Ownership follows PUID and PGID
- **WHEN** the container starts with `PUID` and `PGID` set
- **THEN** the application runs as that user and group
- **AND** the contents of `/config` are owned by them

#### Scenario: The timezone applies to the application and the tick
- **WHEN** the container starts with `TZ` set
- **THEN** the application's local time and the hourly tick's schedule both use
  that timezone

#### Scenario: The tick runs whatever the settings say
- **WHEN** the container starts, with auto-import enabled or disabled
- **THEN** the scheduler is running and holds the hourly tick

#### Scenario: An upgrade delivers the current web server configuration
- **WHEN** an install whose `/config` holds a web server configuration copied by
  an earlier image upgrades to the new one
- **THEN** the web server uses the configuration shipped in the new image,
  including the sign-in rate limit

#### Scenario: Leftover files are not deleted
- **WHEN** the new image starts on a `/config` holding files an earlier image
  created and the new one no longer reads
- **THEN** those files are still present afterwards
