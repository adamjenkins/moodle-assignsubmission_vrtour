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
 * Web services of the virtual tour submission plugin: the tour editor's load and save.
 *
 * @package    assignsubmission_vrtour
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'assignsubmission_vrtour_editor_load' => [
        'classname' => 'assignsubmission_vrtour\external\editor_load',
        'description' => 'Load the virtual tour of a submission for the tour editor.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/assign:submit, mod/assign:grade',
    ],
    'assignsubmission_vrtour_editor_save' => [
        'classname' => 'assignsubmission_vrtour\external\editor_save',
        'description' => 'Validate and save the virtual tour of the current user\'s submission.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/assign:submit',
    ],
];
