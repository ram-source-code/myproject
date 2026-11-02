# End-to-end functional + security checks. Run against a FRESH seeded database:
#   rm -f data/aqarat.sqlite* && ADMIN_PASSWORD=Admin12345! php bin/seed.php --demo
#   php -S 127.0.0.1:8080 -t public public/index.php &
#   pip install requests pillow && python3 tests/e2e.py
import re, io, requests, sys
from PIL import Image
import os
B=os.environ.get('BASE_URL','http://127.0.0.1:8080')
ADMIN_PW=os.environ.get('ADMIN_PASSWORD','Admin12345!')
def tok(html): return re.search(r'name="_csrf" value="([^"]+)"', html).group(1)
def flashes(html): return re.findall(r'class="alert alert-(\w+)">.*?<p>(.*?)</p>', html, re.S)
ok=lambda c,m: print(('PASS ' if c else 'FAIL ')+m)

s=requests.Session()
# CSRF missing
r=s.post(B+'/login', data={'email':'x','password':'y'}); ok(r.status_code==419,'POST without CSRF -> 419')
r=s.get(B+'/register'); t=tok(r.text)
r=s.post(B+'/register', data={'_csrf':t,'name':'Test User','email':'t@example.com','phone':'0501234567','role':'agent','password':'secret123','password_confirm':'secret123'})
ok('/dashboard' in r.url and 'تم إنشاء حسابك' in r.text, 'register + auto-login')
# validation errors
t=tok(s.get(B+'/dashboard/properties/new').text)
r=s.post(B+'/dashboard/properties', data={'_csrf':t,'title':'abc'})
ok('aria-invalid="true"' in r.text and 'form_has_errors' not in r.text and 'يرجى تصحيح' in r.text, 'listing validation errors shown')
ok('value="abc"' in r.text, 'old input preserved')
r=s.get(B+'/dashboard/properties/new'); ok('value="abc"' not in r.text, 'old input single-use')
# images
def img(fmt, size=(1200,800), mode='RGB'):
    b=io.BytesIO(); Image.new(mode,size,(200,100,50,128) if mode=='RGBA' else (200,100,50)).save(b,fmt); return b.getvalue()
files=[('images[]',('a.jpg',img('JPEG'),'image/jpeg')),('images[]',('b.png',img('PNG',mode='RGBA'),'image/png')),
       ('images[]',('evil.jpg',b'<?php system($_GET["c"]); ?>','image/jpeg')),('images[]',('tiny.png',img('PNG',(50,50)),'image/png')),
       ('images[]',('c.gif',img('GIF'),'image/gif'))]
