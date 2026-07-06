# confscheduler Day-Bounds Display Window + Print/Colour Cleanup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an organiser configure a daily display window (daystart/dayend)
for the schedule grid, make the black & white toggle apply live on screen
(not just in `@media print`), and remove the paper-size/orientation print
controls entirely in favour of the browser's own print dialog.

**Architecture:** Three independent changes to `mod_confscheduler`, each
following an existing working pattern in the same plugin (the
gapminutes/pxperhour quick-control pattern for daystart/dayend; the
room/span-block colour-theming pattern for the pill... not applicable here,
that was the other plan). Full design rationale is in
`docs/superpowers/specs/2026-07-06-confscheduler-day-bounds-print-design.md`
in this repo.

**Tech Stack:** Moodle 5.2 plugin (PHP 8.2+), PHPUnit, AMD/ES6 JS built via
`grunt amd --force`, Mustache, Playwright
(`/home/vagrant/.venvs/playwright`) against `https://vagrant.wisecat.net`
(admin/Passw0rd!).

**Prerequisite:** This plan assumes
`docs/superpowers/plans/2026-07-06-confprogram-bugfixes-plan.md` has already
been executed and its `mod_confscheduler` changes (Tasks 8-9: `trackcolour`
in `grid_data.php`, `buildTrackPill()`'s new colour parameter) are already
committed — both plans touch `grid_data.php`, `scheduler_grid.js`,
`scheduler_display.js`, and `styles.css`, and doing them in this order
avoids merge conflicts between the two rounds of work.

## Global Constraints

- Repo: `/vagrant/moodle-dev/moodle-mod_confscheduler`.
- GPL-3.0-or-later header + `@copyright 2026 Adam Jenkins <adam@wisecat.net>`
  docblock tag on every new/touched file (already present everywhere touched
  below).
- New nullable `daystart`/`dayend` columns on the `confscheduler` table
  requires an `install.xml` change, a `db/upgrade.php` step, AND a
  `version.php` bump — use the `moodle-dev:moodle-bump-version` skill for
  Task 6's version number.
- Deploy via targeted rsync only, never symlink:
  `rsync -av --delete /vagrant/moodle-dev/moodle-mod_confscheduler/ /srv/lms/moodle/public/mod/confscheduler/ --exclude .git`,
  then `sudo -u www-data php admin/cli/upgrade.php --non-interactive` from
  `/srv/lms/moodle/public`.
- `phpcs --standard=moodle` (or `moodle-dev:moodle-codestyle`) and PHPUnit
  clean before each commit. `grunt amd --force` (run from inside the
  deployed plugin directory, or the repo directory if node_modules is set up
  there — check which this project's prior AMD work actually used) rebuilt
  and diff-checked (twice, for stability) any time `amd/build/` changes.
- `moodle-reviewer` pass (scoped to this round's own commits) required
  before the final commit (Task 9).
- No PayPal/payment concerns in this plugin at all — not relevant here.

---

### Task 1: Schema — `daystart`/`dayend` columns

**Files:**
- Modify: `/vagrant/moodle-dev/moodle-mod_confscheduler/db/install.xml`
- Modify: `/vagrant/moodle-dev/moodle-mod_confscheduler/db/upgrade.php`
- Modify: `/vagrant/moodle-dev/moodle-mod_confscheduler/version.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `confscheduler.daystart`/`.dayend` (nullable `int`,
  minutes-since-midnight, e.g. `480` = 08:00) — consumed by Tasks 2-5.

- [ ] **Step 1: Add the two columns via the XMLDB editor pattern**

Since this environment doesn't have a live XMLDB editor session active,
hand-edit `db/install.xml` (this project's own established practice already
does this for small additive changes — see e.g. the `pxperhour`/
`notificationsenabled` column additions in this same file's git history)
by adding two `FIELD` elements to the `confscheduler` table definition,
immediately after the existing `pxperhour` field:

```xml
<FIELD NAME="daystart" TYPE="int" LENGTH="10" NOTNULL="false" SEQUENCE="false" COMMENT="Daily display-window start, minutes since midnight (e.g. 480 = 08:00). Null means fully automatic (derive the axis from scheduled slots, as before this feature)."/>
<FIELD NAME="dayend" TYPE="int" LENGTH="10" NOTNULL="false" SEQUENCE="false" COMMENT="Daily display-window end, minutes since midnight. Null means fully automatic, same as daystart."/>
```

- [ ] **Step 2: Bump the version and add the upgrade step**

Use the `moodle-dev:moodle-bump-version` skill against
`/vagrant/moodle-dev/moodle-mod_confscheduler` to pick the next version
number (current is `2026070611`) and have it write the matching
`db/upgrade.php` block. The block should look like:

```php
    if ($oldversion < 2026070612) {
        $table = new xmldb_table('confscheduler');

        $field = new xmldb_field('daystart', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'pxperhour');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('dayend', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'daystart');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026070612, 'confscheduler');
    }
```

(Use whatever exact version number the skill actually assigns if it differs
from `2026070612` — keep `install.xml`/`upgrade.php`/`version.php`
consistent with each other and with the skill's output, not with this
plan's placeholder number.)

- [ ] **Step 3: Deploy and confirm the upgrade runs cleanly**

```bash
rsync -av --delete /vagrant/moodle-dev/moodle-mod_confscheduler/ /srv/lms/moodle/public/mod/confscheduler/ --exclude .git
cd /srv/lms/moodle/public && sudo -u www-data php admin/cli/upgrade.php --non-interactive
```
Expected: no errors; check `mdl_confscheduler`'s columns directly to
confirm both new columns exist:
```bash
sudo -u www-data php -r '
define("CLI_SCRIPT", true);
require("/srv/lms/moodle/public/config.php");
global $DB;
$columns = $DB->get_columns("confscheduler");
echo isset($columns["daystart"]) ? "daystart OK\n" : "daystart MISSING\n";
echo isset($columns["dayend"]) ? "dayend OK\n" : "dayend MISSING\n";
'
```

- [ ] **Step 4: phpcs on the touched PHP files**

```bash
vendor/bin/phpcs --standard=moodle /vagrant/moodle-dev/moodle-mod_confscheduler/db/upgrade.php /vagrant/moodle-dev/moodle-mod_confscheduler/version.php
```
Expected: no errors. (`install.xml` isn't a phpcs target.)

- [ ] **Step 5: Commit**

```bash
cd /vagrant/moodle-dev/moodle-mod_confscheduler
git add db/install.xml db/upgrade.php version.php
git commit -m "Add nullable daystart/dayend columns for the display-window quick control

Both null (the upgrade default) means fully automatic axis behaviour,
unchanged from today, until an organiser explicitly configures a
daily display window."
```

---

### Task 2: `api::set_day_bounds()` + AJAX external function

**Files:**
- Modify: `/vagrant/moodle-dev/moodle-mod_confscheduler/classes/api.php`
- Create: `/vagrant/moodle-dev/moodle-mod_confscheduler/classes/external/set_day_bounds.php`
- Create: `/vagrant/moodle-dev/moodle-mod_confscheduler/tests/external/set_day_bounds_test.php`
- Modify: `/vagrant/moodle-dev/moodle-mod_confscheduler/db/services.php`
- Modify: `/vagrant/moodle-dev/moodle-mod_confscheduler/lang/en/confscheduler.php`
- Modify: `/vagrant/moodle-dev/moodle-mod_confscheduler/lang/ja/confscheduler.php`

**Interfaces:**
- Consumes: `confscheduler.daystart`/`.dayend` columns (Task 1).
- Produces: `api::set_day_bounds(int $confschedulerid, ?int $daystart, ?int $dayend): void`;
  AJAX endpoint `mod_confscheduler_set_day_bounds` — consumed by Task 3
  (`Repository.setDayBounds()`).

- [ ] **Step 1: Write the failing tests**

Create `tests/external/set_day_bounds_test.php`, modeled directly on the
existing `tests/external/set_pxperhour_test.php` (same `create_fixture()`
shape — copy that file's fixture method verbatim):

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

namespace mod_confscheduler\external;

use advanced_testcase;
use mod_confscheduler\api;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the set_day_bounds AJAX external function, which backs the quick
 * "day start"/"day end" display-window control at the top of the schedule
 * grid in edit mode (user feedback, 2026-07-06).
 *
 * @package    mod_confscheduler
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(set_day_bounds::class)]
final class set_day_bounds_test extends advanced_testcase {
    /**
     * Creates a full fixture.
     *
     * @return array{0: \stdClass, 1: int, 2: \stdClass} [$course, $cmid, $confscheduler]
     */
    protected function create_fixture(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();

        $confsubmissions = $this->getDataGenerator()->create_module('confsubmissions', ['course' => $course->id]);
        $confsubmissionscm = get_coursemodule_from_instance('confsubmissions', $confsubmissions->id);

        $confprogram = $this->getDataGenerator()->create_module('confprogram', [
            'course'              => $course->id,
            'confsubmissionscmid' => $confsubmissionscm->id,
        ]);
        $confprogramcm = get_coursemodule_from_instance('confprogram', $confprogram->id);

        $confschedulerrecord = $this->getDataGenerator()->create_module('confscheduler', [
            'course'          => $course->id,
            'confprogramcmid' => $confprogramcm->id,
        ]);
        $cm = get_coursemodule_from_instance('confscheduler', $confschedulerrecord->id);
        $confscheduler = $DB->get_record('confscheduler', ['id' => $confschedulerrecord->id], '*', MUST_EXIST);

        return [$course, (int) $cm->id, $confscheduler];
    }

    /**
     * An editing teacher can set both bounds together.
     */
    public function test_sets_day_bounds(): void {
        $this->resetAfterTest();
        global $DB;

        [$course, $cmid, $confscheduler] = $this->create_fixture();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $result = set_day_bounds::execute($cmid, 480, 1080);
        $this->assertTrue($result['success']);
        $this->assertEquals(480, $DB->get_field('confscheduler', 'daystart', ['id' => $confscheduler->id]));
        $this->assertEquals(1080, $DB->get_field('confscheduler', 'dayend', ['id' => $confscheduler->id]));
    }

    /**
     * Passing null for both clears back to "automatic" (the "Automatic" checkbox path).
     */
    public function test_clears_day_bounds_with_both_null(): void {
        $this->resetAfterTest();
        global $DB;

        [$course, $cmid, $confscheduler] = $this->create_fixture();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        set_day_bounds::execute($cmid, 480, 1080);
        set_day_bounds::execute($cmid, null, null);

        $this->assertNull($DB->get_field('confscheduler', 'daystart', ['id' => $confscheduler->id]));
        $this->assertNull($DB->get_field('confscheduler', 'dayend', ['id' => $confscheduler->id]));
    }

    /**
     * Exactly one of the two being null (rather than both) is rejected, and nothing changes.
     */
    public function test_rejects_exactly_one_null(): void {
        $this->resetAfterTest();
        global $DB;

        [$course, $cmid, $confscheduler] = $this->create_fixture();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $this->expectException(\invalid_parameter_exception::class);
        try {
            set_day_bounds::execute($cmid, 480, null);
        } finally {
            $this->assertNull($DB->get_field('confscheduler', 'daystart', ['id' => $confscheduler->id]));
        }
    }

    /**
     * dayend must be strictly after daystart.
     */
    public function test_rejects_dayend_not_after_daystart(): void {
        $this->resetAfterTest();
        global $DB;

        [$course, $cmid, $confscheduler] = $this->create_fixture();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $this->expectException(\invalid_parameter_exception::class);
        try {
            set_day_bounds::execute($cmid, 600, 600);
        } finally {
            $this->assertNull($DB->get_field('confscheduler', 'daystart', ['id' => $confscheduler->id]));
        }
    }

    /**
     * Values must be within a single day (0-1439 minutes).
     */
    public function test_rejects_out_of_range_values(): void {
        $this->resetAfterTest();

        [$course, $cmid] = $this->create_fixture();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $this->expectException(\invalid_parameter_exception::class);
        set_day_bounds::execute($cmid, -1, 600);
    }

    /**
     * A plain student (no manageschedule) cannot call this endpoint.
     */
    public function test_requires_manageschedule_capability(): void {
        $this->resetAfterTest();

        [$course, $cmid] = $this->create_fixture();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        set_day_bounds::execute($cmid, 480, 1080);
    }

    /**
     * The confschedulerid used is always derived from the validated cmid, never client
     * input: calling this endpoint against one course's cmid can never affect another
     * course's confscheduler instance.
     */
    public function test_only_affects_the_instance_the_cmid_belongs_to(): void {
        $this->resetAfterTest();
        global $DB;

        [$course, $cmid] = $this->create_fixture();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        [, , $otherconfscheduler] = $this->create_fixture();

        set_day_bounds::execute($cmid, 480, 1080);

        $this->assertNull($DB->get_field('confscheduler', 'daystart', ['id' => $otherconfscheduler->id]));
    }
}
```

- [ ] **Step 2: Run to verify failure**

```bash
cd /srv/lms/moodle/public
sudo -u www-data vendor/bin/phpunit mod/confscheduler/tests/external/set_day_bounds_test.php
```
Expected: FAIL — `set_day_bounds` class doesn't exist yet.

- [ ] **Step 3: Implement `api::set_day_bounds()`**

Add to `classes/api.php`, right after the existing `set_pxperhour()` method:

```php
    /**
     * Sets a confscheduler instance's daily display-window bounds (minutes since
     * midnight, e.g. 480 = 08:00), organiser-adjustable via a quick control at the top
     * of the schedule grid in edit mode -- same pattern as set_gap_minutes()/
     * set_pxperhour() above. Both null clears back to "automatic" (the previous,
     * slot-derived axis computation) -- see grid_data::build()'s docblock and
     * amd/src/day_utils.js's computeDayTimelineBounds() for how this is consumed.
     *
     * @param int $confschedulerid The confscheduler instance id
     * @param int|null $daystart The new display-window start, minutes since midnight, or null for "automatic"
     * @param int|null $dayend The new display-window end, minutes since midnight, or null for "automatic"
     * @return void
     * @throws \invalid_parameter_exception if exactly one of the two is null, either is
     *     outside [0, 1439], or dayend is not strictly after daystart
     */
    public static function set_day_bounds(int $confschedulerid, ?int $daystart, ?int $dayend): void {
        global $DB;

        if (($daystart === null) !== ($dayend === null)) {
            throw new \invalid_parameter_exception(get_string('error:invaliddaybounds', 'mod_confscheduler'));
        }

        if ($daystart !== null && $dayend !== null) {
            if ($daystart < 0 || $daystart > 1439 || $dayend < 0 || $dayend > 1439) {
                throw new \invalid_parameter_exception(get_string('error:invaliddaybounds', 'mod_confscheduler'));
            }
            if ($dayend <= $daystart) {
                throw new \invalid_parameter_exception(get_string('error:invaliddaybounds', 'mod_confscheduler'));
            }
        }

        $DB->set_field('confscheduler', 'daystart', $daystart, ['id' => $confschedulerid]);
        $DB->set_field('confscheduler', 'dayend', $dayend, ['id' => $confschedulerid]);
    }
```

- [ ] **Step 4: Add the lang strings**

`lang/en/confscheduler.php` (alphabetically):

```php
$string['daystart'] = 'Day start';
$string['daystart_help'] = 'The earliest time of day the schedule grid displays by default. Leave "Automatic" checked to size the grid from whatever is actually scheduled, as before this setting existed.';
$string['dayend'] = 'Day end';
$string['dayend_help'] = 'The latest time of day the schedule grid displays by default. A presentation scheduled outside the day start/end window is still shown in full -- the grid widens just enough to include it -- but the area outside the configured window is greyed out, the same as the existing out-of-conference-hours band.';
$string['daybounds_automatic'] = 'Automatic';
$string['error:invaliddaybounds'] = 'Day end must be after day start, both must be times of day (00:00-23:59), and both must be set together (or both left as "Automatic").';
```

`lang/ja/confscheduler.php` (matching position):

```php
$string['daystart'] = '表示開始時刻';
$string['daystart_help'] = 'スケジュールグリッドが既定で表示する最も早い時刻です。「自動」のままにすると、この設定が追加される前と同様に、実際にスケジュールされている内容から表示範囲が決まります。';
$string['dayend'] = '表示終了時刻';
$string['dayend_help'] = 'スケジュールグリッドが既定で表示する最も遅い時刻です。表示開始・終了時刻の範囲外にスケジュールされた発表も全体が表示されます(グリッドがそれを含むように広がります)が、設定範囲外の部分は既存の「会議時間外」の帯と同様にグレー表示されます。';
$string['daybounds_automatic'] = '自動';
$string['error:invaliddaybounds'] = '表示終了時刻は表示開始時刻より後にしてください。両方とも時刻(00:00〜23:59)で指定し、両方をまとめて設定するか、両方とも「自動」のままにしてください。';
```

- [ ] **Step 5: Create the external function**

Create `classes/external/set_day_bounds.php`, modeled directly on
`set_pxperhour.php`:

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

namespace mod_confscheduler\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_confscheduler\api;

/**
 * AJAX-only external function that sets a confscheduler instance's daily
 * display-window bounds (minutes since midnight), called from the quick
 * control at the top of the schedule grid in edit mode (user feedback,
 * 2026-07-06). Follows the exact same pattern as set_gap_minutes.php/
 * set_pxperhour.php.
 *
 * @package    mod_confscheduler
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_day_bounds extends external_api {
    use scheduler_context_trait;

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'     => new external_value(PARAM_INT, 'The confscheduler course-module id'),
            'daystart' => new external_value(PARAM_INT, 'Display-window start, minutes since midnight', VALUE_DEFAULT, null, NULL_ALLOWED),
            'dayend'   => new external_value(PARAM_INT, 'Display-window end, minutes since midnight', VALUE_DEFAULT, null, NULL_ALLOWED),
        ]);
    }

    /**
     * Sets the instance's display-window bounds.
     *
     * @param int $cmid The confscheduler course-module id
     * @param int|null $daystart Display-window start, minutes since midnight, or null for "automatic"
     * @param int|null $dayend Display-window end, minutes since midnight, or null for "automatic"
     * @return array{success: bool}
     */
    public static function execute(int $cmid, ?int $daystart, ?int $dayend): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid'     => $cmid,
            'daystart' => $daystart,
            'dayend'   => $dayend,
        ]);

        [, , $confscheduler] = self::require_manage($params['cmid']);

        api::set_day_bounds((int) $confscheduler->id, $params['daystart'], $params['dayend']);

        return ['success' => true];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the update succeeded'),
        ]);
    }
}
```

- [ ] **Step 6: Register the service**

Add to `db/services.php`, right after the existing
`mod_confscheduler_set_pxperhour` entry:

```php
    'mod_confscheduler_set_day_bounds' => [
        'classname'    => 'mod_confscheduler\external\set_day_bounds',
        'description'  => 'Sets a confscheduler instance\'s daily display-window bounds, in minutes since midnight.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'mod/confscheduler:manageschedule',
    ],
