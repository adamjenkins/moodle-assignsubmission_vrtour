# Changelog

All notable changes to this plugin are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [0.1.0] - Unreleased

ALPHA. First release, Moodle 5.2 and 5.3. Not yet tagged.

### Added

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
