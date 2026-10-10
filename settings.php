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
 * Site settings of the virtual tour submission plugin.
 *
 * @package    assignsubmission_vrtour
 * @copyright  2012 NetSpot {@link http://www.netspot.com.au}
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Off by default: a virtual tour is a special kind of assignment.
$settings->add(new admin_setting_configcheckbox(
    'assignsubmission_vrtour/default',
    new lang_string('default', 'assignsubmission_vrtour'),
    new lang_string('default_help', 'assignsubmission_vrtour'),
    0
));

if (isset($CFG->maxbytes)) {
    $maxbytes = get_config('assignsubmission_vrtour', 'maxbytes');
    $settings->add(new admin_setting_configselect(
        'assignsubmission_vrtour/maxbytes',
        new lang_string('maxbytes', 'assignsubmission_vrtour'),
        new lang_string('configmaxbytes', 'assignsubmission_vrtour'),
        $CFG->maxbytes,
        get_max_upload_sizes($CFG->maxbytes, 0, 0, $maxbytes)
    ));
}

// The 360° photo limits are mod_vrroom's, shared with teachers' photo rooms.
$settings->add(new admin_setting_heading(
    'assignsubmission_vrtour/photolimits',
    new lang_string('photolimitsheading', 'assignsubmission_vrtour'),
    new lang_string(
        'photolimitssite',
        'assignsubmission_vrtour',
        (new moodle_url('/admin/settings.php', ['section' => 'modsettingvrroom']))->out()
    )
));
