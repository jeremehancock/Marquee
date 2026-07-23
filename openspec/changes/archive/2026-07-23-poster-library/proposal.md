## Why

The shell can authenticate and render pages, but Marquee cannot yet do its core
job: hold and manage poster images. This change delivers the local poster
library — browsing, searching, uploading, and deleting posters on disk —
independent of Plex (which arrives in a later phase). After this, Marquee is a
usable poster manager end to end.

## What Changes

- Organize posters into four categories: Movies, TV Shows, TV Seasons,
  Collections, each backed by a directory under `/config/posters`.
- Browse a category as a paginated gallery with a stable, article-aware sort.
- Search within a category with a specific (not overly fuzzy) matcher.
- Upload posters from a local file or from a URL, validating type and size.
- Delete a poster from the library.
- Serve poster images through the app (auth-protected, cached), so no static
  path outside the document root is exposed.
- Add Alpine.js (vendored, no build step) for the upload modal and fullscreen
  viewer.

## Capabilities

### New Capabilities
- `poster-library`: categories, gallery listing, pagination, image serving,
  fullscreen view, and deletion.
- `search`: article-aware, specific matching within a category.
- `poster-upload`: uploading posters from disk or URL with type/size validation.

### Modified Capabilities
<!-- None. -->

## Impact

- New: `src/Poster/**` (domain, storage, library, search, upload),
  `src/Controller/{GalleryController,PosterImageController,UploadController,PosterController}.php`,
  `src/Config/PosterConfig.php`, `templates/gallery.html.twig`,
  `public/assets/alpine.min.js`.
- Routes: `/`, `/library/{category}`, `/posters/{category}/{filename}`,
  `/library/{category}/upload`, `/library/{category}/upload-url`,
  `/library/{category}/delete`.
- Environment: `IMAGES_PER_PAGE`, `MAX_FILE_SIZE`, `IGNORE_ARTICLES_IN_SORT`.
- Storage: reads/writes image files under `/config/posters/{category}`.
