## ADDED Requirements

### Requirement: Header brand mark matches the logo asset
The brand mark rendered inline in the page header SHALL draw the same shapes as
`public/assets/logo.svg`, the canonical source of the mark. The header markup is
a duplicate of that asset kept inline so it can be styled and animated, so any
edit to the logo SHALL be reflected in both. A user SHALL never see one mark in
the browser tab or on their home screen and a different one in the header.

#### Scenario: Header mark and logo asset draw the same shapes
- **WHEN** a page rendered from the shared layout is served
- **THEN** the inline brand SVG in the header contains every path and rect
  geometry declared by `public/assets/logo.svg`
- **AND** it declares no shape geometry that the asset does not

#### Scenario: Editing the logo asset alone is detectable
- **WHEN** `public/assets/logo.svg` is changed without the inline header copy
  being updated to match
- **THEN** the application-shell test suite fails, rather than the two marks
  silently diverging
