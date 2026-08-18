## MODIFIED Requirements

### Requirement: Replacement images are validated
The system SHALL accept a replacement image only when it is a JPEG, PNG, or WebP
— determined by inspecting the image data rather than trusting its name or
declared type — and is no larger than `MAX_FILE_SIZE`. A rejected image SHALL
leave the existing poster untouched.

A replacement supplied by URL SHALL additionally be fetched only from a public
internet address. Before any connection is made, the system SHALL resolve the
host and SHALL refuse the fetch unless **every** resolved address is a global
unicast address. Loopback, private, link-local, unique-local, carrier-grade NAT,
multicast, reserved, and unspecified addresses SHALL be refused, in IPv4 and
IPv6, including IPv4-mapped and IPv4-compatible IPv6 forms. The rule SHALL be
expressed as "must be public" rather than as a list of forbidden ranges, so that
a range nobody anticipated fails closed.

Requiring every resolved address to pass, rather than one, is deliberate: a host
resolving to both a public and a private address is the shape of an attack, and
choosing among them is a coin flip.

The system SHALL apply the same check to every redirect it follows, and SHALL
bound the number of redirects. A check applied only to the submitted address is
no check at all, because a public host may answer with a redirect to a private
one.

The fetch SHALL be restricted to the standard web ports, 80 and 443, so that the
capability to scan ports is removed rather than narrowed to the hosts that pass
the address rule.

**This restriction SHALL apply only to a URL a user supplied.** It SHALL NOT
apply to the connected Plex server, the poster search service, the Plex sign-in
requests, or the update check. Marquee's Plex server is normally *at* a private
address, and reaching it is the product working as intended; applying this rule
to those destinations would break nearly every install. Any future caller that
fetches an address a user typed SHALL use the restricted path.

A refused address SHALL be reported distinctly from a fetch that failed, so that
an owner who supplies an address on their own network is told why it cannot be
used rather than being left to guess.

The system SHALL NOT offer a setting, environment variable, or other means of
disabling this restriction. A guard an install can switch off is not a guarantee.

**Known limitation, recorded so it is not mistaken for coverage:** a host that
resolves to a public address when checked and a private one when connected —
DNS rebinding — defeats this. Mitigating it requires pinning the validated
address into the connection, which is disproportionate to the threat and is
deliberately not done. The cases this requirement exists to stop, such as
literal loopback, private, and link-local addresses, involve no DNS resolution
at all.

#### Scenario: Disallowed type is rejected
- **WHEN** a user supplies a replacement whose image data is not JPEG, PNG, or
  WebP
- **THEN** the system rejects it with a clear message and leaves the poster
  unchanged

#### Scenario: Oversized image is rejected
- **WHEN** a user supplies a replacement larger than `MAX_FILE_SIZE`, by file or
  by URL
- **THEN** the system rejects it with a clear message and leaves the poster
  unchanged

#### Scenario: Unusable URL is rejected
- **WHEN** a user supplies a URL that is not a valid `http`/`https` address, or
  that cannot be fetched, or that returns nothing
- **THEN** the system rejects it with a clear message and leaves the poster
  unchanged

#### Scenario: A private network address is refused
- **WHEN** a user supplies a poster URL whose host resolves to a loopback,
  private, link-local, unique-local, carrier-grade NAT, multicast, reserved, or
  unspecified address, in IPv4 or IPv6
- **THEN** no connection is made to it
- **AND** the poster is left unchanged
- **AND** the refusal states that the address is not a public one, distinctly
  from a fetch that failed

#### Scenario: A host resolving to both public and private addresses is refused
- **WHEN** a poster URL's host resolves to more than one address and any of them
  is not a global unicast address
- **THEN** the fetch is refused

#### Scenario: A redirect to a private address is refused
- **WHEN** a poster URL resolves to a public address and that host responds with
  a redirect to a private one
- **THEN** the redirect is not followed
- **AND** the poster is left unchanged

#### Scenario: A non-standard port is refused
- **WHEN** a poster URL names a port other than 80 or 443
- **THEN** the fetch is refused before any connection is made

#### Scenario: The Plex server is reachable at a private address
- **WHEN** an install's Plex server is at a private address such as
  `http://192.168.1.10:32400`
- **THEN** importing, sending, fetching, and browsing Plex posters all work
  normally
- **AND** the restriction on user-supplied poster URLs does not apply to them

#### Scenario: An ordinary public poster URL still works
- **WHEN** a user supplies a poster URL on a public host over http or https
- **THEN** it is fetched and validated exactly as before

#### Scenario: Applying a found poster is governed by the same rule
- **WHEN** a user applies a candidate from the poster search, which is submitted
  as a URL for the system to fetch
- **THEN** the same address rule applies to it
- **AND** an ordinary candidate on a public content delivery host is fetched
  normally
