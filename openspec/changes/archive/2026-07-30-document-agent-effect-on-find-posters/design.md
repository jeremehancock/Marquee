## Context

Marquee sends a TMDB identifier with a poster search whenever import recorded
one, and falls back to the title when it didn't. Both paths are specified and
both work. The difference is accuracy: an identifier names the work exactly,
while a title has to be resolved, and two works sharing a title are separated
only by a release year and a popularity tie-break.

Whether Plex reports an identifier at all is decided outside Marquee, by the
metadata agent the user's library was built with. Plex's current agents report
one for anything they matched. Plex's older agents report it in a form Marquee
does not read, and Plex does not migrate a library's agent when the server is
upgraded — a library set up years ago keeps its original agent indefinitely.

So a user can be on a fully up-to-date Plex server, with a perfectly healthy
library, and still get title-only matching for every item in it. From inside
Marquee that is invisible: searches succeed, results are just quietly worse. The
user has no way to connect the symptom to a Plex-side cause, and the one lever
they could pull is one they will never think to look for.

The counter-pressure is that this affects a minority. Most users are on a modern
agent and need to do nothing, and telling everyone to go audit their Plex
settings would be a net loss — it invites a disruptive re-scan on libraries that
had no problem. The entry therefore has to be findable by the person with the
symptom and ignorable by everyone else.

## Goals / Non-Goals

**Goals:**

- A user seeing thin or wrong Find Posters results can find the likely cause and
  decide what to do about it.
- The reader understands that switching a library's agent has a cost, before
  they do it.
- The entry is written so that Plex changing its interface does not make it
  wrong.

**Non-Goals:**

- Telling users to change anything by default. This is troubleshooting, not
  setup, and the README must not imply Marquee needs a particular Plex
  configuration to work.
- Explaining how Marquee identifies a work, what an identifier is, or where it
  appears in Plex's data. A user deciding whether to touch a Plex setting does
  not need any of it.
- Teaching Plex. The entry says which setting matters and why; finding it is
  Plex's documentation's job.
- Any code, behaviour, or test change.

## Decisions

### Decision 1: A FAQ entry, not a setup step or a new section

Placed in the FAQ next to "Where do 'Find Posters' results come from?", which is
where a reader with a Find Posters problem already looks.

The alternative — a note in Quick start or Usage — was rejected. Anything on the
setup path reads as required, which would push every reader toward an agent
switch that most of them should not make. The FAQ is opt-in by construction: it
is read by someone who already has the symptom.

### Decision 2: Describe the setting, never the navigation

The entry refers to the metadata agent a library uses, and does not name Plex's
menus, screens, buttons, or setting labels, and includes no click path.

Plex moves and renames these, and nothing in this repository fails when it
happens — a stale click path is wrong silently, and the user following it
concludes Marquee's documentation is unreliable. Naming the *concept* stays true
as long as Plex has metadata agents at all, which is a far longer horizon than
any particular screen. The same reasoning applies to correcting a single item's
match: the entry describes the action, not the command that performs it.

### Decision 3: Lead with what is normal, not with the fix

Before the agent explanation, the entry states the cases where title-only
matching is simply how it works — collections, personal media, and anything Plex
hasn't matched. A user whose only affected items are collections should stop
reading there and change nothing.

This ordering also stops the agent switch from looking like a general remedy for
disappointing results. It applies when a *whole library* is affected; a single
bad item is a matching problem in Plex, not an agent problem.

### Decision 4: State the cost of switching in the same breath as the fix

The re-scan and its consequences appear immediately after the suggestion, not as
a footnote below it. A reader who acts on the first half and reads the second
half afterwards has already done the damage.

The entry closes by saying that a user whose results are already good has
nothing to do here, so the last thing read is permission to ignore it.

### Decision 5: Say which posters are protected and which are not

The cost that matters most is the one a Marquee user will least expect. Marquee
locks a poster in Plex when it uploads one — on change-poster or Send to Plex —
so those survive a metadata refresh. A poster Marquee only *imported* was never
uploaded, so it was never locked, and Plex is free to replace it during the
re-scan.

That is close to the opposite of what a user is likely to assume. Marquee's own
gallery shows imported and applied posters side by side, and nothing in the
gallery says one is anchored in Plex and the other isn't. A user reads "Marquee
locks posters", concludes their library is safe, switches the agent, and loses
artwork on everything they never got round to changing. The complaint that
follows is not really about Plex — it is about a warning that was technically
present and practically useless.

