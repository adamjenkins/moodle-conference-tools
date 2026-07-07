# mod_confsubmissions editany Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an editingteacher (or manager) edit any submission in `mod_confsubmissions` — regardless of ownership or whether the call is open — with an entry point from that plugin's own submissions list and from `mod_confprogram`'s Decision report.

**Architecture:** A new `mod/confsubmissions:editany` capability, checked through one new testable helper (`api::can_edit_submission()`); `edit.php`'s existing ownership/call-window gates are relaxed to consult it; two new UI entry points (one per plugin) link into the now-relaxed `edit.php`, one of them via a new `returnurl` param so the organiser lands back where they started.

**Tech Stack:** Moodle 5.2 plugin PHP (moodleform, capabilities API), PHPUnit.

## Global Constraints

- Moodle 5.2 (`2026042000`) or later — both plugins' existing `version.php` floor, unchanged.
- No new AMD/JS in either plugin for this feature — every new control is a plain link/redirect.
- `PARAM_LOCALURL` for the new `returnurl` param — never accept/redirect to an unvalidated string (open-redirect prevention).
- No new capability in `mod_confprogram` — it only reads `mod_confsubmissions`'s new capability, in that plugin's own module context.
- Follow this project's established IDOR-prevention convention: every submission lookup re-verifies `$submission->confsubmissions == $confsubmissions->id` before use (already true of every touched file; do not weaken it).
- phpcs/moodlecheck clean; EN+JA lang parity for every new string; `changelog.md`/`README.md` updated for both plugins; `moodle-reviewer` pass before considering either plugin's changes complete.

---

### Task 1: `mod/confsubmissions:editany` capability + `api::can_edit_submission()`

**Files:**
- Modify: `/vagrant/moodle-dev/moodle-mod_confsubmissions/db/access.php`
- Modify: `/vagrant/moodle-dev/moodle-mod_confsubmissions/classes/api.php`
- Modify: `/vagrant/moodle-dev/moodle-mod_confsubmissions/lang/en/confsubmissions.php`
- Modify: `/vagrant/moodle-dev/moodle-mod_confsubmissions/lang/ja/confsubmissions.php`
- Test: `/vagrant/moodle-dev/moodle-mod_confsubmissions/tests/api_test.php`

**Interfaces:**
- Produces: `\mod_confsubmissions\api::can_edit_submission(\stdClass $submission, \context $context, ?int $userid = null): bool` — `true` if `$userid` (defaults to `$USER->id`) owns `$submission` and holds `mod/confsubmissions:submit` in `$context`, **or** holds `mod/confsubmissions:editany` in `$context` regardless of ownership. Consumed by Task 2's `edit.php`.
- Produces: capability string `mod/confsubmissions:editany`, granted by default to `editingteacher` + `manager` (not plain `teacher`).

- [ ] **Step 1: Add the capability**

In `db/access.php`, insert a new entry immediately after the existing `mod/confsubmissions:deleteany` block (before the closing `];`):

```php
    // New capability (user request, 2026-07-07): "editing teachers should also be
    // able to edit any submission... from the list view". Deliberately distinct from
    // deleteany (manager-only per 2026-07-05 feedback): editing is judged lower-risk
    // than permanent deletion, so editingteacher is included here.
    'mod/confsubmissions:editany' => [
        'riskbitmask'  => RISK_DATALOSS,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],
```

- [ ] **Step 2: Add the lang strings**

In `lang/en/confsubmissions.php`, insert this line right after the existing `$string['confsubmissions:deleteany'] = ...;` line (alphabetically before `confsubmissions:manageform`):

```php
$string['confsubmissions:editany'] = 'Edit any submission';
```

Also insert this line right after the existing `$string['editfield'] = 'Edit field';` line (alphabetically before `editsubmission`):

```php
$string['editinganothersubmission'] = 'You are editing this submission on behalf of {$a}.';
```

