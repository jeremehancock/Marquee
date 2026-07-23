## ADDED Requirements

### Requirement: Upload a poster from a file
The system SHALL let an authenticated user upload an image file into a category,
accepting only JPG, JPEG, PNG, and WebP files no larger than `MAX_FILE_SIZE`.

#### Scenario: Valid file is stored
- **WHEN** a user uploads a PNG within the size limit to a category
- **THEN** the system stores it in that category and it appears in the gallery

#### Scenario: Disallowed type is rejected
- **WHEN** a user uploads a file whose type is not JPG/JPEG/PNG/WebP
- **THEN** the system rejects the upload with a clear error and stores nothing

#### Scenario: Oversized file is rejected
- **WHEN** a user uploads a file larger than `MAX_FILE_SIZE`
- **THEN** the system rejects the upload with a clear error and stores nothing

### Requirement: Upload a poster from a URL
The system SHALL let an authenticated user add a poster by URL, fetching the
image and applying the same type and size validation as file uploads.

#### Scenario: Valid image URL is stored
- **WHEN** a user submits a URL that returns an allowed image within the size
  limit
- **THEN** the system stores the image in the category

#### Scenario: Non-image URL is rejected
- **WHEN** a user submits a URL that does not return an allowed image type
- **THEN** the system rejects it with a clear error and stores nothing

### Requirement: Safe, unique filenames
The system SHALL sanitize uploaded filenames to a safe character set, preserve a
valid image extension, and avoid overwriting an existing poster by making the
stored name unique.

#### Scenario: Colliding name is made unique
- **WHEN** a user uploads a poster whose name matches an existing file in the
  category
- **THEN** the system stores it under a unique name without overwriting the
  existing poster