So the entry names both halves explicitly rather than saying "artwork can
change": applied posters stay, everything else is fair game.

It also gives the way out, because there is one and it has an ordering trap.
Marquee still holds the poster it imported, and **Send to Plex** puts it back —
but importing pulls Plex's *current* artwork into Marquee and overwrites what it
had. A user who re-imports after the refresh, which is the natural thing to do
when posters look wrong, destroys the copy that would have saved them. One
clause covers it: put posters back before the next import.

Rejected: leaving the recovery path out to keep the entry short. Without it the
warning tells a user they may lose something and nothing about getting it back,
which converts a recoverable situation into a permanent one for anyone who
reaches for Import first.

### Decision 6: Proposed wording

To be added to the FAQ in `README.md`, after the "Where do 'Find Posters'
results come from?" entry:

> **Find Posters isn't returning many results for my library**
>
> Find Posters is most accurate when Plex has matched an item to a known title,
> because that lets Marquee ask for that exact movie or show. Without a match it
> searches by name instead, which is less precise — similarly named titles can
> crowd out the one you wanted.
>
> For some things that's simply how it works, and there's nothing to fix:
> collections, personal media libraries such as home videos, and anything Plex
> hasn't matched yet. If it's one stubborn item, correcting that item's match in
> Plex is usually enough.
>
> If results are poor across a whole library, the likely cause is the metadata
> agent that library uses. Libraries created on older versions of Plex keep the
> agent they were built with — upgrading Plex doesn't change it — and the older
> agents don't give Marquee the information it needs to identify a title.
> Switching that library to one of Plex's current agents fixes it from then on.
>
> Do that deliberately, though. Plex re-scans the library afterwards, and that
> re-scan can change artwork. Posters you've applied through Marquee are locked
> in Plex and stay as they are — but a poster Marquee only imported was never
> locked, so Plex can replace it, along with matches you'd corrected by hand.
> Marquee still has its copy: use **Send to Plex** to put a poster back, and do
> that before your next import, because importing pulls whatever artwork Plex
> has at that moment into Marquee.
>
> If Find Posters is already working well for you, there's nothing here you need
> to do.

Rejected phrasings, and why:

- Naming the older agents individually. It is a list that only grows stale, and
  a user cannot act on it any better than on "older agents" — they still have to
  go look at what their library is set to.
- "For best results, use a modern agent." Compact, but it is setup advice in
  disguise; it tells a user with no problem to go change something.
- Mentioning that one of the older agents draws its data from TMDB yet still
  doesn't help. True, and the obvious question for anyone who knows their
  library's agent, but answering it requires explaining how the identifier
  reaches Marquee — which is exactly the technical detail this entry excludes.
- "Your posters are safe — Marquee locks them." The reassuring version, and
  false for exactly the posters most users have most of (Decision 5).
- "This can change artwork in Plex." Short and true, but it lets a Marquee user
  keep believing the locking they've read about elsewhere in the README covers
  them. Naming the unlocked half is the entire point of the sentence.

## Risks / Trade-offs

- **A reader switches agents on a healthy library and loses curated matches and
  artwork.** → The cost is stated with the suggestion, not after it, and the
  entry is scoped to a whole library performing badly rather than to any
  disappointing result. The closing sentence tells a satisfied user to do
  nothing.

- **A reader assumes Marquee's locking protects everything, switches agents, and
  loses posters they never applied through Marquee.** → The entry names the
  unlocked half explicitly rather than warning about "artwork" in general
  (Decision 5), and gives the recovery path with its ordering constraint, so the
  loss is recoverable even for someone who goes ahead anyway.

- **A reader loses posters permanently by re-importing before restoring.** →
  Stated as part of the recovery instruction, not left to be inferred from the
  separate FAQ entry about import overwriting Marquee's copy. Import is the
  first thing a user reaches for when posters look wrong, so the ordering has to
  be in front of them at the moment they're deciding.

- **Plex renames or reorganises metadata agents and the entry drifts anyway.** →
  Decision 2 keeps the entry at the level of the concept, so it survives an
  interface change. If Plex removes agent selection entirely the entry becomes
  obsolete, but that is a visible change worth a documentation pass regardless.

- **The entry is not found by the user who needs it.** → It is titled with the
  symptom rather than the cause, so it matches what that user would search for,
  and sits beside the existing Find Posters entry.

- **It reads as an admission that Find Posters is unreliable.** → The wording
  attributes the limitation to what Plex knows about an item, which is accurate,
  and states plainly that title-only matching is expected for some item types
  rather than a fault.
