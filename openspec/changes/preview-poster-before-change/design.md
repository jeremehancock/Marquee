## Context

The change-poster dialog has three tabs and two different commitment models.

Find Posters (`poster-sources`) opens a candidate full screen, offers *Use this
poster*, then asks *Change the poster to this one?* before posting to
`/change/url` — with a progress overlay and a single-run guard while it runs
(`openFinderPreview` / `applyFinderSelection` in `public/assets/gallery.js`).

Upload and From URL are ordinary AJAX mutation forms. They carry `js-mutate`,
`data-refresh="card"` and four `data-confirm*` attributes; the delegated submit
handler parks the form, raises the shared text confirm dialog, and on
confirmation calls `submitForm()`. Nothing shows the user the image.

Constraints:

- No build step. Alpine + hand-written ES5-style JS in `gallery.js`, one file.
- The two POST endpoints stay exactly as they are — same paths, same fields,
  same 302-to-gallery-with-a-flash response. This change is presentation.
- The preview overlay lives outside the change dialog's panel (it has to cover
  it), so it cannot be scoped to a tab.
- The change dialog is a single instance reused for whichever poster was tapped;
  only Alpine knows the target at the moment of action.

## Goals / Non-Goals

**Goals:**

- One commitment model for all three sources: preview → *Use this poster* →
  confirm → apply, with the same progress overlay and re-entry guard.
- A dismissal of the preview never costs the user their file or URL.
- Delete the now-duplicate path rather than leaving both standing.

**Non-Goals:**

- Any change to `ChangePosterController`, upload validation, Plex export, or
  flash levels.
- Drag-and-drop, multiple files, cropping, or client-side image validation.
- Changing what the shared confirm dialog does for Send, Fetch, and Delete.

## Decisions

### Preview state moves out of `finder` and grows a source discriminator

`finder` currently owns `preview`, `previewLoaded`, `confirming` and `applying`
alongside `loading`, `error`, `notice` and `results`. That whole object is
rebuilt from a literal in four places, and the comment above it already warns
that every literal must repeat `applying`.

Split it: `finder` keeps the search (`loading`, `error`, `notice`, `results`),
and a new sibling `preview` object owns the full-screen step for all three
sources — `{ open, src, loaded, confirming, applying, source, file }`, where
`source` is `'find' | 'url' | 'upload'`. The search literals shrink to four
fields and stop carrying state they never set.

*Alternative rejected:* keep everything on `finder` and add the upload/url
fields to it. It reads as "the finder is applying" when the user never opened
Find Posters, and it grows the literal the code is already fighting with.

### The two tabs stay `<form>` elements, submitting into the preview

`@submit.prevent="openUploadPreview()"` on the Upload form and the URL form.
Keeping the form means `required` and `type="url"` still gate the action for
free — the browser refuses to fire submit on an empty file input or a malformed
URL, exactly as it does today — and Enter in the URL field still works.

*Alternative rejected:* replace them with plain buttons and hand-roll the empty
checks. That re-implements constraint validation and its messages badly.

### The picked file is previewed from an object URL and posted as the captured `File`

`URL.createObjectURL(file)` is synchronous, costs no copy of the bytes, and is
revoked when the preview closes or before a second preview opens. The `File`
object itself is captured into `preview.file` at that same moment and is what
gets posted on confirmation — the blob URL is display-only, and the file input
may have been cleared by then.

*Alternative rejected:* `FileReader` to a data URL. Asynchronous, and it holds
a base64 copy of a multi-megabyte image in memory for no gain.

### A URL the browser cannot render still confirms

The From URL preview points an `<img>` at the user's URL, the same way the
candidate grid points at a source's URL. If it fails, the preview resolves to
its failed state (as candidate images already do) and the user may still
confirm.

The browser and the server are different clients: a referrer check, a
CORS-irrelevant but hotlink-blocked host, or a network the container can reach
and the browser cannot all make a browser failure a bad predictor of a server
failure. The server is the only authority on whether the URL is fetchable, and
it already reports the rejection as a flash. Blocking the confirmation on an
`error` event would refuse changes that work.

### The confirmation question names the poster; the buttons do not change

The text confirmation being removed named the poster it was replacing, and that
requirement is worth keeping — the preview covers the dialog heading that would
otherwise carry the title. So the ask line becomes *Change the poster for
“<title>” to this one?* for all three sources rather than only for the two new
ones; a uniform preview that asks two different questions would defeat the
point. The buttons keep their existing labels (*Use this poster*, *Change
poster*, *Cancel*, *Close*), which the poster-sources spec already fixes.

### One apply path

`applyFinderSelection` becomes `applyPreview()`: it builds the request from
`preview.source` — `filename` + `url` posted to `/change/url` for both `find`
and `url`, `filename` + `poster` (the captured `File`) posted to
`/change/upload` — and everything downstream is unchanged (`!r.ok` throws,
`posterStored`, `refreshCard` or `gallery:refresh`, the flash as a toast, close
preview, close dialog, clear `applying` in `finally`).

The two forms therefore lose `js-mutate`, `data-refresh`, `:data-category` and
all four `data-confirm*` attributes. The delegated submit handler, the shared
confirm dialog, and `submitForm`'s `card`/`none` refresh logic all stay — Send,
Fetch and Delete still use them, and `data-refresh="card"` is still Fetch's.

*Alternative rejected:* keep `submitForm` for the two tabs and let the preview
just replace the confirm dialog. Two apply paths for one operation is what this
change exists to remove, and `submitForm` has no progress overlay or re-entry
guard — the upload of a large file to Plex needs both as much as the finder's
fetch does.

### Escape unwinds the preview, not the dialog under it

Both the change dialog and the preview listen for Escape on the window, and
neither stops propagation, so today one Escape over the Find Posters preview
closes the dialog behind it as well. That was survivable when the only thing
lost was a search that reruns; it is not survivable when the thing lost is the
file the user just picked. The dialog's guard extends to
`if (!confirm.open && !preview.open)`, matching the guard that already exists
for the confirm dialog. Backdrop and *Close* already only reach the preview.

### `.viewer--finder` is renamed

The block is renamed to `.viewer--preview` in `app.css` and the template. A
class named after one of its three callers is how the next reader concludes the
overlay is Find Posters' property.

## Risks / Trade-offs

- **A revoked object URL blanks a preview that is still open** → revoke in
  exactly two places: when the preview closes, and immediately before a new
  object URL replaces it. Never in `applyPreview`'s `finally`, which runs while
  the preview is still on screen on failure.
- **A long title wraps the ask line on a phone** → the action bar is
  bottom-anchored with a `min-height`, so it grows upward and the buttons stay
  put. Already true of the two-line confirm state.
- **The user sees an image the server then rejects** (unsupported type, too
  large): the preview raises expectations the upload can still fail. The flash
  path is unchanged and already says why, and previewing a file the server
  rejects is strictly better than not seeing it at all.
- **`GalleryTest` asserts on the confirm attributes and on exactly three
  "Change poster" buttons** → those assertions are the change, and are rewritten
  to cover the preview instead. The count is now two (the tabs read *Upload
  poster*, the preview's confirm reads *Change poster*).
- **No JS means no change-poster dialog at all** — unchanged from today; the
  forms' actions are already Alpine-bound and empty without it.

## Migration Plan

None. Front-end only, no persisted state, no endpoint or schema change; the
previous behavior returns by reverting the commit.

## Open Questions

None.
