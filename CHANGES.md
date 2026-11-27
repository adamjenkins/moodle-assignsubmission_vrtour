# Changes

## 0.2.0 (2026-11-27) — ALPHA

Building and grading a tour is now clear without a manual. Needs VR room 0.2.0.

For students:

- A new tour opens on a start card: one "Add your first 360° photo" button, three practice photos (free Poly Haven photos, CC0) that start the tour in one click, and "No 360° camera?" steps with pictures.
- The submission form shows the tour as a card with a checklist ("At least 3 points of interest (2 so far)") and one main button, "Build your tour" or "Continue your tour".
- In the editor, a tip says what to do next, and the button it names gets an amber ring. The tip turns green when the tour has everything it needs. The editor's photo panel shows a small picture of the photo.
- Saving a tour that has a photo makes the submission a draft (in assignments with drafts), so it no longer shows "No submission". "Back to my submission" returns to the assignment page with a note on what is left to do.
- A change to a tour that is already handed in (assignments without drafts) is recorded on the submission, so it shows as modified and late work is flagged. This now also applies to adding a practice photo.

For teachers:

- The full-page viewer shows the tour first, under a "Back to grading" button. A caption in the 3D view says which photo is showing ("Photo 2 of 4: The music hall") and what there is to find in it, also in fullscreen.
- Under the tour, an outline lists each photo, its points of interest with their text, its links, and any photo no link reaches. It starts with a one-line verdict: whether the tour has what the assignment asks for.
- Clearer settings: "Most photos in a tour", "Points of interest needed", "Largest image, audio or video file".

Look and feel:

- The tour pages use the MAVR look: dark see-through panels, line icons, amber focus rings, short fades (none for people who ask their device for less motion). On phones the heading is smaller, so the tour starts on the first screen.
- Moodle 5.3's "Previous / Next" bar no longer covers the editor or the viewer.

For administrators:

- Requires VR room (`mod_vrroom`) 0.2.0 (2026112700) or later. The tour editor uses that version's photo-only mode, empty state, requirements strip and exit button.
