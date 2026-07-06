# confprogram Bulk Decisions + Filterable Decision Report Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace `mod_confprogram`'s per-submission-card Decision report with a filterable, bulk-decision-capable table, and replace its per-row "Start new review round" links with one bulk link that lands the organiser on `assign.php`'s existing bulk-assign UI, pre-filtered to every resubmit-decided submission.

**Architecture:** A new `classes/local/decision_report.php` (four static methods: decorate, filter-by-status, filter-resubmitted, apply-bulk-decision) is the sole new business-logic surface, independently unit-tested; `decisions.php` and `assign.php` are thin consumers, following this plugin's existing pattern (`display_list.php`, `grid_data.php`, etc.) of keeping page scripts thin and logic in `classes/local/`. One new AMD module (`decisions.js`, this plugin's first) adds a select-all checkbox and a confirm-before-submit gate on the bulk-apply button, mirroring `mod_confscheduler`'s existing `Notification.confirm()` pattern exactly.

**Tech Stack:** Moodle 5.2 plugin (PHP 8.4, `mod_confprogram`), Moodle AMD/RequireJS (`core/notification`, `core/str`), PHPUnit.

## Global Constraints

- Every new server-side entry point re-verifies a submitted submission id belongs to `$confsubmissionscm->instance` and is not in `$unvettedids` before acting on it — the same instance-scoping/IDOR-prevention check every existing handler in this plugin already does. No new pattern.
- No new capability: `mod/confprogram:decide` (decisions.php) and `mod/confprogram:managereviewers` (assign.php) are unchanged.
- All new user-facing strings go through `get_string()`, added to both `lang/en/confprogram.php` and `lang/ja/confprogram.php` in the same commit — no EN/JA drift.
- Every commit that changes `db/services.php`, lang strings, mustache, JS, or PHP bumps `version.php` (this plugin's own established convention — every prior commit in its history does this, including JS/lang-only ones).
- AMD source changes are rebuilt via `grunt amd --force` (run from the deployed plugin directory at `/srv/lms/moodle/public/mod/confprogram`, with `--gruntfile /srv/lms/moodle/Gruntfile.js`) and reconfirmed stable across two consecutive rebuilds before the build output is copied back into the git repo.
- Deploy to the test Moodle site at `/srv/lms` via `rsync` (never a symlink) and verify live with Playwright before considering any task done — this project's standing rule, and this feature involves new client-side JS (a confirm-before-submit gate) that unit tests cannot exercise at all.
- `TASKLIST.md`/`SUMMARY.md`/`RELATIONS.md` in the coordination repo (`/vagrant/moodle-dev/moodle-conference-tools`) are edited on disk but never `git add`ed — they're gitignored by design. The EN/JA user manuals (`mod_confprogram.en.md`/`.ja.md`) in that same repo ARE tracked and must be committed.

---

### Task 1: `decision_report::decorate_submissions()`

**Files:**
- Create: `/vagrant/moodle-dev/moodle-mod_confprogram/classes/local/decision_report.php`
- Test: `/vagrant/moodle-dev/moodle-mod_confprogram/tests/local/decision_report_test.php`

**Interfaces:**
- Consumes: `\mod_confprogram\local\rounds::get_current_round(int $confprogramid, int $submissionid): int`, `\mod_confprogram\local\rounds::get_latest_decision(int $confprogramid, int $submissionid): ?\stdClass`, `\mod_confprogram\api::get_reviews_for_round(int $confprogramid, int $submissionid, int $round): array` (all already exist).
- Produces: `decision_report::decorate_submissions(int $confprogramid, array $submissions): array` — `$submissions` is an id-keyed array of raw submission `\stdClass` objects (the shape `\mod_confsubmissions\api::get_submissions_for_instance()` already returns). Returns the same id-keying, each value a `\stdClass` with public properties `->submission` (the original object), `->round` (int), `->latestdecision` (`?\stdClass`), `->reviews` (array of `confprogram_review` records). Later tasks (2, 3, 5) consume this exact shape.

- [ ] **Step 1: Write the failing test**

```php
<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

declare(strict_types=1);

namespace mod_confprogram\local;

use advanced_testcase;
use mod_confprogram\api;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for \mod_confprogram\local\decision_report: the data layer behind
 * decisions.php's table and assign.php's "resubmitted" filter mode.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(decision_report::class)]
final class decision_report_test extends advanced_testcase {
    /**
     * Creates a course, a confsubmissions instance, and a confprogram instance
     * pointed at it. Mirrors \mod_confprogram\local\rounds_test's own helper.
     *
     * @return array{0: int, 1: int} [confprogramid, confsubmissionsid]
     */
    private function create_confprogram(): array {
        $course = $this->getDataGenerator()->create_course();
        $confsubmissions = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);
        $confsubmissionscm = get_coursemodule_from_instance('confsubmissions', $confsubmissions->id);

        $confprogram = $this->getDataGenerator()->create_module('confprogram', [
            'course'              => $course->id,
            'confsubmissionscmid' => $confsubmissionscm->id,
        ]);

        return [(int) $confprogram->id, (int) $confsubmissions->id];
    }

    /**
     * Inserts a bare confsubmissions_submission row directly (mirrors
     * \mod_confprogram\local\field_formatter_test's own helper).
     */
    private function create_submission(int $confsubmissionsid): \stdClass {
        global $DB;

        $userid = $this->getDataGenerator()->create_user()->id;
        $id = $DB->insert_record('confsubmissions_submission', (object) [
            'confsubmissions' => $confsubmissionsid,
            'userid'          => $userid,
            'trackid'         => null,
            'title'           => 'A Test Talk',
            'abstract'        => 'Abstract text',
            'status'          => 'submitted',
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        return $DB->get_record('confsubmissions_submission', ['id' => $id]);
    }

    /**
     * A submission with no decision yet: round 1, null latestdecision, empty reviews.
     */
    public function test_decorate_submission_with_no_decision(): void {
        $this->resetAfterTest();

        [$confprogramid, $confsubmissionsid] = $this->create_confprogram();
        $submission = $this->create_submission($confsubmissionsid);

        $decorated = decision_report::decorate_submissions($confprogramid, [$submission->id => $submission]);

        $this->assertCount(1, $decorated);
        $row = $decorated[$submission->id];
        $this->assertSame($submission->id, $row->submission->id);
        $this->assertSame(1, $row->round);
        $this->assertNull($row->latestdecision);
        $this->assertSame([], $row->reviews);
    }

    /**
     * A submission decided 'resubmit' at round 1 is decorated as round 2, with
     * latestdecision populated -- matches \mod_confprogram\local\rounds's own
     * documented round-advancement rule.
     */
    public function test_decorate_submission_after_resubmit_decision(): void {
        $this->resetAfterTest();

        [$confprogramid, $confsubmissionsid] = $this->create_confprogram();
        $submission = $this->create_submission($confsubmissionsid);
        $decider = $this->getDataGenerator()->create_user();

        api::record_decision($confprogramid, $submission->id, 'resubmit', 1, (int) $decider->id);

        $decorated = decision_report::decorate_submissions($confprogramid, [$submission->id => $submission]);

        $row = $decorated[$submission->id];
        $this->assertSame(2, $row->round);
        $this->assertNotNull($row->latestdecision);
        $this->assertSame('resubmit', $row->latestdecision->decision);
    }

    /**
     * Multiple submissions are each decorated independently, keyed by their own id.
     */
    public function test_decorate_multiple_submissions_independently(): void {
        $this->resetAfterTest();

        [$confprogramid, $confsubmissionsid] = $this->create_confprogram();
        $submission1 = $this->create_submission($confsubmissionsid);
        $submission2 = $this->create_submission($confsubmissionsid);
        $decider = $this->getDataGenerator()->create_user();

        api::record_decision($confprogramid, $submission1->id, 'accept', 1, (int) $decider->id);

        $decorated = decision_report::decorate_submissions($confprogramid, [
            $submission1->id => $submission1,
            $submission2->id => $submission2,
        ]);

        $this->assertCount(2, $decorated);
        $this->assertSame('accept', $decorated[$submission1->id]->latestdecision->decision);
        $this->assertNull($decorated[$submission2->id]->latestdecision);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /srv/lms/moodle && sudo -u www-data vendor/bin/phpunit --filter decision_report_test`
Expected: FAIL with `Class "mod_confprogram\local\decision_report" not found`

- [ ] **Step 3: Write minimal implementation**

```php
<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_confprogram\local;

use mod_confprogram\api;
use mod_confsubmissions\api as submissions_api;

/**
 * Data layer behind decisions.php's table and assign.php's "resubmitted"
 * filter mode. Kept out of both page scripts so it's independently
 * unit-testable, matching this plugin's existing display_list.php/
 * grid_data.php convention.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class decision_report {
    /**
     * Decorates each submission with its current round, latest decision, and
     * this round's completed reviews -- everything decisions.php's table needs
     * per row, computed once so callers don't repeat the round/decision/review
     * lookups themselves.
     *
     * @param int $confprogramid The confprogram instance id
     * @param array $submissions Id-keyed raw submission objects
     * @return array Id-keyed \stdClass rows: ->submission, ->round, ->latestdecision, ->reviews
     */
    public static function decorate_submissions(int $confprogramid, array $submissions): array {
        $result = [];
        foreach ($submissions as $id => $submission) {
            $round = rounds::get_current_round($confprogramid, (int) $id);
            $result[$id] = (object) [
                'submission'     => $submission,
                'round'          => $round,
                'latestdecision' => rounds::get_latest_decision($confprogramid, (int) $id),
                'reviews'        => api::get_reviews_for_round($confprogramid, (int) $id, $round),
            ];
        }
        return $result;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /srv/lms/moodle && sudo -u www-data vendor/bin/phpunit --filter decision_report_test`
Expected: `OK (3 tests, ...)` — note this task's tests only cover `decorate_submissions()`; the other three methods added in Tasks 2-4 don't exist yet, so don't add tests for them here.

- [ ] **Step 5: Commit**

```bash
cd /vagrant/moodle-dev/moodle-mod_confprogram
git add classes/local/decision_report.php tests/local/decision_report_test.php
git commit -m "Add decision_report::decorate_submissions() for the Decision report table"
```

---

### Task 2: `decision_report::filter_by_decision_status()`

**Files:**
- Modify: `/vagrant/moodle-dev/moodle-mod_confprogram/classes/local/decision_report.php`
- Test: `/vagrant/moodle-dev/moodle-mod_confprogram/tests/local/decision_report_test.php`

**Interfaces:**
- Consumes: the `array` shape `decorate_submissions()` (Task 1) produces.
- Produces: `decision_report::filter_by_decision_status(array $decorated, string $status): array`. `$status` is one of `''` (no filter), `'none'` (no decision recorded at all), or one of `'accept'|'reject'|'resubmit'|'waitlist'`. Returns the same id-keyed shape, filtered. Task 5 (decisions.php) consumes this.

- [ ] **Step 1: Write the failing test**

Add to `tests/local/decision_report_test.php`, inside the existing `decision_report_test` class:

```php
    /**
     * An empty status filters nothing -- returns every row unchanged.
     */
    public function test_filter_by_decision_status_empty_returns_all(): void {
        $this->resetAfterTest();

        [$confprogramid, $confsubmissionsid] = $this->create_confprogram();
        $submission = $this->create_submission($confsubmissionsid);
        $decorated = decision_report::decorate_submissions($confprogramid, [$submission->id => $submission]);

        $filtered = decision_report::filter_by_decision_status($decorated, '');

        $this->assertCount(1, $filtered);
    }

    /**
     * 'none' keeps only submissions with no decision recorded at all.
     */
    public function test_filter_by_decision_status_none(): void {
        $this->resetAfterTest();

        [$confprogramid, $confsubmissionsid] = $this->create_confprogram();
        $undecided = $this->create_submission($confsubmissionsid);
        $decided = $this->create_submission($confsubmissionsid);
        $decider = $this->getDataGenerator()->create_user();
        api::record_decision($confprogramid, $decided->id, 'accept', 1, (int) $decider->id);

        $decorated = decision_report::decorate_submissions($confprogramid, [
            $undecided->id => $undecided,
            $decided->id   => $decided,
        ]);

        $filtered = decision_report::filter_by_decision_status($decorated, 'none');

        $this->assertCount(1, $filtered);
        $this->assertArrayHasKey($undecided->id, $filtered);
    }

    /**
     * A real decision value keeps only submissions whose latest decision matches it.
     */
    public function test_filter_by_decision_status_specific_decision(): void {
        $this->resetAfterTest();

        [$confprogramid, $confsubmissionsid] = $this->create_confprogram();
        $accepted = $this->create_submission($confsubmissionsid);
        $rejected = $this->create_submission($confsubmissionsid);
        $decider = $this->getDataGenerator()->create_user();
        api::record_decision($confprogramid, $accepted->id, 'accept', 1, (int) $decider->id);
        api::record_decision($confprogramid, $rejected->id, 'reject', 1, (int) $decider->id);

        $decorated = decision_report::decorate_submissions($confprogramid, [
            $accepted->id => $accepted,
            $rejected->id => $rejected,
        ]);

        $filtered = decision_report::filter_by_decision_status($decorated, 'accept');

        $this->assertCount(1, $filtered);
        $this->assertArrayHasKey($accepted->id, $filtered);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /srv/lms/moodle && sudo -u www-data vendor/bin/phpunit --filter decision_report_test`
Expected: FAIL with `Call to undefined method ...\decision_report::filter_by_decision_status()`

- [ ] **Step 3: Write minimal implementation**

Add to `classes/local/decision_report.php`, inside the `decision_report` class, after `decorate_submissions()`:

```php
    /**
     * Filters an already-decorated set down to a single decision-status bucket.
     *
     * @param array $decorated The id-keyed output of decorate_submissions()
     * @param string $status '' (no filter), 'none' (no decision yet), or a decision value
     * @return array The same id-keyed shape, filtered
     */
    public static function filter_by_decision_status(array $decorated, string $status): array {
        if ($status === '') {
            return $decorated;
        }

        return array_filter($decorated, function (\stdClass $row) use ($status): bool {
            if ($status === 'none') {
                return $row->latestdecision === null;
            }
            return $row->latestdecision !== null && $row->latestdecision->decision === $status;
        });
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /srv/lms/moodle && sudo -u www-data vendor/bin/phpunit --filter decision_report_test`
Expected: `OK (6 tests, ...)`

- [ ] **Step 5: Commit**

```bash
cd /vagrant/moodle-dev/moodle-mod_confprogram
git add classes/local/decision_report.php tests/local/decision_report_test.php
git commit -m "Add decision_report::filter_by_decision_status() for the Decision report filter"
```

---

### Task 3: `decision_report::filter_resubmitted()`

**Files:**
- Modify: `/vagrant/moodle-dev/moodle-mod_confprogram/classes/local/decision_report.php`
- Test: `/vagrant/moodle-dev/moodle-mod_confprogram/tests/local/decision_report_test.php`

**Interfaces:**
- Consumes: `rounds::get_latest_decision()` (already exists).
- Produces: `decision_report::filter_resubmitted(int $confprogramid, array $submissions): array`. Unlike Task 2's filter, this operates on **raw** (non-decorated) id-keyed submissions — the exact shape `\mod_confsubmissions\api::get_submissions_for_instance()` returns — and returns that same raw shape, filtered to only those whose latest decision is `resubmit`. Both Task 5 (`decisions.php`, for the bulk-link count) and Task 6 (`assign.php`, for its new filter mode) consume this.

- [ ] **Step 1: Write the failing test**

Add to `tests/local/decision_report_test.php`:

```php
    /**
     * filter_resubmitted() keeps only submissions whose latest decision is
     * 'resubmit', operating on raw (non-decorated) submissions.
     */
    public function test_filter_resubmitted_keeps_only_resubmit_decided(): void {
        $this->resetAfterTest();

        [$confprogramid, $confsubmissionsid] = $this->create_confprogram();
        $resubmitted = $this->create_submission($confsubmissionsid);
        $accepted = $this->create_submission($confsubmissionsid);
        $undecided = $this->create_submission($confsubmissionsid);
        $decider = $this->getDataGenerator()->create_user();
        api::record_decision($confprogramid, $resubmitted->id, 'resubmit', 1, (int) $decider->id);
        api::record_decision($confprogramid, $accepted->id, 'accept', 1, (int) $decider->id);

        $filtered = decision_report::filter_resubmitted($confprogramid, [
            $resubmitted->id => $resubmitted,
            $accepted->id    => $accepted,
            $undecided->id   => $undecided,
        ]);

        $this->assertCount(1, $filtered);
        $this->assertArrayHasKey($resubmitted->id, $filtered);
        $this->assertSame($resubmitted->id, $filtered[$resubmitted->id]->id);
    }

    /**
     * No resubmit-decided submissions in the input means an empty result, not an error.
     */
    public function test_filter_resubmitted_empty_when_none_match(): void {
        $this->resetAfterTest();

        [$confprogramid, $confsubmissionsid] = $this->create_confprogram();
        $accepted = $this->create_submission($confsubmissionsid);
        $decider = $this->getDataGenerator()->create_user();
        api::record_decision($confprogramid, $accepted->id, 'accept', 1, (int) $decider->id);

        $filtered = decision_report::filter_resubmitted($confprogramid, [$accepted->id => $accepted]);

        $this->assertSame([], $filtered);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /srv/lms/moodle && sudo -u www-data vendor/bin/phpunit --filter decision_report_test`
Expected: FAIL with `Call to undefined method ...\decision_report::filter_resubmitted()`

- [ ] **Step 3: Write minimal implementation**

Add to `classes/local/decision_report.php`, after `filter_by_decision_status()`:

```php
    /**
     * Filters a raw (non-decorated) id-keyed submission set down to only
     * those whose latest decision is 'resubmit'. Shared by decisions.php's
     * "start a new round" bulk-link count and assign.php's ?resubmitted=1
     * filter mode -- both need the identical set.
     *
     * @param int $confprogramid The confprogram instance id
     * @param array $submissions Id-keyed raw submission objects
     * @return array The same id-keyed shape, filtered to resubmit-decided ones
     */
    public static function filter_resubmitted(int $confprogramid, array $submissions): array {
        $result = [];
        foreach ($submissions as $id => $submission) {
            $latest = rounds::get_latest_decision($confprogramid, (int) $id);
            if ($latest !== null && $latest->decision === 'resubmit') {
                $result[$id] = $submission;
            }
        }
        return $result;
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /srv/lms/moodle && sudo -u www-data vendor/bin/phpunit --filter decision_report_test`
Expected: `OK (8 tests, ...)`

- [ ] **Step 5: Commit**

```bash
cd /vagrant/moodle-dev/moodle-mod_confprogram
git add classes/local/decision_report.php tests/local/decision_report_test.php
git commit -m "Add decision_report::filter_resubmitted() shared by decisions.php and assign.php"
```

---

### Task 4: `decision_report::apply_bulk_decision()`

**Files:**
- Modify: `/vagrant/moodle-dev/moodle-mod_confprogram/classes/local/decision_report.php`
- Test: `/vagrant/moodle-dev/moodle-mod_confprogram/tests/local/decision_report_test.php`

**Interfaces:**
- Consumes: `\mod_confsubmissions\api::get_submission(int $submissionid): ?\stdClass` (existing, no instance scoping of its own — caller must check), `rounds::get_current_round()`, `api::record_decision(int $confprogramid, int $submissionid, string $decision, int $round, int $decidedby): int` (all existing).
- Produces: `decision_report::apply_bulk_decision(int $confprogramid, int $confsubmissionsinstanceid, array $submissionids, string $decision, array $unvettedids, int $userid): int` — returns the count of submissions actually recorded. Task 5's bulk POST handler in `decisions.php` consumes this directly.

- [ ] **Step 1: Write the failing test**

Add to `tests/local/decision_report_test.php`:

```php
    /**
     * A valid batch of submissionids all belonging to this instance, none
     * unvetted, all get a decision recorded -- and the count reflects that.
     */
    public function test_apply_bulk_decision_valid_batch(): void {
        $this->resetAfterTest();

        [$confprogramid, $confsubmissionsid] = $this->create_confprogram();
        $submission1 = $this->create_submission($confsubmissionsid);
        $submission2 = $this->create_submission($confsubmissionsid);
        $decider = $this->getDataGenerator()->create_user();

        $count = decision_report::apply_bulk_decision(
            $confprogramid,
            $confsubmissionsid,
            [$submission1->id, $submission2->id],
            'accept',
            [],
            (int) $decider->id
        );

        $this->assertSame(2, $count);
        $this->assertSame('accept', rounds::get_latest_decision($confprogramid, $submission1->id)->decision);
        $this->assertSame('accept', rounds::get_latest_decision($confprogramid, $submission2->id)->decision);
    }

    /**
     * A submissionid belonging to a DIFFERENT confsubmissions instance is
     * silently skipped, not acted on -- the same IDOR-prevention check every
     * other entry point in this plugin already applies.
     */
    public function test_apply_bulk_decision_rejects_cross_instance_submission(): void {
        $this->resetAfterTest();

        [$confprogramid, $confsubmissionsid] = $this->create_confprogram();
        [, $othersubmissionsid] = $this->create_confprogram();
        $ownsubmission = $this->create_submission($confsubmissionsid);
        $othersubmission = $this->create_submission($othersubmissionsid);
        $decider = $this->getDataGenerator()->create_user();

        $count = decision_report::apply_bulk_decision(
            $confprogramid,
            $confsubmissionsid,
            [$ownsubmission->id, $othersubmission->id],
            'accept',
            [],
            (int) $decider->id
        );

        $this->assertSame(1, $count);
        $this->assertNull(rounds::get_latest_decision($confprogramid, $othersubmission->id));
    }

    /**
     * A submissionid present in $unvettedids is silently skipped.
     */
    public function test_apply_bulk_decision_skips_unvetted(): void {
        $this->resetAfterTest();

        [$confprogramid, $confsubmissionsid] = $this->create_confprogram();
        $unvetted = $this->create_submission($confsubmissionsid);
        $vetted = $this->create_submission($confsubmissionsid);
        $decider = $this->getDataGenerator()->create_user();

        $count = decision_report::apply_bulk_decision(
            $confprogramid,
            $confsubmissionsid,
            [$unvetted->id, $vetted->id],
            'accept',
            [$unvetted->id],
            (int) $decider->id
        );

        $this->assertSame(1, $count);
        $this->assertNull(rounds::get_latest_decision($confprogramid, $unvetted->id));
    }

    /**
     * An invalid decision string is rejected outright -- zero recorded, no
     * partial processing.
     */
    public function test_apply_bulk_decision_rejects_invalid_decision(): void {
        $this->resetAfterTest();

        [$confprogramid, $confsubmissionsid] = $this->create_confprogram();
        $submission = $this->create_submission($confsubmissionsid);
        $decider = $this->getDataGenerator()->create_user();

        $count = decision_report::apply_bulk_decision(
            $confprogramid,
            $confsubmissionsid,
            [$submission->id],
            'not-a-real-decision',
            [],
            (int) $decider->id
        );

        $this->assertSame(0, $count);
        $this->assertNull(rounds::get_latest_decision($confprogramid, $submission->id));
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /srv/lms/moodle && sudo -u www-data vendor/bin/phpunit --filter decision_report_test`
Expected: FAIL with `Call to undefined method ...\decision_report::apply_bulk_decision()`

- [ ] **Step 3: Write minimal implementation**

Add to `classes/local/decision_report.php`, after `filter_resubmitted()`:

```php
    /**
     * Records the same decision for every valid submission in a batch,
     * re-verifying instance membership and unvetted-exclusion per id exactly
     * like the single-row handler in decisions.php already does. An invalid
     * id in the batch is silently skipped, not an error -- a stale or crafted
     * id must never abort the whole batch or leak which ids were valid,
     * mirroring assign.php's existing assigngroup bulk handler.
     *
     * @param int $confprogramid The confprogram instance id
     * @param int $confsubmissionsinstanceid The confsubmissions instance this confprogram vets
     * @param array $submissionids The submitted batch of ids (untrusted)
     * @param string $decision One of accept/reject/resubmit/waitlist
     * @param array $unvettedids submissionids still awaiting vetting, to exclude
     * @param int $userid The user recording the decision
     * @return int How many submissions actually got a decision recorded
     */
    public static function apply_bulk_decision(
        int $confprogramid,
        int $confsubmissionsinstanceid,
        array $submissionids,
        string $decision,
        array $unvettedids,
        int $userid
    ): int {
        if (!in_array($decision, ['accept', 'reject', 'resubmit', 'waitlist'], true)) {
            return 0;
        }

        $count = 0;
        foreach ($submissionids as $submissionid) {
            $submissionid = (int) $submissionid;

            if (in_array($submissionid, $unvettedids, true)) {
                continue;
            }

            $submission = submissions_api::get_submission($submissionid);
            if (!$submission || (int) $submission->confsubmissions !== $confsubmissionsinstanceid) {
                continue;
            }

            $round = rounds::get_current_round($confprogramid, $submissionid);
            api::record_decision($confprogramid, $submissionid, $decision, $round, $userid);
            $count++;
        }

        return $count;
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /srv/lms/moodle && sudo -u www-data vendor/bin/phpunit --filter decision_report_test`
Expected: `OK (12 tests, ...)`

- [ ] **Step 5: Commit**

```bash
cd /vagrant/moodle-dev/moodle-mod_confprogram
git add classes/local/decision_report.php tests/local/decision_report_test.php
git commit -m "Add decision_report::apply_bulk_decision() for the Decision report's bulk-apply POST handler"
```

---

### Task 5: Rewrite `decisions.php` as a filterable, bulk-capable table

**Files:**
- Modify: `/vagrant/moodle-dev/moodle-mod_confprogram/decisions.php` (full rewrite)
- Modify: `/vagrant/moodle-dev/moodle-mod_confprogram/lang/en/confprogram.php`
- Modify: `/vagrant/moodle-dev/moodle-mod_confprogram/lang/ja/confprogram.php`

**Interfaces:**
- Consumes: `decision_report::decorate_submissions()`, `::filter_by_decision_status()`, `::filter_resubmitted()`, `::apply_bulk_decision()` (Tasks 1-4); `field_formatter::get_track_pill_html(\stdClass $submission): string` (existing); `submissions_api::get_tracks(int $cmid): array` (existing); `identity::can_view_identity(\context $context): bool` (existing).
- Produces: the rendered page has a `<form id="mod_confprogram-decisions-form">` wrapping a bulk toolbar (`.mod_confprogram-apply-bulk-decision` button, a `[name=bulkdecision]` select) and the table (`.mod_confprogram-select-all` header checkbox, `.mod_confprogram-row-checkbox` per row) — Task 7's AMD module binds to exactly these selectors, so they must match verbatim.

No AMD wiring yet in this task — the bulk-apply button is a plain submit button for now (works correctly, just with no confirm dialog); Task 7 adds the confirm gate on top without changing any of these markup contracts.

- [ ] **Step 1: Add the new lang strings (EN)**

In `/vagrant/moodle-dev/moodle-mod_confprogram/lang/en/confprogram.php`, this plugin's own convention is alphabetical-by-key ordering (phpcs enforces it). Make these changes:

Remove (no longer referenced by anything after this task):
```php
$string['startnewreviewround'] = 'Start new review round';
```

Add, in alphabetical position:
```php
$string['alldecisionstatuses'] = 'All statuses';
```
(after `$string['alldayssummary']` or wherever alphabetically correct relative to existing `a*` strings — insert next to any existing `all*` string)

```php
$string['applybulkdecision'] = 'Apply to selected';
```
(near existing `assignreviewers` etc., before it alphabetically: `app` < `ass`)

```php
$string['bulkdecisionsaved'] = '{$a} decision(s) saved.';
```
(near existing `bulkassigngroup`, after it: `bulka` < `bulkd`)

```php
$string['confirmbulkdecision'] = 'Apply {$a->decision} to {$a->count} submissions?';
```
(near existing `confprogram:decide` block, alphabetically `confi` < `confp`)

```php
$string['decisionstatus'] = 'Decision status';
```
(near existing `decisionsaved`, after it: `decisionsa` < `decisionst`)

```php
$string['lastdecisioncolumn'] = 'Latest decision';
```
(near existing `lastdecision`, after it)

```php
$string['nodecisionyet'] = 'Not yet decided';
```
(near existing `noreviewsyet`, before it alphabetically: `nod` < `nor`)

```php
$string['reviews'] = 'Reviews';
```
(near existing `removereviews`, after it: `remo` < `revi`)

```php
$string['selectall'] = 'Select all';
```
(near existing `savedecision`, after it: `save` < `sele`)

```php
$string['startnewroundforresubmits'] = 'Start a new round for {$a} submission(s) awaiting one';
```
(replacing the removed `startnewreviewround` at the same alphabetical position, since `startnewreviewround` < `startnewroundforresubmits`... actually check: `startnewr` vs `startnewr` -- 'startnewreviewround' vs 'startnewroundforresubmits': compare char by char: "startnewr" common, then 'e' vs 'o' -- 'e' < 'o', so `startnewreviewround` would have sorted BEFORE this new string. Since we're removing the old one and adding this new one, just place it at the position `startnewroundforresubmits` alphabetically belongs, independent of where the old one was.)

- [ ] **Step 2: Add the matching JA strings**

In `/vagrant/moodle-dev/moodle-mod_confprogram/lang/ja/confprogram.php`, at the same relative alphabetical positions as their EN keys, remove `startnewreviewround` and add:

```php
$string['alldecisionstatuses'] = 'すべてのステータス';
$string['applybulkdecision'] = '選択した項目に適用';
$string['bulkdecisionsaved'] = '{$a} 件の決定を保存しました。';
$string['confirmbulkdecision'] = '{$a->count} 件の応募に「{$a->decision}」を適用しますか?';
$string['decisionstatus'] = '決定状況';
$string['lastdecisioncolumn'] = '最新の決定';
$string['nodecisionyet'] = '未決定';
$string['reviews'] = 'レビュー';
$string['selectall'] = 'すべて選択';
$string['startnewroundforresubmits'] = '再提出待ちの応募 {$a} 件について新しいラウンドを開始';
```

- [ ] **Step 3: Rewrite `decisions.php`**

Replace the entire file with:

```php
<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Decision report for mod_confprogram: a filterable table of non-unvetted
 * submissions with their current round's completed reviews, letting an
 * editing role record an Accept/Reject/Resubmit/Waitlist call individually
 * or in bulk across a checked selection.
 *
 * See \mod_confprogram\local\rounds for the round-derivation rules this page
 * relies on. In short: a submission whose most recent decision is 'resubmit'
 * is already logically in the next round (round+1) the moment that decision
 * is saved. This page's "Start a new round" link is purely navigational (to
 * assign.php, filtered to every resubmit-decided submission via
 * ?resubmitted=1) and does not itself change any state -- see the docblock
 * on \mod_confprogram\local\rounds for the full reasoning.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/confprogram/lib.php');

use mod_confprogram\api;
use mod_confprogram\local\decision_report;
use mod_confprogram\local\field_formatter;
use mod_confprogram\local\identity;
use mod_confprogram\local\rounds;
use mod_confsubmissions\api as submissions_api;

$id = required_param('id', PARAM_INT);
$filtertrack = optional_param('trackid', '', PARAM_INT);
$filterstatus = optional_param('decisionstatus', '', PARAM_ALPHA);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'confprogram');
$confprogram = $DB->get_record('confprogram', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/confprogram:decide', $context);

$pageurl = new moodle_url('/mod/confprogram/decisions.php', ['id' => $cm->id]);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($confprogram->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->requires->js_call_amd('mod_confprogram/decisions', 'init');

$confsubmissionscm = get_coursemodule_from_id('confsubmissions', $confprogram->confsubmissionscmid, 0, false, MUST_EXIST);

// Review-phase-only screen: block state-changing actions here too, not just rendering.
if ($confprogram->phase !== 'review') {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($confprogram->name), 2);
    echo $OUTPUT->notification(get_string('notinreviewphase', 'mod_confprogram'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$unvettedids = array_map('intval', array_column(
    $DB->get_records('confprogram_unvetted', ['confprogram' => $confprogram->id], '', 'id, submissionid'),
    'submissionid'
));

$validdecisions = ['accept', 'reject', 'resubmit', 'waitlist'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    if (optional_param('applybulkdecision', 0, PARAM_INT)) {
        $bulkdecision = optional_param('bulkdecision', '', PARAM_ALPHA);
        $submissionids = optional_param_array('submissionids', [], PARAM_INT);

        $count = decision_report::apply_bulk_decision(
            (int) $confprogram->id,
            (int) $confsubmissionscm->instance,
            $submissionids,
            $bulkdecision,
            $unvettedids,
            (int) $USER->id
        );

        redirect(
            $pageurl,
            get_string('bulkdecisionsaved', 'mod_confprogram', $count),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    $decidesubmissionid = optional_param('decidesubmissionid', 0, PARAM_INT);

    if ($decidesubmissionid && !in_array($decidesubmissionid, $unvettedids, true)) {
        $decision = optional_param('decision_' . $decidesubmissionid, '', PARAM_ALPHA);

        if (in_array($decision, $validdecisions, true)) {
            $decidesubmission = submissions_api::get_submission($decidesubmissionid);
            if ($decidesubmission && (int) $decidesubmission->confsubmissions === (int) $confsubmissionscm->instance) {
                $round = rounds::get_current_round((int) $confprogram->id, $decidesubmissionid);
                api::record_decision((int) $confprogram->id, $decidesubmissionid, $decision, $round, (int) $USER->id);
                redirect(
                    $pageurl,
                    get_string('decisionsaved', 'mod_confprogram'),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            }
        }
    }
    redirect($pageurl);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($confprogram->name), 2);
echo $OUTPUT->heading(get_string('decisionreport', 'mod_confprogram'), 3);

// Independent of any track/status filter below -- this is a call to action
// to go do a DIFFERENT task on assign.php, so it must reflect the true,
// unfiltered set, not whatever the table below currently happens to show.
$allsubmissions = submissions_api::get_submissions_for_instance($confsubmissionscm->instance);
foreach ($unvettedids as $uid) {
    unset($allsubmissions[$uid]);
}
$resubmitted = decision_report::filter_resubmitted((int) $confprogram->id, $allsubmissions);
if ($resubmitted) {
    $assignurl = new moodle_url('/mod/confprogram/assign.php', ['id' => $cm->id, 'resubmitted' => 1]);
    echo html_writer::tag('p', html_writer::link(
        $assignurl,
        get_string('startnewroundforresubmits', 'mod_confprogram', count($resubmitted)),
        ['class' => 'btn btn-outline-secondary btn-sm']
    ));
}

// Plain GET filter form: track + decision status. No JS required, matches
// assign.php's existing track-filter pattern.
echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => $pageurl->out_omit_querystring(),
    'class'  => 'form-inline mb-3',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);

$tracknames = [];
foreach (submissions_api::get_tracks($confsubmissionscm->id) as $track) {
    $tracknames[$track->id] = format_string($track->name);
}
$trackfilteroptions = ['' => get_string('alltracks', 'mod_confsubmissions')] + $tracknames;
echo html_writer::label(get_string('track', 'mod_confsubmissions'), 'menutrackid', false, ['class' => 'mr-1']);
echo html_writer::select($trackfilteroptions, 'trackid', $filtertrack, null, ['class' => 'mr-3']);

$statusfilteroptions = [
    ''         => get_string('alldecisionstatuses', 'mod_confprogram'),
    'none'     => get_string('nodecisionyet', 'mod_confprogram'),
    'accept'   => get_string('decision_accept', 'mod_confprogram'),
    'reject'   => get_string('decision_reject', 'mod_confprogram'),
    'resubmit' => get_string('decision_resubmit', 'mod_confprogram'),
    'waitlist' => get_string('decision_waitlist', 'mod_confprogram'),
];
echo html_writer::label(get_string('decisionstatus', 'mod_confprogram'), 'menudecisionstatus', false, ['class' => 'mr-1']);
echo html_writer::select($statusfilteroptions, 'decisionstatus', $filterstatus, null, ['class' => 'mr-3']);

echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('filter'), 'class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');

$filters = [];
if ($filtertrack !== '') {
    $filters['trackid'] = $filtertrack;
}
$submissions = submissions_api::get_submissions_for_instance($confsubmissionscm->instance, $filters);
foreach ($unvettedids as $uid) {
    unset($submissions[$uid]);
}

$decorated = decision_report::decorate_submissions((int) $confprogram->id, $submissions);
$decorated = decision_report::filter_by_decision_status($decorated, $filterstatus);

if (!$decorated) {
    echo $OUTPUT->notification(get_string('nosubmissionsfound', 'mod_confsubmissions'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$canviewidentity = identity::can_view_identity($context);

$decisionoptions = [];
foreach ($validdecisions as $decision) {
    $decisionoptions[$decision] = get_string('decision_' . $decision, 'mod_confprogram');
}

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $pageurl->out_omit_querystring(),
    'id'     => 'mod_confprogram-decisions-form',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

echo html_writer::start_tag('div', ['class' => 'mod_confprogram-bulk-toolbar form-inline mb-3']);
echo html_writer::select(
    $decisionoptions,
    'bulkdecision',
    '',
    ['' => get_string('makedecision', 'mod_confprogram')],
    ['class' => 'mr-2']
);
echo html_writer::tag('button', get_string('applybulkdecision', 'mod_confprogram'), [
    'type'  => 'submit',
    'name'  => 'applybulkdecision',
    'value' => 1,
    'class' => 'btn btn-primary mod_confprogram-apply-bulk-decision',
]);
echo html_writer::end_tag('div');

$table = new html_table();
$table->head = [
    html_writer::empty_tag('input', [
        'type'       => 'checkbox',
        'class'      => 'mod_confprogram-select-all',
        'aria-label' => get_string('selectall', 'mod_confprogram'),
    ]),
    get_string('title', 'mod_confsubmissions'),
    get_string('track', 'mod_confsubmissions'),
    get_string('round', 'mod_confprogram'),
    get_string('lastdecisioncolumn', 'mod_confprogram'),
    get_string('reviews', 'mod_confprogram'),
    get_string('makedecision', 'mod_confprogram'),
];
$table->attributes['class'] = 'generaltable';

foreach ($decorated as $row) {
    $submission = $row->submission;

    $decisioncell = $row->latestdecision
        ? get_string('lastdecision', 'mod_confprogram', [
            'decision' => get_string('decision_' . $row->latestdecision->decision, 'mod_confprogram'),
            'round'    => $row->latestdecision->round,
        ])
        : get_string('nodecisionyet', 'mod_confprogram');

    if ($row->reviews) {
        $lines = [];
        $anonymousindex = 1;
        foreach ($row->reviews as $review) {
            if ($canviewidentity) {
                $reviewer = \core_user::get_user($review->reviewerid);
                $reviewerlabel = $reviewer ? fullname($reviewer) : '-';
            } else {
                $reviewerlabel = get_string('anonymousreviewer', 'mod_confprogram', $anonymousindex);
                $anonymousindex++;
            }
            $lines[] = s($reviewerlabel) . ': ' . ($review->grade !== null ? format_float($review->grade, 2) : '-');
        }
        $reviewscell = implode(html_writer::empty_tag('br'), $lines);
    } else {
        $reviewscell = get_string('noreviewsyet', 'mod_confprogram');
    }

    $decisioncontrolcell = html_writer::select(
        $decisionoptions,
        'decision_' . $submission->id,
        '',
        ['' => get_string('makedecision', 'mod_confprogram')],
        ['class' => 'mr-2']
    ) . html_writer::tag('button', get_string('savedecision', 'mod_confprogram'), [
        'type'  => 'submit',
        'name'  => 'decidesubmissionid',
        'value' => $submission->id,
        'class' => 'btn btn-primary btn-sm',
    ]);

    $table->data[] = [
        html_writer::checkbox('submissionids[]', $submission->id, false, '', ['class' => 'mod_confprogram-row-checkbox']),
        format_string($submission->title),
        field_formatter::get_track_pill_html($submission),
        $row->round,
        $decisioncell,
        $reviewscell,
        $decisioncontrolcell,
    ];
}

echo html_writer::table($table);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
```

Note: `decision_' . $submission->id` as each row's per-submission `<select>` name (rather than a single shared `decision` name) mirrors this plugin's own existing `reviewerselect_' . $submission->id` convention in `assign.php` — necessary because every row now shares one `<form>`, so each row's select needs its own unique name; only the clicked submit button's own `name`/`value` (`decidesubmissionid` = this row's id) is what tells the POST handler which row's `decision_<id>` field to read.

- [ ] **Step 4: Deploy and manually smoke-test with phpcs (no AMD yet, so no rebuild needed this task)**

```bash
rsync -av --delete /vagrant/moodle-dev/moodle-mod_confprogram/ /srv/lms/moodle/public/mod/confprogram/ --exclude .git
/srv/lms/moodle/public/local/codechecker/vendor/bin/phpcs --standard=moodle /vagrant/moodle-dev/moodle-mod_confprogram/decisions.php /vagrant/moodle-dev/moodle-mod_confprogram/lang/en/confprogram.php /vagrant/moodle-dev/moodle-mod_confprogram/lang/ja/confprogram.php
```

Expected: no phpcs output (clean). Note `$PAGE->requires->js_call_amd('mod_confprogram/decisions', 'init');` references a module that doesn't exist until Task 7 — this will 404 silently in the browser console until then; that's expected and fine, don't chase it in this task.

- [ ] **Step 5: Commit**

```bash
cd /vagrant/moodle-dev/moodle-mod_confprogram
git add decisions.php lang/en/confprogram.php lang/ja/confprogram.php
git commit -m "Rewrite decisions.php as a filterable table with bulk-decision support"
```

---

### Task 6: `assign.php`'s new `?resubmitted=1` filter mode

**Files:**
- Modify: `/vagrant/moodle-dev/moodle-mod_confprogram/assign.php`
- Modify: `/vagrant/moodle-dev/moodle-mod_confprogram/lang/en/confprogram.php`
- Modify: `/vagrant/moodle-dev/moodle-mod_confprogram/lang/ja/confprogram.php`

**Interfaces:**
- Consumes: `decision_report::filter_resubmitted()` (Task 3).
- Produces: `assign.php?id=<cmid>&resubmitted=1` now works as a third, mutually-exclusive filter mode alongside the existing `?focus=` and plain-track-filter modes — nothing downstream of building `$submissions` (the bulk-assign table, the `groupreviewmode`-gated checkboxes/bulk-assign section) needs to change, since all of that already works against whatever populates `$submissions`.

- [ ] **Step 1: Add the new lang string (EN + JA)**

In `lang/en/confprogram.php`, near existing `$string['removereviews']`/before it alphabetically (`remo` < `resu`... check: `removereviews` vs `resubmittedbanner`: 'remo' < 'resu' since 'm' < 's', so `removereviews` sorts first):

```php
$string['resubmittedbanner'] = 'Now showing: submissions awaiting a new round of review.';
```

In `lang/ja/confprogram.php`, same position:

```php
$string['resubmittedbanner'] = '再提出待ちの応募のみを表示しています。';
```

- [ ] **Step 2: Read the current file to confirm exact line numbers before editing**

```bash
grep -n "optional_param('focus'\|(\$focus ? '&focus=' \. \$focus : '')\|if (\$focus)" /vagrant/moodle-dev/moodle-mod_confprogram/assign.php
```

Expected output (these are the four occurrences of the redirect-suffix ternary, plus the param declaration and the branch, that this step's edits touch):
```
44:$focus = optional_param('focus', 0, PARAM_INT);
90:    redirect($pageurl->out(false) . ($focus ? '&focus=' . $focus : ''));
103:                    redirect($pageurl->out(false) . ($focus ? '&focus=' . $focus : ''));
111:        redirect($pageurl->out(false) . ($focus ? '&focus=' . $focus : ''));
126:        redirect($pageurl->out(false) . ($focus ? '&focus=' . $focus : ''));
144:if ($focus) {
```

- [ ] **Step 3: Add the `$resubmitted` param and a combined back-url suffix**

Change:
```php
$id = required_param('id', PARAM_INT);
$filtertrack = optional_param('trackid', '', PARAM_INT);
$focus = optional_param('focus', 0, PARAM_INT);
```
to:
```php
$id = required_param('id', PARAM_INT);
$filtertrack = optional_param('trackid', '', PARAM_INT);
$focus = optional_param('focus', 0, PARAM_INT);
$resubmitted = optional_param('resubmitted', 0, PARAM_BOOL);

// Whichever of the two mutually-exclusive non-default filter modes is active
// (if any), so every POST-handling redirect below can preserve it -- otherwise
// bulk-assigning reviewers while viewing a filtered set would silently kick
// the organiser back to the unfiltered view after every single action.
$backurlsuffix = $focus ? ('&focus=' . $focus) : ($resubmitted ? '&resubmitted=1' : '');
```

- [ ] **Step 4: Replace the four redirect-suffix ternaries with `$backurlsuffix`**

Use a find-and-replace-all across the file (all four occurrences are byte-identical):

Find: `($focus ? '&focus=' . $focus : '')`
Replace with: `$backurlsuffix`

Resulting lines (for reference, don't type these individually -- the replace-all above produces them):
```php
redirect($pageurl->out(false) . $backurlsuffix);
```
(at what were lines 90, 111, 126) and:
```php
                    redirect(
                        $pageurl->out(false) . $backurlsuffix,
                        get_string('warningreviewercapreached', 'mod_confprogram'),
                        null,
                        \core\output\notification::NOTIFY_WARNING
                    );
```
(the multi-line one at what was line 102-108 -- only its own occurrence of the ternary changes, the rest of that block is untouched).

- [ ] **Step 5: Add the `resubmitted` hidden field alongside the existing `focus` one**

Change:
```php
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
if ($focus) {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'focus', 'value' => $focus]);
}
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
```
to:
```php
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
if ($focus) {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'focus', 'value' => $focus]);
} else if ($resubmitted) {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'resubmitted', 'value' => 1]);
}
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
```

- [ ] **Step 6: Add the new filter branch**

Change:
```php
if ($focus) {
    echo $OUTPUT->notification(get_string('focusedsubmission', 'mod_confprogram'), 'info');
    echo html_writer::tag('p', html_writer::link($pageurl, get_string('backtoall', 'mod_confprogram')));
    $submissions = [];
    $focussubmission = $getownsubmission($focus);
    if ($focussubmission && !in_array($focus, $unvettedids, true)) {
        $submissions[$focus] = $focussubmission;
    }
} else {
```
to:
```php
if ($focus) {
    echo $OUTPUT->notification(get_string('focusedsubmission', 'mod_confprogram'), 'info');
    echo html_writer::tag('p', html_writer::link($pageurl, get_string('backtoall', 'mod_confprogram')));
    $submissions = [];
    $focussubmission = $getownsubmission($focus);
    if ($focussubmission && !in_array($focus, $unvettedids, true)) {
        $submissions[$focus] = $focussubmission;
    }
} else if ($resubmitted) {
    echo $OUTPUT->notification(get_string('resubmittedbanner', 'mod_confprogram'), 'info');
    echo html_writer::tag('p', html_writer::link($pageurl, get_string('backtoall', 'mod_confprogram')));
    $submissions = submissions_api::get_submissions_for_instance($confsubmissionscm->instance);
    foreach ($unvettedids as $uid) {
        unset($submissions[$uid]);
    }
    $submissions = decision_report::filter_resubmitted((int) $confprogram->id, $submissions);
} else {
```

- [ ] **Step 7: Add the `decision_report` import**

Change:
```php
use mod_confprogram\api;
use mod_confprogram\local\field_formatter;
use mod_confprogram\local\reviewer_workload;
use mod_confprogram\local\rounds;
use mod_confsubmissions\api as submissions_api;
```
to:
```php
use mod_confprogram\api;
use mod_confprogram\local\decision_report;
use mod_confprogram\local\field_formatter;
use mod_confprogram\local\reviewer_workload;
use mod_confprogram\local\rounds;
use mod_confsubmissions\api as submissions_api;
```

- [ ] **Step 8: Update the file's own docblock**

Change:
```php
/**
 * Reviewer/reviewer-group assignment screen for mod_confprogram.
 *
 * Lists non-unvetted submissions from the linked mod_confsubmissions
 * instance, optionally filtered by track (or, when arriving from
 * decisions.php's "Start new review round" link, filtered to a single
 * resubmitted submission via the "focus" param). Supports assigning an
 * individual reviewer per submission, and, when groupreviewmode is on,
 * bulk-assigning a reviewer group to a checked set of submissions.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
```
to:
```php
/**
 * Reviewer/reviewer-group assignment screen for mod_confprogram.
 *
 * Lists non-unvetted submissions from the linked mod_confsubmissions
 * instance, optionally filtered by track, or by one of two mutually
 * exclusive modes: a single-submission "focus" (from an old direct link,
 * still supported), or every resubmit-decided submission at once (from
 * decisions.php's "Start a new round" bulk link, via ?resubmitted=1).
 * Supports assigning an individual reviewer per submission, and, when
 * groupreviewmode is on, bulk-assigning a reviewer group to a checked set
 * of submissions -- which is exactly what makes the ?resubmitted=1 mode
 * useful: it lands the organiser directly on that existing bulk-assign UI,
 * pre-filtered to the whole batch that needs a new round's reviewer.
 *
 * @package    mod_confprogram
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
```

- [ ] **Step 9: Deploy and verify with phpcs**

```bash
rsync -av --delete /vagrant/moodle-dev/moodle-mod_confprogram/ /srv/lms/moodle/public/mod/confprogram/ --exclude .git
/srv/lms/moodle/public/local/codechecker/vendor/bin/phpcs --standard=moodle /vagrant/moodle-dev/moodle-mod_confprogram/assign.php /vagrant/moodle-dev/moodle-mod_confprogram/lang/en/confprogram.php /vagrant/moodle-dev/moodle-mod_confprogram/lang/ja/confprogram.php
```

Expected: no phpcs output (clean).

- [ ] **Step 10: Run the full test suite to confirm nothing broke**

```bash
cd /srv/lms/moodle && sudo -u www-data vendor/bin/phpunit --testsuite mod_confprogram_testsuite
```

Expected: all tests pass (no test targets `assign.php` directly today — see Task 9 for why that's this plugin's established pattern — so this just confirms no regression in `classes/`).

- [ ] **Step 11: Commit**

```bash
cd /vagrant/moodle-dev/moodle-mod_confprogram
git add assign.php lang/en/confprogram.php lang/ja/confprogram.php
git commit -m "Add assign.php's ?resubmitted=1 filter mode for the bulk 'start a new round' link"
```

---

### Task 7: New AMD module `decisions.js` (select-all + confirm-before-submit)

**Files:**
- Create: `/vagrant/moodle-dev/moodle-mod_confprogram/amd/src/decisions.js`

**Interfaces:**
- Consumes: the exact DOM contract Task 5 built: `<form id="mod_confprogram-decisions-form">` containing `.mod_confprogram-select-all` (header checkbox), `.mod_confprogram-row-checkbox` (per-row checkboxes), `[name=bulkdecision]` (the bulk decision select), `.mod_confprogram-apply-bulk-decision` (the bulk-apply submit button).
- Produces: `init()`, called by `decisions.php`'s `$PAGE->requires->js_call_amd('mod_confprogram/decisions', 'init');` (already wired in Task 5) with no arguments.

This plugin has no AMD module today — this task establishes the `amd/src/` directory and the Grunt build for it for the first time.

- [ ] **Step 1: Write the module**

```js
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Decision report (decisions.php): a select-all checkbox for the row
 * checkboxes, and a confirm-before-submit gate on the bulk-apply button.
 * This plugin's first AMD module -- everything else in mod_confprogram is
 * plain server-rendered forms.
 *
 * @module     mod_confprogram/decisions
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';
import {getString, getStrings} from 'core/str';

/**
 * Toggles every row checkbox to match the header "select all" checkbox.
 *
 * @param {HTMLElement} root The decisions form element
 */
const wireSelectAll = (root) => {
    const selectAll = root.querySelector('.mod_confprogram-select-all');
    if (!selectAll) {
        return;
    }

    selectAll.addEventListener('change', () => {
        root.querySelectorAll('.mod_confprogram-row-checkbox').forEach((checkbox) => {
            checkbox.checked = selectAll.checked;
        });
    });
};

/**
 * Gates the bulk-apply button behind a confirm dialog naming the chosen
 * decision and how many submissions it will touch. A synthetic second click
 * (not form.submit(), so the button's own name=applybulkdecision/value=1
 * pair is still included in the POST body) re-enters this same listener and
 * lets the real submission through once confirmed.
 *
 * @param {HTMLElement} root The decisions form element
 * @param {Object} strings Preloaded {applybulkdecision, cancel} strings
 */
const wireBulkApply = (root, strings) => {
    const applyButton = root.querySelector('.mod_confprogram-apply-bulk-decision');
    if (!applyButton) {
        return;
    }

    applyButton.addEventListener('click', (event) => {
        if (applyButton.dataset.confirmed === '1') {
            applyButton.dataset.confirmed = '';
            return;
        }

        event.preventDefault();

        const decisionSelect = root.querySelector('[name=bulkdecision]');
        const checked = root.querySelectorAll('.mod_confprogram-row-checkbox:checked');

        if (!decisionSelect.value || checked.length === 0) {
            return;
        }

        const decisionLabel = decisionSelect.options[decisionSelect.selectedIndex].text;

        getString('confirmbulkdecision', 'mod_confprogram', {
            decision: decisionLabel,
            count: checked.length,
        }).then((message) => {
            Notification.confirm(
                strings.applybulkdecision,
                message,
                strings.applybulkdecision,
                strings.cancel,
                () => {
                    applyButton.dataset.confirmed = '1';
                    applyButton.click();
                }
            );
            return null;
        }).catch(Notification.exception);
    });
};

/**
 * Initialises the Decision report's select-all checkbox and bulk-apply
 * confirm dialog. Called from decisions.php.
 *
 * @return {Promise}
 */
export const init = async() => {
    const root = document.getElementById('mod_confprogram-decisions-form');
    if (!root) {
        return;
    }

    const [applybulkdecision, cancel] = await getStrings([
        {key: 'applybulkdecision', component: 'mod_confprogram'},
        {key: 'cancel', component: 'core'},
    ]);

    wireSelectAll(root);
    wireBulkApply(root, {applybulkdecision, cancel});
};
```

- [ ] **Step 2: Build the AMD module**

```bash
rsync -av --delete /vagrant/moodle-dev/moodle-mod_confprogram/ /srv/lms/moodle/public/mod/confprogram/ --exclude .git --exclude amd/build
cd /srv/lms/moodle/public/mod/confprogram
/srv/lms/moodle/node_modules/.bin/grunt amd --force --gruntfile /srv/lms/moodle/Gruntfile.js
```

Expected: `Done, but with warnings.` (the same benign phpcompatibility/eslint warnings this project's other AMD builds already produce) and a new `amd/build/decisions.min.js` + `.min.js.map` created.

- [ ] **Step 3: Copy the build back and confirm it's stable across two rebuilds**

```bash
rsync -a /srv/lms/moodle/public/mod/confprogram/amd/build/ /vagrant/moodle-dev/moodle-mod_confprogram/amd/build/
cd /srv/lms/moodle/public/mod/confprogram
cp -r amd/build /tmp/confprogram_build_check
/srv/lms/moodle/node_modules/.bin/grunt amd --force --gruntfile /srv/lms/moodle/Gruntfile.js > /dev/null 2>&1
diff -rq /tmp/confprogram_build_check amd/build/
echo "stable: $?"
rm -rf /tmp/confprogram_build_check
```

Expected: no diff output, `stable: 0`.

- [ ] **Step 4: Deploy the full plugin and purge caches**

```bash
rsync -av --delete /vagrant/moodle-dev/moodle-mod_confprogram/ /srv/lms/moodle/public/mod/confprogram/ --exclude .git
cd /srv/lms/moodle && sudo -u www-data php admin/cli/purge_caches.php
```

- [ ] **Step 5: Live Playwright verification**

Write `/tmp/claude-1000/-vagrant-moodle-dev-moodle-conference-tools/*/scratchpad/verify_decisions.py` (adjust the scratchpad path to the current session's, and the course/cmid to a demo confprogram instance in Review phase with several vetted submissions -- look one up first via `$DB->get_records('confprogram', ['phase' => 'review'])` joined to `course_modules` if unsure which cmid to use):

```python
from playwright.sync_api import sync_playwright

BASE = "https://vagrant.wisecat.net"
CMID = 11  # replace with a real confprogram cmid in Review phase

with sync_playwright() as p:
    browser = p.chromium.launch(args=["--ignore-certificate-errors"])
    context = browser.new_context(ignore_https_errors=True)
    page = context.new_page()

    page.goto(f"{BASE}/login/index.php")
    page.fill("#username", "admin")
    page.fill("#password", "Passw0rd!")
    page.click("#loginbtn")
    page.wait_for_load_state("networkidle")

    errors = []
    page.on("pageerror", lambda exc: errors.append(str(exc)))

    page.goto(f"{BASE}/mod/confprogram/decisions.php?id={CMID}")
    page.wait_for_load_state("networkidle")
    print("console/page errors on load:", errors)

    rows = page.locator(".mod_confprogram-row-checkbox")
    print("row checkboxes found:", rows.count())

    # Select-all wiring.
    select_all = page.locator(".mod_confprogram-select-all")
    select_all.check()
    page.wait_for_timeout(200)
    checked_count = page.locator(".mod_confprogram-row-checkbox:checked").count()
    print("checked after select-all:", checked_count, "of", rows.count())

    # Bulk-apply with nothing selected should no-op (uncheck all first).
    select_all.uncheck()
    page.wait_for_timeout(200)
    page.select_option("[name=bulkdecision]", "")
    page.click(".mod_confprogram-apply-bulk-decision")
    page.wait_for_timeout(300)
    print("URL after no-op click (should be unchanged, no reload):", page.url)

    # Now check one row, pick a decision, click apply -- confirm dialog should appear.
    rows.first.check()
    page.select_option("[name=bulkdecision]", "waitlist")
    page.click(".mod_confprogram-apply-bulk-decision")
    page.wait_for_timeout(500)
    confirm_text = page.locator(".modal-body").inner_text()
    print("confirm dialog text:", confirm_text)

    # Cancel it -- nothing should be submitted.
    page.click("text=Cancel")
    page.wait_for_timeout(300)
    print("URL after cancel (should still be decisions.php, no reload):", page.url)

    # Now actually confirm.
    page.click(".mod_confprogram-apply-bulk-decision")
    page.wait_for_timeout(500)
    page.click(".modal-footer .btn-primary")
    page.wait_for_load_state("networkidle")
    print("URL after confirming (should have redirected):", page.url)
    print("page errors overall:", errors)

    browser.close()
```

Run it: `/home/vagrant/.venvs/playwright/bin/python <path-to-script>`

Expected: no console errors; row checkboxes present; select-all checks every row; the no-op click makes no navigation happen; the confirm dialog text names the decision and count; Cancel leaves the page unnavigated; confirming redirects back to `decisions.php` with a success notice.

- [ ] **Step 6: Commit**

```bash
cd /vagrant/moodle-dev/moodle-mod_confprogram
git add amd/src/decisions.js amd/build/
git commit -m "Add decisions.js: select-all checkbox and confirm-before-submit for bulk decisions"
```

---

### Task 8: Version bump

**Files:**
- Modify: `/vagrant/moodle-dev/moodle-mod_confprogram/version.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: nothing new (housekeeping only) -- but required before Task 9's live verification, since this plugin's cache-purge-on-upgrade convention depends on it, and this round touched lang strings, PHP, and shipped a brand new AMD module.

- [ ] **Step 1: Read the current version**

```bash
grep 'plugin->version' /vagrant/moodle-dev/moodle-mod_confprogram/version.php
```

Expected: `$plugin->version   = 2026070603; // The current module version (Date: YYYYMMDDXX).` (or whatever it currently is -- confirm before editing, don't assume this number is still current if other work has landed since this plan was written).

- [ ] **Step 2: Bump it**

Increment the trailing two digits by one (same date, since all this round's work happens on 2026-07-06):

```php
$plugin->version   = 2026070604; // The current module version (Date: YYYYMMDDXX).
```

- [ ] **Step 3: Deploy and run the upgrade**

```bash
rsync -av --delete /vagrant/moodle-dev/moodle-mod_confprogram/ /srv/lms/moodle/public/mod/confprogram/ --exclude .git
cd /srv/lms/moodle && sudo -u www-data php admin/cli/upgrade.php --non-interactive
```

Expected: `Command line upgrade ... completed successfully.` with no schema changes listed (this bump has no accompanying `db/upgrade.php` step, since nothing in this round touches the database schema or `db/services.php`).

- [ ] **Step 4: Commit**

```bash
cd /vagrant/moodle-dev/moodle-mod_confprogram
git add version.php
git commit -m "Bump version for the bulk-decisions/filterable-report round"
```

---

### Task 9: Full regression, moodle-reviewer pass, and remaining live verification

**Files:** none new -- this task verifies Tasks 1-8's combined work and fixes whatever a reviewer or live testing turns up.

**Interfaces:** none new.

- [ ] **Step 1: Re-init PHPUnit and run the full plugin suite**

```bash
cd /srv/lms/moodle
sudo -u www-data php public/admin/tool/phpunit/cli/init.php
sudo -u www-data vendor/bin/phpunit --testsuite mod_confprogram_testsuite
```

Expected: all tests pass, including the 12 new `decision_report_test.php` tests from Tasks 1-4.

- [ ] **Step 2: Run phpcs and moodlecheck across the whole plugin**

```bash
/srv/lms/moodle/public/local/codechecker/vendor/bin/phpcs --standard=moodle /vagrant/moodle-dev/moodle-mod_confprogram
```

Expected: no output. Fix anything reported before continuing.

- [ ] **Step 3: Live Playwright verification of the remaining flows not yet covered by Task 7's script**

Extend or write a second script covering:
- The track filter and decision-status filter, individually and combined, each actually narrowing the table (compare row counts before/after applying a filter that's known to exclude at least one visible submission).
- The single-row decision select+Save still works exactly as before (pick a decision on one row, save, confirm the redirect notice and that the table now reflects it).
- The "Start a new round for N submissions" link: record a `resubmit` decision on at least one submission first, reload `decisions.php`, confirm the link appears with the correct count, click it, confirm you land on `assign.php` showing exactly the resubmit-decided submission(s) with the `resubmittedbanner` notice and a working "back to all" link, and (if `groupreviewmode` is on for the test instance) that the bulk-assign checkboxes/section are present and usable there.
- Confirm the link does NOT appear when there are zero resubmit-decided submissions (a different test instance, or after resetting the demo data).

- [ ] **Step 4: Dispatch a `moodle-reviewer` pass**

Use the Agent tool with `subagent_type: "moodle-dev:moodle-reviewer"`, scoped via `git log --oneline` to exactly this round's commits (Tasks 1-8 above), covering `classes/local/decision_report.php`, `decisions.php`, `assign.php`, `amd/src/decisions.js`, and the lang file changes. Fix any findings it reports, following this plugin's established practice from the two prior rounds today (fix real findings, defer only genuinely out-of-scope/pre-existing items with a note in `TASKLIST.md`'s "Known open items").

- [ ] **Step 5: Commit any fixes from Steps 2-4**

```bash
cd /vagrant/moodle-dev/moodle-mod_confprogram
git add -A
git commit -m "Address moodle-reviewer findings for the bulk-decisions round"
```

(Only run this if Steps 2-4 actually produced changes -- if everything was already clean, skip this commit.)

---

### Task 10: Documentation

**Files:**
- Modify: `/vagrant/moodle-dev/moodle-mod_confprogram/changelog.md`
- Modify: `/vagrant/moodle-dev/moodle-mod_confprogram/README.md`
- Modify: `/vagrant/moodle-dev/moodle-conference-tools/RELATIONS.md` (on disk only -- gitignored, don't `git add`)
- Modify: `/vagrant/moodle-dev/moodle-conference-tools/SUMMARY.md` (on disk only -- gitignored, don't `git add`)
- Modify: `/vagrant/moodle-dev/moodle-conference-tools/TASKLIST.md` (on disk only -- gitignored, don't `git add`)
- Modify: `/vagrant/moodle-dev/moodle-conference-tools/mod_confprogram.en.md` (tracked -- commit)
- Modify: `/vagrant/moodle-dev/moodle-conference-tools/mod_confprogram.ja.md` (tracked -- commit)

**Interfaces:** none -- documentation only.

- [ ] **Step 1: `changelog.md`**

Add a new entry under `## Unreleased` (read the file first to match its existing entry style and heading level exactly), describing: the table replacing the per-submission cards, the track + decision-status filters, bulk-apply with its confirm dialog (this plugin's first AMD module), and the bulk "start a new round" link replacing per-row navigation -- landing on `assign.php`'s existing bulk-assign checkboxes pre-filtered to every resubmit-decided submission. Mention test counts (12 new tests in `decision_report_test.php`).

- [ ] **Step 2: `README.md`**

Read the file first and find wherever it currently describes the Decision report / decisions.php (likely near where it describes `assign.php`'s track filter and bulk-assign). Update that description to match the new table/filter/bulk-decision behaviour and the new bulk "start a new round" link, following the same descriptive prose style as the rest of the file (see this session's earlier edits to `mod_confscheduler/README.md` for the expected tone/density).

- [ ] **Step 3: Coordination-repo docs**

In `TASKLIST.md`, add a new `## Revision round 11 -- confprogram bulk decisions + filterable Decision report (user request, 2026-07-06)` section (following the exact format of "Revision round 9"/"Revision round 10" already in that file) summarising what was built, the two real live-verification-only findings if any turned up in Task 9, and test counts.

In `SUMMARY.md`, add a matching narrative paragraph under a new "Revision round 11" heading, following the same style as the existing "Revision round 9"/"Revision round 10" entries.

In `RELATIONS.md`, note the new `decision_report` class's role only if it's referenced by (or relevant to) another plugin's documentation elsewhere in that file -- if nothing else in `RELATIONS.md` currently discusses `mod_confprogram`'s internal `classes/local/` structure, skip this rather than inventing a new cross-reference that doesn't serve a reader.

In `mod_confprogram.en.md` and `mod_confprogram.ja.md`, update whatever section currently describes the Decision report for organisers, matching the existing structure/heading style of those files (see this session's earlier edits to `mod_confscheduler.en.md`/`.ja.md` for the expected format), covering: the new table layout, the two filters, how bulk-apply works (select rows, pick a decision, confirm), and the new single "start a new round" link's behaviour.

- [ ] **Step 4: Commit the tracked files**

```bash
cd /vagrant/moodle-dev/moodle-mod_confprogram
git add changelog.md README.md
git commit -m "Document the bulk-decisions/filterable Decision report round"

cd /vagrant/moodle-dev/moodle-conference-tools
git add mod_confprogram.en.md mod_confprogram.ja.md
git commit -m "Document the confprogram bulk-decisions round in the user manual"
```

---

## Self-Review

**Spec coverage:**
- Section 1 (table layout) — Task 5.
- Section 2 (filters) — Task 5.
- Section 3 (bulk decisions, confirm dialog, new AMD module) — Tasks 4, 5, 7.
- Section 4 (bulk "start new round") — Tasks 3, 5, 6.
- Security section — Tasks 4, 6 (both re-verify instance membership/unvetted-exclusion; no new capability introduced anywhere).
- Verification plan — Tasks 6 (regression after assign.php change), 9 (full regression, moodle-reviewer, remaining live checks), 7 (live check of the new AMD behaviour specifically).
- Docs — Task 10.

No spec section is uncovered.

**Placeholder scan:** no TBD/TODO; every step has complete, runnable code or exact commands with expected output.

**Type consistency:** `decision_report::decorate_submissions()` (Task 1) → consumed by `filter_by_decision_status()` (Task 2) and `decisions.php` (Task 5) with the same `->submission`/`->round`/`->latestdecision`/`->reviews` shape throughout. `filter_resubmitted()` (Task 3) is consumed identically by both `decisions.php` (Task 5) and `assign.php` (Task 6) against the same raw id-keyed submission shape. `apply_bulk_decision()`'s six-parameter signature (Task 4) matches its one call site in `decisions.php` (Task 5) exactly. The AMD module's DOM selectors (Task 7) match the exact classes/ids `decisions.php` renders (Task 5) -- `mod_confprogram-decisions-form`, `mod_confprogram-select-all`, `mod_confprogram-row-checkbox`, `mod_confprogram-apply-bulk-decision`, `[name=bulkdecision]`.
