# mod_confsubmissions: let editing teachers edit any submission (with a Decision-report entry point)

Design doc for a user-requested cross-plugin change. Primary repo:
`/vagrant/moodle-dev/moodle-mod_confsubmissions` (new capability + relaxed
`edit.php` gate). Secondary repo:
`/vagrant/moodle-dev/moodle-mod_confprogram` (new "Edit" link on the
Decision report).

## Current state

`mod_confsubmissions/edit.php` is the single shared submission-edit entry
point — even `mod_confprogram/feedback.php` links into it for a submitter
revising after a `resubmit` decision. Today it is hard-gated to the
submission's owner:

```php
if ((int) $submission->userid !== (int) $USER->id) {
    throw new \moodle_exception('error:notowner', 'mod_confsubmissions');
}
require_capability('mod/confsubmissions:submit', $context);
```

and, independent of ownership, blocked entirely while the call is not open
(`timeopen`/`timeclose`):

```php
if (!$callisopen) {
    echo $OUTPUT->notification(get_string('callnotopen', 'mod_confsubmissions'), 'info');
    echo $OUTPUT->footer();
    exit;
}
```

There is no existing capability for "edit any submission": `viewall` (read)
is granted to `teacher`+`editingteacher`+`manager`, but the only write
equivalent, `deleteany`, is `manager`-only.

`mod_confsubmissions/view.php`'s "All submissions" table already has a
conditionally-added action column (a "Delete" link, shown only for
`deleteany` holders); its Title cell links to the read-only
`submission.php`.

`mod_confprogram/decisions.php` (the Decision report) lists every
submission with a track pill (`field_formatter::get_track_pill_html()`)
but its Title cell is plain text — no link at all today.

## 1. New capability: `mod/confsubmissions:editany`

