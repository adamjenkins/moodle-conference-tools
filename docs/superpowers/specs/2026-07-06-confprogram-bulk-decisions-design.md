# mod_confprogram: bulk decisions, filterable Decision report, bulk "start new round"

Design doc for a user-requested redesign of `mod_confprogram`'s Decision
report (`decisions.php`), plus a related change to `assign.php`. Repo:
`/vagrant/moodle-dev/moodle-mod_confprogram`. Deferred from the 2026-07-06
bug-fix round per explicit user request ("fix bugs now, design features
next").

## Current state

`decisions.php` lists every non-unvetted submission in the linked
`mod_confsubmissions` instance as its own bootstrap "card": title, round,
latest decision (+ a per-row "Start new review round" link when that
decision is `resubmit`, navigating to `assign.php?focus=<id>`), a full
nested `<table>` of this round's reviews (reviewer/grade, or anonymised),
and its own one-row `<form>` with a decision `<select>` + Save button. No
filtering, no bulk actions, no JS at all today.

`assign.php` already has: a track filter (`?trackid=`), a single-submission
`?focus=` mode (shows only that one submission, with a "back to all" link),
and — when `groupreviewmode` is on — a full bulk UI (a checkbox per row +
"assign this group to selected" at the bottom) that works against whatever
list of submissions the current filter mode populated.

## 1. Table layout

Replace the per-submission cards with one `generaltable` row per submission,
matching the visual language `assign.php` already established in this
plugin:

| ☐ | Title | Track | Round | Latest decision | Reviews | Decision |
|---|---|---|---|---|---|---|

- **Track**: the coloured pill from `field_formatter::get_track_pill_html()`
  (already built this session), not plain text.
- **Latest decision**: `get_string('lastdecision', ...)` text only — no
  action link (see section 3, the per-row "Start new review round" link is
  removed entirely in favour of one bulk link).
- **Reviews**: condensed to stacked `reviewer: grade` lines (the same
  `<br>`-joined-lines convention `assign.php`'s "current reviewers" column
  already uses for names), respecting the existing blind-review anonymising
  (`anonymousreviewer`) and "no reviews yet" (`noreviewsyet`) cases exactly
  as today. Not a nested `<table>` per row anymore.
- **Decision**: unchanged from today — the existing per-row `<select>` +
  Save button, for one-off exceptions that shouldn't go in a batch.

## 2. Filters

A plain GET filter form above the table, no JS, matching `assign.php`'s
existing track-filter pattern exactly:

- **Track** — `?trackid=`, "All tracks" default, same options source
  (`submissions_api::get_tracks()`) `assign.php` already uses.
- **Decision status** — new `?decisionstatus=`, one of: `''` (all), `none`
  (no decision recorded yet — a submission with no `confprogram_decision`
  row for this instance at all), or one of the four real decision values
  (`accept`/`reject`/`resubmit`/`waitlist`). Applied server-side against
  each submission's already-computed `rounds::get_latest_decision()` result,
  before the table is built — same shape as the existing track filter.

Both filters combine (AND), same as any standard filter form. Neither
filter mode is related to the `resubmitted=1` mode being added to
`assign.php` (section 3) — that's a different page entirely.

## 3. Bulk decisions

- Each row's checkbox is `submissionids[]`. A header checkbox toggles every
  checkbox currently rendered on the page (new, small inline JS in the new
  AMD module below — no library needed, no interaction with server-side
  filtering beyond "whatever rows are currently on screen").
- A toolbar above the table: a decision `<select>` + an "Apply to selected"
  button.
- Clicking it shows a confirm dialog via `core/notification`'s `confirm()`
  API — the same one `mod_confscheduler`'s existing `confirmdeleteroom`/
  `confirmsendnotifications` dialogs already use — with the count and
  chosen decision label substituted in, e.g. *"Apply Accept to 12
  submissions?"*. The form only submits on confirmation.
- This requires a new `amd/src/decisions.js` — `decisions.php` has no AMD
  module today. Two responsibilities only: the select-all checkbox, and the
  confirm-before-submit gate on the bulk-apply button. No other client-side
  logic (the per-row select+Save stays a plain form submit, unchanged).
- Server-side POST handler (new, alongside the existing single-row handler):
  loops over `submissionids[]` exactly like `assign.php`'s existing
  `assigngroup` bulk handler — for each id, re-verify it belongs to this
  `confsubmissions` instance and isn't unvetted (the same IDOR-prevention
  check the single-row handler already does) before calling
  `api::record_decision()`. Invalid ids in the submitted set are silently
  skipped (not an error), matching `assigngroup`'s existing behaviour for
  the same reason (a stale/crafted id must never abort the whole batch or
  leak which ids were valid).
