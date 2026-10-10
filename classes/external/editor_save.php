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
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Validate and save the virtual tour of the current user's own submission.
 *
 * Only the student (or their group) whose submission it is, while they may edit it. Graders never save here.
 *
 * @package    assignsubmission_vrtour
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class editor_save extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the assignment'),
            'documentjson' => new external_value(PARAM_RAW, 'JSON vrroom-editdoc/1 document'),
        ]);
    }

    /**
     * Validate the document and store it; nothing is stored when anything is wrong.
     *
     * @param int $cmid Course module id.
     * @param string $documentjson The document.
     * @return array
     */
    public static function execute(int $cmid, string $documentjson): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'documentjson' => $documentjson,
        ]);

        $assign = tour_manager::get_assign($params['cmid']);
        self::validate_context($assign->get_context());
        tour_manager::get_plugin($assign);
        $submission = tour_manager::require_editable_submission($assign);
        return tour_manager::save($assign, $submission, $params['documentjson']);
    }

    /**
     * Return structure (the shape of mod_vrroom_editor_save).
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'revision' => new external_value(PARAM_ALPHANUM, 'Revision of what is now stored'),
            'graphversion' => new external_value(PARAM_INT, 'Always 0: a tour has no tasks'),
            'scenesupdated' => new external_value(PARAM_BOOL, 'Whether the photos changed'),
            'graphupdated' => new external_value(PARAM_BOOL, 'Always false'),
            'customised' => new external_value(PARAM_INT, 'Time of this save'),
        ]);
    }
}
