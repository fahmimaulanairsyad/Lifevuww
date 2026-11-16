<?php
require_once __DIR__.'/../config/game.php';
if (!function_exists('game_world_visual_state')) { fwrite(STDERR,"FAIL: visual world progress rule missing\n"); exit(1); }
foreach ([-5=>'quiet',0=>'quiet',1=>'awakening',4=>'awakening',5=>'flourishing',50=>'flourishing'] as $count=>$expected) {
    if (game_world_visual_state($count)!==$expected) { fwrite(STDERR,"FAIL: region art must use actual nonnegative progress thresholds\n");exit(1); }
}
echo "PASS: real attribute progress selects quiet, awakening, and flourishing artwork\n";
