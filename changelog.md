# Changelog

All notable changes to this plugin are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [0.1.2] - 2026-11-10

### Added

- Admin icon `pix/icon.svg`, shown on `/admin/plugins.php`.
- `tour_manager::existing_editable_submission()`: the user's submission when there is one (after the
  `can_edit_submission()` check), never created.
- `tour_manager::lock()`: the per-submission lock (`tour_<submissionid>`, `LOCK_TIMEOUT`,
  `errorconflict`), now shared by the editor save and the upload.
- `tour_manager::restorable_scenes()`: checks a backup's `scenesjson` against the stored-tour rules
  that need no assignment settings or files (`editor_manager::MAX_DOCUMENT_BYTES`, `MAX_PHOTOS`,
  `scene_validator::validate_set()`, panorama rooms without zones or props, allowed object kinds,
  `scene_validator::MEDIA_TYPES`, `editor_manager::TEXT_FORMATS`).
- `assign_submission_vrtour::log_change()` (split out of `save()`): triggers `submission_created` or
  `submission_updated`.
- New and extended tests in `tests/backup_restore_test.php`, `tests/external/editor_services_test.php`,
  `tests/form/asset_upload_test.php` and `tests/local/tour_manager_test.php`.

### Changed

- `tour_manager::load()` takes a nullable submission: with none it returns an empty tour, no assets,
  `submissionid` 0 and an empty `viewurl`.
- `assign_submission_vrtour::problem()` is public, for the editor save.

### Fixed

- In an assignment without drafts, the editor save (`tour_manager::save()`) of a submitted tour
  wrote only the plugin row, so a change after the due date or after grading was not flagged late
  or modified after grading, and a tour below the submit rules stayed submitted. It now applies
  `problem()` and refuses a failing tour, sets `assign_submission.timemodified` (also on the
  member's own row in a team submission, as `update_team_submission()` does) and triggers
  `submission_updated`/`submission_created` and `\mod_assign\event\assessable_submitted`.
- Opening the editor no longer creates an `assign_submission` row: the `editor_load` web service,
  the edit page (`view_page('edit')`) and the upload form's
  `check_access_for_dynamic_submission()` use `existing_editable_submission()`; only the save and
  `process_dynamic_submission()` create it. The edit page omits the Preview link while there is no
  submission.
- The upload form's count check against `MAX_UPLOADS`, `next_upload_number()` and the file creation
  now run under the per-submission lock, so concurrent uploads can neither share a `/N/` folder nor
  both pass the limit.
- Restore (`restore_assignsubmission_vrtour_subplugin`) no longer trusts the backup: a
  `scenesjson` that fails `restorable_scenes()` is restored as an empty tour, and `numphotos` and
  `numpoints` are recomputed with `tour_manager::counts()` instead of taken from the file.

## [0.1.1] - 2026-10-11

### Added

- Camp listing screenshots with captions (`.camp/screenshots/`, `screenshots:` in `.camp/listing.yml`).
- `.gitattributes` with export-ignore rules (`.github`, `.gitattributes`, `.gitignore`, `.camp`,
  `tests`, `node_modules`), which camp's release workflow requires; v0.1.0 failed to publish to
  camp without it.

### Changed

- `$plugin->version` 2026101101, `$plugin->release` 0.1.1. No code changes.

## [0.1.0] - 2026-10-11

ALPHA. First release, Moodle 5.2 and 5.3.

### Added

- camp registry listing (`.camp/listing.yml`) and release workflow
  (`.github/workflows/camp-release.yml`).
- Submission plugin `assignsubmission_vrtour` (`$plugin->version` 2026101100): table
  `assignsubmission_vrtour` (rooms as JSON, revision, photo and point counts) and file area `tour`
  (item id = submission id, folder `/N/` per upload, `/N/variants/` for the smaller copies).
- The tour editor (`view_page('edit')`) runs `mod_vrroom/editor_app` with this plugin's backend:
  web services `assignsubmission_vrtour_editor_load` and `assignsubmission_vrtour_editor_save`, and
  the upload form `assignsubmission_vrtour\form\asset_upload`. Saves accept photo rooms only, the
  student's own files only and the object and media kinds the assignment allows.
- The viewer (`view()` and `view_page('view')`) runs `vrroomruntime_builtin/standalone` with the
  config from `mod_vrroom\local\launch\scene_formatter::standalone_config`.
- Assignment settings: most photos, fewest points of interest (checked before submitting), allowed
  media, largest media file. Site settings: enabled by default, largest media file.
- Privacy provider, backup and restore, `copy_submission`, `get_files` (with `tour.json`).
