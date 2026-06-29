<?php
// ══════════════════════════════════════════════
//  LOBBY PvP — Rock Paper Scissors Online
//  Matchmaking realtime via WebSocket
// ══════════════════════════════════════════════
session_start();
if (!isset($_SESSION['player_id'])) {
    header('Location: Landing_page.php');
    exit;
}
require_once __DIR__ . '/../Backend/database.php';

// ── FIX: ISOLASI IDENTITY PER TAB ──────────────────────────────────────
if (!isset($_SESSION['allowed_player_ids'])) {
    $_SESSION['allowed_player_ids'] = [];
}
if (!in_array($_SESSION['player_id'], $_SESSION['allowed_player_ids'])) {
    $_SESSION['allowed_player_ids'][] = $_SESSION['player_id'];
}

$pid_from_url = trim($_GET['pid'] ?? '');

if ($pid_from_url !== '' && in_array($pid_from_url, $_SESSION['allowed_player_ids'])) {
    $player_id   = $pid_from_url;
    $player_name = $_SESSION['player_names'][$player_id] ?? strtoupper($player_id);
} else {
    $current_id = $_SESSION['player_id'];
    $redirect_url = strtok($_SERVER['REQUEST_URI'], '?') . '?pid=' . urlencode($current_id);
    header('Location: ' . $redirect_url);
    exit;
}

if (!isset($_SESSION['player_names'])) {
    $_SESSION['player_names'] = [];
}
$_SESSION['player_names'][$_SESSION['player_id']] = $_SESSION['player_name'] ?? strtoupper($_SESSION['player_id']);
$player_name = $_SESSION['player_names'][$player_id] ?? strtoupper($player_id);

// Ambil data player dari DB
$playerData = getPlayerData($player_id);
$wins       = $playerData['wins']   ?? 0;
$losses     = $playerData['losses'] ?? 0;
$draws      = $playerData['draws']  ?? 0;
$rating     = $playerData['rating'] ?? 1000;
$playerRank = getPlayerRank($player_id);

// Ambil peak rank (rank tertinggi yang pernah dicapai)
// Default: rating sekarang (tidak bisa lebih rendah dari rating aktif)
$peak_rating = $rating;
try {
    $sp2 = getDB()->prepare("SELECT peak_rating FROM players WHERE id = ? LIMIT 1");
    $sp2->execute([$player_id]);
    $pr2 = $sp2->fetch();
    if ($pr2 && !is_null($pr2['peak_rating']) && (int)$pr2['peak_rating'] > 0) {
        // Ambil nilai terbesar antara peak_rating di DB dan rating aktif
        // (jika DB belum sempat update, rating aktif bisa jadi lebih tinggi)
        $peak_rating = max((int)$pr2['peak_rating'], $rating);
    } else {
        // Kolom kosong / belum ada — pakai rating aktif dan update DB sekalian
        $peak_rating = $rating;
        try {
            getDB()->prepare("UPDATE players SET peak_rating = ? WHERE id = ? AND (peak_rating IS NULL OR peak_rating = 0 OR peak_rating < ?)")
                   ->execute([$rating, $player_id, $rating]);
        } catch (Throwable) {}
    }
} catch (Throwable) {
    $peak_rating = $rating;
}

// Hitung peak tier berdasarkan peak_rating
// ── RANK TIERS (definisi di sini agar bisa dipakai untuk peak tier) ──
$rank_tiers = [
    [2000,'GRANDMASTER','#ffd700','rgba(255,215,0,.55)',  '','Penguasa Arena Tertinggi'],
    [1700,'MASTER',     '#c084fc','rgba(192,132,252,.55)','','Ahli Strategi Tak Tertandingi'],
    [1500,'DIAMOND',    '#4da6ff','rgba(77,166,255,.55)', '','Petarung Berlian Sejati'],
    [1300,'PLATINUM',   '#7dff4d','rgba(125,255,77,.55)', '','Pejuang Kelas Tinggi'],
    [1100,'GOLD',       '#f5c842','rgba(245,200,66,.55)', '','Emas Murni Arena'],
    [950, 'SILVER',     '#c0c0c0','rgba(192,192,192,.55)','','Perak yang Tangguh'],
    [0,   'BRONZE',     '#cd7f32','rgba(205,127,50,.55)', '','Pemula Berbakat'],
];

$peak_tier_name='BRONZE'; $peak_tier_col='#cd7f32'; $peak_tier_icon='⚔️'; $peak_tier_desc='Pemula Berbakat';
foreach($rank_tiers as [$min,$name,$col,$glow,$icon,$desc]){
    if($peak_rating >= $min){ $peak_tier_name=$name; $peak_tier_col=$col; $peak_tier_icon=$icon; $peak_tier_desc=$desc; break; }
}

// Ambil avatar & display_name dari database
$AVATARS_LIST = ['⚔️','🛡️','🔥','💎','🌪️','👑','🤖','🐉','⚡','🌙','🗡️','🔮'];
$nav_avatar   = '⚔️';
$nav_dispname = $player_name;
try {
    $sp = getDB()->prepare("SELECT avatar, avatar_choice, display_name FROM players WHERE id = ? LIMIT 1");
    $sp->execute([$player_id]);
    $pr = $sp->fetch();
    if ($pr) {
        $nav_avatar   = htmlspecialchars($pr['avatar'] ?? ($AVATARS_LIST[(int)($pr['avatar_choice']??0)] ?? '⚔️'));
        $nav_dispname = htmlspecialchars($pr['display_name'] ?? $player_name);
    }
} catch (Throwable) {}

// Leaderboard dari DB
$leaderboard = getLeaderboard(10);

// ── RANK TIERS sudah didefinisikan di atas (sebelum peak tier) ──

$tier_name='BRONZE'; $tier_col='#cd7f32'; $tier_glow='rgba(205,127,50,.55)'; $tier_icon='⚔️'; $tier_desc='Pemula Berbakat';
$next_tier_name='SILVER'; $next_rating=950;
foreach($rank_tiers as $i=>[$min,$name,$col,$glow,$icon,$desc]){
    if($rating>=$min){
        $tier_name=$name;$tier_col=$col;$tier_glow=$glow;$tier_icon=$icon;$tier_desc=$desc;
        // next tier
        if($i>0){
            $next_tier_name=$rank_tiers[$i-1][1];
            $next_rating=$rank_tiers[$i-1][0];
        } else {
            $next_tier_name='MAX';
            $next_rating=$min;
        }
        break;
    }
}
// Progress to next tier
$prev_min = 0;
foreach($rank_tiers as $i=>[$min,$name,$col,$glow,$icon,$desc]){
    if($rating>=$min){
        $prev_min=$min;
        if($i>0) $next_min=$rank_tiers[$i-1][0]; else $next_min=$min;
        break;
    }
}
$progress_pct = ($next_rating>$prev_min && $prev_min!=$next_rating)
    ? min(100,round(($rating-$prev_min)/($next_rating-$prev_min)*100))
    : 100;

// Win rate
$total_games = $wins + $losses + $draws;
$winrate = $total_games > 0 ? round($wins / $total_games * 100) : 0;

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>PvP Lobby — Battle Arena</title>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Bebas+Neue&family=Russo+One&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --rock:#ff4d4d;--paper:#4da6ff;--scissors:#7dff4d;
  --gr:rgba(255,77,77,.6);--gp:rgba(77,166,255,.6);--gs:rgba(125,255,77,.6);
  --dark:#05060d;--mid:#0b0d1a;--card:rgba(255,255,255,.028);
  --text:#eef0ff;--muted:rgba(238,240,255,.38);--border:rgba(238,240,255,.07);
  --color-grandmaster:#ffd700;
  --color-master:#c084fc;
  --color-diamond:#4da6ff;
  --color-platinum:#7dff4d;
  --color-gold:#f5c842;
  --color-silver:#c0c0c0;
  --color-bronze:#cd7f32;
  --glow-grandmaster:rgba(255,215,0,.5);
  --glow-master:rgba(192,132,252,.5);
  --glow-diamond:rgba(77,166,255,.5);
  --glow-platinum:rgba(125,255,77,.5);
  --glow-gold:rgba(245,200,66,.5);
  --glow-silver:rgba(192,192,192,.5);
  --glow-bronze:rgba(205,127,50,.5);
  --win:#7dff4d;--lose:#ff5e5e;--draw:#8899bb;--gold:#ffd700;
  --rc:var(--color-<?= strtolower($tier_name) ?>);--rg:<?php echo $tier_glow?>;
}
html,body{width:100%;min-height:100%;background:var(--dark);font-family:'Rajdhani',sans-serif;overflow-x:hidden}

/* ── LAYERS ── */
canvas#bg{position:fixed;inset:0;z-index:0}
.hex-layer{position:fixed;inset:0;z-index:1;pointer-events:none;opacity:.045;
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='60' height='104'%3E%3Cpolygon points='30,2 58,17 58,47 30,62 2,47 2,17' fill='none' stroke='%234da6ff' stroke-width='0.8'/%3E%3Cpolygon points='30,52 58,67 58,97 30,112 2,97 2,67' fill='none' stroke='%234da6ff' stroke-width='0.8'/%3E%3C/svg%3E");
  background-size:60px 104px}
.noise{position:fixed;inset:0;z-index:2;pointer-events:none;opacity:.03;
  background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
  background-size:200px 200px}
.elines{position:fixed;inset:0;z-index:3;pointer-events:none;overflow:hidden}
.el{position:absolute;width:1px;background:linear-gradient(to bottom,transparent,rgba(77,166,255,.45),transparent);animation:elfall linear infinite}
@keyframes elfall{from{transform:translateY(-100vh);opacity:0}10%,90%{opacity:1}to{transform:translateY(100vh);opacity:0}}
.scanline{position:fixed;inset:0;z-index:4;pointer-events:none;
  background:repeating-linear-gradient(to bottom,transparent 0,transparent 3px,rgba(0,0,0,.07) 3px,rgba(0,0,0,.07) 4px)}
.vignette{position:fixed;inset:0;z-index:4;pointer-events:none;
  background:radial-gradient(ellipse at center,transparent 40%,rgba(0,0,0,.55) 100%)}
.particles{position:fixed;inset:0;z-index:3;pointer-events:none;overflow:hidden}
.p{position:absolute;border-radius:50%;animation:pfloat linear infinite}
@keyframes pfloat{from{transform:translateY(110vh) rotate(0deg);opacity:0}10%,90%{opacity:1}to{transform:translateY(-10vh) rotate(360deg);opacity:0}}

/* ── CORNERS ── */
.corner{position:fixed;z-index:6;pointer-events:none}
.corner::before,.corner::after{content:'';position:absolute;background:rgba(77,166,255,.5)}
.corner::before{width:2px;height:50px}.corner::after{width:50px;height:2px}
.c-tl{top:20px;left:20px}.c-tr{top:20px;right:20px;transform:scaleX(-1)}
.c-bl{bottom:20px;left:20px;transform:scaleY(-1)}.c-br{bottom:20px;right:20px;transform:scale(-1)}
.corner::before,.corner::after{top:0;left:0}

/* ── PLAYER BAR ── */
.pbar{
  position:fixed;top:0;left:0;right:0;z-index:30;
  display:flex;align-items:center;justify-content:space-between;
  padding:10px 28px;
  background:linear-gradient(180deg,rgba(5,6,13,.92) 0%,rgba(5,6,13,.6) 100%);
  border-bottom:1px solid var(--border);backdrop-filter:blur(24px);
}
.pinfo{display:flex;align-items:center;gap:11px;text-decoration:none;cursor:pointer;
  padding:5px 14px 5px 5px;border:1px solid transparent;transition:all .25s;
  clip-path:polygon(10px 0%,100% 0%,calc(100% - 10px) 100%,0% 100%)}
.pinfo:hover{background:rgba(77,166,255,.07);border-color:rgba(77,166,255,.22)}
.pav{
  width:42px;height:42px;font-size:20px;
  background:linear-gradient(135deg,rgba(77,166,255,.18),rgba(125,255,77,.1));
  border:1.5px solid var(--rc);
  display:flex;align-items:center;justify-content:center;
  box-shadow:0 0 20px var(--rg);transition:all .25s;
  clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%);
}
.pname{font-family:'Russo One',sans-serif;font-size:.76rem;color:var(--text);letter-spacing:.1em}
.pid{font-family:'Rajdhani',sans-serif;font-size:.68rem;color:var(--muted);letter-spacing:.06em;margin-top:1px}
.phint{font-size:.58rem;color:rgba(77,166,255,.55);letter-spacing:.1em;margin-top:1px;font-weight:600}

/* rank pill in bar */
.rank-pill{
  display:flex;align-items:center;gap:8px;
  border:1px solid var(--rc);padding:6px 16px;
  background:linear-gradient(135deg,rgba(5,6,13,.8),rgba(13,15,26,.9));
  box-shadow:0 0 18px var(--rg);
  clip-path:polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%);
}
.rank-icon{font-size:18px}
.rank-info{display:flex;flex-direction:column;gap:1px}
.rank-name-lbl{font-family:'Russo One',sans-serif;font-size:.62rem;letter-spacing:.2em;color:var(--rc)}
.rank-pts{font-family:'Bebas Neue',sans-serif;font-size:.82rem;letter-spacing:.08em;color:var(--muted)}

.pstats{display:flex;gap:6px;align-items:center}
.ps{display:flex;align-items:center;gap:6px;padding:6px 12px;
  background:rgba(238,240,255,.03);border:1px solid var(--border);
  clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%)}
.ps-val{font-family:'Bebas Neue',sans-serif;font-size:.88rem;letter-spacing:.06em}
.ps-lbl{font-size:9px;letter-spacing:.18em;text-transform:uppercase;color:var(--muted);font-weight:600}

.btn-back{
  font-family:'Rajdhani',sans-serif;font-size:.7rem;font-weight:700;
  letter-spacing:.18em;text-transform:uppercase;
  color:rgba(77,166,255,.85);
  background:transparent;
  border:1px solid rgba(77,166,255,.2);
  padding:8px 20px;cursor:pointer;transition:all .2s;text-decoration:none;
  display:inline-flex;align-items:center;gap:6px;
  clip-path:polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%)}
.btn-back:hover{background:rgba(77,166,255,.18);border-color:rgba(77,166,255,.45);color:#4da6ff}

/* ── PAGE WRAP ── */
.page{
  position:relative;z-index:10;
  max-width:700px;margin:0 auto;
  padding:86px 18px 60px;
  display:flex;flex-direction:column;gap:16px;
}

/* ── PAGE HEADER ── */
.page-header{text-align:center;padding:6px 0 4px}
.atag{display:flex;align-items:center;justify-content:center;gap:14px;
  font-family:'Rajdhani',sans-serif;font-size:11px;font-weight:700;
  letter-spacing:.55em;text-transform:uppercase;color:var(--paper);margin-bottom:10px}
.atag-line{width:44px;height:1px;
  background:linear-gradient(to right,transparent,var(--paper));opacity:.5}
.page-title{
  font-family:'Bebas Neue',sans-serif;font-size:clamp(2rem,7vw,3.8rem);
  line-height:.9;letter-spacing:.06em;position:relative;
  background:linear-gradient(135deg,#ff4d4d 0%,#eef0ff 40%,#4da6ff 70%,#7dff4d 100%);
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
}
.page-title .wr,.page-title .wp,.page-title .ws{text-shadow:none;}
.page-sub{font-family:'Rajdhani',sans-serif;font-size:.72rem;color:var(--muted);
  font-weight:600;letter-spacing:.28em;text-transform:uppercase;margin-top:4px}

/* ── PANEL BASE ── */
.panel{
  background:rgba(255,255,255,.026);
  border:1px solid var(--border);
  padding:18px 20px;
  position:relative;overflow:hidden;
  clip-path:polygon(12px 0%,100% 0%,calc(100% - 12px) 100%,0% 100%);
}
.panel::before{content:'';position:absolute;top:0;left:0;right:0;height:1.5px;
  background:linear-gradient(90deg,transparent,var(--paper),transparent);opacity:.35}
.panel-title{
  font-family:'Russo One',sans-serif;font-size:.6rem;letter-spacing:.3em;
  text-transform:uppercase;color:var(--muted);margin-bottom:12px;
  display:flex;align-items:center;gap:8px}
.panel-title::after{content:'';flex:1;height:1px;background:var(--border)}

/* ── PLAYER IDENTITY PANEL ── */
.id-panel{display:grid;grid-template-columns:auto 1fr auto;gap:16px;align-items:center}
.id-avatar{
  width:58px;height:58px;font-size:26px;
  background:linear-gradient(135deg,rgba(77,166,255,.18),rgba(125,255,77,.1));
  border:2px solid var(--rc);
  display:flex;align-items:center;justify-content:center;
  box-shadow:0 0 28px var(--rg);
  clip-path:polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%);
}
.id-name{font-family:'Russo One',sans-serif;font-size:.92rem;letter-spacing:.1em;color:var(--text);margin-bottom:4px}
.id-pid{font-size:.68rem;color:var(--muted);letter-spacing:.05em;margin-bottom:8px}
.id-stats{display:flex;gap:14px}
.id-stat{text-align:center}
.id-stat-val{font-family:'Bebas Neue',sans-serif;font-size:1.05rem;letter-spacing:.05em}
.id-stat-lbl{font-size:.6rem;letter-spacing:.2em;text-transform:uppercase;color:var(--muted);font-weight:700}
.id-rank-box{
  border:1.5px solid var(--rc);padding:10px 14px;text-align:center;
  background:linear-gradient(135deg,rgba(5,6,13,.9),rgba(13,15,26,.95));
  box-shadow:0 0 22px var(--rg);
  clip-path:polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%);
  min-width:80px;
}
.id-rank-icon{font-size:22px;display:block;margin-bottom:3px}
.id-rank-name{font-family:'Russo One',sans-serif;font-size:.58rem;letter-spacing:.18em;color:var(--rc);display:block}
.id-rank-rating{font-family:'Bebas Neue',sans-serif;font-size:.95rem;color:var(--muted);letter-spacing:.06em}

