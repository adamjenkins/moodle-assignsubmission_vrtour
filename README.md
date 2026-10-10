# Virtual tour — an assignment submission type for 360° photo tours

Virtual tour (`assignsubmission_vrtour`) is a submission plugin for the Moodle Assignment activity.
Students hand in a virtual tour: they upload 360° photos (equirectangular panoramas), link the
photos together, and place points of interest on them with text, images, audio or video. Teachers
grade the tour with everything the Assignment already offers: due dates, extensions, drafts,
group submissions, rubrics and marking guides, feedback and notifications.

Students build the tour in the room editor of the VR room activity, and the tour is shown in VR
room's built-in viewer (computer, phone or VR headset, with a list view when a browser cannot
show 3D).

## Requirements

- Moodle 5.2 or 5.3 (`$plugin->supported = [502, 503]`).
- [VR room](https://github.com/adamjenkins/moodle-mod_vrroom) (`mod_vrroom`) 2026101100 or later,
  with its bundled built-in viewer (`vrroomruntime_builtin`) 2026101100 or later. VR room provides
  the 360° photo rooms, the room editor and the viewer; a VR room activity is not needed in the course.
- The PHP GD extension (Moodle requires it) to make the smaller copies of each photo.

## Installation

1. Install VR room first.
2. Copy this repository to `public/mod/assign/submission/vrtour` in your Moodle code.
3. Visit *Site administration > Notifications*.

## Settings

Site settings (*Site administration > Plugins > Activity modules > Assignment > Submission plugins >
Virtual tour*):

- **Enabled by default** — whether new assignments have Virtual tour switched on (off by default).
- **Largest media file** — the default largest image, audio or video file on a point of interest.

The limits for 360° photos (largest width, largest file, and the widths of the smaller copies made
for computers and phones) are VR room's settings, shared with teachers' photo rooms: *Site
administration > Plugins > Activity modules > VR room > General settings*, "360° photos". The
defaults are 16384 pixels and 50 MB, with copies 8192 and 4096 pixels wide.

Assignment settings (in the *Submission types* section, when Virtual tour is ticked):

- **Most photos** (1 to 20, default 10).
- **Fewest points of interest** (0 to 100, default 3). A tour with fewer cannot be submitted.
- **Allow images / audio / video on points of interest** (all on by default). Text is always allowed.
- **Largest media file** for this assignment.

## How it works

- The student presses *Add submission* (or *Edit submission*) and then *Edit the tour*. The room
  editor opens with photo rooms only: the student uploads a 360° photo for each room, adds points
  of interest and links between the photos, and presses *Save*. The tour is stored each time; the
  student then goes back and saves (or submits) the submission as usual.
- Graders see "Photos: N. Points of interest: M." in the submissions table, and open the tour in
  the viewer from there.
- Storage: one row per submission in `assignsubmission_vrtour` (the rooms as JSON) and the files in
  the `tour` file area, one folder per upload, with the smaller copies of a photo in its `variants/`
  folder. Files no room uses are deleted when the tour is saved.
- Download all submissions includes each tour's files and the tour itself as `tour.json`.
- Privacy provider, backup and restore are included.

## Licence

GNU GPL v3 or later; see `LICENSE`. Parts of the plugin structure follow Moodle's
`assignsubmission_file` plugin (© 2012 NetSpot, privacy provider © 2018 Adrian Greeve), credited in
the files concerned.
