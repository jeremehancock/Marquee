## ADDED Requirements

### Requirement: Touch poster actions use a bottom sheet
On touch devices the poster actions SHALL be presented in a bottom action sheet
opened by tapping the poster, rather than overlaid on the poster itself, so every
action is shown at full size and none can be triggered by accident.

#### Scenario: Tapping a poster opens the action sheet
- **WHEN** a user taps a poster on a touch device
- **THEN** a sheet opens listing that poster's actions (change, send/fetch to
  Plex when linked, download, copy URL, full screen, delete) with its title

#### Scenario: No tap-through
- **WHEN** a user taps a poster on a touch device
- **THEN** the tap opens the sheet only and does not trigger any poster action

#### Scenario: Dismissing the sheet
- **WHEN** the user taps outside the sheet, presses Escape, or runs one of its
  actions
- **THEN** the sheet closes

### Requirement: Pointer devices keep the hover overlay
On pointer (hover-capable) devices the poster action overlay SHALL reveal on
hover, and clicking a poster SHALL open it full screen.

#### Scenario: Hover reveals actions on desktop
- **WHEN** a user hovers a poster on a pointer device
- **THEN** the action overlay is shown

#### Scenario: Clicking opens full screen on desktop
- **WHEN** a user clicks a poster (not one of its action buttons) on a pointer
  device
- **THEN** the poster opens full screen

### Requirement: Non-overflowing category tabs
Category tabs SHALL fit within the screen width on small screens without
horizontal overflow.

#### Scenario: Tabs fit on a phone
- **WHEN** the gallery is viewed on a narrow screen
- **THEN** the category tabs are laid out without overflowing the screen width
