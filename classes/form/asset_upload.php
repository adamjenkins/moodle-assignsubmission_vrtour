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

namespace assignsubmission_vrtour\form;

use assignsubmission_vrtour\local\tour_manager;
use mod_vrroom\local\editor\editor_manager;
use mod_vrroom\local\panorama;

/**
 * Upload one file for a student's virtual tour: a 360° photo, or an image, audio or video for a point.
 *
 * Shown by core_form/modalform from mod_vrroom/editor_assets, with the arguments {cmid, kind}. Each
 * upload goes into its own folder /N/ of the submission's 'tour' area, so the tour can refer to it as
 * {area: 'assets', itemid: N, filepath: '/', filename} and the save can delete it when no photo uses it.
 * Modelled on mod_vrroom\form\asset_upload.
 *
 * @package    assignsubmission_vrtour
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class asset_upload extends \core_form\dynamic_form {
    /** @var string[] Kinds of file this form can take (the assignment's settings narrow them). */
    public const KINDS = ['panorama', 'image', 'audio', 'video'];

    /** @var \assign|null The assignment, once loaded. */
    private ?\assign $assign = null;

    /**
     * The form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);
        $mform->addElement('hidden', 'kind');
        $mform->setType('kind', PARAM_ALPHA);

        $kind = $this->get_kind();
        if ($kind === 'panorama') {
            $limits = panorama::limits();
            $mform->addElement('static', 'panoramahelp', '', get_string('uploadpanoramahelp', 'assignsubmission_vrtour', (object)[
                'maxwidth' => (int)$limits['maxwidth'],
                'maxsize' => display_size((int)$limits['maxbytes']),
            ]));
        }
        $mform->addElement(
            'filemanager',
            'assetfile',
            get_string('uploadfile', 'assignsubmission_vrtour'),
            null,
            $this->file_options($kind)
        );
        $mform->addRule('assetfile', null, 'required');
    }

    /**
     * A file of the wrong type, too large, or a photo that is not a 360° photo, is a field error.
     *
     * @param array $data Submitted data.
     * @param array $files Files.
     * @return array errors
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty($data['assetfile'])) {
            return $errors;
        }
        $kind = $this->get_kind();
        foreach ($this->draft_files((int)$data['assetfile']) as $file) {
            $problem = $this->problem($kind, $file);
            if ($problem !== null) {
                $errors['assetfile'] = $problem;
            }
        }
        return $errors;
    }

    /**
     * The assignment context named by cmid.
     *
     * @return \context
     */
    protected function get_context_for_dynamic_submission(): \context {
        return $this->get_assign()->get_context();
    }

    /**
     * Only a student who may edit their own submission (the same check as the save), with the plugin enabled.
     */
    protected function check_access_for_dynamic_submission(): void {
        $assign = $this->get_assign();
        tour_manager::get_plugin($assign);
        tour_manager::require_editable_submission($assign);
    }

    /**
     * Fill the hidden fields.
     */
    public function set_data_for_dynamic_submission(): void {
        $this->set_data([
            'cmid' => (int)$this->optional_param('cmid', 0, PARAM_INT),
            'kind' => $this->get_kind(),
        ]);
    }

    /**
     * The tour editor page.
     *
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return tour_manager::edit_url((int)$this->optional_param('cmid', 0, PARAM_INT));
    }

    /**
     * Store the uploaded file in a new folder of the submission's tour area.
     *
     * @return array The asset entry {area: 'assets', itemid: N, filepath: '/', filename, url, mimetype, kind, filesize, panorama?}.
     */
    public function process_dynamic_submission() {
        $kind = $this->get_kind();
        if ($kind === '') {
            throw new \moodle_exception('errorinvalid', 'assignsubmission_vrtour', '', 'file kind');
        }
        $assign = $this->get_assign();
        $submission = tour_manager::require_editable_submission($assign);
        $context = $assign->get_context();
        $data = $this->get_data();

        $draft = $this->draft_files((int)$data->assetfile);
        $source = count($draft) === 1 ? reset($draft) : null;
        if ($source === null) {
            throw new \moodle_exception('errorupload', 'assignsubmission_vrtour');
        }
        // The form's own checks run in the browser's request too, but are repeated here.
        $problem = $this->problem($kind, $source);
        if ($problem !== null) {
            throw new \moodle_exception('errorinvalid', 'assignsubmission_vrtour', '', $problem);
        }
        $submissionid = (int)$submission->id;
        if (count(tour_manager::upload_numbers($context, $submissionid)) >= tour_manager::MAX_UPLOADS) {
            throw new \moodle_exception('errortoomanyuploads', 'assignsubmission_vrtour');
        }

        $number = tour_manager::next_upload_number($context, $submissionid);
        $file = get_file_storage()->create_file_from_storedfile([
            'contextid' => $context->id,
            'component' => tour_manager::COMPONENT,
            'filearea' => tour_manager::FILEAREA,
            'itemid' => $submissionid,
            'filepath' => "/{$number}/",
            'filename' => $source->get_filename(),
        ], $source);
        if ($kind === 'panorama') {
            panorama::make_variants($file);
        }
        return tour_manager::asset_entry($file, $kind === 'panorama' ? 'panorama' : null);
    }

    /**
     * Why a file cannot be used for a kind of upload.
     *
     * @param string $kind One of KINDS.
     * @param \stored_file $file The draft file.
     * @return string|null The error message, or null when it is fine.
     */
    private function problem(string $kind, \stored_file $file): ?string {
        $name = s($file->get_filename());
        $filekind = editor_manager::asset_kind($file->get_filename());
        $expected = $kind === 'panorama' ? 'image' : $kind;
        if ($kind === '' || $filekind !== $expected) {
            return get_string('errorfilekind', 'assignsubmission_vrtour', $name);
        }
        if ($kind === 'panorama') {
            $extension = \core_text::strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
            if (!in_array($extension, panorama::EXTENSIONS, true)) {
                return get_string('errorfilekind', 'assignsubmission_vrtour', $name);
            }
            if ((int)$file->get_filesize() > panorama::max_bytes()) {
                return get_string('errorfilesize', 'assignsubmission_vrtour', (object)[
                    'name' => $name,
                    'max' => display_size(panorama::max_bytes()),
                ]);
            }
            $key = panorama::check($file);
            return $key === null ? null : tour_manager::panorama_message($key);
        }
        $max = $this->max_bytes();
        if ($max > 0 && (int)$file->get_filesize() > $max) {
            return get_string('errorfilesize', 'assignsubmission_vrtour', (object)['name' => $name, 'max' => display_size($max)]);
        }
        return null;
    }

    /**
     * The files of the current user's draft area.
     *
     * @param int $draftitemid Draft item id.
     * @return \stored_file[]
     */
    private function draft_files(int $draftitemid): array {
        global $USER;
        return array_values(get_file_storage()->get_area_files(
            \context_user::instance($USER->id)->id,
            'user',
            'draft',
            $draftitemid,
            'id',
            false
        ));
    }

    /**
     * The assignment named by cmid (a course module id from the browser: it must be an assignment).
     *
     * @return \assign
     */
    private function get_assign(): \assign {
        if ($this->assign === null) {
            $this->assign = tour_manager::get_assign((int)$this->optional_param('cmid', 0, PARAM_INT));
        }
        return $this->assign;
    }

    /**
     * The largest image, audio or video file of this assignment.
     *
     * @return int Bytes.
     */
    private function max_bytes(): int {
        $assign = $this->get_assign();
        return tour_manager::max_bytes($assign, tour_manager::settings(tour_manager::get_plugin($assign)));
    }

    /**
     * The kind of file asked for, when the assignment allows it.
     *
     * @return string One of KINDS, or '' when the request names none of them or the assignment does not allow it.
     */
    private function get_kind(): string {
        $kind = (string)$this->optional_param('kind', '', PARAM_ALPHA);
        if (!in_array($kind, self::KINDS, true)) {
            return '';
        }
        $assign = $this->get_assign();
        $allowed = tour_manager::allowed_uploads(tour_manager::settings(tour_manager::get_plugin($assign)));
        return in_array($kind, $allowed, true) ? $kind : '';
    }

    /**
     * File manager options for a kind.
     *
     * @param string $kind One of KINDS, or ''.
     * @return array
     */
    private function file_options(string $kind): array {
        $extensions = editor_manager::KIND_EXTENSIONS[$kind] ?? [];
        $types = array_map(fn(string $extension): string => '.' . $extension, $extensions);
        return [
            'subdirs' => 0,
            'maxfiles' => 1,
            'maxbytes' => $kind === 'panorama' ? panorama::max_bytes() : ($kind === '' ? 1 : $this->max_bytes()),
            'accepted_types' => $types ?: ['.jpg'],
        ];
    }
}
