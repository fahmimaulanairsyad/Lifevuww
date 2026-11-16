<?php
require_once __DIR__.'/../config/game.php';
if (!function_exists('game_companion_transitions') || !function_exists('game_applied_damage')) { fwrite(STDERR,"FAIL: companion chronicle rules missing\n");exit(1); }
foreach ([[0,239,[]],[239,240,['Awakened']],[559,560,['Sovereign']],[0,720,['Awakened','Sovereign']],[560,500,[]]] as [$old,$new,$expected]) {
    if (game_companion_transitions($old,$new)!==$expected) {fwrite(STDERR,"FAIL: stage crossing boundaries\n");exit(1);}
}
if (game_applied_damage(5,100)!==5 || game_applied_damage(0,100)!==0 || game_applied_damage(100,-5)!==0) {fwrite(STDERR,"FAIL: no overkill bond EXP\n");exit(1);}
echo "PASS: evolution boundaries, multi-stage crossing, and overkill cap\n";
if(!function_exists('game_is_transaction_abort')) {fwrite(STDERR,"FAIL: transaction abort classification missing\n");exit(1);}
$deadlock=new PDOException('Deadlock');$deadlock->errorInfo=['40001',1213,'deadlock'];
$ordinary=new PDOException('Missing table');$ordinary->errorInfo=['42S02',1146,'missing'];
if(!game_is_transaction_abort($deadlock)||game_is_transaction_abort($ordinary)) {fwrite(STDERR,"FAIL: deadlocks require full transaction abort, ordinary errors do not\n");exit(1);}
