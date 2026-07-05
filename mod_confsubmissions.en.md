# Conference Submissions — User Manual

**Activity type:** `mod_confsubmissions`
**Part of the Conference Tools suite** ([overview](README.md))

Conference Submissions is the "Call for Abstracts" activity. Add it to a
course to let presenters submit talk/session proposals, with configurable
limits, tracks, and custom fields. Submissions made here feed directly into
[Conference Program](mod_confprogram.en.md) for review.

## Contents

- [For organisers](#for-organisers)
  - [Adding the activity](#adding-the-activity)
  - [Managing tracks](#managing-tracks)
  - [Managing submission types](#managing-submission-types)
  - [Configuring custom fields](#configuring-custom-fields)
  - [Conference dates and preferred dates](#conference-dates-and-preferred-dates)
  - [Viewing all submissions](#viewing-all-submissions)
- [For presenters](#for-presenters)
  - [Submitting an abstract](#submitting-an-abstract)
  - [Choosing preferred dates](#choosing-preferred-dates)
  - [Adding co-presenters](#adding-co-presenters)
  - [Editing, withdrawing, or losing access to a submission](#editing-withdrawing-or-losing-access-to-a-submission)
- [Frequently asked questions](#frequently-asked-questions)

## For organisers

### Adding the activity

1. Turn editing on in your course and choose **Add an activity or resource**.
2. Select **Conference Submissions**.
3. Give it a name (e.g. "Call for Abstracts") and, optionally, a description
   shown to presenters.
4. Under **Submission limits**, set the maximum title length and the maximum
   abstract length. You can limit by character count, word count, or both.
   Presenters see a live counter as they type, and the form will not submit
   past the limit.
5. Under **Call dates**, set the open and close date/time for submissions.
   Presenters cannot start a new submission before the open date or after
   the close date, and cannot edit an existing submission after the close
   date.
6. Save and return to the course.

### Managing tracks

Tracks group submissions by topic (e.g. "Pedagogy," "Technical," "Case
Studies"). Open the activity and choose **Manage tracks** (visible to
editing teachers/managers).

- **Add a track**: give it a name, an optional **colour**, and an optional
  **icon** from the built-in icon set. The colour and icon you choose here
  are not just cosmetic to this activity — they appear as coloured pill
  badges wherever this track is shown across the whole suite, including the
  accepted-submissions list in Conference Program and the block schedule in
  Conference Scheduler. Pick colours that stay visually distinct from each
  other if you have several tracks.
- **Edit or delete a track**: you can rename, recolour, or re-icon a track
  at any time. Deleting a track that submissions already use will prompt for
  confirmation; existing submissions keep a text record of the track name
  but lose the link to the (now-deleted) track record.
- Presenters choose a track from this list when they submit; if you have not
  created any tracks yet, the track field is hidden from the submission
  form.

### Managing submission types

Submission types (e.g. "Lightning Talk," "Workshop," "Keynote") let presenters
say what kind of session they're proposing, and give each type a default
presentation length in minutes. Open **Manage submission types** to add,
rename, or remove types.

Once you've added at least one submission type, presenters must choose one
when submitting — an instance with no submission types configured simply
never shows this field, so you can skip this entirely for a simpler call for
abstracts. A submission's chosen type's duration becomes the initial length
of its block once [Conference Scheduler](mod_confscheduler.en.md) places it
on the schedule (still freely resizable afterwards, and unaffected by later
changes to the type's own configured duration).

### Configuring custom fields

Beyond title, abstract, and track, you can define your own additional
questions for presenters to answer — for example "Preferred session
length," "Intended audience level," or "Equipment needed." Open **Manage
custom fields** to add, reorder, or remove fields.

For each field you define, choose:

- **Field name** — shown as the question label on the submission form.
- **Field type** — text (single line), text area (multi-line), checkbox,
  date, dropdown/menu (with your own list of options), multi-select menu, or
  number. Pick the type that matches the kind of answer you want; a dropdown
  keeps answers consistent for later filtering, while a text area suits
  free-form answers.
- **Required** — whether presenters must fill it in before submitting.
- **Enabled** — you can disable a field temporarily without deleting it (and
  its previously-collected answers).

Fields you add here appear on the submission form in the order you set, and
their answers are visible alongside the rest of the submission wherever
submissions are displayed to organisers/reviewers.

### Conference dates and preferred dates

In the activity's settings, you can optionally set a **conference start**
and **end** date/time. These aren't required, and don't restrict anything
about the activity by themselves — their only purpose is to define the day
range used by **Offer preferred dates**, a separate setting just below them.

Turn on **Offer preferred dates** (only available once both conference dates
are set) to show each presenter a checkbox for every day of your conference
on the submission form, all checked by default. A presenter can uncheck any
day they can't attend, and [Conference Scheduler](mod_confscheduler.en.md)'s
autoscheduler will, by default, only ever place their presentation on one of
their checked days (it still freely chooses the time of day) — if none of
them have room, the submission is left unscheduled rather than placed on a
day the presenter said they couldn't attend, though the organiser can check
an "ignore preferred dates" option when running the autoscheduler to fall
back to any day instead, for that one run. In the schedule's edit mode,
switching the day selector to a specific day also hides any not-yet-
scheduled presentation whose preferred days don't include that day, so an
organiser doesn't accidentally drag someone onto a day they said they
couldn't attend.

**Disabling specific days**: if your conference has a day nobody should be
offered as a preference at all (for example, a day reserved entirely for a
workshop track, or one you know most presenters can't make), open **Manage
disabled preferred days** and check that day. A regular presenter's checkbox
for a disabled day still appears on the submission form — so the day list
always looks the same to everyone — but it's shown greyed out and can't be
checked. Editing teachers and managers are not affected by this setting:
they still see and can select every day, disabled or not, when submitting or
editing on someone's behalf.

### Viewing all submissions

Editing teachers and managers can see every submission made to this
activity (not just their own) via the **All submissions** view. This lists
title, track, current status, and speakers, and links through to the full
detail of each submission. Reviewing and deciding on submissions itself
happens in the linked Conference Program activity, not here — this view is
for oversight of the raw incoming submissions.

Site administrators and users with the manager role can also permanently
**delete** a submission from this view — this removes it completely,
including its speakers and answers, and cannot be undone. Editing teachers
deliberately cannot delete a submission (only a presenter withdrawing their
own submission, or a manager/admin deleting it outright, can remove a
submission from the active list).

## For presenters

### Submitting an abstract

1. Open the Conference Submissions activity from your course.
2. Choose **Submit an abstract** (only available while the call is open).
3. Fill in the **title** and **abstract**, watching the character/word
   counters if limits are set.
4. Choose a **track**, if the organiser has set any up.
5. Fill in any additional questions the organiser has added.
6. Confirm the **Speakers** section (see below) and submit.

### Choosing preferred dates

If the organiser has turned on **Offer preferred dates** (see the
organiser's section above), you'll see a checkbox for each day of the
conference, all checked by default. Uncheck any day you can't attend — the
autoscheduler will try to place your presentation on one of the days you
leave checked, though this is a preference rather than a guarantee. If the
organiser has disabled a specific day, you'll still see it in the list, but
greyed out and not selectable.

### Adding co-presenters

The Speakers section always starts with you (the submitter) listed as
Speaker 1. To add a co-presenter, choose **Add speaker** and pick one of two
ways to identify them:

- **Search for an enrolled user** — start typing a name; this only finds
  people already enrolled in the course, and correctly displays their full
  name (never a raw user ID) once selected.
- **Enter manually** — for a co-presenter who isn't enrolled in the course
  (e.g. an external guest speaker), type their name and email address
  directly. The form remembers that this row was entered manually if you
  come back to edit the submission later, so you won't have to re-enter it
  or accidentally lose the manual details.

You can add as many co-presenters as the organiser allows, remove a row you
added by mistake, and **drag to reorder** the list — the order you set here
is the order speakers are listed everywhere the submission is shown.

### Editing, withdrawing, or losing access to a submission

While the call for abstracts is still open, go to **My submissions** and
choose your submission to edit any part of it, including the speaker list,
their order, and your preferred dates. Once the close date passes,
submissions become read-only from your side — contact the organiser if you
need a late change.

If you can no longer attend, choose **Withdraw** from **My submissions**
instead of editing it — this marks your submission as withdrawn (you'll be
asked to confirm first) rather than deleting it, so the organiser still has
a record of it. Withdrawing is not the same as deletion: only a site
administrator or manager can permanently delete a submission outright.

## Frequently asked questions

**Can I submit more than one abstract?**
Yes, unless the organiser has said otherwise outside the system — this
activity does not limit the number of submissions per user.

**Why can't I see a track option?**
The organiser hasn't set up any tracks for this call yet, or has disabled
the field. Tracks are entirely optional.

**I made a co-presenter row "manual entry" by mistake — can I switch it back
to search mode?**
Yes — open the submission for editing and change that row's mode; your
choice is saved when you save the submission.

**What happens to my submission after I submit it?**
It moves into the review workflow in the linked Conference Program
activity. You'll typically be notified (or can check there yourself,
depending on how the organiser has configured access) once a decision is
made.

**I unchecked all of my preferred days by mistake — what happens?**
An empty set of preferred days is treated the same as never having a
preference at all: the autoscheduler can place you on any day. It is not
treated as "no day works for me."

**Why is one of the preferred-date checkboxes greyed out and I can't check
it?**
The organiser has disabled that specific day from being offered as a
preference — this doesn't mean you can't be scheduled on it, only that you
can't request it as a preference. Contact the organiser if you need to be
scheduled on that day specifically.