```

- [ ] **Step 7: Run the tests to verify they pass**

```bash
cd /srv/lms/moodle/public
sudo -u www-data vendor/bin/phpunit mod/confscheduler/tests/external/set_day_bounds_test.php
```
Expected: all 7 tests PASS.

- [ ] **Step 8: phpcs**

```bash
vendor/bin/phpcs --standard=moodle /vagrant/moodle-dev/moodle-mod_confscheduler/classes/api.php /vagrant/moodle-dev/moodle-mod_confscheduler/classes/external/set_day_bounds.php /vagrant/moodle-dev/moodle-mod_confscheduler/db/services.php /vagrant/moodle-dev/moodle-mod_confscheduler/lang/en/confscheduler.php /vagrant/moodle-dev/moodle-mod_confscheduler/lang/ja/confscheduler.php
```
Expected: no errors.

- [ ] **Step 9: Commit**

```bash
cd /vagrant/moodle-dev/moodle-mod_confscheduler
git add classes/api.php classes/external/set_day_bounds.php tests/external/set_day_bounds_test.php db/services.php lang/en/confscheduler.php lang/ja/confscheduler.php
git commit -m "Add api::set_day_bounds() and its AJAX endpoint

Same quick-control pattern as set_gap_minutes()/set_pxperhour(): both
null clears back to fully-automatic axis behaviour; both set requires
dayend strictly after daystart, within a single day."
```

---

### Task 3: `grid_data.php` returns `daystart`/`dayend`

**Files:**
- Modify: `/vagrant/moodle-dev/moodle-mod_confscheduler/classes/local/grid_data.php`
- Modify: `/vagrant/moodle-dev/moodle-mod_confscheduler/tests/external/get_grid_data_test.php`

**Interfaces:**
- Consumes: `confscheduler.daystart`/`.dayend` (Task 1).
- Produces: `grid_data::build()`'s return array gains `daystart`/`dayend`
  (nullable ints) — consumed by Task 5 (`Repository`/client state) and
  Task 4 (`day_utils.js` functions read them from client state, not
  directly from this payload, but this is where they enter the client).

- [ ] **Step 1: Write the failing test**

Add to `tests/external/get_grid_data_test.php`:

```php
    /**
     * A fresh instance has null daystart/dayend (fully automatic, unchanged from
     * before this feature existed) until an organiser configures them.
     */
    public function test_payload_includes_null_day_bounds_by_default(): void {
        $this->resetAfterTest();

        [$course, $cmid] = $this->create_fixture();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $result = get_grid_data::execute($cmid);

        $this->assertNull($result['daystart']);
        $this->assertNull($result['dayend']);
    }

    /**
     * Once configured, both bounds are surfaced in the payload.
     */
    public function test_payload_includes_configured_day_bounds(): void {
        $this->resetAfterTest();

        [$course, $cmid, $confscheduler] = $this->create_fixture();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        api::set_day_bounds((int) $confscheduler->id, 480, 1080);

        $result = get_grid_data::execute($cmid);

        $this->assertSame(480, $result['daystart']);
        $this->assertSame(1080, $result['dayend']);
    }
