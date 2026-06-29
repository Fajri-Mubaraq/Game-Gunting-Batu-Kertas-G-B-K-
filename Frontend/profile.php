<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../Backend/database.php';
$my_player_id = $_SESSION['player_id'];
$player_id = trim($_GET['id'] ?? '');
if ($player_id === '') {
    $player_id = $my_player_id;
}
$is_me = ($player_id === $my_player_id);
$db = getDB();
$migs=['display_name'=>"ALTER TABLE players ADD COLUMN display_name VARCHAR(30) DEFAULT NULL AFTER username",'avatar_choice'=>"ALTER TABLE players ADD COLUMN avatar_choice TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER avatar",'bio'=>"ALTER TABLE players ADD COLUMN bio VARCHAR(160) DEFAULT NULL AFTER avatar_choice",'username_changes'=>"ALTER TABLE players ADD COLUMN username_changes TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER max_win_streak"];
foreach($migs as $col=>$sql){try{$c=$db->query("SHOW COLUMNS FROM players LIKE '$col'")->fetchAll();if(empty($c))$db->exec($sql);}catch(Throwable){}}
try{$db->exec("CREATE TABLE IF NOT EXISTS username_history(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,player_id VARCHAR(20) NOT NULL,old_username VARCHAR(30) NOT NULL,new_username VARCHAR(30) NOT NULL,changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),INDEX idx_uh_player(player_id),CONSTRAINT fk_uh_p FOREIGN KEY(player_id) REFERENCES players(id) ON DELETE CASCADE)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");}catch(Throwable){}
// ── Avatar Unlock Table ──
try{$db->exec("CREATE TABLE IF NOT EXISTS avatar_unlocks(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,player_id VARCHAR(20) NOT NULL,avatar_index TINYINT UNSIGNED NOT NULL,unlocked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY uq_av(player_id,avatar_index),INDEX idx_av_p(player_id))ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");}catch(Throwable){}
$stmt=$db->prepare("SELECT * FROM players WHERE id = ? LIMIT 1");$stmt->execute([$player_id]);$player=$stmt->fetch();
if(!$player){header('Location: main_menu.php');exit;}
$AVATARS=['⚔️','🛡️','🔥','💎','🌪️','👑','🤖','🐉','⚡','🌙','🗡️','🔮'];

// ── Avatar Misi Unlock ──
// index 0 = selalu terbuka (default baru register)
// index 1-11 = harus selesaikan misi
$AVATAR_MISSIONS=[
  0=>['label'=>'Default','desc'=>'Avatar awal, selalu terbuka','icon'=>'✅','always'=>true],
  1=>['label'=>'5 Menang','desc'=>'Menangkan 5 pertandingan (PvP atau VS AI)','icon'=>'🏅','always'=>false],
  2=>['label'=>'10 Menang','desc'=>'Menangkan 10 pertandingan (PvP atau VS AI)','icon'=>'🏅','always'=>false],
  3=>['label'=>'1 Match PvP','desc'=>'Mainkan 1 pertandingan PvP','icon'=>'🥊','always'=>false],
  4=>['label'=>'5 Menang VS AI','desc'=>'Kalahkan AI sebanyak 5 kali','icon'=>'🤖','always'=>false],
  5=>['label'=>'Streak 3','desc'=>'Raih 3 kemenangan beruntun','icon'=>'🔥','always'=>false],
  6=>['label'=>'10 Menang VS AI','desc'=>'Kalahkan AI sebanyak 10 kali','icon'=>'🤖','always'=>false],
  7=>['label'=>'Rating 1100','desc'=>'Capai rating 1100 atau lebih','icon'=>'📈','always'=>false],
  8=>['label'=>'20 Menang','desc'=>'Menangkan 20 pertandingan (PvP atau VS AI)','icon'=>'🏆','always'=>false],
  9=>['label'=>'Tulis Bio','desc'=>'Isi bio profil kamu','icon'=>'📝','always'=>false],
  10=>['label'=>'Streak 5','desc'=>'Raih 5 kemenangan beruntun','icon'=>'🔥','always'=>false],
  11=>['label'=>'30 Menang','desc'=>'Menangkan 30 pertandingan (PvP atau VS AI)','icon'=>'👑','always'=>false],
];

// Evaluasi kondisi misi per avatar
function checkAvatarMission(int $idx, array $p): bool {
  $wins      = (int)($p['wins']??0);
  $ai_wins   = (int)($p['ai_wins']??0);
  $total_wins= $wins + $ai_wins;
  $total_pvp = $wins + (int)($p['losses']??0) + (int)($p['draws']??0);
  $streak    = (int)($p['max_win_streak']??0);
  $rating    = (int)($p['rating']??1000);
  $has_bio   = !empty($p['bio']);
  return match($idx) {
    0  => true,
    1  => $total_wins >= 5,
    2  => $total_wins >= 10,
    3  => $total_pvp >= 1,
    4  => $ai_wins >= 5,
    5  => $streak >= 3,
    6  => $ai_wins >= 10,
    7  => $rating >= 1100,
    8  => $total_wins >= 20,
    9  => $has_bio,
    10 => $streak >= 5,
    11 => $total_wins >= 30,
    default => false,
  };
}

// Progress per misi (untuk progress bar)
function getAvatarMissionProgress(int $idx, array $p): array {
  $wins      = (int)($p['wins']??0);
  $ai_wins   = (int)($p['ai_wins']??0);
  $total_wins= $wins + $ai_wins;
  $total_pvp = $wins + (int)($p['losses']??0) + (int)($p['draws']??0);
  $streak    = (int)($p['max_win_streak']??0);
  $rating    = (int)($p['rating']??1000);
  $has_bio   = !empty($p['bio']);
  return match($idx) {
    0  => ['cur'=>1,'max'=>1],
    1  => ['cur'=>min($total_wins,5),'max'=>5],
    2  => ['cur'=>min($total_wins,10),'max'=>10],
    3  => ['cur'=>min($total_pvp,1),'max'=>1],
    4  => ['cur'=>min($ai_wins,5),'max'=>5],
    5  => ['cur'=>min($streak,3),'max'=>3],
    6  => ['cur'=>min($ai_wins,10),'max'=>10],
    7  => ['cur'=>min($rating,1100),'max'=>1100],
    8  => ['cur'=>min($total_wins,20),'max'=>20],
    9  => ['cur'=>$has_bio?1:0,'max'=>1],
    10 => ['cur'=>min($streak,5),'max'=>5],
    11 => ['cur'=>min($total_wins,30),'max'=>30],
    default => ['cur'=>0,'max'=>1],
  };
}

// Ambil avatar yang sudah di-unlock dari DB
$db_unlocked=[];
try{$ust=$db->prepare("SELECT avatar_index FROM avatar_unlocks WHERE player_id=?");$ust->execute([$player_id]);foreach($ust->fetchAll() as $ur)$db_unlocked[]=(int)$ur['avatar_index'];}catch(Throwable){}

// Auto-unlock avatar yang misinya sudah terpenuhi & belum tercatat di DB
$all_unlocked_indices=[];
for($ai_idx=0;$ai_idx<count($AVATARS);$ai_idx++){
  $misi_ok=checkAvatarMission($ai_idx,$player);
  if($misi_ok && !in_array($ai_idx,$db_unlocked)){
    try{$db->prepare("INSERT IGNORE INTO avatar_unlocks(player_id,avatar_index)VALUES(?,?)")->execute([$player_id,$ai_idx]);}catch(Throwable){}
    $db_unlocked[]=$ai_idx;
  }
  if(in_array($ai_idx,$db_unlocked)||$misi_ok) $all_unlocked_indices[]=$ai_idx;
}
$all_unlocked_indices=array_unique($all_unlocked_indices);
$avatar_choice=(int)($player['avatar_choice']??0);
$avatar_emoji=$player['avatar']??($AVATARS[$avatar_choice]??'⚔️');
$display_name=htmlspecialchars($player['display_name']??$player['username']);
$username=htmlspecialchars($player['username']);
$bio=htmlspecialchars($player['bio']??'');
$username_changes=(int)($player['username_changes']??0);
$changes_left=max(0,3-$username_changes);
$wins=(int)($player['wins']??0);$losses=(int)($player['losses']??0);$draws=(int)($player['draws']??0);
$total_pvp=$wins+$losses+$draws;$winrate=$total_pvp>0?round($wins/$total_pvp*100,1):0;
$ai_wins=(int)($player['ai_wins']??0);$ai_losses=(int)($player['ai_losses']??0);$ai_draws=(int)($player['ai_draws']??0);
$total_ai=$ai_wins+$ai_losses+$ai_draws;$ai_winrate=$total_ai>0?round($ai_wins/$total_ai*100,1):0;
$rating=(int)($player['rating']??1000);$max_streak=(int)($player['max_win_streak']??0);$cur_streak=(int)($player['current_win_streak']??0);
$t_rock=(int)($player['total_rock']??0);$t_paper=(int)($player['total_paper']??0);$t_scissors=(int)($player['total_scissors']??0);
$t_choices=$t_rock+$t_paper+$t_scissors;
$rock_pct=$t_choices>0?round($t_rock/$t_choices*100):33;$paper_pct=$t_choices>0?round($t_paper/$t_choices*100):33;$scissors_pct=$t_choices>0?round($t_scissors/$t_choices*100):34;
$rank=getPlayerRank($player_id);

// ── 24 Kartu PvP & Kartu Favorit ──
$ALL_CARDS=['drain_life'=>['name'=>'Drain Life 1','icon'=>'🩸','rarity'=>'common','desc'=>'Setiap menang +10 HP. Aktif 3 game.'],'gambling1'=>['name'=>'The Gambling I','icon'=>'🎲','rarity'=>'common','desc'=>'Menang +10 dmg, kalah +10 dmg diterima.'],'safe_play1'=>['name'=>'Safe Play I','icon'=>'🛡','rarity'=>'common','desc'=>'Kalah = 0 dmg, Menang = 50% dmg. 1 game.'],'barrier'=>['name'=>'Barrier 1','icon'=>'🔮','rarity'=>'common','desc'=>'Kalah = 50% damage. Aktif sampai kalah 1x.'],'critical_attack'=>['name'=>'Critical Attack','icon'=>'⚡','rarity'=>'common','desc'=>'50% chance +30 dmg saat menang. Aktif 2 game.'],'tie_breaker'=>['name'=>'Tie Breaker','icon'=>'⚖️','rarity'=>'common','desc'=>'Seri jadi menang untukmu.'],'shield1'=>['name'=>'Shield I','icon'=>'🛡️','rarity'=>'common','desc'=>'+30 HP shield. Menyerap damage musuh.'],'god_attack1'=>['name'=>'God Attack I','icon'=>'⚡','rarity'=>'common','desc'=>'2× damage saat menang (5% chance 3×).'],'gambling2'=>['name'=>'The Gambling II','icon'=>'🃏','rarity'=>'rare','desc'=>'Menang +30 dmg, kalah +30 dmg diterima.'],'block_one'=>['name'=>'Block One','icon'=>'🚫','rarity'=>'rare','desc'=>'Lawan hanya bisa pakai 1 kartu ronde ini.'],'steal_hp'=>['name'=>'Steal HP 1','icon'=>'💉','rarity'=>'rare','desc'=>'-20 HP lawan → +20 Shield kamu.'],'repeat'=>['name'=>'Repeat','icon'=>'🔁','rarity'=>'rare','desc'=>'Jika kalah, ronde diulang.'],'safe_play2'=>['name'=>'Safe Play II','icon'=>'🛡','rarity'=>'rare','desc'=>'Kalah = 0 dmg, Menang = 20 dmg normal.'],'god_attack2'=>['name'=>'God Attack II','icon'=>'⚔️','rarity'=>'rare','desc'=>'2× damage saat menang (20% chance 3×).'],'shield2'=>['name'=>'Shield II','icon'=>'🔷','rarity'=>'rare','desc'=>'+60 HP shield. Menyerap damage musuh.'],'gambling3'=>['name'=>'The Gambling III','icon'=>'🎰','rarity'=>'epic','desc'=>'Menang +50 dmg, kalah +20 dmg diterima.'],'reverse_result'=>['name'=>'Reverse Result','icon'=>'🔄','rarity'=>'epic','desc'=>'Kalah/Seri → Menang. 3 kesempatan.'],'god_attack3'=>['name'=>'God Attack III','icon'=>'💀','rarity'=>'epic','desc'=>'2× damage saat menang (50% chance 3×).'],'drain_life_2'=>['name'=>'Drain Life 2','icon'=>'🩸','rarity'=>'epic','desc'=>'Menang: musuh -10 HP & kamu +25 HP.'],'steal_hp2'=>['name'=>'Steal HP 2','icon'=>'🩻','rarity'=>'epic','desc'=>'-50 HP lawan → +50 Shield kamu.'],'double_damage'=>['name'=>'Barrier 2','icon'=>'🔮','rarity'=>'epic','desc'=>'Kalah = 25% damage. Aktif sampai kalah 1x.'],'full_damage'=>['name'=>'Full Damage','icon'=>'💥','rarity'=>'legend','desc'=>'Damage ×5 (total 100)! Aktif hingga menang pertama.'],'shield3'=>['name'=>'Shield III','icon'=>'🌟','rarity'=>'legend','desc'=>'+100 shield besar!'],'absolute_reset'=>['name'=>'Absolute Reset','icon'=>'♾️','rarity'=>'legend','desc'=>'Reset match ke ronde 1 game 1!']];
$rarity_meta=['common'=>['color'=>'#c0c0c0','glow'=>'rgba(192,192,192,.5)','border'=>'rgba(192,192,192,.25)','grad'=>'linear-gradient(135deg,rgba(192,192,192,.09),rgba(192,192,192,.02))','label'=>'COMMON'],'rare'=>['color'=>'#4da6ff','glow'=>'rgba(77,166,255,.55)','border'=>'rgba(77,166,255,.38)','grad'=>'linear-gradient(135deg,rgba(77,166,255,.12),rgba(77,166,255,.03))','label'=>'RARE'],'epic'=>['color'=>'#c084fc','glow'=>'rgba(192,132,252,.55)','border'=>'rgba(192,132,252,.38)','grad'=>'linear-gradient(135deg,rgba(192,132,252,.12),rgba(192,132,252,.03))','label'=>'EPIC'],'legend'=>['color'=>'#ffd700','glow'=>'rgba(255,215,0,.55)','border'=>'rgba(255,215,0,.38)','grad'=>'linear-gradient(135deg,rgba(255,215,0,.14),rgba(255,215,0,.03))','label'=>'LEGEND']];
// ── Kartu Favorit PvP (dari player_card_usage) ──
$card_usage_counts=[];
try{$sf=$db->prepare("SELECT card_id,use_count FROM player_card_usage WHERE player_id=? ORDER BY use_count DESC LIMIT 24");$sf->execute([$player_id]);foreach($sf->fetchAll() as $rf){$cid=$rf['card_id'];if(isset($ALL_CARDS[$cid]))$card_usage_counts[$cid]=(int)$rf['use_count'];}}catch(Throwable){}
arsort($card_usage_counts);$top3_ids=array_slice(array_keys($card_usage_counts),0,3);
$fav_cards=[];foreach($top3_ids as $cid){$fav_cards[]=array_merge($ALL_CARDS[$cid],['id'=>$cid,'uses'=>$card_usage_counts[$cid]]);}

