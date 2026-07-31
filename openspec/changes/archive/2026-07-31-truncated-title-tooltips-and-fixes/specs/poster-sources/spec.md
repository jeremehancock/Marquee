## MODIFIED Requirements

### Requirement: The poster search service is named to users
The product documentation SHALL identify posteria.app as the hosted poster
search service that backs Find Posters, as a general statement only — it SHALL
NOT document the service's endpoints, request or response formats, or internal
behavior, and it SHALL NOT elaborate on running or self-hosting the service.

The base URL of the poster search service SHALL remain configurable through the
`POSTER_SOURCE_URL` environment variable, with the same default and behavior as
before, but SHALL NOT be presented to users as a setting: it SHALL NOT appear in
the documented configuration table or in any example deployment configuration.
Find Posters is a hosted service users are not expected to repoint, so offering
the variable as a knob invites configuration that is not supported.

#### Scenario: README names the service
- **WHEN** a reader reviews the README's description of Find Posters
- **THEN** posteria.app is named as the service the search runs against

#### Scenario: No implementation detail is published
- **WHEN** the documentation describes the poster search service
- **THEN** it states only what the service provides, not how it is called or
  how it produces its results

#### Scenario: No self-hosting guidance
- **WHEN** the documentation describes the poster search service
- **THEN** it does not invite the reader to run their own instance or explain
  how to point Marquee at one

#### Scenario: The service URL is not offered as a setting
- **WHEN** a reader reviews the documented configuration table and the example
  deployment configuration
- **THEN** `POSTER_SOURCE_URL` is not listed among them

#### Scenario: The service URL still works when set
- **WHEN** `POSTER_SOURCE_URL` is set in the environment
- **THEN** poster searches are directed at that base URL, exactly as before, and
  when it is unset the default hosted service is used

### Requirement: Search outcomes are distinguishable
The system SHALL distinguish between the reasons a poster search produced no
usable candidates, and SHALL report each to the user in terms that indicate
whether the situation is transient, actionable, or final. In every case the
user's existing poster SHALL be left unchanged.

#### Scenario: Title did not match anything
- **WHEN** the poster source reports that no work matched the search
- **THEN** the system tells the user there was no match for the title

#### Scenario: Work found but has no artwork
- **WHEN** the poster source matches the work but returns no candidates
- **THEN** the system tells the user that the title was found but no posters are
  available, which is reported differently from a title that did not match

#### Scenario: Search is temporarily unavailable
- **WHEN** the poster source cannot be reached, or reports that its upstream
  providers are unavailable
- **THEN** the system tells the user the search is temporarily unavailable and
  that retrying may succeed

#### Scenario: Too many searches
- **WHEN** the poster source reports that the search was rate limited
- **THEN** the system tells the user to wait before searching again

#### Scenario: Partial results
- **WHEN** the poster source returns candidates but reports that one or more of
  its providers failed
- **THEN** the system shows the candidates it received and indicates the results
  may be incomplete

#### Scenario: Failure leaves the poster untouched
- **WHEN** a poster search fails for any reason
- **THEN** the user's existing poster is unchanged
