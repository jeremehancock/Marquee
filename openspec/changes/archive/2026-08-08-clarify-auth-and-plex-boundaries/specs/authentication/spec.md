## MODIFIED Requirements

### Requirement: Authentication bypass
The system SHALL support an `AUTH_BYPASS` option that, when enabled, grants
access without login for deployments on a trusted network.

What that grants has changed with the Plex connection. Marquee performs every
Plex operation with the credential it stores, and no route distinguishes one
visitor from another, so bypassing authentication grants use of that credential:
sending artwork to the user's Plex library, changing and deleting posters, and
disconnecting the install. Restricting sign-in to the server's owner does not
narrow this — it guarantees the stored credential is a privileged one. Anything
describing this option to a user SHALL say so rather than implying that the
owner-only rule limits what a visitor can do.

#### Scenario: Bypass grants access
- **WHEN** `AUTH_BYPASS` is `true` and any route is requested
- **THEN** the system treats the request as authenticated without presenting the
  login page

#### Scenario: Bypass disabled enforces login
- **WHEN** `AUTH_BYPASS` is `false` or unset and an unauthenticated user
  requests a protected route
- **THEN** the system redirects to the login page
