## 1. In-app support ask

- [x] 1.1 In `templates/partials/_support.html.twig`, change the `.support-ask__cta`
      anchor's text from `Hard drive fund` to `Buy me a coffee`. Leave the `href`,
      `target`, `rel`, `@click` and classes exactly as they are.
- [x] 1.2 Update the comment above that anchor only if it names the old label; the
      existing note explains *why the link dismisses on the way out* and stays
      accurate as written. Do not restate the label in a comment — it now lives in
      one place and a second copy is the thing that lets names drift.
- [x] 1.3 Leave the `.support-ask__text` paragraph untouched, hard-drive joke and
      all. It is the ask; only the button stopped repeating it (design Non-Goals).

## 2. Tests for the ask

- [x] 2.1 In `tests/Functional/ApplicationShellTest.php` (~line 506), swap the
      asserted call-to-action string to `Buy me a coffee`. Keep it a literal —
      unlike the overlay's *name*, this label lives in exactly one file and has
      nothing to agree with, so the comparison style used for the heading would
      only remove a check here.
- [x] 2.2 Update the failure message at ~line 612 (`Choosing "Hard drive fund" must
      dismiss the ask on its way out.`) to name the new label.
- [x] 2.3 In the same test that asserts the call to action is present, add one
      assertion that `Hard drive fund` appears nowhere in the rendered overlay.
      This is the testable half of the new spec scenario; it is folded in here
      rather than given its own method because it is the same fact stated
      negatively.
- [x] 2.4 Run `composer test` and confirm `ApplicationShellTest` is green.

## 3. README

- [x] 3.1 Delete the alpha blockquote at the top of `README.md` (lines 3–7, the
      `> ⚠️ **Early Alpha — not ready for general use.** …` paragraph) along with
      the blank line it leaves behind, so the file goes `# Marquee` straight into
      the intro paragraph.
- [x] 3.2 Reword the `PLEX_TOKEN` upgrade note (~line 292) from
      `**`PLEX_TOKEN` is no longer read.** Marquee is in alpha and this is a
      breaking change: one way to connect replaced two.` to keep the bold lead and
      the "one way to connect replaced two" explanation while dropping the alpha
      clause. The rest of the "Upgrading from a version that used `PLEX_TOKEN`"
      subsection stays as it is.
- [x] 3.3 Add the Buy Me A Coffee badge to the existing `## Support Development`
      section at the end of `README.md`, on its own line after the prose
      paragraph:
      `[![Buy Me A Coffee](https://cdn.buymeacoffee.com/buttons/v2/default-yellow.png)](https://www.buymeacoffee.com/jeremehancock)`
      Do not rename the section, move it, add an emoji to its heading, or create a
      second support section (design Decision 1).

## 4. Sweep and gates

- [x] 4.1 `grep -rIn --exclude-dir=.git -w -i 'alpha' .` and confirm the only
      remaining hits are false positives (`alphabetical`, `alphabetised`) and
      archived OpenSpec changes, which are historical records and are not edited.
- [x] 4.2 `grep -rIn --exclude-dir=.git 'Hard drive fund' .` and confirm the only
      remaining hits are under `openspec/changes/archive/` and this change's own
      spec delta (which quotes the old label to say it is gone).
- [x] 4.3 Confirm no other doc went stale: `docs/` and `CLAUDE.md` name neither
      the alpha status nor the button label, so the expected outcome is "nothing
      to change" — state that explicitly rather than inventing edits.
- [x] 4.4 Run all three gates: `composer test`, `composer stan`, `composer cs`.
- [x] 4.5 `openspec validate refresh-support-ask-and-readme --strict`.
