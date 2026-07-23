## ADDED Requirements

### Requirement: Logout reflects authentication mode
The logout control SHALL be shown only when authentication is enabled. When
authentication is bypassed, no logout control is shown.

#### Scenario: Auth bypassed hides logout
- **WHEN** authentication is bypassed and any page renders
- **THEN** no logout link is shown

#### Scenario: Auth enabled shows logout
- **WHEN** authentication is enabled
- **THEN** a logout link is shown

### Requirement: Clear found-poster action label
The action that applies a found poster SHALL be labelled "Select".

#### Scenario: Found-poster action label
- **WHEN** the found-poster results are shown
- **THEN** the apply action for each candidate is labelled "Select"

### Requirement: Responsive gallery layout
The gallery SHALL remain usable on small screens without horizontal overflow:
category tabs scroll rather than overflow, toolbar controls wrap to their own
rows, and posters are sized so at least two fit per row on a phone.

#### Scenario: No overflow on a phone
- **WHEN** the gallery is viewed on a narrow (phone-width) screen
- **THEN** the tabs, toolbar, and poster grid fit without horizontal page overflow

### Requirement: Tap-to-reveal poster actions
On touch devices the poster action overlay SHALL be hidden until the user taps
the poster, and its buttons SHALL NOT be interactive while hidden. On pointer
devices the overlay SHALL reveal on hover.

#### Scenario: Overlay hidden until tapped
- **WHEN** a poster is shown on a touch device and has not been tapped
- **THEN** its action overlay is hidden and its buttons are not interactive

#### Scenario: Tapping reveals the actions
- **WHEN** the user taps a poster on a touch device
- **THEN** its action overlay is revealed; tapping elsewhere hides it again
