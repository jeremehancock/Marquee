## Context

The previous change made `SESSION_DURATION` govern the cookie's expiry, the
store's retention, and the application's own window. All three now agree on
thirty days. One thing still overrides all of them:

```
   /config          ← docker VOLUME, survives recreation
     ├─ data/       ← SQLite + plex-connection.json
     └─ posters/

   /tmp             ← NOT a volume, cleared on every container recreate
     └─ sess_*      ← every session, all thirty days of them
```

`session.save_path` is unset in the image, so PHP writes to `/tmp`. `docker
compose pull && up -d` replaces the container, `/tmp` goes with it, and every
user signs in again. The window is thirty days or one update, whichever comes
first — and on an install with automated updates, it is always the update.

The code already knows. `CsrfMiddleware` carries a carve-out written around this
exact behaviour:

> *"PHP sessions live in the container's /tmp, so recreating the container
> discards them all… Starting a sign-in is the only state-changing route
> reachable with no session behind it, so it is the only place a user meets a
> dead token."*

That comment reasons correctly about CSRF tokens and never notices that the same
mechanism discards the login itself.

### Correcting the record from the previous change

The archived `session-duration-governs-all-layers` design deferred this work with
a reason that was overstated:

> *"it takes on a class of hang the application does not currently have, on a
> path every request travels"*

That is wrong on the premise. `/config` is already on the per-request path:

| Already true today | Where |
| --- | --- |
| `DATA_DIR` defaults to `/config/data` | `AppConfig::fromEnv()` |
| Every protected request reads `/config/data/plex-connection.json` | `PlexConnectionMiddleware` → `PlexConfig::resolve()` → `PlexConnectionStore::load()` |
| The SQLite database lives on the same volume | `Database` at `$app->dataDir . '/marquee.sqlite'` |

So this change is not first contact with `/config`, and it does not introduce
network-filesystem exposure that was previously absent. The real delta is
narrower and worth naming precisely: **PHP's file session handler takes an
exclusive `flock()` and holds it from `session_start()` until the request ends.**
A read is not a held lock. That is a genuine difference — just a much smaller one
than "a class of hang the application does not currently have".

The archived design is corrected as part of this change rather than left to
mislead whoever reads it next.

## Goals / Non-Goals

**Goals:**

- A session survives recreating the container, so an image update stops signing
  everybody out.
- The storage location is Marquee's decision, set before the session starts,
  alongside the retention and cookie settings that already live there.
- An install whose volume is a misbehaving network mount can return to local
  storage without giving up anything else about the session.
- The `/tmp` caveat stops being a documented limitation, because it stops being
  true by default.

**Non-Goals:**

- **A SQLite-backed session handler.** Rejected outright below; not a deferred
  alternative.
- **Changing session semantics.** Duration, sliding renewal, cookie attributes,
  and which routes start a session are all settled by the previous change and
  are not touched.
- **Encrypting session files at rest.** They land on a volume that already holds
  the Plex token in `plex-connection.json`, at higher sensitivity, unencrypted.
  Treating sessions differently would be theatre.
- **Migrating existing sessions.** The sessions in `/tmp` are lost once, on the
  upgrade that deploys this. That is the same sign-out every update causes today.

## Decisions

### 1. A directory under `/config`, not the SQLite database

The database was considered and is rejected, and the codebase already contains
the argument. `PlexConnectionStore` explains why it keeps its own file:

> *"The database is specified as a pure cache of Plex data that is safe to
> delete; a credential in it would make deleting it destructive."*

Sessions fail that test identically. Putting them in `marquee.sqlite` would make
"delete the cache and re-import" also mean "sign everyone out", which is exactly
the coupling that reasoning exists to prevent. Two further objections:

- SQLite over a network mount is *worse* than `flock`, not better — its own
  documentation warns it can corrupt the database. It would aim the risk at the
  one file whose loss actually costs the user something.
- It would add a write to the main database on every session-bearing request.

The precedent is clear and this change follows it: **its own directory, outside
the database, on the volume.**

### 2. `SESSION_DIR`, defaulting to `/config/sessions`

Good default, documented escape — rather than a safe default nobody changes.

| | Default `/config` | Default `/tmp`, opt in |
| --- | --- | --- |
| Who gets the fix | everyone | only those who read the docs and act |
| Who can be hurt | network-mount installs, who can opt out | nobody |
| Matches the reported problem | yes | no — the user asking for this would still have to find a setting |

Defaults are what ship. A durability fix that requires opting in is a fix almost
nobody receives, and the person who reported the problem would still be signing
in after every update until they found the variable.

The variable is deliberately a *path* rather than a boolean. `SESSION_DIR=/tmp`
restores today's behaviour exactly, and anything else — a tmpfs mount, a
different volume — works without Marquee needing to have anticipated it.

