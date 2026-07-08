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
  - [Notifications](#notifications)
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
- Arriving via the Decisions report's **Start a new round** link instead
  lands you here already filtered to every submission with a **Resubmit**
  decision awaiting a reviewer for its new round, ready for a bulk group
  assignment in one action.
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

Once submissions have enough reviews, record decisions from the
**Decisions** report — a table listing every non-unvetted submission
alongside its track, current review round, most recent decision (if any),
and each reviewer's score so far. Narrow the table down with the **track**
and **decision status** (no decision yet / accepted / rejected / resubmit /
waitlisted) filters above it.

Record a decision one submission at a time using the dropdown and **Save
decision** button on its row, or handle several at once: tick the
checkboxes for the rows you want (or the header checkbox to select every
row currently shown), choose **Accept**, **Reject**, **Waitlist**, or
**Resubmit** from the toolbar above the table, and click to apply it. A
confirmation dialog names the decision and how many submissions it's about
to affect before anything is saved. If you choose blind review (see below),
reviewer feedback shown to the presenter never reveals the reviewer's
identity.

If you (or another editing teacher/manager) can edit submissions in the
linked Conference Submissions activity, each row's title also shows an
**Edit** link — use it to fix a submission's track or any other detail
without leaving this report. Saving (or cancelling) brings you straight
back to the Decision report with whatever track/decision-status filter you
had active.

A **Resubmit** decision sends the presenter back to their submission (in
Conference Submissions) with your feedback attached, so they can revise and
you can re-review. Once at least one submission has a Resubmit decision, a
**Start a new round** link appears above the table — follow it to jump
straight to **Assign reviewers**, already filtered to every resubmitted
submission waiting on a reviewer for its new round (see "Assigning
reviewers" above), instead of chasing each one down individually.

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

### Notifications

Once you switch to Display phase, every submission's decision — accept,
reject, or waitlist — is automatically emailed to its speaker(s). A decision
recorded while still in Review phase is never emailed early; it's queued
and sent the moment you switch to Display phase, so a presenter never learns
their result before you're ready to publish.

Open **Manage notifications** to customise the subject/message for this
email, or switch it off entirely for this instance with **Enable
notifications**. Turning notifications back on later still delivers any
decision that was recorded while they were off — nothing is lost, only
delayed. Placeholders available include `[[fullname]]`,
`[[submissiontitle]]`, `[[coursename]]`, and `[[decision]]`.

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
  choose **All days** to see every day's accepted submissions in a single
  table with a divider row for each date, and consistent column widths
  throughout. On a narrow screen (phone or small tablet), the table
  collapses to a two-row-per-submission layout for easier reading.
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

**When exactly does a presenter get emailed about their decision?**
The moment the organiser switches the instance to Display phase — not when
the decision is first recorded. If a decision was made while still in
Review phase, it's queued and the email goes out with everyone else's the
instant Display phase begins (unless the organiser has switched
notifications off entirely for this instance).
