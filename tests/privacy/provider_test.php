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

namespace assignsubmission_vrtour\privacy;

use assignsubmission_vrtour\local\tour_manager;
use assignsubmission_vrtour\tests\tour_test_helper;
use core_privacy\local\request\writer;
use mod_assign\privacy\assign_plugin_request_data;

/**
 * Tests of the privacy provider, with generated tours (modelled on assignsubmission_file's provider test).
 *
 * @package    assignsubmission_vrtour
 * @copyright  2018 Adrian Greeve <adrian@moodle.com>
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\assignsubmission_vrtour\privacy\provider::class)]
final class provider_test extends \mod_assign\tests\provider_testcase {
    use tour_test_helper;

    /**
     * The metadata names the table and the files.
     */
    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection('assignsubmission_vrtour'));
        $names = array_map(fn($item) => $item->get_name(), $collection->get_collection());
        $this->assertContains('assignsubmission_vrtour', $names);
        $this->assertContains('core_files', $names);
    }

    /**
     * A student's export holds the tour as data and its files.
     */
    public function test_export_submission_user_data(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign = $this->create_tour_assign($course);
        $this->setUser($student);
        $submission = $this->give_tour($assign, $student, 2);
        $context = $assign->get_context();

        $writer = writer::with_context($context);
        $this->assertFalse($writer->has_any_data());
        provider::export_submission_user_data(new assign_plugin_request_data($context, $assign, $submission, ['Attempt 1']));

        $subcontext = ['Attempt 1', get_string('pluginname', 'assignsubmission_vrtour')];
        $data = $writer->get_data($subcontext);
        $this->assertSame(1, $data->photos);
        $this->assertSame(2, $data->points);
        $this->assertCount(1, $data->tour);
        $this->assertNotEmpty($writer->get_files($subcontext));
    }

    /**
     * Deleting for a context deletes every tour of the assignment.
     */
    public function test_delete_submission_for_context(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign = $this->create_tour_assign($course);
        $plugin = $assign->get_submission_plugin_by_type('vrtour');
        $submission1 = $this->give_tour($assign, $student1);
        $submission2 = $this->give_tour($assign, $student2);

        provider::delete_submission_for_context(new assign_plugin_request_data($assign->get_context(), $assign));
        $this->assertTrue($plugin->is_empty($submission1));
        $this->assertTrue($plugin->is_empty($submission2));
        $this->assertSame([], tour_manager::list_assets($assign->get_context(), (int)$submission1->id));
    }

    /**
     * Deleting for a user deletes only that user's tour.
     */
    public function test_delete_submission_for_userid(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign = $this->create_tour_assign($course);
        $plugin = $assign->get_submission_plugin_by_type('vrtour');
        $submission1 = $this->give_tour($assign, $student1);
        $submission2 = $this->give_tour($assign, $student2);

        provider::delete_submission_for_userid(
            new assign_plugin_request_data($assign->get_context(), $assign, $submission1, [], $student1)
        );
        $this->assertTrue($plugin->is_empty($submission1));
        $this->assertSame([], tour_manager::list_assets($assign->get_context(), (int)$submission1->id));
        $this->assertFalse($plugin->is_empty($submission2));
        $this->assertNotEmpty(tour_manager::list_assets($assign->get_context(), (int)$submission2->id));
    }

    /**
     * Deleting for some users of a context leaves the others and the other assignments alone.
     */
    public function test_delete_submissions(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student3 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign1 = $this->create_tour_assign($course);
        $assign2 = $this->create_tour_assign($course);
        $this->give_tour($assign1, $student1);
        $this->give_tour($assign1, $student2);
        $this->give_tour($assign1, $student3);
        $this->give_tour($assign2, $student1);

        $deletedata = new assign_plugin_request_data($assign1->get_context(), $assign1);
        $deletedata->set_userids([$student1->id, $student3->id]);
        $deletedata->populate_submissions_and_grades();
        provider::delete_submissions($deletedata);

        $this->assertSame(1, $DB->count_records('assignsubmission_vrtour', ['assignment' => $assign1->get_instance()->id]));
        $this->assertSame(1, $DB->count_records('assignsubmission_vrtour', ['assignment' => $assign2->get_instance()->id]));
        $files = $DB->count_records_select(
            'files',
            "contextid = ? AND component = ? AND filename <> '.'",
            [$assign1->get_context()->id, 'assignsubmission_vrtour']
        );
        $this->assertSame(1, $files);
    }
}
