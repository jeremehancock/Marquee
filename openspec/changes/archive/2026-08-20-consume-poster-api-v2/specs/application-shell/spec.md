## MODIFIED Requirements

### Requirement: Poster provider attribution

The shared layout SHALL credit the providers whose artwork reaches the user
through Marquee's poster source: TMDB, TheTVDB, fanart.tv and TVmaze. The credit
SHALL consist of the label `Posters provided by:` followed by each provider's
logo, and each logo SHALL link to that provider's own website in a new browsing
context. The logos SHALL be served as local static assets, so the credit renders
without a request to any third party.

The credit SHALL be part of the same footer chrome that carries the product name
and version, and SHALL be reachable on every screen size: on a pointer/desktop
screen it appears in the page footer, and on a narrow screen — where the page
footer is hidden — it appears in the navigation drawer's footer. In both places
it SHALL sit above the product name and version rather than replacing them.

The provider list SHALL be defined in exactly one place, so the two footers can
never credit different sets of providers. Providers whose artwork the poster
source does not return SHALL NOT be credited.

The order the providers are credited in SHALL be the order the Find Posters tab
sections them in, and the two SHALL NOT disagree. This credit is the definition
of that order; the section order follows it.

This footer credit stands for the set of services Marquee draws on. It does not
discharge an attribution a licence attaches to an individual image — that is
carried, for the subset of candidates the poster source marks as requiring it, by
the per-candidate credit specified under `poster-sources`. The two coexist:
neither replaces the other, and crediting a service here SHALL NOT be treated as
having credited any particular poster it supplied.

#### Scenario: Page footer credits the providers

- **WHEN** any HTML page is rendered
- **THEN** its footer displays the label `Posters provided by:` with the TMDB,
  TheTVDB, fanart.tv and TVmaze logos
- **AND** the product name and version continue to be displayed below them

#### Scenario: Provider logo links to the provider

- **WHEN** a user activates a provider's logo in either footer
- **THEN** that provider's own website opens in a new browsing context, leaving
  the current page in place

#### Scenario: Attribution is reachable on a phone

- **WHEN** a user on a narrow screen opens the navigation drawer
- **THEN** the drawer's footer displays the same credit line and logos, above
  its product name and version
- **AND** the page footer, which is hidden at that width, is not the only place
  the credit appears

#### Scenario: Logos are served locally

- **WHEN** a page carrying the attribution is rendered
- **THEN** each logo resolves to an asset served by Marquee itself
- **AND** no provider logo is loaded from a third-party host

#### Scenario: Uncredited providers are absent

- **WHEN** the attribution is rendered
- **THEN** it credits only providers the poster source returns artwork from
- **AND** Mediux, which it does not, is absent

#### Scenario: Credit order and section order agree

- **WHEN** the order the footer credits its providers in is compared with the
  order the Find Posters tab shows its sections in
- **THEN** the two are the same
