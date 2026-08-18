# settings Specification

## Purpose
Where an install's configuration lives, and how it is changed. Covers the
settings store on the persistent volume, the one-time seeding from the
environment that carries an upgrading install's compose file across, the
resolution of stored values into the typed configuration objects at bootstrap,
the settings screen that writes to the store, and the reporting of environment
variables that are still set but no longer obeyed.
## Requirements
### Requirement: Configuration is persisted in a settings store
The system SHALL persist configuration to a settings store on the persistent
volume, held separately from the Plex connection store and from the SQLite
database.

The database SHALL NOT be used, because it is specified as a cache of Plex data
that is safe to delete; configuration kept there would make deleting it
destructive. The connection store SHALL NOT be used, because it holds
credentials with a different lifecycle — discarding preferences must not require
signing in to Plex again, and writing a preference must not round-trip a
credential.

Writes SHALL be atomic: the file is written under a temporary name and renamed
into place, so a concurrent reader observes either the previous contents or the
new contents and never a partial write. A write SHALL re-read the stored
contents first and change only the keys it owns, because the web process and the
scheduled import each hold their own store and either may have written since the
other last read.

#### Scenario: A setting survives a container restart
- **WHEN** a setting is written and the container is recreated
- **THEN** the stored value is in effect after the restart

#### Scenario: A write preserves keys it did not set
- **WHEN** one process writes a setting after another process has written a
  different setting
- **THEN** both settings are present in the store

#### Scenario: Configuration is not kept in the deletable cache
- **WHEN** the SQLite database is deleted
- **THEN** every stored setting is still in effect

### Requirement: Configuration resolves once at bootstrap
The system SHALL resolve every setting from the store exactly once per request,
at bootstrap, into the same immutable typed configuration objects that already
carry configuration. No code outside that resolution SHALL read the store.

Value coercion, flooring, and fallback SHALL remain the responsibility of the
configuration objects rather than the store. The store returns stored values;
the configuration decides that a session duration below its floor is a lockout
and that an unrecognized sort order falls back to the default. Keeping that
decision in one place is what guarantees a value corrected at bootstrap and a
value rejected at input cannot disagree.

Because resolution happens per request, a changed setting SHALL take effect on
the next request without restarting the container.

#### Scenario: A stored setting is in effect on the next request
- **WHEN** a setting is changed
- **THEN** the following request observes the new value
- **AND** no restart is required

#### Scenario: Floors and fallbacks are preserved
- **WHEN** a stored value falls below a documented floor or is not recognized
- **THEN** the configuration exposes the same corrected value it would have
  applied to an environment variable holding that value

### Requirement: The environment seeds the store exactly once
The system SHALL treat environment variables as a source that seeds the store,
never as a source consulted when a setting is read.

On the first bootstrap that finds no stored settings, the system SHALL populate
the store from the environment and SHALL record that seeding has happened. An
install upgrading from a version that had no store therefore keeps every value
its compose file set.

Seeding SHALL happen at most once. Thereafter the store is the only source:
environment variables the store owns SHALL NOT be obeyed, and SHALL be read only
in order to report that they no longer take effect.

Reads SHALL come from the store both before and after seeding, so that no
setting ever has two live sources.

#### Scenario: An upgrading install keeps its compose configuration
- **WHEN** an install with settings in its environment starts for the first time
  with no stored settings
- **THEN** those values are written to the store and are in effect

#### Scenario: A fresh install seeds its defaults
- **WHEN** an install with no settings in its environment starts for the first
  time
- **THEN** every setting resolves to its documented default

#### Scenario: The environment stops applying after seeding
- **WHEN** an environment variable the store owns is changed and the container is
  recreated, and the store has already been seeded
- **THEN** the stored value remains in effect
- **AND** the environment value is not applied

#### Scenario: Seeding does not repeat
- **WHEN** a seeded store is present and the container is recreated
- **THEN** the store is not seeded again
- **AND** no stored value is overwritten from the environment

### Requirement: Superseded environment variables are reported
The system SHALL report environment variables that are still set but no longer
take effect, so that an install is told why a value it configured is being
ignored rather than being left to discover it.

The report SHALL distinguish two kinds, because the remedy differs:

- **Retired** — `PLEX_TOKEN`, `AUTH_USERNAME`, `AUTH_PASSWORD`, and
  `AUTH_BYPASS`. These name capabilities that no longer exist and never return.
- **Relocated** — settings the store now owns. These are managed in the
  application instead.

