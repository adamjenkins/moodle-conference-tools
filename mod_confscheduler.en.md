# Conference Scheduler — User Manual

**Activity type:** `mod_confscheduler`
**Part of the Conference Tools suite** ([overview](README.md))

Conference Scheduler turns the accepted submissions from
[Conference Program](mod_confprogram.en.md) into a drag-and-drop
time-by-room block schedule, and publishes a read-only timetable to
attendees.

## Contents

- [For organisers](#for-organisers)
  - [Adding the activity](#adding-the-activity)
  - [Turning on edit mode](#turning-on-edit-mode)
  - [Managing rooms](#managing-rooms)
  - [Scheduling presentations](#scheduling-presentations)
  - [Days and the conference date range](#days-and-the-conference-date-range)
  - [SnapGap](#snapgap)
  - [Row height](#row-height)
  - [Span blocks (plenaries, lunch, breaks)](#span-blocks-plenaries-lunch-breaks)
  - [Running the autoscheduler](#running-the-autoscheduler)
  - [Notifications](#notifications)
- [For attendees](#for-attendees)
- [Printing the schedule](#printing-the-schedule)
- [Frequently asked questions](#frequently-asked-questions)

## For organisers

### Adding the activity

1. Add a **Conference Scheduler** activity to your course.
2. Link it to the **Conference Program** activity in the same course whose
   accepted submissions it should schedule.
3. Under **General**, set the **conference start and end dates**. These
   are **required** — they drive which days you can pick on the schedule
   (including an "All days" view), the autoscheduler's default time
   window, and grey out (and refuse to let you drag a presentation into)
   any time before the start or after the end.
4. Save.

The **SnapGap minimum gap** (see below) isn't set here — it's a quick
control at the top of the schedule itself, once you turn Edit mode on.

### Turning on edit mode

Conference Scheduler uses **Moodle's own course "Edit mode" switch** — the
same one shown at the top of every page in your course, next to your name.
With editing off, you (and everyone else) see the same read-only timetable
attendees get. Turn it on to make changes; turn it off again when you're
done to go back to that same read-only view. There's no separate,
scheduler-specific toggle to learn — if you're already used to turning
Edit mode on to change anything else in the course, this works exactly the
same way here.

While Edit mode is on, you get: room management, drag-and-drop scheduling,
and the autoscheduler. While dragging a block, a highlighted preview shows
exactly where it will land (including any automatic SnapGap adjustment —
see below) before you let go.

### Managing rooms

With Edit mode on, use **Add room** to create a column. For each room, set:

- **Name**.
- **Colour** (optional) — used as the column header background. The
  header's text automatically switches between black and white to stay
  readable against whatever colour you pick, so you don't need to
  separately worry about contrast.
- **Capacity** (optional) — the room's maximum attendee capacity, shown in
  its column header once set. Leave it blank for an unlimited room.

Drag a room's header to reorder columns. A room can be edited or deleted at
any time; a room that still has presentations scheduled in it can't be
deleted until you move or remove those first.

If a room has a capacity set, a presentation scheduled into it is
highlighted (in Edit mode only) as a possible **overbooking** once the
number of attendees who have favourited it exceeds that capacity — this is
purely informational, never a hard restriction, since overbooking a room
(standing room, or a bigger room simply being unavailable that slot) can be
a legitimate organiser choice.

### Scheduling presentations

The **Unscheduled** panel on the left lists every accepted submission not
yet on the grid, showing its title, speakers, and track. With Edit mode
on:

- **Drag** a card from the Unscheduled panel into a room/time slot to
  schedule it. It's given the length configured for its **presentation
  type** in Conference Submissions (see that manual), so a Lightning Talk
  and a full Workshop start out at their own correct lengths without you
  having to resize every block by hand.
- **Drag** an already-scheduled block to a new room or time to reschedule
  it.
- **Drag the small handle on the bottom edge** of a block to resize its
  duration — this always overrides the presentation type's default length
  for that one block only; it doesn't change the type's own setting or
  affect any other block.
- Click a block's **star** to favourite it on behalf of the room (this
  mirrors the same favourite used in Conference Program and by attendees —
  see [Conference Program's manual](mod_confprogram.en.md)).
- Click the **track pill** on a block to jump straight to that track's
  filtered list in Conference Program.

### Days and the conference date range

The **Day** dropdown at the top of the schedule lists every day within
your conference's start/end dates (even a day with nothing scheduled on
it yet, so you can still drop a presentation onto it), plus **All days**,
which lists every day as its own separate table on the same page instead
of one day at a time — handy for a quick overview of the whole event.
Editing (dragging blocks) is only available one day at a time; switch
away from "All days" first if you want to reschedule something.

Any time outside your conference's start/end dates is shown with a
diagonal grey hatch, and you can't drop a presentation into it — dragging
a block toward a greyed area automatically "bounces" it back to the
nearest valid time, the same way SnapGap bounces a block away from
another one it's too close to (see below).

### SnapGap

SnapGap enforces a minimum gap between two presentations scheduled back to
back in the same room — useful for giving speakers and attendees time to
move between sessions. Set the gap (in minutes) using the **SnapGap minimum
gap** control at the top of the schedule, visible with Edit mode on;
changes take effect immediately.

**SnapGap doesn't reject your drag with an error.** If you drop a block
somewhere that would violate the gap (or genuinely overlap another block),
it automatically **snaps to the nearest valid position** instead — nudging
the block just far enough to satisfy the gap, or back to a non-overlapping
time. You'll always end up with a valid placement; you never need to
retry a drag by trial and error.

### Row height

Right next to SnapGap's control is **Row height (pixels per hour)** — how
tall one hour appears on the schedule. The default suits most conferences;
increase it if short presentations don't have enough room to show their
title and speaker names without crowding, or decrease it to fit a longer
day on screen with less scrolling. Changes take effect immediately, apply
to everyone (organisers and attendees alike, since the read-only attendee
view uses the same setting), and don't move or resize anything you've
already scheduled — only the visual density changes.

### Span blocks (plenaries, lunch, breaks)

For anything that isn't a submitted presentation — a plenary, lunch break,
or a keynote spanning multiple rooms — use **Add span block**. Give it a
label, a colour, a time range, and the range of rooms it should span. Like
rooms, its text colour automatically adjusts for contrast against your
chosen colour.

Span blocks can be **edited after creation**: click an existing span block
(with Edit mode on) to reopen the same form, pre-filled, and change its
label, colour, time, or room range.

### Running the autoscheduler

For a large program, manually placing every block can be slow. With Edit
mode on, choose **Run autoscheduler** and set:

- A **time window** to schedule into — pre-filled with your conference's
  start/end dates, so you usually don't need to change it.
- Whether to **clear the existing schedule** in that window first
  (unchecked by default — by default the autoscheduler works around
  whatever's already scheduled).
- Whether to **ignore preferred dates** (unchecked by default — see below).

Each submission is placed using the length configured for its own
presentation type in Conference Submissions (the same as a manual drag —
see above), so there's nothing further to set for duration.

The autoscheduler prioritises keeping submissions from the same **track**
together in the same room where possible, respects your SnapGap gap, and
randomises its placement order each run (so re-running with the same
inputs doesn't always produce an identical layout). It reports how many
submissions it placed and, for any it couldn't fit anywhere in the window,
why not.

**Preferred dates**: if a presenter recorded preferred conference days
(Conference Submissions' "offer preferred dates" feature), the
autoscheduler by default only ever places their submission on one of those
days. If none of them have room anywhere in the window, that submission is
left unscheduled and reported as "could not be placed," rather than being
placed on a day the presenter said they couldn't attend. Check **Ignore
preferred dates** before running to change this for that one run: the
autoscheduler will still try a preferred day first, but falls back to
placing on any day in the window if none of the preferred ones have room,
so nothing gets left unscheduled purely because of a preference. A
presentation that ends up scheduled on a day its presenter did not prefer —
whether from an ignore-preferred-dates run, or from you manually dragging
it there — is shown with a distinct amber border and hatching while Edit
mode is on, as a reminder; with Edit mode off, it displays exactly like any
other block.

### Notifications

Unlike Conference Submissions and Conference Program, scheduling
notifications here are **never automatic** — a schedule is typically
rearranged many times while you're building it, and emailing speakers on
every drag would be noise. Instead, the toolbar (Edit mode on) shows a
**Send notifications** button with a live count of presentations whose
scheduling information has changed since they were last notified. Click it
whenever you're ready — it emails only those speakers, skipping anyone
whose time/room hasn't changed since the last send, so you can click it
again later after making further changes without re-notifying speakers
who weren't affected.

Open **Manage notifications** to customise the subject/message, or switch
notifications off entirely for this instance with **Enable notifications**.
While off, the pending count still updates as you reschedule things, but
clicking **Send notifications** sends nothing — nothing is lost, though:
turn notifications back on and click the button again to deliver everything
that built up while they were disabled. Placeholders available include
`[[fullname]]`, `[[submissiontitle]]`, `[[coursename]]`, `[[roomnames]]`,
`[[starttime]]`, and `[[endtime]]`.

## For attendees

Open the activity (with course editing off, the default view) to see the
published, read-only schedule:

- Use the **day selector** to view one day at a time, or choose **All
  days** to see every day's schedule, one table per day, on the same page.
- Click a scheduled block to open the presentation's detail page in
  Conference Program.
- Click the **star** to add a session to **My timetable**, then use the
  **My timetable** toggle to highlight your favourites and grey out
  everything else — handy for finding your own sessions at a glance in a
  busy schedule.
- Once you've starred your favourites, click **Export my timetable (.ics)**
  next to the My timetable toggle to download them as a calendar file you
  can import into any calendar app (Google Calendar, Outlook, Apple
  Calendar, and so on).
- Click a **track pill** to see everything else in that track.

## Printing the schedule

The **Print** controls (visible in the read-only view) let you choose:

- **Colour** or **black & white** (black & white strips room colour
  theming down to plain borders and text, so it stays legible on a
  monochrome printer or photocopier).
- **Paper size** — A4, A3, or A2.
- **Portrait** or **landscape** orientation.

Choose your options, then use your browser's normal print command; the
schedule is laid out to fit the chosen page size and orientation.

## Frequently asked questions

**I'm an admin/teacher, why don't I see the drag-and-drop grid?**
Turn on Moodle's course **Edit mode** switch, at the top of the page next
to your name — it's off by default for everyone, including admins. This is
the same switch you use to edit anything else in the course; Conference
Scheduler doesn't have a separate toggle of its own.

**Why did my dragged block move somewhere I didn't drop it?**
That's SnapGap automatically nudging it to the nearest position that
satisfies the minimum gap (or avoids an overlap) — it never simply
rejects a drop with an error.

**Can I use a colour theme that follows my device's dark mode?**
Not currently — dark-mode support has been disabled for the time being and
may return in a future update. The schedule always renders in its
standard light appearance.

**Does the schedule enforce the conference start/end dates I set?**
Yes. Conference dates are required, and any time outside them is greyed
out and off-limits — dragging a block toward a greyed area bounces it
back to the nearest valid time, the same way SnapGap does. See "Days and
the conference date range" above.

**Why didn't rescheduling a presentation email its speaker?**
Scheduling notifications here are never sent automatically. Click **Send
notifications** in the toolbar when you're ready — it emails everyone
whose scheduling information has changed since the last time you clicked
it.

**Does the "overbooked" highlight stop me from scheduling into a full
room?**
No — it's informational only. You can still schedule into a room whose
capacity a presentation's favourite count already exceeds; sometimes
that's a deliberate choice (standing room, or the only room available at
that time).

**I exported my timetable, but the file is empty — why?**
You haven't starred any sessions yet (or none of your starred sessions
are currently scheduled). Star at least one session with **My timetable**
first, then export again.
