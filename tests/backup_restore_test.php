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

namespace assignsubmission_vrtour;

use assignsubmission_vrtour\local\tour_manager;
use assignsubmission_vrtour\tests\tour_test_helper;
use backup;
use backup_controller;
use restore_controller;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * A course backup with user data carries each submission's tour row and files to the restored course.
 *
 * @package    assignsubmission_vrtour
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\backup_assignsubmission_vrtour_subplugin::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\restore_assignsubmission_vrtour_subplugin::class)]
final class backup_restore_test extends \advanced_testcase {
    use tour_test_helper;

    /**
     * Back up and restore a course with user data; the tour comes back whole.
     */
    public function test_backup_restore_with_users(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign = $this->create_tour_assign($course);
        $submission = $this->give_tour($assign, $student, 2);
        $cm = $assign->get_course_module();
        $stored = tour_manager::get_row((int)$submission->id);

        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $course->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_status(\backup_setting::NOT_LOCKED);
        $bc->get_plan()->get_setting('users')->set_value(true);
        // In import mode the activity's userinfo setting does not follow the root setting.
        $activitysetting = $bc->get_plan()->get_setting('assign_' . $cm->id . '_userinfo');
        $activitysetting->set_status(\backup_setting::NOT_LOCKED);
        $activitysetting->set_value(true);
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        $newcourseid = \restore_dbops::create_new_course('Restored course', 'restored_' . $course->id, $course->category);
        $rc = new restore_controller(
            $backupid,
            $newcourseid,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id,
            backup::TARGET_NEW_COURSE
        );
        $rc->get_plan()->get_setting('users')->set_status(\backup_setting::NOT_LOCKED);
        $rc->get_plan()->get_setting('users')->set_value(true);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        $newassignid = (int)$DB->get_field('assign', 'id', ['course' => $newcourseid], MUST_EXIST);
        $row = $DB->get_record('assignsubmission_vrtour', ['assignment' => $newassignid], '*', MUST_EXIST);
        $this->assertNotEquals($submission->id, $row->submission);
        $this->assertSame($stored->scenesjson, $row->scenesjson);
        $this->assertSame(sha1($row->scenesjson), $row->revision);
        $this->assertSame(2, (int)$row->numpoints);

        $newcm = get_coursemodule_from_instance('assign', $newassignid);
        $newcontext = \context_module::instance($newcm->id);
        $assets = tour_manager::list_assets($newcontext, (int)$row->submission);
        $this->assertCount(1, $assets);
        $this->assertSame('room.jpg', $assets[0]['filename']);
        $this->assertSame(1, $assets[0]['itemid']);
    }
}
