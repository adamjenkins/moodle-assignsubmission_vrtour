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

namespace assignsubmission_vrtour\form;

use assignsubmission_vrtour\local\tour_manager;
use assignsubmission_vrtour\tests\tour_test_helper;

/**
 * Tests of the tour upload form, submitted the way core_form/modalform submits it.
 *
 * @package    assignsubmission_vrtour
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\assignsubmission_vrtour\form\asset_upload::class)]
final class asset_upload_test extends \advanced_testcase {
    use tour_test_helper;

    /**
     * Put a file in a new draft area of the current user.
     *
     * @param string $filename File name.
     * @param string $content File content.
     * @return int Draft item id.
     */
    private static function draft_with(string $filename, string $content): int {
        global $CFG, $USER;
        require_once($CFG->libdir . '/filelib.php');
        $draftid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftid,
            'filepath' => '/',
            'filename' => $filename,
        ], $content);
        return $draftid;
    }

    /**
     * Submit the form through the dynamic form web service.
     *
     * @param int $cmid Course module id.
     * @param string $kind Kind of file.
     * @param int $draftid Draft item id.
     * @return array Web service result.
     */
    private static function submit(int $cmid, string $kind, int $draftid): array {
        // Sesskey confirmation reads the real $_POST, as the AJAX request carries it.
        $_POST['sesskey'] = sesskey();
        $formdata = http_build_query([
            'cmid' => $cmid,
            'kind' => $kind,
            'assetfile' => $draftid,
            'sesskey' => sesskey(),
            '_qf__assignsubmission_vrtour_form_asset_upload' => 1,
        ], '', '&');
        return \core_form\external\dynamic_form::execute(asset_upload::class, $formdata);
    }

    /**
     * A 360° photo and a point's image go into new folders of the student's submission; the photo gets its smaller copies.
     */
    public function test_upload_photo_and_image(): void {
        $this->resetAfterTest();
        set_config('panomobilewidth', 1024, 'mod_vrroom');
        set_config('panodesktopwidth', 1536, 'mod_vrroom');
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign = $this->create_tour_assign($course);
        $cmid = (int)$assign->get_course_module()->id;
        $this->setUser($student);

        $result = self::submit($cmid, 'panorama', self::draft_with('street.jpg', $this->jpeg(2048, 1024)));
        $this->assertTrue($result['submitted']);
        $asset = json_decode($result['data'], true);
        $this->assertSame('assets', $asset['area']);
        $this->assertSame(1, $asset['itemid']);
        $this->assertSame('/', $asset['filepath']);
        $this->assertSame('street.jpg', $asset['filename']);
        $this->assertSame('panorama', $asset['kind']);
        $this->assertTrue($asset['panorama']);

        $submission = $assign->get_user_submission($student->id, false);
        $this->assertStringContainsString("/assignsubmission_vrtour/tour/{$submission->id}/1/street.jpg", $asset['url']);
        $photo = tour_manager::find_ref_file($assign->get_context(), (int)$submission->id, $this->ref(1, 'street.jpg'));
        $this->assertNotNull($photo);
        $this->assertNotEmpty(array_filter(\mod_vrroom\local\panorama::find_variants($photo)));

        $result = self::submit($cmid, 'image', self::draft_with('sign.png', 'not checked as an image'));
        $this->assertTrue($result['submitted']);
        $asset = json_decode($result['data'], true);
        $this->assertSame(2, $asset['itemid']);
        $this->assertSame('image', $asset['kind']);

        // The listing never shows the smaller copies.
        $listed = array_column(tour_manager::list_assets($assign->get_context(), (int)$submission->id), 'filename');
        $this->assertSame(['street.jpg', 'sign.png'], $listed);
    }

    /**
     * A photo that is not 2:1 is accepted; one too small is a form error and nothing more is stored.
     */
    public function test_photo_shape_and_size(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign = $this->create_tour_assign($course);
        $this->setUser($student);

        $draftid = self::draft_with('flat.jpg', $this->jpeg(1600, 1200));
        $result = self::submit((int)$assign->get_course_module()->id, 'panorama', $draftid);
        $this->assertTrue($result['submitted']);
        $this->assertTrue(json_decode($result['data'], true)['panorama']);

        $draftid = self::draft_with('small.jpg', $this->jpeg(800, 400));
        $result = self::submit((int)$assign->get_course_module()->id, 'panorama', $draftid);
        $this->assertFalse($result['submitted']);
        $submission = $assign->get_user_submission($student->id, false);
        $listed = array_column(tour_manager::list_assets($assign->get_context(), (int)$submission->id), 'filename');
        $this->assertSame(['flat.jpg'], $listed);
    }

    /**
     * A kind the teacher does not allow is refused.
     */
    public function test_kind_not_allowed(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign = $this->create_tour_assign($course, ['assignsubmission_vrtour_allowaudio' => 0]);
        $this->setUser($student);

        try {
            $result = self::submit((int)$assign->get_course_module()->id, 'audio', self::draft_with('tape.mp3', 'sound'));
            $this->assertFalse($result['submitted'], 'Audio was stored although the teacher does not allow it.');
        } catch (\moodle_exception $e) {
            $this->assertSame('errorinvalid', $e->errorcode);
        }
        $submission = $assign->get_user_submission($student->id, false);
        $this->assertSame([], $submission ? tour_manager::list_assets($assign->get_context(), (int)$submission->id) : []);
    }

    /**
     * Only a student who may edit their own submission may upload: not a teacher.
     */
    public function test_teacher_cannot_upload(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $assign = $this->create_tour_assign($course);
        $this->setUser($teacher);

        $this->expectException(\moodle_exception::class);
        self::submit((int)$assign->get_course_module()->id, 'panorama', self::draft_with('street.jpg', $this->jpeg()));
    }
}
