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

Each logo link SHALL name its provider through the shared custom tooltip and
SHALL NOT carry a native `title`, so the hint the credit offers is drawn in the
application's own tooltip rather than the browser's. This is the same rule the
`Consistent custom tooltips` requirement states for the application as a whole;
it is restated here because this credit is where a native tooltip most recently
survived, and because the hosts are links whose only content is an image, where
a `title` is easy to reintroduce as if it were the accessible name. It is not:
the link's accessible name SHALL come from the logo's alternative text, so it is
exposed to assistive technology and on touch devices, where no tooltip is shown.
The tooltip SHALL name the provider it links to, and the two SHALL NOT disagree.

The hint each logo carries is a genuine hint rather than a repetition of visible
text — a logo is a mark, not a readable name — so it SHALL be shown whenever its
device qualifies, without any truncation condition.

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

#### Scenario: Hovering a logo shows the app's tooltip

- **WHEN** a user on a hover-capable pointer device hovers a provider's logo in
  either footer
- **THEN** the shared themed tooltip is shown naming that provider
- **AND** no native browser tooltip appears alongside it

#### Scenario: A logo link carries no native title

- **WHEN** the attribution is rendered
- **THEN** none of its logo links carries a `title` attribute

#### Scenario: A logo link keeps its accessible name without a title

- **WHEN** assistive technology reports a provider's logo link, on any device
- **THEN** it announces that provider's name, taken from the logo's alternative
  text rather than from a tooltip

#### Scenario: The tooltip and the accessible name agree

- **WHEN** a provider's tooltip text is compared with the name its link exposes
  to assistive technology
- **THEN** the two name the same provider

#### Scenario: Touch shows no tooltip and loses nothing

- **WHEN** a user on a touch device taps a provider's logo
- **THEN** no tooltip is shown
- **AND** the link still opens that provider's website, and its accessible name
  is unaffected
