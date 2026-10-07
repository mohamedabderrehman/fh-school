# Deployment and troubleshooting

## Historical status

Older working school portal. I use synthetic school records for demonstrations; included fixtures are labelled synthetic.

بوابة مدرسية قديمة تعمل. أكد المالك أن سجلات قواعد البيانات وهمية وتُوسم العينات بأنها اصطناعية.

## Local release environment

Use fresh configuration, a disposable database/corpus and independently installed dependencies. This release never needs retired production services. Keep credentials, uploaded files, sessions, caches and signing material outside the public source. Credential removal does not revoke a provider key.

## Troubleshooting

### Login fails

Use the academic year belonging to the synthetic account; databases are distributed.

### Database missing

Stop the server and run the explicit fixture restore tool.

### AJAX rejected

The teacher must be authenticated and permitted for the requested class/subject.

### SQLite driver missing

Enable pdo_sqlite and SQLite3 in the PHP runtime.

## Current limits

Local demonstration only for the first release. Legacy password storage and broader CSRF/input review remain relevant before using real records.

عرض محلي للإصدار الأول. يلزم تحديث تخزين كلمات المرور ومراجعة CSRF والمدخلات قبل استخدام سجلات حقيقية.
