"""Real HTTP workflow against local XAMPP, with a disposable fixture account."""
import http.cookiejar
import json
import re
import subprocess
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
BASE = 'http://localhost/Lifevuww/'

def fixture(action, *args):
    result = subprocess.run([r'C:\xampp\php\php.exe', str(ROOT/'tests/followthrough_http_fixture.php'), action, *map(str,args)], cwd=ROOT, capture_output=True, text=True, check=True)
    return result.stdout

account=json.loads(fixture('setup'))
jar=http.cookiejar.CookieJar()
client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
csrf=''

def request(path, data=None, expected=200):
    global csrf
    if data is not None:
        data={'csrf_token':csrf, **data}
        encoded=urllib.parse.urlencode(data).encode()
    else:
        encoded=None
    try:
        response=client.open(BASE+path,encoded,timeout=20)
    except urllib.error.HTTPError as error:
        response=error
    text=response.read().decode('utf-8')
    assert response.code==expected,(path,response.code,text[:250])
    assert not re.search(r'Fatal error|PHP Warning|Parse error',text),path
    token=re.search(r'name="csrf_token" value="([^"]+)"',text)
    if token: csrf=token[1]
    return text

def state(): return json.loads(fixture('state',account['uid']))

try:
    request('login.php')
    request('login.php',{'username':account['username'],'password':account['password']})
    journey=request('pages/journey.php',{'action':'goal','title':'HTTP fixture goal','category':'Intelligence'})
    assert 'class="world-atlas"' in journey and journey.count('class="world-region world-region-')==5
    wealth=request('pages/journey.php?region=bekalara')
    assert 'bekalara_quiet.png' in wealth and 'Rawat Wealth' in wealth
    invalid=request('pages/journey.php?region=../../invalid')
    assert 'aksarimba_quiet.png' in invalid
    goal=int(re.search(r'name="goal_id" value="(\d+)"',journey)[1])
    journey=request('pages/journey.php',{'action':'milestone','goal_id':goal,'title':'Habit step'})
    first=int(re.search(r'name="milestone_id" value="(\d+)"',journey)[1])
    journey=request('pages/journey.php',{'action':'milestone','goal_id':goal,'title':'Quest step'})
    second=max(int(x) for x in re.findall(r'name="milestone_id" value="(\d+)"',journey))
    request('pages/journey.php',{'action':'link','goal_id':goal,'kind':'habit','activity_id':account['hid'],'milestone_id':first})
    request('pages/journey.php',{'action':'link','goal_id':goal,'kind':'quest','activity_id':account['qid'],'milestone_id':second})
    assert int(state()['user']['exp'])==0
    request('pages/habits.php',{'complete_habit':1,'habit_id':account['hid']})
    once=state()
    assert int(once['milestones'][0]['completed'])==1
    request('pages/habits.php',{'complete_habit':1,'habit_id':account['hid']})
    assert state()==once,'duplicate habit completion changed state'
    request('pages/habits.php',{'undo_habit':1,'habit_id':account['hid']})
    assert int(state()['milestones'][0]['completed'])==1,'undo lost achieved goal step'
    request('pages/quests.php',{'complete_quest':1,'quest_id':account['qid']})
    assert all(int(m['completed'])==1 for m in state()['milestones'])
    print('PASS: create goal, link habit/quest, complete, replay, undo preserving milestone')
    start=json.loads(request('pages/focus_rift.php',{'action':'start_rift','rift_key':'alpha','goal_id':goal}))
    token=start['session_token']
    body={'action':'claim','rift_key':'alpha','attribute':'Intelligence','session_token':token}
    request('pages/focus_rift.php',body,409)
    sid=next(cookie.value for cookie in jar if cookie.name=='PHPSESSID')
    fixture('age_focus',account['uid'],token,sid)
    result=json.loads(request('pages/focus_rift.php',body))
    assert result['success'] and state()['focus_count']==1
    request('pages/focus_rift.php',body,409)
    assert state()['focus_count']==1
    print('PASS: focus early rejection, completed claim, replay rejection, one goal focus record')
    for page in ['dashboard','journey','skills','profile','dungeon','calendar','journal','history','shop','habits','quests']:
        request('pages/'+page+'.php')
    print('PASS: all 11 application pages render through authenticated HTTP')
    journey=request('pages/journey.php',{'action':'milestone','goal_id':goal,'title':'Orphan replacement step'})
    orphan=max(int(x) for x in re.findall(r'name="milestone_id" value="(\d+)"',journey))
    request('pages/journey.php',{'action':'link','goal_id':goal,'kind':'habit','activity_id':account['hid'],'milestone_id':orphan})
    # A fresh goal is used because completed activity-to-goal mappings are retained.
    journey=request('pages/journey.php',{'action':'goal','title':'Orphan fixture','category':'Health'})
    orphan_goal=int(re.search(r'name="goal_id" value="(\d+)"',journey)[1])
    journey=request('pages/journey.php',{'action':'milestone','goal_id':orphan_goal,'title':'Replace deleted habit'})
    orphan_step=int(re.search(r'name="milestone_id" value="(\d+)"',journey)[1])
    request('pages/journey.php',{'action':'link','goal_id':orphan_goal,'kind':'habit','activity_id':account['hid'],'milestone_id':orphan_step})
    request('pages/habits.php',{'delete_habit':1,'habit_id':account['hid']})
    journey=request('pages/journey.php')
    assert 'Aktivitas sudah dihapus' in journey and 'delete_link' in journey
    request('pages/journey.php',{'action':'delete_link','goal_id':orphan_goal,'kind':'habit','activity_id':account['hid']})
    journey=request('pages/journey.php',{'action':'link','goal_id':orphan_goal,'kind':'quest','activity_id':account['qid'],'milestone_id':orphan_step})
    assert 'Setiap langkah hanya' not in journey
    print('PASS: deleted activity remains detachable and milestone can be relinked')
    fixture('expire_quest',account['uid'])
    dashboard=request('pages/dashboard.php')
    assert 'dashboard-recovery' in dashboard and 'Mulai lagi dengan ringan' in dashboard
    dashboard=request('pages/dashboard.php',{'dismiss_recovery':1})
    assert 'id="recovery-title"' not in dashboard
    print('PASS: rollover failure shows recovery, daily dismissal persists')
finally:
    fixture('cleanup',account['uid'])
    print('Fixture account and related rows removed')
