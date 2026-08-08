## MODIFIED Requirements

### Requirement: Wall is publicly accessible

The wall page and its supporting endpoints — random poster batches, active
streams, and now-playing posters — SHALL be reachable without authentication so
the wall can run on an unattended display without anyone signing in on the
device. These endpoints expose only poster art and now-playing details; they
SHALL NOT perform or expose any action that changes the library or the server.

Every poster address the wall gives itself SHALL be fetchable by a display that
has no session. Naming the batch endpoint alone is not enough: it answers with
addresses, and a wall that can list its posters but not load them is blank. This
was the defect — the batch pointed at the gallery's poster route, which is behind
the login, so an unattended wall failed every frame of its rotation. It went
unnoticed because now-playing art came from an endpoint that was already public,
so the wall looked correct while anything was streaming and went dark only when
it fell back to the rotation.

The route serving them SHALL refuse any category the wall does not draw from.
Making the rotation work must not turn into a way to read the rest of the library
without signing in, so the refusal and the rotation SHALL read from the same list
of categories — a category cannot become publicly readable without also appearing
on the wall.

The gallery's own poster route SHALL remain behind authentication. The two are
separate addresses for the same file precisely so that the wall's need does not
widen the gallery's exposure.

#### Scenario: Wall opens without signing in
- **WHEN** an unauthenticated visitor opens the wall
- **THEN** the system serves the wall instead of redirecting to login

#### Scenario: Now-playing endpoints are reachable without signing in
- **WHEN** an unauthenticated visitor requests the active-streams data or a
  now-playing poster
- **THEN** the system serves it instead of redirecting to login

#### Scenario: Every poster the wall lists can be fetched without a session
- **WHEN** an unauthenticated display requests each poster address returned by
  the random batch endpoint
- **THEN** the system serves an image for every one of them

#### Scenario: The wall's poster route refuses categories the wall does not show
- **WHEN** an unauthenticated visitor requests a poster from a category the wall
  does not draw from, using the wall's poster route
- **THEN** the system refuses it, even though the file exists

#### Scenario: The gallery's poster route still requires a session
- **WHEN** an unauthenticated visitor requests a poster by the gallery's address
- **THEN** the system redirects to login
