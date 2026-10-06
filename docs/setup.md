# Setup and configuration

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