```

- [ ] **Step 2: Run to verify failure**

```bash
cd /srv/lms/moodle/public
sudo -u www-data vendor/bin/phpunit --filter day_bounds mod/confscheduler/tests/external/get_grid_data_test.php
```
Expected: FAIL — `daystart`/`dayend` keys don't exist in the payload yet.

- [ ] **Step 3: Implement**

In `classes/local/grid_data.php`, add to the returned array (right after
the existing `'conferenceend' => ...` line):

```php
            'daystart'        => $confscheduler->daystart !== null ? (int) $confscheduler->daystart : null,
            'dayend'          => $confscheduler->dayend !== null ? (int) $confscheduler->dayend : null,
```

Update the class's `build()` docblock `@return` shape comment to mention
these two new keys, next to the existing `conferencestart`/`conferenceend`
mention.

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
git commit -m "Surface daystart/dayend in the grid_data payload"
```

---

### Task 4: Shared `day_utils.js` timeline/band computation

**Files:**
- Modify: `/vagrant/moodle-dev/moodle-mod_confscheduler/amd/src/day_utils.js`

**Interfaces:**
- Consumes: nothing new (pure functions of their arguments, as every
  function in this module already is).
- Produces: `computeDayTimelineBounds(slots, fallbackDayKey, daystartminutes, dayendminutes): {start, end}`
  and an updated `outOfHoursBands(dayKey, timelineStart, timelineEnd, conferencestart, conferenceend, daystartminutes, dayendminutes): {start, end}[]`
  — consumed by Task 5 (`scheduler_grid.js`, `scheduler_display.js`).

- [ ] **Step 1: Add `computeDayTimelineBounds()`**

Add this new exported function to `day_utils.js`, right after the existing
`dayBounds()` function:

