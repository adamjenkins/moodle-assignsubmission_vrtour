# Changes

## Unreleased (prototype 2026-11-26)

- Saving a tour that has a photo now makes the submission a draft (in assignments with drafts), as saving the submission form does. Students and the grading table no longer see "No submission" for a saved tour.
- The editor's Done and "Back to my submission" return to the assignment page, with a note on what is left to do.
- The submission form's main button is now "Build your tour" or "Continue your tour". It says what the assignment asks for, and warns about unsaved fields only when the form has other parts.
- The editor page has a three-step guide and a "No 360° camera?" note. The photo upload dialog mentions phone panoramas.
- The submission summary says whether the tour has enough points of interest to be handed in.
- Graders see a readable tour outline above the 3D tour: each photo, its points of interest with their text, its links, and any photo no link reaches. The full-page viewer leads graders back to grading.
- Preview appears only once the tour has a photo.
- Clearer setting names: "Most photos in a tour", "Points of interest needed", "Largest image, audio or video file".
- A new tour opens on a start card instead of an empty editor: one "Add your first 360° photo" button, three practice photos (Poly Haven, CC0) that start the tour in one click, and "No 360° camera?" steps with pictures. After the first photo, a tip above the editor says what to do next, and the guide shrinks to one line.
- The submission form shows the tour as a card with a checklist ("At least 3 points of interest — 2 so far"). When the tour is the only thing to hand in and drafts are on, the form's own "Save changes" is hidden: the editor's save already makes the draft.
- Back from the editor, the note says what the tour still needs, with a "Continue your tour" button.
- The grader's outline starts with a one-line verdict and shows a small picture of each photo.
- The editor and the full-page viewer no longer carry Moodle 5.3's sticky "Previous / Next" bar.
- The editor gets photo-only mode, an empty-state panel, a live requirements strip and an always-visible exit from VR room's editor, when the installed VR room supports them.

## 0.1.3 (2026-11-15) — ALPHA

- The plugin can now be installed with Composer from Packagist: `composer require adamjenkins/moodle-assignsubmission_vrtour`, which also brings in VR room. No change to the plugin itself.
