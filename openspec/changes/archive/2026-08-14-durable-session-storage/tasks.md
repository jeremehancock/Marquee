## 1. `SESSION_DIR` joins the directory family

- [x] 1.1 Add `sessionDir` to `App\Config\AppConfig`, read as `rtrim(Env::str('SESSION_DIR', '/config/sessions'), '/')` — the same shape as `DATA_DIR` and `POSTERS_DIR`, so it reads as one of the family rather than a special case.
- [x] 1.2 Document on the property why it is a path rather than a boolean: `SESSION_DIR=/tmp` restores the previous behaviour exactly, and a tmpfs or a different volume works without Marquee having anticipated it.

## 2. Sessions are written where Marquee decides

- [x] 2.1 Give `App\Support\Session\NativeSession` a second constructor argument, `string $savePath`. Keep `App\Support` free of `App\Config` — a plain string, as the duration is a plain int.
- [x] 2.2 In `start()`, before `session_start()` and alongside the existing `use_strict_mode` and `gc_maxlifetime` calls, create the directory if it is absent (mode `0700` — PHP writes session files `0600`, so the directory must not be looser than its contents) and apply it with `session_save_path()`.
- [x] 2.3 Extend the class docblock: storage location belongs to Marquee for the same reason the cookie's attributes and the retention period do, and it is equally inert once a session is active. Say plainly that this is what makes a session survive the container.
- [x] 2.4 Update the `SessionInterface` factory in `src/bootstrap.php` to inject both `AuthConfig->sessionDuration` and `AppConfig->sessionDir`.

## 3. The container prepares the directory

- [x] 3.1 Add `/config/sessions` to the `mkdir -p` list in `docker/root/etc/s6-overlay/s6-rc.d/init-marquee-config/run`. The existing recursive `lsiown -R abc:abc /config` already covers ownership — do not add permission handling.
- [x] 3.2 Confirm no `Dockerfile` change is needed: the PHP settings are applied by the application, not by an ini file. If that holds, say so explicitly rather than editing the image.

## 4. Tests

- [x] 4.1 Add to `tests/Unit/Support/NativeSessionTest.php` (separate processes, as the file already does): the configured path reaches `session_save_path()`, and a session started against a fresh temporary directory writes its file there rather than in the system temp directory.
- [x] 4.2 Add a test that a missing directory is created on first use, covering the spec scenario and the case where `SESSION_DIR` points somewhere the init script never saw.
- [x] 4.3 Add a test that the created directory is `0700`, so a later refactor cannot silently widen it.
- [x] 4.4 Add to `tests/Unit/Config/` coverage that `SESSION_DIR` defaults to `/config/sessions` and that a trailing slash is trimmed, matching how `DATA_DIR` and `POSTERS_DIR` are covered.
- [x] 4.5 Confirm the whole existing session suite still passes unchanged — duration, sliding renewal, cookie attributes, and which routes start a session are all settled by the previous change and must not shift.

## 5. Docs

- [x] 5.1 Add `SESSION_DIR` to the environment-variable table in `README.md`, next to `DATA_DIR` and `POSTERS_DIR`. State the default and, in one sentence, why someone would change it — a network-mounted `/config` whose file locking misbehaves.
- [x] 5.2 Add the commented `SESSION_DIR` line to the compose example in `README.md`, matching how the other optional variables are shown.
- [x] 5.3 Check whether `docs/` or `CLAUDE.md` state or imply that sessions do not survive a restart, and correct anything that does. If nothing does, say so explicitly rather than inventing edits.
- [x] 5.4 Update the `CsrfMiddleware` carve-out comment, which is written around "PHP sessions live in the container's /tmp, so recreating the container discards them all". That premise no longer holds by default. Keep the carve-out — it is still correct for a genuinely expired token — but stop justifying it with something untrue.

## 6. Correct the archived record

- [x] 6.1 Amend `openspec/changes/archive/2026-08-14-session-duration-governs-all-layers/design.md`, which deferred this work on the grounds that `/config` storage "takes on a class of hang the application does not currently have, on a path every request travels". Note that the premise was overstated — `PlexConnectionStore` already reads `/config/data` on every authenticated request and the SQLite database already lives there — and that the real difference is the exclusive lock the file session handler holds across a request.
- [x] 6.2 Keep the amendment to a correcting note. Do not rewrite the archived reasoning, and do not restate this change's design there — the archive is a record of what was decided and why, and it should show that the premise was revisited.

## 7. Gates

- [x] 7.1 Run `composer test`, `composer stan`, and `composer cs` — all three must pass before any commit.
- [x] 7.2 Run `openspec validate durable-session-storage --strict`.

## 8. Live validation

- [x] 8.1 Build the image locally and smoke-test `/health`, per `docs/docker.md`.
- [x] 8.2 In the running container, confirm session files appear under `/config/sessions` and no longer in `/tmp`, and that the directory is owned by `abc` with mode `0700`.
- [x] 8.3 The one that matters, and the reason this change exists: sign in, then **recreate the container** with the volume retained, and confirm you are still signed in. This is not observable from any test suite.
- [x] 8.4 Confirm the escape hatch: start with `SESSION_DIR=/tmp` and check sessions are written there and everything still works, so the way back is real rather than theoretical.
