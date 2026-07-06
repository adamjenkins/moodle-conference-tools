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
 * Retargets the 8 bilingual EN/JA demo-site user tours (built 2026-07-05,
 * updated 2026-07-06) from TARGET_UNATTACHED (always centered/modal, never
 * highlighting anything) to real TARGET_SELECTOR CSS-selector targeting,
 * one step at a time -- the actual bug behind "tour steps never point at
 * the UI element they describe" (user report, 2026-07-06 night).
 *
 * This script hardcodes the test site's config.php location
 * (/srv/lms/moodle/public/config.php, this project's one fixed test
 * environment per CLAUDE.md/session convention) rather than deriving a
 * relative path, since this coordination repo and the Moodle install are
 * unrelated directory trees with no fixed relative offset between them.
 * Run directly with:
 *   sudo -u www-data php /vagrant/moodle-dev/moodle-conference-tools/scripts/rebuild_user_tours.php
 * This script is idempotent: safe to re-run, since it looks up each step by
 * its known id and overwrites targettype/targetvalue/config every time.
 *
 * Title/content/sortorder/tour role+pathmatch filters are left completely
 * untouched -- this is a targeting fix only, not a content rewrite.
 *
 * A handful of steps describe something that genuinely has no on-page
 * anchor on the tour's own pathmatch (view.php): e.g. mod_confcheckin's
 * personal ticket/badge/certificate download only exists on a page reached
 * AFTER purchase, never on view.php itself for a participant, so all three
 * mod_confcheckin participant-tour steps point at the one real link view.php
 * does have ("Buy or claim a ticket") rather than something that doesn't
 * exist. This is a limitation of the underlying pages, not something this
 * tour-only fix can address without a plugin code change (out of scope for
 * this round). Similarly, mod_confprogram's own view.php has no direct
 * "Edit settings" or "Manage notifications" link, so two of its organizer
 * steps re-target its two closest real links (assign.php / decisions.php).
 *
 * Each step's target uses tool_usertours\target::TARGET_SELECTOR (constant
 * value 0) with a CSS selector in targetvalue, plus a 'placement' config key
 * (top/bottom/left/right) -- the same mechanism Moodle's own shipped tours
 * (tour_gradebook_tour, tour_navigation_*) already use, confirmed by reading
 * their live DB rows before writing this script.
 *
 * @package    local (coordination-repo script, not a plugin)
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require('/srv/lms/moodle/public/config.php');

use tool_usertours\step;
use tool_usertours\target;

/**
 * Retargets one step to a CSS selector, with an optional placement override
 * (defaults to the tour/system default of 'bottom' if not specified).
 *
 * @param int $stepid The tool_usertours_steps.id
 * @param string $selector The CSS selector to target
 * @param string $placement One of top/bottom/left/right
 */
function retarget(int $stepid, string $selector, string $placement = 'bottom'): void {
    $step = step::instance($stepid);
    $step->set_targettype(target::TARGET_SELECTOR);
    $step->set_targetvalue($selector);
    $step->set_config('placement', $placement);
    // backdrop=true is what actually draws the spotlight/highlight cutout around
    // the target element -- without it, tool_usertours still positions the step
    // correctly next to the target, but draws no visual highlight at all, which
    // looks identical to an unattached/centered step at a glance. This is the
    // exact visual improvement this whole rebuild is for, so every retargeted
    // step gets it (matching core's own tours, which all set backdrop=1).
    $step->set_config('backdrop', true);
    $step->persist(true);
    echo "  step {$stepid}: selector=\"{$selector}\" placement={$placement}\n";
}

echo "Tour 14 -- Conference Submissions (Organizer)\n";
retarget(32, '[data-region="activity-information"]');
retarget(33, 'a[href*="submissiontypes.php"]');
retarget(34, 'a[href*="dates.php"]');
retarget(35, 'a[href*="withdraw="]');
retarget(54, 'a[href*="notifications.php"]');

echo "Tour 15 -- Conference Program (Organizer)\n";
retarget(36, '[data-region="activity-information"]');
retarget(37, 'a[href*="decisions.php"]');
retarget(38, 'a[href*="assign.php"]');
retarget(55, 'a[href*="decisions.php"]');

echo "Tour 16 -- Conference Scheduler (Organizer)\n";
retarget(39, '.mod_confscheduler-toolbar');
retarget(40, '.mod_confscheduler-run-autoscheduler');
retarget(41, '.mod_confscheduler-add-spanblock');
retarget(56, '.mod_confscheduler-room-header-edit');
retarget(57, '.mod_confscheduler-send-notifications');

echo "Tour 17 -- Conference Check-in (Organizer)\n";
retarget(42, '[data-region="activity-information"]');
retarget(43, 'a[href*="tickettypes.php"]');
retarget(44, 'a[href*="templates.php"]');
retarget(58, 'a[href*="tickettypes.php"]');

echo "Tour 18 -- Conference Submissions (Participant)\n";
retarget(45, '.singlebutton', 'bottom');
retarget(46, '.singlebutton', 'right');
retarget(47, 'a[href*="withdraw="]');

echo "Tour 19 -- Conference Program (Participant)\n";
retarget(48, '.mod_confprogram-list');

echo "Tour 20 -- Conference Scheduler (Participant)\n";
retarget(49, '.mod_confscheduler-day-select');
retarget(50, '.mod_confscheduler-mytimetable-toggle');
retarget(59, 'a[href*="export.php"]');

echo "Tour 21 -- Conference Check-in (Participant)\n";
retarget(51, 'a[href*="purchase.php"]', 'bottom');
retarget(52, 'a[href*="purchase.php"]', 'right');
retarget(53, 'a[href*="purchase.php"]', 'left');

echo "Done. Purge caches (admin/cli/purge_caches.php) before verifying live.\n";
