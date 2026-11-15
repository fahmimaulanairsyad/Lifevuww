<?php
require_once __DIR__ . '/../config/game.php';
if (!function_exists('game_date_on_break') || !function_exists('game_goal_progress')) { fwrite(STDERR,"FAIL: missing journey rules\n");exit(1); }
$breaks=[['start_date'=>'2026-11-16','end_date'=>'2026-11-18']];
foreach (['2026-11-15'=>false,'2026-11-16'=>true,'2026-11-18'=>true,'2026-11-19'=>false] as $date=>$expected) {
    if(game_date_on_break($date,$breaks)!==$expected){fwrite(STDERR,"FAIL: inclusive break dates\n");exit(1);}
}
if(game_goal_progress(0,0)!==0 || game_goal_progress(2,4)!==50 || game_goal_progress(8,4)!==100){fwrite(STDERR,"FAIL: bounded goal progress\n");exit(1);}
echo "PASS: inclusive breaks and bounded milestone progress\n";
if (!function_exists('game_dungeon_scaled_hp')) { fwrite(STDERR,"FAIL: missing capacity-aware dungeon\n");exit(1); }
if(game_dungeon_scaled_hp(1000,0)!==350 || game_dungeon_scaled_hp(1000,2)!==350 || game_dungeon_scaled_hp(1000,20)!==1000 || game_dungeon_scaled_hp(300,0)<100){fwrite(STDERR,"FAIL: bounded dungeon scaling\n");exit(1);}
echo "PASS: dungeon capacity scaling bounded to 35%-100% with minimum 100 HP\n";
if (!function_exists('game_focus_tier')) { fwrite(STDERR,"FAIL: missing default focus tier\n");exit(1); }
if(game_focus_tier(null)!=='beta'||game_focus_tier('invalid')!=='beta'||game_focus_tier('alpha')!=='alpha'){fwrite(STDERR,"FAIL: focus default tier invalid\n");exit(1);}
echo "PASS: focus modal always has a valid visible default tier\n";
if (!function_exists('game_break_days_between')) { fwrite(STDERR,"FAIL: missing break extension rule\n");exit(1); }
if(game_break_days_between('2026-11-15','2026-11-20',$breaks)!==3){fwrite(STDERR,"FAIL: break overlap day count\n");exit(1);}
echo "PASS: planned break days can extend quest deadlines\n";
