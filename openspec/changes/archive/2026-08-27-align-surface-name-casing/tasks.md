## 1. Rename the support overlay

- [x] 1.1 In [templates/partials/_support.html.twig](../../../templates/partials/_support.html.twig), change the panel's `aria-label` from `Support development` to `Support Development` (line ~48).
- [x] 1.2 In the same file, change the `<h2 class="support-ask__title">` text from `Support development` to `Support Development` (line ~66).
- [x] 1.3 Update the comment block at the top of that file if it quotes the old spelling; leave the reasoning about teleporting, `.modal` choice and panel width untouched.

## 2. Update the tests that assert the old spelling

- [x] 2.1 [tests/Functional/ApplicationShellTest.php:408](../../../tests/Functional/ApplicationShellTest.php#L408) — `testTheSupportAskRendersOnEveryPageWithNavigation` asserts `aria-label="Support development"`. Update to `Support Development`.
- [x] 2.2 [tests/Functional/ApplicationShellTest.php:480](../../../tests/Functional/ApplicationShellTest.php#L480) — `testTheSupportMarkSitsOnTheHeadingRow` asserts the head contains `Support development`. Update to `Support Development`.
- [x] 2.3 [tests/Unit/Asset/DialogFocusTest.php:328](../../../tests/Unit/Asset/DialogFocusTest.php#L328) — the `dialogNames()` provider lists `'Support development' => ['Support development']`. Update both the key and the value.
- [x] 2.4 [tests/Unit/Asset/TrayHeadSpacingTest.php:20](../../../tests/Unit/Asset/TrayHeadSpacingTest.php#L20) — a docblock naming the `.modal__panel` trays says "Support development". Update the prose; it is a comment, so nothing fails either way, which is exactly why it drifts.

## 3. Pin the agreement with a test

- [x] 3.1 Add `testTheSupportOverlayIsNamedWhatItsEntryIsNamed` to `ApplicationShellTest`. It MUST compare the two rendered strings to each other, not to a hard-coded `'Support Development'` — a literal on both sides is the arrangement that let them drift.
- [x] 3.2 Extract the entry's label with the existing `supportEntry()` helper (line ~112), reading its `aria-label`, and the overlay's with `supportOverlay()` (line ~96), reading both the panel's `aria-label` and the `<h2>` text.
- [x] 3.3 Assert all three are equal, across both placements — the desktop header's overflow menu and the phone actions tray — since `supportEntry()` already takes a placement and the entry is rendered in each.
- [x] 3.4 Write the docblock as the other tests in this file are written: state what drifted, why comparing to a literal would not have caught it, and that the test cannot catch a *new* overlay named nothing like its opener.

## 4. Verify the audit's other findings hold

- [x] 4.1 Confirm no remaining occurrence of the lowercase spelling reaches a user: `grep -rn "Support development" templates/ public/ src/` must return nothing.
- [x] 4.2 Confirm the surfaces the audit found already consistent are untouched — Poster Wall, Import from Plex, Plex Connection, Plex Posters, Find Posters, Settings, Log out. This change renames nothing else; a diff touching any of them is out of scope.
- [x] 4.3 Confirm the sentence-case labels are untouched: card actions, confirmation titles, form labels, settings section headings, pagination names.

## 5. Gates and docs

- [x] 5.1 `composer test` — PHPUnit 11, all green.
- [x] 5.2 `composer stan` — PHPStan level 10 over `src/` and `tests/`.
- [x] 5.3 `composer cs` — PHP-CS-Fixer dry-run clean (`composer cs:fix` to apply).
- [x] 5.4 Check `README.md`, `docs/` and `CLAUDE.md` for the lowercase spelling and for whether the new naming rule belongs in `CLAUDE.md`'s conventions list. The rule is the kind of thing that file exists for — a decision no test can fully enforce — so add it there in the same commit if it fits, or state explicitly that nothing user-facing needs updating.