Both kinds SHALL be reported whenever the variable is present. Because seeding
happens on the first bootstrap, a relocated variable has no effect from that
point onward, so there is no state in which reporting it would be false.

The two kinds SHALL NOT be collapsed into one message. A user told that
`AUTH_PASSWORD` is "managed in the application" would look for a password field
that does not exist and never will.

#### Scenario: A retired variable is reported
- **WHEN** `PLEX_TOKEN` is set
- **THEN** it is reported as retired
- **AND** it is not used to authenticate to Plex

#### Scenario: A relocated variable is reported
- **WHEN** a setting the store owns is still set in the environment
- **THEN** that variable is reported as relocated
- **AND** the stored value, not the environment value, is in effect

#### Scenario: The two kinds are distinguishable
- **WHEN** both a retired and a relocated variable are set
- **THEN** the report identifies which kind each one is, so that each can be
  given its own remedy

#### Scenario: Nothing to report
- **WHEN** no superseded variable is set
- **THEN** the report is empty and no notice is shown

### Requirement: A damaged store degrades to defaults
The system SHALL treat a missing, unreadable, or malformed settings store as
"nothing stored" rather than as an error, and SHALL apply its documented
defaults.

A stored entry whose value is not usable SHALL be dropped individually rather
than costing the whole file, so that one bad value costs one setting and not the
install.

The store is read on every request. A parse failure that raised would make one
bad write unreachable to fix, which is the failure this requirement exists to
prevent.

#### Scenario: Absent store
- **WHEN** no settings file exists
- **THEN** every setting resolves to its documented default
- **AND** no error is raised

#### Scenario: Malformed store
- **WHEN** the settings file does not parse
- **THEN** every setting resolves to its documented default
- **AND** the application serves requests normally

#### Scenario: One unusable entry
- **WHEN** the settings file parses but one entry holds a value of the wrong
  shape
- **THEN** that setting resolves to its documented default
- **AND** every other stored setting is still in effect

### Requirement: Settings that locate the store stay in the environment
The system SHALL continue to read from the environment those settings that
cannot come from the store.

`DATA_DIR`, `POSTERS_DIR`, and `SESSION_DIR` SHALL remain environment-only: the
store lives inside `DATA_DIR`, so resolving it from the store is circular.
`DISPLAY_ERRORS` SHALL remain environment-only for a different reason — it is
the switch that makes a broken install diagnosable, and must not depend on
reading a file that may be what broke.

`UPDATE_REPO` and `POSTER_SOURCE_URL` SHALL remain environment-only because they
are development overrides rather than user settings; offering them as settings
would invite installs that point at the wrong service and cannot explain why.

#### Scenario: The data directory is read from the environment
- **WHEN** `DATA_DIR` is set in the environment
- **THEN** the store is located beneath that directory
- **AND** no attempt is made to resolve `DATA_DIR` from the store

#### Scenario: Error display survives an unreadable store
- **WHEN** the settings file cannot be read and `DISPLAY_ERRORS` is set
- **THEN** error display follows the environment value

### Requirement: Configuration is editable in the application
The system SHALL provide a settings screen from which the stored configuration
can be changed, so that changing how an install behaves does not require editing
a file on the host and recreating the container.

The screen SHALL require a session and SHALL sit behind the Plex connection gate,
like every other screen that is not explicitly public. It SHALL be reachable from
the application's secondary navigation at every screen width, not only by URL.

The screen SHALL cover, grouped so that related settings are decided together:

- **Presentation** — the site title, posters per page, the default sort, whether
  leading articles are ignored when sorting, and the maximum upload size.
- **Plex** — the connect timeout, the request timeout, and whether Kometa's
  `Overlay` label is removed when a poster is sent.
- **Session** — how long a session may go unused before it ends.
- **Updates** — whether to check for a newer release.
- **Libraries** — which Plex libraries are excluded from the application.

A saved value SHALL take effect on the next request, without restarting or
recreating the container, because configuration resolves per request at
bootstrap. The screen SHALL NOT tell the user to restart anything.

A submission SHALL be applied as one write rather than one write per setting, so
that a failure part-way cannot leave an install half-configured.

#### Scenario: A changed setting is in effect immediately
- **WHEN** a user changes a setting on the screen and saves
- **THEN** the next request observes the new value
- **AND** no restart or container recreation is involved

#### Scenario: The screen is behind both gates
- **WHEN** a visitor without a session, or an install with no Plex connection,
  requests the settings screen
