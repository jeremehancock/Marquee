## 1. The token

- [x] 1.1 Add a session-backed `CsrfGuard` service exposing `token()`, which
      generates a cryptographically random value on first use and remembers it
      for the session, and `matches(?string $candidate): bool`, comparing with
      `hash_equals` and refusing null and empty
- [x] 1.2 Register it in `src/bootstrap.php` against the shared session

## 2. Enforcement

- [x] 2.1 Add a `CsrfMiddleware` that lets `GET`, `HEAD`, and `OPTIONS` through
      untouched, and for every other method requires a token matching the
      session's, read from the `_token` parsed-body field or the
      `X-CSRF-Token` header
- [x] 2.2 Refuse a mismatch with HTTP 403 raised through the existing error
      middleware, so the response honours `Accept` like every other error
- [x] 2.4 Carve out `POST /login`: a mismatch re-renders the login page saying
      the page expired and should be submitted again, authenticating nobody,
      because it is the only state-changing route a user reaches with no session
      behind it and an error page there reads as a broken install
- [x] 2.3 Register it in `src/bootstrap.php` before `addBodyParsingMiddleware()`
      so it runs innermost — after the body is parsed and after the session
      exists — and record why in the comment that already explains the ordering

## 3. Handing the token to the browser

- [x] 3.1 Add `csrf_field()` and `csrf_token()` Twig functions alongside the
      existing `asset()` function, resolving at render time rather than at
      container build
- [x] 3.2 Add a `csrf-token` meta tag to `templates/layout.html.twig`

## 4. Carry it — forms

- [x] 4.1 `templates/login.html.twig` — the `/login` form
- [x] 4.2 `templates/plex.html.twig` — the `/plex/import` form
- [x] 4.3 `templates/connect.html.twig` — the `/plex/connection/sign-out` form
- [x] 4.4 `templates/orphans/_results.html.twig` — the `/orphans/delete` form
- [x] 4.5 `templates/partials/gallery_results.html.twig` — the `send-to-plex`,
      `fetch-from-plex`, and `delete` forms

## 5. Carry it — scripted requests

- [x] 5.1 Read the token once from the meta tag in `public/assets/gallery.js`
- [x] 5.2 Send `X-CSRF-Token` on the orphan delete fetch (`js-mutate` form)
- [x] 5.3 Send it on `/orphans/delete-all`
- [x] 5.4 Send it on `/plex/connection/sign-in`
- [x] 5.5 Send it on `/plex/import`
- [x] 5.6 Send it on `/library/{category}/change/upload` and `/change/url`
- [x] 5.7 Send it on the shared `js-mutate` form handler

## 6. Tests

- [x] 6.1 Unit-test `CsrfGuard`: the token is stable within a session, differs
      between sessions, and `matches()` refuses null, empty, and a wrong value
- [x] 6.2 Unit-test the middleware: safe methods pass untouched; an unsafe
      method with no token, a wrong token, or another session's token is
      refused; a correct token in the field or the header is accepted
- [x] 6.3 Functional-test that every state-changing route refuses a request
      carrying no token, and that nothing is changed by the refusal
- [x] 6.4 Functional-test that the check still applies with `AUTH_BYPASS`
      enabled
- [x] 6.7 Functional-test that a login with a stale token re-renders the login
      page with an explanation, is not an error page, and authenticates nobody
- [x] 6.5 Update the existing functional tests that post to these routes so they
      carry a token, and confirm they pass unchanged otherwise
- [x] 6.6 Assert a rendered form contains the field and no URL carries the token

## 7. Documentation

- [x] 7.1 Update the README security section, which will otherwise describe a
      protection story this change has moved on from

## 8. Verification

- [x] 8.1 Run `composer test`, `composer stan`, and `composer cs`
- [x] 8.2 Run `openspec validate add-csrf-protection --strict`
- [x] 8.3 In a built image, confirm logging in, importing, changing a poster,
      deleting a poster, deleting an orphan, and disconnecting Plex all still
      work, and that a `POST` without a token is refused with 403
