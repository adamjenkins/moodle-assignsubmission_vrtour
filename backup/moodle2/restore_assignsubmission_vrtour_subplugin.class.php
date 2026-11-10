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
 * Restore of the virtual tour submission plugin.
 *
 * @package    assignsubmission_vrtour
 * @copyright  2012 NetSpot {@link http://www.netspot.com.au}
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restores the tour row of each submission and the files of its 'tour' area.
 *
 * The rooms name their files by upload number inside the submission's own area, never by id, so
 * the stored JSON needs no remapping.
 *
 * @package    assignsubmission_vrtour
 * @copyright  2012 NetSpot {@link http://www.netspot.com.au}
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_assignsubmission_vrtour_subplugin extends restore_subplugin {
    /**
     * The paths handled at submission level.
     *
     * @return array
     */
    protected function define_submission_subplugin_structure() {
        $paths = [];
        $elename = $this->get_namefor('submission');
        $elepath = $this->get_pathfor('/submission_vrtour');
        $paths[] = new restore_path_element($elename, $elepath);
        return $paths;
    }

    /**
     * Restore one submission_vrtour element.
     *
     * @param array $data The element.
     * @return void
     */
    public function process_assignsubmission_vrtour_submission($data) {
        global $DB;

        $data = (object)$data;
        $data->assignment = $this->get_new_parentid('assign');
        $oldsubmissionid = $data->submission;
        // The mapping is set by the assign restore when a submission node is processed.
        $data->submission = $this->get_mappingid('submission', $data->submission);
        if (!$data->submission) {
            return;
        }
        // The backup file is untrusted: a tour that breaks the editor's rules is restored empty, and the
        // counts are taken from the rooms, never from the file.
        $json = (string)($data->scenesjson ?? '');
        $scenes = \assignsubmission_vrtour\local\tour_manager::restorable_scenes($json);
        if ($scenes === null || !$scenes) {
            $json = '';
            $scenes = [];
        }
        [$data->numphotos, $data->numpoints] = \assignsubmission_vrtour\local\tour_manager::counts($scenes);
        $data->scenesjson = $json;
        $data->revision = sha1($json);

        $DB->insert_record('assignsubmission_vrtour', $data);

        $this->add_related_files('assignsubmission_vrtour', 'tour', 'submission', null, $oldsubmissionid);
    }
}
