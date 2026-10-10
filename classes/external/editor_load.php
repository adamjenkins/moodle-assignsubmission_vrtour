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
 * Load a submission's virtual tour for the room editor (mod_vrroom's editor shape).
 *
 * Without a submission id it is the current user's own submission, which they must be allowed to
 * edit. With the id of someone else's submission it is a read-only load for a grader, who must be
 * allowed to view that submission and to grade.
 *
 * @package    assignsubmission_vrtour
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class editor_load extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the assignment'),
            'submissionid' => new external_value(PARAM_INT, 'Submission to view read-only (0 = my own)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Load the document, the files and the limits.
     *
     * @param int $cmid Course module id.
     * @param int $submissionid Submission id, or 0 for the current user's own.
     * @return array
     */
    public static function execute(int $cmid, int $submissionid = 0): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid, 'submissionid' => $submissionid]);

        $assign = tour_manager::get_assign($params['cmid']);
        $context = $assign->get_context();
        self::validate_context($context);
        tour_manager::get_plugin($assign);

        $own = tour_manager::own_submission($assign, (int)$USER->id, false);
        if ($params['submissionid'] === 0 || ($own && (int)$own->id === $params['submissionid'])) {
            if ($assign->can_edit_submission($USER->id)) {
                return tour_manager::load($assign, tour_manager::require_editable_submission($assign), false);
            }
            if (!$own) {
                throw new \moodle_exception('errornoedit', 'assignsubmission_vrtour');
            }
            // A student may still look at their own tour after it can no longer be changed.
            return tour_manager::load($assign, tour_manager::require_viewable_submission($assign, (int)$own->id), true);
        }

        // Someone else's tour: graders only, and only one they may see.
        require_capability('mod/assign:grade', $context);
        $submission = tour_manager::require_viewable_submission($assign, $params['submissionid']);
        return tour_manager::load($assign, $submission, true);
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'documentjson' => new external_value(PARAM_RAW, 'JSON vrroom-editdoc/1 document'),
            'assetsjson' => new external_value(PARAM_RAW, 'JSON list of the files the tour can use'),
            'presetsjson' => new external_value(PARAM_RAW, 'JSON list of built-in rooms (always empty: photo rooms only)'),
            'questionsjson' => new external_value(PARAM_RAW, 'JSON list of questions (always empty)'),
            'infojson' => new external_value(PARAM_RAW, 'JSON object of ids, URLs, limits and editor features'),
        ]);
    }
}
