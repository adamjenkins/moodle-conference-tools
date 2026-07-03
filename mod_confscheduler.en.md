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
  - [GapSnap](#gapsnap)
  - [Span blocks (plenaries, lunch, breaks)](#span-blocks-plenaries-lunch-breaks)
  - [Running the autoscheduler](#running-the-autoscheduler)
- [For attendees](#for-attendees)
- [Printing the schedule](#printing-the-schedule)
- [Frequently asked questions](#frequently-asked-questions)

## For organisers

### Adding the activity

1. Add a **Conference Scheduler** activity to your course.
2. Link it to the **Conference Program** activity in the same course whose
   accepted submissions it should schedule.
3. Under **General**, optionally set the **conference start and end
   dates** — this is a simple organiser-declared record of your event's
   dates; it doesn't restrict where blocks can be scheduled.
4. Set the **GapSnap minimum gap** (see below) if you want a mandatory
   buffer between presentations in the same room.
5. Save.

### Turning on edit mode

**Edit mode is separate from your normal Moodle "Edit mode" course
switch.** Even as a teacher or admin, you see the same read-only timetable
attendees see by default. To make changes, use the **Edit mode** toggle at
the top of the schedule itself. This exists so that organisers browsing
the schedule day-to-day don't risk accidentally dragging a presentation out
of place — deliberate action is required before the grid becomes
interactive.

While edit mode is on, you get: room management, drag-and-drop scheduling,
span blocks, the autoscheduler, and a quick-access GapSnap control at the
top of the grid. Turn edit mode off when you're done to go back to the
same view attendees have.

### Managing rooms

With edit mode on, use **Add room** to create a column. For each room, set:

- **Name**.
- **Colour** (optional) — used as the column header background. The
  header's text automatically switches between black and white to stay
  readable against whatever colour you pick, so you don't need to
  separately worry about contrast.

Drag a room's header to reorder columns. A room can be edited or deleted at
any time; a room that still has presentations scheduled in it can't be
deleted until you move or remove those first.

### Scheduling presentations

The **Unscheduled** panel on the left lists every accepted submission not
yet on the grid, showing its title, speakers, and track. With edit mode
on:

- **Drag** a card from the Unscheduled panel into a room/time slot to
  schedule it.
- **Drag** an already-scheduled block to a new room or time to reschedule
  it.
- **Drag the bottom edge** of a block to resize its duration.
- Click a block's **star** to favourite it on behalf of the room (this
  mirrors the same favourite used in Conference Program and by attendees —
  see [Conference Program's manual](mod_confprogram.en.md)).
- Click the **track pill** on a block to jump straight to that track's
  filtered list in Conference Program.

### GapSnap

GapSnap enforces a minimum gap between two presentations scheduled back to
back in the same room — useful for giving speakers and attendees time to
move between sessions. Set the gap (in minutes) either in the activity's
settings, or, while edit mode is on, via the **quick GapSnap control** at
the top of the schedule grid itself, so you can adjust it on the fly while
you're actively building the timetable.

**GapSnap doesn't reject your drag with an error.** If you drop a block
somewhere that would violate the gap (or genuinely overlap another block),
it automatically **snaps to the nearest valid position** instead — nudging
the block just far enough to satisfy the gap, or back to a non-overlapping
time. You'll always end up with a valid placement; you never need to
retry a drag by trial and error.

### Span blocks (plenaries, lunch, breaks)

For anything that isn't a submitted presentation — a plenary, lunch break,
or a keynote spanning multiple rooms — use **Add span block**. Give it a
label, a colour, a time range, and the range of rooms it should span. Like
rooms, its text colour automatically adjusts for contrast against your
chosen colour.

Span blocks can be **edited after creation**: click an existing span block
(with edit mode on) to reopen the same form, pre-filled, and change its
label, colour, time, or room range.

### Running the autoscheduler

For a large program, manually placing every block can be slow. With edit
mode on, choose **Run autoscheduler** and set:

- A **time window** to schedule into.
- A **default duration** applied to every submission it places (there's no
  per-submission duration otherwise).
- Whether to **clear the existing schedule** in that window first
  (unchecked by default — by default the autoscheduler works around
  whatever's already scheduled).

The autoscheduler prioritises keeping submissions from the same **track**
together in the same room where possible, respects your GapSnap gap, and
randomises its placement order each run (so re-running with the same
inputs doesn't always produce an identical layout). It reports how many
submissions it placed and, for any it couldn't fit anywhere in the window,
why not.

## For attendees

Open the activity (with edit mode off, which is the default view) to see
the published, read-only schedule:

- Use the **day selector** to view one day at a time.
- Click a scheduled block to open the presentation's detail page in
  Conference Program.
- Click the **star** to add a session to **My timetable**, then use the
  **My timetable** toggle to highlight your favourites and grey out
  everything else — handy for finding your own sessions at a glance in a
  busy schedule.
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

**I'm an admin, why don't I see the drag-and-drop grid?**
Edit mode is off by default for everyone, including admins — turn it on
using the toggle at the top of the schedule.

**Why did my dragged block move somewhere I didn't drop it?**
That's GapSnap automatically nudging it to the nearest position that
satisfies the minimum gap (or avoids an overlap) — it never simply
rejects a drop with an error.

**Can I use a colour theme that follows my device's dark mode?**
Not currently — dark-mode support has been disabled for the time being and
may return in a future update. The schedule always renders in its
standard light appearance.

**Does the schedule enforce the conference start/end dates I set?**
No — those dates are just a record for your own reference; they don't
currently restrict where you can place a block.
