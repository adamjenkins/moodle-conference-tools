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
exactly where it will land (including any automatic GapSnap adjustment —
see below) before you let go.

### Managing rooms

With Edit mode on, use **Add room** to create a column. For each room, set:

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

### GapSnap

GapSnap enforces a minimum gap between two presentations scheduled back to
back in the same room — useful for giving speakers and attendees time to
move between sessions. Set the gap (in minutes) in the activity's own
settings.

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
(with Edit mode on) to reopen the same form, pre-filled, and change its
label, colour, time, or room range.

### Running the autoscheduler

For a large program, manually placing every block can be slow. With Edit
mode on, choose **Run autoscheduler** and set:

- A **time window** to schedule into.
- Whether to **clear the existing schedule** in that window first
  (unchecked by default — by default the autoscheduler works around
  whatever's already scheduled).

Each submission is placed using the length configured for its own
presentation type in Conference Submissions (the same as a manual drag —
see above), so there's nothing further to set for duration.

The autoscheduler prioritises keeping submissions from the same **track**
together in the same room where possible, respects your GapSnap gap, and
randomises its placement order each run (so re-running with the same
inputs doesn't always produce an identical layout). It reports how many
submissions it placed and, for any it couldn't fit anywhere in the window,
why not.

## For attendees

Open the activity (with course editing off, the default view) to see the
published, read-only schedule:

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

**I'm an admin/teacher, why don't I see the drag-and-drop grid?**
Turn on Moodle's course **Edit mode** switch, at the top of the page next
to your name — it's off by default for everyone, including admins. This is
the same switch you use to edit anything else in the course; Conference
Scheduler doesn't have a separate toggle of its own.

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
