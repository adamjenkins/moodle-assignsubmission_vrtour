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
 * The library class of the virtual tour submission plugin.
 *
 * The structure follows assignsubmission_file's locallib.php.
 *
 * @package    assignsubmission_vrtour
 * @copyright  2012 NetSpot {@link http://www.netspot.com.au}
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use assignsubmission_vrtour\local\tour_manager;
use mod_assign\output\assign_header;
use mod_vrroom\local\panorama;

/**
 * A student's virtual tour as an assignment submission: 360° photos with points of interest.
 *
 * The student builds the tour in mod_vrroom's room editor (view_page('edit')), which saves through
 * this plugin's web services straight into the assignsubmission_vrtour row. The submission form only
 * shows a summary and a link to the editor; save() refreshes the counts and logs the change.
 *
 * @package    assignsubmission_vrtour
 * @copyright  2012 NetSpot {@link http://www.netspot.com.au}
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_submission_vrtour extends assign_submission_plugin {
    /**
     * The name of the plugin.
     *
     * @return string
     */
    public function get_name() {
        return get_string('pluginname', 'assignsubmission_vrtour');
    }

    /**
     * The tour settings of an assignment.
     *
     * @param MoodleQuickForm $mform The form to add elements to.
     * @return void
     */
    public function get_settings(MoodleQuickForm $mform) {
        global $CFG, $COURSE;

        $settings = tour_manager::settings($this);
        $enabled = 'assignsubmission_vrtour_enabled';

        $options = [];
        for ($i = 1; $i <= tour_manager::MAX_PHOTOS; $i++) {
            $options[$i] = $i;
        }
        $name = get_string('maxphotos', 'assignsubmission_vrtour');
        $mform->addElement('select', 'assignsubmission_vrtour_maxphotos', $name, $options);
        $mform->addHelpButton('assignsubmission_vrtour_maxphotos', 'maxphotos', 'assignsubmission_vrtour');
        $mform->setDefault('assignsubmission_vrtour_maxphotos', $settings['maxphotos']);
        $mform->hideIf('assignsubmission_vrtour_maxphotos', $enabled, 'notchecked');

        $options = [];
        for ($i = 0; $i <= tour_manager::MAX_MINPOINTS; $i++) {
            $options[$i] = $i;
        }
        $name = get_string('minpoints', 'assignsubmission_vrtour');
        $mform->addElement('select', 'assignsubmission_vrtour_minpoints', $name, $options);
        $mform->addHelpButton('assignsubmission_vrtour_minpoints', 'minpoints', 'assignsubmission_vrtour');
        $mform->setDefault('assignsubmission_vrtour_minpoints', $settings['minpoints']);
        $mform->hideIf('assignsubmission_vrtour_minpoints', $enabled, 'notchecked');

        foreach (['image', 'audio', 'video'] as $kind) {
            $name = 'assignsubmission_vrtour_allow' . $kind;
            $mform->addElement('advcheckbox', $name, get_string('allow' . $kind, 'assignsubmission_vrtour'));
            if ($kind === 'image') {
                $mform->addHelpButton($name, 'allowimage', 'assignsubmission_vrtour');
            }
            $mform->setDefault($name, $settings['allow' . $kind]);
            $mform->hideIf($name, $enabled, 'notchecked');
        }

        $choices = get_max_upload_sizes($CFG->maxbytes, $COURSE->maxbytes, get_config('assignsubmission_vrtour', 'maxbytes'));
        $name = get_string('maxbytes', 'assignsubmission_vrtour');
        $mform->addElement('select', 'assignsubmission_vrtour_maxbytes', $name, $choices);
        $mform->addHelpButton('assignsubmission_vrtour_maxbytes', 'maxbytes', 'assignsubmission_vrtour');
        $mform->setDefault('assignsubmission_vrtour_maxbytes', $settings['maxbytes']);
        $mform->hideIf('assignsubmission_vrtour_maxbytes', $enabled, 'notchecked');

        // The photo limits are the site's, set in mod_vrroom.
        $limits = panorama::limits();
        $text = get_string('photolimits', 'assignsubmission_vrtour', (object)[
            'maxwidth' => (int)$limits['maxwidth'],
            'maxsize' => display_size((int)$limits['maxbytes']),
        ]);
        if (has_capability('moodle/site:config', context_system::instance())) {
            $text .= ' ' . html_writer::link(
                new moodle_url('/admin/settings.php', ['section' => 'modsettingvrroom']),
                get_string('photolimitslink', 'assignsubmission_vrtour')
            );
        }
        $name = get_string('photolimitsheading', 'assignsubmission_vrtour');
        $mform->addElement('static', 'assignsubmission_vrtour_photolimits', $name, $text);
        $mform->hideIf('assignsubmission_vrtour_photolimits', $enabled, 'notchecked');
    }

    /**
     * Save the tour settings.
     *
     * @param stdClass $data The form data.
     * @return bool
     */
    public function save_settings(stdClass $data) {
        $defaults = tour_manager::DEFAULTS;
        $value = function (string $name) use ($data, $defaults): int {
            $field = 'assignsubmission_vrtour_' . $name;
            return isset($data->$field) ? (int)$data->$field : (int)$defaults[$name];
        };
        $this->set_config('maxphotos', max(1, min(tour_manager::MAX_PHOTOS, $value('maxphotos'))));
        $this->set_config('minpoints', max(0, min(tour_manager::MAX_MINPOINTS, $value('minpoints'))));
        foreach (['allowimage', 'allowaudio', 'allowvideo'] as $name) {
            $this->set_config($name, $value($name) ? 1 : 0);
        }
        $this->set_config('maxbytes', max(0, $value('maxbytes')));
        return true;
    }

    /**
     * The submission form shows a summary of the tour and a link to the tour editor.
     *
     * @param mixed $submission stdClass|null
     * @param MoodleQuickForm $mform The form.
     * @param stdClass $data The form data.
     * @return bool
     */
    public function get_form_elements($submission, MoodleQuickForm $mform, stdClass $data) {
        global $USER;
        return $this->get_form_elements_for_user($submission, $mform, $data, (int)$USER->id);
    }

    /**
     * The submission form shows a summary of the tour and, to the student whose tour it is, a link to the tour editor.
     *
     * @param mixed $submission stdClass|null
     * @param MoodleQuickForm $mform The form.
     * @param stdClass $data The form data.
     * @param int $userid The user whose submission is edited.
     * @return bool
     */
    public function get_form_elements_for_user($submission, MoodleQuickForm $mform, stdClass $data, $userid) {
        global $OUTPUT, $USER;

        $scenes = $submission ? tour_manager::scenes(tour_manager::get_row((int)$submission->id)) : [];
        $context = [
            'started' => (bool)$scenes,
            'items' => $this->checklist($scenes),
            'editurl' => null,
            'editlabel' => '',
            'note' => '',
            'owneronly' => '',
            'hidesave' => 0,
        ];
        $cmid = (int)$this->assignment->get_course_module()->id;
        if ((int)$userid === (int)$USER->id && $this->assignment->can_edit_submission($USER->id)) {
            $context['editurl'] = tour_manager::edit_url($cmid)->out(false);
            $context['editlabel'] = get_string($scenes ? 'edittourcontinue' : 'edittourstart', 'assignsubmission_vrtour');
            if ($this->other_submission_plugins_enabled()) {
                // The form has other fields to lose, and its Save is needed for them.
                $context['note'] = get_string('edittournote', 'assignsubmission_vrtour');
            } else if ($this->assignment->get_instance()->submissiondrafts) {
                // The editor's save already makes the draft: one way forward, into the editor.
                $context['hidesave'] = 1;
            }
        } else {
            $context['owneronly'] = get_string('owneronly', 'assignsubmission_vrtour');
        }
        $html = $OUTPUT->render_from_template('assignsubmission_vrtour/form_card', $context);
        $mform->addElement('static', 'assignsubmission_vrtour_summary', $this->get_name(), $html);
        return true;
    }

    /**
     * What a tour still needs before it can be handed in, as checklist items.
     *
     * @param array $scenes The tour's rooms.
     * @return array[] {done, label, count}
     */
    private function checklist(array $scenes): array {
        [$photos, $points] = tour_manager::counts($scenes);
        $min = tour_manager::settings($this)['minpoints'];
        $items = [[
            'done' => $photos > 0,
            'label' => get_string('needphoto', 'assignsubmission_vrtour'),
            'count' => get_string('sofar', 'assignsubmission_vrtour', $photos),
        ]];
        if ($min > 0) {
            $items[] = [
                'done' => $points >= $min,
                'label' => get_string('needpoints', 'assignsubmission_vrtour', $min),
                'count' => get_string('sofar', 'assignsubmission_vrtour', $points),
            ];
        }
        return $items;
    }

    /**
     * What the tour still needs, as one phrase ("1 more point of interest"), or null when it is ready.
     *
     * @param array $scenes The tour's rooms.
     * @return string|null
     */
    private function still_needed(array $scenes): ?string {
        [$photos, $points] = tour_manager::counts($scenes);
        $min = tour_manager::settings($this)['minpoints'];
        $needs = [];
        if ($photos < 1) {
            $needs[] = get_string('needaphoto', 'assignsubmission_vrtour');
        }
        if ($points < $min) {
            $missing = $min - $points;
            $needs[] = $missing === 1
                ? get_string('needonepoint', 'assignsubmission_vrtour')
                : get_string('needmorepoints', 'assignsubmission_vrtour', $missing);
        }
        return $needs ? implode(', ', $needs) : null;
    }

    /**
     * Refresh the counts of the tour (stored by the editor) and log the change.
     *
     * When the assignment does not use drafts, saving submits: a tour without a photo or with fewer
     * points of interest than the teacher asks for is refused here, as precheck_submission() does
     * for submitting a draft.
     *
     * @param stdClass $submission The assign_submission row.
     * @param stdClass $data The form data.
     * @return bool
     */
    public function save(stdClass $submission, stdClass $data) {
        $row = tour_manager::get_row((int)$submission->id);
        $scenes = tour_manager::scenes($row);
        if (!$this->assignment->get_instance()->submissiondrafts) {
            $problem = $this->problem($scenes);
            if ($problem !== null) {
                $this->set_error($problem);
                return false;
            }
        }
        $isnew = $row === null;
        $row = tour_manager::store($this->assignment, $submission, $scenes);
        $this->log_change($submission, $row, $isnew);
        return true;
    }

    /**
     * Log a change of a submission's tour (submission_created for its first row, else submission_updated).
     *
     * @param stdClass $submission The assign_submission row.
     * @param stdClass $row The tour row as stored.
     * @param bool $isnew Whether the row was created by this change.
     */
    public function log_change(stdClass $submission, stdClass $row, bool $isnew): void {
        global $DB;

        $params = [
            'context' => context_module::instance($this->assignment->get_course_module()->id),
            'courseid' => $this->assignment->get_course()->id,
            'objectid' => $row->id,
        ];
        $groupname = null;
        $groupid = 0;
        // The group name, as the other fields are not transcribed in the logs.
        if (empty($submission->userid) && !empty($submission->groupid)) {
            $groupname = $DB->get_field('groups', 'name', ['id' => $submission->groupid], MUST_EXIST);
            $groupid = $submission->groupid;
        } else {
            $params['relateduserid'] = $submission->userid;
        }
        if ($this->assignment->is_blind_marking()) {
            $params['anonymous'] = 1;
        }
        $params['other'] = [
            'submissionid' => $submission->id,
            'submissionattempt' => $submission->attemptnumber,
            'submissionstatus' => $submission->status,
            'photocount' => (int)$row->numphotos,
            'pointcount' => (int)$row->numpoints,
            'groupid' => $groupid,
            'groupname' => $groupname,
        ];

        $event = $isnew
            ? \assignsubmission_vrtour\event\submission_created::create($params)
            : \assignsubmission_vrtour\event\submission_updated::create($params);
        $event->set_assign($this->assignment);
        $event->trigger();
    }

    /**
     * A tour without a photo, or with fewer points of interest than the teacher asks for, cannot be submitted.
     *
     * @param stdClass|false|null $submission The assign_submission row.
     * @return bool|string true, or the reason
     */
    public function precheck_submission($submission) {
        if (!$submission) {
            return true;
        }
        $problem = $this->problem(tour_manager::scenes(tour_manager::get_row((int)$submission->id)));
        return $problem ?? true;
    }

    /**
     * Why a tour cannot be submitted.
     *
     * Also checked by the tour editor's save of a submitted tour when the assignment does not use drafts.
     *
     * @param array $scenes The tour's rooms.
     * @return string|null
     */
    public function problem(array $scenes): ?string {
        [$photos, $points] = tour_manager::counts($scenes);
        if ($photos < 1) {
            return get_string('errornophoto', 'assignsubmission_vrtour');
        }
        $min = tour_manager::settings($this)['minpoints'];
        if ($points < $min) {
            return get_string('errorminpoints', 'assignsubmission_vrtour', (object)['min' => $min, 'count' => $points]);
        }
        return null;
    }

    /**
     * Remove the tour of a submission.
     *
     * @param stdClass $submission The submission.
     * @return bool
     */
    public function remove(stdClass $submission) {
        global $DB;
        get_file_storage()->delete_area_files(
            $this->assignment->get_context()->id,
            tour_manager::COMPONENT,
            tour_manager::FILEAREA,
            $submission->id
        );
        $row = tour_manager::get_row((int)$submission->id);
        if ($row) {
            $row->scenesjson = '';
            $row->revision = sha1('');
            $row->numphotos = 0;
            $row->numpoints = 0;
            $row->timemodified = time();
            $DB->update_record(tour_manager::TABLE, $row);
        }
        return true;
    }

    /**
     * The files of the tour, for download-all and portfolios: every upload (not the smaller copies)
     * and the tour itself as tour.json.
     *
     * @param stdClass $submission The submission.
     * @param stdClass $user The user record (unused).
     * @return array Files indexed by name; tour.json as [content].
     */
    public function get_files(stdClass $submission, stdClass $user) {
        $result = [];
        $row = tour_manager::get_row((int)$submission->id);
        $scenes = tour_manager::scenes($row);
        if (!$scenes) {
            return $result;
        }
        $result['tour.json'] = [json_encode($scenes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)];

        $files = get_file_storage()->get_area_files(
            $this->assignment->get_context()->id,
            tour_manager::COMPONENT,
            tour_manager::FILEAREA,
            $submission->id,
            'timemodified, id',
            false
        );
        foreach ($files as $file) {
            if (tour_manager::upload_number($file) === null) {
                continue;
            }
            if (isset($submission->exportfullpath) && $submission->exportfullpath == false) {
                $result[$file->get_filename()] = $file;
            } else {
                $result[ltrim($file->get_filepath(), '/') . $file->get_filename()] = $file;
            }
        }
        return $result;
    }

    /**
     * The summary in the submission status table: photos and points of interest, and a link to the viewer.
     *
     * @param stdClass $submission The submission.
     * @param bool $showviewlink Set to true when there is a tour to view.
     * @return string
     */
    public function view_summary(stdClass $submission, &$showviewlink) {
        $showviewlink = !$this->is_empty($submission);
        $html = s($this->summary_text($submission));
        if ($showviewlink) {
            $url = tour_manager::view_url((int)$this->assignment->get_course_module()->id, (int)$submission->id);
            $html .= ' ' . html_writer::link($url, get_string('opentour', 'assignsubmission_vrtour'));
            $html .= $this->readiness_html(tour_manager::scenes(tour_manager::get_row((int)$submission->id)));
        }
        return $html;
    }

    /**
     * What the assignment asks for, in one sentence.
     *
     * @return string Plain text.
     */
    private function requirements_text(): string {
        $settings = tour_manager::settings($this);
        if ($settings['minpoints'] > 0) {
            return get_string('editorrequirements', 'assignsubmission_vrtour', (object)[
                'minpoints' => $settings['minpoints'],
                'maxphotos' => $settings['maxphotos'],
            ]);
        }
        return get_string('editorrequirementsnomin', 'assignsubmission_vrtour', $settings['maxphotos']);
    }

    /**
     * Whether the tour could be handed in now, as a short line with an icon.
     *
     * @param array $scenes The tour's rooms.
     * @return string HTML.
     */
    private function readiness_html(array $scenes): string {
        global $OUTPUT;

        [$photos, $points] = tour_manager::counts($scenes);
        $min = tour_manager::settings($this)['minpoints'];
        $a = (object)['points' => $points, 'min' => $min];
        if ($photos < 1) {
            $ok = false;
            $text = get_string('checknophoto', 'assignsubmission_vrtour');
        } else if ($points < $min) {
            $ok = false;
            $text = get_string('checkshort', 'assignsubmission_vrtour', $a);
        } else if ($min > 0) {
            $ok = true;
            $text = get_string('checkok', 'assignsubmission_vrtour', $a);
        } else {
            return '';
        }
        $icon = $OUTPUT->pix_icon($ok ? 'i/valid' : 'i/warning', '', 'moodle', ['class' => $ok ? 'text-success' : 'text-warning']);
        return html_writer::div($icon . s($text), 'assignsubmission_vrtour-readiness small mt-1', [
            'data-region' => 'assignsubmission_vrtour-readiness',
            'data-ready' => $ok ? '1' : '0',
        ]);
    }

    /**
     * Whether another submission type (comments aside) is on in this assignment, so the form has other fields.
     *
     * @return bool
     */
    private function other_submission_plugins_enabled(): bool {
        foreach ($this->assignment->get_submission_plugins() as $plugin) {
            if (
                $plugin->get_type() !== 'vrtour' && $plugin->get_type() !== 'comments'
                    && $plugin->is_enabled() && $plugin->is_visible() && $plugin->allow_submissions()
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * A readable outline of the tour for graders and screen readers: a one-line verdict, then each photo
     * with a thumbnail, its points of interest with the start of their text, its links, and the photos
     * no link leads to.
     *
     * @param array $scenes The tour's rooms.
     * @param int $submissionid The submission, for the thumbnails.
     * @return string HTML.
     */
    private function outline_html(array $scenes, int $submissionid): string {
        global $OUTPUT;

        $scenes = array_values(array_filter($scenes, 'is_array'));
        $outline = tour_manager::outline($scenes);
        if (!$outline) {
            return '';
        }
        $context = $this->assignment->get_context();
        [$photocount, $pointcount] = tour_manager::counts($scenes);
        $min = tour_manager::settings($this)['minpoints'];
        $unreachable = 0;
        $photos = [];
        foreach ($outline as $i => $photo) {
            $unreachable += $photo['reachable'] ? 0 : 1;
            $points = [];
            foreach ($photo['points'] as $point) {
                $points[] = [
                    'title' => $point['title'],
                    'kind' => get_string('kind_' . $point['kind'], 'assignsubmission_vrtour'),
                    'text' => $point['text'],
                ];
            }
            $links = [];
            foreach ($photo['links'] as $target) {
                $links[] = ['label' => get_string('outlinelink', 'assignsubmission_vrtour', $target)];
            }
            $ref = $scenes[$i]['environment']['file'] ?? null;
            $photos[] = [
                'title' => get_string('outlinephoto', 'assignsubmission_vrtour', (object)[
                    'number' => $photo['number'],
                    'name' => $photo['name'],
                ]),
                'thumb' => is_array($ref) ? tour_manager::thumb_url($context, $submissionid, $ref) : null,
                'start' => $photo['start'],
                'reachable' => $photo['reachable'],
                'nopoints' => !$photo['points'],
                'points' => $points,
                'links' => $links,
            ];
        }
        $verdict = get_string($min > 0 ? 'verdictcounts' : 'verdictcountsnomin', 'assignsubmission_vrtour', (object)[
            'photos' => $photocount,
            'points' => $pointcount,
            'min' => $min,
        ]);
        return $OUTPUT->render_from_template('assignsubmission_vrtour/outline', [
            'ready' => $photocount > 0 && $pointcount >= $min,
            'verdict' => $verdict,
            'unreachable' => $unreachable ? get_string('verdictunreachable', 'assignsubmission_vrtour', $unreachable) : '',
            'photos' => $photos,
        ]);
    }

    /**
     * "N photos, M points of interest", or "No tour yet".
     *
     * @param stdClass|null $submission The submission.
     * @return string Plain text.
     */
    private function summary_text(?stdClass $submission): string {
        $row = $submission ? tour_manager::get_row((int)$submission->id) : null;
        if (!$row || (int)$row->numphotos < 1) {
            return get_string('notouryet', 'assignsubmission_vrtour');
        }
        return get_string('summary', 'assignsubmission_vrtour', (object)[
            'photos' => (int)$row->numphotos,
            'points' => (int)$row->numpoints,
        ]);
    }

    /**
     * The summary of the tour for notification messages.
     *
     * @param stdClass $submission The submission.
     * @return array [plain text, HTML]
     */
    public function submission_summary_for_messages(stdClass $submission): array {
        if ($this->is_empty($submission)) {
            return ['', ''];
        }
        $text = $this->summary_text($submission);
        return [
            $this->get_name() . ': ' . $text . "\n",
            html_writer::tag('h3', s($this->get_name())) . html_writer::tag('p', s($text)),
        ];
    }

    /**
     * The tour in the built-in viewer.
     *
     * The config is embedded as JSON in the page (it is too large for js_call_amd's arguments) and
     * assignsubmission_vrtour/viewer hands it to vrroomruntime_builtin/standalone.
     *
     * @param stdClass $submission The submission.
     * @return string
     */
    public function view(stdClass $submission) {
        global $PAGE;

        $config = tour_manager::viewer_config($this->assignment, $submission);
        if ($config === null) {
            return html_writer::tag('p', get_string('notouryet', 'assignsubmission_vrtour'));
        }
        $rootid = html_writer::random_id('assignsubmission_vrtour-viewer-');
        $configid = $rootid . '-config';
        $json = json_encode(
            $config,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        $PAGE->requires->js_call_amd('assignsubmission_vrtour/viewer', 'init', [['rootid' => $rootid, 'configid' => $configid]]);

        $outline = $this->outline_html(tour_manager::scenes(tour_manager::get_row((int)$submission->id)), (int)$submission->id);
        return $outline . html_writer::div('', 'vrroom-root assignsubmission_vrtour-viewer', [
                'id' => $rootid,
                'data-region' => 'vrroom-root',
            ]) .
            html_writer::tag('script', $json, ['type' => 'application/json', 'id' => $configid]);
    }

    /**
     * The plugin's own pages: 'edit' (the tour editor of the current user's submission), 'done' (where
     * the editor's Done leads: back to the assignment with a note on what to do next) and 'view'
     * (a full-page viewer of a submission, for the student and the graders).
     *
     * @param string $action edit, done or view.
     * @return string
     */
    public function view_page($action) {
        if ($action === 'edit') {
            return $this->view_editor_page();
        }
        if ($action === 'done') {
            $this->leave_editor();
        }
        if ($action === 'practice') {
            $this->start_with_practice_photo();
        }
        if ($action === 'view') {
            return $this->view_viewer_page();
        }
        return '';
    }

    /**
     * Keep 5.3's sticky "Previous / Next" activity bar off the plugin's full-width pages, where it would
     * cover the editor or the viewer. Moodle 5.2 has no such bar (and no such method).
     */
    private function hide_navigation_footer(): void {
        global $PAGE;
        if (method_exists($PAGE, 'set_show_navigation_footer')) {
            $PAGE->set_show_navigation_footer(false);
        }
    }

    /**
     * The tour editor page.
     *
     * While the tour has no photo, a start card covers the editor: one main action (which opens the
     * editor's own "New photo room"), practice photos, and how to take a photo without a 360° camera.
     * A small script removes the card once the editor lists a room, and brings it back if every room
     * is deleted.
     *
     * @return string
     */
    private function view_editor_page(): string {
        global $OUTPUT, $PAGE;

        $assign = $this->assignment;
        $cm = $assign->get_course_module();
        require_login($assign->get_course(), false, $cm);
        tour_manager::get_plugin($assign);
        // Opening the editor creates no submission: the first upload or save does.
        $submission = tour_manager::existing_editable_submission($assign);
        $cmid = (int)$cm->id;

        $doneurl = tour_manager::done_url($cmid);
        $row = $submission ? tour_manager::get_row((int)$submission->id) : null;
        $hasphoto = $row && (int)$row->numphotos > 0;
        $rootid = 'vrroom-editor-' . $cmid;
        $wrapid = 'assignsubmission_vrtour-editorwrap-' . $cmid;
        $PAGE->add_body_class('vrroom-editor-page');
        if (!$hasphoto) {
            $PAGE->add_body_class('assignsubmission-vrtour-empty');
        }
        $this->hide_navigation_footer();
        [, $points] = tour_manager::counts(tour_manager::scenes($row));
        $tips = [
            'first' => get_string('tipfirstpoint', 'assignsubmission_vrtour'),
            'one' => get_string('tipmorepoint', 'assignsubmission_vrtour'),
            'more' => get_string('tipmorepoints', 'assignsubmission_vrtour', '__N__'),
            'ready' => get_string('tipready', 'assignsubmission_vrtour'),
            'min' => tour_manager::settings($this)['minpoints'],
            'points' => $points,
        ];
        $PAGE->requires->js_call_amd('mod_vrroom/editor_app', 'init', [[
            'cmid' => $cmid,
            'rootid' => $rootid,
            'backend' => [
                'load' => 'assignsubmission_vrtour_editor_load',
                'save' => 'assignsubmission_vrtour_editor_save',
                'args' => ['cmid' => $cmid],
                'uploadForm' => 'assignsubmission_vrtour\\form\\asset_upload',
                'uploadArgs' => ['cmid' => $cmid],
                'doneurl' => $doneurl->out(false),
            ],
        ]]);
        // The start card's button opens the editor's own "New photo room"; the card goes once a room exists.
        $PAGE->requires->js_amd_inline('
            require([], function() {
                var wrap = document.getElementById(' . json_encode($wrapid) . ');
                if (!wrap) {
                    return;
                }
                var tips = ' . json_encode($tips) . ';
                var tiptext = document.querySelector(\'[data-region="assignsubmission_vrtour-tiptext"]\');
                var showtip = function() {
                    if (!tiptext) {
                        return;
                    }
                    var missing = tips.min - tips.points;
                    var html = tips.ready;
                    if (tips.points < 1 && tips.min > 0) {
                        html = tips.first;
                    } else if (missing === 1) {
                        html = tips.one;
                    } else if (missing > 1) {
                        html = tips.more.replace("__N__", String(missing));
                    }
                    if (tiptext.innerHTML !== html) {
                        tiptext.innerHTML = html;
                    }
                };
                var update = function() {
                    var empty = !wrap.querySelector(\'[data-action="vrroom-editor-scene"]\');
                    if (wrap.classList.contains("is-empty") !== empty) {
                        wrap.classList.toggle("is-empty", empty);
                        document.body.classList.toggle("assignsubmission-vrtour-empty", empty);
                    }
                };
                // VR room\'s editor reports its live counts (from its 2026-11-26 prototype on).
                wrap.addEventListener("vrroom-editor:requirements", function(e) {
                    (e.detail && e.detail.items || []).forEach(function(item) {
                        if (item.kind === "pointsofinterest") {
                            tips.points = Number(item.count) || 0;
                        }
                    });
                    showtip();
                });
                showtip();
                new MutationObserver(function() {
                    window.requestAnimationFrame(update);
                }).observe(wrap.querySelector(\'[data-region="vrroom-editor-outline"]\') || wrap,
                    {childList: true, subtree: true});
                wrap.addEventListener("click", function(e) {
                    if (!e.target.closest(\'[data-action="assignsubmission_vrtour-start"]\')) {
                        return;
                    }
                    e.preventDefault();
                    var add = wrap.querySelector(\'[data-action="vrroom-editor-addphotoscene"]\');
                    if (add) {
                        add.click();
                    }
                });
            });');

        $renderer = $assign->get_renderer();
        $o = $renderer->render(new assign_header(
            $assign->get_instance(),
            $assign->get_context(),
            false,
            $cmid,
            get_string('edittour', 'assignsubmission_vrtour')
        ));
        $o .= $this->editor_guide_html();
        // The tip's text is a lang string with a little markup, chosen and kept current by the script above.
        $o .= html_writer::div(
            $OUTPUT->pix_icon('i/info', '') .
            html_writer::span(get_string('tiplabel', 'assignsubmission_vrtour'), 'visually-hidden') .
            html_writer::span('', '', ['data-region' => 'assignsubmission_vrtour-tiptext']),
            'assignsubmission_vrtour-tip alert alert-light border py-2 mb-2',
            ['data-region' => 'assignsubmission_vrtour-tip', 'aria-live' => 'polite']
        );
        $start = $OUTPUT->render_from_template('assignsubmission_vrtour/start_card', [
            'requirements' => $this->requirements_text(),
            'practice' => $this->practice_choices($cmid),
            'nocamera' => ['open' => true],
        ]);
        $editor = $renderer->render_from_template('mod_vrroom/editor_page', [
            'rootid' => $rootid,
            'cmid' => $cmid,
            // No Preview link until the tour has a photo to show.
            'viewurl' => $hasphoto ? tour_manager::view_url($cmid, (int)$submission->id)->out(false) : null,
        ]);
        $o .= html_writer::div($start . $editor, 'assignsubmission_vrtour-editorwrap' . ($hasphoto ? '' : ' is-empty'), [
            'id' => $wrapid,
        ]);
        // VR room's editor shows its own exit in the toolbar from the version that reads info.exit; this
        // link is for older editors and is hidden by styles.css when the toolbar's exit is shown.
        $o .= html_writer::div(
            html_writer::link($doneurl, get_string('backtosubmission', 'assignsubmission_vrtour')),
            'mt-3 assignsubmission_vrtour-back'
        );
        $o .= $renderer->render_footer();
        return $o;
    }

    /**
     * The practice photos a student may start a tour with, while the tour has room for another photo.
     *
     * @param int $cmid Course module id.
     * @return array[] {name, thumb, url}
     */
    private function practice_choices(int $cmid): array {
        global $OUTPUT;
        $choices = [];
        foreach (tour_manager::PRACTICE_PHOTOS as $key) {
            $choices[] = [
                'name' => get_string('practice_' . $key, 'assignsubmission_vrtour'),
                'thumb' => $OUTPUT->image_url('practice/' . $key, 'assignsubmission_vrtour')->out(false),
                'url' => tour_manager::practice_url($cmid, $key)->out(false),
            ];
        }
        return $choices;
    }

    /**
     * The editor's guide, one line until opened: what a tour is, three steps, what this assignment asks
     * for, and the "No 360° camera?" help. Hidden while the start card shows.
     *
     * The step strings hold a little markup (<strong>) and no user data.
     *
     * @return string HTML.
     */
    private function editor_guide_html(): string {
        global $OUTPUT;

        $steps = '';
        foreach (['editorstep1', 'editorstep2', 'editorstep3'] as $key) {
            $steps .= html_writer::tag('li', get_string($key, 'assignsubmission_vrtour'));
        }
        $body = html_writer::tag('p', s(get_string('editorintro', 'assignsubmission_vrtour')), ['class' => 'mb-1']) .
            html_writer::tag('ol', $steps, ['class' => 'mb-1']) .
            html_writer::tag('p', s($this->requirements_text()), ['class' => 'mb-2 fw-bold']) .
            $OUTPUT->render_from_template('assignsubmission_vrtour/nocamera', ['open' => false]);
        return html_writer::tag(
            'details',
            html_writer::tag('summary', s(get_string('guidesummary', 'assignsubmission_vrtour')), ['class' => 'fw-bold']) .
            html_writer::div($body, 'mt-2'),
            [
                'class' => 'assignsubmission_vrtour-guide alert alert-info py-2 mb-3',
                'data-region' => 'assignsubmission_vrtour-guide',
            ]
        );
    }

    /**
     * Start (or extend) the tour with one of the bundled practice photos, then go back to the editor.
     *
     * @return void Redirects.
     */
    private function start_with_practice_photo(): void {
        $assign = $this->assignment;
        $cm = $assign->get_course_module();
        require_login($assign->get_course(), false, $cm);
        require_sesskey();
        tour_manager::get_plugin($assign);
        $key = required_param('photo', PARAM_ALPHA);
        if (!in_array($key, tour_manager::PRACTICE_PHOTOS, true)) {
            throw new \moodle_exception('errorinvalid', 'assignsubmission_vrtour', '', 'practice photo');
        }
        $submission = tour_manager::require_editable_submission($assign);
        tour_manager::add_practice_room(
            $assign,
            $submission,
            $key,
            get_string('practiceroomname', 'assignsubmission_vrtour', get_string('practice_' . $key, 'assignsubmission_vrtour'))
        );
        redirect(tour_manager::edit_url((int)$cm->id));
    }

    /**
     * Leave the tour editor: back to the assignment, saying what is left to do to hand the tour in.
     *
     * With drafts the editor's save has made the submission a draft, so the student goes to the
     * assignment page, where core shows "Submit assignment"; the note says what the tour still needs,
     * with a button back into the editor. Without drafts nothing is handed in until the submission
     * form is saved, so the student goes to that form.
     *
     * @return void Redirects.
     */
    private function leave_editor(): void {
        global $USER;

        $assign = $this->assignment;
        $cm = $assign->get_course_module();
        require_login($assign->get_course(), false, $cm);
        tour_manager::get_plugin($assign);
        $viewurl = new moodle_url('/mod/assign/view.php', ['id' => $cm->id]);
        $submission = tour_manager::own_submission($assign, (int)$USER->id, false);
        $row = $submission ? tour_manager::get_row((int)$submission->id) : null;
        if (!$row || (int)$row->numphotos < 1) {
            redirect($viewurl);
        }
        if ($submission->status === ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
            redirect(
                $viewurl,
                get_string('tourdonesaved', 'assignsubmission_vrtour'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }
        if (!$assign->get_instance()->submissiondrafts) {
            redirect(
                new moodle_url('/mod/assign/view.php', ['id' => $cm->id, 'action' => 'editsubmission']),
                get_string('tourdonehandin', 'assignsubmission_vrtour'),
                null,
                \core\output\notification::NOTIFY_INFO
            );
        }
        // The message is HTML: lang strings, a count and our own URL only.
        $needed = $this->still_needed(tour_manager::scenes($row));
        if ($needed === null) {
            redirect(
                $viewurl,
                get_string('tourdonedraftready', 'assignsubmission_vrtour'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }
        $continue = html_writer::link(
            tour_manager::edit_url((int)$cm->id),
            get_string('edittourcontinue', 'assignsubmission_vrtour'),
            ['class' => 'btn btn-primary btn-sm ms-2 text-white text-decoration-none']
        );
        redirect(
            $viewurl,
            get_string('tourdonedraftneeds', 'assignsubmission_vrtour', s($needed)) . ' ' . $continue,
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }

    /**
     * The full-page viewer of a submission's tour.
     *
     * @return string
     */
    private function view_viewer_page(): string {
        $this->hide_navigation_footer();
        $assign = $this->assignment;
        $cm = $assign->get_course_module();
        require_login($assign->get_course(), false, $cm);
        tour_manager::get_plugin($assign);
        $submission = tour_manager::require_viewable_submission($assign, required_param('sid', PARAM_INT));

        $renderer = $assign->get_renderer();
        $o = $renderer->render(new assign_header(
            $assign->get_instance(),
            $assign->get_context(),
            false,
            (int)$cm->id,
            $this->get_name()
        ));
        $o .= html_writer::tag('p', s($this->summary_text($submission)));
        $o .= $this->view($submission);
        // Graders go back to the submissions they were grading; students to their assignment page.
        if (has_capability('mod/assign:grade', $assign->get_context())) {
            $back = new moodle_url('/mod/assign/view.php', ['id' => $cm->id, 'action' => 'grading']);
            $backlabel = get_string('backtograding', 'assignsubmission_vrtour');
        } else {
            $back = new moodle_url('/mod/assign/view.php', ['id' => $cm->id]);
            $backlabel = get_string('backtoassignment', 'assignsubmission_vrtour');
        }
        $o .= html_writer::div(html_writer::link($back, $backlabel), 'mt-3');
        $o .= $renderer->render_footer();

        \mod_assign\event\submission_viewed::create_from_submission($assign, $submission)->trigger();
        return $o;
    }

    /**
     * The assignment has been deleted: delete its tours (the files go with the context).
     *
     * @return bool
     */
    public function delete_instance() {
        global $DB;
        $DB->delete_records(tour_manager::TABLE, ['assignment' => $this->assignment->get_instance()->id]);
        return true;
    }

    /**
     * Whether the submission has no tour (no photo).
     *
     * @param stdClass $submission The submission.
     * @return bool
     */
    public function is_empty(stdClass $submission) {
        $row = tour_manager::get_row((int)$submission->id);
        return !$row || (int)$row->numphotos < 1;
    }

    /**
     * Whether the submission being saved has no tour. The tour is not in the form data: it is the
     * one the editor stored for the user's (or group's) submission.
     *
     * @param stdClass $data The submission form data.
     * @return bool
     */
    public function submission_is_empty(stdClass $data) {
        global $USER;
        $userid = !empty($data->userid) ? (int)$data->userid : (int)$USER->id;
        $submission = tour_manager::own_submission($this->assignment, $userid, false);
        if (!$submission) {
            return true;
        }
        [$photos] = tour_manager::counts(tour_manager::scenes(tour_manager::get_row((int)$submission->id)));
        return $photos < 1;
    }

    /**
     * The file areas of this plugin.
     *
     * @return array area => description
     */
    public function get_file_areas() {
        return [tour_manager::FILEAREA => $this->get_name()];
    }

    /**
     * Copy a tour to a new submission (a new attempt based on the previous one).
     *
     * @param stdClass $sourcesubmission The old submission.
     * @param stdClass $destsubmission The new submission.
     * @return bool
     */
    public function copy_submission(stdClass $sourcesubmission, stdClass $destsubmission) {
        global $DB;

        $contextid = $this->assignment->get_context()->id;
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $contextid,
            tour_manager::COMPONENT,
            tour_manager::FILEAREA,
            $sourcesubmission->id,
            'id',
            false
        );
        foreach ($files as $file) {
            $fs->create_file_from_storedfile(['itemid' => $destsubmission->id], $file);
        }

        $row = tour_manager::get_row((int)$sourcesubmission->id);
        if ($row) {
            unset($row->id);
            $row->submission = $destsubmission->id;
            $row->timemodified = time();
            $existing = tour_manager::get_row((int)$destsubmission->id);
            if ($existing) {
                $row->id = $existing->id;
                $DB->update_record(tour_manager::TABLE, $row);
            } else {
                $DB->insert_record(tour_manager::TABLE, $row);
            }
        }
        return true;
    }

    /**
     * The plugin settings for external functions.
     *
     * @return array
     */
    public function get_config_for_external() {
        $settings = tour_manager::settings($this);
        return $settings + ['maxbytesvalue' => tour_manager::max_bytes($this->assignment, $settings)];
    }
}
