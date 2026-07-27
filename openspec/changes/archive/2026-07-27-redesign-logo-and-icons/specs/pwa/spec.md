## ADDED Requirements

### Requirement: Distinct any and maskable icons
The manifest SHALL declare its icons as distinct `any` and `maskable` entries
rather than a single set marked `"any maskable"`, and the art referenced by each
maskable entry SHALL keep the logo within the maskable safe zone so that it is
not clipped when the platform applies its icon mask.

#### Scenario: Manifest declares separate icon purposes
- **WHEN** the browser requests the web app manifest
- **THEN** the icon list includes at least one entry with purpose `any` and at
  least one entry with purpose `maskable`
- **AND** no single entry is marked with both purposes at once

#### Scenario: Maskable art is safe-zone correct
- **WHEN** a platform installs the app using a maskable icon and applies its own
  mask (for example a circle or squircle)
- **THEN** the logo remains fully visible because its content sits within the
  maskable safe zone
