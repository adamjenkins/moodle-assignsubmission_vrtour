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
 * Backup of the virtual tour submission plugin.
 *
 * @package    assignsubmission_vrtour
 * @copyright  2012 NetSpot {@link http://www.netspot.com.au}
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Backs up the tour row of each submission and the files of its 'tour' area.
 *
 * @package    assignsubmission_vrtour
 * @copyright  2012 NetSpot {@link http://www.netspot.com.au}
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_assignsubmission_vrtour_subplugin extends backup_subplugin {
    /**
     * The subplugin information attached to the submission element.
     *
     * @return backup_subplugin_element
     */
    protected function define_submission_subplugin_structure() {
        $subplugin = $this->get_subplugin_element();
        $subpluginwrapper = new backup_nested_element($this->get_recommended_name());
        $subpluginelement = new backup_nested_element(
            'submission_vrtour',
            null,
            ['submission', 'scenesjson', 'revision', 'numphotos', 'numpoints', 'timemodified']
        );

        $subplugin->add_child($subpluginwrapper);
        $subpluginwrapper->add_child($subpluginelement);

        $subpluginelement->set_source_table('assignsubmission_vrtour', ['submission' => backup::VAR_PARENTID]);

        // The item id of the files is the submission id.
        $subpluginelement->annotate_files('assignsubmission_vrtour', 'tour', 'submission');
        return $subplugin;
    }
}
