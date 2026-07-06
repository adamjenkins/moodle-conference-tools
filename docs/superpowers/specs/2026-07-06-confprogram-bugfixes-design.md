# mod_confprogram: numeric-button, review-crash, and track-pill fixes

Design/root-cause doc for a user-reported bug-fix round in `mod_confprogram`
(plus a small, tightly-coupled `mod_confscheduler` fix for the same track-colour
issue). All three root causes below were confirmed by reading the code and,
for the review-crash bug, by live-reproducing both paths against the demo site
with Playwright (full stack traces captured). Repos:
`/vagrant/moodle-dev/moodle-mod_confprogram`,
`/vagrant/moodle-dev/moodle-mod_confscheduler`.

## 1. Numeric submit buttons instead of language strings

**Root cause**: for an `<input type="submit">`, the `value` attribute is both
the submitted data AND the visible label. Five buttons across two files set
`value` to a raw row id, so the button visibly displays a number:

- `assign.php`: `removeassignment` (value = assignment id), `assignindividual`
  (value = submission id), `assigngroup` (value = hardcoded `1`, so it would
  literally show "1").
- `unvetted.php`: `unmarkunvetted`/`markunvetted` (value = submission id each),
  with the correct lang string incorrectly placed as sibling text *next to*
  the button rather than inside it.

`decisions.php`, in the same plugin, already does this correctly: the row id
goes in a hidden input, and the submit button's `value` is a real
`get_string()` call. That's the pattern to apply everywhere else.

