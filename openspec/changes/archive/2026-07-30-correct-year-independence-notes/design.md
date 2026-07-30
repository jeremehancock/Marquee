## Context

Verified against the code, not from memory:

- `PosteriaApiPosterSource::params()` sends `year` whenever `$query->year !== null`,
  in a block that runs before and independently of the `tmdb_id` block. Neither
  `if` reads the other's condition.
- `testTitleIsStillSentAlongsideAnId()` already pins `q`, `year` and `tmdb_id`
  arriving together, so the independence is enforced, not merely current.

Nothing needs fixing in behaviour. What needs fixing is four statements claiming the
source ignores `year` when an identifier is supplied:

| Where | Wording |
| --- | --- |
| `PosteriaApiPosterSource.php` comment | "where the endpoint ignores it… no observable effect" |
| `PosteriaApiPosterSourceTest.php` assertion message | "the endpoint ignores year when an id is sent" |
| archived `design.md`, Context bullet | "`year` is ignored when an identifier is supplied" |
| archived `design.md`, Decision 3 | "The service ignores `year` when an identifier is supplied" |

## Goals / Non-Goals

**Goals:**

- Make every surviving statement about `year` accurate.
- Put the durable version where it will actually be read before someone edits this
  code.

**Non-Goals:**

- Any change to what is sent. `year` is already correct.
- Fixing the season year gap. Separate defect, separate change.
- Rewriting archived prose to say something the change did not say.

## Decisions

### 1. The correction belongs in the spec, not only in a comment

A comment protects the line it sits above; a spec statement protects the behaviour.
The living spec currently says the year is used "to disambiguate similarly-titled
works" for a movie or show, and says nothing about the year and the identifier
interacting. Someone changing this code reads the spec, sees no constraint, and is
free to conclude the identifier supersedes the year.

So the requirement gains an explicit sentence: both are sent whenever both are
known, and the year is what disambiguates the fallback when the identifier fails.
That is a behavioural claim, testable, and it is the only one of these four fixes
that survives a rewrite of the class.

### 2. The code comment keeps its argument, loses its false premise

The existing comment reaches the right conclusion — it ends "which is exactly when
year matters" — from the wrong premise, "no observable effect". The correction is to
the premise only. The reasoning about why no branch is worth adding stays.

This is worth doing carefully rather than deleting the comment: the question "why is
this sent when it looks inert?" is exactly what a future reader will ask, and an
absent answer invites the same wrong conclusion as a wrong one.

### 3. The archive gets a correction note, not an edit

Archived changes record what a change believed at the time. Rewriting the prose to
say the opposite would make the record claim foresight it did not have, and would
erase the fact that the mistaken belief is what shipped.

A dated note at the top of the archived design says the claim was wrong, states the
accurate version, and points at this change. The record stays honest and the false
sentence stops being quotable in isolation.

Alternative considered: leave the archive untouched, on the grounds that archives are
history and the spec is the source of truth. Rejected because the archived design is
the most detailed prose about this parameter in the repository, and it presents the
false claim as the service's own documented contract — the exact framing most likely
to be trusted and least likely to be re-verified.

## Risks / Trade-offs

- **A spec sentence about a query parameter sits close to implementation.** → It
  earns the place: the parameter's independence is the behaviour, and stating only
  the outcome is what left the gap. This is the same reasoning that added the
  detection-signal scenario in `fix-stale-tmdb-id-detection`.

- **Correcting the archive sets a precedent for editing history.** → Bounded by
  Decision 3: an appended, dated note that adds a fact, never an edit that changes
  what the document claimed. Anything larger is a new change, not an annotation.
