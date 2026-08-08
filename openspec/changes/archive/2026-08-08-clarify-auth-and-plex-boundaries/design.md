## Context

Marquee has two protections that look related and are not:

```
Marquee login          who may open the app at all
  AUTH_USERNAME / AUTH_PASSWORD, or AUTH_BYPASS to disable

Plex connection        which server, and whose account Marquee acts as
  established by signing in; restricted to the server's owner
```

Restricting the connection to the owner reads like an access control on people.
It is not. Every Plex operation runs on the stored token — sending a poster,
fetching one, importing, orphan deletion — and no route distinguishes one
visitor from another. Whoever gets past the first layer acts with the credential
the second layer holds.

That makes the owner-only rule a slight *increase* in what `AUTH_BYPASS` gives
away, because the stored credential is now guaranteed to be privileged. The
maintainer read it the other way round on first encountering it, which is the
evidence that the current wording teaches the wrong model.

## Goals / Non-Goals

**Goals:**

- Say what bypassing authentication actually exposes
- Stop claiming a stranger could connect Marquee to Plex, which is no longer true
- Make the two layers, and their independence, findable in the README

**Non-Goals:**

- Any behaviour change. No new routes, no authorization checks, no new settings.
- Restricting what a visitor may do once past the login. That would be per-user
  authorization, which Marquee does not have and which is not proposed here.
- Replacing Marquee's login with Plex sign-in. Considered and deferred.

## Decisions

### Describe use of the connection, not the ability to connect

The warning's job is to tell someone what they are giving up. "Could sign this
install in or out" fails twice: signing in is impossible for a stranger, and
signing out is the least of what they could do. Naming the concrete actions —
change and delete posters, send artwork to the library, disconnect the install —
is both true and more alarming, in proportion to the actual risk.

The clause about only the owner being able to connect stays, immediately
followed by the fact that it does not limit anything else. Dropping it would
invite the reader to supply the wrong inference themselves; stating and then
correcting it is what closes the gap.

### Put the distinction in the specs, not only in the copy

Both requirements gain a paragraph explaining the relationship rather than only
mandating a warning. Copy gets rewritten; a recorded reason survives it. The
`authentication` requirement is where the trust model belongs, since that is the
capability that owns `AUTH_BYPASS`.

### A table in the README, not prose

The two layers differ along the same axes — what they control, who sets them,
what happens when they are absent — so a table shows the shape in a way
paragraphs do not, and puts the load-bearing sentence next to the thing it
qualifies.

## Risks / Trade-offs

- **A blunter warning may read as alarmist** → It is proportionate: on a
  connected install with bypass enabled, a visitor really can overwrite artwork
  across the user's Plex library. Understating it is the worse error, and the
  wording is confined to the screen and the docs where the option is chosen.

- **Documenting an exposure without closing it** → Deliberate. Closing it means
  per-user authorization, which is a much larger change and is not what
  `AUTH_BYPASS` users are asking for — they have chosen an open deployment. The
  honest response is to make that choice informed.

- **The wording could drift from behaviour again** → Which is exactly what
  happened here: the owner-only rule landed and the warning was not revisited.
  The added scenario asserts the warning describes *use* rather than
  *connection*, so a future change in either direction has something to fail.
