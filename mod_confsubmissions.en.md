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
  - [Configuring custom fields](#configuring-custom-fields)
  - [Viewing all submissions](#viewing-all-submissions)
- [For presenters](#for-presenters)
  - [Submitting an abstract](#submitting-an-abstract)
  - [Adding co-presenters](#adding-co-presenters)
  - [Editing or withdrawing a submission](#editing-or-withdrawing-a-submission)
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

### Viewing all submissions

Editing teachers and managers can see every submission made to this
activity (not just their own) via the **All submissions** view. This lists
title, track, current status, and speakers, and links through to the full
detail of each submission. Reviewing and deciding on submissions itself
happens in the linked Conference Program activity, not here — this view is
for oversight of the raw incoming submissions.

## For presenters

### Submitting an abstract

1. Open the Conference Submissions activity from your course.
2. Choose **Submit an abstract** (only available while the call is open).
3. Fill in the **title** and **abstract**, watching the character/word
   counters if limits are set.
4. Choose a **track**, if the organiser has set any up.
5. Fill in any additional questions the organiser has added.
6. Confirm the **Speakers** section (see below) and submit.

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

### Editing or withdrawing a submission

While the call for abstracts is still open, go to **My submissions** and
choose your submission to edit any part of it, including the speaker list
and their order. Once the close date passes, submissions become read-only
from your side — contact the organiser if you need a late change.

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
