## ADDED Requirements

### Requirement: The application owns the schedule
The system SHALL decide when a scheduled import runs, rather than encoding the
schedule into the container at startup.

The container SHALL provide only a fixed, unconditional tick: the scheduler
process SHALL always run, and its schedule SHALL NOT depend on any setting read
at boot. Whether a tick does anything SHALL be decided by the application when
that tick fires, from the settings store — the same store the interface writes.

This SHALL hold whatever the settings were when the container started. A
container that booted with auto-import disabled SHALL still be able to run one
after it is enabled, without being recreated.

The previous arrangement made that impossible, and silently: the schedule was
written at boot only when auto-import was enabled, and the scheduler ran only
when that file was written, so an install that booted disabled had no scheduler
to notice it had been enabled.

#### Scenario: Enabling auto-import on a container that booted disabled
- **WHEN** an install starts with auto-import disabled, and a user later enables
  it
- **THEN** scheduled imports begin
- **AND** the container is not restarted or recreated

#### Scenario: Changing the interval takes effect without a restart
- **WHEN** a user changes the interval
- **THEN** the next run follows the new interval
- **AND** the container is not restarted or recreated

#### Scenario: The tick is unconditional
- **WHEN** the container starts with any combination of auto-import settings
- **THEN** the scheduler process is running

### Requirement: A run is due at the interval's slots
The system SHALL treat the day as divided into slots of the configured interval,
anchored at midnight in the container's local time, and SHALL run an import when
a tick falls in a slot the install has not yet completed.

The offered intervals SHALL keep the firing times they had when the schedule
lived in the container: daily at midnight, and every 12, 6, 3, or 1 hours from
midnight. The set of choices SHALL NOT change in this change.

Slots SHALL be used rather than time elapsed since the last run. Elapsed time
drifts — a daily import completing a few minutes after midnight would fall
later every day — and a user who asked for a daily import at midnight did not
ask for one that walks around the clock.

A slot SHALL be recorded as completed only when its import finishes, so that a
run interrupted part-way is retried at the next tick rather than counted as done.

#### Scenario: A tick outside a slot does nothing
- **WHEN** a tick fires at an hour that does not begin a slot for the configured
  interval
- **THEN** no import runs
- **AND** nothing is reported as an error

#### Scenario: A slot runs once
- **WHEN** a slot's import has completed and another tick fires within the same
  slot
- **THEN** no second import runs

#### Scenario: Daily means midnight
- **WHEN** the interval is daily
- **THEN** the import runs at midnight local time, as it did when the schedule
  was a crontab entry

#### Scenario: An interrupted run is retried
- **WHEN** an import is interrupted before it finishes
- **THEN** its slot is not recorded as completed
- **AND** the next tick runs it

### Requirement: A missed slot is caught up
The system SHALL run an import at the first tick after a slot it missed, rather
than waiting for the next slot.

An install that was switched off, or whose host was rebooted, across its
scheduled time has not had the import it was configured to have. Waiting a full
interval to correct that is the behavior of a crontab, not an intention.

Catch-up SHALL run at most one import, however many slots were missed. The
import is a synchronisation with Plex, not a queue of work: running it once
brings the library up to date, and running it five times would not.

#### Scenario: Downtime across a slot
- **WHEN** an install is not running at its scheduled time and starts afterwards
- **THEN** an import runs at the first tick after it starts

#### Scenario: Long downtime runs one import
- **WHEN** an install has been off across several slots and starts again
- **THEN** exactly one import runs

#### Scenario: Catch-up does not repeat
- **WHEN** a catch-up import has completed
- **THEN** the following tick runs nothing further

### Requirement: A scheduled run does not overlap itself
The system SHALL NOT start a scheduled import while one is already running.

An import can outlive its own interval on a large library, and the tick that
fires underneath it must not start a second copy competing for the same database
and the same Plex server.

A guard left behind by a process that was killed SHALL NOT disable auto-import
permanently. The system SHALL treat a guard older than a generous bound as
abandoned and proceed, because a scheduler that silently stops forever is worse
than one that occasionally overlaps.

#### Scenario: A tick during a run is skipped
- **WHEN** a tick fires while a scheduled import is still running
- **THEN** no second import starts

#### Scenario: A crashed run does not block forever
- **WHEN** a scheduled import is killed without releasing its guard
- **THEN** a later tick runs normally rather than being blocked indefinitely

#### Scenario: The guard is released on failure
- **WHEN** a scheduled import fails
- **THEN** the guard is released, and the next due tick may run

### Requirement: Schedule state is kept where losing it is cheap
The system SHALL persist which slot it last completed, and SHALL keep that state
where its loss costs at most one redundant import.

The state SHALL record the slot completed rather than the wall-clock moment the
run finished, because "which slot is done" is the question the scheduler asks,
and storing the answer to a different question invites recomputing it wrongly.

Losing this state SHALL cause the next tick to treat the current slot as
unrun — one extra import, which for an unchanged library is close to free
because imports skip posters that have not changed in Plex. It SHALL NOT cause
an error, and SHALL NOT leave auto-import disabled.

#### Scenario: State lost
- **WHEN** the stored schedule state is removed
- **THEN** the next due tick runs one import
- **AND** no error is reported

#### Scenario: State survives a restart
- **WHEN** a slot has been completed and the container is restarted within that
  slot
- **THEN** no further import runs until the next slot

## MODIFIED Requirements

### Requirement: Auto-import no-ops safely
The system SHALL do nothing (beyond logging) when auto-import is disabled, when
Plex is not configured, or when no media types are enabled.

The schedule decision SHALL sit in front of these checks rather than replace
them: a tick that is not due SHALL exit before doing any work, and a tick that is
due SHALL still pass through every check above. A tick that does nothing SHALL
NOT be reported as a failure, however often it fires — with an unconditional
tick, most ticks do nothing, and that is the design working.

#### Scenario: Disabled
- **WHEN** auto-import is disabled
- **THEN** it imports nothing

#### Scenario: Nothing selected
- **WHEN** auto-import is enabled but no media types are enabled
- **THEN** it imports nothing

#### Scenario: A tick that does nothing is not a failure
- **WHEN** a tick fires and no import is due
- **THEN** the run exits successfully without reporting an error
