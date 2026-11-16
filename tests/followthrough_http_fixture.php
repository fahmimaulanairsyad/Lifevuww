<?php
if (PHP_SAPI!=='cli') { http_response_code(403);exit; }
require __DIR__.'/../config/database.php';
if(!in_array($host,['localhost','127.0.0.1'],true))throw new RuntimeException('Local database required.');
$action=$argv[1]??'';
if($action==='setup') {
    $name='fixture_http_'.bin2hex(random_bytes(5));$pass=bin2hex(random_bytes(12));
    $pdo->prepare('INSERT INTO users(username,password,exp,level,gold,last_daily_check) VALUES (?,?,0,1,1000,CURDATE())')->execute([$name,password_hash($pass,PASSWORD_DEFAULT)]);$uid=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO habits(user_id,habit_name,difficulty,exp_reward,category,last_reset) VALUES (?,'HTTP habit','Easy',5,'Intelligence',NOW())")->execute([$uid]);$hid=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO quests(user_id,quest_name,difficulty,start_date,end_date,duration_days,exp_reward,status,category) VALUES (?,'HTTP quest','Easy',CURDATE(),DATE_ADD(CURDATE(),INTERVAL 7 DAY),7,5,'In Progress','Intelligence')")->execute([$uid]);$qid=(int)$pdo->lastInsertId();
    echo json_encode(['uid'=>$uid,'hid'=>$hid,'qid'=>$qid,'username'=>$name,'password'=>$pass]);exit;
}
$uid=(int)($argv[2]??0);
$owner=$pdo->prepare('SELECT username FROM users WHERE user_id=?');$owner->execute([$uid]);
if(!str_starts_with((string)$owner->fetchColumn(),'fixture_http_'))throw new RuntimeException('Fixture owner required.');
if($action==='state') {
    $u=$pdo->prepare('SELECT exp,gold,companion_exp FROM users WHERE user_id=?');$u->execute([$uid]);
    $m=$pdo->prepare('SELECT m.milestone_id,m.completed FROM goal_milestones m JOIN life_goals g ON g.goal_id=m.goal_id WHERE g.user_id=? ORDER BY m.milestone_id');$m->execute([$uid]);
    $f=$pdo->prepare('SELECT COUNT(*) FROM goal_focus_sessions WHERE user_id=?');$f->execute([$uid]);
    echo json_encode(['user'=>$u->fetch(),'milestones'=>$m->fetchAll(),'focus_count'=>(int)$f->fetchColumn()]);
} elseif($action==='expire_quest') {
    $pdo->prepare("INSERT INTO quests(user_id,quest_name,difficulty,start_date,end_date,duration_days,exp_reward,status,category) VALUES (?,'Expired fixture','Easy',DATE_SUB(CURDATE(),INTERVAL 8 DAY),DATE_SUB(CURDATE(),INTERVAL 1 DAY),7,5,'In Progress','Intelligence')")->execute([$uid]);
    $pdo->prepare("INSERT INTO quests(user_id,quest_name,difficulty,start_date,end_date,duration_days,exp_reward,status,category) VALUES (?,'Available fixture','Easy',CURDATE(),DATE_ADD(CURDATE(),INTERVAL 7 DAY),7,5,'In Progress','Intelligence')")->execute([$uid]);
    $pdo->prepare('UPDATE users SET last_daily_check=DATE_SUB(CURDATE(),INTERVAL 1 DAY) WHERE user_id=?')->execute([$uid]);echo 'ok';
} elseif($action==='age_focus') {
    $token=(string)($argv[3]??'');$sid=(string)($argv[4]??'');
    if(!preg_match('/^[a-f0-9]{64}$/',$token)||!preg_match('/^[a-zA-Z0-9,-]+$/',$sid))throw new RuntimeException('Invalid fixture arguments.');
    $pdo->prepare('UPDATE focus_rift_sessions SET started_at=DATE_SUB(NOW(),INTERVAL 1 HOUR) WHERE token=? AND user_id=? AND completed_at IS NULL')->execute([$token,$uid]);
    session_id($sid);session_start();
    if((int)($_SESSION['user_id']??0)!==$uid)throw new RuntimeException('Fixture session required.');
    $_SESSION['active_rift']['started_at']=time()-3600;session_write_close();echo 'ok';
} elseif($action==='cleanup') {
    // Restrict cleanup to rows created by this fixture user.
    foreach(['goal_activity_progress','goal_focus_sessions','focus_rift_sessions','companion_chronicle','goal_activities','goal_milestones'] as $t) {
        if($t==='goal_activities'||$t==='goal_milestones')$pdo->prepare("DELETE FROM $t WHERE goal_id IN (SELECT goal_id FROM life_goals WHERE user_id=?)")->execute([$uid]);
        else $pdo->prepare("DELETE FROM $t WHERE user_id=?")->execute([$uid]);
    }
    foreach(['life_goals','planned_breaks','habit_logs','quest_logs','habits','quests','skills','dungeon_raids','remember_tokens','user_relics','journal_entries'] as $t)$pdo->prepare("DELETE FROM $t WHERE user_id=?")->execute([$uid]);
    $pdo->prepare('DELETE FROM users WHERE user_id=?')->execute([$uid]);echo 'cleaned';
}
