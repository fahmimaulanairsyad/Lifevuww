<?php
require_once __DIR__ . '/../config/game.php';
if (!function_exists('game_date_on_break') || !function_exists('game_goal_progress')) { fwrite(STDERR,"FAIL: missing journey rules\n");exit(1); }
$breaks=[['start_date'=>'2026-11-16','end_date'=>'2026-11-18']];
foreach (['2026-11-15'=>false,'2026-11-16'=>true,'2026-11-18'=>true,'2026-11-19'=>false] as $date=>$expected) {
    if(game_date_on_break($date,$breaks)!==$expected){fwrite(STDERR,"FAIL: inclusive break dates\n");exit(1);}
}
if(game_goal_progress(0,0)!==0 || game_goal_progress(2,4)!==50 || game_goal_progress(8,4)!==100){fwrite(STDERR,"FAIL: bounded goal progress\n");exit(1);}
echo "PASS: inclusive breaks and bounded milestone progress\n";
if (!function_exists('game_linked_milestones')) { fwrite(STDERR,"FAIL: missing linked milestone selection\n");exit(1); }
$links=[
    ['kind'=>'habit','activity_id'=>7,'goal_id'=>2,'milestone_id'=>11],
    ['kind'=>'habit','activity_id'=>7,'goal_id'=>3,'milestone_id'=>12],
    ['kind'=>'quest','activity_id'=>7,'goal_id'=>4,'milestone_id'=>13],
    ['kind'=>'habit','activity_id'=>8,'goal_id'=>5,'milestone_id'=>14],
    ['kind'=>'habit','activity_id'=>7,'goal_id'=>6,'milestone_id'=>15,'advanced_at'=>'2026-11-16 08:00:00'],
];
if(game_linked_milestones($links,'habit',7)!==[11,12] || game_linked_milestones($links,'quest',7)!==[13] || game_linked_milestones($links,'habit',8)!==[14] || game_linked_milestones($links,'habit',9)!==[]){fwrite(STDERR,"FAIL: linked milestones must match activity type/id across goals without cross-matching or re-advancing completed links\n");exit(1);}
if(game_linked_milestones([['kind'=>'habit','activity_id'=>7,'milestone_id'=>99,'advanced_at'=>'2026-11-16 08:00:00']],'habit',7)!==[]){fwrite(STDERR,"FAIL: advanced milestone link must not advance again\n");exit(1);}
echo "PASS: activity matching supports multiple goals and separates activity types\n";
if (!function_exists('game_dungeon_scaled_hp')) { fwrite(STDERR,"FAIL: missing capacity-aware dungeon\n");exit(1); }
if(game_dungeon_scaled_hp(1000,0)!==350 || game_dungeon_scaled_hp(1000,2)!==350 || game_dungeon_scaled_hp(1000,10)!==500 || game_dungeon_scaled_hp(1000,20)!==1000 || game_dungeon_scaled_hp(1000,1000)!==1000 || game_dungeon_scaled_hp(300,0)<100){fwrite(STDERR,"FAIL: bounded dungeon scaling\n");exit(1);}
echo "PASS: dungeon capacity scaling bounded to 35%-100% with minimum 100 HP\n";
if (!function_exists('game_focus_tier')) { fwrite(STDERR,"FAIL: missing default focus tier\n");exit(1); }
if(game_focus_tier(null)!=='beta'||game_focus_tier('invalid')!=='beta'||game_focus_tier('alpha')!=='alpha'){fwrite(STDERR,"FAIL: focus default tier invalid\n");exit(1);}
echo "PASS: focus modal always has a valid visible default tier\n";
if (!function_exists('game_break_days_between')) { fwrite(STDERR,"FAIL: missing break extension rule\n");exit(1); }
if(game_break_days_between('2026-11-15','2026-11-20',$breaks)!==3){fwrite(STDERR,"FAIL: break overlap day count\n");exit(1);}
echo "PASS: planned break days can extend quest deadlines\n";
if(!function_exists('game_extend_quest_for_break')){fwrite(STDERR,"FAIL: persistent break deadline rule missing\n");exit(1);}
if(game_extend_quest_for_break('2026-11-14','2026-11-17','2026-11-15','2026-11-18',$breaks)!=='2026-11-20' || game_extend_quest_for_break('2026-11-19','2026-11-21','2026-11-15','2026-11-18',$breaks)!=='2026-11-21' || game_extend_quest_for_break('2026-11-01','2026-11-10','2026-11-15','2026-11-18',$breaks)!=='2026-11-10'){fwrite(STDERR,"FAIL: only overlapping live quest dates extend, including a break crossing the original deadline\n");exit(1);}
