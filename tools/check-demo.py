"""Use only the owner-confirmed synthetic school fixtures."""
import urllib.request,urllib.error,urllib.parse,http.cookiejar,json,re,os
base=os.environ.get('DEMO_API_URL','http://127.0.0.1:8083')
jar=http.cookiejar.CookieJar();client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
def call(path,data=None,token=None):
 headers={'X-CSRF-Token':token} if token else {}
 req=urllib.request.Request(base+path,data=urllib.parse.urlencode(data).encode() if data else None,headers=headers)
 try:r=client.open(req)
 except urllib.error.HTTPError as e:r=e
 return r.status,r.read().decode('utf-8')
assert call('/data/teachers/database.sqlite')[0]==403
assert call('/data/main/1.jpg')[0]==200
code,html=call('/teacher.php',{'username':'ahmed','password':'teacher2025'})
assert code==200 and 'teacher2025' not in html
match=re.search(r'const csrfToken = "([a-f0-9]+)"',html)
assert match,'CSRF token missing from authenticated form';token=match.group(1)
payload={'action':'get_students','year':'اول ثانوي','branch':'علمي','part':'','classNum':'2','subject':'الرياضيات'}
assert call('/teacher.php',payload)[0]==403,'CSRF required'
code,html=call('/teacher.php',payload,token)
assert code==200 and len(html)>100,(code,html)
assert call('/teacher.php',dict(payload,classNum='99'),token)[0]==403
assert call('/teacher.php',dict(payload,subject='unsupported'),token)[0]==403
import sqlite3
from pathlib import Path
db=Path(__file__).resolve().parent.parent/'data/1/S/database.sqlite'
with sqlite3.connect(db) as conn:
 row=conn.execute("SELECT student_id, mathematics_term1, absence_details, absence_count FROM scientific_students WHERE class_name='1 علمي 2' LIMIT 1").fetchone()
 assert row
 student,grade,details,count=row
try:
 code,body=call('/teacher.php',dict(payload,action='save_grades',student_id=student,subject_name=payload['subject'],grades=json.dumps({'term1':['17','18']})),token)
 assert code==200 and json.loads(body)['success']
 with sqlite3.connect(db) as conn:assert conn.execute('SELECT mathematics_term1 FROM scientific_students WHERE student_id=?',(student,)).fetchone()[0]=='17,18'
 assert call('/teacher.php',dict(payload,action='save_grades',student_id=student,subject_name=payload['subject'],grades=json.dumps({'term1':['21']})),token)[0]==422
 code,body=call('/teacher.php',dict(payload,action='register_mass_absence',student_ids=json.dumps([student]),from_time='08:00',to_time='09:00'),token)
 assert code==200 and json.loads(body)['success']
 with sqlite3.connect(db) as conn:assert conn.execute('SELECT absence_count FROM scientific_students WHERE student_id=?',(student,)).fetchone()[0]==int(count or 0)+1
finally:
 with sqlite3.connect(db) as conn:conn.execute('UPDATE scientific_students SET mathematics_term1=?,absence_details=?,absence_count=? WHERE student_id=?',(grade,details,count,student))
print('PASS: protected fixtures, teacher login, CSRF, class/subject isolation, persisted grades, invalid grades and attendance updates; modified records restored.')
