# mod_confscheduler: day start/end display window + print/colour cleanup

Design doc for a user-requested follow-up in `mod_confscheduler`. Repo:
`/vagrant/moodle-dev/moodle-mod_confscheduler`. Three independent changes,
built and shipped together since they all touch the same grid rendering code.

## 1. Day start/end display window (edit mode)

**Problem**: The vertical time axis for each day is always auto-computed from
existing slots (min/max start/end, padded 30 minutes, rounded to whole hours,
8-hour minimum), defaulting to 08:00-18:00 when a day has nothing scheduled
yet. There is no way for an organiser to fix the displayed window to their
conference's actual daily hours (e.g. 08:00-18:00 every day), so an empty room
on a new day always starts from scratch with the generic default.

**Design**:

- New nullable `confscheduler.daystart` / `.dayend` columns: `int`,
  minutes-since-midnight (e.g. `08:00` -> `480`). Both `null` (the upgrade
  default) means "unchanged, fully automatic" behaviour — zero behaviour
  change for existing instances until an organiser opts in.
- New toolbar quick control in edit mode, next to the existing
  gapminutes/pxperhour inputs: two `<input type="time">` fields plus an
  "Automatic" checkbox.
  - Checked (default when unset): both time inputs disabled, saved value is
    `null`/`null`.
  - Unchecked: both time inputs required, `dayend` must be strictly greater
    than `daystart` (same-day only — no overnight/cross-midnight window).
- Server: `api::set_day_bounds(int $confschedulerid, ?int $daystart, ?int
  $dayend): void`, validating both-null-or-both-set and `dayend > daystart`
  (0-1439 range each), mirroring `set_gap_minutes()`/`set_pxperhour()`'s
  existing pattern exactly. New `classes/external/set_day_bounds.php` +
  `db/services.php` entry, gated on `mod/confscheduler:manageschedule` like
  its siblings. New `Repository.setDayBounds()` AMD wrapper.
- `grid_data::build()` returns `daystart`/`dayend` (nullable ints) in the
  payload, alongside the existing `conferencestart`/`conferenceend`.
- Rendering (both edit mode and read-only Display mode, single-day and "All
  days" views — the setting is configured in edit mode only, but the
  resulting axis is identical in both modes, same as `pxperhour` today):
  - When configured, a day's default axis is exactly `[daystart, dayend]`
    (no padding/rounding — the organiser's chosen times are already exact).
  - If a real scheduled slot falls outside that window on a given day (e.g. a
    manually-dragged early slot, or legacy data), the axis quietly widens
    just enough to still show it in full. This never hides real data,
    matching this project's established convention (e.g. the day selector
    already unions the conference date range with any day that has an
    existing slot, rather than ever hiding one). The out-of-window portion
    that's shown this way is greyed using the **same band styling** as the
    existing out-of-conference-hours bands, so it reads as "outside the
    normal window" even though it's still visible.
  - When NOT configured (`null`/`null`), behaviour is completely unchanged
    from today.
- **Refactor**: `scheduler_grid.js`'s `computeTimelineBounds()` and
  `scheduler_display.js`'s `computeDayTimeRange()` are today near-duplicate
  copies of the same slot-derived padding logic (the latter's own docblock
  says so explicitly). Both are replaced by one shared function added to
  `day_utils.js` (which already holds the other genuinely-shared
  day-boundary helpers), taking the new daystart/dayend parameters and
  implementing the merge-with-slots logic described above once.
- `day_utils.js`'s `outOfHoursBands()` gains the new intraday bands (before
  `daystart` / after `dayend`, per day) alongside its existing
  out-of-conference-date-range bands, unioned per day, same visual treatment.
- **Explicitly out of scope**: this is a display-only convenience, not a new
  hard scheduling constraint. Dragging or auto-scheduling a block before
  `daystart`/after `dayend` is NOT rejected server-side — only the
  conference-date bounds (`conferencestart`/`conferenceend`) remain an
  authoritative placement restriction. Can be added later if wanted.

## 2. Black & white becomes a live on-screen toggle, inherited by print

**Problem**: `.mod_confscheduler-print-bw`'s rules exist only inside
`@media print` in `styles.css`, so toggling the "Black & white" radio has no
visible effect until the user actually opens the print dialog.

**Design**:

- Move the existing `.mod_confscheduler-print-bw` rule block out of
  `@media print` entirely, so it applies immediately on screen. Print then
  automatically inherits whatever is already on screen — no separate
  print-only logic is needed for colour mode at all.
- Rename for honesty, since the toggle is no longer print-specific:
  - CSS class `mod_confscheduler-print-bw` -> `mod_confscheduler-bw`.
  - Lang strings `printcolourmode`/`printcolour`/`printbw` ->
    `colourmode`/`colour`/`blackandwhite` (EN + JA).
  - Radio input `name` attribute `mod_confscheduler_printcolour` ->
    `mod_confscheduler_colourmode`.

## 3. Remove paper-size and orientation print controls entirely

**Problem/request**: portrait/landscape and A4/A3/A2 controls write a
dynamically-generated `@page` rule (`scheduler_display.js::applyPageSize()`).
Per direct user feedback, browsers already override/ignore the orientation
part in practice, and the user gets better print scaling by picking a size
(specifically A2) directly in the browser's own native print dialog rather
than through this plugin's forced `@page` rule.

**Design**:

- Delete the orientation `<fieldset>` (portrait/landscape radios) and the
  paper-size `<select>` (A4/A3/A2) from `templates/display.mustache`.
- Delete `applyPageSize()` and all its call sites (init-time and
  change-event listeners for both controls) from `scheduler_display.js`. No
  `@page` rule is written at all any more.
- Remaining print controls: the colour/black & white toggle (now also a live
  on-screen view, per section 2) and the existing "Print" button. Paper size
  and orientation are left entirely to the browser's native print dialog.
- Remove the now-unused `papersize`/`orientation`/`portrait`/`landscape` lang
  strings (EN + JA) — confirmed unused anywhere else in the plugin.

## Verification plan

Same discipline as every other change in this project:

- New PHPUnit coverage: `set_day_bounds` validation (both-null, both-set,
  `dayend <= daystart` rejected, capability-gated), `grid_data`'s new
  `daystart`/`dayend` fields and the new intraday out-of-window band
  computation.
- phpcs/moodlecheck clean.
- AMD rebuilt from scratch (`grunt amd --force`) and diff-checked stable,
  per this project's standing rule for any AMD build artifact change.
- Live Playwright verification: quick control save/reload in edit mode, axis
  clipped to configured hours with an out-of-window legacy slot still shown
  and greyed, identical rendering in read-only Display mode and "All days"
  view; B&W toggle now visibly changes the on-screen grid; print preview
  reflects the current colour mode with no paper-size/orientation controls
  present.
- `moodle-reviewer` pass before committing.
- Docs: `mod_confscheduler`'s own `README.md`/`changelog.md`, EN+JA user
  manuals, and this coordination repo's `RELATIONS.md`/`SUMMARY.md`/
  `TASKLIST.md` all updated.
