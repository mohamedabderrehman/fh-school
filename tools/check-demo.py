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
print('PASS: fixture database protection, public artwork, synthetic teacher login, CSRF, permitted class, foreign class and subject rejection.')
