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

namespace assignsubmission_vrtour\tests;

use assignsubmission_vrtour\local\tour_manager;
use mod_vrroom\local\editor\editor_manager;

/**
 * Fixtures for the virtual tour tests: assignments with the plugin, photos made with GD, uploads and rooms.
 *
 * No binary fixtures: every image is drawn here.
 *
 * @package    assignsubmission_vrtour
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait tour_test_helper {
    /**
     * An assignment with the virtual tour plugin enabled (and nothing else).
     *
     * @param \stdClass $course The course.
     * @param array $params Extra assignment fields (for example 'assignsubmission_vrtour_minpoints' => 0).
     * @return \assign
     */
    protected function create_tour_assign(\stdClass $course, array $params = []): \assign {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $params += [
            'course' => $course->id,
            'assignsubmission_vrtour_enabled' => 1,
            'assignsubmission_onlinetext_enabled' => 0,
            'assignsubmission_file_enabled' => 0,
            'assignsubmission_vrtour_maxphotos' => 10,
            'assignsubmission_vrtour_minpoints' => 1,
            'assignsubmission_vrtour_allowimage' => 1,
            'assignsubmission_vrtour_allowaudio' => 1,
            'assignsubmission_vrtour_allowvideo' => 1,
            'assignsubmission_vrtour_maxbytes' => 0,
            'submissiondrafts' => 1,
        ];
        $instance = $this->getDataGenerator()->create_module('assign', $params);
        $cm = get_coursemodule_from_instance('assign', $instance->id);
        $context = \context_module::instance($cm->id);
        return new \assign($context, $cm, $course);
    }

    /**
     * JPEG bytes of a test image drawn with GD.
     *
     * @param int $width Width in pixels.
     * @param int $height Height in pixels.
     * @return string
     */
    protected function jpeg(int $width = 2048, int $height = 1024): string {
        $image = imagecreatetruecolor($width, $height);
        $colour = imagecolorallocate($image, 40, 120, 200);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $colour);
        ob_start();
        imagejpeg($image, null, 80);
        $bytes = (string)ob_get_clean();
        imagedestroy($image);
        return $bytes;
    }

    /**
     * Store a file as one of a submission's uploads, in /N/.
     *
     * @param \assign $assign The assignment.
     * @param \stdClass $submission The submission.
     * @param int $number The upload number N.
     * @param string $filename The file name.
     * @param string $content The bytes.
     * @return \stored_file
     */
    protected function add_upload(
        \assign $assign,
        \stdClass $submission,
        int $number,
        string $filename,
        string $content
    ): \stored_file {
        return get_file_storage()->create_file_from_string([
            'contextid' => $assign->get_context()->id,
            'component' => tour_manager::COMPONENT,
            'filearea' => tour_manager::FILEAREA,
            'itemid' => $submission->id,
            'filepath' => "/{$number}/",
            'filename' => $filename,
        ], $content);
    }

    /**
     * The reference a room uses for upload N.
     *
     * @param int $number Upload number.
     * @param string $filename File name.
     * @return array
     */
    protected function ref(int $number, string $filename): array {
        return ['area' => 'assets', 'itemid' => $number, 'filepath' => '/', 'filename' => $filename];
    }

    /**
     * A 360° photo room.
     *
     * @param string $id Scene id (also used to make the other ids unique).
     * @param array $file Reference of its photo.
     * @param array $items Interactables.
     * @return array
     */
    protected function photo_scene(string $id, array $file, array $items = []): array {
        return [
            'schema' => 'vrroom-scene/1',
            'id' => $id,
            'name' => 'Photo ' . $id,
            'environment' => ['type' => 'panorama', 'file' => $file, 'northoffset' => 0],
            'spawn' => [['id' => 'sp-' . $id, 'position' => [0, 0, 0], 'yaw' => 0]],
            'interactables' => $items,
        ];
    }

    /**
     * A point of interest (a hotspot placed by direction).
     *
     * @param string $id Item id.
     * @param array $media File references for its panel.
     * @return array
     */
    protected function hotspot(string $id, array $media = []): array {
        $item = [
            'id' => $id,
            'kind' => 'hotspot',
            'name' => 'Point ' . $id,
            'placement' => ['yaw' => 30, 'pitch' => 0, 'distance' => 4],
            'position' => \mod_vrroom\local\panorama::placement_position(30, 0, 4),
            'content' => ['title' => 'Point ' . $id, 'text' => 'About it', 'textformat' => '1'],
        ];
        if ($media) {
            $item['content']['media'] = $media;
        }
        return $item;
    }

    /**
     * An editor document for a save.
     *
     * @param array $scenes The rooms.
     * @param string $revision The revision the editor loaded.
     * @return string JSON
     */
    protected function document(array $scenes, string $revision): string {
        return json_encode([
            'schema' => editor_manager::DOC_SCHEMA,
            'revision' => $revision,
            'scenes' => $scenes,
            'graph' => ['schema' => 'vrroom-graph/1', 'hidden' => [], 'objectives' => [], 'rules' => []],
            'bindings' => [],
        ]);
    }

    /**
     * Give a student a stored tour of one photo with some points, without the web service.
     *
     * @param \assign $assign The assignment.
     * @param \stdClass $student The student.
     * @param int $points Points of interest.
     * @return \stdClass The submission.
     */
    protected function give_tour(\assign $assign, \stdClass $student, int $points = 1): \stdClass {
        $submission = $assign->get_user_submission($student->id, true);
        $ref = $this->ref(1, 'room.jpg');
        if (!tour_manager::find_ref_file($assign->get_context(), (int)$submission->id, $ref)) {
            $this->add_upload($assign, $submission, 1, 'room.jpg', $this->jpeg());
        }
        $items = [];
        for ($i = 1; $i <= $points; $i++) {
            $items[] = $this->hotspot('h' . $submission->id . '-' . $i);
        }
        tour_manager::store($assign, $submission, [$this->photo_scene('s' . $submission->id, $ref, $items)]);
        return $submission;
    }
}