In `lang/ja/confsubmissions.php`, insert the matching lines in the same two positions (immediately after that file's own `confsubmissions:deleteany` and `editfield` lines respectively):

```php
$string['confsubmissions:editany'] = '任意の応募を編集する';
```

```php
$string['editinganothersubmission'] = 'この応募は {$a} の代理で編集しています。';
```

- [ ] **Step 3: Write the failing test**

Add this test method to `tests/api_test.php`, inside the existing `api_test` class (anywhere among the other test methods — e.g. right after `test_track_crud()`):

```php
    /**
     * can_edit_submission() allows the owner (with submit) or any editany holder,
     * and refuses everyone else -- including a plain (non-editing) teacher, since
     * editany is deliberately not granted to that archetype (user request, 2026-07-07).
     */
    public function test_can_edit_submission_owner_or_editany(): void {
        $this->resetAfterTest();
        global $DB;

        $confsubmissions = $this->create_instance();
        $cm = get_coursemodule_from_instance('confsubmissions', $confsubmissions->id);
        $context = \context_module::instance($cm->id);

        $owner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($owner->id, $confsubmissions->course, 'student');

        $editingteacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($editingteacher->id, $confsubmissions->course, 'editingteacher');

        $plainteacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($plainteacher->id, $confsubmissions->course, 'teacher');

        $otherstudent = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($otherstudent->id, $confsubmissions->course, 'student');

        $submissionid = $DB->insert_record('confsubmissions_submission', (object) [
            'confsubmissions' => $confsubmissions->id,
            'userid'          => $owner->id,
            'title'           => 'Test submission',
            'abstract'        => 'Abstract text',
            'status'          => 'submitted',
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
        $submission = api::get_submission($submissionid);

        $this->assertTrue(api::can_edit_submission($submission, $context, (int) $owner->id));
        $this->assertTrue(api::can_edit_submission($submission, $context, (int) $editingteacher->id));
        $this->assertFalse(api::can_edit_submission($submission, $context, (int) $plainteacher->id));
        $this->assertFalse(api::can_edit_submission($submission, $context, (int) $otherstudent->id));
    }
```

- [ ] **Step 4: Deploy and run the test to verify it fails**

```bash
rsync -a --delete /vagrant/moodle-dev/moodle-mod_confsubmissions/ /srv/lms/moodle/public/mod/confsubmissions/ --exclude=.git
cd /srv/lms/moodle && sudo -u www-data vendor/bin/phpunit --filter test_can_edit_submission_owner_or_editany
```
Expected: FAIL with an error that `can_edit_submission` is not a known method on `api`.

- [ ] **Step 5: Implement `can_edit_submission()`**

In `classes/api.php`, add this method immediately after the existing `get_submission()` method:

```php
    /**
     * Whether $userid may edit $submission via edit.php: either they own it and
     * hold mod/confsubmissions:submit, or they hold mod/confsubmissions:editany
     * regardless of ownership (user request, 2026-07-07 -- "editing teachers should
     * also be able to edit any submission... especially the selected track").
     *
     * @param \stdClass $submission The confsubmissions_submission record
     * @param \context $context The confsubmissions module context
     * @param int|null $userid The user to check; defaults to the current $USER
     * @return bool
     */
    public static function can_edit_submission(\stdClass $submission, \context $context, ?int $userid = null): bool {
        global $USER;

        $userid = $userid ?? (int) $USER->id;

        if ((int) $submission->userid === $userid) {
            return has_capability('mod/confsubmissions:submit', $context, $userid);
        }

        return has_capability('mod/confsubmissions:editany', $context, $userid);
    }
```

- [ ] **Step 6: Deploy and run the test to verify it passes**

```bash
rsync -a --delete /vagrant/moodle-dev/moodle-mod_confsubmissions/ /srv/lms/moodle/public/mod/confsubmissions/ --exclude=.git
cd /srv/lms/moodle && sudo -u www-data vendor/bin/phpunit --filter test_can_edit_submission_owner_or_editany
```
Expected: PASS

- [ ] **Step 7: Run the whole confsubmissions suite**

Run: `cd /srv/lms/moodle && sudo -u www-data vendor/bin/phpunit --testsuite mod_confsubmissions_testsuite`
Expected: all tests pass (no regressions from the new capability entry).

- [ ] **Step 8: Commit**

```bash
cd /vagrant/moodle-dev/moodle-mod_confsubmissions
git add db/access.php classes/api.php lang/en/confsubmissions.php lang/ja/confsubmissions.php tests/api_test.php
git commit -m "Add mod/confsubmissions:editany capability and can_edit_submission()"
```

---

### Task 2: Relax `edit.php`'s ownership/call-window gates; add `returnurl` and the on-behalf-of banner

**Files:**
- Modify: `/vagrant/moodle-dev/moodle-mod_confsubmissions/edit.php`

**Interfaces:**
- Consumes: `\mod_confsubmissions\api::can_edit_submission()` from Task 1.
- Produces: `edit.php` now accepts an optional `?returnurl=` query param (`PARAM_LOCALURL`) that both the cancel and save redirects target instead of `view.php`, when supplied.

- [ ] **Step 1: Replace the ownership check with `can_edit_submission()`**

Find this block (currently around line 51-63):

```php
if ($submissionid) {
    $submission = api::get_submission($submissionid);
    if (!$submission || $submission->confsubmissions != $confsubmissions->id) {
        throw new \moodle_exception('invalidrecord', 'error', '', 'confsubmissions_submission');
    }
    if ((int) $submission->userid !== (int) $USER->id) {
        throw new \moodle_exception('error:notowner', 'mod_confsubmissions');
    }
    // Ownership alone isn't enough: if the submit capability has since been revoked
    // (e.g. role change), the owner should no longer be able to edit either.
    require_capability('mod/confsubmissions:submit', $context);
    $speakers = api::get_speakers($submission->id);
} else {
    require_capability('mod/confsubmissions:submit', $context);
}
```

Replace it with:

```php
$iseditany = false;

if ($submissionid) {
    $submission = api::get_submission($submissionid);
    if (!$submission || $submission->confsubmissions != $confsubmissions->id) {
        throw new \moodle_exception('invalidrecord', 'error', '', 'confsubmissions_submission');
    }
    $isowner = (int) $submission->userid === (int) $USER->id;
    if (!api::can_edit_submission($submission, $context)) {
        throw new \moodle_exception('error:notowner', 'mod_confsubmissions');
    }
    // can_edit_submission() already folds in the owner's 'submit' check (an owner
    // whose 'submit' capability has since been revoked returns false from it, same
    // as before -- just via the generic 'error:notowner' message now instead of
    // require_capability()'s own exception, since that separate call is redundant
    // once can_edit_submission() above has already verified it).
    $iseditany = !$isowner;
    $speakers = api::get_speakers($submission->id);
} else {
    require_capability('mod/confsubmissions:submit', $context);
}
```

- [ ] **Step 2: Bypass the call-window check for editany**

Find:

```php
if (!$callisopen) {
    echo $OUTPUT->notification(get_string('callnotopen', 'mod_confsubmissions'), 'info');
    echo $OUTPUT->footer();
    exit;
}
```

Replace with:

```php
if (!$callisopen && !$iseditany) {
    echo $OUTPUT->notification(get_string('callnotopen', 'mod_confsubmissions'), 'info');
    echo $OUTPUT->footer();
    exit;
}
```

- [ ] **Step 3: Add `returnurl` support**

Find:

```php
$pageurl = new moodle_url('/mod/confsubmissions/edit.php', ['id' => $cm->id]);
if ($submissionid) {
    $pageurl->param('submissionid', $submissionid);
}
$viewurl = new moodle_url('/mod/confsubmissions/view.php', ['id' => $cm->id]);
```

Replace with:

```php
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

$pageurl = new moodle_url('/mod/confsubmissions/edit.php', ['id' => $cm->id]);
if ($submissionid) {
    $pageurl->param('submissionid', $submissionid);
}
if ($returnurl !== '') {
    $pageurl->param('returnurl', $returnurl);
}
$viewurl = $returnurl !== '' ? new moodle_url($returnurl) : new moodle_url('/mod/confsubmissions/view.php', ['id' => $cm->id]);
```

(Both existing `redirect($viewurl)` and `redirect($viewurl, get_string('submissionsaved', ...), ...)` call sites need no further changes — they already redirect to `$viewurl`, which now resolves correctly either way.)

- [ ] **Step 4: Add the on-behalf-of banner**

Find:

```php
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($confsubmissions->name), 2);
echo $OUTPUT->heading(
    $submission ? get_string('editsubmission', 'mod_confsubmissions') : get_string('newsubmission', 'mod_confsubmissions'),
    3
);
```

Replace with:

```php
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($confsubmissions->name), 2);
echo $OUTPUT->heading(
    $submission ? get_string('editsubmission', 'mod_confsubmissions') : get_string('newsubmission', 'mod_confsubmissions'),
    3
);

if ($iseditany) {
    $owneruser = \core_user::get_user($submission->userid);
    echo $OUTPUT->notification(
        get_string('editinganothersubmission', 'mod_confsubmissions', $owneruser ? fullname($owneruser) : '-'),
        'info'
    );
}
```

- [ ] **Step 5: Deploy and smoke-test live**

This is a page script with `require_login()`/`redirect()` control flow, following this project's established convention that such entry points are verified live rather than via PHPUnit (business logic itself, e.g. `can_edit_submission()`, is already unit-tested in Task 1).

```bash
rsync -a --delete /vagrant/moodle-dev/moodle-mod_confsubmissions/ /srv/lms/moodle/public/mod/confsubmissions/ --exclude=.git
diff -rq /vagrant/moodle-dev/moodle-mod_confsubmissions --exclude=.git /srv/lms/moodle/public/mod/confsubmissions
```

Then, as an `editingteacher` in a course with a `confsubmissions` instance containing another user's submission, open `edit.php?id=<cmid>&submissionid=<id>` in a browser: confirm the "editing on behalf of" banner appears, the form is editable, and saving works and redirects correctly. Then confirm a plain (non-editing) `teacher` gets the `error:notowner` exception on the same URL.

- [ ] **Step 6: Commit**

```bash
cd /vagrant/moodle-dev/moodle-mod_confsubmissions
git add edit.php
git commit -m "Let editany holders edit any submission, bypassing ownership and the call window"
```

---

### Task 3: "Edit" link in `mod_confsubmissions/view.php`'s "All submissions" table

**Files:**
- Modify: `/vagrant/moodle-dev/moodle-mod_confsubmissions/view.php`

**Interfaces:**
- Consumes: capability `mod/confsubmissions:editany` from Task 1; `edit.php` from Task 2 (no `returnurl` needed here, since the default target — this same `view.php` — is already correct).

- [ ] **Step 1: Compute the new capability flag**

Find:

```php
$candeleteany = has_capability('mod/confsubmissions:deleteany', $context);
```

Replace with:

```php
$candeleteany = has_capability('mod/confsubmissions:deleteany', $context);
$caneditany = has_capability('mod/confsubmissions:editany', $context);
```

- [ ] **Step 2: Add the Edit column to the "All submissions" table**

Find (in the "All submissions" table block):

```php
        $table->head = [
            get_string('title', 'mod_confsubmissions'),
            get_string('primaryspeaker', 'mod_confsubmissions'),
            get_string('track', 'mod_confsubmissions'),
            get_string('status', 'mod_confsubmissions'),
            get_string('submitted', 'mod_confsubmissions'),
        ];
        if ($candeleteany) {
            $table->head[] = '';
        }
```

Replace with:

```php
        $table->head = [
            get_string('title', 'mod_confsubmissions'),
            get_string('primaryspeaker', 'mod_confsubmissions'),
            get_string('track', 'mod_confsubmissions'),
            get_string('status', 'mod_confsubmissions'),
            get_string('submitted', 'mod_confsubmissions'),
        ];
        if ($caneditany) {
            $table->head[] = '';
        }
        if ($candeleteany) {
            $table->head[] = '';
        }
```

Find:

```php
            if ($candeleteany) {
                $deleteurl = new moodle_url($pageurl, ['delete' => $submission->id, 'sesskey' => sesskey()]);
                $row[] = html_writer::link($deleteurl, get_string('delete'));
            }

            $table->data[] = $row;
```

Replace with:

```php
            if ($caneditany) {
                $editurl = new moodle_url('/mod/confsubmissions/edit.php', [
                    'id'           => $cm->id,
                    'submissionid' => $submission->id,
                ]);
                $row[] = html_writer::link($editurl, get_string('edit'));
            }
            if ($candeleteany) {
                $deleteurl = new moodle_url($pageurl, ['delete' => $submission->id, 'sesskey' => sesskey()]);
                $row[] = html_writer::link($deleteurl, get_string('delete'));
            }

            $table->data[] = $row;
```

- [ ] **Step 3: Deploy and smoke-test live**

```bash
rsync -a --delete /vagrant/moodle-dev/moodle-mod_confsubmissions/ /srv/lms/moodle/public/mod/confsubmissions/ --exclude=.git
diff -rq /vagrant/moodle-dev/moodle-mod_confsubmissions --exclude=.git /srv/lms/moodle/public/mod/confsubmissions
```

As an `editingteacher`, load `view.php?id=<cmid>` and confirm the "All submissions" table shows an Edit link on every row (and a plain `teacher` sees neither Edit nor Delete). As a `manager`, confirm both Edit and Delete links appear.

- [ ] **Step 4: Commit**

```bash
cd /vagrant/moodle-dev/moodle-mod_confsubmissions
git add view.php
git commit -m "Add an Edit link to the All submissions table for editany holders"
```

---

### Task 4: `mod_confsubmissions` version bump, changelog, README

**Files:**
- Modify: `/vagrant/moodle-dev/moodle-mod_confsubmissions/version.php`
- Modify: `/vagrant/moodle-dev/moodle-mod_confsubmissions/changelog.md`
- Modify: `/vagrant/moodle-dev/moodle-mod_confsubmissions/README.md`

**Interfaces:**
- Consumes: nothing new; this task only documents Tasks 1-3.

- [ ] **Step 1: Bump the version**

In `version.php`, find:

```php
$plugin->version   = 2026070602; // The current module version (Date: YYYYMMDDXX).
```

Replace with:

```php
$plugin->version   = 2026070701; // The current module version (Date: YYYYMMDDXX).
```

- [ ] **Step 2: Add the changelog entry**

In `changelog.md`, insert this new bullet at the very top of the `## Unreleased` section (immediately before the existing first bullet):

```markdown
- User request (2026-07-07): "Editing teachers should also be able to edit
  any submission (especially the selected track) from the list view."
  Added a new `mod/confsubmissions:editany` capability (granted to
  `editingteacher` and `manager`, deliberately not plain `teacher` --
  distinct from the manager-only `deleteany`). `edit.php` now allows an
  `editany` holder to open and save any submission on the instance,
  regardless of ownership, and bypasses the open-call-window restriction
  for them (a submitter without `editany` is still bound by both). A new
  `returnurl` param (validated `PARAM_LOCALURL`) lets a caller send the
  editor back to a specific page after saving/cancelling instead of this
  plugin's own `view.php`. `view.php`'s "All submissions" table gains an
  Edit link for `editany` holders, alongside the existing `deleteany`-gated
  Delete link. See `mod_confprogram`'s own changelog for the matching
  Decision-report entry point into this same `edit.php`.
```

- [ ] **Step 3: Update the README**

In `README.md`, under the `## Architecture notes` section, add a new bullet (at the end of that section's list):

```markdown
- **An `editingteacher`/`manager` holding the new `mod/confsubmissions:editany`
  capability can edit any submission on the instance** via `edit.php`,
  regardless of ownership and regardless of whether the call is currently
  open -- unlike the submitter's own edit access, which still requires both.
  `mod_confprogram`'s Decision report links into this same `edit.php` for
  exactly this purpose (see that plugin's own README).
```

- [ ] **Step 4: Commit**

```bash
cd /vagrant/moodle-dev/moodle-mod_confsubmissions
git add version.php changelog.md README.md
git commit -m "Bump version and document the editany capability"
```

---

### Task 5: "Edit" link on `mod_confprogram`'s Decision report

**Files:**
- Modify: `/vagrant/moodle-dev/moodle-mod_confprogram/decisions.php`
- Modify: `/vagrant/moodle-dev/moodle-mod_confprogram/lang/en/confprogram.php`
- Modify: `/vagrant/moodle-dev/moodle-mod_confprogram/lang/ja/confprogram.php`

**Interfaces:**
- Consumes: capability `mod/confsubmissions:editany` (from Task 1) and `edit.php`'s `returnurl` param (from Task 2), both in the sibling `mod_confsubmissions` plugin.

- [ ] **Step 1: Add the lang string**

In `lang/en/confprogram.php`, insert this line immediately after the existing `$string['editreview'] = 'Edit review';` line (alphabetically before `error:invalidconfsubmissionscmid`):

```php
$string['editsubmissionlink'] = 'Edit {$a}';
```

In `lang/ja/confprogram.php`, insert the matching line in the same position (immediately after that file's own `editreview` line):

```php
$string['editsubmissionlink'] = '{$a} を編集';
```

- [ ] **Step 2: Compute the capability and return URL**

Find (near the top of `decisions.php`, right after `$confsubmissionscm` is fetched):

```php
$confsubmissionscm = get_coursemodule_from_id('confsubmissions', $confprogram->confsubmissionscmid, 0, false, MUST_EXIST);
```

Replace with:

```php
$confsubmissionscm = get_coursemodule_from_id('confsubmissions', $confprogram->confsubmissionscmid, 0, false, MUST_EXIST);
$confsubmissionscontext = context_module::instance($confsubmissionscm->id);
$caneditsubmissions = has_capability('mod/confsubmissions:editany', $confsubmissionscontext);

$returnurlparams = ['id' => $cm->id];
if ($filtertrack !== '') {
    $returnurlparams['trackid'] = $filtertrack;
}
if ($filterstatus !== '') {
    $returnurlparams['decisionstatus'] = $filterstatus;
}
$decisionsreturnurl = new moodle_url('/mod/confprogram/decisions.php', $returnurlparams);
```

- [ ] **Step 3: Add the Edit link to the Title cell**

Find:

```php
    $table->data[] = [
        html_writer::checkbox('submissionids[]', $submission->id, false, '', [
            'class'      => 'mod_confprogram-row-checkbox',
            'aria-label' => get_string('selectsubmission', 'mod_confprogram', format_string($submission->title)),
        ]),
        format_string($submission->title),
        field_formatter::get_track_pill_html($submission),
```

Replace with:

```php
    $titlecell = format_string($submission->title);
    if ($caneditsubmissions) {
        $editurl = new moodle_url('/mod/confsubmissions/edit.php', [
            'id'           => $confsubmissionscm->id,
            'submissionid' => $submission->id,
            'returnurl'    => $decisionsreturnurl->out(false),
        ]);
        $titlecell .= ' ' . html_writer::link($editurl, get_string('edit'), [
            'class'      => 'ml-2',
            'aria-label' => get_string('editsubmissionlink', 'mod_confprogram', format_string($submission->title)),
        ]);
    }

    $table->data[] = [
        html_writer::checkbox('submissionids[]', $submission->id, false, '', [
            'class'      => 'mod_confprogram-row-checkbox',
            'aria-label' => get_string('selectsubmission', 'mod_confprogram', format_string($submission->title)),
        ]),
        $titlecell,
        field_formatter::get_track_pill_html($submission),
```

- [ ] **Step 4: Deploy and smoke-test live**

```bash
rsync -a --delete /vagrant/moodle-dev/moodle-mod_confprogram/ /srv/lms/moodle/public/mod/confprogram/ --exclude=.git
diff -rq /vagrant/moodle-dev/moodle-mod_confprogram --exclude=.git /srv/lms/moodle/public/mod/confprogram
```

As an `editingteacher` (who therefore holds `mod/confsubmissions:editany` per Task 1), load `decisions.php?id=<cmid>`, confirm every row's title shows an "Edit" link, click one, edit the track, save, and confirm you land back on the Decision report with any active track/decision-status filter still applied. Then remove `editingteacher` from the relevant course role (or test as a `manager` viewing this page — if `manager` doesn't hold `mod/confprogram:decide` in your test site's role config, add a user who holds `mod/confprogram:decide` but not `mod/confsubmissions:editany`, e.g. by unassigning `editingteacher`) and confirm the Edit link disappears from every row.

- [ ] **Step 5: Run the confprogram test suite (regression check)**

```bash
cd /srv/lms/moodle && sudo -u www-data vendor/bin/phpunit --testsuite mod_confprogram_testsuite
```
Expected: all existing tests still pass (this task adds no new PHP classes/methods, only page-script markup, so no new PHPUnit coverage is expected here).

- [ ] **Step 6: Commit**

```bash
cd /vagrant/moodle-dev/moodle-mod_confprogram
git add decisions.php lang/en/confprogram.php lang/ja/confprogram.php
git commit -m "Add an Edit link into mod_confsubmissions from the Decision report"
```

---

### Task 6: `mod_confprogram` version bump, changelog, README

**Files:**
- Modify: `/vagrant/moodle-dev/moodle-mod_confprogram/version.php`
- Modify: `/vagrant/moodle-dev/moodle-mod_confprogram/changelog.md`
- Modify: `/vagrant/moodle-dev/moodle-mod_confprogram/README.md`

**Interfaces:**
- Consumes: nothing new; this task only documents Task 5.

- [ ] **Step 1: Bump the version**

In `version.php`, find:

```php
$plugin->version   = 2026070604; // The current module version (Date: YYYYMMDDXX).
```

Replace with:

```php
$plugin->version   = 2026070701; // The current module version (Date: YYYYMMDDXX).
```

- [ ] **Step 2: Add the changelog entry**

In `changelog.md`, insert this new bullet at the very top of the `## Unreleased` section (immediately before the existing first bullet):

```markdown
- User request (2026-07-07): the Decision report's Title cell now shows an
  "Edit" link into `mod_confsubmissions`'s `edit.php` for anyone holding
  that plugin's new `mod/confsubmissions:editany` capability (granted by
  default to `editingteacher`/`manager` -- see that plugin's own
  changelog). Saving or cancelling returns to the Decision report with
  whatever track/decision-status filter was active, via `edit.php`'s new
  `returnurl` param. No other confprogram page changes.
```

- [ ] **Step 3: Update the README**

In `README.md`, under the `## Architecture notes` section, add a new bullet (at the end of that section's list):

```markdown
- **The Decision report links directly into `mod_confsubmissions`'s
  `edit.php`** for any viewer holding that plugin's `mod/confsubmissions:editany`
  capability, so a track (or any other submission detail) can be corrected
  without leaving the review workflow. This is a read of a capability
  defined in a sibling plugin, checked against that plugin's own module
  context -- there is no new capability in this plugin for it.
```

- [ ] **Step 4: Commit**

```bash
cd /vagrant/moodle-dev/moodle-mod_confprogram
git add version.php changelog.md README.md
git commit -m "Bump version and document the Decision report's new Edit link"
```

## Self-Review

**Spec coverage:** Part 1 (capability, ownership/call-window relaxation, returnurl, banner) → Tasks 1-2. Part 2 (both UI entry points) → Tasks 3 and 5. Version/changelog/README for both plugins → Tasks 4 and 6. No spec section is without a task.

**Placeholder scan:** No TBD/TODO; every step has complete, exact code.

**Type consistency:** `can_edit_submission(\stdClass $submission, \context $context, ?int $userid = null): bool` is defined once in Task 1 and consumed with that exact signature in Task 2; no other task calls it. `$iseditany` (bool) is introduced and used consistently within Task 2 only. `returnurl` (string, `PARAM_LOCALURL`) is produced by Task 2 and consumed by Task 5's `$editurl` construction with matching param name.
