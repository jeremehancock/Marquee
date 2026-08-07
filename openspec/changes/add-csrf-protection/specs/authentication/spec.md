## ADDED Requirements

### Requirement: State-changing requests must originate from Marquee
The system SHALL refuse any state-changing request that does not carry a secret
token bound to the requesting session, and SHALL do so before the request
reaches the handler that would act on it. A refused request SHALL change
nothing.

The token SHALL be generated once per session, held server-side, and compared in
a way that does not leak its value through timing. It SHALL NOT appear in a URL,
because URLs are logged, shared, and sent as referrers.

Requests that only read SHALL NOT require a token. `GET`, `HEAD`, and `OPTIONS`
are unaffected, so the poster wall, the health endpoint, and ordinary page loads
behave exactly as before.

Protection SHALL apply while `AUTH_BYPASS` is enabled. Bypass removes the login,
which makes a forged request more valuable rather than less.

This exists because `SameSite=Lax` does not cover the deployment Marquee is
built for. "Site" ignores the port, so every other service on the same host
address is same-site with Marquee and its requests still carry the session
cookie. A self-hosted machine runs many.

Logging in SHALL be the exception to how a refusal is presented. A login whose
token does not match SHALL re-render the login page with an explanation and no
authenticated session, rather than an error page. It is the one route reachable
with no session behind it, so it is the one a user meets after their session has
gone — a container recreation discards them all — and an error page there reads
as a broken installation rather than as a page to submit again. The request is
still refused and still authenticates nobody.

#### Scenario: A state-changing request without a token is refused
- **WHEN** an authenticated session sends a `POST` carrying no token
- **THEN** the system refuses the request with HTTP 403
- **AND** the action is not performed

#### Scenario: A login with a stale token is refused but explained
- **WHEN** a login is submitted with a token that does not match the session's
- **THEN** the system re-renders the login page with an explanation that the
  page expired and should be submitted again
- **AND** no authenticated session is created
- **AND** the response is not an error page

#### Scenario: A state-changing request with the wrong token is refused
- **WHEN** an authenticated session sends a `POST` carrying a token that does
  not match the one held for that session
- **THEN** the system refuses the request with HTTP 403
- **AND** the action is not performed

#### Scenario: One session's token does not work for another
- **WHEN** a request carries a token that is valid for a different session
- **THEN** the system refuses it

#### Scenario: A state-changing request with the right token proceeds
- **WHEN** an authenticated session sends a `POST` carrying the token held for
  that session
- **THEN** the system handles the request normally

#### Scenario: Reading is unaffected
- **WHEN** a `GET` request is made to any route
- **THEN** no token is required

#### Scenario: Bypass does not disable the check
- **WHEN** `AUTH_BYPASS` is enabled and a `POST` arrives without a token
- **THEN** the system refuses it

### Requirement: Marquee's own pages carry the token
The system SHALL include the token in every form it renders that performs a
state-changing request, and SHALL make it available to its own scripts, so that
using Marquee never requires the user to supply it.

The token SHALL be accepted either as a form field or as a request header.
Forms submit a field; scripted requests that build their own body have no form
to carry one, and a header serves both without a second mechanism.

#### Scenario: A rendered form carries the token
- **WHEN** a page containing a state-changing form is rendered
- **THEN** the form includes the session's token as a field

#### Scenario: A scripted request carries the token
- **WHEN** the application's own script sends a state-changing request
- **THEN** the request carries the session's token as a header

#### Scenario: Either carrier is accepted
- **WHEN** a valid token arrives as a form field, or as a header
- **THEN** the system accepts the request in both cases

#### Scenario: The token is not placed in a URL
- **WHEN** any page or request carries the token
- **THEN** it is not present in the query string of any URL