```js
/**
 * Computes the vertical time range to render for one day, given the instance's
 * configured daily display-window bounds (user feedback, 2026-07-06) and that day's
 * own scheduled slots. This is the single shared implementation behind what were
 * previously two near-identical copies: amd/src/scheduler_grid.js's
 * computeTimelineBounds() and amd/src/scheduler_display.js's computeDayTimeRange().
 *
 * When daystartminutes/dayendminutes are BOTH set, the default axis for the day is
 * exactly [daystart, dayend] -- no padding or hour-rounding, since an organiser's
 * chosen times are already exact clock times. If a real scheduled slot falls outside
 * that window, the axis quietly widens just enough to include it in full (never hides
 * real data, matching this project's "never hide existing data" convention elsewhere,
 * e.g. the day selector always including a day with an existing slot even outside the
 * conference date range) -- see outOfHoursBands() below for how that widened sliver is
 * visually greyed to show it's outside the normal window.
 *
 * When EITHER is null (the default before this feature is configured, or the
 * "Automatic" checkbox), behaviour is completely unchanged from before this feature
 * existed: the axis is derived purely from the day's own slots (padded 30 minutes,
 * rounded to whole hours, 8-hour minimum), defaulting to 08:00-18:00 local when the day
 * has no slots at all.
 *
 * @param {Object[]} slots Slots to derive the range from (each with starttime/endtime)
 * @param {String} fallbackDayKey The day (YYYY-MM-DD) being rendered -- used both as the
 *     "no slots" fallback anchor and as the day the daystart/dayend minutes are applied to
 * @param {Number|null} daystartminutes Display-window start, minutes since midnight, or null/undefined for "automatic"
 * @param {Number|null} dayendminutes Display-window end, minutes since midnight, or null/undefined for "automatic"
 * @return {{start: Number, end: Number}}
 */
export const computeDayTimelineBounds = (slots, fallbackDayKey, daystartminutes, dayendminutes) => {
    const times = [];
    slots.forEach((slot) => {
        times.push(slot.starttime);
        times.push(slot.endtime);
    });

    const bothConfigured = daystartminutes !== null && daystartminutes !== undefined
        && dayendminutes !== null && dayendminutes !== undefined;

    if (bothConfigured) {
        const dayStartOfDay = dayBounds(fallbackDayKey).start;
        const configuredStart = dayStartOfDay + (daystartminutes * 60);
        const configuredEnd = dayStartOfDay + (dayendminutes * 60);

        return {
            start: times.length ? Math.min(configuredStart, ...times) : configuredStart,
            end: times.length ? Math.max(configuredEnd, ...times) : configuredEnd,
        };
    }

    let start;
    let end;
    if (times.length) {
        start = Math.min(...times);
        end = Math.max(...times);
    } else {
        start = dayBounds(fallbackDayKey).start + (8 * 3600);
        end = start + (10 * 3600);
    }

    start = (Math.floor(start / 3600) * 3600) - 1800;
    end = (Math.ceil(end / 3600) * 3600) + 1800;
    if (end - start < 8 * 3600) {
        end = start + (8 * 3600);
    }

    return {start, end};
};
```

- [ ] **Step 2: Extend `outOfHoursBands()`**

Replace the existing function:

```js
export const outOfHoursBands = (dayKey, timelineStart, timelineEnd, conferencestart, conferenceend) => {
    if (!conferencestart || !conferenceend) {
        return [];
    }

    const {start: dayStart, end: dayEnd} = dayBounds(dayKey);
    const validStart = Math.max(dayStart, conferencestart);
    const validEnd = Math.min(dayEnd, conferenceend);

    if (validStart >= validEnd) {
        // This day is entirely outside the conference range: grey the whole table.
        return [{start: timelineStart, end: timelineEnd}];
    }

    const bands = [];
    if (timelineStart < validStart) {
        bands.push({start: timelineStart, end: Math.min(validStart, timelineEnd)});
    }
    if (timelineEnd > validEnd) {
        bands.push({start: Math.max(validEnd, timelineStart), end: timelineEnd});
    }
    return bands;
};
```

with (adds the daystart/dayend bands as a second, independent source of
grey bands -- orthogonal to the existing conference-date-range bands, since
one is about which CALENDAR DAYS are valid and the other is about
TIME-OF-DAY within an already-valid day; only reached when the day isn't
already entirely grey from the conference-range check):

```js
export const outOfHoursBands = (
    dayKey,
    timelineStart,
    timelineEnd,
    conferencestart,
    conferenceend,
    daystartminutes,
    dayendminutes
) => {
    const bands = [];

    if (conferencestart && conferenceend) {
        const {start: dayStart, end: dayEnd} = dayBounds(dayKey);
        const validStart = Math.max(dayStart, conferencestart);
        const validEnd = Math.min(dayEnd, conferenceend);

        if (validStart >= validEnd) {
            // This day is entirely outside the conference range: grey the whole table,
            // and skip the daystart/dayend check below entirely -- there's nothing left
            // to distinguish within an already-fully-greyed day.
            return [{start: timelineStart, end: timelineEnd}];
        }

        if (timelineStart < validStart) {
            bands.push({start: timelineStart, end: Math.min(validStart, timelineEnd)});
        }
        if (timelineEnd > validEnd) {
            bands.push({start: Math.max(validEnd, timelineStart), end: timelineEnd});
        }
    }

    const bothConfigured = daystartminutes !== null && daystartminutes !== undefined
        && dayendminutes !== null && dayendminutes !== undefined;
    if (bothConfigured) {
        const dayStartOfDay = dayBounds(dayKey).start;
        const configuredStart = dayStartOfDay + (daystartminutes * 60);
        const configuredEnd = dayStartOfDay + (dayendminutes * 60);

        if (timelineStart < configuredStart) {
            bands.push({start: timelineStart, end: Math.min(configuredStart, timelineEnd)});
        }
        if (timelineEnd > configuredEnd) {
            bands.push({start: Math.max(configuredEnd, timelineStart), end: timelineEnd});
        }
    }

    return bands;
};
```

