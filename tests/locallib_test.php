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

/**
 * Tests of the virtual tour submission plugin class.
 *
 * @package    assignsubmission_vrtour
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\assign_submission_vrtour::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\assignsubmission_vrtour\local\tour_manager::class)]
final class locallib_test extends \advanced_testcase {
    use tour_test_helper;

    /**
     * Settings are saved, clamped, and read back with defaults for the ones not saved.
     */
    public function test_settings(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->create_tour_assign($course, [
            'assignsubmission_vrtour_maxphotos' => 5,
            'assignsubmission_vrtour_minpoints' => 2,
            'assignsubmission_vrtour_allowaudio' => 0,
        ]);
        $plugin = $assign->get_submission_plugin_by_type('vrtour');
        $this->assertTrue((bool)$plugin->is_enabled());
        $settings = tour_manager::settings($plugin);
        $this->assertSame(5, $settings['maxphotos']);
        $this->assertSame(2, $settings['minpoints']);
        $this->assertSame(1, $settings['allowimage']);
        $this->assertSame(0, $settings['allowaudio']);
        $this->assertSame(1, $settings['allowvideo']);
        $this->assertSame(['hotspot', 'portal', 'image', 'text', 'media'], tour_manager::allowed_kinds($settings));
        $this->assertSame(['panorama', 'image', 'video'], tour_manager::allowed_uploads($settings));

        $plugin->save_settings((object)['assignsubmission_vrtour_maxphotos' => 99, 'assignsubmission_vrtour_minpoints' => -3]);
        $settings = tour_manager::settings($plugin);
        $this->assertSame(tour_manager::MAX_PHOTOS, $settings['maxphotos']);
        $this->assertSame(0, $settings['minpoints']);
        // Not sent: the defaults.
        $this->assertSame(1, $settings['allowaudio']);

        $plugin->save_settings((object)[
            'assignsubmission_vrtour_allowimage' => 0,
            'assignsubmission_vrtour_allowaudio' => 0,
            'assignsubmission_vrtour_allowvideo' => 0,
        ]);
        $settings = tour_manager::settings($plugin);
        $this->assertSame(['hotspot', 'portal', 'text'], tour_manager::allowed_kinds($settings));
        $this->assertSame(['panorama'], tour_manager::allowed_uploads($settings));
    }

    /**
     * A submission is empty until its tour has a photo.
     */
    public function test_is_empty(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign = $this->create_tour_assign($course);
        $plugin = $assign->get_submission_plugin_by_type('vrtour');
        $this->setUser($student);

        $submission = $assign->get_user_submission($student->id, true);
        $this->assertTrue($plugin->is_empty($submission));
        $this->assertTrue($plugin->submission_is_empty((object)[]));

        tour_manager::store($assign, $submission, []);
        $this->assertTrue($plugin->is_empty($submission));

        $this->give_tour($assign, $student, 2);
        $this->assertFalse($plugin->is_empty($submission));
        $this->assertFalse($plugin->submission_is_empty((object)['userid' => $student->id]));
    }

    /**
     * save() refreshes the counts from the stored tour and logs a created, then an updated, event.
     */
    public function test_save_counts_and_events(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign = $this->create_tour_assign($course);
        $plugin = $assign->get_submission_plugin_by_type('vrtour');
        $this->setUser($student);

        // No row yet: save() makes one.
        $submission = $assign->get_user_submission($student->id, true);
        $sink = $this->redirectEvents();
        $this->assertTrue($plugin->save($submission, new \stdClass()));
        $events = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof event\submission_created));
        $this->assertCount(1, $events);
        $row = $DB->get_record('assignsubmission_vrtour', ['submission' => $submission->id], '*', MUST_EXIST);
        $this->assertSame(0, (int)$row->numphotos);

        // Two points in one photo, stored with stale counts.
        $this->give_tour($assign, $student, 2);
        $DB->set_field('assignsubmission_vrtour', 'numpoints', 0, ['submission' => $submission->id]);
        $sink->clear();
        $this->assertTrue($plugin->save($submission, new \stdClass()));
        $events = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof event\submission_updated));
        $this->assertCount(1, $events);
        $this->assertSame(1, $events[0]->other['photocount']);
        $this->assertSame(2, $events[0]->other['pointcount']);
        $row = $DB->get_record('assignsubmission_vrtour', ['submission' => $submission->id], '*', MUST_EXIST);
        $this->assertSame(1, (int)$row->numphotos);
        $this->assertSame(2, (int)$row->numpoints);
        $sink->close();
    }

    /**
     * Fewer points than the teacher asks for: a draft can be saved but not submitted; without drafts the save is refused.
     */
    public function test_minpoints_refusal(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $drafts = $this->create_tour_assign($course, ['assignsubmission_vrtour_minpoints' => 3]);
        $plugin = $drafts->get_submission_plugin_by_type('vrtour');
        $this->setUser($student);
        $submission = $drafts->get_user_submission($student->id, true);
        $this->assertSame(get_string('errornophoto', 'assignsubmission_vrtour'), $plugin->precheck_submission($submission));
        $submission = $this->give_tour($drafts, $student, 2);
        $this->assertTrue($plugin->save($submission, new \stdClass()));
        $this->assertSame(
            get_string('errorminpoints', 'assignsubmission_vrtour', (object)['min' => 3, 'count' => 2]),
            $plugin->precheck_submission($submission)
        );
        $submission = $this->give_tour($drafts, $student, 3);
        $this->assertTrue($plugin->precheck_submission($submission));

        $nodrafts = $this->create_tour_assign($course, ['assignsubmission_vrtour_minpoints' => 3, 'submissiondrafts' => 0]);
        $plugin = $nodrafts->get_submission_plugin_by_type('vrtour');
        $submission = $this->give_tour($nodrafts, $student, 1);
        $this->assertFalse($plugin->save($submission, new \stdClass()));
        $this->assertSame(
            get_string('errorminpoints', 'assignsubmission_vrtour', (object)['min' => 3, 'count' => 1]),
            $plugin->get_error()
        );
    }

    /**
     * A new attempt based on the previous one gets a copy of the tour and its files.
     */
    public function test_copy_submission(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign = $this->create_tour_assign($course);
        $plugin = $assign->get_submission_plugin_by_type('vrtour');
        $this->setUser($student);

        $source = $this->give_tour($assign, $student, 2);
        $dest = $assign->get_user_submission($student->id, true, 1);
        $this->assertNotEquals($source->id, $dest->id);
        $this->assertTrue($plugin->copy_submission($source, $dest));

        $copy = tour_manager::get_row((int)$dest->id);
        $this->assertNotNull($copy);
        $this->assertSame(tour_manager::get_row((int)$source->id)->scenesjson, $copy->scenesjson);
        $this->assertSame(2, (int)$copy->numpoints);
        $assets = tour_manager::list_assets($assign->get_context(), (int)$dest->id);
        $this->assertCount(1, $assets);
        $this->assertSame(1, $assets[0]['itemid']);
        $this->assertSame('room.jpg', $assets[0]['filename']);
        $this->assertFalse($plugin->is_empty($dest));
    }

    /**
     * remove() deletes the files and empties the tour.
     */
    public function test_remove(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign = $this->create_tour_assign($course);
        $plugin = $assign->get_submission_plugin_by_type('vrtour');
        $this->setUser($student);

        $submission = $this->give_tour($assign, $student, 1);
        $this->assertTrue($plugin->remove($submission));
        $this->assertTrue($plugin->is_empty($submission));
        $this->assertSame([], tour_manager::scenes(tour_manager::get_row((int)$submission->id)));
        $this->assertSame([], tour_manager::list_assets($assign->get_context(), (int)$submission->id));
    }

    /**
     * get_files() lists the uploads (never the smaller copies) and the tour as JSON; the summary counts.
     */
    public function test_get_files_and_summary(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign = $this->create_tour_assign($course);
        $plugin = $assign->get_submission_plugin_by_type('vrtour');
        $this->setUser($student);

        $submission = $this->give_tour($assign, $student, 2);
        get_file_storage()->create_file_from_string([
            'contextid' => $assign->get_context()->id,
            'component' => tour_manager::COMPONENT,
            'filearea' => tour_manager::FILEAREA,
            'itemid' => $submission->id,
            'filepath' => '/1/variants/',
            'filename' => 'mobile.jpg',
        ], 'small');

        $files = $plugin->get_files($submission, $student);
        $this->assertArrayHasKey('tour.json', $files);
        $this->assertArrayHasKey('1/room.jpg', $files);
        $this->assertArrayNotHasKey('1/variants/mobile.jpg', $files);

        $showviewlink = false;
        $summary = $plugin->view_summary($submission, $showviewlink);
        $this->assertTrue($showviewlink);
        $expected = get_string('summary', 'assignsubmission_vrtour', (object)['photos' => 1, 'points' => 2]);
        $this->assertStringContainsString(s($expected), $summary);
    }
}