```php
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

Deliberately not granted to plain `teacher` (unlike `viewall`) — editing is
a higher-risk write action than viewing. `RISK_DATALOSS` matches
`deleteany`'s own risk bitmask (an edit can silently overwrite content the
original submitter wrote).

## 2. `edit.php`: relax the ownership + call-window gates

```php
$iseditany = has_capability('mod/confsubmissions:editany', $context);
```

- Ownership check becomes: throw `error:notowner` only when
  `!$iseditany` (an `editany` holder may open any submission on this
  instance, regardless of who owns it). The existing
  `require_capability('mod/confsubmissions:submit', $context)` call right
  after stays for the owner path; an `editany` holder does not need
  `submit` too — the `editany` check alone is sufficient and is checked
  first.
- The `!$callisopen` block only fires when `!$iseditany`: an `editany`
  holder can edit at any time, open call or not. (The known follow-up
  limitation noted in `feedback.php`'s docblock, about a *submitter* being
  blocked post-close, is unaffected and stays open as before — this only
  changes the `editany` path.)
- None of this applies to *creating* a new submission (`edit.php` with no
  `submissionid`): that path is untouched, still requires `submit` and an
  open call, regardless of who holds `editany`. `editany` only ever
  applies to an *existing* submission.

## 3. `edit.php`: optional `returnurl`

```php
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
$viewurl = $returnurl !== '' ? new moodle_url($returnurl) : new moodle_url('/mod/confsubmissions/view.php', ['id' => $cm->id]);
```

`PARAM_LOCALURL` (Moodle's standard safe-return-url type — rejects
anything not resolving to this same site) prevents an open-redirect
vector. `$pageurl` (the form's own action URL) carries `returnurl` through
as a param too, so it survives the POST round-trip. Both existing
redirect call sites (cancel, and post-save `redirect($viewurl, ...)`)
are unchanged in behavior otherwise — they just target the resolved
`$viewurl`. When no `returnurl` is supplied (every existing caller today),
behavior is byte-for-byte identical to today.

## 4. On-form banner when editing on someone else's behalf

Shown only when `$iseditany && !$isowner` (an `editany` holder editing
their own submission, e.g. a teacher who is also a presenter, sees nothing
extra):

```php
echo $OUTPUT->notification(
    get_string('editinganothersubmission', 'mod_confsubmissions', fullname(\core_user::get_user($submission->userid))),
    'info'
);
```

New string: `$string['editinganothersubmission'] = 'You are editing this submission on behalf of {$a}.';`

## 5. UI entry point: `mod_confsubmissions/view.php`

The existing conditional action column becomes two independent
conditions, not one:

```php
if ($candeleteany) { $table->head[] = ''; }   // Delete column (unchanged)
if ($caneditany) { $table->head[] = ''; }     // new Edit column
```

Each row gets an `editany`-gated "Edit" link (`get_string('edit')`, core
string) to `edit.php?id=<cmid>&submissionid=<id>` — no `returnurl` needed
here since the default (this same page) is already correct. A holder of
both capabilities sees both columns; an `editingteacher` (editany only)
sees just Edit; a `manager` sees both, as today.

## 6. UI entry point: `mod_confprogram/decisions.php`

```php
$confsubmissionscontext = context_module::instance($confsubmissionscm->id);
$caneditsubmissions = has_capability('mod/confsubmissions:editany', $confsubmissionscontext);
```

(A new cross-plugin capability check — no prior precedent in this
codebase, but the page already holds `$confsubmissionscm` for exactly this
kind of lookup.)

When true, the Title cell gains a small "Edit" link after the title text:

```php
$editurl = new moodle_url('/mod/confsubmissions/edit.php', [
    'id'           => $confsubmissionscm->id,
    'submissionid' => $submission->id,
    'returnurl'    => $pageurl->out(false), // decisions.php's own URL, current filters included
]);
```

`$pageurl` here is `decisions.php`'s existing page URL object, which
already carries whatever `trackid`/`decisionstatus` filters are active —
so saving (or cancelling) returns the organiser to the same filtered view
they started from. New string:
`$string['editsubmissionlink'] = 'Edit {$a}';` (`{$a}` = submission title,
used as the link's `aria-label`, mirroring `selectsubmission`'s existing
pattern in this same file for row checkboxes).

No other confprogram page changes (per your choice — Decision report
only).

## Security

- `editany`'s ownership bypass is capability-gated, `RISK_DATALOSS`-flagged,
  and restricted to `editingteacher`/`manager` by default — consistent
  with how `deleteany` (an equally destructive write action) is scoped
  today.
- `returnurl` uses `PARAM_LOCALURL`, Moodle's standard open-redirect-safe
  type, not a raw string.
- The submission-belongs-to-this-instance check
  (`$submission->confsubmissions != $confsubmissions->id`) is untouched —
  an `editany` holder still can't reach a submission from a different
  `confsubmissions` instance via a crafted `submissionid`.
- The new cross-plugin capability check in `decisions.php` reads
  `mod/confsubmissions:editany` against the *confsubmissions* module
  context (`$confsubmissionscm`), not the confprogram context — checking
  the capability in the wrong plugin's context would be meaningless.

## Verification plan

- New PHPUnit coverage in `mod_confsubmissions`: `edit.php`'s ownership
  bypass (an `editany` holder can open/save a submission they don't own; a
  plain `submit`-only holder still gets `error:notowner` on someone else's
  submission), the call-window bypass for `editany`, and the `returnurl`
  round-trip (falls back to `view.php` when absent; redirects to a
  supplied local URL when present; rejects a non-local one).
- New PHPUnit coverage in `mod_confprogram`: the new capability check
  gating the Decision report's Edit link (present/absent per capability),
  and that the generated `edit.php` URL carries the right `returnurl`.
- phpcs/moodlecheck clean on both plugins.
- `moodle-reviewer` pass on both plugins before committing.
- Live Playwright verification: as an editingteacher without `submit`,
  open another user's submission from both `mod_confsubmissions/view.php`
  and `mod_confprogram/decisions.php`, confirm the "on behalf of" banner,
  edit the track, save, and land back on the page you started from with
  filters intact; confirm a plain (non-editing) `teacher` sees neither
  Edit link; confirm editing works with the call closed for an `editany`
  holder but is still blocked for a plain owner-submitter.
- Docs: `changelog.md`/`README.md` for both plugins, EN+JA lang parity,
  and this coordination repo's `RELATIONS.md`/`SUMMARY.md`/`TASKLIST.md`/
  user manual (both plugins' manual pages).