**Fix**: convert all five from `<input type="submit" value="$id">` to
`<button type="submit" name="..." value="$id">{{label}}</button>` — a
`<button>` element can carry a submitted `value` distinct from its displayed
content, so no POST-handling logic changes at all (every handler already
reads the same field name via `optional_param()`). Labels used (no new lang
strings needed — all already exist):
- `removeassignment` → `get_string('remove')` (core; already used as the
  button's `title` tooltip today).
- `assignindividual` → `get_string('assignreviewer', 'mod_confprogram')`
  ("Assign reviewer" — already exists, used as the column header).
- `assigngroup` → new string `assigngroup` = "Assign group" (EN+JA) — no
  existing short string fits.
- `unmarkunvetted`/`markunvetted` → the existing `get_string('unmarkunvetted'
  | 'markunvetted', 'mod_confprogram')`, moved from sibling text into the
  button itself (removing the now-redundant outside text).

## 2. Review submit/cancel throws a fatal error

**Root cause, confirmed live (2 reproductions, full stack traces)**:
`review.php` calls `echo $OUTPUT->header()` and renders a substantial amount
of page content (heading, submission detail, speakers, track, abstract,
optional fields, "Review" heading) *before* it builds the `review_form` and
checks `is_cancelled()`/`get_data()`. By the time it calls `redirect()` —
line 307 for Cancel, line 325 for a successful Submit — Moodle's page state
is already `STATE_IN_BODY`, so `redirect()` cannot perform a normal HTTP
redirect. It falls through to `debugging('You should really redirect before
you start page output')`, which this environment's error handling escalates
to a fatal `ErrorException` visible to the user (reproduced identically for
both Cancel and Submit — same file, same mechanism, matching the report that
both actions throw).

Every sibling page in this plugin (`assign.php`, `decisions.php`,
`unvetted.php`) already does all POST-handling-and-redirect *before* any
`echo $OUTPUT->header()` call. `review.php` is the one outlier, because its
form depends on data (`$gradinginstance`, `$reviewitemid`) that today is
computed interleaved with page-content rendering.

**Fix**: restructure `review.php`'s single-submission mode so that
everything capable of triggering a `redirect()` — building the grading
instance (`get_grading_manager()`, `get_or_create_instance()`), constructing
`review_form`, and checking `is_cancelled()`/`get_data()` — happens *before*
`echo $OUTPUT->header()`. Output (heading, submission detail, the "no
grading method configured" notice, `$mform->display()`) all move to after
that block, reached only when the request is neither a cancel nor a
successful submit (i.e. the initial GET, or a redraw after a validation
error). The early-exit branches that use `throw new moodle_exception(...)`
(not-assigned, wrong instance) are unaffected — exceptions, unlike
`redirect()`, are safe to raise regardless of page output state, so those
don't move. The "unvetted"/"capacity reached" notice-then-exit branches gain
their own `header()`/`footer()` calls in their new, earlier position (mirroring
the existing "not in review phase" early-exit pattern already in this file).

## 3. Track colour never reaches the pill badges

**Root cause**: `confsubmissions_track.colour` already exists and
`mod_confsubmissions\api::get_tracks()` already returns it — no schema change
needed anywhere. Two separate gaps downstream:

- `mod_confscheduler`: `grid_data.php` builds `$tracksbyid[id] = $track->name`
  — colour is discarded before it ever reaches the client.
  `buildTrackPill()` (duplicated in `scheduler_grid.js` and
  `scheduler_display.js`) has no colour parameter, and
  `.mod_confscheduler-track-pill` hardcodes `background: #3366cc`. Result:
  every pill is the same fixed blue regardless of the track's configured
  colour.
- `mod_confprogram` has no pill concept at all — track renders as plain text
  in `field_formatter.php`'s `case 'track'` (consumed by the Display-phase
  list and modal), and inline in `review.php` and `assign.php`.

**Important constraint found while investigating**: `field_formatter::
format_value()`'s existing contract is "never returns HTML — the caller
escapes it" (the Display list wraps it in `s()`, the modal template uses an
auto-escaping `{{ }}` tag). A pill is real markup, so it cannot be returned
from `format_value()` without breaking that contract for every other field
that shares the same generic per-field loop. The fix follows the *exact*
precedent already established for the `title` field, which has the identical
problem today (title is never plain-escaped text either — it's a link) and is
handled by being **excluded from the generic fields loop and rendered in its
own dedicated slot** in both the list (`view.php`) and the modal
(`get_submission_detail.php` / `submission_modal.mustache`), gated on the
same visibility-matrix setting so an organiser's list/modal show/hide choice
for "track" still applies.

**Fix**:

- `mod_confscheduler`: `grid_data.php`'s `$tracksbyid` keeps the whole track
  record (not just `->name`); each slot/unscheduled entry gains a
  `trackcolour` (nullable hex string) field. `buildTrackPill()` in both AMD
  modules gains a `trackcolour` parameter — when set, applies it as an inline
  `background-color` plus the existing `ColourUtils.contrastTextColour()`
  helper for text colour (identical treatment to room headers/span blocks
  already in this plugin); when absent, no inline style is set and the
  existing blue default CSS applies unchanged, so tracks without a configured
  colour look exactly as they do today.
- `mod_confprogram`: new `field_formatter::get_track_pill_html(\stdClass
  $submission): string`, returning trusted HTML (`format_string()`-escaped
  track name inside a `<span class="mod_confprogram-track-pill" style="...">`,
  same colour/contrast logic as confscheduler's, small PHP-side duplicate of
  the luminance calculation — matching this project's existing precedent of
  deliberately duplicating small pure display logic across the PHP/JS
  boundary rather than sharing it) or the existing `get_string('notrack', ...)`
  fallback text.
  - `view.php`'s `$rendertable`: `track` added to the same `array_diff(...,
    ['title'])` exclusion (now excluding both), with its own dedicated pill
    cell inserted at the same position, built from the new helper (not
    passed through `s()`).
  - `get_submission_detail.php`: same exclusion pattern already applied to
    `title`, extended to `track`; a new `trackpill`/`hastrackpill` key added
    to the modal's export data; `submission_modal.mustache` renders it via a
    raw `{{{trackpill}}}` (the one deliberate exception to the template's
    otherwise-fully-escaped fields, justified exactly like `title`/
    `scheduletext` already are).
  - `review.php` and `assign.php` (reviewer-facing, per explicit request to
    cover "everywhere"): their existing plain-text track mentions are already
    in raw `html_writer`/echo contexts (never routed through the generic
    escaped-fields pipeline), so they switch to the same helper's output
    directly, no contract concerns there.
  - New `.mod_confprogram-track-pill` CSS rule in this plugin's own
    `styles.css` (already auto-loaded by Moodle's plugin CSS aggregation —
    confirmed neither this plugin nor `mod_confscheduler` ever calls
    `$PAGE->requires->css()` for their root stylesheet, yet confscheduler's
    existing pill CSS already works live), mirroring confscheduler's default
    blue/white/rounded look so both plugins' pills read as the same visual
    language.

## Verification plan

- New/updated PHPUnit coverage: `field_formatter::get_track_pill_html()`
  (with/without colour, with/without a track at all), the review.php
  cancel/submit paths (a regression test asserting no output has been sent
  before any `redirect()`-triggering branch, plus the existing save/cancel
  behaviour), `grid_data`'s new `trackcolour` field.
- phpcs/moodlecheck clean on both plugins; AMD rebuilt and diff-checked for
  `mod_confscheduler`.
- Live Playwright re-verification: cancelling and submitting a review no
  longer error (re-run the exact reproduction that found the bug); all five
  buttons show their proper labels; track pills in confscheduler's grid and
  confprogram's Display-phase list/modal/review/assign pages all reflect a
  configured track colour, and fall back to the same blue when unconfigured.
- `moodle-reviewer` pass on both plugins before committing.
- Docs: both plugins' `README.md`/`changelog.md`, EN+JA manuals, and this
  coordination repo's `RELATIONS.md`/`SUMMARY.md`/`TASKLIST.md`.

## Explicitly deferred to a follow-up

Bulk decisions on the decisions.php interface, and making the Decision report
more list-like and filterable — the user asked to fix these three bugs first
and design those two features separately afterward.
