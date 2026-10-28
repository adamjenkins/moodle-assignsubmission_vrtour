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

namespace assignsubmission_vrtour\local;

use mod_vrroom\local\editor\editor_manager;
use mod_vrroom\local\graph\graph_validator;
use mod_vrroom\local\launch\scene_formatter;
use mod_vrroom\local\panorama;
use mod_vrroom\local\scene\scene_validator;

/**
 * The virtual tour of a submission: storage, access, the editor's load and save, and the viewer config.
 *
 * A tour is a list of vrroom-scene/1 documents, each a 360° photo room. It is stored as JSON in the
 * assignsubmission_vrtour row of the submission. The files sit in component assignsubmission_vrtour,
 * area 'tour', item id = submission id, folder /N/ for the student's upload number N (variants of a
 * photo in /N/variants/). The scenes name a file as {area: 'assets', itemid: N, filepath: '/', filename},
 * the same shape the room editor uses for an activity's uploads, so the editor works unchanged.
 *
 * The save path follows mod_vrroom's editor_manager::save (size, decode, lock, revision, validate,
 * store, prune), with the rules of a tour added: every room a photo, only the allowed kinds of object,
 * and every file the student's own.
 *
 * @package    assignsubmission_vrtour
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tour_manager {
    /** @var string Frankenstyle name. */
    public const COMPONENT = 'assignsubmission_vrtour';

    /** @var string The file area of the tours. */
    public const FILEAREA = 'tour';

    /** @var string The area scene file references name (mod_vrroom's editor uploads). */
    public const REF_AREA = 'assets';

    /** @var string The table. */
    public const TABLE = 'assignsubmission_vrtour';

    /** @var int Most photos a teacher may allow. */
    public const MAX_PHOTOS = 20;

    /** @var int Most points of interest a teacher may require. */
    public const MAX_MINPOINTS = 100;

    /** @var int Most uploads (folders) one submission may hold; unused ones are deleted on save. */
    public const MAX_UPLOADS = 200;

    /** @var string The editor document's template name. */
    public const TEMPLATE = 'vrtour';

    /** @var array Setting => default. */
    public const DEFAULTS = [
        'maxphotos' => 10,
        'minpoints' => 3,
        'allowimage' => 1,
        'allowaudio' => 1,
        'allowvideo' => 1,
        'maxbytes' => 0,
    ];

    /** @var int Seconds a save waits for another save of the same tour. */
    private const LOCK_TIMEOUT = 10;

    /** @var int Flags of the stored and sent JSON. */
    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

    /**
     * The assignment of a course module id.
     *
     * @param int $cmid Course module id (from the browser).
     * @return \assign
     */
    public static function get_assign(int $cmid): \assign {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'assign');
        $context = \context_module::instance($cm->id);
        return new \assign($context, $cm, $course);
    }

    /**
     * This plugin in an assignment, which must have it enabled.
     *
     * @param \assign $assign The assignment.
     * @return \assign_submission_vrtour
     * @throws \moodle_exception errornotenabled
     */
    public static function get_plugin(\assign $assign): \assign_submission_vrtour {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/submission/vrtour/locallib.php');
        $plugin = $assign->get_submission_plugin_by_type('vrtour');
        if (!$plugin instanceof \assign_submission_vrtour || !$plugin->is_enabled()) {
            throw new \moodle_exception('errornotenabled', self::COMPONENT);
        }
        return $plugin;
    }

    /**
     * The assignment's tour settings, with defaults for the ones not saved.
     *
     * @param \assign_plugin $plugin This plugin in the assignment.
     * @return array ['maxphotos', 'minpoints', 'allowimage', 'allowaudio', 'allowvideo', 'maxbytes'] as ints
     *     (maxbytes 0 = the site's default).
     */
    public static function settings(\assign_plugin $plugin): array {
        $settings = [];
        foreach (self::DEFAULTS as $name => $default) {
            $value = $plugin->get_config($name);
            $settings[$name] = ($value === false || $value === null || $value === '') ? $default : (int)$value;
        }
        $settings['maxphotos'] = max(1, min(self::MAX_PHOTOS, $settings['maxphotos']));
        $settings['minpoints'] = max(0, min(self::MAX_MINPOINTS, $settings['minpoints']));
        return $settings;
    }

    /**
     * The largest point media file in bytes: the assignment's setting (or the site's), within the
     * site and course upload limits.
     *
     * @param \assign $assign The assignment.
     * @param array $settings From settings().
     * @return int
     */
    public static function max_bytes(\assign $assign, array $settings): int {
        global $CFG;
        $own = (int)$settings['maxbytes'];
        if ($own <= 0) {
            $own = (int)get_config(self::COMPONENT, 'maxbytes');
        }
        return (int)get_max_upload_file_size($CFG->maxbytes, (int)$assign->get_course()->maxbytes, $own);
    }

    /**
     * The object kinds a student may add, from the settings.
     *
     * @param array $settings From settings().
     * @return string[] Interactable kinds.
     */
    public static function allowed_kinds(array $settings): array {
        $kinds = ['hotspot', 'portal'];
        if (!empty($settings['allowimage'])) {
            $kinds[] = 'image';
        }
        $kinds[] = 'text';
        if (!empty($settings['allowaudio']) || !empty($settings['allowvideo'])) {
            $kinds[] = 'media';
        }
        return $kinds;
    }

    /**
     * The object kinds the editor's Add menu offers, from the settings.
     *
     * Like allowed_kinds(), but audio and video objects are named by their media type, so that the
     * editor offers only the allowed one (mod_vrroom/editor_app reads 'media' as both).
     *
     * @param array $settings From settings().
     * @return string[]
     */
    public static function editor_kinds(array $settings): array {
        $kinds = array_values(array_diff(self::allowed_kinds($settings), ['media']));
        foreach (['audio', 'video'] as $media) {
            if (!empty($settings['allow' . $media])) {
                $kinds[] = $media;
            }
        }
        return $kinds;
    }

    /**
     * The upload kinds the form takes, from the settings.
     *
     * @param array $settings From settings().
     * @return string[] panorama, image, audio, video.
     */
    public static function allowed_uploads(array $settings): array {
        $kinds = ['panorama'];
        foreach (['image', 'audio', 'video'] as $kind) {
            if (!empty($settings['allow' . $kind])) {
                $kinds[] = $kind;
            }
        }
        return $kinds;
    }

    /**
     * A user's own submission (or their group's, when the assignment takes group submissions).
     *
     * @param \assign $assign The assignment.
     * @param int $userid The user.
     * @param bool $create Whether to create it when there is none.
     * @return \stdClass|null The assign_submission row.
     */
    public static function own_submission(\assign $assign, int $userid, bool $create): ?\stdClass {
        if ($assign->get_instance()->teamsubmission) {
            $submission = $assign->get_group_submission($userid, 0, $create);
        } else {
            $submission = $assign->get_user_submission($userid, $create);
        }
        return $submission ?: null;
    }

    /**
     * The current user's submission, which they must be allowed to edit.
     *
     * @param \assign $assign The assignment.
     * @return \stdClass The assign_submission row (created when there was none).
     * @throws \moodle_exception errornoedit
     */
    public static function require_editable_submission(\assign $assign): \stdClass {
        global $USER;
        if (!$assign->can_edit_submission($USER->id)) {
            throw new \moodle_exception('errornoedit', self::COMPONENT);
        }
        $submission = self::own_submission($assign, (int)$USER->id, true);
        if (!$submission) {
            throw new \moodle_exception('errornoedit', self::COMPONENT);
        }
        return $submission;
    }

    /**
     * A submission of the assignment that the current user may view (the checks of the file plugin's pluginfile).
     *
     * @param \assign $assign The assignment.
     * @param int $submissionid The submission id (from the browser).
     * @return \stdClass The assign_submission row.
     * @throws \moodle_exception errornoview
     */
    public static function require_viewable_submission(\assign $assign, int $submissionid): \stdClass {
        global $DB;
        $submission = $DB->get_record('assign_submission', ['id' => $submissionid]);
        if (!$submission || !self::can_view($assign, $submission)) {
            throw new \moodle_exception('errornoview', self::COMPONENT);
        }
        return $submission;
    }

    /**
     * Whether the current user may view a submission (as assignsubmission_file_pluginfile decides).
     *
     * @param \assign $assign The assignment.
     * @param \stdClass $submission The assign_submission row.
     * @return bool
     */
    public static function can_view(\assign $assign, \stdClass $submission): bool {
        if ((int)$assign->get_instance()->id !== (int)$submission->assignment) {
            return false;
        }
        if ($assign->get_instance()->teamsubmission) {
            return $assign->can_view_group_submission((int)$submission->groupid);
        }
        return $assign->can_view_submission((int)$submission->userid);
    }

    /**
     * The tour row of a submission.
     *
     * @param int $submissionid The submission id.
     * @return \stdClass|null
     */
    public static function get_row(int $submissionid): ?\stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['submission' => $submissionid]) ?: null;
    }

    /**
     * The stored rooms of a row as arrays.
     *
     * @param \stdClass|null $row The tour row.
     * @return array[] Scene documents ([] when there is no tour).
     */
    public static function scenes(?\stdClass $row): array {
        if (!$row || (string)$row->scenesjson === '') {
            return [];
        }
        $scenes = json_decode((string)$row->scenesjson, true);
        return is_array($scenes) && array_is_list($scenes) ? $scenes : [];
    }

    /**
     * The optimistic-lock revision of what is stored.
     *
     * @param \stdClass|null $row The tour row.
     * @return string 40 hex characters.
     */
    public static function revision(?\stdClass $row): string {
        return sha1($row ? (string)$row->scenesjson : '');
    }

    /**
     * Photos and points of interest of a tour. Every object except a link to another photo is a point of interest.
     *
     * @param array $scenes Scene documents.
     * @return array [number of photos, number of points]
     */
    public static function counts(array $scenes): array {
        $points = 0;
        foreach ($scenes as $scene) {
            foreach (is_array($scene['interactables'] ?? null) ? $scene['interactables'] : [] as $item) {
                if (is_array($item) && ($item['kind'] ?? null) !== 'portal') {
                    $points++;
                }
            }
        }
        return [count($scenes), $points];
    }

    /**
     * Store a tour for a submission, creating the row when there is none.
     *
     * @param \assign $assign The assignment.
     * @param \stdClass $submission The assign_submission row.
     * @param array $scenes The rooms.
     * @return \stdClass The row as stored.
     */
    public static function store(\assign $assign, \stdClass $submission, array $scenes): \stdClass {
        global $DB;
        $json = $scenes ? json_encode(array_values($scenes), self::JSON_FLAGS) : '';
        [$photos, $points] = self::counts($scenes);
        $row = self::get_row((int)$submission->id);
        $fields = [
            'scenesjson' => $json,
            'revision' => sha1($json),
            'numphotos' => $photos,
            'numpoints' => $points,
            'timemodified' => time(),
        ];
        if ($row) {
            foreach ($fields as $name => $value) {
                $row->$name = $value;
            }
            $DB->update_record(self::TABLE, $row);
            return $row;
        }
        $row = (object)(['assignment' => (int)$assign->get_instance()->id, 'submission' => (int)$submission->id] + $fields);
        $row->id = $DB->insert_record(self::TABLE, $row);
        return $row;
    }

    /**
     * Everything the room editor starts from, in mod_vrroom's editor shape.
     *
     * @param \assign $assign The assignment.
     * @param \stdClass $submission The assign_submission row.
     * @param bool $readonly Whether the caller only views the tour (a grader).
     * @return array ['documentjson', 'assetsjson', 'presetsjson', 'questionsjson', 'infojson'] as JSON strings.
     */
    public static function load(\assign $assign, \stdClass $submission, bool $readonly): array {
        $plugin = self::get_plugin($assign);
        $settings = self::settings($plugin);
        $context = $assign->get_context();
        $row = self::get_row((int)$submission->id);

        // Decoded as objects, so that empty JSON objects stay objects.
        $scenes = [];
        if ($row && (string)$row->scenesjson !== '') {
            $decoded = json_decode((string)$row->scenesjson, false, 512, JSON_THROW_ON_ERROR);
            $scenes = is_array($decoded) ? $decoded : [];
        }

        $document = [
            'schema' => editor_manager::DOC_SCHEMA,
            'revision' => self::revision($row),
            'template' => self::TEMPLATE,
            'customised' => true,
            'hasattempts' => false,
            'scenes' => $scenes,
            'graph' => (object)['schema' => graph_validator::SCHEMA, 'hidden' => [], 'objectives' => [], 'rules' => []],
            'bindings' => [],
        ];

        $cmid = (int)$assign->get_course_module()->id;
        $info = [
            'cmid' => $cmid,
            'courseid' => (int)$assign->get_course()->id,
            'contextid' => (int)$context->id,
            'submissionid' => (int)$submission->id,
            'readonly' => $readonly,
            'viewurl' => self::view_url($cmid, (int)$submission->id)->out(false),
            'maxdocumentbytes' => editor_manager::MAX_DOCUMENT_BYTES,
            'maxscenes' => $settings['maxphotos'],
            'maxinteractables' => scene_validator::MAX_INTERACTABLES,
            'maxzones' => 0,
            'maxhints' => 0,
            'maxbytes' => self::max_bytes($assign, $settings),
            'minpoints' => $settings['minpoints'],
            'panorama' => panorama::limits(),
            'uploadkinds' => self::allowed_uploads($settings),
            'features' => [
                'logic' => false,
                'questions' => false,
                'kinds' => self::editor_kinds($settings),
            ],
        ];

        return [
            'documentjson' => json_encode($document, self::JSON_FLAGS),
            'assetsjson' => json_encode(self::list_assets($context, (int)$submission->id), self::JSON_FLAGS),
            'presetsjson' => '[]',
            'questionsjson' => '[]',
            'infojson' => json_encode($info, self::JSON_FLAGS),
        ];
    }

    /**
     * Validate an editor document and store its rooms as the submission's tour.
     *
     * Any failure throws and nothing is written. The revision check and the write run under a
     * per-submission lock, so of two saves from the same revision only the first is stored.
     * In an assignment without drafts, a submitted tour must still pass the plugin's submit rules,
     * and the save moves the submission's time and is logged, as the submission form's save is.
     *
     * @param \assign $assign The assignment.
     * @param \stdClass $submission The assign_submission row (the caller checked it may be edited).
     * @param string $documentjson The vrroom-editdoc/1 document from the editor.
     * @return array ['revision', 'graphversion', 'scenesupdated', 'graphupdated', 'customised']
     * @throws \moodle_exception errortoolarge, errorinvalid, errorconflict
     */
    public static function save(\assign $assign, \stdClass $submission, string $documentjson): array {
        // 1. Size and shape.
        if (strlen($documentjson) > editor_manager::MAX_DOCUMENT_BYTES) {
            throw new \moodle_exception('errortoolarge', self::COMPONENT);
        }
        try {
            $document = json_decode($documentjson, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \moodle_exception('errorinvalid', self::COMPONENT, '', 'document');
        }
        if (!is_array($document) || array_is_list($document) || ($document['schema'] ?? null) !== editor_manager::DOC_SCHEMA) {
            throw new \moodle_exception('errorinvalid', self::COMPONENT, '', 'document');
        }

        $lock = \core\lock\lock_config::get_lock_factory(self::COMPONENT)->get_lock(
            'tour_' . (int)$submission->id,
            self::LOCK_TIMEOUT
        );
        if (!$lock) {
            throw new \moodle_exception('errorconflict', self::COMPONENT);
        }
        try {
            // 2. Nobody saved since this document was loaded.
            $before = self::get_row((int)$submission->id);
            if (!is_string($document['revision'] ?? null) || !hash_equals(self::revision($before), $document['revision'])) {
                throw new \moodle_exception('errorconflict', self::COMPONENT);
            }

            // 3. Rooms.
            $scenes = $document['scenes'] ?? null;
            if (!is_array($scenes) || !array_is_list($scenes)) {
                throw new \moodle_exception('errorinvalid', self::COMPONENT, '', 'scenes must be a list');
            }
            foreach ($scenes as $i => $scene) {
                if (is_array($scene)) {
                    $scenes[$i] = editor_manager::strip_runtime_keys($scene);
                }
            }
            self::validate($assign, (int)$submission->id, $scenes);

            // 4. Without drafts a submitted tour stays submitted, so it must still be one that could be
            // submitted (the check of the submission form's save).
            $submitted = !$assign->get_instance()->submissiondrafts
                && $submission->status === ASSIGN_SUBMISSION_STATUS_SUBMITTED;
            if ($submitted) {
                $problem = self::get_plugin($assign)->problem($scenes);
                if ($problem !== null) {
                    self::fail($problem);
                }
            }

            // 5. Store, then delete the uploads no room uses.
            $row = self::store($assign, $submission, $scenes);
            self::prune($assign->get_context(), (int)$submission->id, $scenes);
            if ($submitted) {
                self::record_submitted_change($assign, $submission, $row, $before === null);
            }
        } finally {
            $lock->release();
        }

        return [
            'revision' => self::revision($row),
            'graphversion' => 0,
            'scenesupdated' => self::revision($before) !== self::revision($row),
            'graphupdated' => false,
            'customised' => (int)$row->timemodified,
        ];
    }

    /**
     * Record on the submission a change the editor made to a submitted tour (an assignment without drafts).
     *
     * The submission form's save does this through core, but the student need not go back to it. Without
     * this the submission would keep its first time: not flagged late, and no "modified after grading" cue.
     * As core's update_team_submission() does, a group submission moves with the member's own row.
     *
     * @param \assign $assign The assignment.
     * @param \stdClass $submission The assign_submission row (status submitted).
     * @param \stdClass $row The tour row as stored.
     * @param bool $isnew Whether the tour row was created by this save.
     */
    private static function record_submitted_change(\assign $assign, \stdClass $submission, \stdClass $row, bool $isnew): void {
        global $DB, $USER;

        $now = (int)$row->timemodified;
        $ids = [(int)$submission->id];
        if ($assign->get_instance()->teamsubmission) {
            $own = $assign->get_user_submission((int)$USER->id, false, (int)$submission->attemptnumber);
            if ($own) {
                $ids[] = (int)$own->id;
            }
        }
        foreach ($ids as $id) {
            $DB->set_field('assign_submission', 'timemodified', $now, ['id' => $id]);
        }
        $submission->timemodified = $now;

        self::get_plugin($assign)->log_change($submission, $row, $isnew);
        \mod_assign\event\assessable_submitted::create_from_submission($assign, $submission, true)->trigger();
    }

    /**
     * Check a tour against the scene rules and the tour's own rules.
     *
     * @param \assign $assign The assignment.
     * @param int $submissionid The submission whose files the rooms may use.
     * @param array $scenes The rooms, runtime keys removed. An empty list is a tour not started yet.
     * @throws \moodle_exception errorinvalid with a message the student can act on
     */
    public static function validate(\assign $assign, int $submissionid, array $scenes): void {
        $plugin = self::get_plugin($assign);
        $settings = self::settings($plugin);
        $context = $assign->get_context();

        if (count($scenes) > $settings['maxphotos']) {
            self::fail(get_string('errortoomanyphotos', self::COMPONENT, $settings['maxphotos']));
        }
        if (!$scenes) {
            return;
        }

        $errors = scene_validator::validate_set($scenes);
        if ($errors) {
            $friendly = editor_manager::scene_set_problem($scenes, $context);
            self::fail(s($friendly ?? (string)reset($errors)), implode('; ', array_slice($errors, 0, 5)));
        }

        $kinds = self::allowed_kinds($settings);
        foreach ($scenes as $scene) {
            $room = self::label($scene['name'] ?? '', $context);
            if (!scene_validator::is_panorama($scene['environment'] ?? null)) {
                self::fail(get_string('errornotphoto', self::COMPONENT, $room));
            }
            foreach (['zones', 'props'] as $list) {
                if (!empty($scene[$list])) {
                    self::fail(get_string('errornot' . $list, self::COMPONENT, $room));
                }
            }
            foreach ($scene['interactables'] ?? [] as $item) {
                $kind = (string)($item['kind'] ?? '');
                $name = self::label($item['name'] ?? '', $context);
                if (!in_array($kind, $kinds, true)) {
                    self::fail(get_string('errorkind', self::COMPONENT, (object)['name' => $name, 'room' => $room]));
                }
                if ($kind === 'media' && empty($settings['allow' . ($item['media'] ?? '')])) {
                    self::fail(get_string('errorkind', self::COMPONENT, (object)['name' => $name, 'room' => $room]));
                }
                if (isset($item['content']['textformat'])) {
                    if (!in_array($item['content']['textformat'], editor_manager::TEXT_FORMATS, true)) {
                        self::fail(s('scenes: content.textformat is not a known text format'));
                    }
                }
            }
        }

        // Every file is one of this submission's uploads, of a kind the room may use.
        foreach (editor_manager::file_refs($scenes) as $ref) {
            if (!self::find_ref_file($context, $submissionid, $ref)) {
                $filename = is_string($ref['filename'] ?? null) ? $ref['filename'] : '';
                self::fail(get_string('errorfile', self::COMPONENT, s($filename)));
            }
        }
        foreach ($scenes as $scene) {
            $environment = self::require_file(
                $context,
                $submissionid,
                $scene['environment']['file'] ?? null,
                $settings,
                ['image'],
                true
            );
            $problem = panorama::check($environment);
            if ($problem !== null) {
                self::fail(get_string('errorpanorama', self::COMPONENT, (object)[
                    'room' => self::label($scene['name'] ?? '', $context),
                    'problem' => self::panorama_message($problem),
                ]));
            }
            foreach ($scene['interactables'] ?? [] as $item) {
                $kind = (string)($item['kind'] ?? '');
                if ($kind === 'image') {
                    self::require_file($context, $submissionid, $item['file'] ?? null, $settings, ['image']);
                } else if ($kind === 'media') {
                    self::require_file($context, $submissionid, $item['file'] ?? null, $settings, [(string)$item['media']]);
                }
                foreach (is_array($item['content']['media'] ?? null) ? $item['content']['media'] : [] as $media) {
                    self::require_file($context, $submissionid, $media, $settings, ['image', 'audio', 'video']);
                }
            }
        }
    }

    /**
     * The stored file a scene file reference names, which must be one of the submission's uploads of an allowed kind.
     *
     * @param \context $context The assignment context.
     * @param int $submissionid The submission.
     * @param mixed $ref The reference.
     * @param array $settings From settings().
     * @param string[] $kinds Kinds of file this place takes (image, audio, video).
     * @param bool $photo Whether this is a photo room's own photo, which is allowed whatever the media settings.
     * @return \stored_file
     * @throws \moodle_exception errorinvalid
     */
    private static function require_file(
        \context $context,
        int $submissionid,
        $ref,
        array $settings,
        array $kinds,
        bool $photo = false
    ): \stored_file {
        $file = is_array($ref) ? self::find_ref_file($context, $submissionid, $ref) : null;
        $filename = is_array($ref) && is_string($ref['filename'] ?? null) ? $ref['filename'] : '';
        if (!$file) {
            self::fail(get_string('errorfile', self::COMPONENT, s($filename)));
        }
        $kind = editor_manager::asset_kind($file->get_filename());
        if (!in_array($kind, $kinds, true) || (!$photo && empty($settings['allow' . $kind]))) {
            self::fail(get_string('errorfilekind', self::COMPONENT, s($filename)));
        }
        return $file;
    }

    /**
     * Throw the save error.
     *
     * @param string $message Plain text the student can act on (lang strings, names already escaped).
     * @param string|null $debug Developer detail.
     * @throws \moodle_exception errorinvalid
     */
    private static function fail(string $message, ?string $debug = null): void {
        throw new \moodle_exception('errorinvalid', self::COMPONENT, '', $message, $debug);
    }

    /**
     * The message of a panorama::check() problem, with the site's photo limits.
     *
     * @param string $key A mod_vrroom lang string identifier from panorama::check().
     * @return string
     */
    public static function panorama_message(string $key): string {
        return panorama::message($key);
    }

    /**
     * A name for a message: plain text, or "(no name)".
     *
     * @param mixed $name The stored name.
     * @param \context $context Context to format it in.
     * @return string Escaped plain text.
     */
    private static function label($name, \context $context): string {
        $name = is_string($name) ? $name : '';
        $name = trim(html_entity_decode(strip_tags(format_string($name, true, ['context' => $context])), ENT_QUOTES | ENT_HTML5));
        return s($name !== '' ? $name : get_string('erroreditorunnamed', 'mod_vrroom'));
    }

    /**
     * The upload number of a file: N for a file directly in /N/, null for anything else (variants, other paths).
     *
     * @param \stored_file $file The file.
     * @return int|null
     */
    public static function upload_number(\stored_file $file): ?int {
        if (!preg_match('#^/([1-9][0-9]{0,8})/$#', $file->get_filepath(), $m)) {
            return null;
        }
        return (int)$m[1];
    }

    /**
     * The stored file a scene reference names, when it is one of the submission's uploads.
     *
     * @param \context $context The assignment context.
     * @param int $submissionid The submission.
     * @param array $ref {area: 'assets', itemid: N, filepath: '/', filename}
     * @return \stored_file|null
     */
    public static function find_ref_file(\context $context, int $submissionid, array $ref): ?\stored_file {
        if (($ref['area'] ?? null) !== self::REF_AREA || ($ref['filepath'] ?? null) !== '/') {
            return null;
        }
        $itemid = $ref['itemid'] ?? null;
        $filename = $ref['filename'] ?? null;
        if (!is_int($itemid) || $itemid < 1 || !is_string($filename) || $filename === '' || $filename === '.') {
            return null;
        }
        $file = get_file_storage()->get_file(
            $context->id,
            self::COMPONENT,
            self::FILEAREA,
            $submissionid,
            "/{$itemid}/",
            $filename
        );
        return ($file && !$file->is_directory()) ? $file : null;
    }

    /**
     * The URL of a stored tour file.
     *
     * @param \stored_file $file The file.
     * @return string
     */
    public static function file_url(\stored_file $file): string {
        return \moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            self::COMPONENT,
            self::FILEAREA,
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        )->out(false);
    }

    /**
     * The $fileurl callback of scene_formatter: a scene reference => its URL and stored file.
     *
     * @param \context $context The assignment context.
     * @param int $submissionid The submission.
     * @return callable (array $ref): ?array ['url' => string, 'file' => \stored_file]
     */
    public static function fileurl_callback(\context $context, int $submissionid): callable {
        return function (array $ref) use ($context, $submissionid): ?array {
            $file = self::find_ref_file($context, $submissionid, $ref);
            return $file ? ['url' => self::file_url($file), 'file' => $file] : null;
        };
    }

    /**
     * Whether an image is offered as a 360° photo: it has variants, or it is at least 1.5 times as wide
     * as it is high (any shape can be used as one).
     *
     * @param \stored_file $file The file.
     * @return bool
     */
    public static function is_panorama_file(\stored_file $file): bool {
        return panorama::has_variants($file) || panorama::has_ratio($file);
    }

    /**
     * The editor's description of one upload.
     *
     * @param \stored_file $file A file directly in /N/.
     * @param string|null $kind The kind to report (the upload form says 'panorama'); null takes it from the name.
     * @return array {area: 'assets', itemid: N, filepath: '/', filename, url, mimetype, kind, filesize, panorama?}
     */
    public static function asset_entry(\stored_file $file, ?string $kind = null): array {
        $entry = [
            'area' => self::REF_AREA,
            'itemid' => (int)self::upload_number($file),
            'filepath' => '/',
            'filename' => $file->get_filename(),
            'url' => self::file_url($file),
            'mimetype' => (string)$file->get_mimetype(),
            'kind' => $kind ?? editor_manager::asset_kind($file->get_filename()),
            'filesize' => (int)$file->get_filesize(),
        ];
        if ($kind === 'panorama' || ($entry['kind'] === 'image' && self::is_panorama_file($file))) {
            $entry['panorama'] = true;
        }
        return $entry;
    }

    /**
     * Every upload of a submission (never the variants).
     *
     * @param \context $context The assignment context.
     * @param int $submissionid The submission.
     * @return array[] Entries of asset_entry().
     */
    public static function list_assets(\context $context, int $submissionid): array {
        $assets = [];
        $files = get_file_storage()->get_area_files(
            $context->id,
            self::COMPONENT,
            self::FILEAREA,
            $submissionid,
            'filepath, filename',
            false
        );
        foreach ($files as $file) {
            if (self::upload_number($file) !== null) {
                $assets[] = self::asset_entry($file);
            }
        }
        usort($assets, fn(array $a, array $b): int => $a['itemid'] <=> $b['itemid']);
        return $assets;
    }

    /**
     * The upload numbers in use (including folders that only hold variants).
     *
     * @param \context $context The assignment context.
     * @param int $submissionid The submission.
     * @return int[] number => number
     */
    public static function upload_numbers(\context $context, int $submissionid): array {
        $numbers = [];
        $files = get_file_storage()->get_area_files($context->id, self::COMPONENT, self::FILEAREA, $submissionid, 'id', true);
        foreach ($files as $file) {
            if (preg_match('#^/([1-9][0-9]{0,8})/#', $file->get_filepath(), $m)) {
                $numbers[(int)$m[1]] = (int)$m[1];
            }
        }
        return $numbers;
    }

    /**
     * The number of the next upload.
     *
     * @param \context $context The assignment context.
     * @param int $submissionid The submission.
     * @return int 1 + the highest number in use, 1 when there is none.
     */
    public static function next_upload_number(\context $context, int $submissionid): int {
        $numbers = self::upload_numbers($context, $submissionid);
        return $numbers ? max($numbers) + 1 : 1;
    }

    /**
     * Delete the uploads (and their variants) no room uses.
     *
     * @param \context $context The assignment context.
     * @param int $submissionid The submission.
     * @param array $scenes The rooms as saved.
     */
    public static function prune(\context $context, int $submissionid, array $scenes): void {
        $referenced = [];
        foreach (editor_manager::file_refs($scenes) as $ref) {
            if (($ref['area'] ?? null) === self::REF_AREA && is_int($ref['itemid'] ?? null)) {
                $referenced[$ref['itemid']] = true;
            }
        }
        $fs = get_file_storage();
        foreach ($fs->get_area_files($context->id, self::COMPONENT, self::FILEAREA, $submissionid, 'id', true) as $file) {
            $number = preg_match('#^/([1-9][0-9]{0,8})/#', $file->get_filepath(), $m) ? (int)$m[1] : null;
            if ($number === null || !isset($referenced[$number])) {
                if ($file->get_filepath() === '/' && $file->is_directory()) {
                    continue;
                }
                $file->delete();
            }
        }
    }

    /**
     * The viewer's config for a submission's tour (scene_formatter::standalone_config).
     *
     * @param \assign $assign The assignment.
     * @param \stdClass $submission The assign_submission row.
     * @return array|null The vrroom-launch/1 config, or null when the tour has no photo yet.
     */
    public static function viewer_config(\assign $assign, \stdClass $submission): ?array {
        global $USER;
        $scenes = self::scenes(self::get_row((int)$submission->id));
        if (!$scenes) {
            return null;
        }
        $context = $assign->get_context();
        return scene_formatter::standalone_config(
            $scenes,
            $context,
            $USER,
            self::fileurl_callback($context, (int)$submission->id),
            [
                'uid' => (int)$submission->id,
                'finishurl' => (new \moodle_url('/mod/assign/view.php', ['id' => $assign->get_course_module()->id]))->out(false),
            ]
        );
    }

    /**
     * The tour editor page.
     *
     * @param int $cmid Course module id.
     * @return \moodle_url
     */
    public static function edit_url(int $cmid): \moodle_url {
        return new \moodle_url('/mod/assign/view.php', [
            'id' => $cmid,
            'action' => 'viewpluginpage',
            'plugin' => 'vrtour',
            'pluginsubtype' => 'assignsubmission',
            'pluginaction' => 'edit',
        ]);
    }

    /**
     * The full-page viewer of a submission's tour.
     *
     * @param int $cmid Course module id.
     * @param int $submissionid The submission.
     * @return \moodle_url
     */
    public static function view_url(int $cmid, int $submissionid): \moodle_url {
        return new \moodle_url('/mod/assign/view.php', [
            'id' => $cmid,
            'action' => 'viewpluginpage',
            'plugin' => 'vrtour',
            'pluginsubtype' => 'assignsubmission',
            'pluginaction' => 'view',
            'sid' => $submissionid,
        ]);
    }
}
