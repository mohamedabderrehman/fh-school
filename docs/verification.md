# Current release verification

Recorded on 2026-10-06 using disposable local data. Historical deployment is a separate owner-provided fact.

## Passed locally

PHP syntax and fixture HTTP checks passed: synthetic teacher login, required CSRF token, permitted class loading, foreign class/subject rejection, blocked database downloads and accessible public artwork. Browser checks passed class loading and grade editing. HTTP checks verified persisted grades, invalid-grade rejection and attendance increments; modified records were restored. Original sample SQLite records are owner-confirmed synthetic; reset snapshots are included.

## Checks and commands

```sh
php -S 127.0.0.1:8083 router.php
# Separate terminal:
python tools/check-syntax.py
python tools/check-demo.py
# Stop PHP before explicitly restoring only the included synthetic fixtures:
python tools/reset_demo.py --confirm
```

## CI status

The configured GitHub Actions workflows are registered, but the initial runs ended with startup_failure before any jobs or check annotations were created. Local results above are independent of CI. No passing CI badge is shown; the service supplied no further diagnostic message through the available API.

## Remaining platform and coverage limits

Legacy synthetic passwords remain plaintext inside the sample database. Treat this release as a local legacy demonstration; real student records require a password-storage migration and broader authorization review. Teacher grade and attendance persistence passed; broader admin/student workflows and cross-role browser review remain.

PHP checks used PHP 8.4.26; Node builds used Node 24.19; Python checks used Python 3.12.10 where applicable. This record does not claim production hardening, paid provider verification or tests on every platform.
