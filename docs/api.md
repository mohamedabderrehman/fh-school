# API and execution paths

This index is extracted from the current source. Router-local paths require their mount prefix from the server entry point. PHP endpoint paths map directly to files unless Apache rewrites them. Controllers and auth middleware are authoritative for request bodies and permissions.

See the source entry points below; this project does not declare Express/Flask router paths.

## Source entry points

- [student.php](../student.php)
- [teacher.php](../teacher.php)
- [management.php](../management.php)
- [student_management.php](../student_management.php)

## الاستخدام

المسارات المذكورة محلية للموجه وتحتاج بادئة الربط في الخادم. ملفات PHP هي مرجع المسارات ما لم تُعَد كتابتها. استخدم بيانات اصطناعية وفحوص الصلاحيات الموجودة في الشيفرة.


## Representative usage

Teacher POST actions require an authenticated session, a matching CSRF header and authorized class/subject values. Loading a permitted class does not grant permission for every subject or a different student. Fixture logins are local synthetic accounts.

```sh
python tools/check-demo.py
# The tool logs in, reads the session CSRF token, requests a populated synthetic
# class, and verifies rejection of foreign class/subject requests.
python tools/reset_demo.py --confirm
```
