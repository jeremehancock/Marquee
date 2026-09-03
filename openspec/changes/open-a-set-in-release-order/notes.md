# Working notes

## Task 1.1 — the shape of a library

`bin/diagnose-sets.php` gained a "shape of this library" section. It reads the
recorded rows only, so it answers without a server.

Run against a **synthetic** 3942-poster library built to the shapes the design
assumes (2400 movies, 260 shows, 1162 seasons, 120 collections):

```
  Recorded release year, by category:
    collections     120 row(s),  120 with no year (100%)
    movies         2400 row(s),   48 with no year (2%)
    tv-seasons     1162 row(s),    0 with no year (0%)
    tv-shows        260 row(s),    0 with no year (0%)
    -> 0 of 120 collections carry a year; a collection sorts ahead of its films
  Seasons with no recorded season number: 0 of 1162
  Sets recorded per poster:
    in 0 set(s):  1800 poster(s)   (falls back to a title search)
    in 1 set(s):  2112 poster(s)
    in 2 set(s):    30 poster(s)
    -> the longest "also in" line names 1 other set(s)
  Sets with no naming poster imported: 0 of 380
```

**Still owed: the same run against the real ~3900-poster library.** The synthetic
numbers prove the report works; they cannot confirm what Plex reports. The two
answers that matter there are whether collections carry a `year`, and how many
films sit in more than one collection.

## Task 1.2 — do the rules survive?

No spec change needed. Both rules were chosen to be answer-independent:

- **Unknown-year-first** is correct whichever way the collection question is
  answered. If Plex reports no year (as above), a collection leads the films it
  holds. If it reports one, the collection sorts among its earliest films. Only
  "unknown last" depends on the answer, which is why it was not chosen.
- **The "also in" line lists the origin poster's own sets**, so its length is
  bounded by how many collections one film is in — 2 at most here. The rejected
  alternative (the union over every member) is the one a large collection could
  blow up.

The real-library run can still change the *emphasis* — if many sets turn out to
have no naming poster imported, `plex_sets` matters more than assumed, not less —
but no requirement moves.

## Task 1.3 — the baseline

`bin/bench-gallery.php` drives the real `GalleryController::show()` and counts
statements at the PDO level, so it cannot drift from what it measures.

**Before**, synthetic 3942-poster library, 20 iterations, Plex not configured
(so `filenamesForCategory` is skipped — a configured install adds 4 more reads
to every All view):

```
  All, unfiltered                      244.9 ms    16 read(s)
  All, unfiltered, by date added        84.7 ms    20 read(s)
  All, filtered by a query              73.1 ms    24 read(s)
  All, showing a set                    70.2 ms    25 read(s)
  Movies, unfiltered                    46.8 ms     5 read(s)
```

**A finding worth naming before the work starts.** The reads are not what makes
the unfiltered All view slow. Ordered by date added it is 84.7 ms; ordered A–Z,
with *fewer* reads, it is 244.9 ms. The difference is the alphabetical sort
itself — `Poster::sortKey()` runs `NaturalOrder` inside the comparison, so the
key is rebuilt on every one of the ~n log n comparisons over 3942 posters. The
filtered and set views are fast for the same reason in reverse: they sort a
handful of posters rather than all of them.

So this change should be expected to cut the **read count** sharply and the
**wall time** modestly. Memoising the sort key is the change that would move the
244.9 ms, and it is a separate one — it touches ordering, not sets.

## Task 2.7 — after the consolidation

Same machine, same synthetic library, same 20 iterations:

| View | Reads before | Reads after | ms before | ms after |
| --- | --- | --- | --- | --- |
| All, unfiltered | 16 | **4** | 244.9 | 226.4 |
| All, by date added | 20 | **4** | 84.7 | 79.7 |
| All, filtered by a query | 24 | **4** | 73.1 | **46.1** |
| All, showing a set | 25 | **5** | 70.2 | **46.7** |
| Movies, unfiltered | 5 | **1** | 46.8 | 48.6 |