Update this function's own docblock to describe the new parameters
(mirroring the existing docblock's style), noting that a day with no
scheduled slot outside the configured window produces zero daystart/dayend
bands (the axis IS the window in that common case, so there's nothing extra
to greary).

- [ ] **Step 3: Deploy and unit-verify via the browser console (no PHPUnit harness for pure JS in this project)**

This project has no JS unit-test framework (confirmed absent from every
prior phase's verification — always live Playwright/manual browser
verification for AMD modules instead). Defer full verification to Task 5's
live Playwright check, once these functions have real call sites wired up;
this task's own commit is a pure addition with no behavioural change yet
(nothing calls the new/changed functions until Task 5).

- [ ] **Step 4: Commit**

```bash
cd /vagrant/moodle-dev/moodle-mod_confscheduler
git add amd/src/day_utils.js
git commit -m "Add shared computeDayTimelineBounds() and extend outOfHoursBands()

Folds what were two near-identical per-file timeline-bounds functions
(scheduler_grid.js's computeTimelineBounds(), scheduler_display.js's
computeDayTimeRange()) into one shared, day_utils.js implementation
that also understands the new daystart/dayend display window -- not
yet wired into either caller (Task 5)."
```

---

### Task 5: Wire daystart/dayend into `scheduler_grid.js` and `scheduler_display.js`

**Files:**
- Modify: `/vagrant/moodle-dev/moodle-mod_confscheduler/amd/src/scheduler_grid.js`
- Modify: `/vagrant/moodle-dev/moodle-mod_confscheduler/amd/src/scheduler_display.js`
- Modify: `/vagrant/moodle-dev/moodle-mod_confscheduler/amd/src/repository.js`
- Modify: `/vagrant/moodle-dev/moodle-mod_confscheduler/templates/grid.mustache`

**Interfaces:**
- Consumes: `DayUtils.computeDayTimelineBounds()`/`DayUtils.outOfHoursBands()`
  (Task 4); `payload.daystart`/`.dayend` from `mod_confscheduler_get_grid_data`
  (Task 3); `Repository.setDayBounds(cmid, daystart, dayend)` (new, this task).
- Produces: nothing other tasks depend on.

- [ ] **Step 1: `repository.js` — add `setDayBounds()`**

Add right after the existing `setPxPerHour` export:

```js
/**
 * Sets a confscheduler instance's daily display-window bounds, in minutes since
 * midnight. Backs the quick control at the top of the schedule grid in edit mode
 * (user feedback, 2026-07-06). Pass null for both to clear back to "automatic".
 *
 * @param {Number} cmid
 * @param {Number|null} daystart
 * @param {Number|null} dayend
 * @return {Promise}
 */
export const setDayBounds = (cmid, daystart, dayend) => Ajax.call([{
    methodname: 'mod_confscheduler_set_day_bounds',
    args: {cmid, daystart, dayend},
}])[0];
```

- [ ] **Step 2: `scheduler_grid.js` — state, payload consumption, and the two `computeTimelineBounds()`/`outOfHoursBands()` call sites**

Remove the local `computeTimelineBounds()` function (lines 267-304) entirely
— it's replaced by `DayUtils.computeDayTimelineBounds()`.

Replace `computeTimeline()`:

```js
const computeTimeline = (state) => {
    const fallbackKey = (state.selectedDay && state.selectedDay !== DayUtils.ALL_DAYS)
        ? state.selectedDay
        : DayUtils.dayKeyForTimestamp(Math.floor(Date.now() / 1000));
    const bounds = computeTimelineBounds(state.slots, fallbackKey);
    state.timelineStart = bounds.start;
    state.timelineEnd = bounds.end;
};
```

with:

```js
const computeTimeline = (state) => {
    const fallbackKey = (state.selectedDay && state.selectedDay !== DayUtils.ALL_DAYS)
        ? state.selectedDay
        : DayUtils.dayKeyForTimestamp(Math.floor(Date.now() / 1000));
    const bounds = DayUtils.computeDayTimelineBounds(state.slots, fallbackKey, state.daystart, state.dayend);
    state.timelineStart = bounds.start;
    state.timelineEnd = bounds.end;
};
```

In `renderAllDaysBody()`, replace:

```js
        const daySlots = state.slotsByDay[dayKey] || [];
        const bounds = computeTimelineBounds(daySlots, dayKey);
```

with:

```js
        const daySlots = state.slotsByDay[dayKey] || [];
        const bounds = DayUtils.computeDayTimelineBounds(daySlots, dayKey, state.daystart, state.dayend);
```

In `renderOutOfHoursBands()`, replace:

```js
    const bands = DayUtils.outOfHoursBands(
        dayKey,
        state.timelineStart,
        state.timelineEnd,
        state.conferencestart,
        state.conferenceend
    );
```

with:

```js
    const bands = DayUtils.outOfHoursBands(
        dayKey,
        state.timelineStart,
        state.timelineEnd,
        state.conferencestart,
        state.conferenceend,
        state.daystart,
        state.dayend
    );
```

Add `daystart: null, dayend: null,` to the initial state object literal
(right after the existing `conferenceend: null,` line).

In BOTH `fetchAndRenderAll()` and its sibling refetch function (the one
around line 371 that also calls `syncGapMinutesInput`/`syncPxPerHourInput`
— this file has two separate refetch functions per the earlier
investigation, one for full refresh and one for post-mutation refresh),
add, right after the existing `state.conferenceend = data.conferenceend;`
line in each:

```js
    state.daystart = data.daystart;
    state.dayend = data.dayend;
```

and add a call to a new `syncDayBoundsInputs(state);` right next to the
existing `syncGapMinutesInput(state); syncPxPerHourInput(state);` pair in
BOTH functions.

- [ ] **Step 3: `scheduler_grid.js` — the quick-control inputs**

Add this new function right after the existing `syncPxPerHourInput()`:

```js
/**
 * Reflects state.daystart/state.dayend into the quick display-window control's two
 * time inputs and its "Automatic" checkbox, without disturbing whichever one currently
 * has focus -- mirrors syncGapMinutesInput()/syncPxPerHourInput(). The two time inputs
 * are disabled whenever "Automatic" is checked (both state values are null).
 *
 * @param {Object} state The module state object
 */
const syncDayBoundsInputs = (state) => {
    const automaticCheckbox = state.root.querySelector('.mod_confscheduler-daybounds-automatic');
    const startInput = state.root.querySelector('.mod_confscheduler-daystart');
    const endInput = state.root.querySelector('.mod_confscheduler-dayend');
    if (!automaticCheckbox || !startInput || !endInput) {
        return;
    }

    const isAutomatic = state.daystart === null || state.dayend === null;
    if (document.activeElement !== automaticCheckbox) {
        automaticCheckbox.checked = isAutomatic;
    }
    startInput.disabled = isAutomatic;
    endInput.disabled = isAutomatic;

    if (document.activeElement !== startInput) {
        startInput.value = isAutomatic ? '' : minutesToTimeValue(state.daystart);
    }
    if (document.activeElement !== endInput) {
        endInput.value = isAutomatic ? '' : minutesToTimeValue(state.dayend);
    }
};

/**
 * Converts minutes-since-midnight to an <input type="time"> value string (HH:MM).
 *
 * @param {Number} minutes
 * @return {String}
 */
const minutesToTimeValue = (minutes) => {
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return `${String(hours).padStart(2, '0')}:${String(mins).padStart(2, '0')}`;
};

/**
 * Converts an <input type="time"> value string (HH:MM) to minutes-since-midnight.
 *
 * @param {String} value
 * @return {Number|null} null if $value is empty/unparseable
 */
const timeValueToMinutes = (value) => {
    const match = (/^(\d{1,2}):(\d{2})$/).exec(value);
    if (!match) {
        return null;
    }
    const hours = parseInt(match[1], 10);
    const mins = parseInt(match[2], 10);
    if (hours < 0 || hours > 23 || mins < 0 || mins > 59) {
        return null;
    }
    return (hours * 60) + mins;
};

/**
 * Persists a change to the quick display-window control (user feedback, 2026-07-06):
 * either the "Automatic" checkbox (clears both to null), or one of the two time inputs
 * (only submitted once BOTH have a valid value -- an incomplete pair is left unsent
 * until the second field is also filled in). Mirrors onGapMinutesChange()/
 * onPxPerHourChange()'s optimistic-update-then-revert-on-failure shape.
 *
 * @param {Object} state The module state object
 */
const onDayBoundsChange = (state) => {
    const automaticCheckbox = state.root.querySelector('.mod_confscheduler-daybounds-automatic');
    const startInput = state.root.querySelector('.mod_confscheduler-daystart');
    const endInput = state.root.querySelector('.mod_confscheduler-dayend');

    const previousStart = state.daystart;
    const previousEnd = state.dayend;

    let newStart = null;
    let newEnd = null;
    if (!automaticCheckbox.checked) {
        newStart = timeValueToMinutes(startInput.value);
        newEnd = timeValueToMinutes(endInput.value);
        if (newStart === null || newEnd === null || newEnd <= newStart) {
            // Incomplete or invalid pair (e.g. only one field filled in so far, or end
            // not after start) -- wait for a valid pair rather than submitting a value
            // the server would reject anyway.
            return;
        }
    }

    state.daystart = newStart;
    state.dayend = newEnd;
    startInput.disabled = true;
    endInput.disabled = true;
    renderGridBody(state);
    Promise.resolve(Repository.setDayBounds(state.cmid, newStart, newEnd)).catch((error) => {
        state.daystart = previousStart;
        state.dayend = previousEnd;
        renderGridBody(state);
        Notification.exception(error);
    }).finally(() => {
        syncDayBoundsInputs(state);
    });
};
```

Wire it into the existing delegated `change` listener (right after the
existing `pxPerHourInput` block):

```js
        const pxPerHourInput = event.target.closest('.mod_confscheduler-pxperhour');
        if (pxPerHourInput) {
            onPxPerHourChange(state, pxPerHourInput);
            return;
        }

        const daybounds = event.target.closest(
            '.mod_confscheduler-daybounds-automatic, .mod_confscheduler-daystart, .mod_confscheduler-dayend'
        );
        if (daybounds) {
            onDayBoundsChange(state);
        }
    });
```

(Note: this adds a `return;` after the existing `onPxPerHourChange(state,
pxPerHourInput);` call, which the current code doesn't have since it's the
last check in the chain today — safe, since there's nothing after it in the
existing listener before this change.)

- [ ] **Step 4: `grid.mustache` — the toolbar controls**

Add to `templates/grid.mustache`, right after the existing `pxperhour`
input block (inside the same `{{#canmanage}}` section):

```mustache
            <label class="mx-2 mb-0" for="mod_confscheduler-daybounds-automatic">
                <input
                    type="checkbox"
                    id="mod_confscheduler-daybounds-automatic"
                    class="mod_confscheduler-daybounds-automatic"
                    checked
                >
                {{#str}} daybounds_automatic, mod_confscheduler {{/str}}
            </label>
            <label class="mx-2 mb-0" for="mod_confscheduler-daystart">{{#str}} daystart, mod_confscheduler {{/str}}</label>
            <input
                type="time"
                id="mod_confscheduler-daystart"
                class="mod_confscheduler-daystart form-control form-control-sm"
                style="width: 6em;"
                disabled
                title="{{#str}} daystart_help, mod_confscheduler {{/str}}"
            >
            <label class="mx-2 mb-0" for="mod_confscheduler-dayend">{{#str}} dayend, mod_confscheduler {{/str}}</label>
            <input
                type="time"
                id="mod_confscheduler-dayend"
                class="mod_confscheduler-dayend form-control form-control-sm"
                style="width: 6em;"
                disabled
                title="{{#str}} dayend_help, mod_confscheduler {{/str}}"
            >
```

- [ ] **Step 5: `scheduler_display.js` — consume daystart/dayend (read-only, no UI)**

Add `daystart: null, dayend: null,` to this file's own state object literal
too (right after its existing `conferenceend: null,` line), and in
`fetchAndRenderAll()`, right after the existing
`state.conferenceend = data.conferenceend;` line, add:

```js
    state.daystart = data.daystart;
    state.dayend = data.dayend;
```

Remove the local `computeDayTimeRange()` function entirely (it's replaced
by the shared `DayUtils.computeDayTimelineBounds()`). In `buildDayGridInto()`,
replace:

```js
    const range = computeDayTimeRange(slots, dayKey);
```

with:

```js
    const range = DayUtils.computeDayTimelineBounds(slots, dayKey, state.daystart, state.dayend);
```

And in the same function, replace the existing `outOfHoursBands()` call:

```js
    const bands = DayUtils.outOfHoursBands(dayKey, range.start, range.end, state.conferencestart, state.conferenceend);
```

with:

```js
    const bands = DayUtils.outOfHoursBands(
        dayKey,
        range.start,
        range.end,
        state.conferencestart,
        state.conferenceend,
        state.daystart,
        state.dayend
    );
```

- [ ] **Step 6: Add the new lang strings' help-attribute usage doesn't need anything further — rebuild AMD**

```bash
cd /vagrant/moodle-dev/moodle-mod_confscheduler
git status amd/build/
grunt amd --force
git diff --stat amd/build/
grunt amd --force
git diff --stat amd/build/
```
Expected: first rebuild shows changes to `scheduler_grid.min.js`/
`scheduler_display.min.js`/`day_utils.min.js`/`repository.min.js` (and
their `.map` files); the SECOND rebuild's `git diff --stat` shows NO
further changes (stable), per this project's standing rule.

- [ ] **Step 7: Deploy and verify live**

```bash
rsync -av --delete /vagrant/moodle-dev/moodle-mod_confscheduler/ /srv/lms/moodle/public/mod/confscheduler/ --exclude .git
cd /srv/lms/moodle/public && sudo -u www-data php admin/cli/upgrade.php --non-interactive
```

Via Playwright, in edit mode: confirm the "Automatic"/day-start/day-end
controls render in the toolbar, start disabled with "Automatic" checked;
uncheck it, fill both times (e.g. 08:00/18:00), confirm the grid's axis
changes to that window and a page reload preserves the setting; drag a
block to a time outside the configured window (or use an existing
out-of-window slot) and confirm the axis widens to show it with a grey band
over the out-of-window portion; re-check "Automatic" and confirm it reverts
to the pre-existing slot-derived behaviour. Repeat viewing the SAME
instance in read-only Display mode and "All days" view, confirming
identical axis/greying with no editable controls present.

- [ ] **Step 8: Full PHPUnit run + phpcs (no PHP changed this task, but confirm no regression from earlier tasks in this plan)**

```bash
cd /srv/lms/moodle/public
sudo -u www-data vendor/bin/phpunit --testsuite mod_confscheduler_testsuite
```
Expected: all passing.

- [ ] **Step 9: Commit**

```bash
cd /vagrant/moodle-dev/moodle-mod_confscheduler
git add amd/src/scheduler_grid.js amd/src/scheduler_display.js amd/src/repository.js amd/build/ templates/grid.mustache
git commit -m "Wire the daystart/dayend display window into both grid modes

New toolbar quick control (Automatic checkbox + two time inputs) in
edit mode, following the exact gapminutes/pxperhour pattern; both edit
and read-only Display mode (including 'All days') now derive their
axis from DayUtils.computeDayTimelineBounds() instead of each file's
own now-removed duplicate."
```

---

### Task 6: Black & white becomes a live on-screen toggle

**Files:**
- Modify: `/vagrant/moodle-dev/moodle-mod_confscheduler/styles.css`
- Modify: `/vagrant/moodle-dev/moodle-mod_confscheduler/templates/display.mustache`
- Modify: `/vagrant/moodle-dev/moodle-mod_confscheduler/amd/src/scheduler_display.js`
- Modify: `/vagrant/moodle-dev/moodle-mod_confscheduler/lang/en/confscheduler.php`
- Modify: `/vagrant/moodle-dev/moodle-mod_confscheduler/lang/ja/confscheduler.php`

**Interfaces:**
- Consumes: nothing from other tasks in this plan.
- Produces: nothing other tasks depend on.

- [ ] **Step 1: Move the B&W CSS out of `@media print` and rename the class**

In `styles.css`, replace the existing block:

```css
@media print {
    /* Compound selector (two classes) deliberately, not just .mod_confscheduler-print-toolbar:
       the toolbar also carries Bootstrap's .d-flex utility class, whose own
       "display: flex !important" rule has equal specificity to a single-class selector here
       and would otherwise win the cascade tiebreak by simply being loaded later in the
       aggregated theme CSS. Two classes gives this rule strictly higher specificity, so it
       wins regardless of load order. */
    .mod_confscheduler-toolbar.mod_confscheduler-print-toolbar {
        display: none !important;
    }

    .mod_confscheduler-grid-scroll {
        overflow: visible;
    }

    .mod_confscheduler-block {
        box-shadow: none;
        border: 1px solid #000;
        break-inside: avoid;
    }

    .mod_confscheduler-print-bw .mod_confscheduler-room-header {
        background-color: #fff !important;
        color: #000;
        border: 1px solid #000;
    }

    .mod_confscheduler-print-bw .mod_confscheduler-room-column.has-colour::before {
        display: none;
    }

    .mod_confscheduler-print-bw .mod_confscheduler-block {
        background: #fff !important;
        color: #000;
    }

    .mod_confscheduler-print-bw .mod_confscheduler-track-pill {
        background: #fff !important;
        color: #000 !important;
        border: 1px solid #000;
    }

    .mod_confscheduler-print-bw .mod_confscheduler-block-fav,
    .mod_confscheduler-print-bw .mod_confscheduler-block-fav-readonly {
        color: #000;
    }
}
```

with (the colour-stripping rules now apply unconditionally, i.e. live on
screen; only the toolbar-hiding/shadow/border/overflow rules stay
print-only, since those genuinely only make sense for a printed page):

```css
/*
 * Black & white mode strips the room/track colour-theming down to plain borders/text
 * via the .mod_confscheduler-bw class below (renamed from .mod_confscheduler-print-bw,
 * user feedback 2026-07-06: it now applies live on screen too, not just when printing --
 * toggling it is no longer a print-only setting). Applied unconditionally (not inside
 * @media print) so it's visible immediately; print naturally inherits whatever is
 * already on screen, with no separate print-only colour logic needed.
 */
.mod_confscheduler-bw .mod_confscheduler-room-header {
    background-color: #fff !important;
    color: #000;
    border: 1px solid #000;
}

.mod_confscheduler-bw .mod_confscheduler-room-column.has-colour::before {
    display: none;
}

.mod_confscheduler-bw .mod_confscheduler-block {
    background: #fff !important;
    color: #000;
}

.mod_confscheduler-bw .mod_confscheduler-track-pill {
    background: #fff !important;
    color: #000 !important;
    border: 1px solid #000;
}

.mod_confscheduler-bw .mod_confscheduler-block-fav,
.mod_confscheduler-bw .mod_confscheduler-block-fav-readonly {
    color: #000;
}

@media print {
    /* Compound selector (two classes) deliberately, not just .mod_confscheduler-print-toolbar:
       the toolbar also carries Bootstrap's .d-flex utility class, whose own
       "display: flex !important" rule has equal specificity to a single-class selector here
       and would otherwise win the cascade tiebreak by simply being loaded later in the
       aggregated theme CSS. Two classes gives this rule strictly higher specificity, so it
       wins regardless of load order. */
    .mod_confscheduler-toolbar.mod_confscheduler-print-toolbar {
        display: none !important;
    }

    .mod_confscheduler-grid-scroll {
        overflow: visible;
    }

    .mod_confscheduler-block {
        box-shadow: none;
        border: 1px solid #000;
        break-inside: avoid;
    }
}
```

- [ ] **Step 2: Rename the lang strings**

In `lang/en/confscheduler.php`, replace:

```php
$string['printbw'] = 'Black & white';
$string['printcolour'] = 'Colour';
$string['printcolourmode'] = 'Print colour mode';
```

with:

```php
$string['blackandwhite'] = 'Black & white';
$string['colour'] = 'Colour';
$string['colourmode'] = 'Colour mode';
```

In `lang/ja/confscheduler.php`, replace the matching three entries
(`printbw`/`printcolour`/`printcolourmode`, currently '白黒'/'カラー'/
'印刷時の色モード') with the renamed keys, same Japanese text:

```php
$string['blackandwhite'] = '白黒';
$string['colour'] = 'カラー';
$string['colourmode'] = '色モード';
```

- [ ] **Step 3: Update `display.mustache`**

Replace:

```mustache
            <fieldset class="mod_confscheduler-print-colourmode mr-3 mb-0">
                <legend class="sr-only">{{#str}} printcolourmode, mod_confscheduler {{/str}}</legend>
                <label class="mr-2">
                    <input type="radio" name="mod_confscheduler_printcolour" value="colour" checked>
                    {{#str}} printcolour, mod_confscheduler {{/str}}
                </label>
                <label>
                    <input type="radio" name="mod_confscheduler_printcolour" value="bw">
                    {{#str}} printbw, mod_confscheduler {{/str}}
                </label>
            </fieldset>
```

with:

```mustache
            <fieldset class="mod_confscheduler-colourmode mr-3 mb-0">
                <legend class="sr-only">{{#str}} colourmode, mod_confscheduler {{/str}}</legend>
                <label class="mr-2">
                    <input type="radio" name="mod_confscheduler_colourmode" value="colour" checked>
                    {{#str}} colour, mod_confscheduler {{/str}}
                </label>
                <label>
                    <input type="radio" name="mod_confscheduler_colourmode" value="bw">
                    {{#str}} blackandwhite, mod_confscheduler {{/str}}
                </label>
            </fieldset>
```

(This same edit is combined with Task 7's template changes below, since
both touch this file's print-controls block — apply both edits together
when you reach Task 7, or apply this one now and Task 7's on top; either
order works since they touch different lines.)

- [ ] **Step 4: Update `scheduler_display.js`'s event handler**

Replace:

```js
        const bwRadio = event.target.closest('[name=mod_confscheduler_printcolour]');
        if (bwRadio) {
            state.root.classList.toggle('mod_confscheduler-print-bw', bwRadio.value === 'bw' && bwRadio.checked);
            return;
        }
```

with:

```js
        const bwRadio = event.target.closest('[name=mod_confscheduler_colourmode]');
        if (bwRadio) {
            state.root.classList.toggle('mod_confscheduler-bw', bwRadio.value === 'bw' && bwRadio.checked);
            return;
        }
```

- [ ] **Step 5: Rebuild AMD, deploy, and verify live**

```bash
cd /vagrant/moodle-dev/moodle-mod_confscheduler
grunt amd --force
git diff --stat amd/build/
grunt amd --force
git diff --stat amd/build/
rsync -av --delete /vagrant/moodle-dev/moodle-mod_confscheduler/ /srv/lms/moodle/public/mod/confscheduler/ --exclude .git
cd /srv/lms/moodle/public && sudo -u www-data php admin/cli/upgrade.php --non-interactive
```

Via Playwright, open Display mode for an instance with coloured rooms/
tracks, click the "Black & white" radio, and confirm the on-screen grid
immediately turns black-and-white (inspect the DOM's actual computed
background colours, not just a screenshot) — no print dialog needed to see
the effect. Switch back to "Colour" and confirm it reverts live.

- [ ] **Step 6: phpcs / mustache lint**

```bash
vendor/bin/phpcs --standard=moodle /vagrant/moodle-dev/moodle-mod_confscheduler/lang/en/confscheduler.php /vagrant/moodle-dev/moodle-mod_confscheduler/lang/ja/confscheduler.php
```
Also run `moodle-dev:moodle-mustache-lint` on `templates/display.mustache`.
Expected: both clean.

- [ ] **Step 7: Commit**

```bash
cd /vagrant/moodle-dev/moodle-mod_confscheduler
git add styles.css templates/display.mustache amd/src/scheduler_display.js amd/build/ lang/en/confscheduler.php lang/ja/confscheduler.php
git commit -m "Make black & white a live on-screen toggle, not print-only

Moved the colour-stripping CSS out of @media print (renamed
.mod_confscheduler-print-bw -> .mod_confscheduler-bw, and the
printcolour/printbw/printcolourmode strings to colour/blackandwhite/
colourmode, since the toggle is no longer print-specific) so it
applies immediately on screen; print now just reflects whatever's
already showing, with no separate print-only colour logic."
```

---

### Task 7: Remove paper-size and orientation print controls

**Files:**
- Modify: `/vagrant/moodle-dev/moodle-mod_confscheduler/templates/display.mustache`
- Modify: `/vagrant/moodle-dev/moodle-mod_confscheduler/amd/src/scheduler_display.js`
- Modify: `/vagrant/moodle-dev/moodle-mod_confscheduler/lang/en/confscheduler.php`
- Modify: `/vagrant/moodle-dev/moodle-mod_confscheduler/lang/ja/confscheduler.php`
- Modify: `/vagrant/moodle-dev/moodle-mod_confscheduler/README.md`

**Interfaces:**
- Consumes: nothing.
- Produces: nothing.

- [ ] **Step 1: Remove the controls from `display.mustache`**

Delete the paper-size `<select>` and orientation `<fieldset>` entirely
(everything between the colour-mode fieldset from Task 6 and the "Print"
button):

```mustache
            <label class="mr-2 mb-0" for="mod_confscheduler-print-papersize">{{#str}} papersize, mod_confscheduler {{/str}}</label>
            <select id="mod_confscheduler-print-papersize" class="mod_confscheduler-print-papersize form-control form-control-sm mr-3">
                <option value="A4" selected>A4</option>
                <option value="A3">A3</option>
                <option value="A2">A2</option>
            </select>
            <fieldset class="mod_confscheduler-print-orientation mr-3 mb-0">
                <legend class="sr-only">{{#str}} orientation, mod_confscheduler {{/str}}</legend>
                <label class="mr-2">
                    <input type="radio" name="mod_confscheduler_printorientation" value="portrait" checked>
                    {{#str}} portrait, mod_confscheduler {{/str}}
                </label>
                <label>
                    <input type="radio" name="mod_confscheduler_printorientation" value="landscape">
                    {{#str}} landscape, mod_confscheduler {{/str}}
                </label>
            </fieldset>
```

(leaving the colour-mode fieldset and the "Print" button as the only two
remaining controls in `.mod_confscheduler-print-controls`).

- [ ] **Step 2: Remove `applyPageSize()` and its call sites from `scheduler_display.js`**

Delete the entire function:

```js
const applyPageSize = (papersize, orientation) => {
    let styleEl = document.getElementById('mod_confscheduler-print-page-rule');
    if (!styleEl) {
        styleEl = document.createElement('style');
        styleEl.id = 'mod_confscheduler-print-page-rule';
        document.head.appendChild(styleEl);
    }
    styleEl.textContent = `@page { size: ${papersize} ${orientation}; }`;
};
```

Delete the paper-size/orientation branch inside the delegated `change`
listener:

```js
        const sizeSelect = event.target.closest('.mod_confscheduler-print-papersize');
        const orientationRadio = event.target.closest('[name=mod_confscheduler_printorientation]');
        if (sizeSelect || orientationRadio) {
            const papersize = state.root.querySelector('.mod_confscheduler-print-papersize').value;
            const orientation = state.root.querySelector('[name=mod_confscheduler_printorientation]:checked').value;
            applyPageSize(papersize, orientation);
        }
```

Delete the init-time call in `init()`:

```js
    const papersizeEl = root.querySelector('.mod_confscheduler-print-papersize');
    const orientationEl = root.querySelector('[name=mod_confscheduler_printorientation]:checked');
    if (papersizeEl && orientationEl) {
        applyPageSize(papersizeEl.value, orientationEl.value);
    }
```

- [ ] **Step 3: Remove the now-unused lang strings**

Delete from both `lang/en/confscheduler.php` and `lang/ja/confscheduler.php`:
`papersize`, `orientation`, `portrait`, `landscape` (confirmed unused
anywhere else in this plugin by the root-cause investigation for this
plan's own spec).

- [ ] **Step 4: Update `README.md`**

Find and update the line describing print support (currently something
like "Printable in colour or black & white, at A4/A3/A2 in either
orientation, via CSS only (no PDF generation)") to reflect the new
behaviour, e.g.: "Printable in colour or black & white (now a live
on-screen toggle, not print-only); paper size and orientation are left
entirely to the browser's own print dialog."

- [ ] **Step 5: Rebuild AMD, deploy, and verify live**

```bash
cd /vagrant/moodle-dev/moodle-mod_confscheduler
grunt amd --force
git diff --stat amd/build/
grunt amd --force
git diff --stat amd/build/
rsync -av --delete /vagrant/moodle-dev/moodle-mod_confscheduler/ /srv/lms/moodle/public/mod/confscheduler/ --exclude .git
cd /srv/lms/moodle/public && sudo -u www-data php admin/cli/upgrade.php --non-interactive
```

Via Playwright, open Display mode and confirm: only the colour-mode
fieldset and Print button remain in the print-controls toolbar section; no
JS console error on page load (confirming the removed `applyPageSize()`
init-time call site is gone cleanly); clicking "Print" still opens the
browser's native print dialog (Playwright can't drive the OS print dialog
itself, but confirm `window.print()` is still called without error, e.g. by
checking Chromium's own print-preview-triggered state or a console log
insertion point if needed).

- [ ] **Step 6: phpcs / mustache lint**

```bash
vendor/bin/phpcs --standard=moodle /vagrant/moodle-dev/moodle-mod_confscheduler/lang/en/confscheduler.php /vagrant/moodle-dev/moodle-mod_confscheduler/lang/ja/confscheduler.php
```
Also run `moodle-dev:moodle-mustache-lint` on `templates/display.mustache`.
Expected: both clean.

- [ ] **Step 7: Commit**

```bash
cd /vagrant/moodle-dev/moodle-mod_confscheduler
git add templates/display.mustache amd/src/scheduler_display.js amd/build/ lang/en/confscheduler.php lang/ja/confscheduler.php README.md
git commit -m "Remove paper-size/orientation print controls

Per direct user feedback: browsers already override/ignore forced
orientation in practice, and the browser's own native print dialog
already offers better paper-size scaling (including A2) than this
plugin's forced @page rule ever did. Print controls are now just
colour/black & white; size and orientation are left entirely to the
browser."
```

---

### Task 8: Full-suite regression check + `moodle-reviewer` pass

**Files:**
- (No new files — verification and fix-up only.)

**Interfaces:**
- Consumes: everything from Tasks 1-7.
- Produces: nothing — verification gate before Task 9's docs/version wrap-up.

- [ ] **Step 1: Full PHPUnit suite**

```bash
cd /srv/lms/moodle/public
sudo -u www-data vendor/bin/phpunit --testsuite mod_confscheduler_testsuite
```
Expected: all passing (note the exact count — it should be the prior
count plus the new tests from Tasks 2 and 3).

- [ ] **Step 2: phpcs/moodlecheck across the whole plugin**

```bash
vendor/bin/phpcs --standard=moodle /vagrant/moodle-dev/moodle-mod_confscheduler
```
Also run the `moodle-dev:moodle-moodlecheck`-equivalent via
`local/moodlecheck` on the deployed copy at
`/srv/lms/moodle/public/mod/confscheduler`.
Expected: clean.

- [ ] **Step 3: Dispatch a `moodle-reviewer` agent scoped to this round's commits**

Scope it to the commit range created by Tasks 1-7 in this plugin (not the
whole plugin history), matching this project's established "one reviewer
agent per plugin repo, scoped to the session's commit range" practice. Fix
every real finding, re-running whichever task's own verification steps are
relevant to what changed, before proceeding to Task 9.

- [ ] **Step 4: Re-run every live Playwright check from Tasks 5-7 one more time after any reviewer-driven fixes**

Confirms nothing regressed from fixing review findings. No new script
needed — re-run the same checks described in each task's own "verify live"
step.

---

### Task 9: Docs and coordination-repo updates

**Files:**
- Modify: `/vagrant/moodle-dev/moodle-mod_confscheduler/changelog.md`
- Modify: `/vagrant/moodle-dev/moodle-mod_confscheduler/README.md` (if not
  already updated in Task 7 for the print-controls line — add the
  daystart/dayend feature description too)
- Modify: `/vagrant/moodle-dev/moodle-conference-tools/RELATIONS.md`,
  `SUMMARY.md`, `TASKLIST.md`
- Modify: `/vagrant/moodle-dev/moodle-conference-tools/mod_confscheduler.en.md`,
  `.ja.md`

**Interfaces:**
- Consumes: everything from Tasks 1-8.
- Produces: nothing — final task in this plan.

- [ ] **Step 1: `changelog.md`**

Add a dated entry (2026-07-06) summarizing all three changes: the
daystart/dayend display-window quick control, the live-on-screen black &
white toggle, and the removed paper-size/orientation controls.

- [ ] **Step 2: `README.md`**

Add the daystart/dayend feature to the existing "Edit mode"/"Display mode"
feature-list paragraphs (alongside the existing gapminutes/pxperhour quick
control mentions), if Task 7 hasn't already covered the print-line update.

- [ ] **Step 3: Coordination repo docs**

- `SUMMARY.md`: a new dated entry describing this round, matching the style
  of every prior "Revision round" entry.
- `TASKLIST.md`: a new "Revision round" section listing these three changes
  with their status checked off.
- `RELATIONS.md`: no cross-plugin contract changed here at all (this is
  entirely internal to `mod_confscheduler`) — no update strictly needed,
  but confirm by re-reading its `mod_confscheduler\api` surface list and add
  `set_day_bounds()` to it for completeness, matching how `set_gap_minutes()`/
  `set_pxperhour()` are already listed there.
- `mod_confscheduler.en.md`/`.ja.md`: add a short section describing the new
  day-start/day-end control and update the print-support description to
  match Tasks 6-7's changes (remove any mention of paper-size/orientation
  options, mention the colour toggle now applies live on screen).

- [ ] **Step 4: Commit**

```bash
cd /vagrant/moodle-dev/moodle-mod_confscheduler
git add changelog.md README.md
git commit -m "Document the day-bounds display window and print/colour cleanup"

cd /vagrant/moodle-dev/moodle-conference-tools
git add RELATIONS.md SUMMARY.md TASKLIST.md mod_confscheduler.en.md mod_confscheduler.ja.md
git commit -m "Document the confscheduler day-bounds/print-cleanup round"
```
