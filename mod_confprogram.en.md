# Conference Program — User Manual

**Activity type:** `mod_confprogram`
**Part of the Conference Tools suite** ([overview](README.md))

Conference Program manages the reviewer workflow for submissions collected
by [Conference Submissions](mod_confsubmissions.en.md), and — once
review is finished — publishes an accepted-submissions list to attendees.
The activity always operates in one of two **phases**:

- **Review phase** — reviewers score submissions, organisers record
  accept/reject/waitlist/resubmit decisions. Nothing is visible to
  attendees yet.
- **Display phase** — the accepted-submissions list is published for
  attendees to browse, filter, and favourite. Review-phase screens become
  read-only.

## Contents

- [For organisers](#for-organisers)
  - [Adding the activity](#adding-the-activity)
  - [Assigning reviewers](#assigning-reviewers)
  - [Setting up a rubric](#setting-up-a-rubric)
  - [The "unvetted" flag](#the-unvetted-flag)
  - [Recording decisions](#recording-decisions)
  - [Switching to Display phase](#switching-to-display-phase)
  - [Controlling what attendees see](#controlling-what-attendees-see)
- [For reviewers](#for-reviewers)
- [For presenters](#for-presenters-resubmission)
- [For attendees](#for-attendees)
- [Frequently asked questions](#frequently-asked-questions)

## For organisers

### Adding the activity

1. Add a **Conference Program** activity to your course.
2. In its settings, choose which **Conference Submissions** activity in the
   same course it should draw submissions from. This link is required —
   Conference Program has nothing to review without it.
3. Save. The activity starts in **Review phase**.

The current phase and the button to switch it are only shown to organisers
with editing turned on — attendees and reviewers never see raw phase
plumbing on the page.

### Assigning reviewers

Open **Assign reviewers**. You can:

- Assign individual reviewers to individual submissions.
- Assign a whole course **group** of reviewers to a submission at once
  (uses your course's existing Groups, not a separate reviewer-group
  concept).
- Filter the submission list by track before bulk-assigning, so e.g. all
  "Technical" track submissions go to your technical reviewers.
- Set a **maximum number of reviews** per reviewer, either as an
  instance-wide default or overridden per reviewer, so no one reviewer gets
  overloaded.

### Setting up a rubric

Reviews are scored using Moodle's own rubric grading tool, opened from the
review screen. Define your criteria and rating levels once; the same
rubric template can be reused across tracks or future instances via
Moodle's standard rubric template sharing.

### The "unvetted" flag

Some submissions — invited keynotes, panel discussions the organisers
already arranged directly — don't need peer review. Mark these
**unvetted** from the decisions screen. Unvetted submissions:

- Are hidden from the reviewer assignment and "my review queue" screens.
- Can still be given a decision (typically Accept) directly by an
  organiser.
- Can be un-marked again if you change your mind.

### Recording decisions

Once a submission has enough reviews, record a decision from the
**Decisions** report: **Accept**, **Reject**, **Waitlist**, or
**Resubmit**. If you choose blind review (see below), reviewer feedback
shown to the presenter never reveals the reviewer's identity.

A **Resubmit** decision sends the presenter back to their submission (in
Conference Submissions) with your feedback attached, so they can revise and
you can re-review.

### Switching to Display phase

When review is finished, use the phase-toggle button (shown next to the
current-phase indicator, only in editing mode) to switch to **Display
phase**. This is a whole-instance switch — do this once you're ready for
attendees to see the accepted list, not before.

### Controlling what attendees see

Open **Display field settings** to choose, for each field on a submission
(title, abstract, track, speakers, any custom fields from Conference
Submissions), whether it appears:

- In the **list view** (the compact table attendees browse), and/or
- In the **detail modal** (opened by clicking a submission for more
  information).

This lets you keep the list compact (e.g. title, track, speaker, time/room
only) while still surfacing the full abstract and any extra fields in the
detail popup.

## For reviewers

Open **My review queue** to see submissions assigned to you. For each:

1. Open the review screen and read the submission (if blind review is on,
   speaker names/identifying details are hidden from you).
2. Score it against the organiser's rubric.
3. Submit your review. You can typically revise a review until the
   organiser records a final decision, depending on the review round.

If a submission is later marked for **resubmission**, you may be asked to
review the revised version in a new round — the decisions screen keeps a
history of each round.

## For presenters (resubmission)

If your submission comes back with a **Resubmit** decision, go to your
submission in Conference Submissions and edit it. Any reviewer feedback
attached to the decision (with reviewer identity hidden if blind review is
in effect) is shown alongside the edit form so you know what to address.
Saving your changes queues the submission for another review round.

## For attendees

Once the organiser switches to **Display phase**, open the activity to
browse **accepted submissions**:

- Use the **day selector** to jump to a specific conference day (defaults
  to today, or the first day, if the schedule spans multiple days), or
  choose **All days** to see every day's accepted submissions listed one
  after another, each under its own date heading.
- Click a submission's title to open a detail popup with the full
  abstract and any extra fields the organiser chose to show.
- Click the **star** icon to add a submission to **My favourites**, then
  use the **Favourites only** filter to see just your starred sessions.
  Your favourites automatically stay in sync with the "my timetable"
  highlight in Conference Scheduler, if that activity is also in the
  course — starring here is the same action as starring there.
- If the course also has a Conference Scheduler activity, each row shows
  its scheduled time and room once the schedule is published there.

## Frequently asked questions

**Why can't I see the accepted-submissions list yet?**
The organiser hasn't switched the activity to Display phase yet. Check
back later, or ask the organiser directly.

**I'm a reviewer — why don't I see a particular submission in my queue?**
Either it hasn't been assigned to you yet, or it's marked **unvetted**
(which deliberately excludes it from review).

**Does starring a submission here affect the schedule, or vice versa?**
Yes, intentionally — "favourite" is one shared piece of state used by both
Conference Program's list and Conference Scheduler's "my timetable"
highlight, so you only ever need to star a session once.
