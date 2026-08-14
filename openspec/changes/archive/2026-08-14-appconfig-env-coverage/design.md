## Context

`AppConfig::fromEnv()` reads five variables:

| Variable | Default | Normalized | In README | Asserted by a test |
| --- | --- | --- | --- | --- |
| `SITE_TITLE` | `Marquee` (`APP_NAME`) | — | yes | no |
| `DATA_DIR` | `/config/data` | `rtrim(…, '/')` | no | no |
| `POSTERS_DIR` | `/config/posters` | `rtrim(…, '/')` | no | no |
| `SESSION_DIR` | `/config/sessions` | `rtrim(…, '/')` | yes | yes |
| `DISPLAY_ERRORS` | `false` | — | no | no |

`DATA_DIR` and `POSTERS_DIR` appear in roughly eight test files, but only as env
setup in `tests/AppTestCase.php`, which sets them explicitly to temp paths. Every
one of those tests passes whatever the defaults are; none of them observes a
default. `DISPLAY_ERRORS` is set to `'false'` in the same place and asserted
nowhere.

The gap surfaced while adding `SESSION_DIR` in `durable-session-storage`
(v2.5.0), where a task assumed parity with the two older directory settings and
found none. It was left alone then rather than widen that change's scope. The
failure it guards against is not hypothetical: `session.save_path` was left to
the runtime's default, the base image put it in the container's `/tmp`, and every
image update silently signed users out. Nothing failed, because nothing asserted.

Two decisions are already settled by the maintainer and are inputs here, not
open questions:

1. `DATA_DIR` and `POSTERS_DIR` stay out of the README.
2. `DISPLAY_ERRORS` is documented in `docs/development-workflow.md`.

## Goals / Non-Goals

**Goals:**

- Every variable `AppConfig` reads has a test asserting the value applied when it
  is absent.
- All three directory settings have a test asserting the trailing separator is
  trimmed — not just `SESSION_DIR`.
- `DISPLAY_ERRORS` is discoverable by a developer from the docs, not only from
  the source.
- The reason `DATA_DIR` and `POSTERS_DIR` are undocumented is written down, so
  the next person to notice the gap finds an answer instead of re-opening it —
  and a test fails if a later edit quietly reverses it.

**Non-Goals:**

- Changing any default value, renaming any variable, or altering `AppConfig`'s
  behaviour in any way. `src/Config/AppConfig.php` is not edited.
- Validating configured paths (existence, writability, absoluteness). Directory
  creation is the responsibility of the code that uses each path; `AppConfig`
  only resolves strings.
- Extending coverage to the other config objects (Plex, session, import). They
  have the same shape of exposure, but this change is scoped to `AppConfig`.
- Any change to `tests/AppTestCase.php`. Its explicit env setup is correct — the
  point is that setup is not assertion.

## Decisions

### Test the defaults through `fromEnv()`, not through the constructor

`AppConfig`'s constructor takes the resolved values, so the defaults live
entirely in `fromEnv()`. Every assertion therefore unsets the variable with
`putenv('NAME')` and reads the property off `AppConfig::fromEnv()`. `Env::str()`
treats both unset and empty string as absent, so the unset form covers the
behaviour that matters.

Alternative considered: asserting against `AppConfig::APP_NAME` for `SITE_TITLE`
rather than the literal `'Marquee'`. Rejected — a test that computes its expected
value from the same constant the code reads cannot detect the constant changing.
The point of pinning a default is to make a change to it visible, so the literal
is written out.

### Extend the existing test class rather than add a second one

`tests/Unit/Config/AppConfigTest.php` exists and currently covers only
`SESSION_DIR`; its class docblock is written entirely about sessions. The class
becomes what its name always claimed — the test for `AppConfig` — and the
docblock is rewritten to say why defaults are pinned at all. The session-specific
reasoning moves down to the session tests it explains, so nothing about the
`session.save_path` incident is lost.

Alternative considered: a separate `AppConfigDefaultsTest`. Rejected — two
classes testing one small value object, split on no meaningful axis, and the
first one a reader opens would be the one missing half the coverage.

### `tearDown()` clears every variable the class touches

The current `tearDown()` unsets `SESSION_DIR` alone. Tests that set `DATA_DIR` or
`POSTERS_DIR` and leak them would corrupt any later test in the same process that
reads a default — `putenv()` is process-global and PHPUnit runs a single process
by default. `tearDown()` therefore unsets all five names unconditionally, and
each default test also unsets its own variable up front, so it does not depend on
the ambient environment being clean when it starts.