/* ── RANK PROGRESS PANEL ── */
.rank-progress-panel{}
.rank-tiers-row{display:flex;gap:6px;margin-bottom:14px;flex-wrap:wrap}
.tier-badge{
  display:flex;align-items:center;gap:5px;padding:5px 10px;
  border:1px solid;font-family:'Rajdhani',sans-serif;font-size:.65rem;font-weight:700;
  letter-spacing:.12em;text-transform:uppercase;transition:all .22s;
  clip-path:polygon(5px 0%,100% 0%,calc(100% - 5px) 100%,0% 100%);
  cursor:default;opacity:.5;
}
.tier-badge.active{opacity:1;box-shadow:0 0 18px var(--rg-badge)}
.tier-badge .t-icon{font-size:13px}

.progress-wrap{margin-bottom:6px}
.progress-labels{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:6px}
.progress-cur{font-family:'Bebas Neue',sans-serif;font-size:.82rem;color:var(--rc);letter-spacing:.06em}
.progress-next{font-size:.62rem;color:var(--muted);letter-spacing:.12em;font-weight:700}
.progress-bar{
  height:4px;
  background:rgba(238,240,255,.08);
  position:relative;overflow:hidden;
}
.progress-fill{
  height:100%;
  background:linear-gradient(90deg,var(--rc),rgba(238,240,255,.65));
  box-shadow:0 0 12px var(--rg);
  transition:width 1s cubic-bezier(.34,1.56,.64,1);
}
.progress-glow{position:absolute;top:-2px;right:0;width:6px;height:8px;
  background:var(--rc);filter:blur(4px);animation:pglow .8s ease-in-out infinite alternate}
@keyframes pglow{from{opacity:.5}to{opacity:1}}

/* ── MATCHMAKING PANEL ── */
.mm-idle-content{text-align:center;padding:10px 0}
.mm-idle-icon{font-size:2.8rem;margin-bottom:10px;opacity:.35}
.mm-idle-title{font-family:'Russo One',sans-serif;font-size:.78rem;letter-spacing:.18em;color:var(--muted);text-transform:uppercase;margin-bottom:4px}
.mm-idle-sub{font-size:.68rem;color:rgba(238,240,255,.2);letter-spacing:.06em}

.mm-searching-content{text-align:center;padding:8px 0}
.radar{
  width:86px;height:86px;margin:0 auto 14px;
  border-radius:50%;border:1.5px solid rgba(77,166,255,.15);
  position:relative;display:flex;align-items:center;justify-content:center;
}
.radar::before{
  content:'';position:absolute;inset:0;border-radius:50%;
  border:1.5px solid var(--paper);
  animation:radar-ring 1.6s ease-out infinite;
}
.radar::after{
  content:'';position:absolute;inset:0;border-radius:50%;
  border:1.5px solid var(--paper);opacity:.4;
  animation:radar-ring 1.6s ease-out infinite .55s;
}
@keyframes radar-ring{0%{transform:scale(.65);opacity:1}100%{transform:scale(1.5);opacity:0}}
.radar-inner{font-size:2.2rem;position:relative;z-index:1;animation:rspin 3s linear infinite}
@keyframes rspin{from{transform:rotate(0)}to{transform:rotate(360deg)}}
.mm-search-title{font-family:'Russo One',sans-serif;font-size:.78rem;letter-spacing:.18em;color:var(--paper);text-transform:uppercase;margin-bottom:4px}
.mm-search-sub{font-size:.68rem;color:var(--muted);letter-spacing:.06em;margin-bottom:10px}
.queue-pill{
  display:inline-flex;align-items:center;gap:7px;
  background:rgba(77,166,255,.08);border:1px solid rgba(77,166,255,.2);
  padding:4px 16px;font-size:.7rem;color:var(--paper);font-weight:700;letter-spacing:.08em;
  clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%);
}
.qdot{width:6px;height:6px;border-radius:50%;background:var(--paper);animation:blink 1s infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.15}}

/* ── ACTION BUTTONS ── */
.btn-row{display:flex;gap:10px}
.xbtn{
  flex:1;padding:13px 8px;
  font-family:'Rajdhani',sans-serif;font-size:.78rem;font-weight:700;
  letter-spacing:.18em;text-transform:uppercase;
  cursor:pointer;border:1px solid;transition:all .28s;position:relative;overflow:hidden;
  clip-path:polygon(10px 0%,100% 0%,calc(100% - 10px) 100%,0% 100%);
  display:flex;align-items:center;justify-content:center;gap:8px;
}
.xbtn::before{content:'';position:absolute;top:0;left:-100%;width:50%;height:100%;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,.07),transparent);
  transform:skewX(-18deg);transition:left .55s ease;pointer-events:none}
