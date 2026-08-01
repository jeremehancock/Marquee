## Context

Everything this change needs already exists; it was generalised from Delete by
`confirm-plex-send-and-fetch` (archived 2026-08-01) and is now the gallery's
standard path for any overwriting action:

- A `js-mutate` form declares its confirmation with `data-confirm` (the message)
  plus `data-confirm-title`, `data-confirm-label`, and `data-confirm-tone`.
- The delegated `submit` handler in [gallery.js:1201](public/assets/gallery.js#L1201)
  sees `data-confirm`, parks the form in `pendingForm`, and dispatches
  `gallery:confirm` instead of posting.
- `askConfirm()` opens the shared dialog in
  [_overlays.html.twig](templates/partials/_overlays.html.twig); `doConfirm()`
  dispatches `gallery:confirmed`, and the handler then posts the parked form.
- That dialog is a modal at desktop widths and restyles itself as a tray on a
  phone — it is literally the Send to Plex confirmation.

The two forms in question, [gallery.html.twig:104-119](templates/gallery.html.twig#L104-L119),
are already `js-mutate` with `data-refresh="card"`, so they run through that same
handler today and simply do not declare a confirmation.

Two things are genuinely new here, and neither exists for Send/Fetch:

1. **The message is not known at render time.** Send and Fetch are rendered per
   card, so Twig can interpolate `caption_title` into a static attribute. The
   change dialog is a single instance reused for whichever poster was tapped; its
   title lives in Alpine state (`change.title`, set by `openChange()`).
2. **The confirmation stacks on an already-open overlay.** Send and Fetch confirm
   over the grid or over the action tray (which the submit handler closes first).
   Here the change dialog must stay open behind the confirmation, because
   declining has to leave the user's file selection and URL intact.

Both `.modal` elements sit at `z-index: 55`; the confirm dialog is included after
the change dialog in the document, so it paints above it with no CSS change. The
scroll lock is computed from "is any overlay open"
([gallery.js:260](public/assets/gallery.js#L260)), so nesting two is already
handled.

## Goals / Non-Goals

**Goals:**

- Upload and From URL each require a deliberate confirmation before any request
  is made.
- Declining is total and lossless: no request, no toast, and the change dialog is
  still open on the same tab with the same file/URL still entered.
- One confirmation path serves pointer and touch, reusing the existing dialog
  rather than adding a second one.
- The dialog's action has one name — "Change poster" — on every control that
  performs it.

**Non-Goals:**

- No change to `/library/{category}/change/upload` or `/change/url`, their
  services, or their validation. Confirmation is a client concern.
- No confirmation for Find Posters — it already has its own inline confirm step
  in the preview viewer, and routing it through the shared dialog would put a
  modal over a full-screen viewer for no gain.
- No "don't ask me again" preference.
- No change to what a URL may point at. Mediux URLs keep working; only the label
  loses the parenthetical.

## Decisions

### Bind the confirmation attributes with Alpine, not Twig

The forms get `:data-confirm` (an Alpine binding) built from `change.title`,
alongside static `data-confirm-title="Change poster?"`,
`data-confirm-label="Change poster"`, and `data-confirm-tone="accent"`. The
delegated handler reads the attributes with `getAttribute` at submit time, by
which point Alpine has written the current title into the DOM, so no script
change is needed to carry a dynamic message.

Alternatives considered:

- *Push the message through the `gallery:confirm` detail from a custom
  `@submit`.* Rejected — it bypasses the parked-form mechanism and would need a
  second, parallel path to actually post the form after confirming.
- *Teach `gallery.js` to special-case the change forms and compose the wording.*
  Rejected for the same reason it was rejected last time: user-facing copy
  belongs in the template, not the script.

### Tone is `accent`, not `danger`

Changing a poster overwrites an image the same way Send and Fetch do. Red stays
reserved for Delete, and the Find Posters confirm button for this exact operation
is already `btn--accent` — a red button here would make the same action look
different depending on which tab reached it.

### Wording states the overwrite and the Plex consequence

Both messages name the poster with `change.title` (the caption title: title with
year, no library) and state that Plex is written to when the poster is linked,
because that is the part a user cannot undo:

- Heading: "Change poster?"
- Upload: `Replace the poster for “<title>” with the selected image? If it is
  linked to Plex, the new image is uploaded and locked.`
- From URL: `Replace the poster for “<title>” with the image at that URL? If it
  is linked to Plex, the new image is uploaded and locked.`
- Confirm label: "Change poster".

The two differ only in the source phrase, which is what distinguishes them if a
user reaches the dialog from the wrong tab.

### The change dialog stops swallowing Escape

The change dialog binds `@keydown.escape.window="change.open = false"` and the
confirm dialog binds its own — one Escape currently closes both, which for a
stacked confirmation means declining also discards the user's input. Guard the
change dialog's binding on `!confirm.open` so Escape unwinds one layer at a time.
The Find Posters viewer is unaffected: it is not open while these forms are
submittable.

Alternative considered: `@keydown.escape.window.stop` on the confirm dialog.
Rejected — `window`-scoped listeners fire on the same event target, so ordering,
not propagation, decides the outcome; an explicit guard is what actually holds.

### Cancelling must not leave the form disabled or the dialog closed

Nothing needs doing here, and that is the point worth recording: the parked-form
pattern never touches the form until `gallery:confirmed`, `beginBusy()` runs
inside `submitForm`, and `gallery:done` (which sets `change.open = false`) is
dispatched only from `submitForm`'s completion. So a declined confirmation
provably leaves the dialog open, the inputs populated, and no busy state
stranded.

## Risks / Trade-offs

- **An extra click on every poster change, including the routine ones.** →
  Accepted: the operation is unrecoverable (the previous poster file is gone) and
  it is the only replacement path that was still one click; Find Posters, the
  most-used path, has confirmed since it shipped.
- **A single `pendingForm` slot shared with the card actions.** → Already true;
  the confirm dialog is exclusive and blocks the page behind a backdrop, so a
  second form cannot be submitted while one waits. The change dialog's two forms
  are mutually exclusive tabs.
- **`change.title` is user-supplied text interpolated into an attribute.** →
  It is written by Alpine as a DOM property value, not parsed as HTML, and the
  same value already renders through `x-text` in the dialog heading.
- **Two stacked overlays on a phone, both draggable trays.** → The confirm tray
  is the topmost element and owns the touch target; the change tray's grip is
  behind its backdrop, and the same stacking already occurs when Send to Plex is
  confirmed from the action tray.
- **The orphans page shares `overlayComponent`.** → Untouched: it renders no
  change dialog and this change adds no fields to the shared confirm state.

## Open Questions

None.
