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

namespace assignsubmission_vrtour\local;

use assignsubmission_vrtour\tests\tour_test_helper;

/**
 * Tests of the tour checks a restore applies to the JSON of a backup.
 *
 * @package    assignsubmission_vrtour
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\assignsubmission_vrtour\local\tour_manager::class)]
final class tour_manager_test extends \advanced_testcase {
    use tour_test_helper;

    /**
     * A tour the editor could have saved is restored as it is; an empty one is empty.
     */
    public function test_restorable_scenes_accepts_a_good_tour(): void {
        $this->resetAfterTest();
        $scenes = [$this->photo_scene('a1', $this->ref(1, 'room.jpg'), [$this->hotspot('h1'), $this->hotspot('h2')])];
        $restored = tour_manager::restorable_scenes(json_encode($scenes));
        $this->assertNotNull($restored);
        $this->assertCount(1, $restored);
        $this->assertSame([1, 2], tour_manager::counts($restored));
        $this->assertSame([], tour_manager::restorable_scenes(''));
        $this->assertSame([], tour_manager::restorable_scenes('[]'));
    }

    /**
     * A tour that breaks one of the editor's rules is refused.
     *
     * @param string $case Which bad tour.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('bad_tour_provider')]
    public function test_restorable_scenes_refuses_a_bad_tour(string $case): void {
        $this->resetAfterTest();
        $scene = $this->photo_scene('a1', $this->ref(1, 'room.jpg'), [$this->hotspot('h1')]);
        switch ($case) {
            case 'notjson':
                $json = '{not json';
                break;
            case 'notlist':
                $json = json_encode(['a' => $scene]);
                break;
            case 'toolarge':
                $json = str_repeat(' ', \mod_vrroom\local\editor\editor_manager::MAX_DOCUMENT_BYTES + 1);
                break;
            case 'toomany':
                $scenes = [];
                for ($i = 1; $i <= tour_manager::MAX_PHOTOS + 1; $i++) {
                    $scenes[] = $this->photo_scene('a' . $i, $this->ref(1, 'room.jpg'));
                }
                $json = json_encode($scenes);
                break;
            case 'notphoto':
                $scene['environment'] = ['type' => 'preset', 'preset' => 'gallery'];
                unset($scene['interactables'][0]['placement']);
                $json = json_encode([$scene]);
                break;
            case 'kind':
                $scene['interactables'][0]['kind'] = 'grabbable';
                $json = json_encode([$scene]);
                break;
            case 'textformat':
                $scene['interactables'][0]['content']['textformat'] = '99';
                $json = json_encode([$scene]);
                break;
            default:
                $scene['schema'] = 'not-a-scene';
                $json = json_encode([$scene]);
        }
        $this->assertNull(tour_manager::restorable_scenes($json), $case);
    }

    /**
     * The bad tours of test_restorable_scenes_refuses_a_bad_tour.
     *
     * @return array
     */
    public static function bad_tour_provider(): array {
        return [
            'not JSON' => ['notjson'],
            'not a list' => ['notlist'],
            'larger than the editor saves' => ['toolarge'],
            'more photos than any tour may have' => ['toomany'],
            'a room that is not a 360° photo' => ['notphoto'],
            'a kind of object a tour never holds' => ['kind'],
            'an unknown text format' => ['textformat'],
            'a scene that breaks the scene rules' => ['scenerules'],
        ];
    }
}
