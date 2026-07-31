## MODIFIED Requirements

### Requirement: Import and orphans run inside their trays on small screens
When the import or orphans experience is opened in a tray on a phone, it SHALL be
fully contained: running an import or deleting orphans SHALL happen in place
without navigating away, progress SHALL be shown contained within the tray rather
than as a full-screen overlay, and the result SHALL be reported to the user. After
an import completes the gallery SHALL reflect the newly imported posters, and after
orphans are deleted the gallery SHALL reflect their removal.

Opening the orphans tray SHALL scan for orphans, every time it is opened and not
only the first time. Each open SHALL show the tray's loading state and then the
current result. A previous scan's results SHALL NOT be presented on a later open,
because an orphan list is a statement about what Plex contains right now, and a
stale one invites deleting a poster that is no longer an orphan. Reopening the
tray is the means of refreshing it; no separate refresh control is required for
this purpose.

The import tray's contents MAY be fetched once and reused for the remainder of the
page session. This difference from the orphans tray is deliberate: the import tray
presents a configuration form, whose correctness does not decay, whereas the
orphans tray presents the result of a scan, which does.

#### Scenario: Import completes without leaving the tray
- **WHEN** a user submits the import form inside the import tray
- **THEN** progress is shown within the tray, the import runs without navigating to
  another page, the result is reported, and the gallery reflects any new posters

#### Scenario: Deleting orphans stays within the tray
- **WHEN** a user deletes one or all orphans from the orphans tray
- **THEN** progress is shown within the tray, the deletion happens without
  navigating away, and the result is reported

#### Scenario: Tray progress is contained
- **WHEN** an import or orphan operation is running inside a tray
- **THEN** its progress indicator is confined to the tray rather than covering the
  whole screen

#### Scenario: Reopening the orphans tray scans again
- **WHEN** a user opens the orphans tray, closes it by any means, and opens it again
- **THEN** the tray shows its loading state and then the result of a fresh scan,
  not the result of the previous one

#### Scenario: An orphan resolved since the last scan is no longer listed
- **WHEN** a poster was listed as an orphan, its media is restored in Plex, and the
  user reopens the orphans tray
- **THEN** that poster is no longer listed as an orphan

#### Scenario: Reopening does not accumulate handlers
- **WHEN** a user opens and closes the orphans tray repeatedly and then confirms a
  deletion
- **THEN** exactly one deletion is performed
