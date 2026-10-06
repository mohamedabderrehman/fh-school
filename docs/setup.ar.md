# الإعداد

استخدم PHP مع PDO SQLite وSQLite3 والجلسات. شغّل `php -S 127.0.0.1:8083 router.php`. امنح الكتابة لقواعد ومرفقات العرض فقط. حسابات العرض في `docs/demo-accounts.md`. لا تعرض الخادم التطويري للعامة. أوقفه قبل تنفيذ أمر استعادة العينات.

## التفاصيل والأوامر

Use PHP with PDO SQLite, SQLite3 and sessions. From this repository run `php -S 127.0.0.1:8083 router.php`. Only demo databases and attachment directories should be writable. Read `docs/demo-accounts.md` for synthetic account examples. Do not expose the development server publicly. Reset via `python tools/reset_demo.py --confirm` after stopping the server.

## متغيرات تقرأها الشيفرة

| Variable | Source consumer | Configuration rule |
|---|---|---|


لا تُحمَّل ملفات الأمثلة تلقائياً. تستخدم وحدات dotenv الملف حيث تكون مهيأة، ويستخدم PHP بيئة العملية أو الاستضافة. افصل المزودين عن العرض وأنشئ أسراراً جديدة واحفظها خارج المستودع.

## أوامر المكونات
