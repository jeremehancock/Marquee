## Why

Marquee now honours `SESSION_DURATION` in every layer it controls, so a session
lasts thirty days of disuse and slides with every request. One thing still
overrides all of it: the session files live in the container's `/tmp`, which is
not a volume. Recreating the container discards every session, and pulling a new
image recreates the container.

So the effective session length is not thirty days. It is "until the next
update" — which, on a self-hosted install with automatic image updates, can be
days. Signing in again requires plex.tv, so a routine update puts a third-party
dependency in front of a user who did nothing but accept a new version.

This is the last remaining reason a user signs in more often than
`SESSION_DURATION` promises. Everything else was fixed; this was deferred
because it needed its own decision, and this change makes it.

## What Changes

- Session files are written to a directory under `/config`, which is a volume,
  rather than the container's `/tmp`. A session survives recreating the
  container, so an update no longer signs everybody out.
- A new `SESSION_DIR` environment variable names that directory, defaulting to
  `/config/sessions`. It exists as an escape hatch: PHP's file session handler
  takes an exclusive lock for the duration of every request, `/config` is
  routinely a network share on a self-hosted install, and a mount whose locking
  misbehaves must have a way back to a local directory without downgrading
  Marquee. Setting `SESSION_DIR=/tmp` restores exactly today's behaviour.
- The directory is created and given the container's ownership at startup,
  alongside the other `/config` directories.
- The session store's durability gains a spec requirement. Retention *duration*
  has one; surviving a restart does not, which is how a thirty-day window ended
  up capped by an implementation detail nobody had written down.

Not breaking. No existing install needs to set anything. The one visible effect
is the intended one: signing in survives an update. Sessions in flight at the
moment of upgrade are lost once, because they are in the old `/tmp`.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `authentication`: Gains a requirement that a session survives the process and
  container that created it, and that where sessions are stored is Marquee's
  decision rather than the runtime's default. The existing retention requirement
  says how long an idle session is kept but says nothing about what it is kept
  *in*, which is the gap that let `/tmp` silently cap the window.

## Impact

- **Code**: `App\Support\Session\NativeSession` (session save path applied
  before the session starts, next to the two settings already applied there);
  `App\Config\AppConfig` (the new `SESSION_DIR` value, read once at bootstrap);
  the session wiring in `src/bootstrap.php`.
- **Container**: `docker/root/etc/s6-overlay/s6-rc.d/init-marquee-config/run`
  creates the directory. The existing recursive `lsiown -R abc:abc /config`
  already covers ownership, so no permission handling is added.
- **Configuration**: One new optional environment variable, `SESSION_DIR`. No
  existing variable changes name, default, or meaning.
- **Docs**: `README.md`'s environment-variable table and the compose example
  gain `SESSION_DIR`. The `/tmp` caveat stated in the previous change's design
  is resolved and should not survive as a documented limitation.
- **Operational**: Session files land on a volume the user backs up. They hold
  the authenticated flag, a CSRF token, and possibly a short-lived Plex sign-in
  request — no Plex token, which lives in the connection store and is already
  on that volume.
- **Corrects an earlier record**: the archived
  `2026-08-14-session-duration-governs-all-layers` design argued that `/config`
  storage "takes on a class of hang the application does not currently have, on
  a path every request travels". That overstated the case — `PlexConnectionStore`
  already reads `/config/data` on every authenticated request, and the SQLite
  database already lives there. The real difference is narrower and is the
  subject of this change's design. The archived file is corrected rather than
  left to mislead a future reader.