- **THEN** it is not rendered, and the visitor is sent where the gate sends every
  other protected screen

#### Scenario: The screen is reachable from the navigation
- **WHEN** a signed-in user is on any screen that renders navigation
- **THEN** Settings is offered among the secondary actions

#### Scenario: A save is one write
- **WHEN** a submission changes several settings at once
- **THEN** either every changed setting is stored or none is

### Requirement: The screen never accepts a value bootstrap would correct
The system SHALL validate a submitted setting against the same rule the
configuration object applies when it resolves that setting, so that a value the
screen accepts is never silently changed on the next request.

Each floor and fallback SHALL have exactly one definition, read both where
configuration resolves and where a submission is validated. A second copy beside
the screen SHALL NOT be introduced.

The screen MAY offer a narrower range than the configuration accepts, but SHALL
NOT offer a wider one. Narrower is safe — nothing the screen accepts is later
corrected — while wider reproduces exactly the failure this requirement exists to
prevent: a setting that appears to save and does not stick.

A stored value outside the range the screen offers SHALL be displayed adjusted
into that range rather than rejected, so that a value seeded before the screen
existed cannot make an unrelated field unsavable.

Where a setting is presented in a friendlier unit than it is stored in — days
rather than seconds, megabytes rather than bytes — the conversion SHALL happen at
the screen's boundary. The stored representation SHALL NOT change, and a stored
value that is not a whole number of the displayed unit SHALL be shown rounded to
the nearest whole one, never to zero. What the user sees before saving is what
saving stores, so no rounding happens invisibly.

#### Scenario: A value the screen accepts is stored unchanged
- **WHEN** a user saves any value the screen accepts
- **THEN** the next request resolves that same value
- **AND** no floor or fallback alters it

#### Scenario: A value below a floor is refused
- **WHEN** a user submits a value below the floor its configuration object
  enforces
- **THEN** the submission is refused with a message naming the field
- **AND** nothing is stored

#### Scenario: An unrecognized choice is refused rather than silently defaulted
- **WHEN** a submission names a sort order the application does not offer
- **THEN** the submission is refused
- **AND** the stored sort order is unchanged

#### Scenario: A seeded value outside the offered range still renders
- **WHEN** a value seeded from the environment falls outside the range the screen
  offers
- **THEN** the screen displays it adjusted into range
- **AND** every other field on the screen can still be saved

#### Scenario: A converted unit round-trips visibly
- **WHEN** a setting stored in seconds or bytes is shown in days or megabytes
- **THEN** the displayed number is the stored value converted and rounded to the
  nearest whole unit
- **AND** saving stores exactly the value that was displayed

### Requirement: Excluded libraries are chosen from the server's own libraries
The system SHALL present library exclusions as a choice among the libraries the
connected Plex server reports, rather than as text naming them.

Exclusion matches on a library's name, so a name that matches nothing excludes
nothing and says nothing about why. Choosing from what the server reports removes
the failure entirely: a name that cannot be mistyped cannot silently fail to
match.

Because the application otherwise hides excluded libraries from every caller, the
screen SHALL be able to list libraries that are currently excluded. Exclusion
would otherwise be a one-way door — a library excluded is one nothing can see in
order to un-exclude it. This is the only place that observes an excluded library;
see `plex-import`.

A save SHALL replace the exclusions only for libraries the server reported when
the screen was rendered. A stored exclusion naming a library the server did not
report SHALL be preserved rather than dropped, because a library that is renamed,
removed, or briefly unreachable must not be un-hidden as a side effect of saving
an unrelated setting. Preserved names SHALL be shown, so that a stale exclusion
is visible and removable rather than invisible and permanent.

Exclusions SHALL remain app-wide. Nothing about this screen makes an exclusion
specific to it.

#### Scenario: Libraries are offered as choices
- **WHEN** a user opens the settings screen with Plex connected
- **THEN** every library the server reports is listed, whether excluded or not
- **AND** the currently excluded ones are marked as excluded

#### Scenario: Excluding a library takes effect app-wide
- **WHEN** a user excludes a library and saves
- **THEN** that library is absent from the import screen and from every import,
  manual or scheduled

#### Scenario: Un-excluding a library is possible
- **WHEN** a user clears the exclusion on a currently excluded library and saves
- **THEN** that library is reported by the application again

#### Scenario: An exclusion for an unreported library survives a save
- **WHEN** a stored exclusion names a library the server does not currently
  report, and the user saves the screen
