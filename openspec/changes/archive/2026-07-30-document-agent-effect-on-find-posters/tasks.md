## 1. Write the FAQ entry

- [x] 1.1 Add the new entry to the FAQ in `README.md`, immediately after "Where
      do 'Find Posters' results come from?", using the wording in Decision 6 —
      symptom as the heading, expected cases before the fix, the cost of
      switching agents in the same paragraph as the suggestion, and the closing
      "nothing you need to do" (Decisions 1, 3, 4)
- [x] 1.2 Verify the refresh warning names both halves, not just "artwork can
      change": applied posters are locked and stay, imported-only posters are
      not locked and can be replaced. Then verify the recovery path is there
      with its ordering — Send to Plex, before the next import (Decision 5)
- [x] 1.3 Check the entry against the constraints before moving on: no Plex menu
      names, setting labels, or click paths; no XML, identifier formats, or
      description of how Marquee reads an identifier; no named list of older
      agents (Decision 2)
- [x] 1.4 Confirm nothing in Quick start, Configuration, or Usage was touched —
      the entry must not become reachable from the setup path, which would make
      it read as required

## 2. Record the requirement

- [x] 2.1 Apply the `poster-sources` delta: the added documentation requirement
      and its scenarios
- [x] 2.2 `openspec validate document-agent-effect-on-find-posters --strict`
      passes — scenarios use exactly four `#`

## 3. Gates and docs

- [x] 3.1 `composer test`, `composer stan`, `composer cs` all pass. No code
      changes, so this is a confirmation that the tree is clean, not a
      verification of new behaviour
- [x] 3.2 Docs gate: `README.md` is the change. Confirm `docs/` and `CLAUDE.md`
      are unaffected — neither documents Find Posters accuracy or Plex agents —
      and say so rather than inventing edits
- [x] 3.3 No live `:dev` verification applies: nothing in the running app
      changes. State that explicitly when shipping rather than skipping the
      question, and note that the README renders on GitHub as the only check
      worth making