t=tok(s.get(B+'/dashboard/properties/new').text)
data={'_csrf':t,'title':'شقة اختبار ثلاث غرف','purpose':'rent','rent_period':'monthly','type':'apartment','price':'٤٥٠٠','city_id':'1','district':'العليا','area':'120.5','bedrooms':'3','bathrooms':'2','features[]':['pool','parking','HACK'],'description':'وصف <script>alert(1)</script>'}
r=s.post(B+'/dashboard/properties', data=data, files=files)
f=flashes(r.text); print(f)
m=re.search(r'/dashboard/properties/(\d+)/edit', r.url); pid=m.group(1)
ok(sum(1 for x in f if x[0]=='error')==2, 'evil + tiny images rejected, others accepted')
ok(r.text.count('_t.webp')==3, '3 webp thumbs on edit page')
ok('قيد المراجعة' in r.text, 'non-admin listing is pending')
# public can't see pending
anon=requests.Session(); ok(anon.get(B+'/property/'+pid).status_code==404,'pending hidden from public')
r=s.get(B+'/property/'+pid); ok(r.status_code==200 and '&lt;script&gt;' in r.text and '<script>alert' not in r.text,'owner sees it; XSS escaped')
ok('4,500' in r.text,'arabic digits price parsed')
webps=re.findall(r'/uploads/([a-f0-9]{32}\.webp)', r.text)
im=Image.open(io.BytesIO(requests.get(B+'/uploads/'+webps[0]).content)); ok(im.format=='WEBP','served file is real WEBP %s'%(im.size,))
# IDOR: other user cannot edit
o=requests.Session(); t=tok(o.get(B+'/register').text)
o.post(B+'/register', data={'_csrf':t,'name':'Other','email':'o@example.com','password':'secret123','password_confirm':'secret123'})
ok(o.get(B+'/dashboard/properties/%s/edit'%pid).status_code==404,'IDOR edit blocked')
t=tok(o.get(B+'/dashboard').text)
ok(o.post(B+'/dashboard/properties/%s/delete'%pid, data={'_csrf':t}).status_code==404,'IDOR delete blocked')
ok(o.get(B+'/admin').status_code==403,'non-admin /admin 403')
# admin approve
a=requests.Session(); t=tok(a.get(B+'/login').text)
r=a.post(B+'/login', data={'_csrf':t,'email':'admin@aqarat.local','password':ADMIN_PW}); ok('/dashboard' in r.url,'admin login')
t=tok(a.get(B+'/admin').text)
r=a.post(B+'/admin/properties/%s/status'%pid, data={'_csrf':t,'status':'published'}, headers={'Referer':B+'/admin'})
ok(anon.get(B+'/property/'+pid).status_code==200,'approved listing public')
# favorite json
t=tok(o.get(B+'/property/'+pid).text)
r=o.post(B+'/favorite/'+pid, data={'_csrf':t}, headers={'Accept':'application/json'}); ok(r.json().get('saved') is True,'favorite json toggle')
r=o.get(B+'/dashboard/favorites'); ok('شقة اختبار' in r.text,'favorites page lists it')
# inquiry
t=tok(anon.get(B+'/property/'+pid).text)
r=anon.post(B+'/property/'+pid+'/inquiry', data={'_csrf':t,'name':'Ali','email':'ali@example.com','phone':'','message':'I want to visit this place please'})
ok('تم إرسال استفسارك' in r.text,'inquiry sent')
r=s.get(B+'/dashboard/inquiries'); ok('ali@example.com' in r.text,'owner sees inquiry')
# set cover / delete image
t=tok(s.get(B+'/dashboard/properties/%s/edit'%pid).text)
ids=re.findall(r'/dashboard/images/(\d+)/delete', s.get(B+'/dashboard/properties/%s/edit'%pid).text)
r=s.post(B+'/dashboard/images/%s/cover'%ids[-1], data={'_csrf':t}); ok('تم تعيين صورة الغلاف' in r.text,'set cover')
r=o.post(B+'/dashboard/images/%s/delete'%ids[0], data={'_csrf':tok(o.get(B+'/dashboard').text)}); ok(r.status_code==404,'IDOR image delete blocked')
r=s.post(B+'/dashboard/images/%s/delete'%ids[0], data={'_csrf':t}); ok(r.text.count('_t.webp')==2,'image deleted')
# login throttle
x=requests.Session()
for i in range(6):
    t=tok(x.get(B+'/login').text); r=x.post(B+'/login', data={'_csrf':t,'email':'t@example.com','password':'wrong'})
ok('محاولات كثيرة' in r.text,'login throttled after 5 failures')
# english
r=anon.get(B+'/lang/en', headers={'Referer':B+'/properties'}); ok('dir="ltr"' in r.text and 'All properties' in r.text,'english LTR')
# filters
r=anon.get(B+'/properties', params={'purpose':'sale','type':'villa','min_price':'1000000','sort':'price_desc'})
prices=[int(p.replace(',','')) for p in re.findall(r'class="card-price">SAR ([\d,]+)', r.text)]
ok(prices==sorted(prices,reverse=True) and all(p>=1000000 for p in prices),'filter+sort correct %s'%prices[:3])
# moderation: owner edit of a published listing goes back to pending
t=tok(s.get(B+'/dashboard/properties/%s/edit'%pid).text)
data2=dict(data); data2['_csrf']=t; data2['title']='تعديل بعد الموافقة للاختبار'
r=s.post(B+'/dashboard/properties/'+pid, data=data2)
ok(requests.get(B+'/property/'+pid).status_code==404,'edited published listing returns to review (hidden)')
