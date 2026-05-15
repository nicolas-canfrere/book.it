# Batch room import uses all-or-nothing validation

When importing multiple rooms via CSV, we validate the entire file before writing anything to the database, and reject the whole batch if any row is invalid. We chose this over a best-effort approach (persist valid rows, skip invalid ones) because partial imports leave the hotel in an ambiguous state — the operator cannot tell at a glance which rooms made it in and which did not. With all-or-nothing, the invariant is simple: either the import succeeded completely or nothing changed. The operator fixes the CSV and retries.

## Considered options

**Best-effort (rejected):** persist valid rows, report failures for invalid ones. Rejected because it forces the operator to cross-reference the error report against the Room Catalogue to reconstruct what actually landed, and because idempotent retry becomes harder — re-uploading the corrected CSV would hit duplicate conflicts on the rows that already succeeded.

**Stop at first error (rejected):** identical problems to best-effort, with the added frustration that a second error only surfaces after the first is fixed.