**Alternative considered: a boot-time `flock` probe** in `init-marquee-config`
that falls back to `/tmp` on failure or timeout. Rejected. It only detects a
mount that fails *immediately*; the failure that actually hurts is a lock that
succeeds at boot and stalls later under load. It would add real logic to
container startup in exchange for confidence it cannot justify, and it makes
where sessions live nondeterministic — which is worse to support than a setting
the user chose.

**Alternative considered: unconditional, no setting.** Defensible, given
`/config` is already read every request. Rejected because the failure mode it
leaves unaddressed is total: if locking stalls, every session-bearing request
stalls with it, and the user's only recourse would be downgrading.

Follows the naming and shape of `DATA_DIR` and `POSTERS_DIR` exactly, including
`rtrim()` of a trailing slash, so it reads as one of the family rather than a
special case.

### 3. The path is applied in `NativeSession::start()`

Next to `use_strict_mode` and `gc_maxlifetime`, before `session_start()`, where
the class docblock already explains why these belong to Marquee rather than the
runtime. `session.save_path` has the same property: inert once a session is
active, and silently replaceable by a base image if left unset.

This also keeps the fix in the code rather than in the image — no `Dockerfile`
change, so no rebuild-and-smoke-test cycle for the PHP settings themselves. The
container work is limited to creating the directory.

### 4. The directory is created in two places, deliberately

- **`init-marquee-config/run`** adds it to the existing `mkdir -p` list. The
  existing `lsiown -R abc:abc /config` then covers ownership with no new
  permission handling. This is the normal path.
- **`NativeSession::start()`** creates it if absent. This covers the cases the
  init script cannot: `SESSION_DIR` pointed somewhere the script does not know
  about, and any non-container deployment.

Belt and braces, for a cheap `is_dir()` check. The failure it prevents is total —
an unwritable save path makes `session_start()` fail and the application
unenterable — and the spec requires it, so it is not merely defensive.

Directory mode is `0700`. PHP writes session files `0600` already; the directory
should not be looser than its contents. The container runs everything as `abc`,
so nothing else needs access.

### 5. Wiring follows `DATA_DIR`'s path exactly

```php
// AppConfig::fromEnv()
sessionDir: rtrim(Env::str('SESSION_DIR', '/config/sessions'), '/'),

// bootstrap.php
SessionInterface::class => static fn (AuthConfig $auth, AppConfig $app): SessionInterface
    => new NativeSession($auth->sessionDuration, $app->sessionDir),
```

`NativeSession` gains a second constructor argument, keeping `App\Support` free
of `App\Config` as the previous change established. It takes a plain string for
the same reason it takes a plain int.

`AppConfig` rather than `AuthConfig` is the right home: it is a directory, and
the other two directories live there. `AuthConfig` is documented as being about
"how long a session lasts" and holds no paths.

## Risks / Trade-offs

- **A network-mounted `/config` stalls on `flock`** → The scenario this change is
  most exposed to, and the reason `SESSION_DIR` exists. Two things bound it:
  the previous change removed session startup from `/health` and the wall, so
  lock frequency dropped from roughly 22,000/day to real user requests only; and
  the escape hatch is one variable, documented next to the default.

- **The directory is unwritable and the app becomes unenterable** → Created by
  the init script and again on first use, at `0700`, owned by the same user
  everything else runs as. The recursive `lsiown` already covers the container
  case.

- **Session files land in the user's backups** → They hold the authenticated
  flag, a CSRF token, and possibly a short-lived sign-in request. They do *not*
  hold the Plex token, which lives in `plex-connection.json` — already on that
  volume, at higher sensitivity. No new class of secret is exposed.

- **Files accumulate for thirty days rather than twenty-four minutes** → A
  consequence of the previous change, not this one, and only real sessions are
  retained now that public routes start none. For a single-user app that is a
  handful of small files.

- **One more environment variable to support** → Accepted. It is the price of
  the escape hatch, it follows the established naming, and the default is the
  answer for nearly every install.

- **Sessions in flight are lost on the upgrade that deploys this** → Unavoidable;
  the old sessions are in the old `/tmp`. Identical to what every update does
  today, and the last time it happens.

## Migration Plan

No data migration. Deploying recreates the container, which clears `/tmp` and
ends existing sessions exactly as an update already does. Users sign in once
more; from then on, updates stop signing them out.

Rollback is a plain revert. An older image ignores `SESSION_DIR` and writes to
`/tmp` again — sessions written under the new behaviour are simply not found,
which reads as a single sign-out rather than an error. The directory left on the
volume is inert.

## Open Questions

None blocking. One worth revisiting only if it is ever reported: whether a stalled
lock on a network mount should be detected at runtime rather than left to the
user's `SESSION_DIR` setting. That needs evidence from a real install before it
justifies logic, and the escape hatch means nobody is stuck while that evidence
is gathered.
