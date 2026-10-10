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
 * Moodle hooks of the virtual tour submission plugin.
 *
 * @package    assignsubmission_vrtour
 * @copyright  2012 NetSpot {@link http://www.netspot.com.au}
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Serve the files of a tour: the photos (and their smaller copies) and the media of its points.
 *
 * The access checks are those of assignsubmission_file_pluginfile. Raster images are shown inline
 * so the viewer can use them; everything else follows mod_vrroom's rule and is sent as a download.
 *
 * @param stdClass $course The course.
 * @param stdClass $cm The course module.
 * @param context $context The context.
 * @param string $filearea The file area.
 * @param array $args Item id, then the path.
 * @param bool $forcedownload Whether the request asks for a download.
 * @param array $options Options for send_stored_file().
 * @return bool false if the file is not found or may not be seen; does not return otherwise
 */
function assignsubmission_vrtour_pluginfile($course, $cm, context $context, $filearea, $args, $forcedownload, array $options = []) {
    global $DB, $CFG;

    if ($context->contextlevel != CONTEXT_MODULE || $filearea !== 'tour') {
        return false;
    }

    require_login($course, false, $cm);
    $itemid = (int)array_shift($args);
    $record = $DB->get_record('assign_submission', ['id' => $itemid], 'id, userid, assignment, groupid');
    if (!$record) {
        return false;
    }

    require_once($CFG->dirroot . '/mod/assign/locallib.php');
    $assign = new assign($context, $cm, $course);

    if ($assign->get_instance()->id != $record->assignment) {
        return false;
    }
    if ($assign->get_instance()->teamsubmission && !$assign->can_view_group_submission($record->groupid)) {
        return false;
    }
    if (!$assign->get_instance()->teamsubmission && !$assign->can_view_submission($record->userid)) {
        return false;
    }

    $relativepath = implode('/', $args);
    $fullpath = "/{$context->id}/assignsubmission_vrtour/{$filearea}/{$itemid}/{$relativepath}";

    $fs = get_file_storage();
    if (!($file = $fs->get_file_by_hash(sha1($fullpath))) || $file->is_directory()) {
        return false;
    }

    $force = \mod_vrroom\local\file_serving::force_download((string)$file->get_mimetype(), (bool)$forcedownload);
    send_stored_file($file, null, 0, $force, $options);
}
