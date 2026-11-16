<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
// Explicit opt-in: fixture rows are rolled back; never use production credentials.
if (!in_array('--local', $argv, true)) { fwrite(STDERR,"Use --local against a local development database.\n"); exit(1); }
require __DIR__.'/../config/database.php';
if (!in_array($host,['localhost','127.0.0.1'],true)) throw new RuntimeException('Local database required.');
function check_followthrough(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); echo "PASS: $message\n"; }
$pdo->beginTransaction();
try {
    $name='fixture_'.bin2hex(random_bytes(6));
    $pdo->prepare('INSERT INTO users(username,password,exp,level,companion_exp,last_daily_check) VALUES (?,?,1000,11,239,CURDATE())')->execute([$name,password_hash('unused-fixture-password',PASSWORD_DEFAULT)]);
    $uid=(int)$pdo->lastInsertId();
    $goals=[];
    foreach (['One','Two'] as $title) {
        $pdo->prepare('INSERT INTO life_goals(user_id,title) VALUES (?,?)')->execute([$uid,$title]);$gid=(int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO goal_milestones(goal_id,title) VALUES (?,?)')->execute([$gid,'First step']);$mid=(int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO goal_activity_progress(user_id,kind,activity_id,goal_id,milestone_id) VALUES (?,\'habit\',123,?,?)')->execute([$uid,$gid,$mid]);$goals[]=$mid;
    }
    check_followthrough(advance_linked_goal_milestones($pdo,$uid,'quest',123)===0,'different activity kind cannot advance habit link');
    check_followthrough(advance_linked_goal_milestones($pdo,$uid+1,'habit',123)===0,'other user cannot advance links');
    check_followthrough(advance_linked_goal_milestones($pdo,$uid,'habit',123)===2,'one activity advances two linked goals');
    check_followthrough(advance_linked_goal_milestones($pdo,$uid,'habit',123)===0,'replay advances zero milestones');
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM goal_milestones m JOIN life_goals g ON g.goal_id=m.goal_id WHERE g.user_id=? AND m.completed=1');$stmt->execute([$uid]);
    check_followthrough((int)$stmt->fetchColumn()===2,'both milestones are completed');
    $stmt=$pdo->prepare('SELECT exp FROM users WHERE user_id=?');$stmt->execute([$uid]);
    check_followthrough((int)$stmt->fetchColumn()===1000,'goal propagation grants no duplicate EXP');
    $week=date('o-W');
    $pdo->prepare("INSERT INTO dungeon_raids(user_id,week_key,boss_key,boss_hp,boss_max_hp) VALUES (?,?,'gate_e_crawler',5,300)")->execute([$uid,$week]);
    $hit=deal_dungeon_damage($pdo,$uid,100,'Fixture');
    check_followthrough($hit['applied']===5 && $hit['boss_hp']===0,'damage and bond EXP cap at remaining HP');
    $stmt=$pdo->prepare('SELECT companion_exp FROM users WHERE user_id=?');$stmt->execute([$uid]);
    check_followthrough((int)$stmt->fetchColumn()===244,'only actual damage grants bond EXP');
    $stmt=$pdo->prepare('SELECT stage FROM companion_chronicle WHERE user_id=? ORDER BY event_id');$stmt->execute([$uid]);
    check_followthrough($stmt->fetchAll(PDO::FETCH_COLUMN)===['Hatchling','Awakened'],'chronicle records baseline then evolution');
    deal_dungeon_damage($pdo,$uid,100,'Fixture replay');
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM companion_chronicle WHERE user_id=?');$stmt->execute([$uid]);
    check_followthrough((int)$stmt->fetchColumn()===2,'defeated boss does not duplicate chronicle events');
    $pdo->rollBack();
    echo "PASS: all fixture writes rolled back\n";
} catch (Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); fwrite(STDERR,'FAIL: '.$error->getMessage()."\n"); exit(1); }
