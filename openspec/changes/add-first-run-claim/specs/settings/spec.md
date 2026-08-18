## ADDED Requirements

### Requirement: The Plex server address is set in the application
The system SHALL take the Plex server address from the settings store, entered
when the install is claimed and changeable afterwards by the owner, so that no
application configuration remains in the container's environment.

This is safe only because claiming replaced what the address used to provide. It
SHALL NOT be read as a general finding that a trust anchor can be moved into a
browser: the address stopped being one when a separate mechanism took over the
job (see `authentication`).

The address entered during the claim MAY be probed with an unauthenticated
request to the server's identity endpoint so that the server's name can be shown
back before the user commits. That probe is a **usability** feature and SHALL be
specified as one. It catches a typo, a wrong port, and an unreachable host. It
SHALL NOT be described or relied on as a security control, because the party
entering the address chose the server, and a server that returns a plausible
identity response satisfies it.

An address that cannot be used SHALL be reported the way an absent one is, as the
configuration already specifies, rather than raising.

#### Scenario: The address is entered when claiming
- **WHEN** an install is claimed with a server address
- **THEN** that address is stored and used for every later request to Plex

#### Scenario: The address is changed later
- **WHEN** the owner changes the server address after claiming
- **THEN** the new address is in effect on the next request
- **AND** no container change is required

#### Scenario: A typo is caught before it is committed
- **WHEN** an address is entered that no Plex server answers on
- **THEN** the user is told before the address is stored

#### Scenario: The probe is not treated as proof
- **WHEN** an address is entered that answers with a plausible identity response
- **THEN** the system does not treat that as evidence the server is the user's
- **AND** ownership is still established by the claim and by the sign-in

## REMOVED Requirements

### Requirement: The Plex server address is withheld from the screen
**Reason**: The reason it was withheld has been discharged. It was held back
because it was a trust anchor — an assertion only someone with host access could
make — and moving it into the browser without replacing that property would have
let the first stranger to reach an unconfigured install claim it. The claim code
introduced in this change carries that property now, so the address is an
ordinary setting.

**Migration**: Replaced by "The Plex server address is set in the application"
above. The property itself moves to `authentication`, under "An install is
claimed before it can be signed in to".
