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
 * Privacy provider of the virtual tour submission plugin.
 *
 * @package    assignsubmission_vrtour
 * @copyright  2018 Adrian Greeve <adrian@moodle.com>
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_vrtour\privacy;

use assignsubmission_vrtour\local\tour_manager;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\writer;
use mod_assign\privacy\assign_plugin_request_data;

/**
 * Privacy provider: the tour of each submission (a table row) and its files. Modelled on assignsubmission_file's.
 *
 * @package    assignsubmission_vrtour
 * @copyright  2018 Adrian Greeve <adrian@moodle.com>
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \mod_assign\privacy\assignsubmission_provider,
    \mod_assign\privacy\assignsubmission_user_provider {
    /**
     * Metadata: the table and the files.
     *
     * @param collection $collection A list of information to add to.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('assignsubmission_vrtour', [
            'assignment' => 'privacy:metadata:assignmentid',
            'submission' => 'privacy:metadata:submissionpurpose',
            'scenesjson' => 'privacy:metadata:scenesjson',
            'numphotos' => 'privacy:metadata:numphotos',
            'numpoints' => 'privacy:metadata:numpoints',
            'timemodified' => 'privacy:metadata:timemodified',
        ], 'privacy:metadata:tablepurpose');
        $collection->link_subsystem('core_files', 'privacy:metadata:filepurpose');
        return $collection;
    }

    /**
     * Covered by the mod_assign provider and its query on assign_submission.
     *
     * @param int $userid The user ID that we are finding contexts for.
     * @param contextlist $contextlist A context list to add sql and params to for contexts.
     */
    public static function get_context_for_userid_within_submission(int $userid, contextlist $contextlist) {
        // Already fetched by mod_assign.
    }

    /**
     * Covered by the mod_assign provider and its queries.
     *
     * @param \mod_assign\privacy\useridlist $useridlist An object for obtaining user IDs of students.
     */
    public static function get_student_user_ids(\mod_assign\privacy\useridlist $useridlist) {
        // No need.
    }

    /**
     * Rows of this plugin always belong to an assign_submission row, which mod_assign lists.
     *
     * @param \core_privacy\local\request\userlist $userlist The userlist object.
     */
    public static function get_userids_from_context(\core_privacy\local\request\userlist $userlist) {
        // Not required.
    }

    /**
     * Export the tour of a submission: the rooms as data, and the files.
     *
     * @param assign_plugin_request_data $exportdata What to export.
     */
    public static function export_submission_user_data(assign_plugin_request_data $exportdata) {
        // Submissions are not exported for teachers who only marked them.
        if ($exportdata->get_user() != null) {
            return null;
        }
        $submission = $exportdata->get_pluginobject();
        $context = $exportdata->get_context();
        $row = tour_manager::get_row((int)$submission->id);
        if (!$row) {
            return;
        }
        $subcontext = array_merge($exportdata->get_subcontext(), [get_string('pluginname', 'assignsubmission_vrtour')]);
        writer::with_context($context)->export_data($subcontext, (object)[
            'photos' => (int)$row->numphotos,
            'points' => (int)$row->numpoints,
            'timemodified' => \core_privacy\local\request\transform::datetime((int)$row->timemodified),
            'tour' => tour_manager::scenes($row),
        ]);
        writer::with_context($context)->export_area_files(
            $subcontext,
            tour_manager::COMPONENT,
            tour_manager::FILEAREA,
            (int)$submission->id
        );
    }

    /**
     * Delete every tour of the context.
     *
     * @param assign_plugin_request_data $requestdata The context and the assignment.
     */
    public static function delete_submission_for_context(assign_plugin_request_data $requestdata) {
        global $DB;
        get_file_storage()->delete_area_files($requestdata->get_context()->id, tour_manager::COMPONENT, tour_manager::FILEAREA);
        $DB->delete_records(tour_manager::TABLE, ['assignment' => $requestdata->get_assign()->get_instance()->id]);
    }

    /**
     * Delete the tour of one submission.
     *
     * @param assign_plugin_request_data $deletedata The user, the context and the submission.
     */
    public static function delete_submission_for_userid(assign_plugin_request_data $deletedata) {
        global $DB;
        $submissionid = (int)$deletedata->get_pluginobject()->id;
        get_file_storage()->delete_area_files(
            $deletedata->get_context()->id,
            tour_manager::COMPONENT,
            tour_manager::FILEAREA,
            $submissionid
        );
        $DB->delete_records(tour_manager::TABLE, ['assignment' => $deletedata->get_assignid(), 'submission' => $submissionid]);
    }

    /**
     * Delete the tours of the given submissions of a context.
     *
     * @param assign_plugin_request_data $deletedata The context, the assignment and the submission ids.
     */
    public static function delete_submissions(assign_plugin_request_data $deletedata) {
        global $DB;
        if (empty($deletedata->get_submissionids())) {
            return;
        }
        [$sql, $params] = $DB->get_in_or_equal($deletedata->get_submissionids(), SQL_PARAMS_NAMED);
        get_file_storage()->delete_area_files_select(
            $deletedata->get_context()->id,
            tour_manager::COMPONENT,
            tour_manager::FILEAREA,
            $sql,
            $params
        );
        $params['assignid'] = $deletedata->get_assignid();
        $DB->delete_records_select(tour_manager::TABLE, "assignment = :assignid AND submission $sql", $params);
    }
}
