## ADDED Requirements

### Requirement: Download a poster
The system SHALL let a user download a poster's image.

The system SHALL NOT offer an action that copies a poster's URL to the clipboard.
A poster's image address is served only to an authenticated session, so a copied
URL resolves nowhere except the browser that copied it — it cannot be pasted into
another application, sent to anyone, or used by any other tool. Download is the
supported way to take a poster's image out of Marquee.

#### Scenario: Download a poster
- **WHEN** a user chooses to download a poster
- **THEN** the system provides the image file for download

#### Scenario: No copy-URL action is offered
- **WHEN** a poster's actions are shown, on a pointer device or in the touch
  action sheet
- **THEN** no action copies the poster's URL to the clipboard

## REMOVED Requirements

### Requirement: Download and copy a poster
**Reason**: The requirement covered two actions, and one of them is being
withdrawn, so its identity no longer holds. The URL the copy action produced
(`/posters/{category}/{filename}`) is behind the session — only the Poster Wall's
separate route is public — so a copied link worked solely inside the browser that
copied it. It was the one poster action whose result could not be used anywhere
else, which is why it is the control given up to make room for Related posters
rather than one of the six that remain. Downloading is unchanged and continues
under "Download a poster".

**Migration**: Use **Download** to obtain a poster's image file. The card action
that replaces the copy action, Related posters, is itself a link and can be
copied, opened in a new tab, or shared — and unlike the removed URL, what it
addresses is a view the recipient's own session can open.
