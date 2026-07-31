## Context

On a touch device the Orphans link does not navigate. A delegated click handler
intercepts it — but only when `isTouch()` — and opens a tray instead:

```
 Desktop:  click "Orphans" ──> navigate /orphans ──> fresh page ──> fresh scan
 Touch:    click "Orphans" ──> intercepted ─────────> tray ──> scanned once,
                                                             then cached
```

`openOrphans` returns early once the tray has been loaded:

```js
this.orphansOpen = true;
if (this.orphansLoaded || this.orphansLoading) { return; }
```

so the second and every later open shows the first scan's DOM. No spinner runs,
because the spinner belongs to the nested `orphansPage` component's `loading`
flag, and that component is never re-initialised.

The caching comes from `_loadTray`, a helper shared by the import and orphans
trays. Fetching once is right for import — that tray holds a configuration form,
which does not decay. It is wrong for orphans, where the fetched content is a
scan result. The helper cannot tell the two apart, and the caller did not.

**The code already documents the assumption this breaks.** `submitDelete`
declines to re-scan after deleting a single orphan, and says why:

> the others are unaffected, so re-scanning Plex would only stall the page for no
> gain; **the next page open scans fresh.**

That is true on desktop and false in the tray. So the tray's caching quietly
invalidated a correctness argument made elsewhere in the same file — which is the
strongest evidence that "reopening re-scans" was the intended model all along,
not a new requirement being introduced here.

**This is not a regression from `fix-mobile-tray-dismissal`.** The guard dates to
`e3970df` (2026-07-27); that change touched neither `openOrphans` nor
`_loadTray`. It made the bug easier to reach — closing the tray became reliable,
so people began reopening it rather than reloading the page — but it did not
cause it. Recording this so it is not re-litigated later.

## Goals / Non-Goals

**Goals:**

- Every open of the orphans tray scans Plex and shows the tray's loading state.
- Restore the assumption `submitDelete` already relies on.
- Keep the import tray's fetch-once behaviour, and make the asymmetry explicit.

**Non-Goals:**

- A refresh button, pull-to-refresh, or any other new affordance. Reopening is
  the refresh gesture.
- A staleness window or time-based cache. Explicitly rejected by the user: a scan
  is either current or it is not, and "recent enough" is the reasoning that makes
  a stale orphan list feel trustworthy.
- Changing how orphans are detected or deleted. Only *when* the scan runs changes.
- Reworking `_loadTray` into something that knows about staleness. One caller
  needs different behaviour; that belongs in the caller.

## Decisions

### Re-run the scan, do not re-load the tray

On reopening an already-loaded tray, keep the existing DOM and component and
re-run the nested `orphansPage.reload()`, driving its `loading` flag around the
call the same way its own `init()` does. Reach the component through
`window.Alpine.$data()`, as the import stepper already does in this file.

`reload()` is exactly the right unit: it re-fetches `/orphans/list`, replaces
`$refs.results`, re-wires the fetched fragment, and refreshes the count and the
delete-all button. It is what the first open runs; the second should run the
same thing.

### Rejected: clearing the loaded flag on close

The one-line version — `this.orphansLoaded = false` in `closeOrphans` — looks
equivalent and is not. It makes every reopen re-run `_loadTray`, which calls
`Alpine.initTree`, which re-runs `orphansPage.bindInteractions`, which does:

```js
window.addEventListener('gallery:confirmed', function () {
    if (self._pendingForm) { ... self.submitDelete(form); }
});
```

Nothing removes that listener. Each reopen adds another, bound to a component
instance whose DOM has already been thrown away:

```
 open #1 ──> listener A (instance 1)
 open #2 ──> listener A + B (instances 1, 2)
 open #3 ──> listener A + B + C (instances 1, 2, 3)
                     │
        confirm ─────┴──> every listener fires
```

Most stale instances no-op because their `_pendingForm` is `null`. But the flag
is set on submit and cleared only when that instance's own listener runs, so an
instance that had a pending delete when the tray closed keeps it — and fires on a
later, unrelated confirmation. That is a duplicate delete on the destructive
path, produced by a fix for a staleness bug. Recorded here so it is not
reintroduced as an obvious simplification.

Making that route safe would mean giving `orphansPage` a teardown that unbinds
its window listener, and calling it before each re-init. That is a larger change
to a component that otherwise works, to reach the same outcome as re-running one
method.

### Leave the import tray alone, and say why in the spec

The asymmetry is load-bearing and invisible: two trays built from one helper,
now behaving differently. Without a stated reason it reads as an inconsistency
and someone eventually "fixes" it in one direction or the other. The spec states
that a form does not go stale and a scan result does.

## Risks / Trade-offs

- **[Every reopen costs a Plex round trip]** → Accepted, and intended. The scan
  is the entire value of the screen; a fast wrong answer is worse than a slow
  right one. The tray shows its loading state, so the cost is legible.

- **[`Alpine.$data()` on the wrong element yields the wrong scope]** → The lookup
  must resolve the element carrying `x-data="orphansPage(...)"` inside the tray
  body, not the tray body itself. Guard for the component being absent — the
  first load can fail, leaving an error message in place of the component — and
  fall back to a full load in that case rather than throwing.

- **[A reopen lands while a delete is still in flight]** → `orphansLoading`
  already guards the initial fetch; the re-scan path needs the same protection so
  a rapid close/open cannot run two scans over each other.

- **[The fix is invisible to the test suite]** → There is no JS test runner here,
  so a source-shape tripwire is the available defence. It should pin the two
  things that matter: that the reopen path re-runs the scan, and that it does not
  re-init the tray. The second is the one that protects against the rejected
  alternative being reintroduced.

## Open Questions

- Should closing the tray mid-scan abort the in-flight request? Today it does
  not, and the result lands in a hidden tray harmlessly. Re-scanning on reopen
  makes that slightly more likely to happen; not worth handling unless it shows
  up in practice.
