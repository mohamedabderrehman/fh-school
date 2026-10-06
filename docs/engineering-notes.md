## A working school workflow in a legacy PHP application

FH-School connects student viewing, teacher grade and absence entry, class and stream organization, announcements and administration. The supplied records are owner-confirmed fake data, which makes them useful reproducible fixtures. Snapshot restoration lets an evaluator return to the original sample state after editing.

The application uses distributed SQLite databases rather than a central database service. That keeps the local runtime compact, but creates operational responsibilities: required files must exist, directories must be writable, and a reset must restore the related databases together. The directory guide explains these responsibilities instead of treating the data layout as incidental.

## Checking writes, not only dashboard rendering

The release review added CSRF protection and examined the teacher's permitted class, subject and student boundaries. A dashboard screenshot alone cannot establish that an edit was saved. The acceptance script verifies stored grade changes, rejects an out-of-range grade, checks absence increments and restores its modified fields.

The original synthetic password-storage behavior remains a documented legacy limitation for local demonstration. A real deployment would need a deliberate account/password migration and a wider administration review. Keeping the design and architecture makes the project's evolution explainable without disguising it as a newer framework application.
