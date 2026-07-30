## Context

The gallery renders two separate clear-the-search links:

| | Where | Added by |
| --- | --- | --- |
| `Clear` | `templates/gallery.html.twig`, inside the `.toolbar` search form | `97c673a`, the original gallery |
| `Clear search` | `templates/partials/gallery_results.html.twig`, inside the `.stats--filter` strip | `16aa473`, alongside the "Filtered view is clearly indicated" requirement |

The second was added later without removing the first.

The toolbar link is not merely redundant, it is unreliable. `load()` in
`public/assets/gallery.js` swaps only the `#results` element; the toolbar sits
outside it and is therefore frozen for the life of the page:

```
┌─ [data-gallery] ───────────────────────────────────┐
│  <div class="toolbar">        ← never swapped      │
│     [ search input ]  [Clear] ← stale after load   │
│  <div id="results">           ← the only swap tgt  │
│     3 matches for "matrix"  [Clear search]         │
└────────────────────────────────────────────────────┘
```

That produces four inconsistent states: absent during live search, present after
a reload with `?q=`, still present (and meaningless) after clearing via the
results-strip link, and arbitrary after a tab switch.

Both links are already served by the same delegated `.search__clear` click
handler, so neither carries behaviour the other lacks.

## Goals / Non-Goals

**Goals:**

- Exactly one clear control for an active query, living with the filtered-state
  summary so it updates as one unit with the results.
- No change to how clearing works — only to how many ways it is offered.

**Non-Goals:**

- Making the toolbar participate in no-reload updates. Nothing else in the
  toolbar needs it, and the tabs already resync through `syncActiveTab()`.
- Touching the browser's native in-field clear affordance on `type="search"`.
  It fires `input`, drives live search correctly, and is the user agent's, not
  the gallery's.
- Restyling or relocating the retained control.

## Decisions

**Keep the results-strip control, delete the toolbar one.**
The retained control is the one the spec describes, it carries the match count
and the active query alongside it, and it covers the filtered-empty-state
scenario. It also lives inside `#results`, which is the only region kept
truthful by live updates. The alternative — keeping the toolbar link and making
it live — would mean new JavaScript to toggle it on every load, and would leave
the filtered summary without the clear control the spec requires it to carry.

**Delete the markup, keep the CSS class.**
`.search__clear` is not exclusive to search. It also styles the
"&larr; Back to gallery" links on `templates/plex.html.twig` and
`templates/orphans.html.twig`, and `_loadTray()` in `gallery.js` queries that
class to strip the back link when a page is loaded into a tray. Removing the
rule in `public/assets/app.css`, or narrowing the delegated click handler to the
results strip, would break those. Only the three lines of Twig go.

**No-JS behaviour is unaffected.**
The retained control is server-rendered under the same `query is not empty`
condition, so a browser without JavaScript still gets a working clear link after
submitting the form.

## Risks / Trade-offs

- **The toolbar looks barer without the link, on desktop where the results strip
  sits further from the input.** → Accepted. The control moves closer to the
  thing it describes (the match count for the active query), and the native
  in-field ✕ remains right inside the search box on Chrome and Safari.
- **A future toolbar edit reintroduces a second clear.** → Mitigated by the new
  single-control scenario in the `search` spec and a functional test asserting
  the gallery renders exactly one clear control for an active query.
