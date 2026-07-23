## 1. Copy

- [ ] 1.1 In `templates/orphans.html.twig`, remove the sentence "Posters you
      uploaded yourself are never treated as orphans." from the intro paragraph
      (line 10).
- [ ] 1.2 Replace it with a sentence stating that deleting an orphan removes the
      stored poster file and its Plex mapping.

## 2. Verify

- [ ] 2.1 Functional: the orphans page response contains the deletion
      explanation and does not contain "uploaded yourself".
- [ ] 2.2 PHPUnit, PHPStan (level 8), PHP-CS-Fixer green.
- [ ] 2.3 `openspec validate fix-orphan-page-copy` passes.