Reads fall to one per category the view holds, everywhere. The set view's fifth
read is the keyed lookup that names the set — one per render, not per category —
and it is the only read left that is not a category scan.

Wall time behaves exactly as the baseline predicted it would:

- **The filtered and set views are ~35% faster.** These were paying the most
  reads (24 and 25) over a list they then cut to a handful of posters, so the
  reads were most of their cost and removing them shows plainly.
- **The unfiltered All view moves 7%,** from 244.9 ms to 226.4 ms, because its
  cost was never the reads. It is the alphabetical sort of 3942 posters, which
  this change does not touch.
- **A single category is unchanged within noise** (46.8 → 48.6 ms). It was
  reading five columns' worth of rows and now reads one row's worth of columns;
  at 2400 rows those cost about the same, and the win there is the four reads
  that no longer happen rather than the time.

**How much to trust the millisecond columns.** Re-running the "after" benchmark
four times gave 219, 226, 233 and 237 ms for the unfiltered All view, and one
outlier at 345 ms on a loaded machine. So the wall-time figures carry roughly
±10% of ordinary variance and the outliers are worse than that — which is
comfortably smaller than the 35% drop on the filtered and set views, and
comfortably LARGER than the 7% on the unfiltered one. Read the first as real and
the second as "no measurable change", not as a small improvement. The read counts
have no such caveat: they were identical on every run.

So: a clear win on reads, a real win on the filtered paths, and an honest
non-result on the view that opens by default. The 226 ms is still the number to
beat, and beating it means memoising `Poster::sortKey()` — a separate change
about ordering, not about sets.

## Task 3 — the A–Z symptoms, checked rather than repeated

The proposal's "why" quoted two symptoms of A–Z on a set. Both were tested
against the real sort key before the field was written, and one of them was
stated wrongly:

| Claim | Verdict |
| --- | --- |
| A show's seasons sort before the show itself | **Confirmed** — but only for filenames as import stores them |
| The Matrix trilogy reads "Reloaded, Resurrections, Revolutions, The Matrix" | **Not reproducible** |

The seasons case only appears with the *stored* filename, which carries the
library name: "Breaking Bad - Season 1 TV" against "Breaking Bad TV", where `-`
sorts below `T`. Given bare titles it does not happen at all, which is why it is
worth writing down — a test built on tidy titles would have said the symptom was
imaginary.

The trilogy sorts as `The Matrix | The Matrix Reloaded | The Matrix
Resurrections | The Matrix Revolutions`, whatever the article setting. So A–Z is
still wrong for it — *Resurrections* is 2021 and *Revolutions* is 2003, so the
fourth film lands third — but not in the way the proposal said. The proposal has
been corrected to state what actually happens.

Nothing in the design moved: release order is the answer to both. Only the
justification needed to match the code.

## Task 7 — the design's own example did not work

The design's table said a set opened from *Jackass Forever* would be offered
"Jackass". It is not, and the first version of the test failed on exactly that.

`BroaderQuery::candidatesFor()` produces cuts at `:`, at ` - `, and at a trailing
instalment (a digit-leading word, a roman numeral, "Part N"). "Jackass Forever"
is none of those, so it yields no candidates and there is nothing to compare
against the set's size.

The feature is unaffected — opened from *Jackass: Best and Last* the same
incomplete collection offers "Jackass" and reports the count — but the example
was wrong, and picking the convenient title would have hidden a limit worth
knowing. The design table now carries the failing row alongside the working one,
and `SetBroaderOfferTest::testAnOriginTitleWithNothingToCutIsOfferedNothing`
pins it.

Deliberately not fixed by widening `BroaderQuery` to drop any last word: that
would change what the typed search offers as well, which is a decision about a
different feature and one nobody asked for here.

## Task 8.3 — what is still owed