- **THEN** that exclusion is still stored
- **AND** the screen lists it, so it can be removed deliberately

### Requirement: The settings screen degrades when Plex cannot be reached
The system SHALL render the settings screen when the Plex server is unreachable
or not configured, rather than failing.

The library section SHALL state why no libraries can be offered, using the same
wording the application uses for other Plex failures, and SHALL list the stored
exclusions as text. Every other section SHALL remain editable and savable, and a
save made in that state SHALL leave the stored exclusions untouched.

Configuration is what a user reaches for when something is wrong, so a settings
screen that requires a working Plex connection would be unavailable exactly when
it is needed.

#### Scenario: Plex is unreachable
- **WHEN** the settings screen is opened and the Plex server cannot be reached
- **THEN** the screen renders, the library section explains the failure, and the
  stored exclusions are listed

#### Scenario: Saving with Plex unreachable
- **WHEN** a user changes a presentation setting and saves while Plex is
  unreachable
- **THEN** that setting is stored
- **AND** every stored exclusion is unchanged

### Requirement: Relocated environment variables are reported where they are now managed
The system SHALL list, on the settings screen, the environment variables an
install still sets that the settings store has taken over, with the instruction
that they are managed here and can be deleted from the compose file.

Retired variables SHALL NOT be listed here. A variable whose capability no longer
exists needs a different sentence and a different place — the screen that
replaced it — and the two kinds SHALL remain distinguishable, as `settings`
already requires.

#### Scenario: A relocated variable is named on the screen
- **WHEN** a setting the store owns is still set in the environment
- **THEN** the settings screen names that variable and says it is now managed
  here

#### Scenario: A retired variable is not listed here
- **WHEN** `AUTH_PASSWORD` is still set
- **THEN** the settings screen does not list it among the variables it manages

#### Scenario: Nothing to report
- **WHEN** no relocated variable is set
- **THEN** the screen shows no such panel

### Requirement: A refused submission keeps what the user typed
The system SHALL re-render the settings screen with the submitted values and a
message against each field it refused, rather than discarding the submission.

A successful save SHALL confirm and redirect, so that reloading the screen does
not resubmit it. Submissions SHALL carry the application's cross-site request
forgery token, as every other form does.

#### Scenario: One bad field does not cost the rest
- **WHEN** a user changes several settings and one of them is refused
- **THEN** the screen re-renders carrying every submitted value
- **AND** the refused field is identified

#### Scenario: A save confirms and redirects
- **WHEN** a submission is accepted
- **THEN** the user is redirected to the settings screen with a confirmation
- **AND** reloading does not save again

#### Scenario: A submission without a valid token is refused
- **WHEN** a settings submission arrives without the request forgery token
- **THEN** it is refused and nothing is stored

### Requirement: The Plex server address is withheld from the screen
The system SHALL NOT offer the Plex server address on the settings screen.

The address is not merely a setting: it is an assertion only someone with host
access can make, and it is what stops the first stranger who reaches an
unconfigured install from claiming it as their own. Ownership is verified against
*that* address, so an address chosen in the browser verifies nothing. It SHALL
remain outside the screen until the property it provides has been replaced.

#### Scenario: The server address is not editable on the settings screen
- **WHEN** a user opens the settings screen
- **THEN** no field offers the Plex server address

### Requirement: Auto-import is configured on the settings screen
The system SHALL offer auto-import on the settings screen: whether it is enabled,
which media types it imports, and how often it runs.

The controls SHALL take effect without restarting or recreating the container,
like every other setting on the screen. They differ in one way that SHALL be
stated rather than left to be discovered: the rest of the screen applies to the
next request, while these apply to the next scheduled tick, because that is when
anything reads them.

The interval SHALL be offered as the same set of choices the container's schedule
used to encode, so that an install upgrading into this screen finds the schedule
it already had.

#### Scenario: Auto-import is enabled from the browser
- **WHEN** a user enables auto-import and saves
- **THEN** scheduled imports begin without the container being restarted

#### Scenario: Auto-import is disabled from the browser
- **WHEN** a user disables auto-import and saves
- **THEN** no further scheduled import runs, without the container being
  restarted

#### Scenario: The interval is changed
- **WHEN** a user changes how often auto-import runs and saves
- **THEN** the new interval governs from the next scheduled tick

#### Scenario: The screen does not promise the wrong timing
- **WHEN** a user saves an auto-import setting
- **THEN** the screen describes it as taking effect on the next scheduled run
  rather than on the next page load