### One test per property, not one table-driven test

Each directory setting gets its own default assertion and its own trimming
assertion. A single data-provider-driven test over the three directories would be
shorter, but a failure would then report a row index rather than name the setting
that regressed. These are guardrails read at the moment something breaks;
naming the thing that broke is the whole value.

### The audience split is asserted by a test, like the defaults

The change's own thesis — a default nobody asserts is one a refactor can move
silently — applies unchanged to a documentation decision. `DATA_DIR` staying out
of the README is a choice with reasoning behind it; nothing stops a future edit
from adding it to the table in passing, and nothing would fail.

`tests/Unit/Config/ConfigurationSurfaceTest.php` therefore reads `README.md` and
`docs/development-workflow.md` and asserts three things:

| Assertion | Guards against |
| --- | --- |
| `DISPLAY_ERRORS` appears in `docs/development-workflow.md` | the developer note being dropped |
| `DATA_DIR` and `POSTERS_DIR` appear nowhere in `README.md` | the volume layout quietly becoming configurable |
| `SITE_TITLE` and `SESSION_DIR` appear in `README.md` | the absence assertions passing against a gutted README |

The third is the one that makes the second mean anything. An absence assertion
alone is satisfied by an empty file, so a positive control has to sit beside it.

Matching is on the variable-name token only — never on table syntax, surrounding
prose, or position — so reformatting the table cannot break the test. Absence is
checked across the whole README including commented-out compose examples, since a
commented `# DATA_DIR:` line advertises the setting just as effectively as a table
row.

This is an established pattern in the repo rather than a new one:
`tests/Unit/Asset/` asserts over `app.css`, `gallery.js`, and `gallery.html.twig`
by reading them as text, and `PosterGroupsTest` strips comments before asserting
absence for the same class of reason.

Alternative considered: leaving the requirement unenforced, on the grounds that
every docs rule in this repo is unenforced. Rejected — that argument justifies
the status quo, not this change. The requirement was written precisely because
prose had already failed to hold the line once.

### `DISPLAY_ERRORS` is documented next to the toolchain, not in a new section

`docs/development-workflow.md` already has *Sanity-check the toolchain* under
Part 1, which is where a developer is oriented to running the app locally. The
variable is described there, stating plainly that it makes the error handler
render the exception instead of the generic error page, and that it must stay off
in any reachable install because stack traces disclose paths and configuration.
No new top-level section; a variable this small does not need one.

### The `/config` layout stays fixed in the README

Recorded here because the decision, not the omission, is the thing worth
preserving. The README tells users that `/config` holds the posters, the database
and logs, and the sessions, and that backing up that directory is enough.
`DATA_DIR` and `POSTERS_DIR` would make that promise conditional: an install that
moved one subpath out of the volume would find a `/config` backup incomplete, and
the support burden of that lands on a maintainer who never wanted the option.
`SESSION_DIR` is documented despite this because it answers a real failure — a
`/config` on a network share whose file locking breaks the session handler. The
other two answer nothing a user has asked for. They stay readable in
`AppConfig.php` for an operator who has already gone looking; they are not
advertised.

## Risks / Trade-offs

- **A test that unsets an env var leaks into an unrelated test** → `tearDown()`
  clears all five names, and each test unsets what it reads before reading it.
  Both directions are covered, so ordering cannot matter.
- **Pinning defaults makes an intentional future change to one of them noisier**
  → That is the intent. A one-line test edit is a cheap prompt to also update
  the README and the migration note; a silent move is what this change exists to
  prevent.
- **`DATA_DIR` / `POSTERS_DIR` stay undocumented, so a user who needs them must
  read the source** → Accepted deliberately, and now recorded in the spec with
  its reasoning. If a concrete need appears — the network-share case that
  justified documenting `SESSION_DIR` — the decision can be revisited with
  evidence rather than symmetry.
- **The spec now constrains documentation** → Enforced by
  `ConfigurationSurfaceTest` rather than left to review. The residual risk is the
  narrower one below.
- **A docs test couples tests to prose and can annoy a future editor** →
  Mitigated by matching on the variable-name token alone, so wording, table
  layout, and section order are all free to change. When it does fire, it fires
  for the right reason: someone changed which variables Marquee advertises, which
  is a spec decision and should require overturning the spec.
- **The absence assertion is only as good as its positive control** → `SITE_TITLE`
  and `SESSION_DIR` are asserted present in the same test. A README that lost its
  configuration table would fail rather than silently satisfy the absence checks.
