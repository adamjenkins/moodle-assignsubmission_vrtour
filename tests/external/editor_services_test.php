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

namespace assignsubmission_vrtour\external;

use assignsubmission_vrtour\local\tour_manager;
use assignsubmission_vrtour\tests\tour_test_helper;
use core_external\external_api;

/**
 * Tests of the tour editor's web services: who may load and save, what a save refuses, and conflicts.
 *
 * @package    assignsubmission_vrtour
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\assignsubmission_vrtour\external\editor_load::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\assignsubmission_vrtour\external\editor_save::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\assignsubmission_vrtour\local\tour_manager::class)]
final class editor_services_test extends \advanced_testcase {
    use tour_test_helper;

    /** @var \stdClass The course. */
    private \stdClass $course;

    /** @var \assign The assignment. */
    private \assign $assign;

    /** @var int Its course module id. */
    private int $cmid;

    /** @var \stdClass A student. */
    private \stdClass $student;

    /** @var \stdClass Another student. */
    private \stdClass $other;

    /** @var \stdClass A teacher. */
    private \stdClass $teacher;

    /**
     * An assignment with two students and a teacher.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->assign = $this->create_tour_assign($this->course, ['assignsubmission_vrtour_allowvideo' => 0]);
        $this->cmid = (int)$this->assign->get_course_module()->id;
    }

    /**
     * Load as the current user.
     *
     * @param int $submissionid Submission to view, 0 for my own.
     * @return array The decoded result.
     */
    private function load(int $submissionid = 0): array {
        $result = editor_load::execute($this->cmid, $submissionid);
        $result = external_api::clean_returnvalue(editor_load::execute_returns(), $result);
        return [
            'document' => json_decode($result['documentjson'], true),
            'assets' => json_decode($result['assetsjson'], true),
            'info' => json_decode($result['infojson'], true),
            'presets' => json_decode($result['presetsjson'], true),
            'questions' => json_decode($result['questionsjson'], true),
        ];
    }

    /**
     * Save as the current user.
     *
     * @param array $scenes The rooms.
     * @param string $revision The revision loaded.
     * @return array The result.
     */
    private function save(array $scenes, string $revision): array {
        $result = editor_save::execute($this->cmid, $this->document($scenes, $revision));
        return external_api::clean_returnvalue(editor_save::execute_returns(), $result);
    }

    /**
     * A student loads an empty tour, uploads a photo, saves a room with a point and loads it back.
     */
    public function test_load_and_save_own_tour(): void {
        $this->setUser($this->student);
        $loaded = $this->load();
        $this->assertSame('vrroom-editdoc/1', $loaded['document']['schema']);
        $this->assertSame('vrtour', $loaded['document']['template']);
        $this->assertSame([], $loaded['document']['scenes']);
        $this->assertSame([], $loaded['assets']);
        $this->assertSame([], $loaded['presets']);
        $this->assertSame([], $loaded['questions']);
        $this->assertFalse($loaded['info']['features']['logic']);
        $this->assertFalse($loaded['info']['features']['questions']);
        // Video is not allowed in this assignment: the editor offers audio objects only.
        $this->assertSame(['hotspot', 'portal', 'image', 'text', 'audio'], $loaded['info']['features']['kinds']);
        $this->assertSame(10, $loaded['info']['maxscenes']);
        $this->assertArrayHasKey('maxwidth', $loaded['info']['panorama']);

        $submission = $this->assign->get_user_submission($this->student->id, false);
        $this->add_upload($this->assign, $submission, 1, 'room.jpg', $this->jpeg());
        $this->add_upload($this->assign, $submission, 2, 'unused.jpg', $this->jpeg());
        $scene = $this->photo_scene('a1', $this->ref(1, 'room.jpg'), [$this->hotspot('h1')]);
        $result = $this->save([$scene], $loaded['document']['revision']);
        $this->assertTrue($result['scenesupdated']);

        $again = $this->load();
        $this->assertSame($result['revision'], $again['document']['revision']);
        $this->assertCount(1, $again['document']['scenes']);
        // The unused upload was deleted by the save; the photo is listed and marked as a 360° photo.
        $this->assertCount(1, $again['assets']);
        $this->assertSame(1, $again['assets'][0]['itemid']);
        $this->assertSame('/', $again['assets'][0]['filepath']);
        $this->assertTrue($again['assets'][0]['panorama']);
        $row = tour_manager::get_row((int)$submission->id);
        $this->assertSame(1, (int)$row->numphotos);
        $this->assertSame(1, (int)$row->numpoints);
    }

    /**
     * A student can neither load nor save another student's tour; a teacher can load it read-only and never save.
     */
    public function test_ownership(): void {
        $this->setUser($this->other);
        $othersubmission = $this->give_tour($this->assign, $this->other, 1);

        $this->setUser($this->student);
        try {
            $this->load((int)$othersubmission->id);
            $this->fail('A student loaded another student\'s tour');
        } catch (\required_capability_exception $e) {
            // Graders only.
            $this->addToAssertionCount(1);
        }
        // The student's save always goes to their own submission, never the other's.
        $own = $this->load();
        $this->save([], $own['document']['revision']);
        $this->assertCount(1, tour_manager::scenes(tour_manager::get_row((int)$othersubmission->id)));

        $this->setUser($this->teacher);
        $viewed = $this->load((int)$othersubmission->id);
        $this->assertTrue($viewed['info']['readonly']);
        $this->assertCount(1, $viewed['document']['scenes']);
        $this->expectException(\moodle_exception::class);
        $this->save([], $viewed['document']['revision']);
    }

    /**
     * Saves are refused for a room that is not a 360° photo, a file of someone else, a kind of media
     * the teacher does not allow, and too many photos.
     *
     * @param string $case Which bad tour.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('refused_provider')]
    public function test_validation_failures(string $case): void {
        $this->setUser($this->other);
        $othersubmission = $this->give_tour($this->assign, $this->other, 1);
        $this->setUser($this->student);
        $loaded = $this->load();
        $submission = $this->assign->get_user_submission($this->student->id, false);
        $this->add_upload($this->assign, $submission, 1, 'room.jpg', $this->jpeg());
        $this->add_upload($this->assign, $submission, 2, 'clip.mp4', 'not really a video');
        $this->add_upload($this->assign, $submission, 3, 'small.jpg', $this->jpeg(800, 400));
        $good = $this->photo_scene('a1', $this->ref(1, 'room.jpg'), [$this->hotspot('h1')]);

        switch ($case) {
            case 'notphoto':
                $scene = $good;
                $scene['environment'] = ['type' => 'preset', 'preset' => 'gallery'];
                $scene['spawn'][0]['position'] = [0, 0, 0];
                unset($scene['interactables'][0]['placement']);
                $scenes = [$scene];
                break;
            case 'foreignref':
                // Upload 9 exists only in the other student's submission: it must not be reachable.
                $this->add_upload($this->assign, $othersubmission, 9, 'room.jpg', $this->jpeg());
                $scenes = [$this->photo_scene('a1', $this->ref(9, 'room.jpg'))];
                break;
            case 'videonotallowed':
                $video = [
                    'id' => 'v1',
                    'kind' => 'media',
                    'media' => 'video',
                    'name' => 'Clip',
                    'file' => $this->ref(2, 'clip.mp4'),
                    'placement' => ['yaw' => 0, 'pitch' => 0],
                    'position' => [0, 1.6, -4],
                ];
                $scenes = [$this->photo_scene('a1', $this->ref(1, 'room.jpg'), [$video])];
                break;
            case 'videoinpanel':
                $point = $this->hotspot('h1', [$this->ref(2, 'clip.mp4')]);
                $scenes = [$this->photo_scene('a1', $this->ref(1, 'room.jpg'), [$point])];
                break;
            case 'toosmall':
                $scenes = [$this->photo_scene('a1', $this->ref(3, 'small.jpg'))];
                break;
            case 'toomany':
                $scenes = [];
                for ($i = 1; $i <= 11; $i++) {
                    $scenes[] = $this->photo_scene('a' . $i, $this->ref(1, 'room.jpg'));
                }
                break;
            default:
                $scenes = [$good];
        }

        try {
            $this->save($scenes, $loaded['document']['revision']);
            $this->fail('The tour was saved: ' . $case);
        } catch (\moodle_exception $e) {
            $this->assertSame('errorinvalid', $e->errorcode, $case . ': ' . $e->getMessage());
        }
        $this->assertNull(tour_manager::get_row((int)$submission->id));
    }

    /**
     * The bad tours of test_validation_failures.
     *
     * @return array
     */
    public static function refused_provider(): array {
        return [
            'a room that is not a 360° photo' => ['notphoto'],
            'a file of another submission' => ['foreignref'],
            'a video object when video is not allowed' => ['videonotallowed'],
            'a video in a panel when video is not allowed' => ['videoinpanel'],
            'a photo narrower than the site allows' => ['toosmall'],
            'more photos than allowed' => ['toomany'],
        ];
    }

    /**
     * A save from a revision that is no longer the stored one is refused.
     */
    public function test_revision_conflict(): void {
        $this->setUser($this->student);
        $loaded = $this->load();
        $submission = $this->assign->get_user_submission($this->student->id, false);
        $this->add_upload($this->assign, $submission, 1, 'room.jpg', $this->jpeg());
        $scene = $this->photo_scene('a1', $this->ref(1, 'room.jpg'));
        $this->save([$scene], $loaded['document']['revision']);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('errorconflict', 'assignsubmission_vrtour'));
        $this->save([$scene], $loaded['document']['revision']);
    }
}
