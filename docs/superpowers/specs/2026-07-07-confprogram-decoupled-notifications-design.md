# mod_confprogram: decouple "switch to Display phase" from sending decision notifications

Design doc for a user-requested change to `mod_confprogram`'s phase-toggle
handler (`view.php`). Repo: `/vagrant/moodle-dev/moodle-mod_confprogram`.

## Current state

`view.php`'s `togglephase` POST handler, when switching Review → Display,
unconditionally does two things in sequence:

1. `api::sync_submission_statuses_to_confsubmissions()` — reveals every
   recorded accept/reject/waitlist decision's status to `mod_confsubmissions`
   immediately (a one-way reveal; switching back to Review does not hide it
   again).
2. `api::send_pending_decision_notifications()` — sends the deferred
   notification for every not-yet-notified accept/reject/waitlist decision
   on the instance, in one batch, and marks each row's `notifiedtime`.

There is no way to do (1) without (2) today — an organiser who wants to
publish the programme before speakers are emailed (e.g. to proofread the
public list first) cannot.

## 1. Decouple the phase switch from sending

The `togglephase` handler keeps calling
`sync_submission_statuses_to_confsubmissions()` exactly as today (status
reveal is unchanged and stays automatic). It **stops** calling
`send_pending_decision_notifications()`. Switching to Display phase no
longer sends any notification as a side effect, ever.

## 2. New: count of pending notifications

`send_pending_decision_notifications()`'s existing "not yet notified"
query (`confprogram_decision` rows where `notifiedtime = 0` and `decision`
is one of `notifier::NOTIFIABLE_DECISIONS`) is factored out into a new,
reusable method:

```php
public static function count_pending_decision_notifications(int $confprogramid): int
```

`send_pending_decision_notifications()` is otherwise unchanged (still does
the actual sending); this is purely extracting the count so `view.php` can
show it without duplicating the query.

## 3. New "Send pending notifications" button

Added to `view.php`'s existing edit-mode organiser controls block (the
`if ($PAGE->user_is_editing() && has_capability('mod/confprogram:managereviewers', ...))`
block that already renders the phase-toggle form) — same capability, same
edit-mode gate, sitting right next to the phase-toggle button. No new
capability.

Shown only when both are true:
- the instance's current phase is `display`,
- `count_pending_decision_notifications()` > 0.

Label includes the live count: *"Send pending notifications (3)"*
(`sendpendingnotifications` lang string, `{$a}` = count).

Clicking it is gated behind a `core/notification` confirm dialog — same
pattern as `decisions.php`'s existing bulk-decision confirm (`confirmbulkdecision`)
and the AMD-side synthetic-second-click approach that preserves the submit
button's own name/value pair through the confirm gate. Confirm text: *"Send
3 pending notifications?"* (`confirmsendpendingnotifications`, `{$a}` =
count). This needs a new, small AMD module (`amd/src/notifications_confirm.js`)
since `view.php` has no AMD module today — one responsibility only:
confirm-before-submit on this one button.

## 4. Server-side handling

A new POST branch in `view.php`, alongside the existing `togglephase`
branch: `sendpendingnotifications=1` + sesskey, gated on
`mod/confprogram:managereviewers` (same capability as the toggle). Calls
`api::send_pending_decision_notifications()`, then redirects back to the
page with a success notice: *"3 notification(s) sent."*
(`pendingnotificationssent`, `{$a}` = count actually sent), same
redirect-with-notification pattern `decisions.php`'s `bulkdecisionsaved`
already uses.

`send_pending_decision_notifications()`'s signature changes from
`: void` to `: int`, returning the number of rows whose `notifiedtime` it
actually updated this call — not the pre-send pending count, since a
`message_send()` failure can leave a row still pending (its existing
"only mark notified if actually sent" behaviour is unchanged, this just
surfaces the tally instead of discarding it). Its one existing caller
(the `togglephase` handler, per section 1, no longer calls it at all) is
unaffected; the new `sendpendingnotifications` handler is the method's
only caller now.

## Security

No new capability, no new entry point beyond the one new POST branch on an
already-`require_login`+`require_capability`-gated page. No user-supplied
ids involved — the action operates on "every pending row for this
instance" exactly like the existing phase-toggle-triggered send did,
scoped by `$confprogramid` from the already-validated `$cm`/`$confprogram`.

## Verification plan

- New PHPUnit coverage: `count_pending_decision_notifications()` (zero,
  some pending, scoped to the right instance), and that the `togglephase`
  handler no longer sends notifications as a side effect (existing
  `notifier_test`/`api_test` coverage of `send_pending_decision_notifications()`
  itself is untouched, since that method's behaviour doesn't change).
- phpcs/moodlecheck clean, AMD built via `grunt amd --force`, confirmed
  stable across two rebuilds.
- `moodle-reviewer` pass before committing.
- Live Playwright verification: switch to Display with pending decisions
  and confirm no email/notification fires; confirm the new button appears
  with the right count; confirm the dialog (both confirm and cancel paths);
  confirm sending marks rows notified and the button disappears
  (count reaches zero) on reload.
- Docs: `changelog.md`/`README.md`, EN+JA lang parity, and this coordination
  repo's user manual.
