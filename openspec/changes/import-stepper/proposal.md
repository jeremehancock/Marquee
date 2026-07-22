## Why

The import screen shows every library at once and asks the user to disable the
content types that don't match — an awkward, error-prone flow. A step-by-step
approach reads far better: first say what kind of content you want, then pick from
only the libraries that can provide it.

## What Changes

- Turn the import form into two steps:
  1. **What do you want to import?** — choose one content type (Movies, TV Shows,
     TV Seasons, or Collections).
  2. **Which libraries?** — reveal only the libraries compatible with that choice
     (movie libraries for Movies; TV libraries for Shows/Seasons; all libraries
     for Collections). Selecting a different type resets the library choices.
- The Import button stays disabled until a type and at least one library are
  chosen.
- Remove the "disable what doesn't match" checkboxes.

## Capabilities

### New Capabilities
- `import-stepper`: a type-first, step-by-step import selection flow.

## Impact

- Modified: `templates/plex.html.twig`, `public/assets/app.css` (type chips).
- No controller or service changes: the existing import endpoint already accepts
  a list of section keys and media types and safely ignores type/library
  mismatches. No new environment variables.
