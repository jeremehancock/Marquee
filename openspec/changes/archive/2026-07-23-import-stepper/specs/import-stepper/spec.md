## ADDED Requirements

### Requirement: Type-first import selection
The import screen SHALL ask the user to choose a content type first and only then
present the libraries that can provide that content type.

#### Scenario: Choosing a content type reveals matching libraries
- **WHEN** a user selects a content type on the import screen
- **THEN** the screen shows only the libraries compatible with that type — movie
  libraries for Movies, TV libraries for TV Shows or TV Seasons, and all
  libraries for Collections

#### Scenario: No libraries offered before a type is chosen
- **WHEN** no content type is selected yet
- **THEN** no libraries are shown for selection

#### Scenario: Changing the content type resets library selection
- **WHEN** a user changes the selected content type
- **THEN** any previously selected libraries are cleared so only compatible
  libraries can be submitted

#### Scenario: Import is blocked until the selection is complete
- **WHEN** either no content type or no library is selected
- **THEN** the Import action is unavailable

#### Scenario: No matching libraries
- **WHEN** the selected content type has no compatible libraries on the server
- **THEN** the screen tells the user that no libraries provide that content type
