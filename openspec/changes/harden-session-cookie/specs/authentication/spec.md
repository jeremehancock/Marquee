## ADDED Requirements

### Requirement: The session cookie's attributes are Marquee's decision
The system SHALL set the session cookie's attributes itself before the session
is started, rather than inheriting whatever the runtime's configuration
supplies. The cookie SHALL be marked `HttpOnly` and SHALL carry
`SameSite=Lax`.

`SameSite=Lax` is what stops another site driving a state-changing request with
the user's session attached. It is set here rather than left to the runtime
because a default is not a decision: an image rebuild, a base-image change, or a
different `php.ini` would silently remove it, and nothing would fail.

The system SHALL NOT set the `Secure` attribute. Marquee is routinely reached
over plain HTTP on a local network, and a `Secure` cookie is never sent over
HTTP, so setting it unconditionally would prevent logging in at all on those
installs. Withholding it is a deliberate trade, not an oversight.

#### Scenario: The cookie is not readable from script
- **WHEN** the session cookie is issued
- **THEN** it carries the `HttpOnly` attribute

#### Scenario: The cookie is withheld from cross-site requests
- **WHEN** the session cookie is issued
- **THEN** it carries `SameSite=Lax`

#### Scenario: Plain HTTP installs can still log in
- **WHEN** Marquee is reached over plain HTTP
- **THEN** the session cookie is sent with subsequent requests, because it is
  not marked `Secure`

### Requirement: Logging in issues a new session identifier
The system SHALL replace the session identifier on a successful login, carrying
the session's contents across, and SHALL discard the identifier that was in use
before. It SHALL also refuse to adopt a session identifier it did not itself
issue.

Together these close session fixation. Regeneration means an identifier known to
somebody else before the user logged in is worthless afterwards; refusing an
unissued identifier means one cannot be planted to begin with. Either alone
leaves a gap, so both are required.

A failed login SHALL NOT regenerate the identifier: nothing has been granted, so
there is nothing to protect, and rotating on failure would let an unauthenticated
caller churn session identifiers at will.

#### Scenario: A successful login replaces the identifier
- **WHEN** a user submits credentials matching `AUTH_USERNAME` and
  `AUTH_PASSWORD`
- **THEN** the session identifier in use afterwards differs from the one the
  request arrived with
- **AND** the session is authenticated

#### Scenario: Session contents survive the replacement
- **WHEN** the session identifier is replaced on login
- **THEN** values stored in the session before the login are still readable
  afterwards

#### Scenario: A failed login does not replace the identifier
- **WHEN** a user submits credentials that do not match the configured values
- **THEN** the session identifier is unchanged
- **AND** no authenticated session is created

#### Scenario: An identifier the system did not issue is refused
- **WHEN** a request arrives carrying a session identifier that the system never
  issued
- **THEN** the system does not adopt it as a valid session