// ── Kartu Favorit VS AI (dari ai_card_usage) ──
$ai_card_usage_counts=[];
try{$sf2=$db->prepare("SELECT card_id,use_count FROM ai_card_usage WHERE player_id=? ORDER BY use_count DESC LIMIT 24");$sf2->execute([$player_id]);foreach($sf2->fetchAll() as $rf2){$cid2=$rf2['card_id'];if(isset($ALL_CARDS[$cid2]))$ai_card_usage_counts[$cid2]=(int)$rf2['use_count'];}}catch(Throwable){}
arsort($ai_card_usage_counts);$top3_ai_ids=array_slice(array_keys($ai_card_usage_counts),0,3);
$fav_cards_ai=[];foreach($top3_ai_ids as $cid2){$fav_cards_ai[]=array_merge($ALL_CARDS[$cid2],['id'=>$cid2,'uses'=>$ai_card_usage_counts[$cid2]]);}
function getRankTier(int $r):array{return match(true){$r>=2000=>['name'=>'GRANDMASTER','icon'=>'👑','color'=>'#ffd700','glow'=>'rgba(255,215,0,.55)','min'=>2000,'max'=>9999],$r>=1700=>['name'=>'MASTER','icon'=>'💎','color'=>'#c084fc','glow'=>'rgba(192,132,252,.55)','min'=>1700,'max'=>1999],$r>=1500=>['name'=>'DIAMOND','icon'=>'🔷','color'=>'#4da6ff','glow'=>'rgba(77,166,255,.55)','min'=>1500,'max'=>1699],$r>=1300=>['name'=>'PLATINUM','icon'=>'🪙','color'=>'#7dff4d','glow'=>'rgba(125,255,77,.55)','min'=>1300,'max'=>1499],$r>=1100=>['name'=>'GOLD','icon'=>'🥇','color'=>'#f5c842','glow'=>'rgba(245,200,66,.55)','min'=>1100,'max'=>1299],$r>=950=>['name'=>'SILVER','icon'=>'🥈','color'=>'#c0c0c0','glow'=>'rgba(192,192,192,.55)','min'=>950,'max'=>1099],default=>['name'=>'BRONZE','icon'=>'🥉','color'=>'#cd7f32','glow'=>'rgba(205,127,50,.55)','min'=>0,'max'=>949]};}
$tier=getRankTier($rating);$tier_pct=min(100,max(0,(int)round(($rating-$tier['min'])/max(1,$tier['max']-$tier['min'])*100)));
function computeAchievements(array $p):array{$w=(int)($p['wins']??0);$l=(int)($p['losses']??0);$d=(int)($p['draws']??0);$aw=(int)($p['ai_wins']??0);$sk=(int)($p['max_win_streak']??0);$rt=(int)($p['rating']??1000);$rk=(int)($p['total_rock']??0);$pp=(int)($p['total_paper']??0);$sc=(int)($p['total_scissors']??0);$ch=(int)($p['avatar_choice']??0);$hasBio=!empty($p['bio']);return array_map(fn($a)=>array_merge($a,['unlocked'=>(bool)$a['cond']]),
[['id'=>'first_win','icon'=>'🏆','name'=>'Kemenangan Pertama','desc'=>'Menangkan pertandingan pertamamu!','cond'=>$w>=1],['id'=>'win_10','icon'=>'⚔️','name'=>'Pejuang','desc'=>'Menangkan 10 pertandingan ranked.','cond'=>$w>=10],['id'=>'win_50','icon'=>'🎖️','name'=>'Veteran','desc'=>'Menangkan 50 pertandingan ranked.','cond'=>$w>=50],['id'=>'win_100','icon'=>'👑','name'=>'Legenda','desc'=>'Menangkan 100 pertandingan ranked.','cond'=>$w>=100],['id'=>'streak_5','icon'=>'🔥','name'=>'Di Zona','desc'=>'Raih 5 kemenangan beruntun.','cond'=>$sk>=5],['id'=>'streak_10','icon'=>'⚡','name'=>'Tak Terkalahkan','desc'=>'Raih 10 kemenangan beruntun.','cond'=>$sk>=10],['id'=>'rating_1200','icon'=>'📈','name'=>'Naik Peringkat','desc'=>'Capai rating 1200+.','cond'=>$rt>=1200],['id'=>'rating_1500','icon'=>'💎','name'=>'Elite','desc'=>'Capai rating 1500+.','cond'=>$rt>=1500],['id'=>'first_pvp','icon'=>'🥊','name'=>'Petarung Sejati','desc'=>'Mainkan pertandingan PvP pertamamu.','cond'=>($w+$l+$d)>=1],['id'=>'ai_master','icon'=>'🤖','name'=>'Pembunuh AI','desc'=>'Kalahkan AI sebanyak 20 kali.','cond'=>$aw>=20],['id'=>'rock_master','icon'=>'🪨','name'=>'Rock Master','desc'=>'Gunakan Batu lebih dari 100 kali.','cond'=>$rk>=100],['id'=>'paper_master','icon'=>'📄','name'=>'Paper Tactician','desc'=>'Gunakan Kertas lebih dari 100 kali.','cond'=>$pp>=100],['id'=>'scissor_master','icon'=>'✂️','name'=>'Scissor Ninja','desc'=>'Gunakan Gunting lebih dari 100 kali.','cond'=>$sc>=100],['id'=>'pacifist','icon'=>'🤝','name'=>'Diplomat','desc'=>'Raih 10+ hasil seri.','cond'=>$d>=10],['id'=>'customizer','icon'=>'🎨','name'=>'Expresi Diri','desc'=>'Atur avatar dan bio profilmu.','cond'=>($ch>0||$hasBio)]]);}
$achievements=computeAchievements($player);$unlocked_count=count(array_filter($achievements,fn($a)=>$a['unlocked']));$total_ach=count($achievements);
$pvp_matches=[];$ai_matches=[];
try{$pvp_matches=getPlayerMatchHistory($player_id,5);}catch(Throwable){}
try{$ai_matches=getPlayerAIHistory($player_id,5);}catch(Throwable){}
$from = $_GET['from'] ?? '';
$back_href  = $from === 'collection.php' ? 'collection.php' : 'main_menu.php';
$back_label = $from === 'collection.php' ? '&larr; Back ' : '&larr; Menu';

