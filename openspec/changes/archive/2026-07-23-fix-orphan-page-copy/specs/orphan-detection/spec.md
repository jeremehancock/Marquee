## ADDED Requirements

### Requirement: The orphans page explains what deletion does
The orphans page SHALL describe what an orphan is and what deleting one
removes, and SHALL NOT claim that any poster in the library is exempt from
orphan detection.

#### Scenario: Page explains the criterion and the consequence
- **WHEN** a user opens the orphans page
- **THEN** it states that orphans are posters imported from Plex whose media no
  longer exists there
- **AND** it states that deleting an orphan removes the stored poster file and
  its Plex mapping

#### Scenario: No exemption is claimed
- **WHEN** a user opens the orphans page
- **THEN** it does not claim that manually uploaded posters, or any other class
  of poster, are excluded from orphan detection
