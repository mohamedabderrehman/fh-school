# A working legacy school portal

## From the problem to the implementation

Give students, teachers and administrators role-specific views of academic records and school communication.

Student signs into an academic-year record → teacher opens a permitted subject/class → grades or attendance are saved → administrator publishes announcements and manages accounts.

## Decisions and tradeoffs

Databases are distributed by year, branch and stream, with separate teachers and posts stores. The design simplifies small local datasets but complicates cross-stream reporting and migrations.

The historical username:password field is retained for synthetic local accounts and documented accurately. It must not be used for real student credentials.

Teacher AJAX endpoints now check authentication and the requested class/subject before execution. Public database downloads are blocked by the demo router and Apache configuration.

Original sample state is retained as a fixture snapshot; restoring it overwrites only demo databases through an explicit reset tool.

## What the publication preparation established

PHP syntax and fixture HTTP checks passed: synthetic teacher login, required CSRF token, permitted class loading, foreign class/subject rejection, blocked database downloads and accessible public artwork. Browser checks passed class loading and grade editing. HTTP checks verified persisted grades, invalid-grade rejection and attendance increments; modified records were restored. Original sample SQLite records are owner-confirmed synthetic; reset snapshots are included.

## Deployment experience and evidence limits

Older working school portal. The owner confirms the database records are fake; included fixtures are labelled synthetic.

Legacy synthetic passwords remain plaintext inside the sample database. Treat this release as a local legacy demonstration; real student records require a password-storage migration and broader authorization review. Teacher grade and attendance persistence passed; broader admin/student workflows and cross-role browser review remain.

## Next steps

Complete the uncovered checks above, record the results, and update the demonstration. Retain the existing architecture and add reproducible synthetic cases before claiming performance improvements or another provider integration.