The gates pass and the change was exercised against the seeded 3942-poster
library: release order ascending leads with the yearless posters, descending
leads with 2024, and a set view renders in 36 ms. What that CANNOT tell you is
anything about a real Plex server, so the following is still owed and is the
reason this must not be archived yet:

1. `php bin/diagnose-sets.php` against the real library — the "shape" section.
   The two answers that matter are whether collections carry a `year` and how
   many films sit in more than one collection.
2. A trilogy and a show's set opened from a late member, read top to bottom.
3. A film in two collections: the "Also in" line, and following it back.
4. A tab switch and a sort press inside a set — both should keep the set, which
   is the behaviour that changed.
5. A collection missing a film the library holds, opened from a film whose title
   has a subtitle or an instalment number.
6. `php bin/bench-gallery.php` before and after on the real library, to confirm
   the read counts fall to one per category there too.

One edge found while validating and left as it is: a film with NO recorded year
ties with a yearless collection, and category order puts the film first — so
"the collection leads its films" holds whenever the films have years, which is
the ordinary case. Both directions are pinned in `ReleaseOrderTest`.

## Validation feedback, and the two things it found

Both came from the first pass over the `:dev` image, and both were mine.

### The release arrow was backwards

Reported as "when the arrow is down it is showing oldest to newest; it should be
newest to oldest". Correct, and the cause is worth naming because the convention
looked satisfied.

The arrow reports whether a field is running its *ordinary* way, not whether it
ascends — deliberately, so that A–Z (ascending) and newest-first (descending) can
both rest pointing down. That works while each field's "ordinary way" is
self-evident. It stops working the moment two fields answer the SAME kind of
question and disagree: Date added rested down meaning newest-first, Release
rested down meaning oldest-first, and the two buttons sat side by side looking
identical while ordering time in opposite directions.

So Release's default direction is now **descending, latest first**, matching Date
added. The slugs move with it, following the pattern the date field already set —
the unsuffixed slug names the default direction — so `release` is latest-first and
`release_asc` is earliest-first.

**A set still opens earliest first**, and now does so by naming that direction
explicitly rather than inheriting the field's default. That is the right split
anyway: browsing a library by release and reading a trilogy are different acts,
which is the premise the whole change rests on. Three tests pin it — that both
time fields default the same way, that they default to *descending* specifically,
and that the library leads with the newest while a set leads with the earliest.

### "Also in MonsterVerse" did not say what it was about

Reported as: the link is neat but you cannot tell which film it refers to, and
following it loses the thread entirely.

Correct, and this was a straight implementation miss — the design doc's own
sentence was "Godzilla vs. Kong is also in MonsterVerse" and it shipped as "Also
in MonsterVerse". A set view holds many posters, so the subject is not
recoverable from context, and after one hop the reader is being told something
about a film they can no longer name.

The line now names the poster, using the same caption the card carries. The
requirement says so explicitly rather than leaving it to the wording, and the
tests assert the film's name rather than the bare preposition.

### "6 posters in Breaking Bad" was wrong for half the sets

Reported as: "in" makes sense for collections, but a TV show's set showing the
number of posters "in" the show title does not.

Correct, and the reason is that a set is two different relations wearing one
word. A film really is *in* a collection. A season is not *in* its show — it is
artwork *for* it. The summary was written against the collection case, which
reads perfectly, and nobody noticed it was making a claim that is false for every
show.

The obvious fix — vary the preposition by the kind of set — was not taken. The
summary would have to ask what kind of item names the set, which means recording
a type that has no other use, and every set recorded before that column existed
would have no answer. One wording has to serve both.

"for" is true of each: these are the posters Marquee holds for that work. It is
also the preposition the search summary beside it already uses — "12 matches for
“dune” in Movies" — so the two filtered states now read as siblings rather than
as two unrelated sentences.

The view is named for the same reason the search names it, and it earns its place
now in a way it would not have before: a set survives a tab change as of this
change, so "2 posters for Breaking Bad in TV Seasons" is something a reader can
actually be looking at.
