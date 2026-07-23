# Poster Wall Specification

## Purpose

A full-screen, slideshow-style view of the library — posters drawn at random
from every category, cross-fading indefinitely. Intended for a spare display or
a TV, which is why it opens in its own tab and why kiosk use is expected to rely
on the authentication-bypass option rather than a separate access path.

## Requirements

### Requirement: Full-screen rotating wall
The system SHALL provide a full-screen page that continuously displays posters
drawn at random from the library, transitioning between them automatically. The
wall SHALL open in a separate browser tab so the gallery stays open behind it.

#### Scenario: Wall displays posters
- **WHEN** an authenticated user opens the wall and the library has posters
- **THEN** the system presents a full-screen display that rotates through random
  posters

#### Scenario: Open the wall
- **WHEN** a user opens the Poster Wall from the gallery
- **THEN** it opens in a new tab

#### Scenario: Empty library
- **WHEN** the library has no posters
- **THEN** the wall shows a message that there is nothing to display yet

### Requirement: Random poster batches
The system SHALL expose an endpoint that returns a fresh batch of random poster
references so the wall can keep refreshing without a full reload.

#### Scenario: Batch of random posters
- **WHEN** the wall requests more posters
- **THEN** the system returns a batch of poster references selected at random
  from across the library's categories

### Requirement: Wall requires authentication
The wall and its poster batches SHALL require authentication like the rest of the
app (a kiosk uses the authentication-bypass option).

#### Scenario: Unauthenticated access is redirected
- **WHEN** an unauthenticated user opens the wall
- **THEN** the system redirects to the login page