// ── Siapkan data misi untuk JavaScript (untuk live-refresh) ──
$missions_for_js = [];
foreach($AVATAR_MISSIONS as $idx => $m) {
  $prog = getAvatarMissionProgress($idx, $player);
  $missions_for_js[] = [
    'index'     => $idx,
    'emoji'     => $AVATARS[$idx],
    'label'     => $m['label'],
    'desc'      => $m['desc'],
    'icon'      => $m['icon'],
    'unlocked'  => in_array($idx, $all_unlocked_indices),
    'cur'       => $prog['cur'],
    'max'       => $prog['max'],
  ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $display_name?> – Profil | Lucky Battle</title>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Bebas+Neue&family=Russo+One&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --rock:#ff4d4d;--paper:#4da6ff;--scissors:#7dff4d;
  --gr:rgba(255,77,77,.6);--gp:rgba(77,166,255,.6);--gs:rgba(125,255,77,.6);
  --dark:#05060d;--mid:#0b0d1a;--card:rgba(255,255,255,.028);
  --text:#eef0ff;--muted:rgba(238,240,255,.38);--border:rgba(238,240,255,.07);
  --card-bg:rgba(11,13,26,.92);
  
  /* Tier colors (Dark Mode) */
  --color-grandmaster: #ffd700;
  --color-master: #c084fc;
  --color-diamond: #4da6ff;
  --color-platinum: #7dff4d;
  --color-gold: #f5c842;
  --color-silver: #c0c0c0;
  --color-bronze: #cd7f32;

  --glow-grandmaster: rgba(255,215,0,.5);
  --glow-master: rgba(192,132,252,.5);
  --glow-diamond: rgba(77,166,255,.5);
  --glow-platinum: rgba(125,255,77,.5);
  --glow-gold: rgba(245,200,66,.5);
  --glow-silver: rgba(192,192,192,.5);
  --glow-bronze: rgba(205,127,50,.5);

  --rc: var(--color-<?php echo strtolower($tier['name']) ?>);
  --rg: var(--glow-<?php echo strtolower($tier['name']) ?>);

  /* Rarity Colors (Dark Mode) */
  --rarity-common-color: #c0c0c0;
  --rarity-common-glow: rgba(192,192,192,.5);
  --rarity-common-border: rgba(192,192,192,.25);
  --rarity-common-grad: linear-gradient(135deg,rgba(192,192,192,.09),rgba(192,192,192,.02));
  
  --rarity-rare-color: #4da6ff;
  --rarity-rare-glow: rgba(77,166,255,.55);
  --rarity-rare-border: rgba(77,166,255,.38);
  --rarity-rare-grad: linear-gradient(135deg,rgba(77,166,255,.12),rgba(77,166,255,.03));
  
  --rarity-epic-color: #c084fc;
  --rarity-epic-glow: rgba(192,132,252,.55);
  --rarity-epic-border: rgba(192,132,252,.38);
  --rarity-epic-grad: linear-gradient(135deg,rgba(192,132,252,.12),rgba(192,132,252,.03));
  
  --rarity-legend-color: #ffd700;
  --rarity-legend-glow: rgba(255,215,0,.55);
  --rarity-legend-border: rgba(255,215,0,.38);
  --rarity-legend-grad: linear-gradient(135deg,rgba(255,215,0,.14),rgba(255,215,0,.03));
}
html{scroll-behavior:smooth}
body{min-height:100vh;background:var(--dark);font-family:'Rajdhani',sans-serif;color:var(--text)}
canvas#bg{position:fixed;inset:0;z-index:0;pointer-events:none}

/* layers */
.hex-layer{position:fixed;inset:0;z-index:1;pointer-events:none;opacity:.04;
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='60' height='104'%3E%3Cpolygon points='30,2 58,17 58,47 30,62 2,47 2,17' fill='none' stroke='%234da6ff' stroke-width='0.8'/%3E%3Cpolygon points='30,52 58,67 58,97 30,112 2,97 2,67' fill='none' stroke='%234da6ff' stroke-width='0.8'/%3E%3C/svg%3E");
  background-size:60px 104px}
.noise{position:fixed;inset:0;z-index:2;pointer-events:none;opacity:.028;
  background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
  background-size:200px 200px}
.elines{position:fixed;inset:0;z-index:3;pointer-events:none;overflow:hidden}
.el{position:absolute;width:1px;background:linear-gradient(to bottom,transparent,rgba(77,166,255,.4),transparent);animation:elfall linear infinite}
@keyframes elfall{from{transform:translateY(-100vh);opacity:0}10%,90%{opacity:1}to{transform:translateY(100vh);opacity:0}}
.scanline{position:fixed;inset:0;z-index:4;pointer-events:none;
  background:repeating-linear-gradient(to bottom,transparent 0,transparent 3px,rgba(0,0,0,.06) 3px,rgba(0,0,0,.06) 4px)}
.vignette{position:fixed;inset:0;z-index:4;pointer-events:none;
  background:radial-gradient(ellipse at center,transparent 45%,rgba(0,0,0,.45) 100%)}
.corner{position:fixed;z-index:6;pointer-events:none}
.corner::before,.corner::after{content:'';position:absolute;background:rgba(77,166,255,.4)}
.corner::before{width:2px;height:40px}.corner::after{width:40px;height:2px}
.c-tl{top:16px;left:16px}.c-tr{top:16px;right:16px;transform:scaleX(-1)}
.c-bl{bottom:16px;left:16px;transform:scaleY(-1)}.c-br{bottom:16px;right:16px;transform:scale(-1)}
.corner::before,.corner::after{top:0;left:0}

/* ── NAVBAR ── */
.navbar{
  position:sticky;top:0;z-index:60;display:flex;align-items:center;justify-content:flex-end;
  padding:26px 28px 8px;background:transparent;
  border-bottom:none;backdrop-filter:none}

.nav-brand{font-family:'Bebas Neue',sans-serif;font-size:1.5rem;letter-spacing:.14em;
  background:linear-gradient(90deg,var(--rock),var(--paper),var(--scissors));
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.nav-right{display:flex;gap:8px}
.nav-btn{font-family:'Rajdhani',sans-serif;font-size:.7rem;font-weight:700;letter-spacing:.12em;
  padding:7px 18px;border:1px solid rgba(77,166,255,.2);background:transparent;
  color:rgba(77,166,255,.85);cursor:pointer;transition:all .2s;text-decoration:none;
  display:inline-flex;align-items:center;gap:5px;
  clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%)}
.nav-btn:not(.danger):hover{background:rgba(77,166,255,.18);color:#4da6ff;border-color:rgba(77,166,255,.45)}
.nav-btn.danger{border-color:rgba(255,77,77,.22);color:rgba(255,140,140,.6);background:rgba(255,77,77,.03)}
.nav-btn.danger:hover{border-color:rgba(255,77,77,.5);color:#ff9090;background:rgba(255,77,77,.09)}

/* ── PAGE ── */
.page{position:relative;z-index:10;max-width:1080px;margin:0 auto;padding:2rem 1.4rem 6rem}

/* ── HERO ── */
.hero{
  display:flex;gap:2.2rem;align-items:flex-start;position:relative;overflow:hidden;
  background:var(--card-bg);border:1px solid var(--border);
  padding:2.2rem 2.6rem;margin-bottom:1.4rem;
  clip-path:polygon(18px 0%,100% 0%,calc(100% - 18px) 100%,0% 100%)}
.hero::before{content:'';position:absolute;top:-1px;left:-1px;right:-1px;height:2px;
  background:linear-gradient(90deg,var(--rock),var(--paper),var(--scissors));background-size:200% 100%;animation:rbow 4s linear infinite}
/* hero ambient glow bg */
.hero::after{content:'';position:absolute;inset:0;pointer-events:none;
  background:radial-gradient(ellipse at 15% 50%,var(--rg) 0%,transparent 60%);opacity:.08}

/* avatar */
.avatar-wrap{flex-shrink:0;text-align:center}
.avatar-ring{
  width:120px;height:120px;font-size:54px;
  border:2px solid var(--rc);
  box-shadow:0 0 50px var(--rg),0 0 0 5px rgba(5,6,13,.9),inset 0 0 30px rgba(0,0,0,.4);
  display:flex;align-items:center;justify-content:center;cursor:pointer;
  background:linear-gradient(135deg,rgba(77,166,255,.1),rgba(125,255,77,.07));
  transition:all .35s;animation:rpulse 3.5s ease-in-out infinite alternate;
  clip-path:polygon(14px 0%,100% 0%,calc(100% - 14px) 100%,0% 100%)}
@keyframes rpulse{from{box-shadow:0 0 32px var(--rg),0 0 0 5px rgba(5,6,13,.9)}to{box-shadow:0 0 65px var(--rg),0 0 0 5px rgba(5,6,13,.9)}}
.avatar-ring:hover{transform:scale(1.07)}
.av-edit-btn{display:block;margin-top:9px;font-size:.63rem;letter-spacing:.14em;text-transform:uppercase;
  color:var(--muted);cursor:pointer;background:none;border:none;font-family:'Rajdhani',sans-serif;font-weight:700;transition:color .2s}
.av-edit-btn:hover{color:var(--text)}

/* hero info */
.hero-info{flex:1;min-width:0;position:relative;z-index:1}
.hero-dname{font-family:'Bebas Neue',sans-serif;font-size:2.2rem;color:var(--text);
  letter-spacing:.06em;margin-bottom:2px;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.edit-name-btn{font-size:14px;cursor:pointer;opacity:.35;transition:opacity .2s;flex-shrink:0}
.edit-name-btn:hover{opacity:1}
.hero-username{font-size:.68rem;color:var(--muted);letter-spacing:.12em;margin-bottom:12px;font-weight:600}

/* rank badge */
.rank-badge{
  display:inline-flex;align-items:center;gap:9px;
  border:1.5px solid var(--rc);padding:6px 18px;
  background:linear-gradient(135deg,rgba(5,6,13,.85),rgba(13,15,26,.9));
  box-shadow:0 0 28px var(--rg),inset 0 1px 0 rgba(255,255,255,.04);
  margin-bottom:14px;
  clip-path:polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%)}
.rank-icon{font-size:18px}
.rank-name{font-family:'Russo One',sans-serif;font-size:.68rem;letter-spacing:.22em;color:var(--rc)}
.rank-pos{font-size:.62rem;color:var(--muted)}

/* bio */
.bio-display{font-size:.82rem;color:var(--muted);font-style:italic;line-height:1.6;max-width:460px;
  border-left:2px solid var(--rc);padding-left:13px;transition:all .2s}
.bio-empty{opacity:.35;cursor:pointer}.bio-empty:hover{opacity:.6}

/* hero stats row */
.hero-stats{display:flex;gap:0;flex-wrap:wrap;margin-top:20px;
  border:1px solid var(--border);
  clip-path:polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%)}
.hstat{flex:1;min-width:70px;display:flex;flex-direction:column;align-items:center;justify-content:center;
  gap:3px;padding:12px 8px;position:relative}
.hstat+.hstat::before{content:'';position:absolute;left:0;top:20%;bottom:20%;width:1px;background:var(--border)}
.hstat-val{font-family:'Bebas Neue',sans-serif;font-size:1.5rem;letter-spacing:.05em;line-height:1}
.hstat-lbl{font-size:.58rem;letter-spacing:.2em;text-transform:uppercase;color:var(--muted);font-weight:700}
.cg{color:#f5c842}.cw{color:#7dff4d}.cl{color:#ff7d7d}.co{color:#ff9060}.cb{color:#90c4ff}

/* rating bar */
.rating-bar-wrap{margin-top:16px}
.rating-bar-hd{display:flex;justify-content:space-between;font-size:.66rem;margin-bottom:7px}
.rating-bar-lbl{color:var(--muted);letter-spacing:.14em;text-transform:uppercase;font-weight:700}
.rating-bar-val{font-family:'Bebas Neue',sans-serif;font-size:.92rem;letter-spacing:.08em;color:var(--rc)}
.rating-bar-track{height:5px;background:rgba(238,240,255,.05);overflow:hidden;position:relative}
.rating-bar-fill{height:100%;width:<?php echo $tier_pct?>%;
  background:linear-gradient(90deg,var(--rc),rgba(255,255,255,.6));
  box-shadow:0 0 12px var(--rg);transition:width 1.4s cubic-bezier(.4,0,.2,1)}
/* track glow pulse */
.rating-bar-track::after{content:'';position:absolute;inset:0;
  background:linear-gradient(90deg,transparent 0%,rgba(255,255,255,.04) 50%,transparent 100%);
  animation:barglow 2s ease-in-out infinite alternate}
@keyframes barglow{from{opacity:.4}to{opacity:1}}

/* ── GRID ── */
.grid{display:grid;grid-template-columns:1fr 1fr;gap:1.2rem}
.full{grid-column:1/-1}
@media(max-width:680px){
  .grid{grid-template-columns:1fr}
  .hero{flex-direction:column;align-items:center;text-align:center;clip-path:none}
  .hero-stats{justify-content:center}
  .bio-display{border-left:none;border-top:2px solid var(--rc);padding:10px 0 0}}

/* ── CARD ── */
.card{
  background:var(--card-bg);border:1px solid var(--border);
  padding:1.5rem;position:relative;overflow:hidden;
  clip-path:polygon(12px 0%,100% 0%,calc(100% - 12px) 100%,0% 100%);
  transition:border-color .25s}
.card:hover{border-color:rgba(238,240,255,.13)}
.card::before{content:'';position:absolute;top:-1px;left:-1px;right:-1px;height:2px}
.c-pvp::before{background:linear-gradient(90deg,var(--rock),transparent)}
.c-ai::before{background:linear-gradient(90deg,var(--paper),transparent)}
.c-rank::before{background:linear-gradient(90deg,var(--rc),transparent)}
.c-choice::before{background:linear-gradient(90deg,var(--scissors),transparent)}
.c-pvp-fav::before{background:linear-gradient(90deg,var(--rock),var(--paper),transparent)}
.c-pvp-fav::after{content:'';position:absolute;bottom:0;left:0;right:0;height:50%;pointer-events:none;background:linear-gradient(to top,rgba(255,77,77,.04),transparent)}
.c-ai-fav::before{background:linear-gradient(90deg,var(--paper),var(--scissors),transparent)}
.c-ai-fav::after{content:'';position:absolute;bottom:0;left:0;right:0;height:50%;pointer-events:none;background:linear-gradient(to top,rgba(77,166,255,.04),transparent)}
.c-ach::before{background:linear-gradient(90deg,#ffd700,#ff8800,transparent)}
.c-hist::before{background:linear-gradient(90deg,var(--paper),var(--scissors),transparent)}
/* ambient inner glow */
.c-pvp::after{content:'';position:absolute;bottom:0;left:0;right:0;height:50%;pointer-events:none;background:linear-gradient(to top,rgba(255,77,77,.04),transparent)}
.c-ai::after{content:'';position:absolute;bottom:0;left:0;right:0;height:50%;pointer-events:none;background:linear-gradient(to top,rgba(77,166,255,.04),transparent)}
.c-rank::after{content:'';position:absolute;bottom:0;left:0;right:0;height:50%;pointer-events:none;background:linear-gradient(to top,var(--rg),transparent);opacity:.08}
.card-ttl{font-family:'Rajdhani',sans-serif;font-size:.65rem;font-weight:700;letter-spacing:.28em;
  text-transform:uppercase;color:var(--muted);margin-bottom:1.2rem;display:flex;align-items:center;gap:7px}

/* stat boxes */
.sbox-grid{display:grid;gap:9px}
.g3{grid-template-columns:repeat(3,1fr)}.g2{grid-template-columns:repeat(2,1fr)}
.sbox{background:rgba(238,240,255,.025);border:1px solid var(--border);
  padding:13px 10px;text-align:center;transition:all .22s;
  clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%)}
.sbox:hover{background:rgba(238,240,255,.06);border-color:rgba(238,240,255,.14)}
.sbox-val{font-family:'Bebas Neue',sans-serif;font-size:1.55rem;letter-spacing:.05em;line-height:1.1;margin-bottom:3px}
.sbox-lbl{font-size:.57rem;letter-spacing:.22em;text-transform:uppercase;color:var(--muted);font-weight:700}

/* winrate bar */
.wr-bar{height:7px;overflow:hidden;background:rgba(238,240,255,.05);display:flex;margin:12px 0 8px}
.wr-w{background:linear-gradient(90deg,#3dcc6e,#7dff4d)}.wr-d{background:rgba(238,240,255,.18)}.wr-l{background:linear-gradient(90deg,#e24b4a,#ff7d7d)}
.wr-legend{display:flex;gap:14px;flex-wrap:wrap}
.wr-it{display:flex;align-items:center;gap:5px;font-size:.66rem;color:var(--muted);font-weight:700}
.wr-dot{width:7px;height:7px;border-radius:50%}

/* choice cards */
.choice-cards{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:10px}
.ch-card{
  background:rgba(238,240,255,.035);border:1px solid var(--border);
  clip-path:polygon(7px 0%,100% 0%,calc(100% - 7px) 100%,0% 100%);
  padding:14px 8px 12px;display:flex;flex-direction:column;align-items:center;gap:6px;
  transition:all .28s;position:relative;overflow:hidden;text-align:center}
.ch-card::before{content:'';position:absolute;top:-1px;left:-1px;right:-1px;height:2px}
.ch-card-r::before{background:linear-gradient(90deg,var(--rock),transparent)}
.ch-card-p::before{background:linear-gradient(90deg,var(--paper),transparent)}
.ch-card-s::before{background:linear-gradient(90deg,var(--scissors),transparent)}
.ch-card:hover{transform:translateY(-3px);border-color:rgba(238,240,255,.18);box-shadow:0 8px 24px rgba(0,0,0,.2)}
.ch-card-ico{font-size:26px;line-height:1}
.ch-card-name{font-family:'Rajdhani',sans-serif;font-size:.58rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:var(--muted)}
.ch-card-pct{font-family:'Bebas Neue',sans-serif;font-size:1.6rem;letter-spacing:.05em;line-height:1}
.ch-pct-r{color:var(--rock)} .ch-pct-p{color:var(--paper)} .ch-pct-s{color:var(--scissors)}
.ch-card-count{font-size:.6rem;color:var(--muted);letter-spacing:.07em}
/* mini arc ring using conic-gradient */
.ch-ring{width:48px;height:48px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin-bottom:2px;position:relative}
.ch-ring-r{background:conic-gradient(var(--rock) calc(var(--p)*1%),rgba(255,255,255,.06) 0)}
.ch-ring-p{background:conic-gradient(var(--paper) calc(var(--p)*1%),rgba(255,255,255,.06) 0)}
.ch-ring-s{background:conic-gradient(var(--scissors) calc(var(--p)*1%),rgba(255,255,255,.06) 0)}
.ch-ring::after{content:'';position:absolute;inset:6px;background:var(--bg);border-radius:50%}
.ch-ring-ico{position:relative;z-index:1;font-size:20px}

/* ── 3 KARTU FAVORIT PVP ── */
.fav-cards-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:10px}
@media(max-width:540px){.fav-cards-grid{grid-template-columns:1fr}}
.fav-card{position:relative;padding:20px 12px 16px;text-align:center;border:1px solid var(--rarity-border);background:var(--rarity-grad);overflow:hidden;cursor:default;clip-path:polygon(12px 0%,100% 0%,calc(100% - 12px) 100%,0% 100%);transition:transform .32s cubic-bezier(.34,1.56,.64,1),box-shadow .28s ease}
.fav-card:hover{transform:translateY(-2px);box-shadow:0 12px 30px rgba(0,0,0,.3),0 0 15px var(--rarity-glow)}
.fav-card .shine{position:absolute;top:0;left:-100%;width:55%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.07),transparent);transform:skewX(-15deg);transition:left .6s ease;pointer-events:none}
.fav-card:hover .shine{left:160%}
.fav-corner{position:absolute;width:12px;height:12px;opacity:0;transition:opacity .3s;color:var(--rarity-color)}
.fav-corner::before,.fav-corner::after{content:'';position:absolute;background:currentColor}
.fav-corner::before{width:1.5px;height:9px}.fav-corner::after{width:9px;height:1.5px}
.fav-tl{top:6px;left:6px}.fav-br{bottom:6px;right:6px;transform:scale(-1)}
.fav-card:hover .fav-corner{opacity:.9}
.fav-rank-badge{position:absolute;top:-1px;left:50%;transform:translateX(-50%);font-family:'Russo One',sans-serif;font-size:.45rem;letter-spacing:.14em;padding:3px 9px;white-space:nowrap;clip-path:polygon(4px 0%,100% 0%,calc(100% - 4px) 100%,0% 100%);background:var(--rarity-badge-bg);border:1px solid var(--rarity-border);color:var(--rarity-color)}
.fav-icon{font-size:2rem;display:block;margin:10px 0 4px;filter:drop-shadow(0 2px 10px rgba(0,0,0,.6));transition:transform .3s}
.fav-card:hover .fav-icon{transform:scale(1.02)}
.fav-rarity{font-family:'Russo One',sans-serif;font-size:.48rem;letter-spacing:.16em;text-transform:uppercase;margin-bottom:4px;color:var(--rarity-color)}
.fav-name{font-family:'Rajdhani',sans-serif;font-size:.78rem;font-weight:700;letter-spacing:.04em;margin-bottom:5px;line-height:1.2}
.fav-desc{font-family:'Rajdhani',sans-serif;font-size:.56rem;color:var(--muted);line-height:1.4;letter-spacing:.03em;font-weight:600;margin-bottom:8px}
.fav-uses-bar-wrap{height:4px;background:rgba(238,240,255,.06);border-radius:100px;overflow:hidden;margin-bottom:4px}
.fav-uses-bar{height:100%;border-radius:100px;background:var(--rarity-color);box-shadow:0 0 8px var(--rarity-glow)}
.fav-uses-count{font-family:'Bebas Neue',sans-serif;font-size:1rem;letter-spacing:.06em;line-height:1;color:var(--rarity-color);text-shadow:0 0 14px var(--rarity-glow)}
.fav-uses-lbl{font-family:'Rajdhani',sans-serif;font-size:.48rem;color:var(--muted);font-weight:700;letter-spacing:.14em;text-transform:uppercase}

.rarity-common{
  --rarity-color:var(--rarity-common-color);
  --rarity-glow:var(--rarity-common-glow);
  --rarity-border:var(--rarity-common-border);
  --rarity-grad:var(--rarity-common-grad);
  --rarity-badge-bg:rgba(192,192,192,.12);
}
.rarity-rare{
  --rarity-color:var(--rarity-rare-color);
  --rarity-glow:var(--rarity-rare-glow);
  --rarity-border:var(--rarity-rare-border);
  --rarity-grad:var(--rarity-rare-grad);
  --rarity-badge-bg:rgba(77,166,255,.18);
}
.rarity-epic{
  --rarity-color:var(--rarity-epic-color);
  --rarity-glow:var(--rarity-epic-glow);
  --rarity-border:var(--rarity-epic-border);
  --rarity-grad:var(--rarity-epic-grad);
  --rarity-badge-bg:rgba(192,132,252,.18);
}
.rarity-legend{
  --rarity-color:var(--rarity-legend-color);
  --rarity-glow:var(--rarity-legend-glow);
  --rarity-border:var(--rarity-legend-border);
  --rarity-grad:var(--rarity-legend-grad);
  --rarity-badge-bg:rgba(255,215,0,.18);
}
.fav-card-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:7px;padding:28px 10px;background:rgba(238,240,255,.015);border:1px dashed rgba(238,240,255,.1);clip-path:polygon(12px 0%,100% 0%,calc(100% - 12px) 100%,0% 100%)}
.fav-empty-icon{font-size:1.8rem;opacity:.22}
.fav-empty-text{font-family:'Rajdhani',sans-serif;font-size:.62rem;color:var(--muted);font-weight:600;letter-spacing:.08em;text-align:center}
/* shimmer on rating bar */
.rating-bar-fill::after{content:'';position:absolute;inset:0;
  background:linear-gradient(90deg,transparent 0%,rgba(255,255,255,.2) 50%,transparent 100%);
  animation:shimmer 2.5s ease-in-out infinite;background-size:200% 100%}
@keyframes shimmer{from{background-position:-200% 0}to{background-position:200% 0}}

/* ── AVATAR MISSION SECTION ── */
.c-avmission::before{background:linear-gradient(90deg,#a855f7,#4da6ff,transparent)}
.c-avmission::after{content:'';position:absolute;bottom:0;left:0;right:0;height:50%;pointer-events:none;background:linear-gradient(to top,rgba(168,85,247,.04),transparent)}

.avm-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.2rem;flex-wrap:wrap;gap:8px}
.avm-counter{display:flex;align-items:center;gap:8px}
.avm-count-pill{font-family:'Bebas Neue',sans-serif;font-size:1rem;letter-spacing:.06em;
  background:rgba(168,85,247,.12);border:1px solid rgba(168,85,247,.3);
  padding:3px 12px;color:#c084fc;
  clip-path:polygon(5px 0%,100% 0%,calc(100% - 5px) 100%,0% 100%)}
.avm-refresh-btn{font-family:'Rajdhani',sans-serif;font-size:.6rem;font-weight:700;letter-spacing:.14em;
  text-transform:uppercase;background:rgba(238,240,255,.04);border:1px solid var(--border);
  color:var(--muted);padding:5px 12px;cursor:pointer;transition:all .2s;
  clip-path:polygon(4px 0%,100% 0%,calc(100% - 4px) 100%,0% 100%)}
.avm-refresh-btn:hover{background:rgba(168,85,247,.1);border-color:rgba(168,85,247,.3);color:#c084fc}
.avm-refresh-btn.spinning{animation:spin .7s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}

.avm-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px}
@media(max-width:540px){.avm-grid{grid-template-columns:1fr 1fr}}

.avm-card{
  background:rgba(238,240,255,.025);border:1px solid var(--border);
  padding:14px 13px 12px;position:relative;overflow:hidden;
  clip-path:polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%);
  transition:all .28s;display:flex;flex-direction:column;gap:8px}
.avm-card.unlocked{
  background:rgba(168,85,247,.07);border-color:rgba(168,85,247,.3);
  box-shadow:0 0 20px rgba(168,85,247,.06)}
.avm-card.unlocked:hover{background:rgba(168,85,247,.12);border-color:rgba(168,85,247,.48);
  transform:translateY(-3px);box-shadow:0 8px 28px rgba(168,85,247,.14)}
.avm-card.locked:hover{background:rgba(238,240,255,.05);border-color:rgba(238,240,255,.1);transform:translateY(-2px)}
.avm-card::before{content:'';position:absolute;top:-1px;left:-1px;right:-1px;height:1.5px;
  transition:opacity .3s}
.avm-card.unlocked::before{background:linear-gradient(90deg,#a855f7,#4da6ff,transparent);opacity:1}
.avm-card.locked::before{background:rgba(238,240,255,.08);opacity:1}

/* top row: avatar emoji + status badge */
.avm-top{display:flex;align-items:center;justify-content:space-between}
.avm-emoji{font-size:2rem;line-height:1;transition:transform .3s;
  filter:drop-shadow(0 2px 6px rgba(0,0,0,.5))}
.avm-card.locked .avm-emoji{filter:grayscale(1) opacity(.4)}
.avm-card.unlocked:hover .avm-emoji{transform:scale(1.02)}
.avm-status{font-family:'Russo One',sans-serif;font-size:.48rem;letter-spacing:.16em;
  text-transform:uppercase;padding:3px 9px;
  clip-path:polygon(4px 0%,100% 0%,calc(100% - 4px) 100%,0% 100%)}
.avm-status.s-done{background:rgba(168,85,247,.15);border:1px solid rgba(168,85,247,.4);color:#c084fc}
.avm-status.s-lock{background:rgba(238,240,255,.04);border:1px solid rgba(238,240,255,.1);color:var(--muted)}
.avm-status.s-new{background:rgba(125,255,77,.15);border:1px solid rgba(125,255,77,.45);color:#7dff4d;
  animation:newpulse 1.6s ease-in-out infinite alternate}
@keyframes newpulse{from{box-shadow:none}to{box-shadow:0 0 12px rgba(125,255,77,.3)}}

/* mission label */
.avm-label{font-family:'Russo One',sans-serif;font-size:.56rem;letter-spacing:.1em;
  text-transform:uppercase;color:var(--muted);line-height:1.3}
.avm-card.unlocked .avm-label{color:rgba(192,132,252,.85)}

/* mission description */
.avm-desc{font-family:'Rajdhani',sans-serif;font-size:.7rem;font-weight:600;
  color:var(--muted);line-height:1.5;letter-spacing:.03em}

/* progress */
.avm-prog-wrap{margin-top:2px}
.avm-prog-row{display:flex;justify-content:space-between;align-items:center;
  margin-bottom:5px;font-size:.6rem;font-family:'Rajdhani',sans-serif;font-weight:700}
.avm-prog-label{color:var(--muted);letter-spacing:.08em}
.avm-prog-val{color:var(--text);letter-spacing:.05em}
.avm-prog-track{height:4px;background:rgba(238,240,255,.06);overflow:hidden;
  clip-path:polygon(2px 0%,100% 0%,calc(100% - 2px) 100%,0% 100%)}
.avm-prog-fill{height:100%;transition:width 1.2s cubic-bezier(.4,0,.2,1);position:relative}
.avm-prog-fill.done{background:linear-gradient(90deg,#a855f7,#c084fc)}
.avm-prog-fill.partial{background:linear-gradient(90deg,#4da6ff,#7dff4d)}
.avm-prog-fill::after{content:'';position:absolute;inset:0;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,.25),transparent);
  animation:shimmer 2s ease-in-out infinite;background-size:200% 100%}

/* unlock animation overlay */
@keyframes unlockPop{
  0%{transform:scale(.7);opacity:0}
  60%{transform:scale(1.12)}
  100%{transform:scale(1);opacity:1}}
.avm-card.just-unlocked{animation:unlockPop .55s cubic-bezier(.34,1.56,.64,1) both}

/* achievements */
.ach-hd{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.1rem}
.ach-count{font-size:.7rem;color:var(--muted);font-weight:700}
.ach-count span{font-family:'Bebas Neue',sans-serif;color:#ffd700;font-size:1.1rem;letter-spacing:.05em}
.ach-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:9px}
.ach-item{
  background:rgba(238,240,255,.025);border:1px solid var(--border);
  padding:14px 10px;display:flex;flex-direction:column;align-items:center;gap:7px;text-align:center;
  transition:all .28s;position:relative;overflow:hidden;
  clip-path:polygon(7px 0%,100% 0%,calc(100% - 7px) 100%,0% 100%)}
.ach-item.ok{background:rgba(255,200,0,.06);border-color:rgba(255,200,0,.25);box-shadow:0 0 22px rgba(255,200,0,.06)}
.ach-item.ok:hover{background:rgba(255,200,0,.11);border-color:rgba(255,200,0,.45);transform:translateY(-3px);box-shadow:0 8px 32px rgba(255,200,0,.12)}
.ach-item.locked{opacity:.3;filter:grayscale(.9)}
.ach-ico{font-size:28px;line-height:1}
.ach-name{font-family:'Russo One',sans-serif;font-size:.53rem;letter-spacing:.07em;text-transform:uppercase}
.ach-desc{font-size:.6rem;color:var(--muted);line-height:1.35}
.ach-lock{position:absolute;top:7px;right:7px;font-size:10px;opacity:.3}

/* rank pos */
.rank-pos-box{display:flex;align-items:center;gap:14px;
  background:rgba(238,240,255,.025);border:1px solid var(--border);
  padding:14px 16px;margin-top:10px;
  clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%)}
.rank-pos-num{font-family:'Bebas Neue',sans-serif;font-size:2.2rem;letter-spacing:.06em;
  color:var(--rc);text-shadow:0 0 24px var(--rg);min-width:48px;text-align:center;line-height:1}
.rank-pos-lbl{font-size:.57rem;letter-spacing:.22em;text-transform:uppercase;color:var(--muted);font-weight:700}
.rank-pos-desc{font-size:.78rem;color:var(--text);margin-top:2px;font-weight:600}

/* streak badge */
.streak-badge{display:inline-flex;align-items:center;gap:6px;margin-top:13px;
  background:rgba(255,100,0,.1);border:1px solid rgba(255,100,0,.3);padding:5px 15px;
  font-family:'Russo One',sans-serif;font-size:.6rem;color:#ff9060;
  clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%);
  animation:sflash 2s ease-in-out infinite alternate}
@keyframes sflash{from{box-shadow:none}to{box-shadow:0 0 16px rgba(255,100,0,.3)}}

/* match history */
.hist-list{display:flex;flex-direction:column;gap:7px}
.hist-item{display:flex;align-items:center;gap:11px;
  background:rgba(238,240,255,.025);border:1px solid var(--border);
  padding:10px 14px;transition:all .2s;
  clip-path:polygon(7px 0%,100% 0%,calc(100% - 7px) 100%,0% 100%)}
.hist-item:hover{background:rgba(238,240,255,.06);border-color:rgba(238,240,255,.12)}
.hist-badge{font-family:'Russo One',sans-serif;font-size:.58rem;letter-spacing:.1em;
  padding:5px 10px;min-width:52px;text-align:center;
  clip-path:polygon(4px 0%,100% 0%,calc(100% - 4px) 100%,0% 100%)}
.b-won{background:rgba(125,239,160,.1);color:#7dff4d;border:1px solid rgba(125,239,160,.3)}
.b-lost{background:rgba(255,125,125,.1);color:#ff7d7d;border:1px solid rgba(255,125,125,.3)}
.b-draw{background:rgba(238,240,255,.06);color:var(--muted);border:1px solid var(--border)}
.hist-vs{flex:1}
.hist-opp{font-size:.78rem;font-weight:700}
.hist-dt{font-size:.62rem;color:var(--muted);margin-top:1px}
.hist-delta{font-family:'Bebas Neue',sans-serif;font-size:.92rem;letter-spacing:.05em}
.hist-score{font-family:'Bebas Neue',sans-serif;font-size:1rem;letter-spacing:.06em;color:var(--text);min-width:32px;text-align:center}
.d-up{color:#7dff4d}.d-dn{color:#ff7d7d}.d-null{color:var(--muted)}

/* ── MODAL ── */
.overlay{position:fixed;inset:0;z-index:200;background:rgba(5,6,13,.92);
  backdrop-filter:blur(18px);display:flex;align-items:center;justify-content:center;
  opacity:0;pointer-events:none;transition:opacity .3s ease}
.overlay.open{opacity:1;pointer-events:all}
.mbox{
  background:linear-gradient(155deg,rgba(13,15,30,.99),rgba(5,6,13,1));
  border:1px solid rgba(238,240,255,.1);padding:2.2rem 2.4rem 2.6rem;
  width:min(510px,94vw);max-height:92vh;overflow-y:auto;position:relative;
  box-shadow:0 40px 110px rgba(0,0,0,.85),0 0 80px rgba(77,166,255,.05);
  transform:translateY(30px) scale(.96);transition:transform .38s cubic-bezier(.34,1.56,.64,1);
  clip-path:polygon(18px 0%,100% 0%,calc(100% - 18px) 100%,0% 100%)}
.overlay.open .mbox{transform:translateY(0) scale(1)}
.mbox::before{content:'';position:absolute;top:-1px;left:-1px;right:-1px;height:2px;
  background:linear-gradient(90deg,var(--rock),var(--paper),var(--scissors),var(--paper),var(--rock));
  background-size:200% 100%;animation:rbow 4s linear infinite}
.m-close{position:absolute;top:14px;right:14px;background:rgba(238,240,255,.05);
  border:1px solid var(--border);width:32px;height:32px;color:var(--muted);
  font-size:15px;cursor:pointer;display:flex;align-items:center;justify-content:center;
  transition:all .2s;clip-path:polygon(4px 0%,100% 0%,calc(100% - 4px) 100%,0% 100%)}
.m-close:hover{background:rgba(255,77,77,.14);color:var(--text)}
.m-title{font-family:'Bebas Neue',sans-serif;font-size:1.4rem;letter-spacing:.12em;margin-bottom:1.5rem}
.m-label{font-size:9px;letter-spacing:.3em;text-transform:uppercase;color:var(--muted);margin-bottom:7px;display:block;font-weight:700}
.m-input{width:100%;background:rgba(238,240,255,.04);border:1px solid rgba(238,240,255,.1);
  padding:12px 15px;font-family:'Rajdhani',sans-serif;font-size:.92rem;font-weight:600;
  color:var(--text);outline:none;transition:border-color .2s,background .2s,box-shadow .2s;
  letter-spacing:.05em;clip-path:polygon(5px 0%,100% 0%,calc(100% - 5px) 100%,0% 100%)}
.m-input:focus{border-color:rgba(77,166,255,.55);background:rgba(77,166,255,.06);box-shadow:0 0 0 3px rgba(77,166,255,.07)}
.m-textarea{resize:none;height:72px}
.char-count{font-size:.6rem;color:var(--muted);text-align:right;margin-top:3px;margin-bottom:.9rem}
.m-error{background:rgba(255,77,77,.1);border:1px solid rgba(255,77,77,.3);
  padding:10px 13px;font-size:.76rem;color:#ff9090;margin-bottom:.9rem;display:none;
  clip-path:polygon(5px 0%,100% 0%,calc(100% - 5px) 100%,0% 100%)}
.m-error.show{display:block}
.m-info{background:rgba(77,166,255,.07);border:1px solid rgba(77,166,255,.2);
  padding:10px 13px;font-size:.73rem;color:rgba(144,196,255,.88);margin-bottom:.9rem;line-height:1.5;
  clip-path:polygon(5px 0%,100% 0%,calc(100% - 5px) 100%,0% 100%)}

/* modal tabs */
.modal-tabs{display:flex;gap:5px;margin-bottom:1.5rem}
.tab-btn{flex:1;justify-content:center;font-family:'Rajdhani',sans-serif;font-size:.7rem;font-weight:700;
  letter-spacing:.14em;text-transform:uppercase;padding:10px;border:1px solid var(--border);
  background:rgba(238,240,255,.04);color:var(--muted);cursor:pointer;transition:all .2s;
  clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%)}
.tab-btn:hover{background:rgba(238,240,255,.09);color:var(--text)}
.active-tab{background:rgba(77,166,255,.14)!important;color:var(--text)!important;border-color:rgba(77,166,255,.35)!important}

/* btn save */
.btn-save{width:100%;margin-top:.9rem;font-family:'Russo One',sans-serif;font-size:.78rem;
  letter-spacing:.2em;text-transform:uppercase;color:var(--dark);
  background:var(--text);border:none;padding:14px;cursor:pointer;
  transition:all .25s;display:flex;align-items:center;justify-content:center;gap:7px;
  clip-path:polygon(12px 0%,100% 0%,calc(100% - 12px) 100%,0% 100%)}
.btn-save:hover{background:linear-gradient(135deg,var(--paper),var(--scissors));transform:translateY(-2px);box-shadow:0 8px 30px rgba(77,166,255,.25)}
.btn-save:disabled{opacity:.45;pointer-events:none}

/* avatar grid */
.av-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:9px;margin-bottom:1.5rem}
.av-opt{aspect-ratio:1;background:rgba(238,240,255,.04);border:2px solid transparent;
  display:flex;align-items:center;justify-content:center;font-size:28px;cursor:pointer;
  transition:all .2s;clip-path:polygon(5px 0%,100% 0%,calc(100% - 5px) 100%,0% 100%)}
.av-opt:hover{background:rgba(238,240,255,.09);border-color:rgba(238,240,255,.22);transform:scale(1.01)}
.av-opt.sel{border-color:var(--paper);background:rgba(77,166,255,.12);box-shadow:0 0 18px rgba(77,166,255,.28)}
/* locked avatar */
.av-opt.locked{cursor:not-allowed;opacity:.42;position:relative;filter:grayscale(1)}
.av-opt.locked:hover{transform:none;background:rgba(238,240,255,.04);border-color:transparent}
.av-opt.locked .av-lock-ico{position:absolute;top:3px;right:4px;font-size:10px;line-height:1}
/* tooltip misi */
.av-mission-tip{
  display:none;position:absolute;bottom:calc(100% + 8px);left:50%;transform:translateX(-50%);
  background:rgba(5,6,13,.98);border:1px solid rgba(77,166,255,.35);
  padding:8px 11px;font-size:.65rem;font-family:'Rajdhani',sans-serif;font-weight:600;
  color:var(--text);white-space:nowrap;z-index:200;letter-spacing:.05em;line-height:1.5;
  box-shadow:0 8px 28px rgba(0,0,0,.7);pointer-events:none;
  clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%)}
.av-opt:hover .av-mission-tip{display:block}
.av-mission-tip .tip-label{color:rgba(77,166,255,.9);display:block;margin-bottom:2px;font-size:.6rem;text-transform:uppercase;letter-spacing:.15em}
/* progress bar di tooltip */
.tip-bar-wrap{margin-top:5px;height:3px;background:rgba(238,240,255,.1);overflow:hidden}
.tip-bar-fill{height:100%;background:linear-gradient(90deg,var(--paper),var(--scissors));transition:width .3s}

/* toast */
.toast{position:fixed;bottom:30px;left:50%;transform:translateX(-50%) translateY(22px);z-index:300;
  background:rgba(5,6,13,.98);border:1px solid rgba(238,240,255,.1);padding:12px 24px;
  font-family:'Rajdhani',sans-serif;font-size:.84rem;font-weight:700;color:var(--text);
  letter-spacing:.07em;backdrop-filter:blur(16px);box-shadow:0 8px 36px rgba(0,0,0,.7);
  opacity:0;pointer-events:none;transition:opacity .3s,transform .3s;
  clip-path:polygon(12px 0%,100% 0%,calc(100% - 12px) 100%,0% 100%)}
.toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
.toast.err{border-color:rgba(255,77,77,.38);color:#ff9090}

::-webkit-scrollbar{width:4px}
::-webkit-scrollbar-thumb{background:rgba(238,240,255,.1);}
::-webkit-scrollbar-thumb:hover{background:rgba(238,240,255,.2)}

/* ══════════════════════════════════════════════════════════
   LIGHT MODE
══════════════════════════════════════════════════════════ */
.btn-theme-toggle{
  width:auto;height:34px;border-radius:0;
  border:1px solid rgba(77,166,255,.2);
  background:transparent;color:rgba(77,166,255,.85);
  clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%);
  cursor:pointer;display:flex;align-items:center;justify-content:center;
  font-family:'Rajdhani',sans-serif;font-weight:700;font-size:.7rem;
  letter-spacing:.12em;text-transform:uppercase;padding:0 14px;
  transition:all .2s;flex-shrink:0;
}
.btn-theme-toggle:hover{
  background:rgba(77,166,255,.18);
  border-color:rgba(77,166,255,.45);
  color:#4da6ff;
}
[data-theme="light"]{
  --dark:#f0f4fc;--mid:#e4e8f4;--card:rgba(255,255,255,.75);
  --text:#1a1d2e;--muted:rgba(26,29,46,.5);--border:rgba(0,0,0,.09);
  --rock:#d93030;--paper:#2874c2;--scissors:#1a9940;
  --gr:rgba(217,48,48,.45);--gp:rgba(40,116,194,.45);--gs:rgba(26,153,64,.45);
  --card-bg:rgba(255,255,255,.88);

  /* Tier overrides (Light Mode) */
  --color-grandmaster: #b45309;
  --color-master: #7c3aed;
  --color-diamond: #1060b0;
  --color-platinum: #047857;
  --color-gold: #92400e;
  --color-silver: #4b5563;
  --color-bronze: #78350f;

  --glow-grandmaster: rgba(180,83,9,.3);
  --glow-master: rgba(124,58,237,.3);
  --glow-diamond: rgba(16,96,176,.3);
  --glow-platinum: rgba(4,120,87,.3);
  --glow-gold: rgba(146,64,14,.3);
  --glow-silver: rgba(75,85,99,.3);
  --glow-bronze: rgba(120,53,15,.3);

  /* Rarity overrides (Light Mode) */
  --rarity-common-color: #4b5563;
  --rarity-common-glow: rgba(75,85,99,0.18);
  --rarity-common-border: rgba(75,85,99,0.3);
  --rarity-common-grad: linear-gradient(135deg, rgba(75, 85, 99, 0.08), rgba(75, 85, 99, 0.02));

  --rarity-rare-color: #1060b0;
  --rarity-rare-glow: rgba(16, 96, 176, 0.22);
  --rarity-rare-border: rgba(16, 96, 176, 0.35);
  --rarity-rare-grad: linear-gradient(135deg, rgba(16, 96, 176, 0.08), rgba(16, 96, 176, 0.02));

  --rarity-epic-color: #7c3aed;
  --rarity-epic-glow: rgba(124, 58, 237, 0.22);
  --rarity-epic-border: rgba(124, 58, 237, 0.35);
  --rarity-epic-grad: linear-gradient(135deg, rgba(124, 58, 237, 0.08), rgba(124, 58, 237, 0.02));

  --rarity-legend-color: #b45309;
  --rarity-legend-glow: rgba(180, 83, 9, 0.25);
  --rarity-legend-border: rgba(180, 83, 9, 0.35);
  --rarity-legend-grad: linear-gradient(135deg, rgba(180, 83, 9, 0.08), rgba(180, 83, 9, 0.02));
}
[data-theme="light"] body{background:#f0f4fc;color:var(--text);}
[data-theme="light"] canvas#bg{opacity:.12;}
[data-theme="light"] .hex-layer{opacity:.015;filter:invert(1);}
[data-theme="light"] .noise{opacity:.012;}
[data-theme="light"] .elines{opacity:.22;}
[data-theme="light"] .scanline{opacity:.025;}
[data-theme="light"] .vignette{background:radial-gradient(ellipse at center,transparent 50%,rgba(0,0,0,.06) 100%);}
[data-theme="light"] .corner::before,[data-theme="light"] .corner::after{background:rgba(40,116,194,.25);}
[data-theme="light"] .nav-btn:not(.danger){background:transparent;border-color:rgba(40,116,194,.18);color:rgba(40,116,194,.8);}
[data-theme="light"] .nav-btn:not(.danger):hover{background:rgba(40,116,194,.08);border-color:rgba(40,116,194,.35);color:#2874c2;}
[data-theme="light"] .nav-btn.danger{background:transparent;border-color:rgba(217,48,48,.18);color:rgba(217,48,48,.65);}
[data-theme="light"] .nav-btn.danger:hover{border-color:rgba(217,48,48,.4);color:#d93030;background:rgba(217,48,48,.06);}
[data-theme="light"] .hero,.card,.sbox,.ach-item,.hist-item{background:var(--card-bg);border-color:rgba(0,0,0,.07);}
[data-theme="light"] .sbox:hover,.ach-item.ok:hover{background:rgba(240,244,252,.95);}
[data-theme="light"] .card-ttl,.sbox-lbl,.hstat-lbl{color:rgba(26,29,46,.4);}
[data-theme="light"] .avm-card{background:rgba(255,255,255,.7);border-color:rgba(0,0,0,.07);}
[data-theme="light"] .avm-card.unlocked{background:rgba(168,85,247,.07);border-color:rgba(168,85,247,.25);}
[data-theme="light"] .fav-card-empty{background:rgba(240,244,252,.6);border-color:rgba(0,0,0,.07);}
[data-theme="light"] .overlay{background:rgba(240,244,252,.92);}
[data-theme="light"] .mbox{background:linear-gradient(155deg,rgba(245,247,255,.99),rgba(240,244,252,1));border-color:rgba(40,116,194,.1);}
[data-theme="light"] .m-input{background:rgba(255,255,255,.8);border-color:rgba(0,0,0,.12);color:var(--text);}
[data-theme="light"] .m-input:focus{border-color:rgba(40,116,194,.5);background:#fff;box-shadow:0 0 0 3px rgba(40,116,194,.07);}
[data-theme="light"] .tab-btn{background:rgba(0,0,0,.03);border-color:rgba(0,0,0,.08);color:var(--muted);}
[data-theme="light"] .tab-btn:hover{background:rgba(40,116,194,.06);color:var(--text);}
[data-theme="light"] .active-tab{background:rgba(40,116,194,.12)!important;color:var(--text)!important;border-color:rgba(40,116,194,.3)!important;}
[data-theme="light"] .toast{background:rgba(240,244,252,.98);border-color:rgba(40,116,194,.15);color:var(--text);}
[data-theme="light"] .btn-theme-toggle{background:transparent;border-color:rgba(40,116,194,.18);color:rgba(40,116,194,.8);}
[data-theme="light"] .btn-theme-toggle:hover{background:rgba(40,116,194,.08);border-color:rgba(40,116,194,.35);color:#2874c2;}
/* ── FIX KRITIS: gradient text → gelap di light mode ── */
[data-theme="light"] .nav-brand{
  background:linear-gradient(90deg,#c0200f,#1060b0,#0f7a30);
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
}
[data-theme="light"] .hero-title{color:#1a1d2e;text-shadow:none;}
[data-theme="light"] .hero-sub{color:rgba(26,29,46,.45);}
[data-theme="light"] .pid{color:rgba(26,29,46,.4);}
[data-theme="light"] .sbox-lbl{color:rgba(26,29,46,.4);}
[data-theme="light"] .sbox-val{text-shadow:none;}
[data-theme="light"] .card-ttl{color:rgba(26,29,46,.38);}
[data-theme="light"] .hstat-lbl{color:rgba(26,29,46,.45);}
[data-theme="light"] .hstat-val{color:#1a1d2e;}
[data-theme="light"] .ach-name{color:#1a1d2e;}
[data-theme="light"] .ach-desc{color:rgba(26,29,46,.5);}
[data-theme="light"] .hist-opp{color:#1a1d2e;}
[data-theme="light"] .hist-date{color:rgba(26,29,46,.4);}
[data-theme="light"] .m-label{color:rgba(26,29,46,.55);}

/* Extra overrides for text & element contrast in Light Mode */
[data-theme="light"] .cg{color:#b45309}
[data-theme="light"] .cw{color:#0f7a30}
[data-theme="light"] .cl{color:#c0200f}
[data-theme="light"] .co{color:#c2410c}
[data-theme="light"] .cb{color:#1060b0}
[data-theme="light"] .b-won{background:rgba(15,122,48,.08);color:#0f7a30;border-color:rgba(15,122,48,.25)}
[data-theme="light"] .b-lost{background:rgba(192,32,15,.08);color:#c0200f;border-color:rgba(192,32,15,.25)}
[data-theme="light"] .b-draw{background:rgba(0,0,0,.04);color:rgba(0,0,0,.5);border-color:rgba(0,0,0,.1)}
[data-theme="light"] .d-up{color:#0f7a30}
[data-theme="light"] .d-dn{color:#c0200f}
[data-theme="light"] .d-null{color:rgba(0,0,0,.4)}
[data-theme="light"] .streak-badge{background:rgba(234,88,12,.08);border:1px solid rgba(234,88,12,.28);color:#c2410c;box-shadow:none;animation:none}
[data-theme="light"] .rating-bar-track{background:rgba(0,0,0,.08)}
[data-theme="light"] .rating-bar-fill{background:var(--rc);box-shadow:none}
[data-theme="light"] .avm-prog-track{background:rgba(0,0,0,.08)}
[data-theme="light"] .avm-prog-fill.done{background:linear-gradient(90deg,#7c3aed,#a78bfa)}
[data-theme="light"] .avm-prog-fill.partial{background:linear-gradient(90deg,#1060b0,#047857)}
[data-theme="light"] .fav-card:hover{box-shadow:0 12px 30px rgba(0,0,0,0.12),0 0 8px var(--rarity-glow)}
[data-theme="light"] .rank-badge{background:rgba(255,255,255,0.9);box-shadow:0 4px 14px var(--rg);border-color:var(--rc)}
[data-theme="light"] .avatar-ring{box-shadow:0 0 30px var(--rg), 0 0 0 5px #f0f4fc, inset 0 0 15px rgba(0,0,0,0.08);animation:rpulse-light 3.5s ease-in-out infinite alternate}

@keyframes rpulse-light{from{box-shadow:0 0 20px var(--rg),0 0 0 5px #f0f4fc}to{box-shadow:0 0 40px var(--rg),0 0 0 5px #f0f4fc}}

body,.hero,.card,.sbox,.avm-card,.mbox,.m-input,.tab-btn,.nav-btn,.toast{
  transition:background .4s ease,border-color .4s ease,color .4s ease;
}
</style>
<style>
/* ══ UNIVERSAL BUTTON STYLES (Default & Hover) ══ */
button:not(.btn-theme-toggle):not(.btn-setting):not(.m-close):not(.btn-close-modal):not(.lb2-close-btn):not(.btn-close):not(.ss-mute-btn):not(.xbtn-cancel):not(.exit-btn-cancel):not(.nav-btn.danger):not(.av-edit-btn):not(.edit-name-btn):not(.avm-refresh-btn):not(.toggle-pw):not(.btn-cancel-card):not(.btn-quit):not(.modal-tab),
.btn, .cta, .btn-submit, .btn-to-login,
.exit-btn-confirm, a.btn, .xbtn-battle, .lb2-act-btn, .btn-save, .chat-send-btn, .btn-continue, .btn-rematch, .btn-use-card, .btn-confirm-card {
  background: var(--text) !important;
  color: var(--dark) !important;
  border-color: var(--border) !important;
}
button:not(.btn-theme-toggle):not(.btn-setting):not(.m-close):not(.btn-close-modal):not(.lb2-close-btn):not(.btn-close):not(.ss-mute-btn):not(.xbtn-cancel):not(.exit-btn-cancel):not(.nav-btn.danger):not(.av-edit-btn):not(.edit-name-btn):not(.avm-refresh-btn):not(.toggle-pw):not(.btn-cancel-card):not(.btn-quit):not(.modal-tab):hover,
.btn:hover, .mbtn:hover, .cta:hover, .btn-submit:hover, .btn-to-login:hover,
.exit-btn-confirm:hover, a.btn:hover, .xbtn-battle:hover, .lb2-act-btn:hover, .btn-save:hover, .chat-send-btn:hover, .btn-continue:hover, .btn-rematch:hover, .btn-use-card:hover, .btn-confirm-card:hover {
  background: linear-gradient(135deg, #2874c2 0%, #1a9940 100%) !important;
  color: #fff !important;
  border-color: transparent !important;
  box-shadow: 0 4px 15px rgba(26,153,64,0.4) !important;
  transform: translateY(-2px) scale(1.02);
}
.cta::before, .btn-submit::after, .mbtn::before, .exit-btn::before,
.cta:hover::before, .btn-submit:hover::after, .mbtn:hover::before, .exit-btn:hover::before {
  display: none !important;
}
</style>
</head>
<body>

<canvas id="bg"></canvas>
<div class="hex-layer"></div>
<div class="noise"></div>
<div class="elines" id="EL"></div>
<div class="scanline"></div>
<div class="vignette"></div>
<div class="corner c-tl"></div><div class="corner c-tr"></div>
<div class="corner c-bl"></div><div class="corner c-br"></div>

<nav class="navbar">
  <div class="nav-right">
    <a class="nav-btn" id="btnBack" href="<?php echo $back_href?>">&#x2190;<?php echo $back_label?></a>
    <button class="btn-theme-toggle" id="btnThemeToggle" title="Ganti Tema"><span class="theme-icon">Light Mode</span></button>
    <?php if($is_me): ?>
    <button class="nav-btn danger" onclick="openEditModal()">&#x270F;&#xFE0F; Edit Profil</button>
    <?php endif; ?>
  </div>
</nav>

<div class="page">

<!-- HERO -->
<div class="hero">
  <div class="avatar-wrap">
    <div class="avatar-ring" id="avRing" <?php if($is_me): ?>onclick="openEditModal()" style="cursor:pointer"   <?php else: ?>style="cursor:default"<?php endif; ?>>
      <span id="avEmoji"><?php echo $avatar_emoji?></span>
    </div>
    <?php if($is_me): ?>
    <button class="av-edit-btn" onclick="openEditModal()">✏️ Edit Profil</button>
    <?php endif; ?>
  </div>

  <div class="hero-info">
    <div class="hero-dname">
      <span id="heroName"><?php echo $display_name?></span>
      <?php if($is_me): ?>
      <span class="edit-name-btn" onclick="openEditModal()">✏️</span>
      <?php endif; ?>
    </div>
    <div class="hero-username">@<?php echo htmlspecialchars($player['username'])?> · Bergabung <?php echo date('M Y',strtotime($player['created_at']??'now'))?></div>

    <div class="rank-badge">
      <span class="rank-icon"><?php echo $tier['icon']?></span>
      <span class="rank-name"><?php echo $tier['name']?></span>
      <span class="rank-pos">· #<?php echo $rank?> Global</span>
    </div>

    <?php if($bio):?>
      <div class="bio-display" id="bioDisplay"><?php echo $bio?></div>
    <?php else:?>
      <div class="bio-display bio-empty" id="bioDisplay" <?php if($is_me): ?>onclick="openEditModal()" style="cursor:pointer"<?php else: ?>style="cursor:default"<?php endif; ?>>
        <?php echo $is_me ? '「 Tambahkan bio kamu… 」' : '「 Player ini belum menambahkan bio 」'; ?>
      </div>
    <?php endif;?>

    <div class="hero-stats">
      <div class="hstat"><div class="hstat-val cg" id="cnt-rating">0</div><div class="hstat-lbl">Rating</div></div>
      <div class="hstat"><div class="hstat-val cw" id="cnt-wins">0</div><div class="hstat-lbl">Menang</div></div>
      <div class="hstat"><div class="hstat-val cl" id="cnt-losses">0</div><div class="hstat-lbl">Kalah</div></div>
      <div class="hstat"><div class="hstat-val co" id="cnt-streak">0</div><div class="hstat-lbl">Best Streak</div></div>
      <div class="hstat"><div class="hstat-val cb"><?php echo $unlocked_count?>/<?php echo $total_ach?></div><div class="hstat-lbl">Achievement</div></div>
    </div>

    <div class="rating-bar-wrap">
      <div class="rating-bar-hd">
        <span class="rating-bar-lbl"><?php echo $tier['name']?> · <?php echo $tier_pct?>% ke tier berikutnya</span>
        <span class="rating-bar-val"><?php echo number_format($rating)?> ERP</span>
      </div>
      <div class="rating-bar-track"><div class="rating-bar-fill" id="rFill"></div></div>
    </div>
  </div>
</div>

<!-- SECTIONS -->
<div class="grid">

  <!-- PvP -->
  <div class="card c-pvp">
    <div class="card-ttl">⚔️ Statistik PvP</div>
    <div class="sbox-grid g3">
      <div class="sbox"><div class="sbox-val cw"><?php echo $wins?></div><div class="sbox-lbl">Menang</div></div>
      <div class="sbox"><div class="sbox-val cl"><?php echo $losses?></div><div class="sbox-lbl">Kalah</div></div>
      <div class="sbox"><div class="sbox-val" style="color:var(--muted)"><?php echo $draws?></div><div class="sbox-lbl">Seri</div></div>
    </div>
    <div class="wr-bar">
      <?php if($total_pvp>0):?>
        <div class="wr-w" style="width:<?php echo round($wins/$total_pvp*100)?>%"></div>
        <div class="wr-d" style="width:<?php echo round($draws/$total_pvp*100)?>%"></div>
        <div class="wr-l" style="width:<?php echo round($losses/$total_pvp*100)?>%"></div>
      <?php endif;?>
    </div>
    <div class="wr-legend">
      <div class="wr-it"><div class="wr-dot" style="background:#7dff4d"></div><?php echo $winrate?>% Winrate</div>
      <div class="wr-it"><div class="wr-dot" style="background:rgba(238,240,255,.2)"></div><?php echo $total_pvp?> Match</div>
    </div>
    <?php if($cur_streak>=2):?><div class="streak-badge">🔥 Streak <?php echo $cur_streak?> Aktif</div><?php endif;?>
  </div>

  <!-- AI -->
  <div class="card c-ai">
    <div class="card-ttl">🤖 Statistik VS AI</div>
    <div class="sbox-grid g3">
      <div class="sbox"><div class="sbox-val cw"><?php echo $ai_wins?></div><div class="sbox-lbl">Menang</div></div>
      <div class="sbox"><div class="sbox-val cl"><?php echo $ai_losses?></div><div class="sbox-lbl">Kalah</div></div>
      <div class="sbox"><div class="sbox-val" style="color:var(--muted)"><?php echo $ai_draws?></div><div class="sbox-lbl">Seri</div></div>
    </div>
    <div class="wr-bar">
      <?php if($total_ai>0):?>
        <div class="wr-w" style="width:<?php echo round($ai_wins/$total_ai*100)?>%"></div>
        <div class="wr-d" style="width:<?php echo round($ai_draws/$total_ai*100)?>%"></div>
        <div class="wr-l" style="width:<?php echo round($ai_losses/$total_ai*100)?>%"></div>
      <?php endif;?>
    </div>
    <div class="wr-legend">
      <div class="wr-it"><div class="wr-dot" style="background:#7dff4d"></div><?php echo $ai_winrate?>% Winrate</div>
      <div class="wr-it"><div class="wr-dot" style="background:rgba(238,240,255,.2)"></div><?php echo $total_ai?> Match AI</div>
    </div>
  </div>

  <!-- 3 Kartu Favorit PvP (menggantikan Posisi Rank) -->
  <div class="card c-pvp-fav">
    <div class="card-ttl">🃏 3 Kartu Favorit PvP</div>
    <div class="fav-cards-grid">
      <?php
      $rank_labels=['#1 PALING SERING','#2 FAVORIT','#3 TERPILIH'];
      $max_uses=!empty($fav_cards)?($fav_cards[0]['uses']??1):1;
      for($fi=0;$fi<3;$fi++):
        if(!empty($fav_cards[$fi])):
          $fc=$fav_cards[$fi];$rm=$rarity_meta[$fc['rarity']]??$rarity_meta['common'];
          $bar_w=$max_uses>0?round($fc['uses']/$max_uses*100):0;
      ?>
      <div class="fav-card rarity-<?php echo $fc['rarity']?>">
        <div class="shine"></div>
        <div class="fav-corner fav-tl"></div>
        <div class="fav-corner fav-br"></div>
        <div class="fav-rank-badge"><?php echo $rank_labels[$fi]?></div>
        <span class="fav-icon"><?php echo $fc['icon']?></span>
        <div class="fav-rarity"><?php echo $rm['label']?></div>
        <div class="fav-name" style="color:var(--text)"><?php echo htmlspecialchars($fc['name'])?></div>
        <div class="fav-desc"><?php echo htmlspecialchars($fc['desc'])?></div>
        <div class="fav-uses-bar-wrap">
          <div class="fav-uses-bar" style="width:<?php echo $bar_w?>%"></div>
        </div>
        <div class="fav-uses-count"><?php echo $fc['uses']?></div>
        <div class="fav-uses-lbl">kali dipakai</div>
      </div>
      <?php else:?>
      <div class="fav-card-empty">
        <span class="fav-empty-icon">🃏</span>
        <span class="fav-empty-text">Belum ada data<br>kartu #<?php echo $fi+1?></span>
      </div>
      <?php endif; endfor;?>
    </div>
    <?php if(empty($fav_cards)):?>
    <div style="text-align:center;padding:8px 0 4px;font-family:'Rajdhani',sans-serif;color:var(--muted);font-size:.7rem;font-weight:600;letter-spacing:.09em">
      Mainkan pertandingan PvP untuk melihat kartu favorit kamu
    </div>
    <?php endif;?>

  </div>

  <!-- 3 Kartu Favorit VS AI (menggantikan posisi lama c-choice) -->
  <div class="card c-ai-fav">
    <div class="card-ttl">🤖 3 Kartu Favorit VS AI</div>
    <div class="fav-cards-grid">
      <?php
      $rank_labels_ai=['#1 PALING SERING','#2 FAVORIT','#3 TERPILIH'];
      $max_uses_ai=!empty($fav_cards_ai)?($fav_cards_ai[0]['uses']??1):1;
      for($ai=0;$ai<3;$ai++):
        if(!empty($fav_cards_ai[$ai])):
          $fca=$fav_cards_ai[$ai];$rma=$rarity_meta[$fca['rarity']]??$rarity_meta['common'];
          $bar_w_ai=$max_uses_ai>0?round($fca['uses']/$max_uses_ai*100):0;
      ?>
      <div class="fav-card rarity-<?php echo $fca['rarity']?>">
        <div class="shine"></div>
        <div class="fav-corner fav-tl"></div>
        <div class="fav-corner fav-br"></div>
        <div class="fav-rank-badge"><?php echo $rank_labels_ai[$ai]?></div>
        <span class="fav-icon"><?php echo $fca['icon']?></span>
        <div class="fav-rarity"><?php echo $rma['label']?></div>
        <div class="fav-name" style="color:var(--text)"><?php echo htmlspecialchars($fca['name'])?></div>
        <div class="fav-desc"><?php echo htmlspecialchars($fca['desc'])?></div>
        <div class="fav-uses-bar-wrap">
          <div class="fav-uses-bar" style="width:<?php echo $bar_w_ai?>%"></div>
        </div>
        <div class="fav-uses-count"><?php echo $fca['uses']?></div>
        <div class="fav-uses-lbl">kali dipakai</div>
      </div>
      <?php else:?>
      <div class="fav-card-empty">
        <span class="fav-empty-icon">🤖</span>
        <span class="fav-empty-text">Belum ada data<br>kartu AI #<?php echo $ai+1?></span>
      </div>
      <?php endif; endfor;?>
    </div>
    <?php if(empty($fav_cards_ai)):?>
    <div style="text-align:center;padding:8px 0 4px;font-family:'Rajdhani',sans-serif;color:var(--muted);font-size:.7rem;font-weight:600;letter-spacing:.09em">
      Mainkan pertandingan VS AI untuk melihat kartu favorit kamu
    </div>
    <?php endif;?>
  </div>

  <!-- Achievements -->
  <div class="card c-ach full">
    <div class="ach-hd">
      <div class="card-ttl" style="margin-bottom:0">🏆 Pencapaian</div>
      <div class="ach-count"><span><?php echo $unlocked_count?></span> / <?php echo $total_ach?> terbuka</div>
    </div>
    <div class="ach-grid">
      <?php foreach($achievements as $a):?>
        <div class="ach-item <?php echo $a['unlocked']?'ok':'locked'?>">
          <?php if(!$a['unlocked']):?><span class="ach-lock">🔒</span><?php endif;?>
          <div class="ach-ico"><?php echo $a['icon']?></div>
          <div class="ach-name"><?php echo htmlspecialchars($a['name'])?></div>
          <div class="ach-desc"><?php echo htmlspecialchars($a['desc'])?></div>
        </div>
      <?php endforeach;?>
    </div>
  </div>

  <!-- ════════════ AVATAR MISSION SECTION ════════════ -->
  <div class="card c-avmission full" id="avMissionCard">
    <div class="avm-header">
      <div class="card-ttl" style="margin-bottom:0">🎭 Misi Buka Avatar</div>
      <div class="avm-counter">
        <div class="avm-count-pill" id="avmCountPill"><?php echo count($all_unlocked_indices)?>/<?php echo count($AVATARS)?> Terbuka</div>
        <button class="avm-refresh-btn" id="avmRefreshBtn" onclick="refreshAvatarMissions()" title="Refresh status misi">↻ Refresh</button>
      </div>
    </div>
    <div class="avm-grid" id="avmGrid">
      <?php foreach($missions_for_js as $m): ?>
        <?php
          $is_default = ($m['index'] === 0);
          $prog_pct   = $m['max'] > 0 ? round($m['cur'] / $m['max'] * 100) : 100;
          $unlocked   = $m['unlocked'];
          $card_cls   = $unlocked ? 'unlocked' : 'locked';
          $status_cls = $unlocked ? 's-done' : 's-lock';
          $status_lbl = $unlocked ? ($is_default ? '✓ DEFAULT' : '✓ TERBUKA') : '🔒 TERKUNCI';
        ?>
        <div class="avm-card <?php echo $card_cls?>" id="avmCard<?php echo $m['index']?>" data-idx="<?php echo $m['index']?>">
          <div class="avm-top">
            <span class="avm-emoji"><?php echo $m['emoji']?></span>
            <span class="avm-status <?php echo $status_cls?>" id="avmStatus<?php echo $m['index']?>"><?php echo $status_lbl?></span>
          </div>
          <div class="avm-label"><?php echo htmlspecialchars($m['label'])?></div>
          <div class="avm-desc"><?php echo htmlspecialchars($m['desc'])?></div>
          <?php if(!$is_default): ?>
          <div class="avm-prog-wrap">
            <div class="avm-prog-row">
              <span class="avm-prog-label">Progress</span>
              <span class="avm-prog-val" id="avmProgVal<?php echo $m['index']?>"><?php echo $m['cur']?>/<?php echo $m['max']?></span>
            </div>
            <div class="avm-prog-track">
              <div class="avm-prog-fill <?php echo $unlocked?'done':'partial'?>"
                   id="avmProgFill<?php echo $m['index']?>"
                   style="width:<?php echo $prog_pct?>%"></div>
            </div>
          </div>
          <?php else: ?>
          <div style="font-size:.62rem;color:rgba(168,85,247,.7);font-family:'Rajdhani',sans-serif;font-weight:700;letter-spacing:.08em">
            Terbuka otomatis saat register
          </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- PvP History -->
  <div class="card c-hist">
    <div class="card-ttl">📜 Riwayat PvP</div>
    <?php if(empty($pvp_matches)):?>
      <div style="text-align:center;color:var(--muted);font-size:.8rem;padding:1.5rem 0">Belum ada pertandingan PvP</div>
    <?php else:?>
      <div class="hist-list">
        <?php foreach($pvp_matches as $m):?>
          <?php
            $res=$m['result']??'draw';
            $isP1=($m['player1_id']===$player_id);
            $opp=$isP1?($m['player2_name']??'—'):($m['player1_name']??'—');
            $rb=$isP1?($m['player1_rating_before']??null):($m['player2_rating_before']??null);
            $ra=$isP1?($m['player1_rating_after']??null):($m['player2_rating_after']??null);
            $delta=($rb!==null&&$ra!==null)?($ra-$rb):null;
            $bc=$res==='won'?'b-won':($res==='lost'?'b-lost':'b-draw');
            $bl=$res==='won'?'MENANG':($res==='lost'?'KALAH':'SERI');
            $myRW=$isP1?(int)($m['player1_round_wins']??0):(int)($m['player2_round_wins']??0);
            $opRW=$isP1?(int)($m['player2_round_wins']??0):(int)($m['player1_round_wins']??0);
          ?>
          <div class="hist-item">
            <div class="hist-badge <?php echo $bc?>"><?php echo $bl?></div>
            <div class="hist-vs">
              <div class="hist-opp">vs <?php echo htmlspecialchars($opp)?></div>
              <div class="hist-dt"><?php echo date('d M Y, H:i',strtotime($m['played_at']??'now'))?></div>
            </div>
            <div class="hist-score"><?php echo $myRW?>-<?php echo $opRW?></div>
            <?php if($delta!==null):?>
              <div class="hist-delta <?php echo $delta>0?'d-up':($delta<0?'d-dn':'d-null')?>"><?php echo $delta>0?'+':''?><?php echo $delta?></div>
            <?php else:?><div class="hist-delta d-null">—</div><?php endif;?>
          </div>
        <?php endforeach;?>
      </div>
    <?php endif;?>
  </div>

  <!-- AI History -->
  <div class="card c-ai">
    <div class="card-ttl">🤖 Riwayat VS AI</div>
    <?php if(empty($ai_matches)):?>
      <div style="text-align:center;color:var(--muted);font-size:.8rem;padding:1.5rem 0">Belum ada pertandingan VS AI</div>
    <?php else:?>
      <div class="hist-list">
        <?php foreach($ai_matches as $m):?>
          <?php
            $res=$m['result']??'draw';
            $bc=$res==='won'?'b-won':($res==='lost'?'b-lost':'b-draw');
            $bl=$res==='won'?'MENANG':($res==='lost'?'KALAH':'SERI');
            $myRW=(int)($m['player_round_wins']??0);
            $aiRW=(int)($m['ai_round_wins']??0);
          ?>
          <div class="hist-item">
            <div class="hist-badge <?php echo $bc?>"><?php echo $bl?></div>
            <div class="hist-vs">
              <div class="hist-opp">vs Computer AI</div>
              <div class="hist-dt"><?php echo date('d M Y, H:i',strtotime($m['played_at']??'now'))?></div>
            </div>
            <div class="hist-score"><?php echo $myRW?>-<?php echo $aiRW?></div>
            <div class="hist-delta d-null">AI</div>
          </div>
        <?php endforeach;?>
      </div>
    <?php endif;?>
  </div>

</div>
</div>

<!-- MODAL EDIT PROFIL -->
<div class="overlay" id="editOverlay">
  <div class="mbox">
    <button class="m-close" onclick="closeModal()">✕</button>
    <div class="m-title">✏️ Edit Profil</div>
    <div class="modal-tabs">
      <button class="tab-btn active-tab" id="tabAv" onclick="switchTab('av')">🎭 Avatar &amp; Bio</button>
      <button class="tab-btn" id="tabName" onclick="switchTab('name')">✏️ Ganti Nama</button>
    </div>
    <div id="panelAv">
      <div id="avErrMsg" class="m-error"></div>
      <label class="m-label">Pilih Avatar</label>
      <div style="font-size:.67rem;color:var(--muted);background:rgba(77,166,255,.06);border:1px solid rgba(77,166,255,.18);padding:8px 11px;margin-bottom:10px;letter-spacing:.04em;clip-path:polygon(5px 0%,100% 0%,calc(100% - 5px) 100%,0% 100%)">
        🔓 <strong style="color:rgba(77,166,255,.9)"><?php echo count($all_unlocked_indices)?>/<?php echo count($AVATARS)?></strong> avatar terbuka · Avatar 🔒 hover untuk lihat misi
      </div>
      <div class="av-grid" id="avGrid">
        <?php foreach($AVATARS as $i=>$ico):
          $is_unlocked=in_array($i,$all_unlocked_indices);
          $mission=$AVATAR_MISSIONS[$i];
          $prog=getAvatarMissionProgress($i,$player);
          $prog_pct=$prog['max']>0?round($prog['cur']/$prog['max']*100):100;
          $is_sel=($i===$avatar_choice);
        ?>
          <?php if($is_unlocked):?>
          <div class="av-opt <?php echo $is_sel?'sel':''?>" data-idx="<?php echo $i?>" onclick="pickAvatar(<?php echo $i?>,'<?php echo $ico?>')" title="<?php echo htmlspecialchars($mission['label'])?>">
            <?php echo $ico?>
          </div>
          <?php else:?>
          <div class="av-opt locked" data-idx="<?php echo $i?>" title="Terkunci">
            <?php echo $ico?>
            <span class="av-lock-ico">🔒</span>
            <div class="av-mission-tip">
              <span class="tip-label">🔒 Misi untuk membuka</span>
              <?php echo htmlspecialchars($mission['desc'])?>
              <div class="tip-bar-wrap"><div class="tip-bar-fill" style="width:<?php echo $prog_pct?>%"></div></div>
              <span style="font-size:.58rem;color:var(--muted)"><?php echo $prog['cur']?>/<?php echo $prog['max']?></span>
            </div>
          </div>
          <?php endif;?>
        <?php endforeach;?>
      </div>
      <label class="m-label" for="bioInput">Bio (maks. 160 karakter)</label>
      <textarea class="m-input m-textarea" id="bioInput" maxlength="160" placeholder="Cerita singkat tentang dirimu…"><?php echo $bio?></textarea>
      <div class="char-count" id="bioCount"><?php echo mb_strlen($player['bio']??'','UTF-8')?>/160</div>
      <button class="btn-save" id="btnSaveAv" onclick="saveAvBio()">
        <span id="savAvTxt">💾 Simpan Avatar &amp; Bio</span><span id="savAvSpin" style="display:none">⏳</span>
      </button>
    </div>
    <div id="panelName" style="display:none">
      <div id="nameErrMsg" class="m-error"></div>
      <div class="m-info">💡 <strong>Nama Tampilan</strong> bisa diubah kapan saja.<br><strong>Username</strong> hanya bisa diganti maks. <strong>3×</strong>. Sisa: <strong id="changesLeft"><?php echo $changes_left?></strong> kali.</div>
      <label class="m-label" for="dispNameInput">Nama Tampilan</label>
      <input class="m-input" id="dispNameInput" type="text" maxlength="30" placeholder="Nama yang terlihat semua orang" value="<?php echo htmlspecialchars($player['display_name']??$player['username'])?>">
      <div style="margin-bottom:.9rem"></div>
      <button class="btn-save" id="btnSaveName" onclick="saveDisplayName()">
        <span id="savNmTxt">✅ Simpan Nama Tampilan</span><span id="savNmSpin" style="display:none">⏳</span>
      </button>
      <div style="margin-top:1.2rem;border-top:1px solid var(--border);padding-top:1.2rem">
        <label class="m-label">Ganti Username (Login) — Memerlukan Password</label>
        <div class="m-info" style="font-size:.68rem">⚠️ Username digunakan untuk login. Setelah diganti, gunakan username baru untuk masuk.</div>
        <input class="m-input" id="newUsernameInput" type="text" maxlength="20" placeholder="Username baru (3–20 karakter, a-z 0-9 _)" style="margin-bottom:.7rem" value="<?php echo $username?>">
        <input class="m-input" id="confirmPwInput" type="password" placeholder="Konfirmasi password kamu" style="margin-bottom:.3rem">
        <div class="char-count" id="unameInfo"><?php echo $username_changes?>/3 kali sudah diganti</div>
        <button class="btn-save" id="btnSaveUname" onclick="saveUsername()" <?php echo $changes_left<=0?'disabled style="opacity:.35;pointer-events:none"':''?>>
          <span id="savUnTxt">🔄 Ganti Username</span><span id="savUnSpin" style="display:none">⏳</span>
        </button>
      </div>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
/* ── BACK BUTTON ── */
(function(){
  const KP=['main_menu.php','lobby.php','lobby_pvp.php','history.php','option.php','statistik.php','gameplay.php','gameplay_pvp.php','collection.php'];
  const btn=document.getElementById('btnBack');
  const urlFrom=new URLSearchParams(window.location.search).get('from');
  if(urlFrom&&KP.includes(urlFrom)){
    btn.href=urlFrom;
    btn.textContent=urlFrom==='collection.php'?'← Back':'← Back';
    return;
  }
  try{const ref=document.referrer;if(ref){const rp=new URL(ref).pathname.split('/').pop();if(KP.includes(rp)&&rp!=='profile.php'){btn.href=ref;btn.textContent='← Back';return;}}}catch(e){}
  btn.href='main_menu.php';btn.textContent='← Menu';
})();

const API_BASE='../Api/profile_update.php';
const AVATARS=<?php echo json_encode($AVATARS)?>;
const UNLOCKED_AVATARS=<?php echo json_encode(array_values($all_unlocked_indices))?>;
// Data misi lengkap dari server (untuk render ulang sisi client)
let MISSION_DATA=<?php echo json_encode(array_values($missions_for_js))?>;
let curAvChoice=<?php echo $avatar_choice?>;
let curAvEmoji=<?php echo json_encode($avatar_emoji)?>;
let activeTab='av';

// ── AVATAR MISSION REFRESH ──────────────────────────────────────────
async function refreshAvatarMissions(silent=false){
  const btn=document.getElementById('avmRefreshBtn');
  if(btn){btn.classList.add('spinning');btn.disabled=true;}
  try{
    const r=await fetch('../Api/avatar_mission_get.php',{cache:'no-store'});
    const d=await r.json();
    if(!d.success) throw new Error(d.error||'Gagal');

    const missions=d.missions;
    let justUnlocked=d.newly_unlocked||[];

    missions.forEach(m=>{
      const i=m.index;
      const wasUnlocked=(MISSION_DATA[i]||{}).unlocked;
      const nowUnlocked=m.unlocked;

      // Update local data
      if(MISSION_DATA[i]) MISSION_DATA[i]={...MISSION_DATA[i],...m};

      // DOM update
      const card=document.getElementById('avmCard'+i);
      const statusEl=document.getElementById('avmStatus'+i);
      const progFill=document.getElementById('avmProgFill'+i);
      const progVal=document.getElementById('avmProgVal'+i);

      if(!card) return;

      if(nowUnlocked&&!wasUnlocked){
        // Baru unlock!
        card.classList.remove('locked');card.classList.add('unlocked','just-unlocked');
        setTimeout(()=>card.classList.remove('just-unlocked'),700);
        if(statusEl){statusEl.textContent='✨ BARU TERBUKA';statusEl.className='avm-status s-new';
          setTimeout(()=>{statusEl.textContent='✓ TERBUKA';statusEl.className='avm-status s-done';},3500);}
        // Buka avatar di grid modal juga
        const avOpt=document.querySelector(`.av-opt[data-idx="${i}"]`);
        if(avOpt){
          avOpt.classList.remove('locked');
          avOpt.innerHTML=AVATARS[i]; // hapus ikon 🔒
          avOpt.onclick=()=>pickAvatar(i,AVATARS[i]);
        }
        if(!UNLOCKED_AVATARS.includes(i)) UNLOCKED_AVATARS.push(i);
      } else if(nowUnlocked&&wasUnlocked){
        card.classList.remove('locked');card.classList.add('unlocked');
        if(statusEl&&statusEl.className.includes('s-lock')){
          statusEl.textContent= i===0?'✓ DEFAULT':'✓ TERBUKA';
          statusEl.className='avm-status s-done';
        }
      }

      if(progFill&&i>0){
        progFill.style.width=m.pct+'%';
        progFill.className='avm-prog-fill '+(nowUnlocked?'done':'partial');
      }
      if(progVal&&i>0) progVal.textContent=m.cur+'/'+m.max;
    });

    // Update pill counter
    const pill=document.getElementById('avmCountPill');
    if(pill) pill.textContent=d.unlocked_count+'/'+d.total+' Terbuka';

    if(justUnlocked.length>0){
      toast('🎉 Avatar baru terbuka: '+justUnlocked.map(i=>AVATARS[i]).join(' '),false,5000);
    } else if(!silent){
      toast('✅ Status misi diperbarui',false,2000);
    }
  }catch(e){
    if(!silent) toast('❌ Gagal refresh: '+e.message,true);
  }finally{
    if(btn){btn.classList.remove('spinning');btn.disabled=false;}
  }
}

// Auto-refresh setiap 30 detik (silent)
setInterval(()=>refreshAvatarMissions(true),30000);

// Tangkap event avatar_unlocked dari WebSocket (jika halaman gameplay masih hidup di tab lain)
// Gunakan BroadcastChannel agar server.php → gameplay_pvp.php → profile.php bisa sinkron
(function(){
  try{
    const bc=new BroadcastChannel('lucky_battle_events');
    bc.onmessage=(ev)=>{
      const d=ev.data;
      if(d&&d.type==='avatar_unlocked'&&Array.isArray(d.avatar_indices)){
        // Tandai avatar yang baru unlock, lalu refresh UI
        refreshAvatarMissions(true);
      }
    };
  }catch(e){}
})();

/* ── CANVAS NODE NETWORK ── */
(function(){
  const c=document.getElementById('bg'),x=c.getContext('2d');
  let W,H,NS=[];
  const COLS=['rgba(255,77,77,','rgba(77,166,255,','rgba(125,255,77,'];
  function rsz(){W=c.width=innerWidth;H=c.height=innerHeight}
  function mkN(){NS=Array.from({length:55},()=>({
    x:Math.random()*W,y:Math.random()*H,
    vx:(Math.random()-.5)*.45,vy:(Math.random()-.5)*.45,
    r:Math.random()*2+.8,col:COLS[Math.floor(Math.random()*3)],
    a:Math.random()*.5+.1,da:.002
  }))}
  function draw(){
    x.clearRect(0,0,W,H);
    const g=x.createRadialGradient(W/2,H*.4,0,W/2,H*.4,Math.max(W,H)*.75);
    g.addColorStop(0,'rgba(14,17,38,.97)');g.addColorStop(1,'rgba(5,6,13,1)');
    x.fillStyle=g;x.fillRect(0,0,W,H);
    for(const n of NS){
      n.x+=n.vx;n.y+=n.vy;
      if(n.x<0||n.x>W)n.vx*=-1;if(n.y<0||n.y>H)n.vy*=-1;
      n.a+=n.da;if(n.a>.65||n.a<.05)n.da*=-1;
      for(const m of NS){const d=Math.hypot(n.x-m.x,n.y-m.y);if(d<160){x.beginPath();x.moveTo(n.x,n.y);x.lineTo(m.x,m.y);x.strokeStyle=n.col+(1-d/160)*.065+')';x.lineWidth=.5;x.stroke();}}
      x.beginPath();x.arc(n.x,n.y,n.r,0,Math.PI*2);x.fillStyle=n.col+n.a+')';x.fill();
      if(n.r>1.6){x.beginPath();x.arc(n.x,n.y,n.r*2.8,0,Math.PI*2);x.fillStyle=n.col+n.a*.18+')';x.fill();}
    }
    for(let i=0;i<100;i++){const sx=(i*137.5)%W,sy=(i*93.7)%H;const sa=.06+.38*Math.abs(Math.sin(Date.now()*.0008+i));x.beginPath();x.arc(sx,sy,.6,0,Math.PI*2);x.fillStyle=`rgba(238,240,255,${sa})`;x.fill();}
    requestAnimationFrame(draw);
  }
  window.addEventListener('resize',()=>{rsz();mkN()});rsz();mkN();draw();
})();

/* ── ENERGY LINES ── */
const ELC=document.getElementById('EL');
for(let i=0;i<7;i++){const e=document.createElement('div');e.className='el';e.style.cssText=`left:${Math.random()*100}%;height:${Math.random()*45+18}px;animation-duration:${Math.random()*9+5}s;animation-delay:${Math.random()*9}s;opacity:.32;`;ELC.appendChild(e);}

/* ── COUNTER ANIMATION ── */
function animCount(el,target,dur=1400){
  const start=performance.now();
  function step(now){
    const p=Math.min((now-start)/dur,1);
    const v=Math.floor(p*target);
    el.textContent=v.toLocaleString();
    if(p<1)requestAnimationFrame(step);
    else el.textContent=target.toLocaleString();
  }
  requestAnimationFrame(step);
}

/* ── ANIMATE ON LOAD ── */
window.addEventListener('DOMContentLoaded',()=>{
  /* counter roll */
  setTimeout(()=>{
    animCount(document.getElementById('cnt-rating'),<?php echo $rating?>,1600);
    animCount(document.getElementById('cnt-wins'),<?php echo $wins?>,1100);
    animCount(document.getElementById('cnt-losses'),<?php echo $losses?>,1100);
    animCount(document.getElementById('cnt-streak'),<?php echo $max_streak?>,900);
  },300);
  /* bar animate */
  const rf=document.getElementById('rFill');
  if(rf){const t=rf.style.width;rf.style.width='0%';setTimeout(()=>rf.style.width=t,500);}
  document.querySelectorAll('.ch-fill').forEach(el=>{const w=el.style.width;el.style.width='0%';setTimeout(()=>el.style.width=w,600);});
  /* avatar mission progress bar animate */
  document.querySelectorAll('.avm-prog-fill').forEach((el,i)=>{
    const w=el.style.width;el.style.width='0%';
    setTimeout(()=>el.style.width=w, 400+i*60);
  });
  /* card stagger entrance */
  document.querySelectorAll('.card').forEach((c,i)=>{
    c.style.opacity='0';c.style.transform='translateY(22px)';
    setTimeout(()=>{c.style.transition='opacity .5s ease,transform .5s ease';c.style.opacity='1';c.style.transform='translateY(0)';},200+i*80);
  });
});

/* ── MODAL ── */
function openEditModal(){document.getElementById('editOverlay').classList.add('open');switchTab(activeTab);}
function closeModal(){document.getElementById('editOverlay').classList.remove('open');}
document.getElementById('editOverlay').addEventListener('click',e=>{if(e.target===document.getElementById('editOverlay'))closeModal();});
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeModal();});

function switchTab(tab){
  activeTab=tab;
  document.getElementById('panelAv').style.display=tab==='av'?'':'none';
  document.getElementById('panelName').style.display=tab==='name'?'':'none';
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active-tab'));
  document.getElementById(tab==='av'?'tabAv':'tabName').classList.add('active-tab');
}

function pickAvatar(idx,emoji){
  if(!UNLOCKED_AVATARS.includes(idx)){
    toast('🔒 Avatar ini masih terkunci! Selesaikan misinya terlebih dahulu.',true,3500);
    return;
  }
  document.querySelectorAll('.av-opt').forEach(el=>el.classList.remove('sel'));
  document.querySelector(`.av-opt[data-idx="${idx}"]`).classList.add('sel');
  curAvChoice=idx;curAvEmoji=emoji;
}

const bioInput=document.getElementById('bioInput'),bioCount=document.getElementById('bioCount');
bioInput.addEventListener('input',function(){const l=[...this.value].length;bioCount.textContent=l+'/160';bioCount.style.color=l>140?'#ff9090':'var(--muted)';});

function toast(msg,isErr=false,dur=3000){
  const t=document.getElementById('toast');t.textContent=msg;
  t.className='toast show'+(isErr?' err':'');clearTimeout(t._t);
  t._t=setTimeout(()=>t.classList.remove('show','err'),dur);
}
function setLoading(tId,sId,bId,on){
  document.getElementById(tId).style.display=on?'none':'';
  document.getElementById(sId).style.display=on?'':'none';
  document.getElementById(bId).disabled=on;
}

async function saveAvBio(){
  const er=document.getElementById('avErrMsg');er.classList.remove('show');
  setLoading('savAvTxt','savAvSpin','btnSaveAv',true);
  const b=new FormData();b.append('action','update_profile');b.append('avatar_choice',curAvChoice);b.append('bio',bioInput.value.trim());
  try{const r=await fetch(API_BASE,{method:'POST',body:b});const d=await r.json();
    if(d.success){document.getElementById('avEmoji').textContent=d.avatar;document.getElementById('heroName').textContent=d.display_name;
      const bd=document.getElementById('bioDisplay');if(d.bio){bd.textContent=d.bio;bd.classList.remove('bio-empty');bd.onclick=null;}else{bd.textContent='「 Tambahkan bio kamu… 」';bd.classList.add('bio-empty');bd.onclick=openEditModal;}
      toast('✅ Avatar & bio berhasil disimpan!');setTimeout(closeModal,800);}
    else{er.textContent='❌ '+(d.error||'Gagal menyimpan.');er.classList.add('show');}
  }catch(e){er.textContent='❌ Koneksi gagal: '+e.message;er.classList.add('show');}
  finally{setLoading('savAvTxt','savAvSpin','btnSaveAv',false);}
}

async function saveDisplayName(){
  const er=document.getElementById('nameErrMsg'),nv=document.getElementById('dispNameInput').value.trim();er.classList.remove('show');
  if(!nv){er.textContent='❌ Nama tidak boleh kosong.';er.classList.add('show');return;}
  setLoading('savNmTxt','savNmSpin','btnSaveName',true);
  const b=new FormData();b.append('action','update_display_name');b.append('display_name',nv);
  try{const r=await fetch(API_BASE,{method:'POST',body:b});const d=await r.json();
    if(d.success){document.getElementById('heroName').textContent=d.display_name;toast('✅ Nama tampilan diperbarui!');setTimeout(closeModal,800);}
    else{er.textContent='❌ '+(d.error||'Gagal menyimpan.');er.classList.add('show');}
  }catch(e){er.textContent='❌ Koneksi gagal.';er.classList.add('show');}
  finally{setLoading('savNmTxt','savNmSpin','btnSaveName',false);}
}

async function saveUsername(){
  const er=document.getElementById('nameErrMsg'),un=document.getElementById('newUsernameInput').value.trim(),pw=document.getElementById('confirmPwInput').value;er.classList.remove('show');
  if(!un||!pw){er.textContent='❌ Isi username dan password.';er.classList.add('show');return;}
  setLoading('savUnTxt','savUnSpin','btnSaveUname',true);
  const b=new FormData();b.append('action','update_username');b.append('username',un);b.append('password',pw);
  try{const r=await fetch(API_BASE,{method:'POST',body:b});const d=await r.json();
    if(d.success){document.getElementById('changesLeft').textContent=d.changes_left;document.getElementById('unameInfo').textContent=(3-d.changes_left)+'/3 kali sudah diganti';toast('✅ Username berhasil diubah!',false,4000);setTimeout(()=>location.reload(),2200);}
    else{er.textContent='❌ '+(d.error||'Gagal.');er.classList.add('show');}
  }catch(e){er.textContent='❌ Koneksi gagal.';er.classList.add('show');}
  finally{setLoading('savUnTxt','savUnSpin','btnSaveUname',false);}
}
// ── THEME TOGGLE ──
(function(){
  const saved = localStorage.getItem('rps_theme') || 'dark';
  const iconEl = document.querySelector('#btnThemeToggle .theme-icon');
  if (saved === 'light') {
    document.documentElement.setAttribute('data-theme', 'light');
    if (iconEl) iconEl.textContent = 'Dark Mode';
  } else {
    if (iconEl) iconEl.textContent = 'Light Mode';
  }
  document.getElementById('btnThemeToggle')?.addEventListener('click', () => {
    const isLight = document.documentElement.getAttribute('data-theme') === 'light';
    document.documentElement[isLight ? 'removeAttribute' : 'setAttribute']('data-theme', 'light');
    localStorage.setItem('rps_theme', isLight ? 'dark' : 'light');
    const icon = document.querySelector('#btnThemeToggle .theme-icon');
    if (icon) icon.textContent = isLight ? 'Light Mode' : 'Dark Mode';
  });
})();
</script>
<script src="assets/sound_system.js"></script>
</body>
</html>