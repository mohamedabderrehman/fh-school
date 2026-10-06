# Clean setup

Enable SQLite3 and PDO SQLite. The included synthetic teacher is ahmed / teacher2025. Choose first secondary, scientific stream, class 2 and mathematics for populated fixtures. Set DEMO_ADMIN_PASSWORD in the PHP environment before using demo-admin administration. Stop the server before fixture reset. Passwords in the original synthetic teacher/student fixtures remain legacy plaintext.

## Commands

```sh
php -S 127.0.0.1:8083 router.php
# Separate terminal:
python tools/check-syntax.py
python tools/check-demo.py
# Stop PHP before explicitly restoring only the included synthetic fixtures:
python tools/reset_demo.py --confirm
```

## Complete configuration inventory

Use PHP with PDO SQLite, SQLite3 and sessions. From this repository run `php -S 127.0.0.1:8083 router.php`. Only demo databases and attachment directories should be writable. Read `docs/demo-accounts.md` for synthetic account examples. Do not expose the development server publicly. Reset via `python tools/reset_demo.py --confirm` after stopping the server.

## Environment variables read by source

| Variable | Source consumer | Configuration rule |
|---|---|---|


Environment examples do not load themselves. Node dotenv modules read local `.env` where configured; PHP uses its process/hosting environment. Keep provider integrations disconnected for demos. Generate a new secret with `node -e "console.log(require('crypto').randomBytes(32).toString('hex'))"` or equivalent, then store it privately.

## Declared component commands



## Source boundaries

| Component | Responsibility |
|---|---|
| `student.php` | Student authentication/dashboard |
| `teacher.php` | Teacher AJAX and dashboard |
| `management.php` | Admin accounts and announcement management |
| `student_management.php` | Admin academic records |
| `data/` | Synthetic distributed SQLite stores |


Variables in the inventory are not all mandatory: the preceding prerequisites identify the required core values. Provider variables are required only for their enabled live integration. Tests may use DEMO_API_URL to override the local target. Never point bootstrap/reset/check scripts at a production database.
