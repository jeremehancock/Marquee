## 1. Regeneration on the session abstraction

- [x] 1.1 Add `regenerate(): void` to `SessionInterface`, documented as issuing
      a new identifier while preserving the session's contents
- [x] 1.2 Implement it in `NativeSession` as `session_regenerate_id(true)`,
      guarded by the same active-session and `headers_sent()` checks the rest of
      the class uses
- [x] 1.3 Implement it in `ArraySession` as an observable counter exposed by
      `regenerations(): int`, so the behaviour can be asserted without touching
      global session state

## 2. The cookie's attributes

- [x] 2.1 In `NativeSession::start()`, call `session_set_cookie_params()` with
      `httponly` true, `samesite` `Lax`, `path` `/`, and no `secure`, before
      `session_start()` and inside the existing guard
- [x] 2.2 Set `session.use_strict_mode` in the same place, so a session
      identifier the system did not issue is refused
- [x] 2.3 Record in the class docblock why `Secure` is absent, so the omission
      reads as a decision rather than a gap

## 3. Regenerate on login

- [x] 3.1 In `SessionAuthenticator::attempt()`, regenerate before marking the
      session authenticated, and only on a successful match

## 4. Tests

- [x] 4.1 Unit-test that a successful `attempt()` regenerates exactly once and
      that a failed one does not regenerate at all
- [x] 4.2 Unit-test that values stored in the session before a successful login
      are still readable afterwards
- [x] 4.3 Assert the issued `Set-Cookie` carries `HttpOnly` and `SameSite=Lax`
      and does not carry `Secure`
- [x] 4.4 Confirm the existing authentication, expiry, logout, and bypass tests
      still pass unchanged

## 5. Documentation

- [x] 5.1 Check whether `README.md`'s security section should say what the
      session cookie does and does not protect, and correct it or record
      explicitly that it needs no change

## 6. Verification

- [x] 6.1 Run `composer test`, `composer stan`, and `composer cs`
- [x] 6.2 Run `openspec validate harden-session-cookie --strict`
- [x] 6.3 In a built image reached over plain HTTP, confirm logging in still
      works and the response's `Set-Cookie` carries `HttpOnly` and
      `SameSite=Lax`
