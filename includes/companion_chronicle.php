<?php
// Read-only panel. Requires authenticated user and existing PDO connection.
$chronicleStmt=$pdo->prepare('SELECT companion_key,event_key,stage,bond_exp,created_at FROM companion_chronicle WHERE user_id=? ORDER BY event_id DESC LIMIT 12');
$chronicleStmt->execute([(int)$_SESSION['user_id']]);
$chronicleEvents=$chronicleStmt->fetchAll(PDO::FETCH_ASSOC);
$chronicleCatalog=game_companions();
?>
<section class="bento-panel mb-4" aria-labelledby="companion-chronicle-title">
    <h2 class="panel-title mb-3" id="companion-chronicle-title">Jejak companion</h2>
    <p class="text-muted small">Ikatan tumbuh lewat damage yang benar-benar diberikan. EXP ikatan mengikuti karakter saat kamu berganti companion.</p>
    <?php if (!$chronicleEvents): ?>
        <p class="small">Perjalanan belum tercatat. Selesaikan aktivitas untuk memulai ekspedisi bersama companion.</p>
    <?php else: ?>
        <ol class="companion-chronicle">
            <?php foreach ($chronicleEvents as $event):
                $eventComp=$chronicleCatalog[$event['companion_key']]??null;
                if (!$eventComp) continue;
                $stageSuffix=['Hatchling'=>'hatchling','Awakened'=>'awakened','Sovereign'=>'sovereign'][$event['stage']]??'hatchling';
                $eventImage='assets/images/companions/'.$event['companion_key'].'_'.$stageSuffix.'.png';
                if (!is_file(__DIR__.'/../'.$eventImage)) $eventImage=$eventComp['image'];
            ?>
            <li>
                <img src="../<?php echo htmlspecialchars($eventImage,ENT_QUOTES,'UTF-8'); ?>" width="48" height="48" alt="<?php echo htmlspecialchars($eventComp['name'].' '.$event['stage'],ENT_QUOTES,'UTF-8'); ?>">
                <div><strong><?php echo htmlspecialchars(game_world_label('companions',$event['companion_key'])); ?></strong><p><?php echo $event['event_key']==='first_record'?'Mulai tercatat pada tahap':'Berevolusi menjadi'; ?> <?php echo htmlspecialchars(game_world_label('companion_stages',$event['stage'])); ?> (<?php echo htmlspecialchars($event['stage']); ?>) · <?php echo (int)$event['bond_exp']; ?> EXP ikatan</p><time datetime="<?php echo htmlspecialchars(str_replace(' ','T',$event['created_at']).'+07:00'); ?>"><?php echo htmlspecialchars(indo_short_date($event['created_at'],true)); ?> WIB</time></div>
            </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>
