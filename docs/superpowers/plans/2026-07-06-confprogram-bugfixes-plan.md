# confprogram Bug Fixes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix three confirmed bugs — numeric submit-button labels, `review.php`'s
redirect-after-output crash on submit/cancel, and track colour never reaching
the pill badges — across `mod_confprogram` and (for the track-colour half)
`mod_confscheduler`.

**Architecture:** Three independent fixes, each following an existing,
working pattern already present elsewhere in the same codebase (no new
architecture introduced). Full root-cause analysis for all three lives in
`docs/superpowers/specs/2026-07-06-confprogram-bugfixes-design.md` in this
repo — read it before starting if anything below is unclear.

**Tech Stack:** Moodle 5.2 plugin (PHP 8.2+), PHPUnit (`advanced_testcase`),
AMD/ES6 JS built via `grunt amd --force`, Mustache templates, Playwright
(`/home/vagrant/.venvs/playwright`) for live verification against
`https://vagrant.wisecat.net` (admin/Passw0rd!).

## Global Constraints

- Repos: `/vagrant/moodle-dev/moodle-mod_confprogram` (current version
  `2026070602`), `/vagrant/moodle-dev/moodle-mod_confscheduler` (current
  version `2026070611`).
- GPL-3.0-or-later header + `@copyright 2026 Adam Jenkins <adam@wisecat.net>`
  docblock tag on every new/touched PHP file (already present in every file
  touched below — preserve, don't strip).
- No new DB schema needed anywhere in this plan (track colour already exists
  as `confsubmissions_track.colour`) — but per this project's established
  convention, bump `version.php` anyway for both plugins so the deploy step
  triggers a cache purge; use `moodle-dev:moodle-bump-version` skill for the
  exact bump on each plugin at the end of its round of work.
- Deploy via targeted rsync only, never symlink:
  `rsync -av --delete <repo>/ /srv/lms/moodle/public/mod/<name>/` (exclude
  `.git`), then `php admin/cli/upgrade.php --non-interactive` from
  `/srv/lms/moodle/public`. Run as the same user PHP normally runs as
  (`sudo -u www-data php ...` for CLI, matching how this session already
  queried the DB).
- Run `phpcs --standard=moodle <path>` (or the `moodle-dev:moodle-codestyle`
  skill) and PHPUnit for each plugin before moving to the next task's commit.
- `moodle-reviewer` agent pass required before final commit of each round
  (see Task 9).
- Site debug is currently ON with display enabled (`$CFG->debug = 30719`,
  `$CFG->debugdisplay = 1`) on the demo site — this is what made the
  review.php crash visible as a full stack trace; leave it on for this
  session's verification, matching established practice.

---

### Task 1: Fix numeric submit buttons in `assign.php`

**Files:**
- Modify: `/vagrant/moodle-dev/moodle-mod_confprogram/assign.php:215-268`
- Modify: `/vagrant/moodle-dev/moodle-mod_confprogram/lang/en/confprogram.php`
- Modify: `/vagrant/moodle-dev/moodle-mod_confprogram/lang/ja/confprogram.php`

**Interfaces:**
- Consumes: nothing from other tasks.
- Produces: nothing other tasks depend on (page-script fix, self-contained).

- [ ] **Step 1: Add the new `assigngroup` lang string**

In `lang/en/confprogram.php`, add (alphabetically, right after the existing
`assignreviewers` entry at line 29):

```php
$string['assigngroup'] = 'Assign group';
```

In `lang/ja/confprogram.php`, add in the same relative position:

```php
$string['assigngroup'] = 'グループを割り当て';
```

- [ ] **Step 2: Replace the three numeric-labelled buttons with `<button>` elements**

In `assign.php`, replace lines 215-221 (the remove-assignment button):

```php
        $removebutton = html_writer::empty_tag('input', [
            'type'  => 'submit',
            'name'  => 'removeassignment',
            'value' => $assignment->id,
            'class' => 'btn btn-link btn-sm p-0 ml-2',
            'title' => get_string('remove'),
        ]);
```

with:

```php
        $removebutton = html_writer::tag('button', get_string('remove'), [
            'type'  => 'submit',
            'name'  => 'removeassignment',
            'value' => $assignment->id,
            'class' => 'btn btn-link btn-sm p-0 ml-2',
        ]);
```

Replace lines 232-239 (the assign-individual button):

```php
    $assigncell = html_writer::select($reviewerselect, 'reviewerselect_' . $submission->id, 0, null)
        . ' '
        . html_writer::empty_tag('input', [
            'type'  => 'submit',
            'name'  => 'assignindividual',
            'value' => $submission->id,
            'class' => 'btn btn-secondary btn-sm',
        ]);
```

with:

```php
    $assigncell = html_writer::select($reviewerselect, 'reviewerselect_' . $submission->id, 0, null)
        . ' '
        . html_writer::tag('button', get_string('assignreviewer', 'mod_confprogram'), [
            'type'  => 'submit',
            'name'  => 'assignindividual',
            'value' => $submission->id,
            'class' => 'btn btn-secondary btn-sm',
        ]);
```

Replace lines 263-268 (the bulk assign-group button):

```php
    echo html_writer::empty_tag('input', [
        'type'  => 'submit',
        'name'  => 'assigngroup',
        'value' => 1,
        'class' => 'btn btn-primary',
    ]);
```

with:

```php
    echo html_writer::tag('button', get_string('assigngroup', 'mod_confprogram'), [
        'type'  => 'submit',
        'name'  => 'assigngroup',
        'value' => 1,
        'class' => 'btn btn-primary',
    ]);
```

Note: no change needed anywhere to the POST handlers (`optional_param('removeassignment', 0, PARAM_INT)` etc. at lines 87, 92, 113) — a `<button type="submit" name="X" value="Y">` submits identically to `<input type="submit" name="X" value="Y">`; only the rendered label changes.

- [ ] **Step 3: Verify with phpcs**

Run: `vendor/bin/phpcs --standard=moodle /vagrant/moodle-dev/moodle-mod_confprogram/assign.php /vagrant/moodle-dev/moodle-mod_confprogram/lang/en/confprogram.php /vagrant/moodle-dev/moodle-mod_confprogram/lang/ja/confprogram.php`
Expected: no errors.

- [ ] **Step 4: Commit**

```bash
cd /vagrant/moodle-dev/moodle-mod_confprogram
git add assign.php lang/en/confprogram.php lang/ja/confprogram.php
git commit -m "Fix assign.php buttons showing a raw id instead of a label

<button> elements let value (submitted) differ from displayed content,
unlike <input type=submit> where they're the same attribute."
```

---

### Task 2: Fix numeric submit buttons in `unvetted.php`

**Files:**
- Modify: `/vagrant/moodle-dev/moodle-mod_confprogram/unvetted.php:124-138`

**Interfaces:**
- Consumes: nothing from other tasks.
- Produces: nothing other tasks depend on.

- [ ] **Step 1: Replace both numeric-labelled buttons with `<button>` elements**

Replace lines 124-138:

```php
    if ($isunvetted) {
        $form .= html_writer::empty_tag('input', [
            'type'  => 'submit',
            'name'  => 'unmarkunvetted',
            'value' => $submission->id,
            'class' => 'btn btn-secondary btn-sm',
        ]) . ' ' . get_string('unmarkunvetted', 'mod_confprogram');
    } else {
        $form .= html_writer::empty_tag('input', [
            'type'  => 'submit',
            'name'  => 'markunvetted',
            'value' => $submission->id,
            'class' => 'btn btn-secondary btn-sm',
        ]) . ' ' . get_string('markunvetted', 'mod_confprogram');
    }
```

with:

```php
    if ($isunvetted) {
        $form .= html_writer::tag('button', get_string('unmarkunvetted', 'mod_confprogram'), [
            'type'  => 'submit',
            'name'  => 'unmarkunvetted',
            'value' => $submission->id,
            'class' => 'btn btn-secondary btn-sm',
        ]);
    } else {
        $form .= html_writer::tag('button', get_string('markunvetted', 'mod_confprogram'), [
            'type'  => 'submit',
            'name'  => 'markunvetted',
            'value' => $submission->id,
            'class' => 'btn btn-secondary btn-sm',
        ]);
    }
```

(The lang string is now the button's own content instead of trailing sibling
text — no lang string changes needed, both already exist.)

- [ ] **Step 2: Verify with phpcs**

Run: `vendor/bin/phpcs --standard=moodle /vagrant/moodle-dev/moodle-mod_confprogram/unvetted.php`
Expected: no errors.

- [ ] **Step 3: Commit**

```bash
cd /vagrant/moodle-dev/moodle-mod_confprogram
git add unvetted.php
git commit -m "Fix unvetted.php buttons showing a raw id instead of a label

Same fix as assign.php: <button> instead of <input type=submit> so the
row id (submitted) and the label (displayed) aren't forced to be the
same attribute."
```

---

### Task 3: Fix `review.php`'s redirect-after-output crash

**Files:**
- Modify: `/vagrant/moodle-dev/moodle-mod_confprogram/review.php` (single-submission mode, lines 159-330)

**Interfaces:**
- Consumes: nothing from other tasks.
- Produces: nothing other tasks depend on. Task 8 (track pill in review.php)
  edits this same file's submission-detail rendering block further down —
  do Task 3 first so Task 8 works from the corrected structure.

- [ ] **Step 1: Reproduce the bug first (if not already confirmed in this session)**

Two throwaway Playwright scripts already exist from the root-cause
investigation and reproduce this live:
`/tmp/claude-1000/-vagrant-moodle-dev-moodle-conference-tools/a073adda-1bb6-4356-adf9-d276cda58563/scratchpad/repro_review_bug.py` (Cancel path, submission id 19, confprogram cmid 3)
`/tmp/claude-1000/-vagrant-moodle-dev-moodle-conference-tools/a073adda-1bb6-4356-adf9-d276cda58563/scratchpad/repro_review_submit.py` (Submit path, submission id 29, same cmid)

Run: `/home/vagrant/.venvs/playwright/bin/python <path>/repro_review_bug.py 2>&1 | tail -40`
Expected (pre-fix): `Whoops \ Exception \ ErrorException (E_USER_NOTICE)` /
`You should really redirect before you start page output`, stack frame
`.../mod/confprogram/review.php:307`.

If a fresh reviewer wants to re-confirm rather than trust this session's
prior run, this step is how.

- [ ] **Step 2: Restructure `review.php`'s single-submission mode**

Replace the entire block from `// Single-submission review mode.` (line 159)
through the end of the file (line 331) with the version below. The only
logic change is REORDERING — no check's condition, no `redirect()`'s
arguments, no `api::` call's arguments differ from the current code; only
the point at which `echo $OUTPUT->header()` and the informational rendering
happen moves to after all cancel/submit/redirect handling.

```php
// Single-submission review mode.
$submission = submissions_api::get_submission($submissionid);
if (!$submission || (int) $submission->confsubmissions !== (int) $confsubmissionscm->instance) {
    throw new \moodle_exception('invalidrecord', 'error', '', 'confsubmissions_submission');
}

// The assignment check MUST run before the unvetted check, not after: mod/confprogram:review
// is a broad, instance-wide capability (not per-submission), so any reviewer can request any
// submissionid in this instance. If the unvetted check ran first, an unassigned reviewer would
// get a distinguishable error for unvetted vs. non-unvetted submissions -- an oracle that lets
// them enumerate which submissions are unvetted, which must be completely invisible to them.
if (!api::is_user_assigned((int) $confprogram->id, $submissionid, (int) $USER->id)) {
    throw new \moodle_exception('error:notassigned', 'mod_confprogram');
}

// Defensive re-check: the queue listing already excludes unvetted submissions, but this
// page must not trust a stale/crafted link either (task 5's race-condition guard). Safe to
// check now that we know the caller is genuinely assigned to this submission.
if (in_array($submissionid, $unvettedids, true)) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($confprogram->name), 2);
    echo $OUTPUT->notification(get_string('error:unvetted', 'mod_confprogram'), 'error');
    echo $OUTPUT->footer();
    exit;
}

$round = rounds::get_current_round((int) $confprogram->id, $submissionid);
$existingreview = api::get_review((int) $confprogram->id, $submissionid, (int) $USER->id, $round);

// Max-reviews cap: only enforced for *starting* a new review. Re-editing a review the
// reviewer has already completed for this exact submission+round is never blocked.
if (!$existingreview && !reviewer_workload::has_capacity((int) $confprogram->id, (int) $USER->id, $round)) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($confprogram->name), 2);
    echo $OUTPUT->notification(get_string('error:reviewcapreached', 'mod_confprogram'), 'error');
    echo $OUTPUT->footer();
    exit;
}

// IMPORTANT: the grading API's itemid here is deliberately the confprogram_review row id,
// NOT $submissionid, despite the integration recipe's example using the submission id as
// itemid. Reason: gradingform_controller::get_current_instance() (and, through it,
// create_instance(), which get_or_create_instance() falls back to when no instance yet
// exists) filters only by itemid, NOT raterid -- the raterid filter is present in the code
// but wrapped in "if (false)" behind a "TODO MDL-31237 should be: if
// ($manager->allow_multiple_raters())" comment (grade/grading/form/lib.php). In other
// words core's advanced grading API does not yet support more than one rater grading the
// same itemid independently: the second reviewer to open a submission that another
// reviewer has already scored would have their form silently pre-filled with the first
// reviewer's rubric answers (found via that rater-blind lookup), which would both break
// review independence and leak content across a blind review boundary.
//
// Fixing this without patching core: mint an itemid that is unique per
// (confprogram, submissionid, reviewerid, round) by using the confprogram_review row's own
// id, ensuring the row exists (as a blank placeholder, upserted with a null grade) before
// the grading instance is created. No other reviewer can ever collide with this itemid, so
// the core lookup above can never find a different rater's instance. The same id is passed
// to submit_and_get_grade() below, per its docblock ("itemid must be specified here").
if (!$existingreview) {
    api::upsert_review((int) $confprogram->id, $submissionid, (int) $USER->id, $round, 0, null);
    $existingreview = api::get_review((int) $confprogram->id, $submissionid, (int) $USER->id, $round);
}
$reviewitemid = (int) $existingreview->id;

$gradingmanager = get_grading_manager($context, 'mod_confprogram', 'review');
$gradinginstance = null;
// Deferred until after header() (below) -- built here so the decision of WHICH notice (if
// any) to show is made before any output, but the notice is only ever echoed once we know
// this request is neither a cancel nor a successful submit.
$noreviewformnotice = null;

if ($gradingmethod = $gradingmanager->get_active_method()) {
    $controller = $gradingmanager->get_controller($gradingmethod);
    if ($controller->is_form_available()) {
        // Reuse this reviewer's own existing grading instance for this submission+round
        // when re-editing or re-posting after a validation error, rather than letting the
        // grading API spin up a fresh copy every time; 0 means "create a new one" and is
        // only ever hit the first time this reviewer opens this submission+round.
        $default = (int) $existingreview->gradinginstanceid;
        $instanceid = optional_param('advancedgradinginstanceid', $default, PARAM_INT);
        $controller->set_grade_range(make_grades_menu(100), true);
        $gradinginstance = $controller->get_or_create_instance($instanceid, $USER->id, $reviewitemid);
    } else {
        $noreviewformnotice = $controller->form_unavailable_notification();
    }
} else if (has_capability('mod/confprogram:managereviewers', $context)) {
    $manageurl = new moodle_url('/grade/grading/manage.php', [
        'contextid'  => $context->id,
        'component'  => 'mod_confprogram',
        'area'       => 'review',
        'returnurl'  => $pageurl->out(false),
    ]);
    $noreviewformnotice = get_string('error:noreviewform', 'mod_confprogram') . ' '
        . html_writer::link($manageurl, get_string('setupreviewform', 'mod_confprogram'));
} else {
    $noreviewformnotice = get_string('error:noreviewform', 'mod_confprogram');
}

$mform = new review_form($pageurl, ['gradinginstance' => $gradinginstance]);

// This is the fix: is_cancelled()/get_data() are checked, and any resulting redirect()
// fired, BEFORE echo $OUTPUT->header() below -- unlike the previous version of this file,
// which rendered the submission detail (header, heading, speakers, track, abstract,
// optional fields) before ever reaching this point. redirect() cannot perform a real HTTP
// redirect once the page has started outputting (moodle_page::state past
// STATE_BEFORE_HEADER); calling it after output had already started this cancel/submit
// error live (see this plugin's changelog.md) with a full "You should really redirect
// before you start page output" stack trace. Every sibling page in this plugin
// (assign.php, decisions.php, unvetted.php) already does all POST-handling-and-redirect
// before any header output -- this restores that same, already-correct pattern here.
if ($mform->is_cancelled()) {
    redirect($queueurl);
} else if ($gradinginstance && ($data = $mform->get_data())) {
    // Same itemid as used to create the instance above -- see the block comment there.
    $grade = $gradinginstance->submit_and_get_grade($data->advancedgrading, $reviewitemid);
    // Note: submit_and_get_grade() returns -1 (not null) for an intentionally-empty/cleared
    // rubric; normalise that sentinel to null to match the schema's "null until submitted"
    // contract for confprogram_review.grade.
    $storedgrade = ($grade !== null && (float) $grade >= 0) ? (float) $grade : null;

    api::upsert_review(
        (int) $confprogram->id,
        $submissionid,
        (int) $USER->id,
        $round,
        (int) $gradinginstance->get_id(),
        $storedgrade
    );

    redirect($queueurl, get_string('reviewsaved', 'mod_confprogram'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Reached only on the initial GET, or a redraw after a validation error -- neither
// cancelled nor a successful submit. Safe to start page output from here on.
$canviewidentity = identity::can_view_identity($context);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($confprogram->name), 2);
echo $OUTPUT->heading(format_string($submission->title), 3);

// Speaker identity is only fetched at all when the viewer is allowed to see it (defence in
// depth against a stray debug/log statement leaking it while blinded).
if ($canviewidentity) {
    $speakerlines = [];
    foreach (submissions_api::get_speakers($submissionid) as $speaker) {
        if (!empty($speaker->userid)) {
            $user = \core_user::get_user($speaker->userid);
            $speakerlines[] = $user ? fullname($user) : '-';
        } else if (!empty($speaker->name)) {
            $speakerlines[] = format_string($speaker->name);
        }
    }
    if ($speakerlines) {
        echo html_writer::tag('p', html_writer::tag('strong', get_string('speakers', 'mod_confsubmissions') . ': ')
            . implode(', ', $speakerlines));
    }
} else {
    echo $OUTPUT->notification(get_string('identityhidden', 'mod_confprogram'), 'info');
}

if (!empty($submission->trackid)) {
    $track = $DB->get_record('confsubmissions_track', ['id' => $submission->trackid]);
    if ($track) {
        echo html_writer::tag('p', html_writer::tag('strong', get_string('track', 'mod_confsubmissions') . ': ')
            . format_string($track->name));
    }
}

echo html_writer::tag('div', format_text($submission->abstract, FORMAT_PLAIN), ['class' => 'mb-3']);

// Every organiser-defined optional field is shown here unconditionally, regardless of
// the Display-phase show-in-list/show-in-modal visibility matrix (classes/local/
// field_settings.php) -- that matrix only governs what the public Display-phase list
// surfaces; a reviewer here always sees everything. Fields are identified by their
// confsubmissions_field id, not name: mod_confsubmissions's fields are organiser-free-text
// (not a fixed lang-string vocabulary), so a field's own name is used directly as its
// label rather than looked up via get_string().
$fieldvalues = submissions_api::get_optional_field_values($submissionid);
foreach (submissions_api::get_fields($confsubmissionscm->instance) as $field) {
    $value = $fieldvalues[$field->id] ?? '';
    if ($value === '') {
        continue;
    }
    echo html_writer::tag('p', html_writer::tag('strong', format_string($field->name) . ': ') . s($value));
}

echo $OUTPUT->heading(get_string('review', 'mod_confprogram'), 3);

if ($noreviewformnotice !== null) {
    echo $OUTPUT->notification($noreviewformnotice, $gradinginstance === null && $gradingmanager->get_active_method() ? 'warning' : 'warning');
}

$mform->display();

echo $OUTPUT->footer();
```

Note: the `$noreviewformnotice` ternary above is intentionally always
`'warning'` on both branches — written that way (rather than a plain
`'warning'` literal) only because it mirrors exactly which of the three
original `echo $OUTPUT->notification(..., 'warning')` call sites produced
`$noreviewformnotice`; simplify to a plain `'warning'` string, since all
three original branches used the same message type. Use:

```php
if ($noreviewformnotice !== null) {
    echo $OUTPUT->notification($noreviewformnotice, 'warning');
}
```

- [ ] **Step 3: Deploy and re-run both reproduction scripts to confirm the fix**

```bash
rsync -av --delete /vagrant/moodle-dev/moodle-mod_confprogram/ /srv/lms/moodle/public/mod/confprogram/ --exclude .git
cd /srv/lms/moodle/public && sudo -u www-data php admin/cli/upgrade.php --non-interactive
```

Run: `/home/vagrant/.venvs/playwright/bin/python <scratchpad>/repro_review_bug.py 2>&1 | tail -20`
Expected: after the "Cancel" click, `page.url` is
`https://vagrant.wisecat.net/mod/confprogram/review.php` (the queue, no
`submissionid`) with NO `Whoops` text anywhere in the body — a clean
redirect.

Run: `/home/vagrant/.venvs/playwright/bin/python <scratchpad>/repro_review_submit.py 2>&1 | tail -20`
Expected: after clicking each rubric level cell and submitting, no `Whoops`
text; the page redirects to the queue with a "Review saved" success
notification visible.

- [ ] **Step 4: Run this plugin's full PHPUnit suite to confirm no regression**

```bash
cd /srv/lms/moodle/public
sudo -u www-data vendor/bin/phpunit --testsuite mod_confprogram_testsuite
```
Expected: all tests passing (same count as before this change — this is a
pure reorder, not a logic change, so no existing test's assertions should
be affected).

- [ ] **Step 5: phpcs**

Run: `vendor/bin/phpcs --standard=moodle /vagrant/moodle-dev/moodle-mod_confprogram/review.php`
Expected: no errors.

- [ ] **Step 6: Commit**

```bash
cd /vagrant/moodle-dev/moodle-mod_confprogram
git add review.php
git commit -m "Fix review.php crashing on submit/cancel (redirect after output started)

echo \$OUTPUT->header() and page rendering happened before the form's
is_cancelled()/get_data() checks and their redirect() calls, so by the
time redirect() ran the page was already mid-output and Moodle could not
perform a real HTTP redirect -- reproduced live for both Cancel and a
successful Submit (full stack trace: 'You should really redirect before
you start page output', review.php:307/:325). Fixed by moving all
cancel/submit/redirect handling above the header() call, matching the
pattern every sibling page in this plugin (assign.php, decisions.php,
unvetted.php) already uses."
```

---

### Task 4: `field_formatter::get_track_pill_html()` + PHP contrast helper

**Files:**
- Modify: `/vagrant/moodle-dev/moodle-mod_confprogram/classes/local/field_formatter.php`
- Modify: `/vagrant/moodle-dev/moodle-mod_confprogram/tests/local/field_formatter_test.php`
- Modify: `/vagrant/moodle-dev/moodle-mod_confprogram/styles.css`

**Interfaces:**
- Consumes: `confsubmissions_track` row shape (`id`, `name`, `colour` —
  6-digit hex string or null, per `mod_confsubmissions`'s existing schema).
- Produces: `field_formatter::get_track_pill_html(\stdClass $submission): string`
  — trusted HTML, safe to echo/insert raw (NOT run through `s()` or an
  auto-escaping Mustache tag) — consumed by Tasks 5, 6, 7.

- [ ] **Step 1: Extend the existing `create_submission()` helper to accept an optional track, and write the failing tests**

This file's existing private helper (lines 39-56) is:

```php
    private function create_submission(int $confsubmissionsid): \stdClass {
        global $DB;

        $userid = $this->getDataGenerator()->create_user()->id;
        $id = $DB->insert_record('confsubmissions_submission', (object) [
            'confsubmissions' => $confsubmissionsid,
            'userid'          => $userid,
            'title'           => 'A Test Talk',
            'abstract'        => 'Abstract text',
            'status'          => 'submitted',
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        return $DB->get_record('confsubmissions_submission', ['id' => $id]);
    }
```

Add an optional trailing `?int $trackid = null` parameter (backward
compatible — no existing call site passes a third argument, so they're
unaffected) and include it in the inserted record:

```php
    private function create_submission(int $confsubmissionsid, ?int $trackid = null): \stdClass {
        global $DB;

        $userid = $this->getDataGenerator()->create_user()->id;
        $id = $DB->insert_record('confsubmissions_submission', (object) [
            'confsubmissions' => $confsubmissionsid,
            'userid'          => $userid,
            'trackid'         => $trackid,
            'title'           => 'A Test Talk',
            'abstract'        => 'Abstract text',
            'status'          => 'submitted',
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        return $DB->get_record('confsubmissions_submission', ['id' => $id]);
    }
```

Add these test methods to the same class (using
`\mod_confsubmissions\api::add_track(int $confsubmissionsid, string $name,
?string $colour = null, ?string $icon = null): int` — this plugin's own
`use mod_confsubmissions\api as submissions_api;` import at the top of the
file already covers this, so call it as `submissions_api::add_track(...)`):

```php
    /**
     * A submission with a coloured track gets a pill with that colour as its
     * background, and white text (the track's colour, #3366cc, is dark enough
     * by the YIQ formula that white text is the correct contrast pick).
     */
    public function test_get_track_pill_html_with_colour(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $confsubmissions = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);
        $trackid = submissions_api::add_track((int) $confsubmissions->id, 'AI & Machine Learning', '#3366cc');
        $submission = $this->create_submission((int) $confsubmissions->id, $trackid);

        $html = field_formatter::get_track_pill_html($submission);

        $this->assertStringContainsString('mod_confprogram-track-pill', $html);
        $this->assertStringContainsString('background-color:#3366cc', $html);
        $this->assertStringContainsString('color:#ffffff', $html);
        // format_string()-escaped, not double-escaped: a literal '&' stays '&amp;' once.
        $this->assertStringContainsString('AI &amp; Machine Learning', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
    }

    /**
     * A track with no configured colour gets the pill markup with no inline
     * background-color style at all, so the plugin's own default CSS colour applies.
     */
    public function test_get_track_pill_html_without_colour(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $confsubmissions = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);
        $trackid = submissions_api::add_track((int) $confsubmissions->id, 'Uncoloured Track');
        $submission = $this->create_submission((int) $confsubmissions->id, $trackid);

        $html = field_formatter::get_track_pill_html($submission);

        $this->assertStringContainsString('mod_confprogram-track-pill', $html);
        $this->assertStringNotContainsString('background-color', $html);
        $this->assertStringContainsString('Uncoloured Track', $html);
    }

    /**
     * A submission with no track at all falls back to the existing plain
     * "notrack" string, not an empty/broken pill.
     */
    public function test_get_track_pill_html_no_track(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $confsubmissions = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);
        $submission = $this->create_submission((int) $confsubmissions->id);

        $html = field_formatter::get_track_pill_html($submission);

        $this->assertSame(get_string('notrack', 'mod_confsubmissions'), $html);
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
cd /srv/lms/moodle/public
sudo -u www-data vendor/bin/phpunit --filter get_track_pill_html mod/confprogram/tests/local/field_formatter_test.php
```
Expected: FAIL — `get_track_pill_html()` doesn't exist yet (`Error: Call to
undefined method`).

- [ ] **Step 3: Implement `get_track_pill_html()` and the PHP contrast helper**

Add to `classes/local/field_formatter.php` (as new public/private static
methods on the existing `field_formatter` class):

```php
    /**
     * Returns a submission's track as trusted, ready-to-echo HTML: a coloured
     * "pill" badge (mirroring mod_confscheduler's identical
     * .mod_confscheduler-track-pill visual language, via this plugin's own
     * .mod_confprogram-track-pill CSS class), or the existing plain "no track"
     * string when the submission has none.
     *
     * Deliberately separate from format_value('track', ...): that method's
     * contract is "never returns HTML, caller escapes it" (the Display list
     * wraps it in s(), the modal template uses an auto-escaping {{ }} tag) --
     * a pill is real markup and returning it from format_value() would break
     * that contract for every other field sharing the same generic loop. This
     * follows the exact precedent already used for the 'title' field: excluded
     * from the generic per-field loop, rendered in its own dedicated slot,
     * still gated by the same show-in-list/show-in-modal visibility setting by
     * the caller.
     *
     * @param \stdClass $submission The confsubmissions_submission record
     * @return string Trusted HTML -- do not pass through s()/format_string() again
     */
    public static function get_track_pill_html(\stdClass $submission): string {
        global $DB;

        if (empty($submission->trackid)) {
            return get_string('notrack', 'mod_confsubmissions');
        }

        $track = $DB->get_record('confsubmissions_track', ['id' => $submission->trackid]);
        if (!$track) {
            return get_string('notrack', 'mod_confsubmissions');
        }

        $name = format_string($track->name, true, ['escape' => false]);
        $style = '';
        if (!empty($track->colour)) {
            $textcolour = self::contrast_text_colour($track->colour);
            $style = "background-color:{$track->colour};color:{$textcolour}";
        }

        // Fully-qualified: this file's namespace is mod_confprogram\local, and it has no
        // existing `use html_writer;` import (core classes resolve globally only when
        // referenced with a leading backslash from inside a namespace).
        return \html_writer::tag('span', $name, [
            'class' => 'mod_confprogram-track-pill',
            'style' => $style,
        ]);
    }

    /**
     * Picks black or white text to sit legibly on top of a given background hex
     * colour, using the classic YIQ "perceived brightness" formula. A PHP-side
     * duplicate of mod_confscheduler/amd/src/colour_utils.js's
     * contrastTextColour() (kept in sync by hand -- see that module's own
     * docblock for why this project duplicates small pure display logic across
     * the PHP/JS boundary rather than sharing it).
     *
     * @param string $hex A 6-digit hex colour, with or without a leading '#'
     * @return string '#000000' or '#ffffff'
     */
    private static function contrast_text_colour(string $hex): string {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;

        return $brightness >= 128 ? '#000000' : '#ffffff';
    }
```

No `use` statement needed for `html_writer` — see the fully-qualified
`\html_writer::tag(...)` call above.

- [ ] **Step 4: Run the tests to verify they pass**

```bash
cd /srv/lms/moodle/public
sudo -u www-data vendor/bin/phpunit --filter get_track_pill_html mod/confprogram/tests/local/field_formatter_test.php
```
Expected: 3 tests, all PASS.

- [ ] **Step 5: Add the CSS**

Add to `/vagrant/moodle-dev/moodle-mod_confprogram/styles.css` (append at
the end):

```css
.mod_confprogram-track-pill {
    display: inline-block;
    background: #3366cc;
    color: #fff;
    border-radius: 10px;
    padding: 0 0.4rem;
    font-size: 0.75rem;
    white-space: nowrap;
}
```

- [ ] **Step 6: phpcs + full field_formatter test file**

```bash
vendor/bin/phpcs --standard=moodle /vagrant/moodle-dev/moodle-mod_confprogram/classes/local/field_formatter.php
cd /srv/lms/moodle/public
sudo -u www-data vendor/bin/phpunit mod/confprogram/tests/local/field_formatter_test.php
```
Expected: phpcs clean; all tests in the file passing (not just the 3 new ones).

- [ ] **Step 7: Commit**

```bash
cd /vagrant/moodle-dev/moodle-mod_confprogram
git add classes/local/field_formatter.php tests/local/field_formatter_test.php styles.css
git commit -m "Add field_formatter::get_track_pill_html() for coloured track badges

Separate from format_value('track', ...), which must keep returning
plain text (its callers escape it, some via an auto-escaping mustache
tag) -- mirrors the existing precedent for the 'title' field, which is
already excluded from the generic escaped-fields loop for the same
reason."
```

---

### Task 5: Wire the track pill into `view.php`'s Display-phase list

**Files:**
- Modify: `/vagrant/moodle-dev/moodle-mod_confprogram/view.php:248-252,326-331`

**Interfaces:**
- Consumes: `field_formatter::get_track_pill_html(\stdClass $submission): string` (Task 4).
- Produces: nothing other tasks depend on.

- [ ] **Step 1: Exclude 'track' from the generic escaped-fields loop**

Replace lines 249-252:

```php
    $listfields = array_values(array_diff(
        field_settings::get_visible_fieldnames((int) $confprogram->id, $availablefields, 'list'),
        ['title'] // Title is always rendered as the row's clickable link; never duplicated as a plain field.
    ));
```

with:

```php
    $allvisiblelistfields = field_settings::get_visible_fieldnames((int) $confprogram->id, $availablefields, 'list');
    $showtrackpill = in_array('track', $allvisiblelistfields, true);
    $listfields = array_values(array_diff(
        $allvisiblelistfields,
        // Title is always rendered as the row's clickable link, never duplicated as a plain
        // field; track gets its own coloured-pill cell below (built from trusted HTML, not
        // run through s() like every other field in this loop) instead of a plain-text cell.
        ['title', 'track']
    ));
```

- [ ] **Step 2: Add the dedicated track-pill cell in `$rendertable`**

The `use` clause of the `$rendertable` closure and its row-building loop
need `$showtrackpill` and `field_formatter`. Replace:

```php
    $rendertable = function (array $rows) use ($listfields, $cm, $canfavourite, $USER) {
```

with:

```php
    $rendertable = function (array $rows) use ($listfields, $showtrackpill, $cm, $canfavourite, $USER) {
```

Then, in the same closure, replace the table-head-building loop:

```php
        $head = [get_string('title', 'mod_confsubmissions')];
        foreach ($listfields as $fieldname) {
            $head[] = field_formatter::get_label($fieldname);
        }
```

with:

```php
        $head = [get_string('title', 'mod_confsubmissions')];
        if ($showtrackpill) {
            $head[] = get_string('track', 'mod_confsubmissions');
        }
        foreach ($listfields as $fieldname) {
            $head[] = field_formatter::get_label($fieldname);
        }
```

And, in the row loop, right after the `$titlecell`/`$data[] = $titlecell;`
lines (currently lines 322-324), insert the pill cell before the generic
`foreach ($listfields as $fieldname)` loop:

```php
            $titlecell = new html_table_cell($titlelink);
            $titlecell->attributes['data-label'] = get_string('title', 'mod_confsubmissions');
            $data[] = $titlecell;

            if ($showtrackpill) {
                $trackcell = new html_table_cell(field_formatter::get_track_pill_html($submission));
                $trackcell->attributes['data-label'] = get_string('track', 'mod_confsubmissions');
                $data[] = $trackcell;
            }

            foreach ($listfields as $fieldname) {
```

(Everything after that `foreach` — the generic `s(format_value(...))` loop,
the schedule cell, the favourite toggle — is unchanged.)

- [ ] **Step 3: Deploy and verify live**

```bash
rsync -av --delete /vagrant/moodle-dev/moodle-mod_confprogram/ /srv/lms/moodle/public/mod/confprogram/ --exclude .git
cd /srv/lms/moodle/public && sudo -u www-data php admin/cli/upgrade.php --non-interactive
```

Use Playwright to load a confprogram instance in Display phase whose
accepted submissions have a track with a configured colour, and confirm:
the "Track" column header still appears when track is configured visible in
the list; the cell renders a coloured pill (inspect the actual DOM's
`background-color` style, not just a screenshot); a submission whose track
has no colour shows the pill in the plugin's default blue.

- [ ] **Step 4: phpcs**

Run: `vendor/bin/phpcs --standard=moodle /vagrant/moodle-dev/moodle-mod_confprogram/view.php`
Expected: no errors.

- [ ] **Step 5: Full PHPUnit run for the plugin (confirm no regression)**

```bash
cd /srv/lms/moodle/public
sudo -u www-data vendor/bin/phpunit --testsuite mod_confprogram_testsuite
```
Expected: all passing.

- [ ] **Step 6: Commit**

```bash
cd /vagrant/moodle-dev/moodle-mod_confprogram
git add view.php
git commit -m "Render track as a coloured pill in the Display-phase list

Excludes 'track' from the generic escaped-fields loop (same precedent
already used for 'title') and renders it via the new
field_formatter::get_track_pill_html() in its own dedicated cell,
still gated by the existing show-in-list visibility setting."
```

---

### Task 6: Wire the track pill into the submission detail modal

**Files:**
- Modify: `/vagrant/moodle-dev/moodle-mod_confprogram/classes/external/get_submission_detail.php`
- Modify: `/vagrant/moodle-dev/moodle-mod_confprogram/classes/output/submission_modal.php`
- Modify: `/vagrant/moodle-dev/moodle-mod_confprogram/templates/submission_modal.mustache`
- Modify: `/vagrant/moodle-dev/moodle-mod_confprogram/tests/external/get_submission_detail_test.php`

**Interfaces:**
- Consumes: `field_formatter::get_track_pill_html(\stdClass $submission): string` (Task 4).
- Produces: nothing other tasks depend on.

- [ ] **Step 1: Write the failing test**

This file's existing `create_fixture(string $phase = 'display')` helper
(lines 39-64) builds a course + confsubmissions + confprogram + one bare
submission with no track, via direct `$DB->insert_record()`, returning
`[$course, $cmid, $submissionid, $confprogramid]`. `'track'` is
`showinmodal => true` by default (no `confprogram_fieldsetting` row needed
for a fresh instance — see `field_settings::get_settings_with_defaults()`),
so no visibility configuration step is needed in the test. Add a new test
method to the same class, using `\mod_confsubmissions\api::add_track()`
directly (add `use mod_confsubmissions\api as submissions_api;` to this
file's existing `use` block) and setting `trackid` on the submission after
creation:

```php
    /**
     * When 'track' is visible in the modal (the default), its value renders as
     * the coloured pill span, not a plain "Track: X" label/value row.
     */
    public function test_track_renders_as_a_pill_not_a_plain_field(): void {
        global $DB;
        $this->resetAfterTest();

        [$course, $cmid, $submissionid, $confprogramid] = $this->create_fixture('display');
        $confsubmissionsid = $DB->get_field('confsubmissions_submission', 'confsubmissions', ['id' => $submissionid]);
        $trackid = submissions_api::add_track((int) $confsubmissionsid, 'Data Science', '#3366cc');
        $DB->set_field('confsubmissions_submission', 'trackid', $trackid, ['id' => $submissionid]);
        $decider = $this->getDataGenerator()->create_user();
        api::record_decision($confprogramid, $submissionid, 'accept', 1, (int) $decider->id);

        $viewer = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($viewer);

        $result = get_submission_detail::execute($cmid, $submissionid);

        $this->assertStringContainsString('mod_confprogram-track-pill', $result['html']);
        $this->assertStringContainsString('background-color:#3366cc', $result['html']);
        // The generic label/value row for 'track' must NOT also appear.
        $this->assertStringNotContainsString('>Track<', $result['html']);
    }
```

- [ ] **Step 2: Run to verify it fails**

```bash
cd /srv/lms/moodle/public
sudo -u www-data vendor/bin/phpunit --filter test_track_renders_as_a_pill_not_a_plain_field mod/confprogram/tests/external/get_submission_detail_test.php
```
Expected: FAIL (no `mod_confprogram-track-pill` in the current output).

- [ ] **Step 3: Exclude 'track' from the modal's generic fields loop, add a dedicated slot**

In `get_submission_detail.php`, replace:

```php
        $modalfields = array_values(array_diff(
            field_settings::get_visible_fieldnames((int) $confprogram->id, $availablefields, 'modal'),
            ['title']
        ));

        $fields = [];
        foreach ($modalfields as $fieldname) {
            $value = field_formatter::format_value($fieldname, $submission);
            if ($value === '') {
                continue;
            }
            $fields[] = ['label' => field_formatter::get_label($fieldname), 'value' => $value];
        }

        $scheduletext = schedule_info::format_for_display(schedule_info::get_for_submission((int) $submission->id));

        $modal = new submission_modal($fields, $scheduletext);
```

with:

```php
        $allvisiblemodalfields = field_settings::get_visible_fieldnames((int) $confprogram->id, $availablefields, 'modal');
        // Title is excluded here even if configured showinmodal: it is always used as the
        // modal's own heading (below), so listing it again as a field would be a duplicate --
        // matching the same exclusion view.php applies to the list's title column. Track is
        // excluded for the same reason 'title' is: it gets its own trusted-HTML pill slot
        // (see field_formatter::get_track_pill_html()'s docblock), not a plain escaped
        // label/value row like every other field here.
        $modalfields = array_values(array_diff($allvisiblemodalfields, ['title', 'track']));

        $fields = [];
        foreach ($modalfields as $fieldname) {
            $value = field_formatter::format_value($fieldname, $submission);
            if ($value === '') {
                continue;
            }
            $fields[] = ['label' => field_formatter::get_label($fieldname), 'value' => $value];
        }

        $trackpill = in_array('track', $allvisiblemodalfields, true)
            ? field_formatter::get_track_pill_html($submission)
            : null;

        $scheduletext = schedule_info::format_for_display(schedule_info::get_for_submission((int) $submission->id));

        $modal = new submission_modal($fields, $scheduletext, $trackpill);
```

- [ ] **Step 4: Update `submission_modal.php` to accept and export `trackpill`**

Replace the class:

```php
class submission_modal implements renderable, templatable {
    /** @var array{label: string, value: string}[] The visible (showinmodal) fields, in order */
    protected $fields;

    /** @var string The formatted schedule text, including the "not yet scheduled" fallback */
    protected $scheduletext;

    /**
     * Constructor.
     *
     * @param array $fields The visible (showinmodal) fields, each ['label' => ..., 'value' => ...]
     * @param string $scheduletext The formatted schedule text
     */
    public function __construct(array $fields, string $scheduletext) {
        $this->fields = $fields;
        $this->scheduletext = $scheduletext;
    }

    /**
     * Exports data for the submission_modal.mustache template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'scheduletext' => $this->scheduletext,
            'hasfields'    => !empty($this->fields),
            'fields'       => $this->fields,
        ];
    }
}
```

with:

```php
class submission_modal implements renderable, templatable {
    /** @var array{label: string, value: string}[] The visible (showinmodal) fields, in order */
    protected $fields;

    /** @var string The formatted schedule text, including the "not yet scheduled" fallback */
    protected $scheduletext;

    /**
     * @var string|null Trusted HTML for the track pill (see
     * field_formatter::get_track_pill_html()), or null when 'track' is not
     * configured visible in the modal for this instance. Rendered via a raw
     * {{{ }}} tag in the template -- the one deliberate exception to this
     * class's otherwise-fully-escaped fields, for the same reason 'title' is
     * also handled outside the generic fields loop.
     */
    protected $trackpill;

    /**
     * Constructor.
     *
     * @param array $fields The visible (showinmodal) fields, each ['label' => ..., 'value' => ...]
     * @param string $scheduletext The formatted schedule text
     * @param string|null $trackpill Trusted HTML for the track pill, or null to omit it
     */
    public function __construct(array $fields, string $scheduletext, ?string $trackpill = null) {
        $this->fields = $fields;
        $this->scheduletext = $scheduletext;
        $this->trackpill = $trackpill;
    }

    /**
     * Exports data for the submission_modal.mustache template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'scheduletext'  => $this->scheduletext,
            'hasfields'     => !empty($this->fields),
            'fields'        => $this->fields,
            'hastrackpill'  => $this->trackpill !== null,
            'trackpill'     => $this->trackpill,
        ];
    }
}
```

- [ ] **Step 5: Render the pill in the template**

`Read` `templates/submission_modal.mustache` first to see its current
structure (heading/schedule/fields loop), then add a track-pill block
right after the heading/schedule area and before the generic `{{#fields}}`
loop, e.g.:

```mustache
{{#hastrackpill}}
<p class="mod_confprogram-submission-modal-track">{{{trackpill}}}</p>
{{/hastrackpill}}
```

(`{{{trackpill}}}` — triple braces, raw/unescaped — is the one deliberate
exception in this template; every other field still goes through the
existing escaped `{{value}}`.)

- [ ] **Step 6: Run the test to verify it passes**

```bash
cd /srv/lms/moodle/public
sudo -u www-data vendor/bin/phpunit mod/confprogram/tests/external/get_submission_detail_test.php
```
Expected: all tests in the file passing, including the new one.

- [ ] **Step 7: Deploy and verify live**

```bash
rsync -av --delete /vagrant/moodle-dev/moodle-mod_confprogram/ /srv/lms/moodle/public/mod/confprogram/ --exclude .git
cd /srv/lms/moodle/public && sudo -u www-data php admin/cli/upgrade.php --non-interactive
```

Via Playwright, open the Display-phase list, click a submission with a
coloured track to open its modal, and confirm the pill renders (inspect the
DOM's actual `background-color`, not a screenshot) and there is no duplicate
plain "Track: X" row.

- [ ] **Step 8: phpcs + mustache lint**

```bash
vendor/bin/phpcs --standard=moodle /vagrant/moodle-dev/moodle-mod_confprogram/classes/external/get_submission_detail.php /vagrant/moodle-dev/moodle-mod_confprogram/classes/output/submission_modal.php
```
Also run the `moodle-dev:moodle-mustache-lint` skill against
`templates/submission_modal.mustache`.
Expected: both clean.

- [ ] **Step 9: Commit**

```bash
cd /vagrant/moodle-dev/moodle-mod_confprogram
git add classes/external/get_submission_detail.php classes/output/submission_modal.php templates/submission_modal.mustache tests/external/get_submission_detail_test.php
git commit -m "Render track as a coloured pill in the submission detail modal

Same exclusion-from-generic-loop pattern as the list (Task 5) and as
'title' already uses in this same modal: track gets a dedicated
trackpill slot rendered via a raw {{{ }}} tag, gated on the same
show-in-modal visibility setting, instead of a plain escaped
label/value row."
```

---

### Task 7: Wire the track pill into `review.php` and `assign.php`

**Files:**
- Modify: `/vagrant/moodle-dev/moodle-mod_confprogram/review.php` (the track line inside the block from Task 3)
- Modify: `/vagrant/moodle-dev/moodle-mod_confprogram/assign.php:248`

**Interfaces:**
- Consumes: `field_formatter::get_track_pill_html(\stdClass $submission): string` (Task 4).
- Produces: nothing other tasks depend on.

- [ ] **Step 1: `review.php`**

In the restructured file from Task 3, replace:

```php
if (!empty($submission->trackid)) {
    $track = $DB->get_record('confsubmissions_track', ['id' => $submission->trackid]);
    if ($track) {
        echo html_writer::tag('p', html_writer::tag('strong', get_string('track', 'mod_confsubmissions') . ': ')
            . format_string($track->name));
    }
}
```

with:

```php
echo html_writer::tag('p', html_writer::tag('strong', get_string('track', 'mod_confsubmissions') . ': ')
    . field_formatter::get_track_pill_html($submission));
```

Add `use mod_confprogram\local\field_formatter;` to the file's existing
`use` block near the top (alongside the existing `use mod_confprogram\api;`
etc.) if it is not already imported.

- [ ] **Step 2: `assign.php`**

Replace line 248:

```php
        $submission->trackid ? ($tracknames[$submission->trackid] ?? '-') : get_string('notrack', 'mod_confsubmissions'),
```

with:

```php
        field_formatter::get_track_pill_html($submission),
```

Add `use mod_confprogram\local\field_formatter;` to `assign.php`'s existing
`use` block. Note: `$tracknames` (built at line 138-141 from
`submissions_api::get_tracks()`) is still used elsewhere in this file for
the track FILTER dropdown (`$trackfilteroptions`) — leave that usage
untouched; only this one table-cell usage changes.

- [ ] **Step 3: Deploy and verify live**

```bash
rsync -av --delete /vagrant/moodle-dev/moodle-mod_confprogram/ /srv/lms/moodle/public/mod/confprogram/ --exclude .git
cd /srv/lms/moodle/public && sudo -u www-data php admin/cli/upgrade.php --non-interactive
```

Via Playwright: open `review.php` for a submission with a coloured track,
confirm the "Track:" line now shows a coloured pill; open `assign.php`,
confirm the track column shows the same pill styling for every row.

- [ ] **Step 4: phpcs**

```bash
vendor/bin/phpcs --standard=moodle /vagrant/moodle-dev/moodle-mod_confprogram/review.php /vagrant/moodle-dev/moodle-mod_confprogram/assign.php
```
Expected: no errors.

- [ ] **Step 5: Full PHPUnit run for the plugin**

```bash
cd /srv/lms/moodle/public
sudo -u www-data vendor/bin/phpunit --testsuite mod_confprogram_testsuite
```
Expected: all passing.

- [ ] **Step 6: Commit**

```bash
cd /vagrant/moodle-dev/moodle-mod_confprogram
git add review.php assign.php
git commit -m "Render track as a coloured pill in review.php and assign.php too

Per explicit request to cover every screen where track is shown, not
just the public Display-phase list/modal. Both were already raw
html_writer/echo contexts (never routed through the escaped-fields
pipeline), so this is a direct substitution with no contract concerns."
```

---

### Task 8: `mod_confscheduler` — thread track colour through `grid_data.php`

**Files:**
- Modify: `/vagrant/moodle-dev/moodle-mod_confscheduler/classes/local/grid_data.php`
- Modify: `/vagrant/moodle-dev/moodle-mod_confscheduler/tests/external/get_grid_data_test.php`

**Interfaces:**
- Consumes: `mod_confsubmissions\api::get_tracks(int $cmid): array` (already
  returns full track rows including `colour` — no upstream change needed).
- Produces: each `slots`/`unscheduled` entry in `grid_data::build()`'s
  return array gains a `trackcolour` key (nullable hex string) — consumed by
  Task 9.

- [ ] **Step 1: Write the failing test**

This file's existing `create_fixture()` (lines 41-76) returns `[$course,
$cmid, $confscheduler, $confprogram, $submissionid]`, where `$submissionid`
has no track set. Add `use mod_confsubmissions\api as submissions_api;` to
this file's existing `use` block, then add:

```php
    /**
     * A scheduled slot's track colour is surfaced in the payload, for the
     * client to theme the track pill with (user request, 2026-07-06).
     */
    public function test_slot_includes_trackcolour(): void {
        global $DB;
        $this->resetAfterTest();

        [$course, $cmid, $confscheduler, , $submissionid] = $this->create_fixture();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $confsubmissionsid = $DB->get_field('confsubmissions_submission', 'confsubmissions', ['id' => $submissionid]);
        $trackid = submissions_api::add_track((int) $confsubmissionsid, 'Data Science', '#3366cc');
        $DB->set_field('confsubmissions_submission', 'trackid', $trackid, ['id' => $submissionid]);

        $roomid = api::add_room((int) $confscheduler->id, 'Main Hall', null, null);
        api::add_slot(
            (int) $confscheduler->id,
            [$roomid],
            strtotime('2026-09-01 10:00:00'),
            strtotime('2026-09-01 10:30:00'),
            $submissionid
        );

        $result = get_grid_data::execute($cmid);

        $this->assertSame('#3366cc', $result['slots'][0]['trackcolour']);
    }

    /**
     * A track with no configured colour surfaces trackcolour as null, not an
     * empty string or the string 'null'.
     */
    public function test_slot_trackcolour_null_when_track_has_no_colour(): void {
        global $DB;
        $this->resetAfterTest();

        [$course, $cmid, $confscheduler, , $submissionid] = $this->create_fixture();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $confsubmissionsid = $DB->get_field('confsubmissions_submission', 'confsubmissions', ['id' => $submissionid]);
        $trackid = submissions_api::add_track((int) $confsubmissionsid, 'Uncoloured Track');
        $DB->set_field('confsubmissions_submission', 'trackid', $trackid, ['id' => $submissionid]);

        $roomid = api::add_room((int) $confscheduler->id, 'Main Hall', null, null);
        api::add_slot(
            (int) $confscheduler->id,
            [$roomid],
            strtotime('2026-09-01 10:00:00'),
            strtotime('2026-09-01 10:30:00'),
            $submissionid
        );

        $result = get_grid_data::execute($cmid);

        $this->assertNull($result['slots'][0]['trackcolour']);
    }
```

- [ ] **Step 2: Run to verify failure**

```bash
cd /srv/lms/moodle/public
sudo -u www-data vendor/bin/phpunit --filter trackcolour mod/confscheduler/tests/external/get_grid_data_test.php
```
Expected: FAIL — `trackcolour` key doesn't exist in the returned array yet.

- [ ] **Step 3: Implement**

In `grid_data.php`, replace:

```php
        $tracksbyid = [];
        foreach (submissions_api::get_tracks($confsubmissionscm->id) as $track) {
            $tracksbyid[(int) $track->id] = $track->name;
        }
```

with:

```php
        $tracksbyid = [];
        foreach (submissions_api::get_tracks($confsubmissionscm->id) as $track) {
            $tracksbyid[(int) $track->id] = $track;
        }
```

Then update the two places that read `$tracksbyid[...]` as if it were a
plain name string. In the scheduled-slot branch, replace:

```php
            if ($slot->submissionid !== null) {
                $scheduledsubmissionids[] = (int) $slot->submissionid;
                $submission = submissions_api::get_submission((int) $slot->submissionid);
                if ($submission) {
                    $entry['title'] = format_string($submission->title, true, ['escape' => false]);
                    $entry['speakers'] = self::format_speakers((int) $submission->id);
                    $hastrack = !empty($submission->trackid) && isset($tracksbyid[(int) $submission->trackid]);
                    $entry['track'] = $hastrack
                        ? format_string($tracksbyid[(int) $submission->trackid], true, ['escape' => false])
                        : null;
                    $entry['trackid'] = $hastrack ? (int) $submission->trackid : null;
```

with:

```php
            if ($slot->submissionid !== null) {
                $scheduledsubmissionids[] = (int) $slot->submissionid;
                $submission = submissions_api::get_submission((int) $slot->submissionid);
                if ($submission) {
                    $entry['title'] = format_string($submission->title, true, ['escape' => false]);
                    $entry['speakers'] = self::format_speakers((int) $submission->id);
                    $hastrack = !empty($submission->trackid) && isset($tracksbyid[(int) $submission->trackid]);
                    $entry['track'] = $hastrack
                        ? format_string($tracksbyid[(int) $submission->trackid]->name, true, ['escape' => false])
                        : null;
                    $entry['trackid'] = $hastrack ? (int) $submission->trackid : null;
                    $entry['trackcolour'] = $hastrack ? ($tracksbyid[(int) $submission->trackid]->colour ?: null) : null;
```

Add `'trackcolour' => null,` to the `$entry` array's initial declaration
(alongside the existing `'trackid' => null,` a few lines above this block)
so it's always present even for a column-spanning block with no
submission.

In the unscheduled-submissions branch, replace:

```php
            $hastrack = !empty($submission->trackid) && isset($tracksbyid[(int) $submission->trackid]);
            $submissiontypeid = !empty($submission->submissiontypeid) ? (int) $submission->submissiontypeid : null;
            $unscheduledout[] = [
                'submissionid'    => (int) $submission->id,
                'title'           => format_string($submission->title, true, ['escape' => false]),
                'speakers'        => self::format_speakers((int) $submission->id),
                'track'           => $hastrack
                    ? format_string($tracksbyid[(int) $submission->trackid], true, ['escape' => false])
                    : null,
                'trackid'         => $hastrack ? (int) $submission->trackid : null,
```

with:

```php
            $hastrack = !empty($submission->trackid) && isset($tracksbyid[(int) $submission->trackid]);
            $submissiontypeid = !empty($submission->submissiontypeid) ? (int) $submission->submissiontypeid : null;
            $unscheduledout[] = [
                'submissionid'    => (int) $submission->id,
                'title'           => format_string($submission->title, true, ['escape' => false]),
                'speakers'        => self::format_speakers((int) $submission->id),
                'track'           => $hastrack
                    ? format_string($tracksbyid[(int) $submission->trackid]->name, true, ['escape' => false])
                    : null,
                'trackid'         => $hastrack ? (int) $submission->trackid : null,
                'trackcolour'     => $hastrack ? ($tracksbyid[(int) $submission->trackid]->colour ?: null) : null,
```

Also update the class docblock's `@return` shape comment to mention the new
`trackcolour` key, next to the existing `track`/`trackid` mentions.

- [ ] **Step 4: Run the tests to verify they pass**

```bash
cd /srv/lms/moodle/public
sudo -u www-data vendor/bin/phpunit mod/confscheduler/tests/external/get_grid_data_test.php
```
Expected: all tests in the file passing.

- [ ] **Step 5: phpcs**

```bash
vendor/bin/phpcs --standard=moodle /vagrant/moodle-dev/moodle-mod_confscheduler/classes/local/grid_data.php
```
Expected: no errors.

- [ ] **Step 6: Commit**

```bash
cd /vagrant/moodle-dev/moodle-mod_confscheduler
git add classes/local/grid_data.php tests/external/get_grid_data_test.php
git commit -m "Surface a scheduled/unscheduled submission's track colour in grid_data

\$tracksbyid previously kept only the track name, discarding colour --
this is what made every track pill render in the plugin's fixed
default blue regardless of the organiser-configured track colour."
```

---

### Task 9: `mod_confscheduler` — apply track colour in `buildTrackPill()`

**Files:**
- Modify: `/vagrant/moodle-dev/moodle-mod_confscheduler/amd/src/scheduler_grid.js`
- Modify: `/vagrant/moodle-dev/moodle-mod_confscheduler/amd/src/scheduler_display.js`
- Modify: `/vagrant/moodle-dev/moodle-mod_confscheduler/styles.css`

**Interfaces:**
- Consumes: `slot.trackcolour`/`unscheduled.trackcolour` (nullable hex
  string) from the `mod_confscheduler_get_grid_data` payload (Task 8);
  `ColourUtils.contrastTextColour(hex)` (already exists in
  `amd/src/colour_utils.js`, already imported by both files).
- Produces: nothing other tasks depend on.

- [ ] **Step 1: `scheduler_grid.js`**

Replace the existing `buildTrackPill()` (currently at lines 164-183):

```js
const buildTrackPill = (programUrl, trackid, trackname, filterbytrackstr) => {
    if (programUrl && trackid) {
        const pill = document.createElement('a');
        pill.className = 'mod_confscheduler-track-pill';
        pill.href = `${programUrl}&trackid=${trackid}`;
        pill.textContent = trackname;
        // Descriptive accessible name beyond the bare track name (WCAG "link purpose"):
        // the visible text alone doesn't convey that activating it navigates to a
        // filtered list on a different activity.
        if (filterbytrackstr) {
            pill.setAttribute('aria-label', filterbytrackstr.replace('{$a}', trackname));
        }
        return pill;
    }

    const pill = document.createElement('span');
    pill.className = 'mod_confscheduler-track-pill';
    pill.textContent = trackname;
    return pill;
};
```

with (adds a `trackcolour` parameter and applies it identically to both the
link and plain-span branches, right before each `return`, mirroring
`renderBlock()`'s existing `ColourUtils.contrastTextColour()` usage
elsewhere in this same file for room/span-block colour):

```js
const buildTrackPill = (programUrl, trackid, trackname, trackcolour, filterbytrackstr) => {
    const applyColour = (pill) => {
        if (trackcolour) {
            pill.style.backgroundColor = trackcolour;
            const textColour = ColourUtils.contrastTextColour(trackcolour);
            if (textColour) {
                pill.style.color = textColour;
            }
        }
    };

    if (programUrl && trackid) {
        const pill = document.createElement('a');
        pill.className = 'mod_confscheduler-track-pill';
        pill.href = `${programUrl}&trackid=${trackid}`;
        pill.textContent = trackname;
        // Descriptive accessible name beyond the bare track name (WCAG "link purpose"):
        // the visible text alone doesn't convey that activating it navigates to a
        // filtered list on a different activity.
        if (filterbytrackstr) {
            pill.setAttribute('aria-label', filterbytrackstr.replace('{$a}', trackname));
        }
        applyColour(pill);
        return pill;
    }

    const pill = document.createElement('span');
    pill.className = 'mod_confscheduler-track-pill';
    pill.textContent = trackname;
    applyColour(pill);
    return pill;
};
```

Update both existing call sites. Line 921 (scheduled-block footer):

```js
            footer.appendChild(buildTrackPill(state.programUrl, slot.trackid, slot.track, state.strings.filterbytrack));
```

becomes:

```js
            footer.appendChild(
                buildTrackPill(state.programUrl, slot.trackid, slot.track, slot.trackcolour, state.strings.filterbytrack)
            );
```

Line 1028 (unscheduled-panel card):

```js
                    buildTrackPill(state.programUrl, item.trackid, item.track, state.strings.filterbytrack)
```

becomes:

```js
                    buildTrackPill(state.programUrl, item.trackid, item.track, item.trackcolour, state.strings.filterbytrack)
```

- [ ] **Step 2: `scheduler_display.js`**

Same change, mirrored in this file's own `buildTrackPill()` copy (currently
lines 217-236, per its own docblock deliberately duplicated rather than
shared with `scheduler_grid.js`). Replace:

```js
const buildTrackPill = (programUrl, trackid, trackname, filterbytrackstr) => {
    if (programUrl && trackid) {
        const pill = document.createElement('a');
        pill.className = 'mod_confscheduler-track-pill';
        pill.href = `${programUrl}&trackid=${trackid}`;
        pill.textContent = trackname;
        // Descriptive accessible name beyond the bare track name (WCAG "link purpose"):
        // the visible text alone doesn't convey that activating it navigates to a
        // filtered list on a different activity.
        if (filterbytrackstr) {
            pill.setAttribute('aria-label', filterbytrackstr.replace('{$a}', trackname));
        }
        return pill;
    }

    const pill = document.createElement('span');
    pill.className = 'mod_confscheduler-track-pill';
    pill.textContent = trackname;
    return pill;
};
```

with the identical replacement already written out in Step 1 above (same
`applyColour` helper, same both-branches treatment).

Update its one call site (currently line 338):

```js
    const footer = document.createElement('div');
    footer.className = 'mod_confscheduler-block-footer';
    if (slot.track) {
        footer.appendChild(buildTrackPill(state.programUrl, slot.trackid, slot.track, state.strings.filterbytrack));
    }
```

to:

```js
    const footer = document.createElement('div');
    footer.className = 'mod_confscheduler-block-footer';
    if (slot.track) {
        footer.appendChild(
            buildTrackPill(state.programUrl, slot.trackid, slot.track, slot.trackcolour, state.strings.filterbytrack)
        );
    }
```

- [ ] **Step 3: Rebuild AMD from scratch and diff-check**

```bash
cd /vagrant/moodle-dev/moodle-mod_confscheduler
git status amd/build/
grunt amd --force
git diff --stat amd/build/
```
Expected: `scheduler_grid.min.js`/`scheduler_display.min.js` (and their
`.map` files) show as changed; re-run `grunt amd --force` a second time and
confirm `git diff --stat amd/build/` shows NO further changes (stable
rebuild), per this project's standing rule for any AMD build artifact
change.

- [ ] **Step 4: Deploy and verify live**

```bash
rsync -av --delete /vagrant/moodle-dev/moodle-mod_confscheduler/ /srv/lms/moodle/public/mod/confscheduler/ --exclude .git
cd /srv/lms/moodle/public && sudo -u www-data php admin/cli/upgrade.php --non-interactive
```

Via Playwright, open the edit-mode grid for an instance with a coloured
track scheduled, confirm the track pill on the block's footer (and in the
unscheduled-panel card) now shows the track's configured colour (inspect
DOM `background-color`, not a screenshot); confirm a track with no colour
still shows the existing default blue; repeat in read-only Display mode.

- [ ] **Step 5: eslint/phpcs (JS lint via this project's usual grunt/eslint step) + full PHPUnit run**

```bash
cd /srv/lms/moodle/public
sudo -u www-data vendor/bin/phpunit --testsuite mod_confscheduler_testsuite
```
Expected: all passing (this task made no PHP change, so this is a pure
regression check).

- [ ] **Step 6: Commit**

```bash
cd /vagrant/moodle-dev/moodle-mod_confscheduler
git add amd/src/scheduler_grid.js amd/src/scheduler_display.js amd/build/ styles.css
git commit -m "Apply a track's configured colour to its pill badge

buildTrackPill() (both the edit-mode grid and read-only Display mode's
own copy) now takes the track's colour and applies it via the same
inline-background-color + ColourUtils.contrastTextColour() pattern
already used for room headers and span blocks -- falls back to the
existing fixed blue default when a track has no colour configured."
```

---

### Task 10: Version bump, moodle-reviewer pass, and docs

**Files:**
- Modify: `/vagrant/moodle-dev/moodle-mod_confprogram/version.php`, `db/upgrade.php`, `changelog.md`, `README.md`
- Modify: `/vagrant/moodle-dev/moodle-mod_confscheduler/version.php`, `db/upgrade.php`, `changelog.md`, `README.md`
- Modify: `/vagrant/moodle-dev/moodle-conference-tools/RELATIONS.md`, `SUMMARY.md`, `TASKLIST.md`
- Modify: `/vagrant/moodle-dev/moodle-conference-tools/mod_confprogram.en.md`, `.ja.md`, `mod_confscheduler.en.md`, `.ja.md` (if these fixes are user-visible enough to need a manual mention — the button-label and crash fixes are bugfixes, not new documented behaviour, so likely no manual change needed there; the track-pill visual change may warrant one sentence in each plugin's manual)

**Interfaces:**
- Consumes: everything from Tasks 1-9 (this is the wrap-up task).
- Produces: nothing — this is the final task in this plan.

- [ ] **Step 1: Bump `mod_confprogram`'s version**

Use the `moodle-dev:moodle-bump-version` skill on
`/vagrant/moodle-dev/moodle-mod_confprogram`. No schema change in this
round, so this is a version-number-only bump (no new `db/upgrade.php` step
needed) — confirm the skill agrees given no `install.xml` change.

- [ ] **Step 2: Bump `mod_confscheduler`'s version**

Same, on `/vagrant/moodle-dev/moodle-mod_confscheduler`.

- [ ] **Step 3: Run a `moodle-reviewer` pass on both plugins, scoped to this round's commits**

Dispatch the `moodle-reviewer` agent twice (once per repo), each scoped to
the commit range created in Tasks 1-9 for that repo (not the whole plugin
history) — matching this project's established "one reviewer agent per
plugin repo, scoped to the session's commit range" practice (see this
project's own memory notes on this). Fix every real finding before
proceeding, re-running the relevant task's own verification steps for
whatever it touches.

- [ ] **Step 4: Update each plugin's own `changelog.md`/`README.md`**

Add an entry to each plugin's `changelog.md` (and `README.md` if it
documents the specific behaviour changed, e.g. `mod_confscheduler`'s
existing track-pill/print description) summarizing: the three bug fixes,
their root causes, and the track-pill colour change.

- [ ] **Step 5: Update this coordination repo's docs**

- `RELATIONS.md`: no cross-plugin contract actually changed shape (grid_data's
  new `trackcolour` field is additive, not a signature change) — a short
  note is still worth adding near the existing `get_tracks()`/track-pill
  discussion for future readers.
- `SUMMARY.md`: a new dated entry describing this round (mirroring the style
  of every prior "Revision round" entry).
- `TASKLIST.md`: a new "Revision round" section listing these three fixes
  with their status checked off, matching every prior round's format.

- [ ] **Step 6: Commit the coordination-repo doc updates**

```bash
cd /vagrant/moodle-dev/moodle-conference-tools
git add RELATIONS.md SUMMARY.md TASKLIST.md mod_confprogram.en.md mod_confprogram.ja.md mod_confscheduler.en.md mod_confscheduler.ja.md
git commit -m "Document the confprogram bug-fix round (numeric buttons, review crash, track pill colour)"
```

- [ ] **Step 7: Commit each plugin's own version/changelog/README update**

```bash
cd /vagrant/moodle-dev/moodle-mod_confprogram
git add version.php changelog.md README.md
git commit -m "Bump version and document the numeric-button/review-crash/track-pill fixes"

cd /vagrant/moodle-dev/moodle-mod_confscheduler
git add version.php changelog.md README.md
git commit -m "Bump version and document the track-pill colour fix"
```
