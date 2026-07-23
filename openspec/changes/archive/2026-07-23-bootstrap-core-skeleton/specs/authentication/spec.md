## ADDED Requirements

### Requirement: Session-based login
The system SHALL authenticate users against a single username and password
supplied via environment variables and establish a server-side session on
success.

#### Scenario: Successful login
- **WHEN** a user submits credentials matching `AUTH_USERNAME` and
  `AUTH_PASSWORD`
- **THEN** the system creates an authenticated session and redirects to the
  home page

#### Scenario: Failed login
- **WHEN** a user submits credentials that do not match the configured values
- **THEN** the system re-renders the login page with an error and does not
  create a session

### Requirement: Protected routes require authentication
The system SHALL require a valid authenticated session for all routes except
the login route, the logout route, the health endpoint, and static assets.

#### Scenario: Unauthenticated access is redirected
- **WHEN** an unauthenticated user requests a protected route
- **THEN** the system redirects to the login page

#### Scenario: Health and assets remain public
- **WHEN** an unauthenticated user requests `/health` or a static asset
- **THEN** the system serves the response without requiring authentication

### Requirement: Session expiry
The system SHALL expire an authenticated session after the number of seconds
configured by `SESSION_DURATION`, after which access is treated as
unauthenticated.

#### Scenario: Expired session is rejected
- **WHEN** the time since a session was established exceeds `SESSION_DURATION`
- **THEN** the next request to a protected route is treated as unauthenticated
  and redirected to login

### Requirement: Logout
The system SHALL provide a logout action that destroys the current session.

#### Scenario: User logs out
- **WHEN** an authenticated user triggers logout
- **THEN** the system destroys the session and redirects to the login page

### Requirement: Authentication bypass
The system SHALL support an `AUTH_BYPASS` option that, when enabled, grants
access without login for deployments on a trusted network.

#### Scenario: Bypass grants access
- **WHEN** `AUTH_BYPASS` is `true` and any route is requested
- **THEN** the system treats the request as authenticated without presenting the
  login page

#### Scenario: Bypass disabled enforces login
- **WHEN** `AUTH_BYPASS` is `false` or unset and an unauthenticated user
  requests a protected route
- **THEN** the system redirects to the login page
