## Why

Marquee now has two independent protections, and nothing explains how they
relate — so the obvious reading of them is wrong in a way that matters.

Restricting the Plex connection to the server's owner sounds like it limits what
visitors can do. It does not. It limits which credential Marquee *holds*.
Everyone who gets past Marquee's own login then acts with that credential, and
since it is now guaranteed to be the owner's, disabling that login gives away
more than it used to, not less.

The warning on the connection screen currently says the opposite of the useful
thing, and the README never draws the distinction at all.

## What Changes

- **Correct the `AUTH_BYPASS` warning.** It claims a stranger could "sign this
  install in or out of Plex". Signing *in* is no longer possible for anyone but
  the owner. What is possible — and what the warning should say — is that anyone
  reaching Marquee acts with the stored connection: changing and deleting
  posters, sending artwork to the Plex library, and disconnecting the install.
- **Correct the same phrasing in the README**, in the environment table and in
  Security considerations.
- **Explain the two layers in the README.** Marquee's login decides *who may
  open the app*; the Plex connection decides *which server and whose account
  Marquee acts as*. The second does not restrict people.
- **Record the consequence in the specs**, so the trust model `AUTH_BYPASS`
  assumes is written down rather than implied.

No behaviour changes: no new routes, no authorization changes, no new
configuration. This is accuracy work on text that describes security, which is
the kind of text that is worth being exactly right.

Marquee's own username and password login stays. Replacing it with Plex sign-in
was considered and deferred; it would need its own change.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `application-shell`: The connection screen's bypass warning must describe use
  of the stored connection rather than the ability to establish one.
- `authentication`: The bypass requirement records that it grants use of the
  stored Plex credential, which can write to the user's Plex library.

## Impact

**Code**

- `templates/connect.html.twig` — the bypass warning's wording

**Docs**

- `README.md` — the `AUTH_BYPASS` environment row, Security considerations, and
  a new section describing the two layers

**Not affected**

- No route, service, or configuration behaviour. The owner-only sign-in, the
  connection gate, and Marquee's own login all keep working exactly as they do.