.xbtn:hover::before{left:160%}
.xbtn-battle{
  background:rgba(255,77,77,.1);border-color:rgba(255,77,77,.5);color:var(--rock);
  box-shadow:0 0 18px rgba(255,77,77,.15);flex:2;
}
.xbtn-battle:hover{background:rgba(255,77,77,.2);box-shadow:0 0 32px rgba(255,77,77,.35);transform:translateY(-3px) scale(1.02)}
.xbtn-battle:disabled{opacity:.35;cursor:not-allowed;transform:none;box-shadow:none}
.xbtn-lb{
  background:rgba(238,240,255,.03);border-color:var(--border);color:var(--muted);
}
.xbtn-lb:hover{background:rgba(245,200,66,.1);border-color:rgba(245,200,66,.4);color:#f5c842}
.xbtn-chat{
  background:rgba(77,166,255,.06);border-color:rgba(77,166,255,.3);color:var(--paper);
  position:relative;
}
.xbtn-chat:hover{background:rgba(77,166,255,.16);border-color:rgba(77,166,255,.55);color:#90d0ff;box-shadow:0 0 22px rgba(77,166,255,.2);transform:translateY(-2px)}
.chat-online-dot{
  width:8px;height:8px;border-radius:50%;background:#7dff4d;
  box-shadow:0 0 8px rgba(125,255,77,.8);animation:blink 1.4s infinite;
  flex-shrink:0;
}
.xbtn-cancel{
  background:rgba(255,77,77,.08);border-color:rgba(255,77,77,.3);color:var(--rock);
}
.xbtn-cancel:hover{background:rgba(255,77,77,.18);border-color:rgba(255,77,77,.5)}


/* ── DIVIDER ── */
.vrow{display:flex;align-items:center;gap:16px}
.vline{flex:1;height:1px;background:linear-gradient(to right,transparent,var(--border),transparent)}
.vtxt{font-family:'Rajdhani',sans-serif;font-size:10px;font-weight:700;
  letter-spacing:.42em;color:var(--muted);text-transform:uppercase;white-space:nowrap}

/* ── MODAL OVERLAY ── */
.modal-overlay{
  position:fixed;inset:0;z-index:100;
  background:rgba(0,0,0,.85);backdrop-filter:blur(10px);
  display:none;align-items:center;justify-content:center;padding:16px;
}
.modal-overlay.show{display:flex}

/* responsive leaderboard */
@media(max-width:700px){
  .lb2-shell{
    grid-template-columns:1fr;
    grid-template-rows:auto 1fr;
    width:100%;height:min(92vh,640px);
  }
  .lb2-left{max-height:52%;border-right:none;border-bottom:1px solid rgba(238,240,255,.07)}
  .lb2-right{min-height:200px}
}
.modal-box{
  background:linear-gradient(160deg,rgba(11,13,26,.98),rgba(5,6,13,.99));
  border:1px solid var(--border);
  width:100%;max-width:580px;max-height:88vh;overflow:hidden;
  display:flex;flex-direction:column;
  clip-path:polygon(16px 0%,100% 0%,calc(100% - 16px) 100%,0% 100%);
  position:relative;
}
.modal-box::before{content:'';position:absolute;top:0;left:0;right:0;height:1.5px;
  background:linear-gradient(90deg,transparent,#f5c842,transparent);opacity:.6}
.modal-head{
  display:flex;justify-content:space-between;align-items:center;
  padding:16px 22px;border-bottom:1px solid var(--border);
  background:rgba(255,255,255,.02);flex-shrink:0;
}
.modal-head-title{
  font-family:'Bebas Neue',sans-serif;font-size:1.4rem;letter-spacing:.15em;
  color:#f5c842;display:flex;align-items:center;gap:10px}
.modal-head-sub{font-family:'Rajdhani',sans-serif;font-size:.6rem;
  letter-spacing:.25em;color:var(--muted);text-transform:uppercase;margin-top:2px}
.btn-close{
  background:rgba(238,240,255,.04);border:1px solid var(--border);
  color:var(--muted);font-size:1rem;cursor:pointer;padding:6px 12px;
  clip-path:polygon(5px 0%,100% 0%,calc(100% - 5px) 100%,0% 100%);
  font-family:'Rajdhani',sans-serif;font-weight:700;letter-spacing:.1em;
  transition:all .2s;
}
.btn-close:hover{background:rgba(255,77,77,.1);border-color:rgba(255,77,77,.3);color:#ff9090}

.modal-body{overflow-y:auto;padding:18px 22px;flex:1}
.modal-body::-webkit-scrollbar{width:3px}
.modal-body::-webkit-scrollbar-track{background:transparent}
.modal-body::-webkit-scrollbar-thumb{background:rgba(77,166,255,.3);border-radius:2px}

/* ── LEADERBOARD TABLE (upgraded) ── */
.lb-header{display:none} /* hidden - replaced */
.lb-row,.lb-me,.lb-player,.lb-pav,.lb-pname,.lb-you,.lb-tier,.lb-tier-badge,.lb-rating-val,.lb-wins-val,.lb-pos{display:none}

/* ═══════════════════════════════════════
   REDESIGNED LEADERBOARD v2
═══════════════════════════════════════ */
.lb2-shell{
  position:relative;
  display:grid;grid-template-columns:340px 1fr;
  width:min(92vw,860px);height:min(88vh,640px);
  background:linear-gradient(160deg,rgba(8,10,22,.97),rgba(5,6,13,.99));
  border:1px solid rgba(238,240,255,.1);
  overflow:hidden;
  animation:lb2-in .35s cubic-bezier(.34,1.2,.64,1);
}
@keyframes lb2-in{from{opacity:0;transform:scale(.94) translateY(16px)}to{opacity:1;transform:none}}
.lb2-shell::before{
  content:'';position:absolute;top:0;left:0;right:0;height:2px;
  background:linear-gradient(90deg,transparent,#f5c842,rgba(77,166,255,.8),transparent);
  opacity:.7;pointer-events:none;
}

/* ── LEFT PANEL ── */
.lb2-left{
  display:flex;flex-direction:column;
  border-right:1px solid rgba(238,240,255,.07);
  overflow:hidden;
}

.lb2-head{
  display:flex;justify-content:space-between;align-items:flex-start;
  padding:20px 20px 14px;
  background:linear-gradient(180deg,rgba(255,255,255,.03),transparent);
  border-bottom:1px solid rgba(238,240,255,.06);
  flex-shrink:0;
}
.lb2-head-eyebrow{
  font-size:.55rem;letter-spacing:.35em;color:rgba(238,240,255,.6);
  font-family:'Rajdhani',sans-serif;font-weight:700;text-transform:uppercase;margin-bottom:4px;
}
.lb2-head-title{
  font-family:'Bebas Neue',sans-serif;font-size:1.7rem;letter-spacing:.18em;
  color:#f5c842;line-height:1;
  text-shadow:0 0 30px rgba(245,200,66,.35);
}
.lb2-head-sub{
  font-size:.58rem;letter-spacing:.18em;color:rgba(238,240,255,.55);
  font-family:'Rajdhani',sans-serif;font-weight:600;margin-top:4px;
}
.lb2-close-btn{
  background:rgba(238,240,255,.04);border:1px solid rgba(238,240,255,.08);
  color:rgba(238,240,255,.4);font-size:.9rem;
  cursor:pointer;padding:7px 11px;
  clip-path:polygon(5px 0%,100% 0%,calc(100% - 5px) 100%,0% 100%);
  font-family:'Rajdhani',sans-serif;font-weight:700;
  transition:all .2s;flex-shrink:0;
}
.lb2-close-btn:hover{background:rgba(255,77,77,.12);border-color:rgba(255,77,77,.3);color:#ff8888}

.lb2-col-head{
  display:grid;grid-template-columns:40px 1fr 58px 70px 16px;
  padding:8px 14px;flex-shrink:0;
  font-size:.52rem;letter-spacing:.26em;color:rgba(238,240,255,.48);
  font-family:'Rajdhani',sans-serif;font-weight:700;text-transform:uppercase;
  border-bottom:1px solid rgba(238,240,255,.05);
}

.lb2-list{flex:1;overflow-y:auto;padding:5px 7px}
.lb2-list::-webkit-scrollbar{width:2px}
.lb2-list::-webkit-scrollbar-thumb{background:rgba(245,200,66,.25);border-radius:2px}

.lb2-row{
  display:grid;grid-template-columns:40px 1fr 58px 70px 16px;
  align-items:center;gap:0;
  padding:8px 7px;margin-bottom:2px;
  border:1px solid transparent;
  transition:all .22s;cursor:pointer;position:relative;
  clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%);
  border-radius:0;
}
.lb2-row:hover{background:rgba(255,255,255,.04);border-color:rgba(238,240,255,.1)}
.lb2-row-active{background:rgba(245,200,66,.06)!important;border-color:rgba(245,200,66,.25)!important}
.lb2-row-active::before{content:'';position:absolute;left:0;top:0;bottom:0;width:2.5px;background:#f5c842;border-radius:0}
.lb2-me{background:rgba(77,166,255,.05);border-color:rgba(77,166,255,.12)}
.lb2-me.lb2-row-active{background:rgba(77,166,255,.1)!important;border-color:rgba(77,166,255,.3)!important}

/* top 3 rows */
.lb2-gold .lb2-r-pos .lb2-pos-num{color:#ffd700;text-shadow:0 0 14px rgba(255,215,0,.7)}
.lb2-silver .lb2-r-pos .lb2-pos-num{color:#d0d0d0;text-shadow:0 0 12px rgba(192,192,192,.6)}
.lb2-bronze .lb2-r-pos .lb2-pos-num{color:#e07832;text-shadow:0 0 12px rgba(205,127,50,.6)}

.lb2-r-pos{
  display:flex;flex-direction:column;align-items:center;gap:2px;
  position:relative;flex-shrink:0;
}
.lb2-pos-num{
  font-family:'Bebas Neue',sans-serif;font-size:1.05rem;letter-spacing:.06em;
  color:rgba(238,240,255,.55);line-height:1;
}
.lb2-pos-glow{
  width:18px;height:1.5px;border-radius:2px;opacity:.6;
  animation:lb2-pglow 1.5s ease-in-out infinite alternate;
}
@keyframes lb2-pglow{from{opacity:.3}to{opacity:.8;transform:scaleX(1.3)}}

.lb2-r-player{display:flex;align-items:center;gap:8px;min-width:0;padding:0 4px}
.lb2-r-av{
  width:30px;height:30px;font-size:15px;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;
  border:1px solid;
  clip-path:polygon(4px 0%,100% 0%,calc(100% - 4px) 100%,0% 100%);
  background:rgba(255,255,255,.04);
  transition:all .2s;
}
.lb2-row:hover .lb2-r-av{transform:scale(1.08)}
.lb2-r-info{min-width:0;flex:1}
.lb2-r-name{
  font-family:'Russo One',sans-serif;font-size:.64rem;letter-spacing:.05em;
  color:#eef0ff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
  line-height:1.2;
}
.lb2-r-name-me{color:#4da6ff}
.lb2-r-tier{font-size:.5rem;letter-spacing:.1em;font-family:'Rajdhani',sans-serif;font-weight:700;margin-top:2px}
.lb2-you-badge{
  font-size:.42rem;letter-spacing:.15em;color:#4da6ff;font-family:'Rajdhani',sans-serif;
  font-weight:700;background:rgba(77,166,255,.1);border:1px solid rgba(77,166,255,.25);
  padding:1px 5px;clip-path:polygon(3px 0%,100% 0%,calc(100% - 3px) 100%,0% 100%);
  flex-shrink:0;
}
.lb2-r-rating{display:flex;align-items:center}
.lb2-r-rating-val{
  font-family:'Bebas Neue',sans-serif;font-size:.9rem;letter-spacing:.04em;
  color:#f5c842;
}
.lb2-r-wl{display:flex;align-items:center;gap:3px;font-family:'Bebas Neue',sans-serif;font-size:.78rem}
.lb2-r-w{color:#7dff4d}.lb2-r-sep{color:rgba(238,240,255,.15)}.lb2-r-l{color:#ff6b6b}
.lb2-r-arrow{
  color:rgba(238,240,255,.18);font-size:.85rem;
  transition:all .2s;
}
.lb2-row:hover .lb2-r-arrow{color:rgba(238,240,255,.5);transform:translateX(2px)}
.lb2-row-active .lb2-r-arrow{color:#f5c842}

.lb2-foot{
  padding:11px 16px;border-top:1px solid rgba(238,240,255,.05);flex-shrink:0;
  background:linear-gradient(0deg,rgba(255,255,255,.02),transparent);
}
.lb2-foot-rank{
  font-size:.6rem;letter-spacing:.1em;color:rgba(238,240,255,.5);
  font-family:'Rajdhani',sans-serif;font-weight:600;text-align:center;
}

/* ── RIGHT PANEL ── */
.lb2-right{
  overflow-y:auto;display:flex;flex-direction:column;
  background:rgba(255,255,255,.008);
}
.lb2-right::-webkit-scrollbar{width:2px}
.lb2-right::-webkit-scrollbar-thumb{background:rgba(77,166,255,.2);border-radius:2px}

/* Empty state */
.lb2-empty{
  flex:1;display:flex;flex-direction:column;
  align-items:center;justify-content:center;
  gap:12px;padding:40px 30px;text-align:center;
  opacity:.4;
}
.lb2-empty-icon{font-size:2.5rem;animation:lb2-bounce 2s ease-in-out infinite}
@keyframes lb2-bounce{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
.lb2-empty-title{font-family:'Russo One',sans-serif;font-size:.82rem;letter-spacing:.18em;color:#eef0ff}
.lb2-empty-sub{font-size:.62rem;color:rgba(238,240,255,.65);letter-spacing:.06em;line-height:1.6;max-width:180px}

/* Profile view */
.lb2-profile{padding:0 0 20px;display:flex;flex-direction:column}

.lb2-prof-hero{
  padding:24px 22px 18px;
  border-bottom:1px solid;
  position:relative;overflow:hidden;
  display:flex;flex-direction:column;align-items:center;text-align:center;gap:8px;
  background:linear-gradient(180deg,rgba(255,255,255,.03),transparent);
}
.lb2-prof-hero::before{
  content:'';position:absolute;inset:0;pointer-events:none;
  background:radial-gradient(ellipse at 50% 0%,var(--prof-col,#f5c842) 0%,transparent 65%);
  opacity:.07;
}
.lb2-prof-pos-tag{
  font-family:'Rajdhani',sans-serif;font-size:.55rem;font-weight:700;
  letter-spacing:.28em;text-transform:uppercase;
  padding:3px 12px;border:1px solid;
  clip-path:polygon(5px 0%,100% 0%,calc(100% - 5px) 100%,0% 100%);
  margin-bottom:4px;
}
.lb2-prof-av-wrap{position:relative;margin:2px 0}
.lb2-prof-av{
  width:72px;height:72px;font-size:34px;
  display:flex;align-items:center;justify-content:center;
  position:relative;z-index:1;
  background:rgba(255,255,255,.04);
  clip-path:polygon(10px 0%,100% 0%,calc(100% - 10px) 100%,0% 100%);
}
.lb2-prof-av-ring{
  position:absolute;inset:-4px;
  clip-path:polygon(14px 0%,100% 0%,calc(100% - 14px) 100%,0% 100%);
  border:1.5px solid;
  animation:lb2-ring-pulse 2s ease-in-out infinite;
}
@keyframes lb2-ring-pulse{0%,100%{opacity:.6}50%{opacity:1}}
.lb2-prof-name{
  font-family:'Russo One',sans-serif;font-size:1rem;letter-spacing:.1em;
  line-height:1;margin-top:4px;
}
.lb2-prof-id{
  font-size:.58rem;letter-spacing:.1em;color:rgba(238,240,255,.55);
  font-family:'Rajdhani',sans-serif;margin-top:-4px;
}
.lb2-prof-tier-badge{
  font-family:'Russo One',sans-serif;font-size:.62rem;letter-spacing:.15em;
  padding:4px 14px;border:1px solid;
  clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%);
  margin-top:2px;
}

/* Stats grid */
.lb2-prof-stats{
  display:grid;grid-template-columns:repeat(3,1fr);gap:8px;
  padding:14px 16px 0;
}
.lb2-stat-card{
  background:rgba(255,255,255,.03);border:1px solid rgba(238,240,255,.07);
  padding:10px 10px 8px;text-align:center;
  clip-path:polygon(5px 0%,100% 0%,calc(100% - 5px) 100%,0% 100%);
}
.lb2-stat-label{
  font-size:.5rem;letter-spacing:.22em;color:rgba(238,240,255,.55);
  font-family:'Rajdhani',sans-serif;font-weight:700;text-transform:uppercase;margin-bottom:5px;
}
.lb2-stat-val{
  font-family:'Bebas Neue',sans-serif;font-size:1.25rem;letter-spacing:.06em;line-height:1;
}

/* W/D/L */
.lb2-wdl-section{padding:14px 16px 0}
.lb2-wdl-label-row{display:flex;justify-content:space-between;margin-bottom:7px}
.lb2-wdl-tag{
  font-family:'Bebas Neue',sans-serif;font-size:.78rem;letter-spacing:.1em;
}
.wdl-w{color:#7dff4d}.wdl-d{color:rgba(238,240,255,.4)}.wdl-l{color:#ff6b6b}
.lb2-wdl-bar{
  display:flex;height:5px;
  background:rgba(238,240,255,.06);overflow:hidden;
  clip-path:polygon(3px 0%,100% 0%,calc(100% - 3px) 100%,0% 100%);
}
.lb2-wdl-seg{height:100%;width:0%;transition:width .7s cubic-bezier(.34,1.2,.64,1)}
.wdl-seg-w{background:#7dff4d;box-shadow:0 0 8px #7dff4d88}
.wdl-seg-d{background:rgba(238,240,255,.25)}
.wdl-seg-l{background:#ff6b6b;box-shadow:0 0 8px #ff6b6b55}

/* Tier progress */
.lb2-tier-progress{padding:14px 16px 0}
.lb2-tp-label{
  display:flex;justify-content:space-between;align-items:baseline;margin-bottom:6px;
}
.lb2-tp-label span:first-child{
  font-family:'Rajdhani',sans-serif;font-size:.65rem;font-weight:700;letter-spacing:.1em;
}
.lb2-tp-next{
  font-size:.58rem;color:rgba(238,240,255,.55);letter-spacing:.08em;
  font-family:'Rajdhani',sans-serif;font-weight:600;
}
.lb2-tp-track{
  height:3.5px;background:rgba(238,240,255,.07);
  position:relative;overflow:hidden;
  clip-path:polygon(2px 0%,100% 0%,calc(100% - 2px) 100%,0% 100%);
}
.lb2-tp-fill{height:100%;width:0%;transition:width .9s cubic-bezier(.34,1.56,.64,1)}

/* Actions */
.lb2-prof-actions{
  display:flex;gap:8px;padding:16px 16px 0;
}
.lb2-act-btn{
  flex:1;padding:11px 8px;
  font-family:'Rajdhani',sans-serif;font-size:.7rem;font-weight:700;
  letter-spacing:.15em;text-transform:uppercase;
  cursor:pointer;border:1px solid;transition:all .25s;
  clip-path:polygon(7px 0%,100% 0%,calc(100% - 7px) 100%,0% 100%);
  display:flex;align-items:center;justify-content:center;gap:7px;
  text-decoration:none;
}
.lb2-act-profile{
  background:rgba(255,255,255,.04);border-color:rgba(238,240,255,.12);
  color:rgba(238,240,255,.6);
}
.lb2-act-profile:hover{background:rgba(77,166,255,.1);border-color:rgba(77,166,255,.35);color:#90c4ff}
.lb2-act-challenge{
  background:rgba(255,77,77,.08);border-color:rgba(255,77,77,.3);color:#ff8080;
}
.lb2-act-challenge:hover{background:rgba(255,77,77,.2);border-color:rgba(255,77,77,.55);color:#ffaaaa;transform:translateY(-2px)}

.lb2-me-note{
  margin:14px 16px 0;padding:10px;
  background:rgba(77,166,255,.06);border:1px solid rgba(77,166,255,.18);
  font-family:'Rajdhani',sans-serif;font-size:.62rem;letter-spacing:.2em;
  color:rgba(77,166,255,.7);text-align:center;
  clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%);
  justify-content:center;
}

/* ── RANK DETAIL MODAL EXTRAS ── */
.rank-modal-hero{
  text-align:center;padding:20px 0 14px;
  border-bottom:1px solid var(--border);margin-bottom:16px;
}
.rank-hero-icon{font-size:3rem;display:block;margin-bottom:6px}
.rank-hero-name{
  font-family:'Bebas Neue',sans-serif;font-size:2rem;letter-spacing:.2em;
  text-shadow:0 0 30px var(--rg);
}
.rank-hero-rating{
  font-family:'Bebas Neue',sans-serif;font-size:1rem;color:var(--muted);
  letter-spacing:.12em;margin-top:2px;
}
.rank-hero-desc{
  font-size:.65rem;font-style:italic;color:rgba(238,240,255,.35);
  letter-spacing:.1em;margin-top:6px;
}
.peak-box{
  display:flex;align-items:center;gap:12px;
  background:rgba(255,215,0,.06);border:1px solid rgba(255,215,0,.2);
  padding:12px 16px;margin-bottom:16px;
  clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%);
}
.peak-label{font-size:.55rem;letter-spacing:.2em;text-transform:uppercase;color:rgba(255,215,0,.55);font-weight:700;margin-bottom:2px}
.peak-val{font-family:'Russo One',sans-serif;font-size:.75rem;letter-spacing:.08em}
.section-label{
  font-family:'Russo One',sans-serif;font-size:.58rem;letter-spacing:.28em;
  text-transform:uppercase;color:var(--muted);margin-bottom:10px;
  display:flex;align-items:center;gap:8px;
}
.section-label::after{content:'';flex:1;height:1px;background:var(--border)}
.rank-tier-row{
  display:flex;gap:10px;align-items:center;padding:10px 12px;
  border:1px solid transparent;margin-bottom:4px;transition:all .2s;
  clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%);
}
.rank-tier-row.rt-active{border-color:var(--rc);background:rgba(255,255,255,.04);}
.rank-tier-row.rt-peak{border-style:dashed;}
.rt-icon{font-size:1.3rem;width:30px;text-align:center;flex-shrink:0}
.rt-info{flex:1;min-width:0}
.rt-name{font-family:'Russo One',sans-serif;font-size:.72rem;letter-spacing:.12em}
.rt-req{font-size:.6rem;color:var(--muted);letter-spacing:.06em;margin-top:2px}
.rt-desc{font-size:.6rem;font-style:italic;color:rgba(238,240,255,.3);margin-top:1px}
.rt-badge-group{display:flex;flex-direction:column;align-items:flex-end;gap:3px;flex-shrink:0}
.rt-status-badge{
  font-size:.48rem;letter-spacing:.12em;padding:2px 7px;font-weight:700;
  font-family:'Rajdhani',sans-serif;text-transform:uppercase;
  clip-path:polygon(3px 0%,100% 0%,calc(100% - 3px) 100%,0% 100%);border:1px solid;
}
/* ── LEADERBOARD CTA BUTTON ── */
.lb-cta-panel{
  position:relative;overflow:hidden;
  display:grid;grid-template-columns:1fr auto auto;gap:0;
  align-items:center;
  background:linear-gradient(135deg,rgba(255,255,255,.038) 0%,rgba(255,255,255,.018) 100%);
  border:1px solid var(--rc);
  padding:0;
  cursor:pointer;
  transition:all .32s cubic-bezier(.34,1.2,.64,1);
  clip-path:polygon(12px 0%,100% 0%,calc(100% - 12px) 100%,0% 100%);
  box-shadow:0 0 30px var(--rg),inset 0 0 60px rgba(255,255,255,.012);
}
.lb-cta-panel:hover{
  transform:translateY(-4px) scale(1.012);
  box-shadow:0 0 55px var(--rg),0 12px 40px rgba(0,0,0,.45),inset 0 0 80px rgba(255,255,255,.025);
  border-color:rgba(238,240,255,.35);
}
.lb-cta-panel:active{transform:translateY(-1px) scale(1.005)}
.lb-cta-topbar{position:absolute;top:0;left:0;right:0;height:1.5px;opacity:.55;pointer-events:none}
.lb-cta-shimmer{
  position:absolute;top:0;left:-150%;width:80%;height:100%;pointer-events:none;
  background:linear-gradient(105deg,transparent 20%,rgba(255,255,255,.04) 50%,transparent 80%);
  transform:skewX(-20deg);
  animation:lb-shimmer 3.5s ease-in-out infinite;
}
@keyframes lb-shimmer{0%,100%{left:-150%}60%,100%{left:160%}}

/* Left section */
.lb-cta-left{
  padding:18px 16px 18px 22px;
  display:flex;flex-direction:column;gap:12px;
  border-right:1px solid var(--border);
}
.lb-cta-rank-badge{display:flex;align-items:center;gap:11px}
.lb-cta-rank-icon{
  font-size:2rem;
  filter:drop-shadow(0 0 14px var(--rg));
  animation:lb-icon-pulse 2.5s ease-in-out infinite;
}
@keyframes lb-icon-pulse{0%,100%{filter:drop-shadow(0 0 10px var(--rg))}50%{filter:drop-shadow(0 0 22px var(--rg))}}
.lb-cta-rank-name{
  font-family:'Russo One',sans-serif;font-size:.88rem;letter-spacing:.2em;
  color:var(--rc);text-transform:uppercase;line-height:1;
}
.lb-cta-rank-pts{
  font-family:'Bebas Neue',sans-serif;font-size:.78rem;letter-spacing:.1em;
  color:var(--muted);margin-top:3px;
}

.lb-cta-prog-wrap{display:flex;flex-direction:column;gap:5px}
.lb-cta-prog-track{
  height:3px;background:rgba(238,240,255,.1);
  position:relative;overflow:visible;
}
.lb-cta-prog-fill{
  height:100%;
  background:linear-gradient(90deg,var(--rc),rgba(238,240,255,.55));
  box-shadow:0 0 10px var(--rg);
  transition:width 1.2s cubic-bezier(.34,1.56,.64,1);
  position:relative;
}
.lb-cta-prog-dot{
  position:absolute;top:50%;right:0;transform:translate(50%,-50%);
  width:7px;height:7px;border-radius:50%;
  background:var(--rc);box-shadow:0 0 8px var(--rg);
  animation:pglow .9s ease-in-out infinite alternate;
}
.lb-cta-prog-label{
  font-size:.6rem;color:var(--muted);letter-spacing:.1em;font-weight:600;
  font-family:'Rajdhani',sans-serif;
}

/* Center: tier mini-map */
.lb-cta-tiers{
  display:flex;flex-direction:column;gap:3px;
  padding:14px 14px;align-items:center;
  border-right:1px solid var(--border);
}
.lb-mini-tier{
  width:22px;height:8px;
  clip-path:polygon(3px 0%,100% 0%,calc(100% - 3px) 100%,0% 100%);
  border:1px solid var(--tc);
  transition:all .2s;
  display:flex;align-items:center;justify-content:center;
  position:relative;overflow:hidden;
}
.lb-mini-tier .lmt-icon{display:none}
.lmt-active{
  background:var(--tc);height:14px;width:26px;
  box-shadow:0 0 12px var(--tc);
}
.lmt-reached{background:rgba(255,255,255,.06)}
.lmt-locked{opacity:.22;border-style:dashed}

/* Right: CTA */
.lb-cta-right{
  display:flex;flex-direction:column;align-items:center;
  gap:10px;padding:16px 18px;
}
.lb-cta-rank-no{text-align:center}
.lb-cta-rank-label{
  display:block;font-size:.48rem;letter-spacing:.28em;
  color:var(--muted);font-family:'Rajdhani',sans-serif;font-weight:700;
  text-transform:uppercase;margin-bottom:2px;
}
.lb-cta-rank-num{
  display:block;font-family:'Bebas Neue',sans-serif;font-size:1.55rem;
  letter-spacing:.08em;color:#f5c842;
  text-shadow:0 0 20px rgba(245,200,66,.45);
  line-height:1;
}
.lb-cta-arrow-wrap{position:relative}
.lb-cta-arrow-ring{
  width:46px;height:46px;border-radius:50%;
  border:1.5px solid var(--rc);
  display:flex;align-items:center;justify-content:center;
  background:rgba(255,255,255,.05);
  box-shadow:0 0 18px var(--rg);
  position:relative;overflow:hidden;
  transition:all .28s;
}
.lb-cta-panel:hover .lb-cta-arrow-ring{
  background:var(--rc);box-shadow:0 0 30px var(--rg);
  transform:scale(1.1);
}
.lb-cta-arrow-txt{
  font-family:'Russo One',sans-serif;font-size:.42rem;letter-spacing:.2em;
  color:var(--rc);text-align:center;line-height:1.3;
  font-weight:700;
}
.lb-cta-panel:hover .lb-cta-arrow-txt{color:#fff}

/* ── TOAST ── */
.toast{
  position:fixed;bottom:34px;left:50%;transform:translateX(-50%) translateY(24px);
  z-index:200;background:rgba(5,6,13,.96);border:1px solid rgba(238,240,255,.1);
  padding:11px 28px;font-family:'Rajdhani',sans-serif;font-size:.85rem;font-weight:700;
  color:var(--text);letter-spacing:.07em;backdrop-filter:blur(16px);
  box-shadow:0 8px 40px rgba(0,0,0,.7);opacity:0;pointer-events:none;
  transition:opacity .3s,transform .3s;
  clip-path:polygon(12px 0%,100% 0%,calc(100% - 12px) 100%,0% 100%)}
.toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
.toast.t-green{border-color:rgba(125,255,77,.35);color:var(--scissors)}
.toast.t-red{border-color:rgba(255,77,77,.35);color:var(--rock)}

/* ══════════════════════════════════════════════
   LOBBY CHAT MODAL
══════════════════════════════════════════════ */
.chat-shell{
  position:relative;
  display:grid;grid-template-rows:auto auto 1fr;
  width:min(96vw,820px);height:min(88vh,580px);
  background:linear-gradient(160deg,rgba(8,10,22,.98),rgba(5,6,13,.99));
  border:1px solid rgba(77,166,255,.18);
  overflow:hidden;
  animation:lb2-in .35s cubic-bezier(.34,1.2,.64,1);
  clip-path:polygon(14px 0%,100% 0%,calc(100% - 14px) 100%,0% 100%);
}
.chat-top-bar{
  height:2px;
  background:linear-gradient(90deg,transparent,rgba(77,166,255,.9),rgba(125,255,77,.6),transparent);
  opacity:.8;
}
.chat-head{
  display:flex;justify-content:space-between;align-items:flex-start;
  padding:16px 22px 14px;
  background:linear-gradient(180deg,rgba(77,166,255,.05),transparent);
  border-bottom:1px solid rgba(77,166,255,.1);
  flex-shrink:0;
}
.chat-head-eyebrow{font-size:.55rem;letter-spacing:.35em;color:rgba(77,166,255,.5);
  font-family:'Rajdhani',sans-serif;font-weight:700;text-transform:uppercase;margin-bottom:4px}
.chat-head-title{font-family:'Bebas Neue',sans-serif;font-size:1.7rem;letter-spacing:.18em;
  color:#4da6ff;line-height:1;text-shadow:0 0 28px rgba(77,166,255,.4)}
.chat-head-sub{font-size:.58rem;letter-spacing:.15em;color:rgba(238,240,255,.3);
  font-family:'Rajdhani',sans-serif;font-weight:600;margin-top:4px}

.chat-head-left{}
.chat-body{
  display:grid;grid-template-columns:200px 1fr;
  overflow:hidden;min-height:0;flex:1;
  height:100%;
}

/* ── Online Players Panel ── */
.chat-online-panel{
  border-right:1px solid rgba(77,166,255,.1);
  display:flex;flex-direction:column;overflow:hidden;
  background:rgba(0,0,0,.15);
}
.chat-panel-title{
  display:flex;align-items:center;gap:7px;
  padding:10px 14px;
  font-family:'Russo One',sans-serif;font-size:.52rem;letter-spacing:.26em;
  color:rgba(125,255,77,.7);text-transform:uppercase;
  border-bottom:1px solid rgba(77,166,255,.08);flex-shrink:0;
}
.cop-dot{width:7px;height:7px;border-radius:50%;background:#7dff4d;
  box-shadow:0 0 8px rgba(125,255,77,.9);animation:blink 1.4s infinite;flex-shrink:0}
.cop-count{
  margin-left:auto;font-family:'Bebas Neue',sans-serif;font-size:1rem;
  color:#7dff4d;letter-spacing:.05em;line-height:1;
}
.chat-online-list{flex:1;overflow-y:auto;padding:6px 8px}
.chat-online-list::-webkit-scrollbar{width:2px}
.chat-online-list::-webkit-scrollbar-thumb{background:rgba(77,166,255,.25)}
.cop-empty{font-size:.65rem;color:rgba(238,240,255,.22);text-align:center;
  padding:20px 8px;letter-spacing:.1em;font-family:'Rajdhani',sans-serif;font-weight:600}
.cop-item{
  display:flex;align-items:center;gap:8px;
  padding:7px 8px;margin-bottom:2px;
  border:1px solid transparent;transition:all .2s;
  clip-path:polygon(5px 0%,100% 0%,calc(100% - 5px) 100%,0% 100%);
  cursor:default;
}
.cop-item:hover{background:rgba(77,166,255,.06);border-color:rgba(77,166,255,.15)}
.cop-item-me{background:rgba(77,166,255,.05);border-color:rgba(77,166,255,.12)}
.cop-av{width:26px;height:26px;font-size:14px;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;
  border:1px solid rgba(77,166,255,.3);background:rgba(77,166,255,.08);
  clip-path:polygon(4px 0%,100% 0%,calc(100% - 4px) 100%,0% 100%)}
.cop-info{min-width:0;flex:1}
.cop-name{font-family:'Russo One',sans-serif;font-size:.58rem;letter-spacing:.05em;
  color:#eef0ff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.3}
.cop-name-me{color:#4da6ff}
.cop-status{font-size:.5rem;color:rgba(125,255,77,.65);letter-spacing:.1em;
  font-family:'Rajdhani',sans-serif;font-weight:700}
.cop-live-dot{width:5px;height:5px;border-radius:50%;background:#7dff4d;
  flex-shrink:0;box-shadow:0 0 5px rgba(125,255,77,.7)}

/* ── Chat Right Panel ── */
.chat-right{display:flex;flex-direction:column;min-height:0;overflow:hidden}
.chat-messages{
  flex:1;overflow-y:auto;padding:14px 16px;
  display:flex;flex-direction:column;gap:8px;
}
.chat-messages::-webkit-scrollbar{width:3px}
.chat-messages::-webkit-scrollbar-thumb{background:rgba(77,166,255,.25);border-radius:2px}

.chat-welcome{text-align:center;padding:22px 16px;opacity:.5}
.chat-welcome-icon{font-size:2rem;margin-bottom:8px}
.chat-welcome-txt{font-family:'Russo One',sans-serif;font-size:.68rem;letter-spacing:.14em;
  color:#eef0ff;text-transform:uppercase;margin-bottom:5px}
.chat-welcome-sub{font-size:.62rem;color:rgba(238,240,255,.4);letter-spacing:.06em;
  font-family:'Rajdhani',sans-serif;font-weight:600}

/* Chat message bubbles */
.chat-msg{display:flex;flex-direction:column;gap:2px;max-width:88%;align-self:flex-start}
.chat-msg-me{align-self:flex-end;align-items:flex-end}
.chat-msg-head{display:flex;align-items:center;gap:6px}
.chat-msg-me .chat-msg-head{flex-direction:row-reverse}
.chat-msg-av{width:20px;height:20px;font-size:11px;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;
  border:1px solid rgba(77,166,255,.3);background:rgba(77,166,255,.08);
  clip-path:polygon(3px 0%,100% 0%,calc(100% - 3px) 100%,0% 100%)}
.chat-msg-name{font-family:'Russo One',sans-serif;font-size:.52rem;letter-spacing:.08em;
  color:rgba(77,166,255,.85)}
.chat-msg-name-me{color:rgba(125,255,77,.85)}
.chat-msg-time{font-size:.46rem;color:rgba(238,240,255,.25);letter-spacing:.06em;
  font-family:'Rajdhani',sans-serif}
.chat-msg-bubble{
  padding:7px 12px;
  background:rgba(77,166,255,.07);
  border:1px solid rgba(77,166,255,.15);
  font-family:'Rajdhani',sans-serif;font-size:.75rem;font-weight:600;
  color:rgba(238,240,255,.88);line-height:1.45;letter-spacing:.03em;
  clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%);
  word-break:break-word;
}
.chat-msg-me .chat-msg-bubble{
  background:rgba(125,255,77,.07);border-color:rgba(125,255,77,.18);
}
.chat-msg-system{
  align-self:center;
  font-size:.58rem;color:rgba(238,240,255,.28);letter-spacing:.12em;
  font-family:'Rajdhani',sans-serif;font-weight:700;text-transform:uppercase;
  padding:3px 12px;border:1px solid rgba(238,240,255,.06);
  background:rgba(255,255,255,.02);
  clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%);
}

/* Input area */
.chat-input-wrap{
  display:flex;gap:0;
  padding:12px 14px 14px;
  border-top:1px solid rgba(77,166,255,.1);
  background:rgba(0,0,0,.2);flex-shrink:0;
}
.chat-input{
  flex:1;background:rgba(77,166,255,.06);
  border:1px solid rgba(77,166,255,.25);border-right:none;
  color:#eef0ff;font-family:'Rajdhani',sans-serif;font-size:.8rem;font-weight:600;
  padding:10px 14px;letter-spacing:.04em;outline:none;transition:all .22s;
  clip-path:polygon(8px 0%,100% 0%,100% 100%,0% 100%);
}
.chat-input:focus{background:rgba(77,166,255,.1);border-color:rgba(77,166,255,.5);
  box-shadow:0 0 16px rgba(77,166,255,.12)}
.chat-input::placeholder{color:rgba(238,240,255,.2);font-weight:500}
.chat-send-btn{
  background:rgba(77,166,255,.15);border:1px solid rgba(77,166,255,.4);
  color:#4da6ff;font-family:'Russo One',sans-serif;font-size:.6rem;letter-spacing:.18em;
  padding:10px 18px;cursor:pointer;transition:all .22s;
  display:flex;align-items:center;gap:6px;flex-shrink:0;
  clip-path:polygon(0 0%,100% 0%,calc(100% - 8px) 100%,0% 100%);
}
.chat-send-btn:hover{background:rgba(77,166,255,.3);border-color:rgba(77,166,255,.7);
  color:#90d4ff;box-shadow:0 0 18px rgba(77,166,255,.25)}
.chat-send-arrow{font-size:.8rem}

@media(max-width:600px){
  .chat-body{grid-template-columns:1fr;grid-template-rows:160px 1fr}
  .chat-online-panel{border-right:none;border-bottom:1px solid rgba(77,166,255,.1)}
  .chat-shell{clip-path:none}
}

/* ══ MOBILE RESPONSIVE ══ */
@media(max-width:480px){
  html,body{overflow-y:auto}
  .pbar{padding:8px 12px}
  .pstats,.rank-pill{display:none}
  .pav{width:34px;height:34px;font-size:16px}
  .pname{font-size:.65rem}
  .pid,.phint{display:none}
  .btn-out{padding:6px 10px;font-size:.6rem}
  .corner{display:none}
  /* stage layout */
  .stage-pvp{
    position:relative;min-height:100dvh;
    padding:70px 10px 24px;
    overflow-y:auto;justify-content:flex-start;
    gap:.7rem;
  }
  /* chat */
  .chat-shell{
    width:100%;max-width:100%;clip-path:none;border-radius:12px;
  }
  .chat-body{grid-template-columns:1fr;grid-template-rows:120px 1fr}
  .chat-online-panel{border-right:none;border-bottom:1px solid rgba(77,166,255,.1);padding:10px}
  .chat-msg-area{max-height:200px}
  .chat-input-field{font-size:16px} /* prevent iOS zoom */
  /* matchmaking */
  .mm-shell,.queue-shell{width:100%;clip-path:none;border-radius:12px}
  .mm-title{font-size:1.4rem}
  .btn-find-match,.btn-cancel-queue{padding:14px;font-size:.75rem;clip-path:none;border-radius:8px}
}
@media(max-width:360px){
  .stage-pvp{padding-left:8px;padding-right:8px}
}

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
  --dark:#f0f4fc;--mid:#e4e8f4;--card:rgba(255,255,255,.85);
  --text:#1a1d2e;--muted:rgba(26,29,46,.55);--border:rgba(0,0,0,.11);
  --rock:#c0200f;--paper:#1a5fa8;--scissors:#0f7a30;
  --gr:rgba(192,32,15,.4);--gp:rgba(26,95,168,.4);--gs:rgba(15,122,48,.4);
  --color-grandmaster:#b45309;
  --color-master:#7c3aed;
  --color-diamond:#1060b0;
  --color-platinum:#047857;
  --color-gold:#92400e;
  --color-silver:#4b5563;
  --color-bronze:#78350f;
  --glow-grandmaster:rgba(180,83,9,.3);
  --glow-master:rgba(124,58,237,.3);
  --glow-diamond:rgba(16,96,176,.3);
  --glow-platinum:rgba(4,120,87,.3);
  --glow-gold:rgba(146,64,14,.3);
  --glow-silver:rgba(75,85,99,.3);
  --glow-bronze:rgba(120,53,15,.3);
  --win:#1a9940;--lose:#d93030;--draw:#5577aa;--gold:#c8a000;
  --prog-end:rgba(26,29,46,.4);
  --rg:rgba(0,0,0,0.04) !important;
}
[data-theme="light"] html,[data-theme="light"] body{background:#eef2fa;color:var(--text);}
[data-theme="light"] canvas#bg{opacity:.08;}
[data-theme="light"] .hex-layer{opacity:.010;filter:invert(1);}
[data-theme="light"] .noise{opacity:.008;}
[data-theme="light"] .elines{opacity:.15;}
[data-theme="light"] .scanline{opacity:.015;}
[data-theme="light"] .vignette{background:radial-gradient(ellipse at center,transparent 60%,rgba(0,0,0,.03) 100%);}
[data-theme="light"] .corner::before,[data-theme="light"] .corner::after{background:rgba(26,95,168,.2);}

/* ── Player Bar ── */
[data-theme="light"] .pbar{
  background:linear-gradient(180deg,rgba(238,242,250,.98) 0%,rgba(235,240,250,.90) 100%);
  border-bottom-color:rgba(26,95,168,.14);
}
[data-theme="light"] .pinfo:hover{background:rgba(26,95,168,.06);border-color:rgba(26,95,168,.18);}
[data-theme="light"] .pname{color:#1a1d2e !important;font-weight:700;}
[data-theme="light"] .pid{color:rgba(26,29,46,.58) !important;}
[data-theme="light"] .phint{color:rgba(26,95,168,.88) !important;}

/* ── Navigation ── */
[data-theme="light"] .btn-back{
  background:transparent;border-color:rgba(26,95,168,.22);color:rgba(26,95,168,.88);
}
[data-theme="light"] .btn-back:hover{background:rgba(26,95,168,.09);border-color:rgba(26,95,168,.42);color:#1a5fa8;}
[data-theme="light"] .btn-theme-toggle{background:transparent !important;border-color:rgba(26,95,168,.22) !important;color:rgba(26,95,168,.88) !important;}
[data-theme="light"] .btn-theme-toggle:hover{background:rgba(26,95,168,.09) !important;border-color:rgba(26,95,168,.42) !important;color:#1a5fa8 !important;}

/* ── Rank Pill ── */
[data-theme="light"] .rank-pill{
  background:linear-gradient(135deg,rgba(255,255,255,.9),rgba(235,240,250,.95));
  border-color:rgba(0,0,0,.12);
  box-shadow:0 2px 8px rgba(0,0,0,.06);
}
[data-theme="light"] .rank-pts{color:rgba(26,29,46,.52);}
[data-theme="light"] .rank-name-lbl{text-shadow:none;}

/* ── Panel Base ── */
[data-theme="light"] .panel{
  background:rgba(255,255,255,.88);
  border-color:rgba(0,0,0,.09);
  box-shadow:0 2px 14px rgba(0,0,0,.06);
}
[data-theme="light"] .panel::before{opacity:.1;}
[data-theme="light"] .panel-title{color:rgba(26,29,46,.42);}

/* ── Player Identity ── */
[data-theme="light"] .id-avatar{
  background:linear-gradient(135deg,rgba(26,95,168,.1),rgba(15,122,48,.07));
  box-shadow:none;
}
[data-theme="light"] .id-name{color:#1a1d2e;}
[data-theme="light"] .id-pid{color:rgba(26,29,46,.55);}
/* Override inline style for stat values */
[data-theme="light"] .id-stat-val{color:#1a1d2e !important;}
[data-theme="light"] .id-stat-lbl{color:rgba(26,29,46,.50);}
/* Re-apply semantic colors in light mode — Win=green, Loss=red, Draw=blue-grey, WinRate=amber */
[data-theme="light"] .id-stats .id-stat:nth-child(1) .id-stat-val{color:#0f7a30 !important;}
[data-theme="light"] .id-stats .id-stat:nth-child(2) .id-stat-val{color:#c0200f !important;}
[data-theme="light"] .id-stats .id-stat:nth-child(3) .id-stat-val{color:#1a5fa8 !important;}
[data-theme="light"] .id-stats .id-stat:nth-child(4) .id-stat-val{color:#9a6500 !important;}
[data-theme="light"] .id-rank-box{
  background:linear-gradient(135deg,rgba(255,255,255,.85),rgba(235,240,250,.92));
  border-color:var(--rc) !important;
  box-shadow:0 2px 8px rgba(0,0,0,.06);
}
[data-theme="light"] .id-rank-name{text-shadow:none;}
[data-theme="light"] .id-rank-rating{color:rgba(26,29,46,.50);}
/* TAP DETAIL text & id-rank-tap */
[data-theme="light"] .id-rank-tap{color:rgba(26,29,46,.35);}
[data-theme="light"] .id-rank-box span[style]{color:rgba(26,29,46,.38) !important;}

/* ── LB CTA Panel (Rank Progress) ── */
[data-theme="light"] .lb-cta-panel{
  background:rgba(255,255,255,.9);
  border-color:var(--rc);
  box-shadow:0 3px 16px rgba(0,0,0,.08);
}
[data-theme="light"] .lb-cta-panel:hover{
  border-color:rgba(26,29,46,.35);
  box-shadow:0 6px 22px rgba(0,0,0,.10);
  transform:translateY(-3px) scale(1.008);
}
[data-theme="light"] .lb-cta-shimmer{display:none;}
[data-theme="light"] .lb-cta-rank-name{color:var(--rc);text-shadow:none;}
[data-theme="light"] .lb-cta-rank-pts{color:rgba(26,29,46,.52);}
[data-theme="light"] .lb-cta-prog-track{background:rgba(0,0,0,.08);}
[data-theme="light"] .lb-cta-prog-fill{
  background:linear-gradient(90deg,var(--rc),rgba(26,29,46,.35)) !important;
  box-shadow:none !important;
}
[data-theme="light"] .lb-cta-prog-dot{box-shadow:none;}
[data-theme="light"] .lb-cta-prog-label{color:rgba(26,29,46,.6);}
[data-theme="light"] .lb-cta-left{border-right-color:rgba(0,0,0,.08);}
[data-theme="light"] .lb-cta-tiers{border-right-color:rgba(0,0,0,.08);}
[data-theme="light"] .lb-mini-tier{--tc:rgba(26,29,46,.14);border-color:var(--tc);}
[data-theme="light"] .lmt-active{background:var(--rc) !important;box-shadow:none;}
[data-theme="light"] .lmt-reached{background:rgba(0,0,0,.06);}
[data-theme="light"] .lmt-locked{opacity:.2;}
[data-theme="light"] .lb-cta-rank-num{color:var(--color-gold);text-shadow:none;}
[data-theme="light"] .lb-cta-rank-label{color:rgba(26,29,46,.48);}
/* LIHAT button */
[data-theme="light"] .lb-cta-arrow-ring{
  background:rgba(0,0,0,.04) !important;
  border-color:var(--rc) !important;
  box-shadow:0 2px 8px rgba(0,0,0,.08) !important;
}
[data-theme="light"] .lb-cta-panel:hover .lb-cta-arrow-ring{
  background:var(--rc) !important;
  box-shadow:0 4px 14px rgba(0,0,0,.15) !important;
}
[data-theme="light"] .lb-cta-arrow-txt{color:var(--rc) !important;}
[data-theme="light"] .lb-cta-panel:hover .lb-cta-arrow-txt{color:#fff !important;}
[data-theme="light"] .lb-cta-rank-icon{filter:none !important;animation:none;}

/* ── Rank Progress Bar ── */
[data-theme="light"] .rank-progress-panel .panel{
  background:rgba(255,255,255,.88);
}
[data-theme="light"] .progress-bar{background:rgba(0,0,0,.08);}
[data-theme="light"] .progress-fill{
  background:linear-gradient(90deg,var(--rc),rgba(26,29,46,.35)) !important;
  box-shadow:none !important;
}
[data-theme="light"] .progress-glow{display:none;}
[data-theme="light"] .progress-cur{color:var(--rc);text-shadow:none;}
[data-theme="light"] .progress-next{color:rgba(26,29,46,.58);font-weight:700;}
[data-theme="light"] .tier-badge{opacity:.55;box-shadow:none;}
[data-theme="light"] .tier-badge.active{opacity:1;box-shadow:0 2px 8px rgba(0,0,0,.1);}

/* ── Matchmaking Panel ── */
[data-theme="light"] .mm-shell{
  background:rgba(255,255,255,.88);border-color:rgba(26,95,168,.12);
  box-shadow:0 2px 14px rgba(0,0,0,.06);
}
[data-theme="light"] .queue-shell{
  background:rgba(255,255,255,.88);border-color:rgba(26,95,168,.12);
}
[data-theme="light"] .panel.mm-panel{
  background:rgba(255,255,255,.88);
  border-color:rgba(0,0,0,.09);
  box-shadow:0 2px 14px rgba(0,0,0,.06);
}
[data-theme="light"] .mm-idle-title{color:rgba(26,29,46,.52);}
[data-theme="light"] .mm-idle-sub{color:rgba(26,29,46,.35);}
[data-theme="light"] .mm-idle-icon{opacity:.22;filter:grayscale(.3);}
[data-theme="light"] .mm-search-title{color:#1a5fa8;}
[data-theme="light"] .mm-search-sub{color:rgba(26,29,46,.55);}
[data-theme="light"] .mm-subtitle{color:rgba(26,29,46,.45);}
[data-theme="light"] .queue-status-txt{color:rgba(26,29,46,.5);}
[data-theme="light"] .queue-pill{background:rgba(26,95,168,.07);border-color:rgba(26,95,168,.2);color:#1a5fa8;}
[data-theme="light"] .qdot{background:#1a5fa8;}
/* Radar rings light mode */
[data-theme="light"] .radar::before,[data-theme="light"] .radar::after{border-color:#1a5fa8;}
[data-theme="light"] .radar{border-color:rgba(26,95,168,.18);}
/* Matchmaking section label */
[data-theme="light"] .panel-title{color:rgba(26,29,46,.42);}

/* ── Action Buttons ── */
[data-theme="light"] .xbtn-battle{
  background:rgba(192,32,15,.08) !important;border-color:rgba(192,32,15,.38) !important;color:#c0200f !important;
  box-shadow:none !important;
}
[data-theme="light"] .xbtn-battle:hover{
  background:rgba(192,32,15,.16) !important;border-color:rgba(192,32,15,.58) !important;
  box-shadow:0 4px 14px rgba(192,32,15,.15) !important;
  transform:translateY(-3px) scale(1.02);
}
[data-theme="light"] .xbtn-battle:disabled{opacity:.35;cursor:not-allowed;transform:none !important;box-shadow:none !important;}
[data-theme="light"] .xbtn-lb{
  background:rgba(0,0,0,.04) !important;border-color:rgba(0,0,0,.12) !important;color:rgba(26,29,46,.55) !important;
}
[data-theme="light"] .xbtn-lb:hover{
  background:rgba(26,95,168,.08) !important;border-color:rgba(26,95,168,.3) !important;color:#1a5fa8 !important;
}
[data-theme="light"] .xbtn-chat{
  background:rgba(26,95,168,.07) !important;border-color:rgba(26,95,168,.3) !important;color:#1a5fa8 !important;
}
[data-theme="light"] .xbtn-chat:hover{
  background:rgba(26,95,168,.15) !important;border-color:rgba(26,95,168,.5) !important;color:#1a5fa8 !important;
  box-shadow:0 4px 12px rgba(26,95,168,.15) !important;
}
[data-theme="light"] .xbtn-cancel{
  background:rgba(192,32,15,.06) !important;border-color:rgba(192,32,15,.28) !important;color:#c0200f !important;
}
[data-theme="light"] .xbtn-cancel:hover{
  background:rgba(192,32,15,.14) !important;border-color:rgba(192,32,15,.5) !important;
}
/* Chat online dot light mode */
[data-theme="light"] .chat-online-dot{background:#0f7a30;box-shadow:0 0 7px rgba(15,122,48,.7);}

/* ── Toast ── */
[data-theme="light"] .toast{background:rgba(238,242,250,.97);border-color:rgba(26,95,168,.18);color:#1a1d2e;}
[data-theme="light"] .toast.t-green{border-color:rgba(15,122,48,.35);color:#0f7a30;}
[data-theme="light"] .toast.t-red{border-color:rgba(192,32,15,.35);color:#c0200f;}

/* ── Chat Shell ── */
[data-theme="light"] .chat-shell{
  background:linear-gradient(160deg,rgba(245,247,255,.99),rgba(238,242,250,.99));
  border-color:rgba(26,95,168,.14);
}
[data-theme="light"] .chat-top-bar{background:linear-gradient(90deg,transparent,rgba(26,95,168,.7),rgba(15,122,48,.5),transparent);}
[data-theme="light"] .chat-head{background:rgba(26,95,168,.04);border-bottom-color:rgba(26,95,168,.1);}
[data-theme="light"] .chat-head-eyebrow{color:rgba(26,95,168,.6);}
[data-theme="light"] .chat-head-title{color:#1a5fa8;text-shadow:none;}
[data-theme="light"] .chat-head-sub{color:rgba(26,29,46,.4);}
[data-theme="light"] .chat-online-panel{background:rgba(0,0,0,.025);border-right-color:rgba(26,95,168,.1);}
[data-theme="light"] .chat-panel-title{color:rgba(15,122,48,.8);border-bottom-color:rgba(26,95,168,.08);}
[data-theme="light"] .cop-dot{background:#0f7a30;box-shadow:0 0 8px rgba(15,122,48,.7);}
[data-theme="light"] .cop-count{color:#0f7a30;}
[data-theme="light"] .cop-empty{color:rgba(26,29,46,.3);}
[data-theme="light"] .cop-item:hover{background:rgba(26,95,168,.06);border-color:rgba(26,95,168,.15);}
[data-theme="light"] .cop-item-me{background:rgba(26,95,168,.05);border-color:rgba(26,95,168,.12);}
[data-theme="light"] .cop-av{border-color:rgba(26,95,168,.25);background:rgba(26,95,168,.07);}
[data-theme="light"] .cop-name{color:#1a1d2e;}
[data-theme="light"] .cop-name-me{color:#1a5fa8;}
[data-theme="light"] .cop-status{color:rgba(15,122,48,.7);}
[data-theme="light"] .cop-live-dot{background:#0f7a30;box-shadow:0 0 5px rgba(15,122,48,.6);}
[data-theme="light"] .chat-msg-bubble{background:rgba(26,95,168,.06);border-color:rgba(26,95,168,.14);color:rgba(26,29,46,.85);}
[data-theme="light"] .chat-msg-me .chat-msg-bubble{background:rgba(15,122,48,.06);border-color:rgba(15,122,48,.16);}
[data-theme="light"] .chat-msg-name{color:rgba(26,95,168,.88);}
[data-theme="light"] .chat-msg-name-me{color:rgba(15,122,48,.88);}
[data-theme="light"] .chat-msg-time{color:rgba(26,29,46,.3);}
[data-theme="light"] .chat-msg-system{color:rgba(26,29,46,.35);border-color:rgba(0,0,0,.07);background:rgba(0,0,0,.02);}
[data-theme="light"] .chat-welcome-txt{color:#1a1d2e;}
[data-theme="light"] .chat-welcome-sub{color:rgba(26,29,46,.45);}
[data-theme="light"] .chat-input-wrap{background:rgba(0,0,0,.025);border-top-color:rgba(26,95,168,.1);}
[data-theme="light"] .chat-input{
  background:rgba(26,95,168,.05);border-color:rgba(26,95,168,.2);
  color:#1a1d2e;
}
[data-theme="light"] .chat-input:focus{background:rgba(26,95,168,.08);border-color:rgba(26,95,168,.4);box-shadow:0 0 12px rgba(26,95,168,.08);}
[data-theme="light"] .chat-input::placeholder{color:rgba(26,29,46,.3);}
[data-theme="light"] .chat-send-btn{background:rgba(26,95,168,.1);border-color:rgba(26,95,168,.35);color:#1a5fa8;}
[data-theme="light"] .chat-send-btn:hover{background:rgba(26,95,168,.22);border-color:rgba(26,95,168,.58);box-shadow:0 0 14px rgba(26,95,168,.15);}

/* ── Divider ── */
[data-theme="light"] .vline{background:linear-gradient(to right,transparent,rgba(0,0,0,.1),transparent);}
[data-theme="light"] .vtxt{color:rgba(26,29,46,.4);}

/* ── Modal Box ── */
[data-theme="light"] .modal-box{
  background:linear-gradient(160deg,rgba(245,247,255,.99),rgba(238,242,250,.99));
  border-color:rgba(26,95,168,.1);
}
[data-theme="light"] .modal-head{background:rgba(26,95,168,.03);border-bottom-color:rgba(26,95,168,.1);}
[data-theme="light"] .modal-head-title{color:#1a5fa8;}
[data-theme="light"] .modal-head-sub{color:rgba(26,29,46,.45);}
[data-theme="light"] .btn-close{background:rgba(0,0,0,.03);border-color:rgba(0,0,0,.09);color:rgba(26,29,46,.5);}
[data-theme="light"] .btn-close:hover{background:rgba(192,32,15,.08);border-color:rgba(192,32,15,.3);color:#c0200f;}

/* ── Leaderboard v2 ── */
[data-theme="light"] .lb2-shell{
  background:linear-gradient(160deg,rgba(245,247,255,.99),rgba(238,242,250,.99));
  border-color:rgba(26,95,168,.1);
}
[data-theme="light"] .lb2-head{background:rgba(26,95,168,.03);border-bottom-color:rgba(0,0,0,.07);}
[data-theme="light"] .lb2-head-eyebrow{color:rgba(26,29,46,.65);}
[data-theme="light"] .lb2-head-title{color:var(--color-gold);text-shadow:none;}
[data-theme="light"] .lb2-head-sub{color:rgba(26,29,46,.65);}
[data-theme="light"] .lb2-close-btn{background:rgba(0,0,0,.03);border-color:rgba(0,0,0,.09);color:rgba(26,29,46,.5);}
[data-theme="light"] .lb2-close-btn:hover{background:rgba(192,32,15,.08);border-color:rgba(192,32,15,.3);color:#c0200f;}
[data-theme="light"] .lb2-col-head{color:rgba(26,29,46,.68);border-bottom-color:rgba(0,0,0,.08);}
[data-theme="light"] .lb2-row:hover{background:rgba(26,95,168,.04);border-color:rgba(26,95,168,.12);}
[data-theme="light"] .lb2-row-active{background:rgba(26,95,168,.08) !important;border-color:rgba(26,95,168,.3) !important;}
[data-theme="light"] .lb2-row-active::before{background:#1a5fa8 !important;}
[data-theme="light"] .lb2-me{background:rgba(26,95,168,.05);border-color:rgba(26,95,168,.12);}
[data-theme="light"] .lb2-me.lb2-row-active{background:rgba(26,95,168,.1) !important;border-color:rgba(26,95,168,.3) !important;}
[data-theme="light"] .lb2-pos-num{text-shadow:none !important;color:rgba(26,29,46,.65);}
[data-theme="light"] .lb2-r-name{color:#1a1d2e;}
[data-theme="light"] .lb2-r-name-me{color:#1a5fa8;}
[data-theme="light"] .lb2-r-tier{color:rgba(26,29,46,.68);}
[data-theme="light"] .lb2-r-rating-val{color:var(--color-gold);text-shadow:none;}
[data-theme="light"] .lb2-r-w{color:#0f7a30;}
[data-theme="light"] .lb2-r-l{color:#c0200f;}
[data-theme="light"] .lb2-r-sep{color:rgba(26,29,46,.15);}
[data-theme="light"] .lb2-r-arrow{color:rgba(26,29,46,.25);}
[data-theme="light"] .lb2-you-badge{color:#1a5fa8;background:rgba(26,95,168,.1);border-color:rgba(26,95,168,.25);}
[data-theme="light"] .lb2-foot{border-top-color:rgba(0,0,0,.06);background:rgba(0,0,0,.01);}
[data-theme="light"] .lb2-foot-rank{color:rgba(26,29,46,.7);}
[data-theme="light"] .lb2-right{background:rgba(0,0,0,.01);}
[data-theme="light"] .lb2-empty{color:rgba(26,29,46,.65);opacity:1;}
[data-theme="light"] .lb2-empty-title{color:#1a1d2e;}
[data-theme="light"] .lb2-empty-sub{color:rgba(26,29,46,.7);}
[data-theme="light"] .lb2-prof-hero{background:rgba(26,95,168,.02);}
[data-theme="light"] .lb2-prof-id{color:rgba(26,29,46,.68);}
[data-theme="light"] .lb2-prof-name{color:#1a1d2e;}
[data-theme="light"] .lb2-stat-card{background:rgba(0,0,0,.025);border-color:rgba(0,0,0,.07);}
[data-theme="light"] .lb2-stat-label{color:rgba(26,29,46,.68);}
[data-theme="light"] .lb2-stat-val{color:#1a1d2e;}
[data-theme="light"] .lb2-wdl-bar{background:rgba(0,0,0,.07);}
[data-theme="light"] .wdl-w{color:#0f7a30;}
[data-theme="light"] .wdl-d{color:rgba(26,29,46,.4);}
[data-theme="light"] .wdl-l{color:#c0200f;}
[data-theme="light"] .wdl-seg-w{box-shadow:none;}
[data-theme="light"] .wdl-seg-l{box-shadow:none;}
[data-theme="light"] .wdl-seg-d{opacity:.5;}
[data-theme="light"] .lb2-tp-next{color:rgba(26,29,46,.68);}
[data-theme="light"] .lb2-tp-track{background:rgba(0,0,0,.07);}
[data-theme="light"] .lb2-me-note{background:rgba(26,95,168,.06);border-color:rgba(26,95,168,.2);color:#1a5fa8;}
[data-theme="light"] .lb2-act-profile{background:rgba(0,0,0,.04);border-color:rgba(0,0,0,.09);color:rgba(26,29,46,.68);}
[data-theme="light"] .lb2-act-profile:hover{background:rgba(26,95,168,.08);border-color:rgba(26,95,168,.25);color:#1a5fa8;}
[data-theme="light"] .lb2-act-challenge{background:rgba(192,32,15,.06);border-color:rgba(192,32,15,.25);color:#c0200f;}
[data-theme="light"] .lb2-act-challenge:hover{background:rgba(192,32,15,.14);border-color:rgba(192,32,15,.45);color:#a01800;}
[data-theme="light"] .lb2-pos-glow{opacity:.35;animation:none;}
[data-theme="light"] .lb2-gold .lb2-pos-num,
[data-theme="light"] .lb2-gold .lb2-r-pos .lb2-pos-num { color: var(--color-gold) !important; text-shadow: none !important; }
[data-theme="light"] .lb2-silver .lb2-pos-num,
[data-theme="light"] .lb2-silver .lb2-r-pos .lb2-pos-num { color: var(--color-silver) !important; text-shadow: none !important; }
[data-theme="light"] .lb2-bronze .lb2-pos-num,
[data-theme="light"] .lb2-bronze .lb2-r-pos .lb2-pos-num { color: var(--color-bronze) !important; text-shadow: none !important; }
[data-theme="light"] .rank-hero-icon{filter:none !important;animation:none;}
[data-theme="light"] .rank-hero-name{text-shadow:none;}
[data-theme="light"] .rank-hero-rating{color:rgba(26,29,46,.5);}
[data-theme="light"] .rank-hero-desc{color:rgba(26,29,46,.45);}
[data-theme="light"] .peak-box{background:rgba(0,0,0,.02);border-color:rgba(154,101,0,.2);}
[data-theme="light"] .peak-label{color:rgba(154,101,0,.65);}
[data-theme="light"] .rt-req{color:rgba(26,29,46,.5);}
[data-theme="light"] .rt-desc{color:rgba(26,29,46,.38);}

/* ── Page Header ── */
[data-theme="light"] .page-title{
  background:none;
  -webkit-text-fill-color:#1a1d2e;
  color:#1a1d2e;
  text-shadow:none;
}
[data-theme="light"] .page-sub{color:rgba(26,29,46,.45);}
[data-theme="light"] .atag{color:rgba(26,29,46,.42);}
[data-theme="light"] .atag-line{background:linear-gradient(to right,transparent,rgba(26,95,168,.22),transparent) !important;opacity:1;}
[data-theme="light"] .tier-name{text-shadow:none;}

/* Silver text in rank panel — ensure contrast */
[data-theme="light"] .lb-cta-rank-name.silver-text{color:var(--color-silver) !important;}

body,html,.pbar,.panel,.lb-cta-panel,.modal-box,.xbtn,.toast,.btn-back,.rank-pill,.chat-shell,.mm-shell{
  transition:background .4s ease,border-color .4s ease,color .4s ease;
}
</style>
<style>
/* ══ UNIVERSAL BUTTON STYLES (Default & Hover) ══ */
button:not(.btn-theme-toggle):not(.btn-setting):not(.m-close):not(.btn-close-modal):not(.lb2-close-btn):not(.btn-close):not(.ss-mute-btn):not(.xbtn-cancel):not(.exit-btn-cancel):not(.nav-btn.danger):not(.av-edit-btn):not(.edit-name-btn):not(.avm-refresh-btn):not(.toggle-pw):not(.btn-cancel-card):not(.btn-quit):not(.modal-tab),
.btn, .mbtn, .cta, .btn-submit, .btn-to-login,
.nav-btn:not(.danger),
.exit-btn-confirm, a.btn, .xbtn-battle, .lb2-act-btn, .btn-save, .chat-send-btn, .btn-continue, .btn-rematch, .btn-use-card, .btn-confirm-card {
  background: var(--text) !important;
  color: var(--dark) !important;
  border-color: var(--border) !important;
}
button:not(.btn-theme-toggle):not(.btn-setting):not(.m-close):not(.btn-close-modal):not(.lb2-close-btn):not(.btn-close):not(.ss-mute-btn):not(.xbtn-cancel):not(.exit-btn-cancel):not(.nav-btn.danger):not(.av-edit-btn):not(.edit-name-btn):not(.avm-refresh-btn):not(.toggle-pw):not(.btn-cancel-card):not(.btn-quit):not(.modal-tab):hover,
.btn:hover, .mbtn:hover, .cta:hover, .btn-submit:hover, .btn-to-login:hover,
.nav-btn:not(.danger):hover,
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
<div class="particles" id="PT"></div>
<div class="corner c-tl"></div><div class="corner c-tr"></div>
<div class="corner c-bl"></div><div class="corner c-br"></div>

<!-- PLAYER BAR -->
<div class="pbar">
  <a class="pinfo" href="profile.php?from=lobby_pvp.php<?= isset($pid_from_url)?'&pid='.urlencode($pid_from_url):'' ?>">
    <div class="pav"><?= $nav_avatar ?></div>
    <div>
      <div class="pname"><?= $nav_dispname ?></div>
      <div class="pid">@<?= htmlspecialchars($playerData['username'] ?? $player_name) ?></div>
      <div class="phint">👤 Lihat Profil →</div>
    </div>
  </a>

  <div style="display:flex;align-items:center;gap:8px">
    <a class="btn-back" href="main_menu.php">↩ Menu</a>
    <button class="btn-theme-toggle" id="btnThemeToggle" title="Ganti Tema"><span class="theme-icon">Light Mode</span></button>
  </div>
</div>

<!-- ── PAGE ── -->
<div class="page" id="mainPage">

  <!-- HEADER -->
  <div class="page-header">
    <div class="atag">
      <div class="atag-line"></div>
      ✦ Battle Arena · PvP ✦
      <div class="atag-line" style="background:linear-gradient(to left,transparent,var(--paper))"></div>
    </div>
    <div class="page-title">
      <span class="wr">PVP</span>&nbsp;<span class="wp">AR</span><span class="ws">ENA</span>
    </div>
    <div class="page-sub">Player vs Player · Real-Time Battle</div>
  </div>

  <!-- PLAYER IDENTITY -->
  <div class="panel">
    <div class="panel-title">⚡ Identitas Petarung</div>
    <div class="id-panel">
      <div class="id-avatar"><?= $nav_avatar ?></div>
      <div>
        <div class="id-name"><?= $nav_dispname ?></div>
        <div class="id-pid">@<?= htmlspecialchars($playerData['username'] ?? $player_name) ?> · Rank #<?= $playerRank ?></div>
        <div class="id-stats">
          <div class="id-stat">
            <div class="id-stat-val" style="color:var(--scissors)"><?= $wins ?></div>
            <div class="id-stat-lbl">Menang</div>
          </div>
          <div class="id-stat">
            <div class="id-stat-val" style="color:var(--rock)"><?= $losses ?></div>
            <div class="id-stat-lbl">Kalah</div>
          </div>
          <div class="id-stat">
            <div class="id-stat-val" style="color:var(--muted)"><?= $draws ?></div>
            <div class="id-stat-lbl">Draw</div>
          </div>
          <div class="id-stat">
            <div class="id-stat-val" style="color:#f5c842"><?= $winrate ?>%</div>
            <div class="id-stat-lbl">Win Rate</div>
          </div>
        </div>
      </div>
      <div class="id-rank-box" onclick="openRankModal()" title="Tap untuk detail rank & leaderboard" style="cursor:pointer">
        <span class="id-rank-icon"><?= $tier_icon ?></span>
        <span class="id-rank-name"><?= $tier_name ?></span>
        <span class="id-rank-rating"><?= $rating ?></span>
        <span style="display:block;font-size:.46rem;letter-spacing:.14em;color:rgba(238,240,255,.28);margin-top:5px;font-family:'Rajdhani',sans-serif;font-weight:700">TAP DETAIL ▼</span>
      </div>
    </div>
  </div>

  <!-- LEADERBOARD BUTTON PANEL -->
  <div class="lb-cta-panel" onclick="openLeaderboard()" id="lbCtaBtn">
    <div class="lb-cta-shimmer"></div>

    <!-- Left: rank info -->
    <div class="lb-cta-left">
      <div class="lb-cta-rank-badge">
        <span class="lb-cta-rank-icon"><?= $tier_icon ?></span>
        <div>
          <div class="lb-cta-rank-name"><?= $tier_name ?></div>
          <div class="lb-cta-rank-pts"><?= $rating ?> pts</div>
        </div>
      </div>

      <!-- Mini progress bar -->
      <div class="lb-cta-prog-wrap">
        <div class="lb-cta-prog-track">
          <div class="lb-cta-prog-fill" id="progFill" style="width:0%"></div>
          <div class="lb-cta-prog-dot"></div>
        </div>
        <div class="lb-cta-prog-label">
          <?php if($tier_name !== 'GRANDMASTER'): ?>
            <?= $progress_pct ?>% menuju <strong style="color:var(--rc)"><?= $next_tier_name ?></strong>
          <?php else: ?>
            ✦ Rank Tertinggi
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Center: tier mini-map -->
    <div class="lb-cta-tiers">
      <?php
      $tier_colors_map = [
        'GRANDMASTER'=>'#ffd700','MASTER'=>'#c084fc','DIAMOND'=>'#4da6ff',
        'PLATINUM'=>'#7dff4d','GOLD'=>'#f5c842','SILVER'=>'#c0c0c0','BRONZE'=>'#cd7f32',
      ];
      foreach(array_reverse($rank_tiers) as [$min,$name,$col,$glow,$icon,$desc]):
        $is_active = ($tier_name === $name);
        $is_reached = ($rating >= $min);
        $tc = $tier_colors_map[$name] ?? $col;
      ?>
      <div class="lb-mini-tier <?= $is_active?'lmt-active':($is_reached?'lmt-reached':'lmt-locked') ?>"
           style="<?= $is_active?'--tc:'.$tc.';':($is_reached?'--tc:'.$tc.';':'--tc:rgba(238,240,255,.18);') ?>">
        <span class="lmt-icon"><?= $icon ?: '★' ?></span>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Right: CTA -->
    <div class="lb-cta-right">
      <div class="lb-cta-rank-no">
        <span class="lb-cta-rank-label">RANK ARENA</span>
        <span class="lb-cta-rank-num">#<?= $playerRank ?></span>
      </div>
      <div class="lb-cta-arrow-wrap">
        <div class="lb-cta-arrow-ring">
          <span class="lb-cta-arrow-txt">LIHAT</span>
        </div>
      </div>
    </div>

    <!-- Top bar accent -->
    <div class="lb-cta-topbar" style="background:linear-gradient(90deg,transparent,<?= $tier_col ?>,transparent)"></div>
  </div>

  <!-- MATCHMAKING -->
  <div class="panel" id="mm-card">
    <div class="panel-title">🎮 Matchmaking</div>

    <!-- STATE: idle -->
    <div id="mm-idle">
      <div class="mm-idle-content">
        <div class="mm-idle-icon">⚔️</div>
        <div class="mm-idle-title">Siap Bertarung?</div>
        <div class="mm-idle-sub">Tekan tombol di bawah untuk mencari lawan sepadan</div>
      </div>
    </div>

    <!-- STATE: searching -->
    <div id="mm-searching" style="display:none">
      <div class="mm-searching-content">
        <div class="radar"><div class="radar-inner">🔍</div></div>
        <div class="mm-search-title">Mencari Lawan...</div>
        <div class="mm-search-sub">Mencocokkan rating <strong style="color:var(--rock)"><?= $rating ?></strong> ±150</div>
        <div class="queue-pill">
          <div class="qdot"></div>
          <span id="queue-txt">Terhubung ke server...</span>
        </div>
      </div>
    </div>
  </div>

  <!-- ACTION BUTTONS -->
  <div class="btn-row">
    <button class="xbtn xbtn-chat" onclick="openLobbyChat()">
      💬 Lobby Chat
      <span class="chat-online-dot" id="chatOnlineBadge" style="display:none"></span>
    </button>
    <button class="xbtn xbtn-battle" id="btn-find" onclick="startMatchmaking()">⚔️ Cari Lawan!</button>
    <button class="xbtn xbtn-cancel" id="btn-cancel" style="display:none" onclick="cancelMatchmaking()">✕ Batal</button>
  </div>


</div><!-- /page -->

<!-- ══════════════════════════════════════════════ -->
<!-- ── LEADERBOARD MODAL (REDESIGNED) ── -->
<!-- ══════════════════════════════════════════════ -->
<div class="modal-overlay" id="lb-modal" onclick="handleOverlay(event,'lb-modal')">
  <div class="lb2-shell">

    <!-- ── LEFT PANEL: Leaderboard List ── -->
    <div class="lb2-left">
      <!-- Header -->
      <div class="lb2-head">
        <div class="lb2-head-left">
          <div class="lb2-head-eyebrow">🏟️ Battle Arena</div>
          <div class="lb2-head-title">LEADERBOARD</div>
          <div class="lb2-head-sub">Top 10 Petarung Terbaik</div>
        </div>
        <button class="lb2-close-btn" onclick="closeModal('lb-modal')">
          <span>✕</span>
        </button>
      </div>

      <!-- Column headers -->
      <div class="lb2-col-head">
        <span class="lbc-pos">#</span>
        <span class="lbc-player">Petarung</span>
        <span class="lbc-rating">PTS</span>
        <span class="lbc-wr">W/L</span>
      </div>

      <!-- Rows -->
      <div class="lb2-list" id="lb2List">
        <?php
        $lb_json = [];
        foreach($leaderboard as $e):
          $isMe   = ($e['id'] === $player_id);
          $posNum = (int)$e['rank'];
          $e_rating = (int)($e['rating'] ?? 1000);
          $e_wins   = (int)($e['wins']   ?? 0);
          $e_losses = (int)($e['losses'] ?? 0);
          $e_draws  = (int)($e['draws']  ?? 0);
          $e_streak = (int)($e['current_win_streak'] ?? 0);
          $e_avatar = htmlspecialchars($e['avatar'] ?? '⚔️');
          $e_username = htmlspecialchars($e['username']);
          $e_id = htmlspecialchars($e['id']);

          $e_tier='BRONZE';$e_col='#cd7f32';$e_icon='⚔️';$e_desc='Pemula Berbakat';
          foreach($rank_tiers as [$min,$name,$col,$glow,$icon,$desc]){
            if($e_rating>=$min){$e_tier=$name;$e_col=$col;$e_icon=$icon;$e_desc=$desc;break;}
          }
          $e_total = $e_wins+$e_losses+$e_draws;
          $e_wr    = $e_total>0 ? round($e_wins/$e_total*100) : 0;

          $pos_cls = match($posNum){1=>'lb2-gold',2=>'lb2-silver',3=>'lb2-bronze',default=>''};
          $pos_lbl = match($posNum){1=>'01',2=>'02',3=>'03',default=>str_pad($posNum,2,'0',STR_PAD_LEFT)};

          // Store data for JS profile view
          $lb_json[] = [
            'id'=>$e['id'],'username'=>$e_username,'avatar'=>$e_avatar,
            'rating'=>$e_rating,'wins'=>$e_wins,'losses'=>$e_losses,'draws'=>$e_draws,
            'streak'=>$e_streak,'tier'=>$e_tier,'tierCol'=>$e_col,'tierIcon'=>$e_icon,
            'tierDesc'=>$e_desc,'rank'=>$posNum,'winrate'=>$e_wr,'isMe'=>$isMe,
          ];
        ?>
        <div class="lb2-row <?= $isMe?'lb2-me':'' ?> <?= $pos_cls ?>"
             onclick="showProfile(<?= $posNum-1 ?>)"
             data-idx="<?= $posNum-1 ?>">

          <!-- Rank number -->
          <div class="lb2-r-pos">
            <span class="lb2-pos-num"><?= $pos_lbl ?></span>
            <?php if($posNum<=3): ?>
            <div class="lb2-pos-glow" style="background:var(--color-<?= strtolower($e_tier) ?>)"></div>
            <?php endif; ?>
          </div>

          <!-- Avatar + name -->
          <div class="lb2-r-player">
            <div class="lb2-r-av" style="border-color:var(--color-<?= strtolower($e_tier) ?>);box-shadow:0 0 10px var(--glow-<?= strtolower($e_tier) ?>)">
              <?= $e_avatar ?>
            </div>
            <div class="lb2-r-info">
              <div class="lb2-r-name <?= $isMe?'lb2-r-name-me':'' ?>"><?= $e_username ?></div>
              <div class="lb2-r-id" style="font-size:0.58rem;color:var(--muted);margin-top:1px;font-family:'Rajdhani',sans-serif;font-weight:600;">@<?= $e_username ?></div>
              <div class="lb2-r-tier" style="color:var(--color-<?= strtolower($e_tier) ?>)"><?= $e_icon ?> <?= $e_tier ?></div>
            </div>
            <?php if($isMe): ?>
            <div class="lb2-you-badge">YOU</div>
            <?php endif; ?>
          </div>

          <!-- Rating -->
          <div class="lb2-r-rating">
            <span class="lb2-r-rating-val"><?= $e_rating ?></span>
          </div>

          <!-- W/L -->
          <div class="lb2-r-wl">
            <span class="lb2-r-w"><?= $e_wins ?>W</span>
            <span class="lb2-r-sep">/</span>
            <span class="lb2-r-l"><?= $e_losses ?>L</span>
          </div>

          <!-- Arrow indicator -->
          <div class="lb2-r-arrow">›</div>
        </div>
        <?php endforeach; ?>
      </div><!-- /lb2-list -->

      <!-- Footer -->
      <div class="lb2-foot">
        <div class="lb2-foot-rank">
          Rank kamu saat ini: <strong style="color:var(--paper)">#<?= $playerRank ?></strong>
          <?php if($playerRank > 10): ?> · Belum masuk top 10<?php endif; ?>
        </div>
      </div>
    </div><!-- /lb2-left -->

    <!-- ── RIGHT PANEL: Player Profile ── -->
    <div class="lb2-right" id="lb2Right">

      <!-- Empty state -->
      <div class="lb2-empty" id="lb2Empty">
        <div class="lb2-empty-icon">👆</div>
        <div class="lb2-empty-title">Pilih Petarung</div>
        <div class="lb2-empty-sub">Klik salah satu pemain di sebelah kiri untuk melihat profil lengkap mereka</div>
      </div>

      <!-- Profile content (hidden initially) -->
      <div class="lb2-profile" id="lb2Profile" style="display:none">

        <!-- Profile hero -->
        <div class="lb2-prof-hero" id="profHero">
          <div class="lb2-prof-pos-tag" id="profPosTag"></div>
          <div class="lb2-prof-av-wrap">
            <div class="lb2-prof-av" id="profAv"></div>
            <div class="lb2-prof-av-ring" id="profAvRing"></div>
          </div>
          <div class="lb2-prof-name" id="profName"></div>
          <div class="lb2-prof-id" id="profId"></div>
          <div class="lb2-prof-tier-badge" id="profTierBadge"></div>
        </div>

        <!-- Stats grid -->
        <div class="lb2-prof-stats" id="profStats">
          <div class="lb2-stat-card lb2-stat-rating">
            <div class="lb2-stat-label">Rating</div>
            <div class="lb2-stat-val" id="profRating">—</div>
          </div>
          <div class="lb2-stat-card lb2-stat-wr">
            <div class="lb2-stat-label">Win Rate</div>
            <div class="lb2-stat-val" id="profWR">—</div>
          </div>
          <div class="lb2-stat-card lb2-stat-streak">
            <div class="lb2-stat-label">Streak 🔥</div>
            <div class="lb2-stat-val" id="profStreak">—</div>
          </div>
        </div>

        <!-- W/D/L bar -->
        <div class="lb2-wdl-section">
          <div class="lb2-wdl-label-row">
            <span class="lb2-wdl-tag wdl-w" id="profWins">—W</span>
            <span class="lb2-wdl-tag wdl-d" id="profDraws">—D</span>
            <span class="lb2-wdl-tag wdl-l" id="profLosses">—L</span>
          </div>
          <div class="lb2-wdl-bar">
            <div class="lb2-wdl-seg wdl-seg-w" id="wdlW"></div>
            <div class="lb2-wdl-seg wdl-seg-d" id="wdlD"></div>
            <div class="lb2-wdl-seg wdl-seg-l" id="wdlL"></div>
          </div>
        </div>

        <!-- Tier progress -->
        <div class="lb2-tier-progress" id="profTierSection">
          <div class="lb2-tp-label">
            <span id="profTierLabel">—</span>
            <span class="lb2-tp-next" id="profTierNext">—</span>
          </div>
          <div class="lb2-tp-track">
            <div class="lb2-tp-fill" id="profTierFill"></div>
          </div>
        </div>

        <!-- Action buttons -->
        <div class="lb2-prof-actions" id="profActions">
          <a class="lb2-act-btn lb2-act-profile" id="profViewBtn" href="#">
            <span>👤</span> Lihat Profil
          </a>
          <button class="lb2-act-btn lb2-act-challenge" id="profChallengeBtn" onclick="startMatchmaking()">
            <span>⚔️</span> Tantang!
          </button>
        </div>

        <!-- "That's you" note -->
        <div class="lb2-me-note" id="profMeNote" style="display:none">
          <span>✦ Ini profil kamu ✦</span>
        </div>

      </div><!-- /lb2-profile -->
    </div><!-- /lb2-right -->

  </div><!-- /lb2-shell -->
</div>

<!-- Inject leaderboard data for JS -->
<script>
const LB_DATA = <?= json_encode($lb_json) ?>;
const MY_PLAYER_ID = <?= json_encode($player_id) ?>;

// rank tiers data for progress calc
const RANK_TIERS = [
  {min:2000,name:'GRANDMASTER',col:'#ffd700'},
  {min:1700,name:'MASTER',col:'#c084fc'},
  {min:1500,name:'DIAMOND',col:'#4da6ff'},
  {min:1300,name:'PLATINUM',col:'#7dff4d'},
  {min:1100,name:'GOLD',col:'#f5c842'},
  {min:950, name:'SILVER',col:'#c0c0c0'},
  {min:0,   name:'BRONZE',col:'#cd7f32'},
];

function getTierProgress(rating){
  let cur=RANK_TIERS[RANK_TIERS.length-1],next=null;
  for(let i=0;i<RANK_TIERS.length;i++){
    if(rating>=RANK_TIERS[i].min){
      cur=RANK_TIERS[i];
      next=i>0?RANK_TIERS[i-1]:null;
      break;
    }
  }
  if(!next) return {pct:100,nextName:'MAX',curMin:cur.min,nextMin:cur.min};
  const pct=Math.min(100,Math.round((rating-cur.min)/(next.min-cur.min)*100));
  return {pct,nextName:next.name,curMin:cur.min,nextMin:next.min};
}

let activeIdx = -1;

function showProfile(idx){
  const d = LB_DATA[idx];
  if(!d) return;

  // Highlight active row
  document.querySelectorAll('.lb2-row').forEach((r,i)=>{
    r.classList.toggle('lb2-row-active', i===idx);
  });
  activeIdx = idx;

  // Switch panels
  document.getElementById('lb2Empty').style.display='none';
  const prof = document.getElementById('lb2Profile');
  prof.style.display='block';
  prof.style.opacity='0';
  prof.style.transform='translateY(10px)';
  requestAnimationFrame(()=>{
    prof.style.transition='opacity .35s ease, transform .35s ease';
    prof.style.opacity='1';
    prof.style.transform='translateY(0)';
  });

  const tierNameLower = d.tier.toLowerCase();
  prof.style.setProperty('--tier-col', 'var(--color-' + tierNameLower + ')');
  prof.style.setProperty('--tier-glow', 'var(--glow-' + tierNameLower + ')');

  // Hero
  const hero = document.getElementById('profHero');
  hero.style.setProperty('--prof-col', 'var(--tier-glow)');
  hero.style.borderBottomColor = 'color-mix(in srgb, var(--tier-col) 20%, transparent)';

  const posLabels={1:'🥇 #1 · JUARA',2:'🥈 #2 · RUNNER UP',3:'🥉 #3 · SEMIFINAL'};
  const posTag = document.getElementById('profPosTag');
  posTag.textContent = posLabels[d.rank] || `# ${String(d.rank).padStart(2,'0')}`;
  posTag.style.color = d.rank<=3 ? 'var(--tier-col)' : 'var(--muted)';
  posTag.style.borderColor = d.rank<=3 ? 'color-mix(in srgb, var(--tier-col) 33%, transparent)' : 'var(--border)';
  posTag.style.background = d.rank<=3 ? 'color-mix(in srgb, var(--tier-col) 7%, transparent)' : 'transparent';

  document.getElementById('profAv').textContent = d.avatar;
  const ring = document.getElementById('profAvRing');
  ring.style.borderColor = 'var(--tier-col)';
  ring.style.boxShadow = `0 0 28px var(--tier-glow), inset 0 0 20px color-mix(in srgb, var(--tier-col) 7%, transparent)`;

  document.getElementById('profName').textContent = d.username;
  document.getElementById('profName').style.color = d.isMe ? 'var(--color-diamond)' : 'var(--text)';
  document.getElementById('profId').textContent = `@${d.username}`;

  const tb = document.getElementById('profTierBadge');
  tb.textContent = `${d.tierIcon} ${d.tier}`;
  tb.style.color = 'var(--tier-col)';
  tb.style.borderColor = 'color-mix(in srgb, var(--tier-col) 40%, transparent)';
  tb.style.background = 'color-mix(in srgb, var(--tier-col) 9%, transparent)';
  tb.style.boxShadow = `0 0 20px var(--tier-glow)`;

  // Stats
  document.getElementById('profRating').textContent = d.rating;
  document.getElementById('profRating').style.color = 'var(--tier-col)';
  document.getElementById('profWR').textContent = d.winrate + '%';
  document.getElementById('profWR').style.color = d.winrate>=60?'var(--win)':d.winrate>=40?'var(--gold)':'var(--lose)';
  document.getElementById('profStreak').textContent = d.streak > 0 ? `${d.streak}🔥` : '—';
  document.getElementById('profStreak').style.color = d.streak>=3?'var(--color-grandmaster)':d.streak>0?'var(--color-gold)':'var(--muted)';

  // W/D/L bar
  const total = d.wins+d.draws+d.losses;
  const wPct = total>0?d.wins/total*100:0;
  const dPct = total>0?d.draws/total*100:0;
  const lPct = total>0?d.losses/total*100:0;
  document.getElementById('profWins').textContent = d.wins+'W';
  document.getElementById('profDraws').textContent = d.draws+'D';
  document.getElementById('profLosses').textContent = d.losses+'L';
  setTimeout(()=>{
    document.getElementById('wdlW').style.width = wPct+'%';
    document.getElementById('wdlD').style.width = dPct+'%';
    document.getElementById('wdlL').style.width = lPct+'%';
  },100);

  // Tier progress
  const tp = getTierProgress(d.rating);
  document.getElementById('profTierLabel').textContent = `${d.tier} · ${d.rating} pts`;
  document.getElementById('profTierLabel').style.color = 'var(--tier-col)';
  document.getElementById('profTierNext').textContent = tp.nextName==='MAX' ? '✦ Rank Tertinggi' : `→ ${tp.nextName} (${tp.nextMin} pts)`;
  setTimeout(()=>{
    const tf = document.getElementById('profTierFill');
    if(tf) {
      tf.style.width=tp.pct+'%';
      tf.style.background = 'var(--tier-col)';
      tf.style.boxShadow = '0 0 10px var(--tier-glow)';
    }
  }, 150);

  // Actions
  const pid_param = <?= json_encode($pid_from_url) ?>;
  document.getElementById('profViewBtn').href = `profile.php?id=${encodeURIComponent(d.id)}&from=lobby_pvp.php&pid=${encodeURIComponent(pid_param)}`;

  const meNote = document.getElementById('profMeNote');
  const actions = document.getElementById('profActions');
  if(d.isMe){
    meNote.style.display='flex';
    actions.style.display='none';
    document.getElementById('profViewBtn').href = `profile.php?from=lobby_pvp.php&pid=${encodeURIComponent(pid_param)}`;
    actions.style.display='flex';
    document.getElementById('profChallengeBtn').style.display='none';
  } else {
    meNote.style.display='none';
    actions.style.display='flex';
    document.getElementById('profChallengeBtn').style.display='flex';
  }
}
</script>

<!-- ── RANK DETAIL MODAL ── -->
<div class="modal-overlay" id="rank-modal" onclick="handleOverlay(event,'rank-modal')">
  <div class="modal-box" style="max-width:520px">
    <div class="modal-head" style="border-color:var(--rc)">
      <div>
        <div class="modal-head-title" style="color:var(--rc)"><?= $tier_icon ?> SISTEM RANK</div>
        <div class="modal-head-sub">Detail rank &amp; progress petarung</div>
      </div>
      <button class="btn-close" onclick="closeModal('rank-modal')">✕ TUTUP</button>
    </div>
    <div class="modal-body">

      <!-- RANK SEKARANG HERO -->
      <div class="rank-modal-hero">
        <span class="rank-hero-icon" style="filter:drop-shadow(0 0 16px <?= $tier_col ?>)"><?= $tier_icon ?></span>
        <div class="rank-hero-name" style="color:<?= $tier_col ?>"><?= $tier_name ?></div>
        <div class="rank-hero-rating"><?= $rating ?> pts — Rank Arena #<?= $playerRank ?></div>
        <div class="rank-hero-desc">"<?= $tier_desc ?>"</div>
      </div>

      <!-- PEAK RANK -->
      <div class="peak-box" style="border-color:<?= $peak_tier_col ?>55;background:<?= $peak_tier_col ?>0d">
        <div style="flex:1">
          <div class="peak-label">🏆 Rank Tertinggi yang Pernah Dicapai</div>
          <div class="peak-val" style="color:<?= $peak_tier_col ?>;font-size:.88rem">
            <?= $peak_tier_name ?>
            <span style="color:var(--muted);font-family:'Bebas Neue',sans-serif;font-size:.82rem;margin-left:6px"><?= $peak_rating ?> pts</span>
          </div>
          <?php if($peak_tier_name !== $tier_name): ?>
          <div style="font-size:.55rem;letter-spacing:.1em;color:rgba(238,240,255,.28);margin-top:3px;font-style:italic">Rank saat ini: <?= $tier_name ?> (<?= $rating ?> pts)</div>
          <?php endif; ?>
        </div>
        <?php if($peak_tier_name === $tier_name): ?>
        <div style="font-size:.52rem;letter-spacing:.12em;color:<?= $peak_tier_col ?>;font-weight:700;background:rgba(0,0,0,.3);padding:3px 8px;border:1px solid <?= $peak_tier_col ?>;border-radius:4px;align-self:center">● SAAT INI</div>
        <?php else: ?>
        <div style="font-size:.52rem;letter-spacing:.12em;color:rgba(255,215,0,.6);font-weight:700;background:rgba(255,215,0,.08);padding:3px 8px;border:1px solid rgba(255,215,0,.3);border-radius:4px;align-self:center">★ PEAK</div>
        <?php endif; ?>
      </div>

      <!-- PROGRESS BAR (sama dg panel utama) -->
      <div style="margin-bottom:18px">
        <div class="progress-labels">
          <span class="progress-cur"><?= $tier_icon ?> <?= $tier_name ?> · <?= $rating ?> pts</span>
          <span class="progress-next">
            <?php if($tier_name !== 'GRANDMASTER'): ?>
              Selanjutnya: <?= $next_tier_name ?> (<?= $next_rating ?> pts)
            <?php else: ?> ✦ Rank Tertinggi <?php endif; ?>
          </span>
        </div>
        <div class="progress-bar">
          <div class="progress-fill" id="modalProgFill" style="width:0%"></div>
          <div class="progress-glow"></div>
        </div>
        <div style="font-size:.58rem;color:var(--muted);margin-top:5px;letter-spacing:.08em">Progress ke tier berikutnya: <?= $progress_pct ?>%</div>
      </div>

      <!-- SEMUA TIER -->
      <div class="section-label">🏅 Semua Tier Rank</div>
      <?php foreach($rank_tiers as [$min,$name,$col,$glow,$icon,$desc]):
        $isActive = ($tier_name === $name);
        $isPeak   = ($peak_tier_name === $name && !$isActive);
        $isReached = ($rating >= $min || $peak_rating >= $min);
        $rowStyle = "border-color:".($isActive ? $col : ($isPeak ? $col : 'var(--border)')).";".($isActive?"background:rgba(255,255,255,.05);":"");
      ?>
      <div class="rank-tier-row <?= $isActive?'rt-active':'' ?> <?= $isPeak?'rt-peak':'' ?>"
           style="<?= $rowStyle ?>; opacity:<?= $isReached?'1':'.42' ?>">
        <div class="rt-icon"><?= $icon ?></div>
        <div class="rt-info">
          <div class="rt-name" style="color:<?= $col ?>"><?= $name ?></div>
          <div class="rt-req"><?= $min === 0 ? 'Mulai dari 0 pts' : $min.'+ pts' ?></div>
          <div class="rt-desc"><?= $desc ?></div>
        </div>
        <div class="rt-badge-group">
          <?php if($isActive): ?>
            <span class="rt-status-badge" style="border-color:<?= $col ?>;color:<?= $col ?>;background:rgba(0,0,0,.3)">● RANK KAMU</span>
          <?php endif; ?>
          <?php if($isPeak): ?>
            <span class="rt-status-badge" style="border-color:rgba(255,215,0,.5);color:#ffd700;background:rgba(255,215,0,.06)">★ PEAK</span>
          <?php endif; ?>
          <?php if(!$isActive && !$isPeak && $isReached): ?>
            <span class="rt-status-badge" style="border-color:rgba(125,255,77,.3);color:#7dff4d;background:transparent">✓ CAPAI</span>
          <?php elseif(!$isReached): ?>
            <span class="rt-status-badge" style="border-color:rgba(238,240,255,.12);color:var(--muted);background:transparent">🔒 KUNCI</span>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>

    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<!-- ══════════════════════════════════════════════ -->
<!-- ── LOBBY CHAT MODAL ── -->
<!-- ══════════════════════════════════════════════ -->
<div class="modal-overlay" id="chat-modal" onclick="handleOverlay(event,'chat-modal')">
  <div class="chat-shell">

    <!-- Top accent bar -->
    <div class="chat-top-bar"></div>

    <!-- Header -->
    <div class="chat-head">
      <div class="chat-head-left">
        <div class="chat-head-eyebrow">💬 Battle Arena</div>
        <div class="chat-head-title">LOBBY CHAT</div>
        <div class="chat-head-sub">Ngobrol & Lihat Siapa yang Online</div>
      </div>
      <button class="lb2-close-btn" onclick="closeModal('chat-modal')">✕</button>
    </div>

    <!-- Body: two columns -->
    <div class="chat-body">

      <!-- LEFT: Online Players -->
      <div class="chat-online-panel">
        <div class="chat-panel-title">
          <span class="cop-dot"></span>
          ONLINE SEKARANG
          <span class="cop-count" id="onlineCount">0</span>
        </div>
        <div class="chat-online-list" id="onlineList">
          <div class="cop-empty">Menghubungkan...</div>
        </div>
      </div>

      <!-- RIGHT: Chat -->
      <div class="chat-right">
        <div class="chat-messages" id="chatMessages">
          <div class="chat-welcome">
            <div class="chat-welcome-icon">⚔️</div>
            <div class="chat-welcome-txt">Selamat datang di Lobby Chat!</div>
            <div class="chat-welcome-sub">Ngobrol bareng player lain yang sedang online.</div>
          </div>
        </div>
        <div class="chat-input-wrap">
          <input class="chat-input" id="chatInput" type="text" maxlength="200"
                 placeholder="Ketik pesan... (Enter untuk kirim)"
                 autocomplete="off">
          <button class="chat-send-btn" id="chatSendBtn" onclick="sendChatMsg()">
            <span>KIRIM</span>
            <span class="chat-send-arrow">▶</span>
          </button>
        </div>
      </div>

    </div><!-- /chat-body -->
  </div><!-- /chat-shell -->
</div>

<script>
// ── CONFIG ──────────────────────────────────
// FIX CROSS-DEVICE: pakai hostname dari browser agar device lain di jaringan
// yang sama bisa connect (bukan localhost yang hanya bekerja di device server)
const WS_URL        = 'ws://' + window.location.hostname + ':8080';
const PLAYER_ID     = <?= json_encode($player_id) ?>;
const PLAYER_NAME   = <?= json_encode($player_name) ?>;
const PLAYER_RATING = <?= (int)$rating ?>;

sessionStorage.setItem('lobby_player_id',     PLAYER_ID);
sessionStorage.setItem('lobby_player_name',   PLAYER_NAME);
sessionStorage.setItem('lobby_player_rating', String(PLAYER_RATING));

// ── STATE ────────────────────────────────────
let ws = null, searching = false, authed = false;

// ── WS HELPERS ──────────────────────────────
function wsSend(obj){if(ws&&ws.readyState===WebSocket.OPEN)ws.send(JSON.stringify(obj));}

function initWS(cb){
  if(ws&&ws.readyState===WebSocket.OPEN){cb();return;}
  if(ws){try{ws.close();}catch(e){}}
  authed=false;
  ws=new WebSocket(WS_URL);
  ws.onopen=()=>{wsSend({type:'auth',player_id:PLAYER_ID,player_name:PLAYER_NAME,rating:PLAYER_RATING});};
  ws.onmessage=(e)=>{let m;try{m=JSON.parse(e.data);}catch{return;}handleMsg(m,cb);};
  ws.onerror=()=>{toast('❌ Tidak bisa terhubung ke server game.','t-red',5000);resetUI();};
  ws.onclose=()=>{authed=false;if(searching){toast('⚠️ Koneksi terputus.','t-red',3000);searching=false;resetUI();}};
}

function handleMsg(msg,cb){
  switch(msg.type){
    case 'auth_ok':
      authed=true;if(typeof cb==='function')cb();break;
    case 'queue_joined':
      document.getElementById('queue-txt').textContent=`${msg.queue_size} pemain dalam antrian`;break;
    case 'match_found':
      msg._my_player_id=PLAYER_ID;msg._my_player_name=PLAYER_NAME;msg._my_rating=PLAYER_RATING;
      sessionStorage.setItem('match_data',JSON.stringify(msg));
      sessionStorage.setItem('ws_url',WS_URL);
      toast('✅ Lawan ditemukan! Memasuki arena...','t-green',1500);
      // FIX CROSS-DEVICE: encode match_data ke URL (?md=) agar device/browser lain
      // yang tidak punya sessionStorage tetap bisa mendapatkan data match
      setTimeout(()=>{
        const mdEncoded = btoa(unescape(encodeURIComponent(JSON.stringify(msg))));
        window.location.href='gameplay_pvp.php?pid='+encodeURIComponent(PLAYER_ID)+'&md='+encodeURIComponent(mdEncoded);
      },1300);
      break;
    case 'queue_left':resetUI();break;
    case 'error':toast('❌ '+(msg.msg||'Error tidak diketahui'),'t-red',3000);break;
  }
}

// ── MATCHMAKING ──────────────────────────────
function startMatchmaking(){
  if(searching)return;
  enterSearchingUI();
  initWS(()=>wsSend({type:'join_queue'}));
}
function cancelMatchmaking(){
  wsSend({type:'leave_queue'});
  searching=false;resetUI();toast('Pencarian dibatalkan.','',2000);
}
function enterSearchingUI(){
  searching=true;
  document.getElementById('mm-idle').style.display='none';
  document.getElementById('mm-searching').style.display='block';
  document.getElementById('btn-find').style.display='none';
  document.getElementById('btn-cancel').style.display='flex';
}
function resetUI(){
  searching=false;
  document.getElementById('mm-idle').style.display='block';
  document.getElementById('mm-searching').style.display='none';
  document.getElementById('btn-find').style.display='flex';
  document.getElementById('btn-cancel').style.display='none';
}

// ── MODAL ────────────────────────────────────
function openLeaderboard(){
  document.getElementById('lb-modal').classList.add('show');
  // small pulse on the button
  const btn=document.getElementById('lbCtaBtn');
  if(btn){btn.style.transform='scale(0.97)';setTimeout(()=>btn.style.transform='',200);}
}
function openRankModal(){
  document.getElementById('rank-modal').classList.add('show');
  setTimeout(()=>{
    const pf=document.getElementById('modalProgFill');
    if(pf)pf.style.width='<?= $progress_pct ?>%';
  },200);
}
function closeModal(id){document.getElementById(id).classList.remove('show');}
function handleOverlay(e,id){if(e.target===document.getElementById(id))closeModal(id);}

// ── TOAST ────────────────────────────────────
let toastTimer;
function toast(msg,cls='',dur=2500){
  const el=document.getElementById('toast');
  el.textContent=msg;el.className='toast show'+(cls?' '+cls:'');
  clearTimeout(toastTimer);toastTimer=setTimeout(()=>el.classList.remove('show'),dur);
}

// ── PROGRESS BAR ANIMATE ─────────────────────
setTimeout(()=>{
  const pf=document.getElementById('progFill');
  if(pf)pf.style.width='<?= $progress_pct ?>%';
},400);

// ── CANVAS NODE NETWORK ─────────────────────
const cv=document.getElementById('bg'),cx=cv.getContext('2d');
let W,H,NS=[];
const COLS=['rgba(255,77,77,','rgba(77,166,255,','rgba(125,255,77,'];
function rsz(){W=cv.width=innerWidth;H=cv.height=innerHeight}
function mkN(){NS=Array.from({length:70},()=>({
  x:Math.random()*W,y:Math.random()*H,
  vx:(Math.random()-.5)*.55,vy:(Math.random()-.5)*.55,
  r:Math.random()*2.2+.8,col:COLS[Math.floor(Math.random()*3)],
  a:Math.random()*.55+.1,maxA:Math.random()*.55+.1,da:.002
}))}
function frame(){
  cx.clearRect(0,0,W,H);
  const g=cx.createRadialGradient(W/2,H*.45,0,W/2,H*.45,Math.max(W,H)*.72);
  g.addColorStop(0,'rgba(15,18,38,.97)');g.addColorStop(1,'rgba(5,6,13,1)');
  cx.fillStyle=g;cx.fillRect(0,0,W,H);
  for(const n of NS){
    n.x+=n.vx;n.y+=n.vy;
    if(n.x<0||n.x>W)n.vx*=-1;if(n.y<0||n.y>H)n.vy*=-1;
    n.a+=n.da;if(n.a>n.maxA||n.a<.05)n.da*=-1;
    for(const m of NS){
      const d=Math.hypot(n.x-m.x,n.y-m.y);
      if(d<170){
        cx.beginPath();cx.moveTo(n.x,n.y);cx.lineTo(m.x,m.y);
        cx.strokeStyle=n.col+(1-d/170)*.07+')';cx.lineWidth=.5;cx.stroke();
      }
    }
    cx.beginPath();cx.arc(n.x,n.y,n.r,0,Math.PI*2);
    cx.fillStyle=n.col+n.a+')';cx.fill();
    if(n.r>1.8){cx.beginPath();cx.arc(n.x,n.y,n.r*2.5,0,Math.PI*2);
      cx.fillStyle=n.col+n.a*.2+')';cx.fill();}
  }
  for(let i=0;i<140;i++){
    const sx=(i*137.5)%W,sy=(i*93.7)%H;
    const sa=.07+.45*Math.abs(Math.sin(Date.now()*.0008+i));
    cx.beginPath();cx.arc(sx,sy,.6,0,Math.PI*2);
    cx.fillStyle=`rgba(238,240,255,${sa})`;cx.fill();
  }
  requestAnimationFrame(frame);
}
window.addEventListener('resize',()=>{rsz();mkN()});rsz();mkN();frame();

// ── ENERGY LINES ────────────────────────────
const ELC=document.getElementById('EL');
for(let i=0;i<10;i++){
  const e=document.createElement('div');e.className='el';
  e.style.cssText=`left:${Math.random()*100}%;height:${Math.random()*50+20}px;animation-duration:${Math.random()*9+5}s;animation-delay:${Math.random()*9}s;opacity:.38;`;
  ELC.appendChild(e);}

// ── PARTICLES ───────────────────────────────
const PC=document.getElementById('PT');
for(let i=0;i<30;i++){
  const p=document.createElement('div');p.className='p';
  const s=Math.random()*4.5+1,col=COLS[i%3];
  p.style.cssText=`left:${Math.random()*100}%;width:${s}px;height:${s}px;background:${col}${Math.random()*.5+.25});box-shadow:0 0 ${s*3}px ${col}.55);animation-duration:${Math.random()*16+9}s;animation-delay:${Math.random()*16}s;`;
  PC.appendChild(p);}

// ── ENTRANCE ANIM ────────────────────────────
const page=document.getElementById('mainPage');
page.style.cssText+='opacity:0;transform:translateY(18px)';
setTimeout(()=>{page.style.transition='opacity .7s ease,transform .7s ease';page.style.opacity='1';page.style.transform='translateY(0)'},120);

// ════════════════════════════════════════════
//  LOBBY CHAT SYSTEM  — fully synced via server broadcast
// ════════════════════════════════════════════
let chatWs       = null;
let chatAuthed   = false;
let chatOpen     = false;
let chatReconTimer = null;
const CHAT_MAX   = 150;

// ── Open / Close ──────────────────────────
function openLobbyChat() {
  document.getElementById('chat-modal').classList.add('show');
  chatOpen = true;
  initChatWS();
  setTimeout(() => { document.getElementById('chatInput')?.focus(); }, 320);
}

// Wrap the global closeModal so chat gets cleanup
(function() {
  const _orig = window.closeModal;
  window.closeModal = function(id) {
    _orig(id);
    if (id === 'chat-modal') {
      chatOpen   = false;
      chatAuthed = false; // FIX: tandai perlu re-auth saat buka lagi
      if (chatWs && chatWs.readyState === WebSocket.OPEN) {
        // Tell server we're leaving so it removes us from online list immediately
        chatWs.send(JSON.stringify({ type: 'chat_leave', player_id: PLAYER_ID }));
      }
    }
  };
})();

// ── WebSocket Init ────────────────────────
function initChatWS() {
  // Already connected and authed — nothing to do
  if (chatWs && chatWs.readyState === WebSocket.OPEN && chatAuthed) return;
  // Connecting — wait
  if (chatWs && chatWs.readyState === WebSocket.CONNECTING) return;

  // FIX: WS masih hidup tapi player sudah leave — cukup re-auth, tidak perlu reconnect penuh
  if (chatWs && chatWs.readyState === WebSocket.OPEN && !chatAuthed) {
    chatWs.send(JSON.stringify({
      type:        'chat_auth',
      player_id:   PLAYER_ID,
      player_name: PLAYER_NAME,
      rating:      PLAYER_RATING,
    }));
    return;
  }

  // Close stale connection (WS closed/closing/null)
  if (chatWs) { try { chatWs.close(); } catch(e){} chatWs = null; }
  chatAuthed = false;

  chatWs = new WebSocket(WS_URL);

  chatWs.onopen = () => {
    clearTimeout(chatReconTimer);
    // Authenticate as chat user
    chatWs.send(JSON.stringify({
      type:        'chat_auth',
      player_id:   PLAYER_ID,
      player_name: PLAYER_NAME,
      rating:      PLAYER_RATING,
    }));
  };

  chatWs.onmessage = (e) => {
    let m; try { m = JSON.parse(e.data); } catch { return; }
    handleChatMsg(m);
  };

  chatWs.onerror = () => {
    if (chatOpen) appendSystemMsg('⚠️ Koneksi error. Mencoba ulang...');
  };

  chatWs.onclose = () => {
    chatAuthed = false;
    updateOnlineList({});          // clear online list locally
    if (chatOpen) {
      appendSystemMsg('🔌 Koneksi terputus. Menghubungkan kembali...');
      // Auto-reconnect after 3s if modal still open
      chatReconTimer = setTimeout(() => { if (chatOpen) initChatWS(); }, 3000);
    }
  };
}

// ── Message Handler ───────────────────────
function handleChatMsg(msg) {
  switch (msg.type) {
    case 'chat_auth_ok':
      chatAuthed = true;
      // Hapus welcome placeholder — history akan mengisinya jika ada.
      // Notif "bergabung" ditampilkan setelah handler chat_history berjalan.
      break;

    case 'chat_history':
      // Render riwayat pesan dari server (dikirim tepat setelah chat_auth_ok)
      renderChatHistory(msg.messages || []);
      // Tampilkan notif bergabung setelah history selesai dirender
      appendSystemMsg('\u2746 Kamu bergabung sebagai ' + PLAYER_NAME);
      break;

    case 'chat_online_update':
      // Server is single source of truth — always trust this
      updateOnlineList(msg.players || {});
      break;

    case 'chat_message':
      // Server echoes message to ALL including sender — just render it
      appendChatBubble(msg);
      break;

    case 'chat_system':
      appendSystemMsg(msg.msg || '');
      break;

    // Ignore game auth / other messages on this connection
    default: break;
  }
}

// ── Send Message ──────────────────────────
function sendChatMsg() {
  const inp  = document.getElementById('chatInput');
  const text = inp.value.trim();
  if (!text) return;

  if (!chatAuthed || !chatWs || chatWs.readyState !== WebSocket.OPEN) {
    toast('⚠️ Belum terhubung ke server chat.', 't-red', 2000);
    return;
  }

  // Send to server — server broadcasts back to ALL (including us)
  // Do NOT append locally here to avoid duplicates
  chatWs.send(JSON.stringify({
    type:        'chat_send',
    player_id:   PLAYER_ID,
    player_name: PLAYER_NAME,
    text:        text,
  }));

  inp.value = '';
  inp.focus();
}

// ── Render: Chat History (dari server saat join/rejoin) ──────
function renderChatHistory(messages) {
  const box = document.getElementById('chatMessages');

  // Hapus welcome placeholder jika masih ada
  const welcome = box.querySelector('.chat-welcome');
  if (welcome) welcome.remove();

  if (!messages.length) return;

  // Separator penanda awal history
  const sep = document.createElement('div');
  sep.className = 'chat-msg-system';
  sep.style.cssText = 'opacity:.5;font-size:.62rem;letter-spacing:.08em';
  sep.textContent = '─── Riwayat Chat ───';
  box.appendChild(sep);

  // Render setiap pesan history (oldest ke newest, sudah urut dari server)
  messages.forEach(m => appendChatBubble(m, true));

  box.scrollTop = box.scrollHeight;
}

// ── Render: Chat Bubble ───────────────────
function appendChatBubble(msg, isHistory = false) {
  const box  = document.getElementById('chatMessages');
  const isMe = (msg.player_id === PLAYER_ID);

  // Use server timestamp if available, else client clock
  let time;
  if (msg.ts) {
    const d = new Date(msg.ts * 1000);
    time = d.getHours().toString().padStart(2,'0') + ':' + d.getMinutes().toString().padStart(2,'0');
  } else {
    const d = new Date();
    time = d.getHours().toString().padStart(2,'0') + ':' + d.getMinutes().toString().padStart(2,'0');
  }

  const av = escHtml(msg.avatar || '⚔️');
  const el = document.createElement('div');
  el.className = 'chat-msg' + (isMe ? ' chat-msg-me' : '');
  el.innerHTML =
    '<div class="chat-msg-head">' +
      '<div class="chat-msg-av">' + av + '</div>' +
      '<span class="chat-msg-name' + (isMe ? ' chat-msg-name-me' : '') + '">' + escHtml(msg.player_name || '???') + '</span>' +
      '<span style="font-size:0.52rem;color:var(--muted);margin-left:2px;font-family:\'Rajdhani\',sans-serif;font-weight:600;">@' + escHtml(msg.player_name || '') + '</span>' +
      '<span class="chat-msg-time">' + time + '</span>' +
    '</div>' +
    '<div class="chat-msg-bubble">' + escHtml(msg.text || '') + '</div>';

  box.appendChild(el);
  trimChatBox(box);
  box.scrollTop = box.scrollHeight;
}

// ── Render: System Message ────────────────
function appendSystemMsg(txt) {
  const box = document.getElementById('chatMessages');
  // Remove welcome placeholder if still there
  const welcome = box.querySelector('.chat-welcome');
  if (welcome) welcome.remove();

  const el = document.createElement('div');
  el.className = 'chat-msg-system';
  el.textContent = txt;
  box.appendChild(el);
  trimChatBox(box);
  box.scrollTop = box.scrollHeight;
}

function trimChatBox(box) {
  while (box.children.length > CHAT_MAX) box.removeChild(box.firstChild);
}

// ── Render: Online Players List ───────────
function updateOnlineList(players) {
  const list    = document.getElementById('onlineList');
  const countEl = document.getElementById('onlineCount');
  const badge   = document.getElementById('chatOnlineBadge');
  const ids     = Object.keys(players);

  countEl.textContent        = ids.length;
  badge.style.display        = ids.length > 0 ? 'inline-block' : 'none';

  if (ids.length === 0) {
    list.innerHTML = '<div class="cop-empty">Belum ada yang online</div>';
    return;
  }

  // Sort: current player first, then alphabetical by name
  ids.sort((a, b) => {
    if (a === PLAYER_ID) return -1;
    if (b === PLAYER_ID) return  1;
    return (players[a].name || '').localeCompare(players[b].name || '');
  });

  list.innerHTML = '';
  ids.forEach(id => {
    const p    = players[id];
    const isMe = (id === PLAYER_ID);
    const av   = escHtml(p.avatar || '⚔️');
    const el   = document.createElement('div');
    el.className = 'cop-item' + (isMe ? ' cop-item-me' : '');
    el.innerHTML =
      '<div class="cop-av">' + av + '</div>' +
      '<div class="cop-info">' +
        '<div class="cop-name' + (isMe ? ' cop-name-me' : '') + '">' +
          escHtml(p.name || id) +
          (isMe ? ' <span style="font-size:.44rem;color:rgba(77,166,255,.6)">(kamu)</span>' : '') +
        '</div>' +
        '<div style="font-size:0.58rem;color:var(--muted);margin-top:1px;font-family:\'Rajdhani\',sans-serif;font-weight:600;">@' + escHtml(p.name || id) + '</div>' +
        '<div class="cop-status">● Online</div>' +
      '</div>' +
      '<div class="cop-live-dot"></div>';
    list.appendChild(el);
  });
}

// ── Helpers ───────────────────────────────
function escHtml(s) {
  return String(s)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Enter key to send | ESC key to close any open modal
document.addEventListener('keydown', (e) => {
  if (e.key === 'Enter' && chatOpen) {
    const inp = document.getElementById('chatInput');
    if (document.activeElement === inp) sendChatMsg();
  }
  if (e.key === 'Escape') {
    ['lb-modal','rank-modal','chat-modal'].forEach(id => {
      const el = document.getElementById(id);
      if (el && el.classList.contains('show')) closeModal(id);
    });
  }
});

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