- Redirects with a single success notice mentioning how many were updated
  (new `bulkdecisionsaved` string, `{$a}` = count), the same
  redirect-with-notification pattern the single-row handler already uses.

## 4. Bulk "start new round" (replaces every per-row link)

Recording a `resubmit` decision already auto-advances that submission's own
round internally (`rounds::get_current_round()` derives it from decision
history — nothing is stored that needs "advancing"). The existing per-row
"Start new review round" link is therefore purely navigational: it takes
the organiser to `assign.php?focus=<id>` to assign a reviewer for the new
round, one submission at a time.

- Every per-row link is removed from the **Latest decision** column
  entirely.
- One line appears once above the table (only when at least one non-
  unvetted submission's latest decision is `resubmit`): *"3 submissions
  awaiting a new round of review → Start new round"*, linking to
  `assign.php?id=<cmid>&resubmitted=1`.
- `assign.php` gains a new filter mode, `?resubmitted=1`, alongside its
  existing `?trackid=`/`?focus=` ones (mutually exclusive with both,
  matching how `?focus=` already excludes the track filter today):
  populates `$submissions` with every non-unvetted, resubmit-decided
  submission belonging to this instance (the identical
  `rounds::get_latest_decision()` check `decisions.php` already computes),
  shown with a "Now showing: submissions awaiting a new round" banner + a
  "back to all" link — mirroring the existing single-`focus` banner UX
  exactly, just for a set instead of one id.
- Because `assign.php`'s bulk reviewer-group-assign UI (checkboxes +
  "assign this group to selected") already works against whatever list
  populates `$submissions`, regardless of which filter branch built it,
  this works with **no new UI on `assign.php`'s rendering side** — only the
  new filtering branch. The existing single-`focus` mode is untouched
  (still reachable directly by URL; nothing currently links to it besides
  the per-row links being removed here, but it's not being deleted).

## Security

Every new server-side entry point follows this project's established
instance-scoping pattern, with no new pattern introduced:

- The bulk-decision POST handler re-verifies each submitted id belongs to
  this `confsubmissions` instance and isn't unvetted, exactly like the
  existing single-row handler.
- `assign.php`'s new `resubmitted=1` branch only ever selects from
  `submissions_api::get_submissions_for_instance($confsubmissionscm->instance)`
  (the same instance-scoped source every other branch already uses), never
  from a caller-supplied id list.
- `require_capability('mod/confprogram:decide', ...)` (decisions.php) and
  `mod/confprogram:managereviewers` (assign.php) are unchanged — no new
  capability, no new capability check needed since both pages already gate
  their whole POST-handling on the existing one.

## Verification plan

- New PHPUnit coverage: the bulk-decision POST handler (valid batch, a
  submission from another `confsubmissions` instance rejected, an unvetted
  submission skipped, capability check), the decision-status filter, and
  `assign.php`'s new `resubmitted=1` branch (returns the right set, excludes
  unvetted, excludes non-resubmit).
- phpcs/moodlecheck clean, AMD built via `grunt amd --force` and confirmed
  stable across two rebuilds (this plugin's first AMD module).
- `moodle-reviewer` pass before committing.
- Live Playwright verification: track + decision-status filters, select-all
  checkbox, bulk-apply confirm-then-submit flow (including cancelling the
  confirm — nothing should change), the single "start new round" link
  landing on `assign.php` pre-filtered with the bulk-assign checkboxes
  ready to use.
- Docs: `changelog.md`/`README.md`, EN+JA lang parity, and this coordination
  repo's `RELATIONS.md`/`SUMMARY.md`/`TASKLIST.md`/user manual.
