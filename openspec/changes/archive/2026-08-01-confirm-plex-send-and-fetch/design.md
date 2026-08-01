## Context

The gallery already has exactly the machinery this change needs, built for
Delete:

- `templates/partials/gallery_results.html.twig` puts `data-confirm="<message>"`
  on the Delete form.
- The delegated `submit` handler in `public/assets/gallery.js` sees that
  attribute, parks the form in `pendingForm`, and dispatches `gallery:confirm`
  instead of posting.
- `overlayComponent().askConfirm()` opens the shared modal from
  `templates/partials/_overlays.html.twig`; `doConfirm()` dispatches
  `gallery:confirmed`, and the submit handler then posts the parked form.

Two things in that path are hardcoded to deletion. The dispatcher supplies
`title: 'Delete poster?'` and `label: 'Delete'` for *any* form carrying
`data-confirm`, and the modal's confirm button is permanently
`class="btn btn--danger"`. So attaching `data-confirm` to the Send and Fetch
forms as-is would produce a red "Delete" button that sends a poster to Plex.

The same markup serves both presentations: on touch, `sheetDetailFor()` clones
the card's `.card__actions` into the action tray, so a form that confirms on the
desktop overlay confirms in the tray too, with no separate wiring. The confirm
modal restyles itself as a tray at small widths and already sits above an open
tray.

The orphans page (`orphansPage`) has its own copy of the submit handler with its
own hardcoded `'Delete orphan?'` / `'Delete'`. It renders no Send or Fetch form,
so it is not in scope beyond not breaking it.

## Goals / Non-Goals

**Goals:**

- Send to Plex and Fetch from Plex each require a deliberate confirmation before
  any Plex request is made.
- Each confirmation states what will be overwritten, in the poster's own name, so
  the two are not mistakable for one another.
- One confirmation path serves pointer and touch.
- Declining is total: no request, no local write, no toast.

**Non-Goals:**

- No change to the `/library/{category}/send-to-plex` or `/fetch-from-plex`
  endpoints, their services, or their responses. Confirmation is a client
  concern; the server contract is untouched.
- No "don't ask me again" preference. Two clicks on a rare, destructive action is
  the point; a suppression toggle would reintroduce exactly the mis-tap this
  change closes.
- No confirmation for Change poster (it already has its own in-dialog confirm
  step), Download, Copy URL, or Full screen.

## Decisions

### Declare the confirmation on the form, not in the script

Send and Fetch get the same `data-confirm="<message>"` the Delete form has, plus
two new siblings: `data-confirm-title` and `data-confirm-label`. The script stops
naming the delete case and reads all three, falling back to the current
`'Delete poster?'` / `'Delete'` when the new attributes are absent.

Alternative considered: branching in `gallery.js` on the form's `action` to pick
wording. Rejected — it puts user-facing copy in the script, splits the Twig
template's ownership of what a card says, and needs editing again for the next
action that wants confirming. The fallback keeps this change from having to touch
the orphans component at all.

### The confirm button's tone comes from the confirmation

`overlayComponent().confirm` gains a `tone` field (`'danger'` by default,
`'accent'` when the form asks for it via `data-confirm-tone`), and the modal
binds its action button's class from it. Send and Fetch are overwrites, not
deletions, and the app's own vocabulary already reserves red for Delete — the
Change poster dialog confirms with `btn--accent`.

Alternative considered: leaving every confirmation red. Rejected: if every
confirmation looks identical the colour stops carrying information, and the one
action that genuinely destroys a file (Delete) loses its distinction.

### Wording names the poster and the direction of the overwrite

The two operations are adjacent buttons that move the same image in opposite
directions, which is exactly what makes a mis-tap plausible; a generic "Are you
sure?" would not tell a user which one they hit. Each message uses the caption
title already computed for the card (`caption_title` — title with year, no
library, the same string the caption, tooltip, tray heading, change dialog, and
delete confirmation use):

- Send — heading "Send to Plex?", body "Replace the artwork on Plex for
  “<title>” with Marquee's copy, and lock it?", confirm label "Send to Plex".
- Fetch — heading "Fetch from Plex?", body "Replace Marquee's poster for
  “<title>” with the artwork currently in Plex? This overwrites the stored
  poster.", confirm label "Fetch from Plex".

### Confirmation gates the request, not the response

The parked-form pattern is kept as-is: the form is never submitted until
`gallery:confirmed` fires, so declining cannot leave a request in flight. Each
form's existing `data-refresh` contract (`none` for Send, `card` for Fetch) rides
along untouched, because the confirmation changes only *when* `submitForm` is
called, not what it does.

## Risks / Trade-offs

- **Two extra clicks on a routine re-send.** Re-sending after a Plex agent
  refresh is the one case a user might repeat across several posters →
  Accepted: it is still one dialog with a large primary button, and the cost of
  a wrong Fetch (a custom poster gone) is unrecoverable while the cost of a
  cancelled Send is nothing.
- **A single `pendingForm` slot.** Two confirmations cannot be pending at once →
  Already true for Delete, and the modal is exclusive: opening it blocks the
  gallery behind a backdrop and page scroll lock, so a second form cannot be
  submitted while one waits.
- **The orphans component shares `overlayComponent` but not the gallery's submit
  handler.** A change to the shared confirm state could desync the two → The new
  `tone` field is defaulted in `overlayComponent` itself and `askConfirm` fills
  it when absent, so `orphansPage` keeps rendering a red Delete without being
  modified.
- **Touch tray clones markup as HTML.** The message is interpolated into a
  `data-` attribute containing a user-supplied title → Twig auto-escaping already
  covers this for the identical `data-confirm` on Delete; the new attributes use
  the same expression.

## Open Questions

None.
