## 1. The bypass warning

- [x] 1.1 Rewrite the `AUTH_BYPASS` warning on the connection screen to describe
      acting with the stored connection — changing and deleting posters, sending
      artwork to the Plex library, disconnecting the install
- [x] 1.2 Stop claiming a visitor could sign the install in to Plex; keep the
      point that only the owner can connect it, immediately qualified by the
      fact that this does not limit what a visitor may do
- [x] 1.3 Update the functional tests for the new wording, and add one asserting
      the warning does not describe connecting as the risk

## 2. README

- [x] 2.1 Correct the `AUTH_BYPASS` row in the environment table
- [x] 2.2 Correct the Security considerations paragraph, which carries the same
      "sign it in or out" phrasing
- [x] 2.3 Add a short section describing the two layers — Marquee login versus
      Plex connection — with what each controls and who sets it
- [x] 2.4 State plainly that the Plex connection does not restrict people, only
      which credential Marquee holds
- [x] 2.6 Reword the owner-only rule's rationale, which described a shared
      account deleting posters in the present tense and so read as current
      behaviour rather than as the reason the account is refused
- [x] 2.5 Check whether `CLAUDE.md` or `docs/` describe the relationship
      inaccurately, and correct them or record explicitly that they do not —
      they do not: the remaining `AUTH_BYPASS` mentions are the dev compose
      sample, the test script's config table, and the CI smoke test, none of
      which describe what bypass exposes

## 3. Vocabulary and transient confirmations

- [x] 3.1 Rename the connection controls and confirmations to connect and
      disconnect, leaving log in and log out to Marquee's own authentication
- [x] 3.2 Auto-dismiss success flashes after a few seconds; leave errors and
      warnings in place
- [x] 3.3 Update the README's steps, feature list and connection section for the
      renamed controls
- [x] 3.4 Update the tests that assert the old labels

## 4. Verification

- [x] 4.1 Confirm the warning renders correctly with bypass on and is absent
      with it off, in a built image
- [x] 4.2 Run `composer test`, `composer stan`, and `composer cs`
- [x] 4.3 Run `openspec validate clarify-auth-and-plex-boundaries --strict`
