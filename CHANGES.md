# Changes

## 0.1.2 (2026-11-10) — ALPHA

Fixes to how tours are saved, uploaded and restored.

- In an assignment without drafts, a student who changes a submitted tour in the tour editor now
  updates the submission as the submission form does: the change moves the submission's time, so a
  change after the due date shows as late and a change after grading shows as modified after
  grading, and it is logged. The editor also refuses to save a submitted tour that no longer meets
  the submit rules (at least one photo and the fewest points of interest).
- Opening the tour editor no longer creates an empty submission. The first upload or save does.
  Until then the editor's Preview link is not shown.
- Two uploads at the same moment can no longer share a folder or together go past the upload limit.
- Restoring a backup checks each tour against the editor's rules. A tour that breaks them is
  restored empty, and the photo and point counts are always recalculated from the tour itself.
- The plugin now has an icon on the Plugins overview page.
