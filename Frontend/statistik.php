<?php
// ══════════════════════════════════════════════
//  STATISTIK — Lucky Battle
//  Layout: history.php | Tema: main_menu.php
//  Mode toggle: PvP ↔ AI terpisah
//  3 Kartu Favorit PvP (dari 24 kartu yang ada)
// ══════════════════════════════════════════════
session_start();
require_once __DIR__ . '/../Backend/database.php';

if (!isset($_SESSION['player_id'])) {
    header('Location: Landing_page.php');
    exit;
}

$player_id = $_SESSION['player_id'];

$player = getPlayerData($player_id);
if (!$player) { header('Location: main_menu.php'); exit; }

$AVATARS_LIST = ['⚔️','🛡️','🔥','💎','🌪️','👑','🤖','🐉','⚡','🌙','🗡️','🔮'];
$nav_avatar   = $player['avatar'] ?? ($AVATARS_LIST[(int)($player['avatar_choice'] ?? 0)] ?? '⚔️');
$nav_dispname = htmlspecialchars($player['display_name'] ?? $player['username']);

// ── Stats PvP ──
$wins   = (int)($player['wins']   ?? 0);
$losses = (int)($player['losses'] ?? 0);
$draws  = (int)($player['draws']  ?? 0);
$total_pvp = $wins + $losses + $draws;
$winrate_pvp = $total_pvp > 0 ? round($wins / $total_pvp * 100, 1) : 0;

// ── Stats AI ──
$ai_wins   = (int)($player['ai_wins']   ?? 0);
$ai_losses = (int)($player['ai_losses'] ?? 0);
$ai_draws  = (int)($player['ai_draws']  ?? 0);
$total_ai  = $ai_wins + $ai_losses + $ai_draws;
$winrate_ai = $total_ai > 0 ? round($ai_wins / $total_ai * 100, 1) : 0;

// ── Rating & Streak ──
$rating     = (int)($player['rating']             ?? 1000);
$cur_streak = (int)($player['current_win_streak'] ?? 0);
$max_streak = (int)($player['max_win_streak']      ?? 0);
$rank       = getPlayerRank($player_id);

// ── Leaderboard PvP & AI untuk modal ──
$lb_pvp = [];
$lb_ai  = [];
try { $lb_pvp = getLeaderboard(10);    } catch (Throwable) {}
try { $lb_ai  = getAILeaderboard(10);  } catch (Throwable) {}

// ── Senjata & Pilihan ──
$t_rock     = (int)($player['total_rock']     ?? 0);
$t_paper    = (int)($player['total_paper']    ?? 0);
$t_scissors = (int)($player['total_scissors'] ?? 0);
$t_choices  = $t_rock + $t_paper + $t_scissors;

$choices = [
    ['name'=>'Batu',   'icon'=>'🪨','count'=>$t_rock,    'key'=>'rock'],
    ['name'=>'Kertas', 'icon'=>'📄','count'=>$t_paper,   'key'=>'paper'],
    ['name'=>'Gunting','icon'=>'✂️','count'=>$t_scissors,'key'=>'scissors'],
];
usort($choices, fn($a,$b) => $b['count']-$a['count']);

$rock_pct     = $t_choices > 0 ? round($t_rock     / $t_choices * 100) : 33;
$paper_pct    = $t_choices > 0 ? round($t_paper    / $t_choices * 100) : 33;
$scissors_pct = $t_choices > 0 ? round($t_scissors / $t_choices * 100) : 34;

// ── Rank Tier ──
function getRankTier(int $r): array {
    return match(true) {
        $r >= 2000 => ['name'=>'GRANDMASTER','icon'=>'👑','color'=>'#ffd700','glow'=>'rgba(255,215,0,.5)','next'=>9999],
        $r >= 1700 => ['name'=>'MASTER',     'icon'=>'💎','color'=>'#c084fc','glow'=>'rgba(192,132,252,.5)','next'=>2000],
        $r >= 1500 => ['name'=>'DIAMOND',    'icon'=>'🔷','color'=>'#4da6ff','glow'=>'rgba(77,166,255,.5)', 'next'=>1700],
        $r >= 1300 => ['name'=>'PLATINUM',   'icon'=>'🪙','color'=>'#7dff4d','glow'=>'rgba(125,255,77,.5)','next'=>1500],
        $r >= 1100 => ['name'=>'GOLD',       'icon'=>'🥇','color'=>'#f5c842','glow'=>'rgba(245,200,66,.5)','next'=>1300],
        $r >= 950  => ['name'=>'SILVER',     'icon'=>'🥈','color'=>'#c0c0c0','glow'=>'rgba(192,192,192,.5)','next'=>1100],
        default    => ['name'=>'BRONZE',     'icon'=>'🥉','color'=>'#cd7f32','glow'=>'rgba(205,127,50,.5)', 'next'=>950],
    };
}
$tier     = getRankTier($rating);
$prev_min = match($tier['name']) {
    'GRANDMASTER'=>2000,'MASTER'=>1700,'DIAMOND'=>1500,
    'PLATINUM'=>1300,'GOLD'=>1100,'SILVER'=>950,default=>0
};
$tier_pct = $tier['next']===9999 ? 100
          : min(100,max(0,round(($rating-$prev_min)/max(1,$tier['next']-$prev_min)*100)));

// Nama tier berikutnya untuk label progress
$next_tier_name = match($tier['name']) {
    'BRONZE'=>'SILVER','SILVER'=>'GOLD','GOLD'=>'PLATINUM',
    'PLATINUM'=>'DIAMOND','DIAMOND'=>'MASTER','MASTER'=>'GRANDMASTER',
    default=>'MAX'
};

// Semua tier untuk mini-map (urut rendah ke tinggi)
$all_tiers_map = [
    ['name'=>'BRONZE',     'min'=>0,    'color'=>'#cd7f32'],
    ['name'=>'SILVER',     'min'=>950,  'color'=>'#c0c0c0'],
    ['name'=>'GOLD',       'min'=>1100, 'color'=>'#f5c842'],
    ['name'=>'PLATINUM',   'min'=>1300, 'color'=>'#7dff4d'],
    ['name'=>'DIAMOND',    'min'=>1500, 'color'=>'#4da6ff'],
    ['name'=>'MASTER',     'min'=>1700, 'color'=>'#c084fc'],
    ['name'=>'GRANDMASTER','min'=>2000, 'color'=>'#ffd700'],
];

// ── AI Rating & Tier — ambil dari kolom ai_rating (BUKAN rating PvP) ──
$ai_rating = 1000; // default base rating
$peak_ai_rating = 0;
try {
    $db_air   = getDB();
    $row_air2 = $db_air->prepare("SELECT COALESCE(ai_rating,1000) AS ai_rating, COALESCE(peak_ai_rating,0) AS peak_ai_rating FROM players WHERE id = ? LIMIT 1");
    $row_air2->execute([$player_id]);
    $r2 = $row_air2->fetch();
    if ($r2) { $ai_rating = (int)$r2['ai_rating']; $peak_ai_rating = (int)$r2['peak_ai_rating']; }
} catch (Throwable) {}
$ai_tier     = getRankTier($ai_rating);
$ai_prev_min = match($ai_tier['name']) {
    'GRANDMASTER'=>2000,'MASTER'=>1700,'DIAMOND'=>1500,
    'PLATINUM'=>1300,'GOLD'=>1100,'SILVER'=>950,default=>0
};
$ai_tier_pct = $ai_tier['next']===9999 ? 100
             : min(100,max(0,round(($ai_rating-$ai_prev_min)/max(1,$ai_tier['next']-$ai_prev_min)*100)));

// ── AI Rank (peringkat berdasarkan kolom ai_rating) ──
$ai_rank = 1;
try {
    $db_air  = getDB();
    $stmt_air = $db_air->prepare("
        SELECT COUNT(*)+1 AS ai_rank
        FROM players
        WHERE COALESCE(ai_rating, 1000) > (SELECT COALESCE(ai_rating, 1000) FROM players WHERE id = ?)
    ");
    $stmt_air->execute([$player_id]);
    $row_air = $stmt_air->fetch();
    if ($row_air) $ai_rank = (int)$row_air['ai_rank'];
} catch (Throwable) {}

// ── Match History ──
$pvp_matches = [];
$ai_matches  = [];
try { $pvp_matches = getPlayerMatchHistory($player_id, 10); } catch (Throwable) {}
try { $ai_matches  = getPlayerAIHistory($player_id, 10);    } catch (Throwable) {}

$avg_pvp_duration = 0;
$avg_ai_duration  = 0;
if (!empty($pvp_matches))
    $avg_pvp_duration = round(array_sum(array_column($pvp_matches,'duration_sec'))/count($pvp_matches));
if (!empty($ai_matches))
    $avg_ai_duration  = round(array_sum(array_column($ai_matches,'duration_sec'))/count($ai_matches));

$total_all = $total_pvp + $total_ai;
$total_rounds_pvp = array_sum(array_map(
    fn($m) => ($m['player1_round_wins']??0)+($m['player2_round_wins']??0), $pvp_matches));
$total_rounds_ai  = array_sum(array_map(
    fn($m) => ($m['player_round_wins']??0)+($m['ai_round_wins']??0), $ai_matches));

// ── Definisi 24 Kartu PvP ──
$ALL_CARDS = [
    'drain_life'     => ['name'=>'Drain Life 1',     'icon'=>'🩸','rarity'=>'common','desc'=>'Setiap menang +10 HP. Aktif 3 game.'],
    'gambling1'      => ['name'=>'The Gambling I',   'icon'=>'🎲','rarity'=>'common','desc'=>'Menang +10 dmg, kalah +10 dmg diterima.'],
    'safe_play1'     => ['name'=>'Safe Play I',      'icon'=>'🛡','rarity'=>'common','desc'=>'Kalah = 0 dmg, Menang = 50% dmg. 1 game.'],
    'barrier'        => ['name'=>'Barrier 1',        'icon'=>'🔮','rarity'=>'common','desc'=>'Kalah = 50% damage. Aktif sampai kalah 1x.'],
    'critical_attack'=> ['name'=>'Critical Attack',  'icon'=>'⚡','rarity'=>'common','desc'=>'50% chance +30 dmg saat menang. Aktif 2 game.'],
    'tie_breaker'    => ['name'=>'Tie Breaker',      'icon'=>'⚖️','rarity'=>'common','desc'=>'Seri jadi menang untukmu.'],
    'shield1'        => ['name'=>'Shield I',         'icon'=>'🛡️','rarity'=>'common','desc'=>'+30 HP shield. Menyerap damage musuh.'],
    'god_attack1'    => ['name'=>'God Attack I',     'icon'=>'⚡','rarity'=>'common','desc'=>'2× damage saat menang (5% chance 3×).'],
    'gambling2'      => ['name'=>'The Gambling II',  'icon'=>'🃏','rarity'=>'rare',  'desc'=>'Menang +30 dmg, kalah +30 dmg diterima.'],
    'block_one'      => ['name'=>'Block One',        'icon'=>'🚫','rarity'=>'rare',  'desc'=>'Lawan hanya bisa pakai 1 kartu ronde ini.'],
    'steal_hp'       => ['name'=>'Steal HP 1',       'icon'=>'💉','rarity'=>'rare',  'desc'=>'-20 HP lawan → +20 Shield kamu.'],
    'repeat'         => ['name'=>'Repeat',           'icon'=>'🔁','rarity'=>'rare',  'desc'=>'Jika kalah, ronde diulang.'],
    'safe_play2'     => ['name'=>'Safe Play II',     'icon'=>'🛡','rarity'=>'rare',  'desc'=>'Kalah = 0 dmg, Menang = 20 dmg normal.'],
    'god_attack2'    => ['name'=>'God Attack II',    'icon'=>'⚔️','rarity'=>'rare',  'desc'=>'2× damage saat menang (20% chance 3×).'],
    'shield2'        => ['name'=>'Shield II',        'icon'=>'🔷','rarity'=>'rare',  'desc'=>'+60 HP shield. Menyerap damage musuh.'],
    'gambling3'      => ['name'=>'The Gambling III', 'icon'=>'🎰','rarity'=>'epic',  'desc'=>'Menang +50 dmg, kalah +20 dmg diterima.'],
    'reverse_result' => ['name'=>'Reverse Result',   'icon'=>'🔄','rarity'=>'epic',  'desc'=>'Kalah/Seri → Menang. 3 kesempatan.'],
    'god_attack3'    => ['name'=>'God Attack III',   'icon'=>'💀','rarity'=>'epic',  'desc'=>'2× damage saat menang (50% chance 3×).'],
    'drain_life_2'   => ['name'=>'Drain Life 2',     'icon'=>'🩸','rarity'=>'epic',  'desc'=>'Menang: musuh -10 HP & kamu +25 HP.'],
    'steal_hp2'      => ['name'=>'Steal HP 2',       'icon'=>'🩻','rarity'=>'epic',  'desc'=>'-50 HP lawan → +50 Shield kamu.'],
    'double_damage'  => ['name'=>'Barrier 2',        'icon'=>'🔮','rarity'=>'epic',  'desc'=>'Kalah = 25% damage. Aktif sampai kalah 1x.'],
    'full_damage'    => ['name'=>'Full Damage',      'icon'=>'💥','rarity'=>'legend','desc'=>'Damage ×5 (total 100)! Aktif hingga menang pertama.'],
    'shield3'        => ['name'=>'Shield III',       'icon'=>'🌟','rarity'=>'legend','desc'=>'+100 shield besar!'],
    'absolute_reset' => ['name'=>'Absolute Reset',   'icon'=>'♾️','rarity'=>'legend','desc'=>'Reset match ke ronde 1 game 1!'],
];

// ── Ambil 3 Kartu Favorit dari tabel player_card_usage ──
$card_usage_counts = [];
try {
    $db_fav  = getDB();
    $stmt_fav = $db_fav->prepare("
        SELECT card_id, use_count
        FROM player_card_usage
        WHERE player_id = ?
        ORDER BY use_count DESC
        LIMIT 24
    ");
    $stmt_fav->execute([$player_id]);
    foreach ($stmt_fav->fetchAll() as $row_fav) {
        $cid = $row_fav['card_id'];
        if (isset($ALL_CARDS[$cid])) {
            $card_usage_counts[$cid] = (int)$row_fav['use_count'];
        }
    }
} catch (Throwable) {}

// Urutkan & ambil 3 teratas
arsort($card_usage_counts);
$top3_card_ids = array_slice(array_keys($card_usage_counts), 0, 3);
$fav_cards = [];
foreach ($top3_card_ids as $cid) {
    $fav_cards[] = array_merge($ALL_CARDS[$cid], [
        'id'    => $cid,
        'uses'  => $card_usage_counts[$cid],
    ]);
}

// ── Ambil 3 Kartu Favorit VS AI dari tabel ai_card_usage (fallback: player_card_usage) ──
$ai_card_usage_counts = [];
try {
    $db_aifav  = getDB();
    // Coba tabel ai_card_usage dulu
    $stmt_aifav = $db_aifav->prepare("
        SELECT card_id, use_count
        FROM ai_card_usage
        WHERE player_id = ?
        ORDER BY use_count DESC
        LIMIT 24
    ");
    $stmt_aifav->execute([$player_id]);
    foreach ($stmt_aifav->fetchAll() as $row_aifav) {
        $cid = $row_aifav['card_id'];
        if (isset($ALL_CARDS[$cid])) {
            $ai_card_usage_counts[$cid] = (int)$row_aifav['use_count'];
        }
    }
} catch (Throwable) {}

// Fallback ke player_card_usage jika tabel ai_card_usage tidak ada atau kosong
if (empty($ai_card_usage_counts)) {
    $ai_card_usage_counts = $card_usage_counts;
}

arsort($ai_card_usage_counts);
$ai_top3_card_ids = array_slice(array_keys($ai_card_usage_counts), 0, 3);
$ai_fav_cards = [];
foreach ($ai_top3_card_ids as $cid) {
    $ai_fav_cards[] = array_merge($ALL_CARDS[$cid], [
        'id'   => $cid,
        'uses' => $ai_card_usage_counts[$cid],
    ]);
}

// Warna per rarity
$rarity_meta = [
    'common' => ['color'=>'#c0c0c0','glow'=>'rgba(192,192,192,.5)','border'=>'rgba(192,192,192,.25)','grad'=>'linear-gradient(135deg,rgba(192,192,192,.09),rgba(192,192,192,.02))','label'=>'COMMON'],
    'rare'   => ['color'=>'#4da6ff','glow'=>'rgba(77,166,255,.55)', 'border'=>'rgba(77,166,255,.38)', 'grad'=>'linear-gradient(135deg,rgba(77,166,255,.12),rgba(77,166,255,.03))','label'=>'RARE'],
    'epic'   => ['color'=>'#c084fc','glow'=>'rgba(192,132,252,.55)','border'=>'rgba(192,132,252,.38)','grad'=>'linear-gradient(135deg,rgba(192,132,252,.12),rgba(192,132,252,.03))','label'=>'EPIC'],
    'legend' => ['color'=>'#ffd700','glow'=>'rgba(255,215,0,.55)',  'border'=>'rgba(255,215,0,.38)',  'grad'=>'linear-gradient(135deg,rgba(255,215,0,.14),rgba(255,215,0,.03))','label'=>'LEGEND'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Statistik – Battle Arena</title>
<!-- TEMA: Identik main_menu.php -->
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Bebas+Neue&family=Russo+One&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --rock:#ff4d4d;--paper:#4da6ff;--scissors:#7dff4d;
  --gr:rgba(255,77,77,.6);--gp:rgba(77,166,255,.6);--gs:rgba(125,255,77,.6);
  --dark:#05060d;--mid:#0b0d1a;--card:rgba(255,255,255,.028);
  --text:#eef0ff;--muted:rgba(238,240,255,.38);--border:rgba(238,240,255,.07);
  --gold:#ffd700;--win:#7dff4d;--lose:#ff5e5e;--draw:#8899bb;
  
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
html,body{min-height:100%;background:var(--dark);font-family:'Rajdhani',sans-serif;color:var(--text);overflow-x:hidden}

/* ── LAYERS (identik main_menu) ── */
canvas#bg{position:fixed;inset:0;z-index:0;pointer-events:none}
.hex-layer{position:fixed;inset:0;z-index:1;pointer-events:none;opacity:.045;
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='60' height='104'%3E%3Cpolygon points='30,2 58,17 58,47 30,62 2,47 2,17' fill='none' stroke='%234da6ff' stroke-width='0.8'/%3E%3Cpolygon points='30,52 58,67 58,97 30,112 2,97 2,67' fill='none' stroke='%234da6ff' stroke-width='0.8'/%3E%3C/svg%3E");
  background-size:60px 104px}
.noise{position:fixed;inset:0;z-index:2;pointer-events:none;opacity:.03;
  background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
  background-size:200px}
.elines{position:fixed;inset:0;z-index:3;pointer-events:none;overflow:hidden}
.el{position:absolute;width:1px;background:linear-gradient(to bottom,transparent,rgba(77,166,255,.4),transparent);animation:elfall linear infinite}
@keyframes elfall{from{transform:translateY(-100vh);opacity:0}10%,90%{opacity:1}to{transform:translateY(100vh);opacity:0}}
.scanline{position:fixed;inset:0;z-index:4;pointer-events:none;
  background:repeating-linear-gradient(to bottom,transparent 0,transparent 3px,rgba(0,0,0,.07) 3px,rgba(0,0,0,.07) 4px)}
.vignette{position:fixed;inset:0;z-index:4;pointer-events:none;
  background:radial-gradient(ellipse at center,transparent 40%,rgba(0,0,0,.55) 100%)}
.particles{position:fixed;inset:0;z-index:3;pointer-events:none;overflow:hidden}
.p{position:absolute;border-radius:50%;animation:pfloat linear infinite}
@keyframes pfloat{from{transform:translateY(110vh) rotate(0deg);opacity:0}10%,90%{opacity:1}to{transform:translateY(-10vh) rotate(360deg);opacity:0}}

/* ── CORNER ACCENTS (identik main_menu) ── */
.corner{position:fixed;z-index:6;pointer-events:none}
.corner::before,.corner::after{content:'';position:absolute;background:rgba(77,166,255,.5)}
.corner::before{width:2px;height:50px}.corner::after{width:50px;height:2px}
.c-tl{top:20px;left:20px}.c-tr{top:20px;right:20px;transform:scaleX(-1)}
.c-bl{bottom:20px;left:20px;transform:scaleY(-1)}.c-br{bottom:20px;right:20px;transform:scale(-1)}
.corner::before,.corner::after{top:0;left:0}

/* ── TOPBAR (gaya main_menu player bar) ── */
.pbar{
  position:sticky;top:0;z-index:30;
  display:flex;align-items:center;justify-content:space-between;
  padding:10px 28px;
  background:linear-gradient(180deg,rgba(5,6,13,.92) 0%,rgba(5,6,13,.6) 100%);
  border-bottom:1px solid var(--border);backdrop-filter:blur(24px);
}
.pinfo{display:flex;align-items:center;gap:11px;text-decoration:none;cursor:pointer;
  padding:5px 14px 5px 5px;border:1px solid transparent;transition:all .25s;
  clip-path:polygon(10px 0%,100% 0%,calc(100% - 10px) 100%,0% 100%)}
.pinfo:hover{background:rgba(77,166,255,.07);border-color:rgba(77,166,255,.22)}
.pav{width:42px;height:42px;font-size:20px;
  background:linear-gradient(135deg,rgba(77,166,255,.18),rgba(125,255,77,.1));
  border:1.5px solid var(--rc);
  display:flex;align-items:center;justify-content:center;
  box-shadow:0 0 20px var(--rg);transition:all .25s;
  clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%)}
.pname{font-family:'Russo One',sans-serif;font-size:.76rem;color:var(--text);letter-spacing:.1em}
.pid{font-family:'Rajdhani',sans-serif;font-size:.65rem;color:var(--muted);letter-spacing:.06em;margin-top:1px}
.tb-right{display:flex;align-items:center;gap:10px}
.live-dot{display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--scissors);
  box-shadow:0 0 8px rgba(125,255,77,.8);animation:ldot 1.5s ease-in-out infinite}
@keyframes ldot{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.7)}}
.live-label{font-family:'Rajdhani',sans-serif;font-size:.65rem;color:var(--scissors);font-weight:700;letter-spacing:.14em}
.btn-back{font-family:'Rajdhani',sans-serif;font-size:.7rem;font-weight:700;
  letter-spacing:.18em;text-transform:uppercase;
  color:rgba(77,166,255,.85);
  background:transparent;
  border:1px solid rgba(77,166,255,.2);
  padding:8px 20px;cursor:pointer;transition:all .2s;text-decoration:none;
  clip-path:polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%)}
.btn-back:hover{background:rgba(77,166,255,.18);border-color:rgba(77,166,255,.45);color:#4da6ff}

/* ── MAIN CONTENT (layout dari history.php) ── */
.main-content{position:relative;z-index:10;padding:32px 24px 80px;max-width:960px;margin:0 auto}

/* ── PAGE HEADER (gaya main_menu title) ── */
.page-header{text-align:center;margin-bottom:2.2rem}
.atag{display:flex;align-items:center;justify-content:center;gap:14px;
  font-family:'Rajdhani',sans-serif;font-size:11px;font-weight:700;
  letter-spacing:.55em;text-transform:uppercase;color:var(--paper);margin-bottom:.8rem}
.atag-line{width:44px;height:1px;background:linear-gradient(to right,transparent,var(--paper));opacity:.5}
.page-title{
  font-family:'Bebas Neue',sans-serif;
  font-size:clamp(2.2rem,7vw,4.5rem);line-height:.9;letter-spacing:.06em;
  background:linear-gradient(135deg,#ff4d4d 0%,#eef0ff 40%,#4da6ff 70%,#7dff4d 100%);
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
  margin-bottom:.5rem;position:relative}
.page-title::before,.page-title::after{
  content:attr(data-text);position:absolute;inset:0;
  font-family:'Bebas Neue',sans-serif;font-size:inherit;letter-spacing:inherit;pointer-events:none;
  -webkit-background-clip:unset;-webkit-text-fill-color:transparent;background-clip:unset}
.page-title::before{color:var(--rock);clip-path:polygon(0 20%,100% 20%,100% 38%,0 38%);animation:g1 6s infinite steps(1);opacity:.55}
.page-title::after{color:var(--paper);clip-path:polygon(0 62%,100% 62%,100% 76%,0 76%);animation:g2 6s infinite steps(1);opacity:.55}
@keyframes g1{0%,93%{transform:none;opacity:0}94%{transform:translateX(-4px);opacity:.55}95%{transform:translateX(4px) skewX(6deg);opacity:.55}96%{transform:none;opacity:0}}
@keyframes g2{0%,95%{transform:none;opacity:0}96%{transform:translateX(4px);opacity:.55}97%{transform:translateX(-4px) skewX(-4deg);opacity:.55}98%{transform:none;opacity:0}}
.page-subtitle{font-family:'Rajdhani',sans-serif;font-size:.82rem;color:var(--muted);font-weight:600;letter-spacing:.18em;text-transform:uppercase}
.last-update{font-size:.6rem;color:rgba(125,255,77,.5);margin-top:.4rem;letter-spacing:.1em;font-weight:600}

/* ── MODE TOGGLE (gaya history, warna main_menu) ── */
.mode-toggle{display:flex;gap:6px;background:rgba(255,255,255,.025);
  border:1px solid var(--border);padding:5px;width:fit-content;margin:0 auto 2rem;
  clip-path:polygon(10px 0%,100% 0%,calc(100% - 10px) 100%,0% 100%)}
.mode-btn{font-family:'Russo One',sans-serif;font-size:.62rem;letter-spacing:.15em;text-transform:uppercase;
  padding:9px 22px;border:1px solid transparent;cursor:pointer;transition:all .25s;
  background:none;color:var(--muted);display:flex;align-items:center;gap:8px;
  clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%)}
.mode-btn.active-ranked{background:rgba(255,77,77,.15);border-color:rgba(255,77,77,.5);color:#ff9090;box-shadow:0 0 20px rgba(255,77,77,.15)}
.mode-btn.active-ai{background:rgba(77,166,255,.15);border-color:rgba(77,166,255,.5);color:#90c4ff;box-shadow:0 0 20px rgba(77,166,255,.15)}
.mode-btn:not(.active-ranked):not(.active-ai):hover{background:rgba(238,240,255,.04);color:var(--text)}

/* ── STATS GRID ── */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(155px,1fr));gap:14px;margin-bottom:1.5rem}
.stat-card{
  background:rgba(238,240,255,.025);border:1px solid var(--border);
  padding:18px 20px;position:relative;overflow:hidden;transition:transform .25s,border-color .25s;
  clip-path:polygon(10px 0%,100% 0%,calc(100% - 10px) 100%,0% 100%)}
.stat-card:hover{transform:translateY(-4px);border-color:rgba(238,240,255,.12)}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px}
.stat-card.type-ranked::before{background:linear-gradient(90deg,var(--rock),transparent)}
.stat-card.type-ai::before{background:linear-gradient(90deg,var(--paper),transparent)}
.stat-card.type-win::before{background:linear-gradient(90deg,var(--win),transparent)}
.stat-card.type-lose::before{background:linear-gradient(90deg,var(--lose),transparent)}
.stat-card.type-gold::before{background:linear-gradient(90deg,var(--gold),transparent)}
.stat-card-icon{font-size:1.4rem;margin-bottom:8px}
.stat-card-value{font-family:'Bebas Neue',sans-serif;font-size:clamp(1.6rem,3.5vw,2.4rem);color:var(--text);letter-spacing:.04em;line-height:1;margin-bottom:4px}
.stat-card-label{font-family:'Rajdhani',sans-serif;font-size:.6rem;letter-spacing:.25em;text-transform:uppercase;color:var(--muted);font-weight:700}
.stat-card-sub{font-family:'Rajdhani',sans-serif;font-size:.65rem;color:var(--muted);margin-top:4px}
.winrate-wrap{display:flex;align-items:center;gap:14px}
.winrate-circle{width:64px;height:64px;flex-shrink:0}
.winrate-circle svg{transform:rotate(-90deg)}
.winrate-circle .track{fill:none;stroke:rgba(238,240,255,.07);stroke-width:5}
.winrate-circle .fill{fill:none;stroke-width:5;stroke-linecap:round;transition:stroke-dashoffset 1.2s ease}
.winrate-pct{font-family:'Bebas Neue',sans-serif;font-size:1.6rem;letter-spacing:.04em;line-height:1}
.winrate-label{font-family:'Rajdhani',sans-serif;font-size:.58rem;letter-spacing:.22em;text-transform:uppercase;color:var(--muted);font-weight:700}

/* ── SECTION DIVIDER (gaya main_menu) ── */
.section-row{display:flex;align-items:center;gap:14px;margin-bottom:1rem}
.section-line{flex:1;height:1px;background:var(--border)}
.section-title{font-family:'Russo One',sans-serif;font-size:.6rem;font-weight:400;letter-spacing:.3em;color:var(--muted);text-transform:uppercase;white-space:nowrap}

/* ── CHOICE STATS BARS (layout history) ── */
.choice-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:1.5rem}
.choice-card{
  background:rgba(238,240,255,.025);border:1px solid var(--border);
  padding:16px;text-align:center;transition:transform .2s,border-color .25s;
  clip-path:polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%)}
.choice-card:hover{transform:translateY(-3px)}
.choice-card.rock-card{border-color:rgba(255,77,77,.3)}
.choice-card.paper-card{border-color:rgba(77,166,255,.3)}
.choice-card.scissors-card{border-color:rgba(125,255,77,.3)}
.choice-emoji{font-size:1.8rem;margin-bottom:6px}
.choice-name{font-family:'Russo One',sans-serif;font-size:.55rem;letter-spacing:.18em;text-transform:uppercase;margin-bottom:10px}
.rock-card .choice-name{color:var(--rock)}
.paper-card .choice-name{color:var(--paper)}
.scissors-card .choice-name{color:var(--scissors)}
.choice-bar-wrap{height:6px;background:rgba(238,240,255,.06);border-radius:100px;margin-bottom:6px;overflow:hidden}
.choice-bar-fill{height:100%;border-radius:100px;width:0%;transition:width 1.2s cubic-bezier(.4,0,.2,1)}
.rock-card .choice-bar-fill{background:linear-gradient(90deg,var(--rock),#ff9090)}
.paper-card .choice-bar-fill{background:linear-gradient(90deg,var(--paper),#90c4ff)}
.scissors-card .choice-bar-fill{background:linear-gradient(90deg,var(--scissors),#a8e860)}
.choice-pct{font-family:'Bebas Neue',sans-serif;font-size:1.2rem;letter-spacing:.04em}
.choice-count{font-family:'Rajdhani',sans-serif;font-size:.62rem;color:var(--muted);margin-top:2px;font-weight:600}

/* ── STREAK ── */
.streak-section{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:1.5rem}
.streak-card{
  background:rgba(238,240,255,.025);border:1px solid var(--border);
  padding:18px 20px;display:flex;align-items:center;gap:16px;
  clip-path:polygon(10px 0%,100% 0%,calc(100% - 10px) 100%,0% 100%);
  transition:border-color .25s}
.streak-card:hover{border-color:rgba(238,240,255,.12)}
.streak-icon{font-size:2rem;flex-shrink:0}
.streak-value{font-family:'Bebas Neue',sans-serif;font-size:1.8rem;letter-spacing:.04em;color:var(--text)}
.streak-value span{font-family:'Rajdhani',sans-serif;font-size:.75rem;font-weight:600;color:var(--muted);margin-left:4px;vertical-align:middle}
.streak-label{font-family:'Rajdhani',sans-serif;font-size:.6rem;letter-spacing:.2em;text-transform:uppercase;color:var(--muted);margin-top:2px;font-weight:700}

/* ── 3 KARTU FAVORIT PVP ── */
.fav-cards-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:1.5rem}
.fav-card{
  position:relative;padding:20px 14px 16px;text-align:center;
  border:1px solid var(--rarity-border);background:var(--rarity-grad);overflow:hidden;cursor:default;
  clip-path:polygon(14px 0%,100% 0%,calc(100% - 14px) 100%,0% 100%);
  transition:transform .32s cubic-bezier(.34,1.56,.64,1),box-shadow .28s ease;
  backdrop-filter:blur(2px);
}
.fav-card:hover{transform:translateY(-8px) scale(1.02);box-shadow:0 12px 30px rgba(0,0,0,.3),0 0 15px var(--rarity-glow)}

.fav-card .shine{position:absolute;top:0;left:-100%;width:55%;height:100%;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,.07),transparent);
  transform:skewX(-15deg);transition:left .6s ease;pointer-events:none}
.fav-card:hover .shine{left:160%}

.fav-corner{position:absolute;width:14px;height:14px;opacity:0;transition:opacity .3s;color:var(--rarity-color)}
.fav-corner::before,.fav-corner::after{content:'';position:absolute;background:currentColor}
.fav-corner::before{width:1.5px;height:10px}.fav-corner::after{width:10px;height:1.5px}
.fav-tl{top:6px;left:6px}.fav-br{bottom:6px;right:6px;transform:scale(-1)}
.fav-card:hover .fav-corner{opacity:.9}

.fav-rank-badge{position:absolute;top:-1px;left:50%;transform:translateX(-50%);
  font-family:'Russo One',sans-serif;font-size:.48rem;letter-spacing:.16em;
  padding:3px 10px;white-space:nowrap;
  clip-path:polygon(4px 0%,100% 0%,calc(100% - 4px) 100%,0% 100%);
  background:var(--rarity-badge-bg);border:1px solid var(--rarity-border);color:var(--rarity-color)}

.fav-icon{font-size:2.2rem;display:block;margin:10px 0 5px;
  filter:drop-shadow(0 2px 10px rgba(0,0,0,.6));transition:transform .3s}
.fav-card:hover .fav-icon{transform:scale(1.18) rotate(-6deg)}

.fav-rarity{font-family:'Russo One',sans-serif;font-size:.52rem;letter-spacing:.16em;text-transform:uppercase;margin-bottom:4px;color:var(--rarity-color)}
.fav-name{font-family:'Rajdhani',sans-serif;font-size:.82rem;font-weight:700;letter-spacing:.04em;margin-bottom:6px;line-height:1.2;color:var(--text)}
.fav-desc{font-family:'Rajdhani',sans-serif;font-size:.58rem;color:var(--muted);line-height:1.45;letter-spacing:.03em;font-weight:600;margin-bottom:8px}

.fav-uses-bar-wrap{height:5px;background:rgba(238,240,255,.12);border-radius:100px;overflow:hidden;margin-bottom:5px}
.fav-uses-bar{height:100%;border-radius:100px;transition:width 1.3s cubic-bezier(.4,0,.2,1);background:var(--rarity-color);box-shadow:0 0 8px var(--rarity-glow)}
.fav-uses-count{font-family:'Bebas Neue',sans-serif;font-size:1.1rem;letter-spacing:.06em;line-height:1;color:var(--rarity-color);text-shadow:0 0 18px var(--rarity-glow)}
.fav-uses-lbl{font-family:'Rajdhani',sans-serif;font-size:.52rem;color:var(--muted);font-weight:700;letter-spacing:.16em;text-transform:uppercase}

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

/* Kartu kosong (belum ada data) */
.fav-card-empty{
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  gap:8px;padding:30px 14px;
  background:rgba(238,240,255,.045);border:1px dashed rgba(238,240,255,.22);
  clip-path:polygon(14px 0%,100% 0%,calc(100% - 14px) 100%,0% 100%)}
.fav-empty-icon{font-size:2rem;opacity:.25}
.fav-empty-text{font-family:'Rajdhani',sans-serif;font-size:.65rem;color:var(--muted);font-weight:600;letter-spacing:.1em;text-align:center}

/* ── HISTORY TABLE (identik history.php) ── */
.history-section{
  background:rgba(238,240,255,.02);border:1px solid var(--border);overflow:hidden;margin-bottom:1.5rem;
  clip-path:polygon(12px 0%,100% 0%,calc(100% - 12px) 100%,0% 100%)}
.history-header{display:flex;align-items:center;justify-content:space-between;padding:16px 22px;border-bottom:1px solid var(--border)}
.history-header-title{font-family:'Russo One',sans-serif;font-size:.68rem;letter-spacing:.2em;color:var(--text)}
.history-count{font-family:'Rajdhani',sans-serif;font-size:.65rem;color:var(--muted);font-weight:700;
  background:rgba(238,240,255,.04);border:1px solid var(--border);
  clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%);padding:3px 12px}
.history-list{padding:8px 0}
.history-row{display:grid;grid-template-columns:36px 1fr 80px 80px 80px 100px;
  align-items:center;gap:12px;padding:12px 22px;
  border-bottom:1px solid rgba(238,240,255,.03);transition:background .2s;
  animation:fadeSlide .35s ease both}
.history-row:last-child{border-bottom:none}
.history-row:hover{background:rgba(238,240,255,.02)}
@keyframes fadeSlide{from{opacity:0;transform:translateX(-10px)}to{opacity:1;transform:translateX(0)}}
.row-num{font-family:'Bebas Neue',sans-serif;font-size:.9rem;letter-spacing:.08em;color:var(--muted);text-align:center}
.row-match{display:flex;flex-direction:column;gap:2px}
.row-match-id{font-family:'Russo One',sans-serif;font-size:.62rem;color:var(--text);letter-spacing:.06em}
.row-match-time{font-family:'Rajdhani',sans-serif;font-size:.6rem;color:var(--muted);font-weight:600}
.row-choice{display:flex;align-items:center;gap:6px;font-family:'Rajdhani',sans-serif;font-size:.7rem;color:var(--text);font-weight:700}
.choice-icon-sm{font-size:1.1rem}
.row-vs-choice{font-family:'Rajdhani',sans-serif;font-size:.68rem;color:var(--muted);font-weight:600;letter-spacing:.06em}
.row-score{font-family:'Bebas Neue',sans-serif;font-size:1rem;letter-spacing:.06em;text-align:center}
.row-result{display:flex;justify-content:flex-end}
.result-badge{font-family:'Russo One',sans-serif;font-size:.55rem;letter-spacing:.14em;
  padding:5px 12px;border:1px solid;
  clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%)}
.badge-win{background:rgba(125,255,77,.1);border-color:rgba(125,255,77,.4);color:var(--win)}
.badge-lose{background:rgba(255,94,94,.1);border-color:rgba(255,94,94,.4);color:var(--lose)}
.badge-draw{background:rgba(136,153,187,.08);border-color:rgba(136,153,187,.25);color:var(--draw)}

/* ── CARDS (stats PvP/AI) ── */
.card{
  background:rgba(238,240,255,.025);border:1px solid var(--border);
  padding:1.4rem;position:relative;overflow:hidden;transition:border-color .25s;
  clip-path:polygon(12px 0%,100% 0%,calc(100% - 12px) 100%,0% 100%)}
.card:hover{border-color:rgba(238,240,255,.1)}
.card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px}
.cv-pvp::before{background:linear-gradient(90deg,var(--rock),transparent)}
.cv-ai::before{background:linear-gradient(90deg,var(--paper),transparent)}
.cv-choice::before{background:linear-gradient(90deg,var(--scissors),transparent)}
.cv-hist-pvp::before{background:linear-gradient(90deg,var(--rock),var(--paper))}
.cv-hist-ai::before{background:linear-gradient(90deg,var(--paper),var(--scissors))}
.cv-session::before{background:linear-gradient(90deg,#ff9060,transparent)}
.cv-combo::before{background:linear-gradient(90deg,#c084fc,var(--paper),transparent)}
.card-ttl{font-family:'Russo One',sans-serif;font-size:.6rem;letter-spacing:.28em;
  text-transform:uppercase;color:var(--muted);margin-bottom:1.1rem;display:flex;align-items:center;gap:6px}

/* ── WINRATE BOXES ── */
.wr-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:.9rem}
.wr-box{background:rgba(238,240,255,.025);border:1px solid var(--border);
  clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%);padding:12px 8px;text-align:center}
.wr-box-val{font-family:'Bebas Neue',sans-serif;font-size:1.6rem;letter-spacing:.04em;line-height:1;margin-bottom:3px}
.wr-box-lbl{font-family:'Rajdhani',sans-serif;font-size:.55rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:var(--muted)}
.c-win{color:var(--win)}.c-lose{color:var(--lose)}.c-draw{color:var(--draw)}

/* ── WIN TRACK BAR ── */
.wr-track{height:8px;border-radius:100px;overflow:hidden;display:flex;margin-bottom:6px;background:rgba(238,240,255,.04)}
.wr-seg-w{background:linear-gradient(90deg,#3dcc6e,var(--win))}
.wr-seg-d{background:var(--draw)}
.wr-seg-l{background:linear-gradient(90deg,#e24b4a,var(--lose))}
.wr-legend{display:flex;gap:12px;flex-wrap:wrap}
.wr-it{display:flex;align-items:center;gap:4px;font-family:'Rajdhani',sans-serif;font-size:.62rem;color:var(--muted);font-weight:700}
.wr-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0}

/* ── RATING BAR ── */
/* ── RANK PROGRESS PANEL (gaya lb-cta dari lobby_pvp) ── */
.rating-prog{
  position:relative;overflow:hidden;
  display:grid;grid-template-columns:1fr auto auto;gap:0;
  align-items:center;
  background:linear-gradient(135deg,rgba(255,255,255,.038) 0%,rgba(255,255,255,.018) 100%);
  border:1px solid var(--rc);
  clip-path:polygon(12px 0%,100% 0%,calc(100% - 12px) 100%,0% 100%);
  box-shadow:0 0 30px var(--rg),inset 0 0 60px rgba(255,255,255,.012);
  margin-bottom:1.6rem;margin-top:.4rem;
  transition:all .32s cubic-bezier(.34,1.2,.64,1);
}
.rating-prog:hover{
  transform:translateY(-2px);
  box-shadow:0 0 55px var(--rg),0 8px 30px rgba(0,0,0,.4),inset 0 0 80px rgba(255,255,255,.02);
}
.rp-shimmer{
  position:absolute;top:0;left:-150%;width:80%;height:100%;pointer-events:none;
  background:linear-gradient(105deg,transparent 20%,rgba(255,255,255,.04) 50%,transparent 80%);
  transform:skewX(-20deg);animation:lb-shimmer 3.5s ease-in-out infinite;
}
@keyframes lb-shimmer{0%,100%{left:-150%}60%,100%{left:160%}}
.rp-topbar{position:absolute;top:0;left:0;right:0;height:1.5px;opacity:.55;pointer-events:none;background:linear-gradient(90deg,transparent,var(--rc),transparent)}

/* Left section */
.rp-left{
  padding:18px 16px 18px 22px;
  display:flex;flex-direction:column;gap:10px;
  border-right:1px solid var(--border);
}
.rp-tier{display:flex;align-items:center;gap:11px}
.rp-icon{
  font-size:2rem;
  filter:drop-shadow(0 0 14px var(--rg));
  animation:lb-icon-pulse 2.5s ease-in-out infinite;
}
@keyframes lb-icon-pulse{0%,100%{filter:drop-shadow(0 0 10px var(--rg))}50%{filter:drop-shadow(0 0 22px var(--rg))}}
.rp-name{font-family:'Russo One',sans-serif;font-size:.88rem;letter-spacing:.2em;color:var(--rc);text-transform:uppercase;line-height:1}
.rp-pts{font-family:'Bebas Neue',sans-serif;font-size:.78rem;letter-spacing:.1em;color:var(--muted);margin-top:3px}

/* progress bar inside */
.rp-bar-wrap{display:flex;flex-direction:column;gap:5px}
.rp-bar-track{
  height:3px;background:rgba(238,240,255,.08);position:relative;overflow:visible;
}
.rp-bar-fill{
  height:100%;
  background:linear-gradient(90deg,var(--rc),rgba(238,240,255,.5));
  box-shadow:0 0 10px var(--rg);
  transition:width 1.2s cubic-bezier(.34,1.56,.64,1);
  position:relative;width:0;
}
.rp-bar-dot{
  position:absolute;top:50%;right:0;transform:translate(50%,-50%);
  width:7px;height:7px;border-radius:50%;
  background:var(--rc);box-shadow:0 0 8px var(--rg);
  animation:pglow .9s ease-in-out infinite alternate;
}
.rp-bar-head{
  font-size:.6rem;color:var(--muted);letter-spacing:.1em;font-weight:600;
  font-family:'Rajdhani',sans-serif;
}

/* Center: tier mini-map */
.rp-tiers{
  display:flex;flex-direction:column;gap:3px;
  padding:14px;align-items:center;
  border-right:1px solid var(--border);
}
.rp-mini-tier{
  width:22px;height:8px;
  clip-path:polygon(3px 0%,100% 0%,calc(100% - 3px) 100%,0% 100%);
  border:1px solid var(--rtc);
  transition:all .2s;
}
.rp-mini-active{background:var(--rtc);height:14px;width:26px;box-shadow:0 0 12px var(--rtc)}
.rp-mini-reached{background:rgba(255,255,255,.06)}
.rp-mini-locked{opacity:.22;border-style:dashed}

/* Right: rank & button */
.rp-right{
  display:flex;flex-direction:column;align-items:center;
  gap:10px;padding:16px 18px;
}
.rp-rank-no{text-align:center}
.rp-rank-label{
  display:block;font-size:.48rem;letter-spacing:.28em;
  color:var(--muted);font-family:'Rajdhani',sans-serif;font-weight:700;
  text-transform:uppercase;margin-bottom:2px;
}
.rp-rank-num{
  display:block;font-family:'Bebas Neue',sans-serif;font-size:1.55rem;
  letter-spacing:.08em;color:#f5c842;
  text-shadow:0 0 20px rgba(245,200,66,.45);line-height:1;
}
.rp-lihat-ring{
  width:46px;height:46px;border-radius:50%;
  border:1.5px solid var(--rc);
  display:flex;align-items:center;justify-content:center;
  background:rgba(255,255,255,.03);
  box-shadow:0 0 18px var(--rg);
  transition:all .28s;
}
.rating-prog:hover .rp-lihat-ring{background:var(--rc);box-shadow:0 0 30px var(--rg);transform:scale(1.1)}
.rp-lihat-txt{
  font-family:'Russo One',sans-serif;font-size:.38rem;letter-spacing:.14em;
  color:var(--rc);text-align:center;line-height:1.3;
}
.rating-prog:hover .rp-lihat-txt{color:#05060d}

/* ── GRID LAYOUT (history.php) ── */
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;margin-bottom:1.2rem}

/* ── SESSION & COMBO ── */
.sess-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:9px}
.sess-box{background:rgba(238,240,255,.025);border:1px solid var(--border);
  clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%);padding:14px 12px;text-align:center}
.sess-val{font-family:'Bebas Neue',sans-serif;font-size:1.4rem;letter-spacing:.04em;line-height:1;margin-bottom:3px}
.sess-lbl{font-family:'Rajdhani',sans-serif;font-size:.55rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:var(--muted)}
.combo-row{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid rgba(238,240,255,.04)}
.combo-row:last-child{border-bottom:none;padding-bottom:0}
.combo-lbl{font-family:'Rajdhani',sans-serif;font-size:.75rem;font-weight:700;color:var(--text)}
.combo-sub{font-family:'Rajdhani',sans-serif;font-size:.6rem;color:var(--muted);margin-top:1px}
.combo-val{font-family:'Bebas Neue',sans-serif;font-size:1.2rem;letter-spacing:.04em}

/* ── HERO STAT BAR ── */
.hero-bar{display:grid;grid-template-columns:repeat(5,1fr);gap:1px;
  background:var(--border);border:1px solid var(--border);overflow:hidden;margin-bottom:1.6rem;margin-top:.4rem;
  clip-path:polygon(10px 0%,100% 0%,calc(100% - 10px) 100%,0% 100%)}
.hb-cell{background:rgba(5,6,13,.9);display:flex;flex-direction:column;align-items:center;
  justify-content:center;gap:4px;padding:18px 12px;transition:background .2s}
.hb-cell:hover{background:rgba(238,240,255,.03)}
.hb-val{font-family:'Bebas Neue',sans-serif;font-size:1.6rem;letter-spacing:.04em;line-height:1}
.hb-lbl{font-family:'Rajdhani',sans-serif;font-size:.52rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:var(--muted)}
.c-rating{color:var(--gold);text-shadow:0 0 20px rgba(255,215,0,.4)}
.c-streak{color:#ff9060;text-shadow:0 0 16px rgba(255,144,96,.3)}
.c-rank{color:var(--rc);text-shadow:0 0 16px var(--rg)}

/* ── STREAK LIVE BADGE ── */
.streak-live{display:inline-flex;align-items:center;gap:6px;margin-top:10px;
  background:rgba(255,144,96,.1);border:1px solid rgba(255,144,96,.3);
  clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%);
  padding:4px 14px;font-family:'Rajdhani',sans-serif;font-size:.65rem;font-weight:700;color:#ff9060;letter-spacing:.1em;
  animation:streak-pulse 2s ease-in-out infinite alternate}
@keyframes streak-pulse{from{box-shadow:none}to{box-shadow:0 0 16px rgba(255,144,96,.28)}}

/* ── EMPTY / LOADING ── */
.empty-state{text-align:center;padding:40px 24px;color:var(--muted)}
.empty-icon{font-size:2.5rem;margin-bottom:.8rem;display:block;opacity:.4}
.loading-state{text-align:center;padding:36px 24px;font-family:'Rajdhani',sans-serif;color:var(--muted);font-size:.8rem;font-weight:700;letter-spacing:.14em}

/* ── TOAST ── */
.toast{position:fixed;bottom:28px;left:50%;transform:translateX(-50%) translateY(20px);z-index:200;
  background:rgba(5,6,13,.97);border:1px solid rgba(238,240,255,.1);
  clip-path:polygon(12px 0%,100% 0%,calc(100% - 12px) 100%,0% 100%);
  padding:11px 28px;font-family:'Rajdhani',sans-serif;font-size:.85rem;font-weight:700;color:var(--text);
  letter-spacing:.07em;backdrop-filter:blur(16px);box-shadow:0 8px 40px rgba(0,0,0,.8);
  opacity:0;pointer-events:none;transition:opacity .3s,transform .3s}
.toast.show{opacity:1;transform:translateX(-50%) translateY(0)}

/* ── CHOICE ROWS ── */
.choice-rows{display:flex;flex-direction:column;gap:10px}
.choice-row{display:flex;align-items:center;gap:10px}
.ch-icon{font-size:1.1rem;width:24px;text-align:center;flex-shrink:0}
.ch-name{font-family:'Russo One',sans-serif;font-size:.6rem;letter-spacing:.08em;width:52px;flex-shrink:0}
.ch-track{flex:1;height:10px;background:rgba(238,240,255,.04);border-radius:100px;overflow:hidden;position:relative}
.ch-fill{height:100%;border-radius:100px;transition:width 1.4s cubic-bezier(.4,0,.2,1)}
.cf-rock{background:linear-gradient(90deg,var(--rock),#ff9090)}
.cf-paper{background:linear-gradient(90deg,var(--paper),#90c4ff)}
.cf-scissors{background:linear-gradient(90deg,var(--scissors),#a8e860)}
.ch-pct{font-family:'Bebas Neue',sans-serif;font-size:.95rem;letter-spacing:.04em;min-width:38px;text-align:right;flex-shrink:0}
.ch-count{font-family:'Rajdhani',sans-serif;font-size:.58rem;color:var(--muted);font-weight:700;min-width:36px;text-align:right;flex-shrink:0}

/* ── HIST ITEMS ── */
.hist-list{display:flex;flex-direction:column;gap:6px}
.hist-item{display:flex;align-items:center;gap:10px;
  background:rgba(238,240,255,.02);border:1px solid var(--border);
  clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%);
  padding:10px 13px;transition:all .18s}
.hist-item:hover{background:rgba(238,240,255,.04);border-color:rgba(238,240,255,.1)}
.hist-badge{font-family:'Russo One',sans-serif;font-size:.52rem;letter-spacing:.1em;
  padding:4px 10px;flex-shrink:0;white-space:nowrap;
  clip-path:polygon(4px 0%,100% 0%,calc(100% - 4px) 100%,0% 100%)}
.hb-win{background:rgba(125,255,77,.1);color:var(--win);border:1px solid rgba(125,255,77,.3)}
.hb-lose{background:rgba(255,107,107,.1);color:var(--lose);border:1px solid rgba(255,107,107,.3)}
.hb-draw{background:rgba(136,153,187,.08);color:var(--draw);border:1px solid rgba(136,153,187,.2)}
.hist-vs{flex:1;min-width:0}
.hist-opp{font-family:'Russo One',sans-serif;font-size:.7rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.hist-meta{font-family:'Rajdhani',sans-serif;font-size:.6rem;color:var(--muted);margin-top:2px;font-weight:600}
.hist-score{font-family:'Bebas Neue',sans-serif;font-size:1rem;letter-spacing:.06em;color:var(--muted);flex-shrink:0}
.hist-delta{font-family:'Bebas Neue',sans-serif;font-size:1rem;letter-spacing:.04em;min-width:34px;text-align:right;flex-shrink:0}
.d-up{color:var(--win)}.d-dn{color:var(--lose)}.d-nu{color:var(--muted)}

@media(max-width:640px){
  .history-row{grid-template-columns:28px 1fr 60px 90px}
  .row-vs-choice,.row-score{display:none}
  .stats-grid{grid-template-columns:1fr 1fr}
  .streak-section{grid-template-columns:1fr}
  .fav-cards-grid{grid-template-columns:1fr}
  .grid-2{grid-template-columns:1fr}
  .hero-bar{grid-template-columns:repeat(3,1fr)}
  .hb-cell:nth-child(4),.hb-cell:nth-child(5){display:none}
}

/* ── AI LB-CTA RANK PANEL (identik lobby.php) ── */
.ai-lb-cta{
  position:relative;overflow:hidden;
  display:grid;grid-template-columns:minmax(0,1fr) 52px 110px;gap:0;
  align-items:stretch;
  background:linear-gradient(135deg,rgba(255,255,255,.038) 0%,rgba(255,255,255,.018) 100%);
  border:1px solid var(--ai-rc);
  padding:0;
  clip-path:polygon(12px 0%,100% 0%,calc(100% - 12px) 100%,0% 100%);
  box-shadow:0 0 28px var(--ai-rg),inset 0 0 60px rgba(255,255,255,.012);
}
.ai-lb-cta .lb-cta-topbar{
  position:absolute;top:0;left:0;right:0;height:1.5px;opacity:.6;pointer-events:none;z-index:2;
}
.ai-lb-cta .lb-cta-shimmer{
  position:absolute;top:0;left:-150%;width:80%;height:100%;pointer-events:none;z-index:1;
  background:linear-gradient(105deg,transparent 20%,rgba(255,255,255,.045) 50%,transparent 80%);
  transform:skewX(-20deg);animation:ai-lb-shimmer 3.8s ease-in-out infinite;
}
@keyframes ai-lb-shimmer{0%,100%{left:-150%}65%,100%{left:160%}}

/* Left column */
.ai-lb-cta .lb-cta-left{
  padding:16px 14px 16px 20px;
  display:flex;flex-direction:column;gap:10px;
  border-right:1px solid var(--border);
  min-width:0;
}
.ai-lb-cta .lb-cta-rank-badge{
  display:flex;align-items:center;gap:12px;
}
.ai-lb-cta .lb-cta-rank-icon{
  font-size:2.2rem;flex-shrink:0;
  filter:drop-shadow(0 0 10px var(--ai-rg));
  animation:ai-lb-icon-pulse 2.8s ease-in-out infinite;
}
@keyframes ai-lb-icon-pulse{
  0%,100%{filter:drop-shadow(0 0 8px var(--ai-rg))}
  50%{filter:drop-shadow(0 0 20px var(--ai-rg))}
}
.ai-lb-cta .lb-cta-badge-info{display:flex;flex-direction:column;gap:2px;min-width:0}
.ai-lb-cta .lb-cta-rank-name{
  font-family:'Russo One',sans-serif;font-size:.9rem;
  letter-spacing:.22em;color:var(--ai-rc);text-transform:uppercase;line-height:1;
}
.ai-lb-cta .lb-cta-rank-pts{
  font-family:'Bebas Neue',sans-serif;font-size:.8rem;
  letter-spacing:.1em;color:var(--muted);
}
.ai-lb-cta .lb-cta-prog-wrap{display:flex;flex-direction:column;gap:6px}
.ai-lb-cta .lb-cta-prog-track{
  height:3px;background:rgba(238,240,255,.08);
  position:relative;overflow:visible;border-radius:2px;
}
.ai-lb-cta .lb-cta-prog-fill{
  height:100%;border-radius:2px;
  background:linear-gradient(90deg,var(--ai-rc),rgba(238,240,255,.55));
  box-shadow:0 0 10px var(--ai-rg);
  transition:width 1.2s cubic-bezier(.34,1.56,.64,1);
  position:relative;
}
.ai-lb-cta .lb-cta-prog-dot{
  position:absolute;top:50%;right:0;
  transform:translate(50%,-50%);
  width:7px;height:7px;border-radius:50%;
  background:var(--ai-rc);box-shadow:0 0 8px var(--ai-rg);
  animation:pglow .9s ease-in-out infinite alternate;
}
.ai-lb-cta .lb-cta-prog-label{
  font-size:.58rem;color:var(--muted);
  letter-spacing:.08em;font-weight:600;
  font-family:'Rajdhani',sans-serif;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}
.ai-lb-cta .lb-cta-prog-label strong{font-weight:700}

/* Center column — tier mini-map */
.ai-lb-cta .lb-cta-tiers{
  display:flex;flex-direction:column;gap:4px;
  padding:12px 0;align-items:center;justify-content:center;
  border-right:1px solid var(--border);
  width:52px;
}
.ai-lb-cta .lb-mini-tier{
  width:24px;height:7px;
  clip-path:polygon(3px 0%,100% 0%,calc(100% - 3px) 100%,0% 100%);
  border:1px solid var(--tc);
  transition:all .22s;
  background:transparent;
}
.ai-lb-cta .lmt-active{
  background:var(--tc);height:13px;width:28px;
  box-shadow:0 0 10px var(--tc),0 0 4px var(--tc);
}
.ai-lb-cta .lmt-reached{background:rgba(255,255,255,.07)}
.ai-lb-cta .lmt-locked{opacity:.2;border-style:dashed}

/* Right column */
.ai-lb-cta .lb-cta-right{
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  gap:10px;padding:14px 12px;
}
.ai-lb-cta .lb-cta-rank-no{text-align:center}
.ai-lb-cta .lb-cta-rank-label{
  display:block;font-size:.44rem;letter-spacing:.3em;
  color:var(--muted);font-family:'Rajdhani',sans-serif;
  font-weight:700;text-transform:uppercase;margin-bottom:3px;
}
.ai-lb-cta .lb-cta-rank-num{
  display:block;font-family:'Bebas Neue',sans-serif;
  font-size:1.6rem;letter-spacing:.06em;
  color:#f5c842;text-shadow:0 0 18px rgba(245,200,66,.5);line-height:1;
}
.ai-lb-cta .lb-cta-arrow-ring{
  width:42px;height:42px;border-radius:50%;
  border:1.5px solid var(--ai-rc);
  display:flex;align-items:center;justify-content:center;
  background:rgba(255,255,255,.03);
  box-shadow:0 0 16px var(--ai-rg);
}
.ai-lb-cta .lb-cta-arrow-txt{
  font-family:'Russo One',sans-serif;font-size:.4rem;
  letter-spacing:.16em;color:var(--ai-rc);
  text-align:center;line-height:1.4;
}

/* ════════ LEADERBOARD MODAL (statistik) ════════ */
.modal-overlay{
  display:none;position:fixed;inset:0;z-index:200;
  background:rgba(0,0,0,.72);backdrop-filter:blur(8px);
  align-items:center;justify-content:center;padding:16px;
}
.modal-overlay.show{display:flex}
.lb2-shell{
  position:relative;
  display:grid;grid-template-columns:320px 1fr;
  width:min(92vw,820px);height:min(88vh,620px);
  background:linear-gradient(160deg,rgba(8,10,22,.97),rgba(5,6,13,.99));
  border:1px solid rgba(238,240,255,.1);overflow:hidden;
  clip-path:polygon(14px 0%,100% 0%,calc(100% - 14px) 100%,0% 100%);
  animation:lb2-in .35s cubic-bezier(.34,1.2,.64,1);
}
@keyframes lb2-in{from{opacity:0;transform:scale(.94) translateY(16px)}to{opacity:1;transform:none}}
.lb2-shell::before{
  content:'';position:absolute;top:0;left:0;right:0;height:2px;
  background:linear-gradient(90deg,transparent,#f5c842,rgba(77,166,255,.8),transparent);
  opacity:.7;pointer-events:none;
}
.lb2-left{display:flex;flex-direction:column;border-right:1px solid rgba(238,240,255,.07);overflow:hidden;}
.lb2-head{
  display:flex;justify-content:space-between;align-items:flex-start;
  padding:18px 18px 13px;
  background:linear-gradient(180deg,rgba(255,255,255,.03),transparent);
  border-bottom:1px solid rgba(238,240,255,.06);flex-shrink:0;
}
.lb2-head-eyebrow{font-size:.68rem;letter-spacing:.35em;color:rgba(238,240,255,.7);font-family:'Rajdhani',sans-serif;font-weight:700;text-transform:uppercase;margin-bottom:4px;}
.lb2-head-title{font-family:'Bebas Neue',sans-serif;font-size:1.7rem;letter-spacing:.18em;color:#f5c842;line-height:1;text-shadow:0 0 30px rgba(245,200,66,.35);}
.lb2-head-sub{font-size:.72rem;letter-spacing:.18em;color:rgba(238,240,255,.7);font-family:'Rajdhani',sans-serif;font-weight:600;margin-top:4px;}
.lb2-close-btn{
  background:rgba(238,240,255,.04);border:1px solid rgba(238,240,255,.08);
  color:rgba(238,240,255,.4);font-size:.9rem;cursor:pointer;padding:6px 10px;
  clip-path:polygon(5px 0%,100% 0%,calc(100% - 5px) 100%,0% 100%);
  font-family:'Rajdhani',sans-serif;font-weight:700;transition:all .2s;flex-shrink:0;
}
.lb2-close-btn:hover{background:rgba(255,77,77,.12);border-color:rgba(255,77,77,.3);color:#ff8888}
.lb2-col-head{
  display:grid;grid-template-columns:40px 1fr 58px 70px 16px;
  padding:8px 14px;flex-shrink:0;
  font-size:.72rem;letter-spacing:.18em;color:rgba(238,240,255,.75);
  font-family:'Rajdhani',sans-serif;font-weight:700;text-transform:uppercase;
  border-bottom:1px solid rgba(238,240,255,.15);
}
.lb2-list{flex:1;overflow-y:auto;padding:5px 7px}
.lb2-list::-webkit-scrollbar{width:2px}
.lb2-list::-webkit-scrollbar-thumb{background:rgba(245,200,66,.25);border-radius:2px}
.lb2-row{
  display:grid;grid-template-columns:40px 1fr 58px 70px 16px;
  align-items:center;padding:8px 7px;margin-bottom:2px;
  border:1px solid transparent;transition:all .22s;cursor:pointer;
  clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%);
}
.lb2-row:hover{background:rgba(255,255,255,.04);border-color:rgba(238,240,255,.1)}
.lb2-row-active{background:rgba(245,200,66,.06)!important;border-color:rgba(245,200,66,.25)!important}
.lb2-row-active::before{content:'';position:absolute;left:0;top:0;bottom:0;width:2.5px;background:#f5c842;}
.lb2-me{background:rgba(77,166,255,.05);border-color:rgba(77,166,255,.12)}
.lb2-gold .lb2-pos-num{color:#ffd700;text-shadow:0 0 14px rgba(255,215,0,.7)}
.lb2-silver .lb2-pos-num{color:#d0d0d0;text-shadow:0 0 12px rgba(192,192,192,.6)}
.lb2-bronze .lb2-pos-num{color:#e07832;text-shadow:0 0 12px rgba(205,127,50,.6)}
.lb2-r-pos{display:flex;flex-direction:column;align-items:center;gap:2px;}
.lb2-pos-num{font-family:'Bebas Neue',sans-serif;font-size:1.05rem;letter-spacing:.06em;color:rgba(238,240,255,.75);line-height:1;}
.lb2-pos-glow{width:16px;height:1.5px;border-radius:2px;opacity:.6;animation:lb2-pglow 1.5s ease-in-out infinite alternate;}
@keyframes lb2-pglow{from{opacity:.3}to{opacity:.8;transform:scaleX(1.3)}}
.lb2-r-player{display:flex;align-items:center;gap:7px;min-width:0;padding:0 3px}
.lb2-r-av{width:28px;height:28px;font-size:14px;flex-shrink:0;display:flex;align-items:center;justify-content:center;border:1px solid;clip-path:polygon(4px 0%,100% 0%,calc(100% - 4px) 100%,0% 100%);background:rgba(255,255,255,.04);}
.lb2-r-info{min-width:0;flex:1}
.lb2-r-name{font-family:'Russo One',sans-serif;font-size:.78rem;letter-spacing:.04em;color:#eef0ff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.2;}
.lb2-r-name-me{color:#4da6ff}
.lb2-r-tier{font-size:.66rem;letter-spacing:.1em;font-family:'Rajdhani',sans-serif;font-weight:700;margin-top:2px}
.lb2-you-badge{font-size:.65rem;letter-spacing:.15em;color:#4da6ff;font-family:'Rajdhani',sans-serif;font-weight:700;background:rgba(77,166,255,.1);border:1px solid rgba(77,166,255,.25);padding:2px 6px;clip-path:polygon(3px 0%,100% 0%,calc(100% - 3px) 100%,0% 100%);flex-shrink:0;}
.lb2-r-rating{display:flex;align-items:center}
.lb2-r-rating-val{font-family:'Bebas Neue',sans-serif;font-size:.92rem;letter-spacing:.04em;color:#f5c842;}
.lb2-r-wl{display:flex;align-items:center;gap:3px;font-family:'Bebas Neue',sans-serif;font-size:.82rem}
.lb2-r-w{color:#7dff4d}.lb2-r-sep{color:rgba(238,240,255,.15)}.lb2-r-l{color:#ff6b6b}
.lb2-r-arrow{color:rgba(238,240,255,.18);font-size:.85rem;transition:all .2s;}
.lb2-row:hover .lb2-r-arrow{color:rgba(238,240,255,.5);transform:translateX(2px)}
.lb2-foot{padding:10px 14px;border-top:1px solid rgba(238,240,255,.05);flex-shrink:0;background:linear-gradient(0deg,rgba(255,255,255,.02),transparent);}
.lb2-foot-rank{font-size:.72rem;letter-spacing:.1em;color:rgba(238,240,255,.7);font-family:'Rajdhani',sans-serif;font-weight:600;text-align:center;}
.lb2-right{overflow-y:auto;display:flex;flex-direction:column;background:rgba(255,255,255,.008);}
.lb2-right::-webkit-scrollbar{width:2px}
.lb2-right::-webkit-scrollbar-thumb{background:rgba(77,166,255,.2);border-radius:2px}
.lb2-empty{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;padding:32px 24px;text-align:center;opacity:1;color:rgba(238,240,255,.6);}
.lb2-empty-icon{font-size:2.5rem;animation:lb2-bounce 2s ease-in-out infinite}
@keyframes lb2-bounce{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
.lb2-empty-title{font-family:'Russo One',sans-serif;font-size:.88rem;letter-spacing:.18em;color:#eef0ff}
.lb2-empty-sub{font-size:.72rem;color:rgba(238,240,255,.65);letter-spacing:.06em;line-height:1.6;max-width:180px}
.lb2-profile{padding:0 0 18px;display:flex;flex-direction:column}
.lb2-prof-hero{
  padding:22px 20px 16px;border-bottom:1px solid;
  position:relative;overflow:hidden;
  display:flex;flex-direction:column;align-items:center;text-align:center;gap:7px;
  background:linear-gradient(180deg,rgba(255,255,255,.03),transparent);
}
.lb2-prof-hero::before{content:'';position:absolute;inset:0;pointer-events:none;background:radial-gradient(ellipse at 50% 0%,var(--prof-col,#f5c842) 0%,transparent 65%);opacity:.07;}
.lb2-prof-pos-tag{font-family:'Rajdhani',sans-serif;font-size:.65rem;font-weight:700;letter-spacing:.28em;text-transform:uppercase;padding:3px 11px;border:1px solid;clip-path:polygon(5px 0%,100% 0%,calc(100% - 5px) 100%,0% 100%);margin-bottom:3px;}
.lb2-prof-av-wrap{position:relative;margin:2px 0}
.lb2-prof-av{width:68px;height:68px;font-size:32px;display:flex;align-items:center;justify-content:center;position:relative;z-index:1;background:rgba(255,255,255,.04);clip-path:polygon(10px 0%,100% 0%,calc(100% - 10px) 100%,0% 100%);}
.lb2-prof-av-ring{position:absolute;inset:-4px;clip-path:polygon(14px 0%,100% 0%,calc(100% - 14px) 100%,0% 100%);border:1.5px solid;animation:lb2-ring-pulse 2s ease-in-out infinite;}
@keyframes lb2-ring-pulse{0%,100%{opacity:.6}50%{opacity:1}}
.lb2-prof-name{font-family:'Russo One',sans-serif;font-size:.96rem;letter-spacing:.1em;line-height:1;margin-top:3px;}
.lb2-prof-id{font-size:.7rem;letter-spacing:.1em;color:rgba(238,240,255,.7);font-family:'Rajdhani',sans-serif;margin-top:-3px;}
.lb2-prof-tier-badge{font-family:'Russo One',sans-serif;font-size:.65rem;letter-spacing:.15em;padding:4px 13px;border:1px solid;clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%);margin-top:2px;}
.lb2-prof-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:7px;padding:13px 14px 0;}
.lb2-stat-card{background:rgba(255,255,255,.03);border:1px solid rgba(238,240,255,.07);padding:9px 8px;text-align:center;clip-path:polygon(5px 0%,100% 0%,calc(100% - 5px) 100%,0% 100%);}
.lb2-stat-label{font-size:.65rem;letter-spacing:.22em;color:rgba(238,240,255,.7);font-family:'Rajdhani',sans-serif;font-weight:700;text-transform:uppercase;margin-bottom:4px;}
.lb2-stat-val{font-family:'Bebas Neue',sans-serif;font-size:1.2rem;letter-spacing:.06em;line-height:1;}
.lb2-wdl-section{padding:12px 14px 0}
.lb2-wdl-label-row{display:flex;justify-content:space-between;margin-bottom:6px}
.lb2-wdl-tag{font-family:'Bebas Neue',sans-serif;font-size:.75rem;letter-spacing:.1em;}
.wdl-w{color:#7dff4d}.wdl-d{color:rgba(238,240,255,.4)}.wdl-l{color:#ff6b6b}
.lb2-wdl-bar{display:flex;height:5px;background:rgba(238,240,255,.06);overflow:hidden;clip-path:polygon(3px 0%,100% 0%,calc(100% - 3px) 100%,0% 100%);}
.lb2-wdl-seg{height:100%;width:0%;transition:width .7s cubic-bezier(.34,1.2,.64,1)}
.wdl-seg-w{background:#7dff4d;box-shadow:0 0 8px #7dff4d88}
.wdl-seg-d{background:rgba(238,240,255,.25)}
.wdl-seg-l{background:#ff6b6b;box-shadow:0 0 8px #ff6b6b55}
.lb2-tier-progress{padding:12px 14px 0}
.lb2-tp-label{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:5px;}
.lb2-tp-label span:first-child{font-family:'Rajdhani',sans-serif;font-size:.63rem;font-weight:700;letter-spacing:.1em;}
.lb2-tp-next{font-size:.7rem;color:rgba(238,240,255,.7);letter-spacing:.08em;font-family:'Rajdhani',sans-serif;font-weight:600;}
.lb2-tp-track{height:3.5px;background:rgba(238,240,255,.07);position:relative;overflow:hidden;clip-path:polygon(2px 0%,100% 0%,calc(100% - 2px) 100%,0% 100%);}
.lb2-tp-fill{height:100%;width:0%;transition:width .9s cubic-bezier(.34,1.56,.64,1)}
.lb2-prof-actions{display:flex;gap:7px;padding:14px 14px 0;}
.lb2-act-btn{flex:1;padding:10px 8px;font-family:'Rajdhani',sans-serif;font-size:.68rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;cursor:pointer;border:1px solid;transition:all .25s;clip-path:polygon(7px 0%,100% 0%,calc(100% - 7px) 100%,0% 100%);display:flex;align-items:center;justify-content:center;gap:7px;text-decoration:none;}
.lb2-act-profile{background:rgba(255,255,255,.04);border-color:rgba(238,240,255,.12);color:rgba(238,240,255,.6);}
.lb2-act-profile:hover{background:rgba(77,166,255,.1);border-color:rgba(77,166,255,.35);color:#90c4ff}
.lb2-me-note{margin:12px 14px 0;padding:9px;background:rgba(77,166,255,.06);border:1px solid rgba(77,166,255,.18);font-family:'Rajdhani',sans-serif;font-size:.6rem;letter-spacing:.2em;color:rgba(77,166,255,.7);text-align:center;clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%);}
/* pulse hint on rank panels */
.rating-prog:hover .rp-lihat-ring,
.ai-lb-cta:hover .lb-cta-arrow-ring{animation:ring-tap-hint .6s cubic-bezier(.34,1.56,.64,1)}
@keyframes ring-tap-hint{0%{transform:scale(1)}50%{transform:scale(1.18)}100%{transform:scale(1.1)}}
@media(max-width:600px){
  .lb2-shell{grid-template-columns:1fr;height:min(92vh,680px);width:min(96vw,500px);}
  .lb2-right{display:none}
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
  --dark:#f0f4fc;--mid:#e4e8f4;--card:rgba(255,255,255,.75);
  --text:#1a1d2e;--muted:rgba(26,29,46,.5);--border:rgba(0,0,0,.09);
  --rock:#d93030;--paper:#2874c2;--scissors:#1a9940;
  --gr:rgba(217,48,48,.45);--gp:rgba(40,116,194,.45);--gs:rgba(26,153,64,.45);
  --gold:#c8a000;--win:#1a9940;--lose:#d93030;--draw:#5577aa;
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
[data-theme="light"] .pbar{background:linear-gradient(180deg,rgba(240,244,252,.96) 0%,rgba(240,244,252,.85) 100%);border-bottom-color:rgba(40,116,194,.1);}
[data-theme="light"] .pinfo:hover{background:rgba(40,116,194,.05);border-color:rgba(40,116,194,.15);}
[data-theme="light"] .pname{color:var(--text);}
[data-theme="light"] .btn-back{background:transparent;border-color:rgba(40,116,194,.18);color:rgba(40,116,194,.8);}
[data-theme="light"] .btn-back:hover{background:rgba(40,116,194,.08);border-color:rgba(40,116,194,.35);color:#2874c2;}
[data-theme="light"] .stat-card{background:rgba(255,255,255,.75);border-color:rgba(0,0,0,.07);}
[data-theme="light"] .card{background:rgba(255,255,255,.75);border-color:rgba(0,0,0,.07);}
[data-theme="light"] .card-ttl{color:rgba(26,29,46,.4);}
[data-theme="light"] .choice-card{background:rgba(255,255,255,.75);border-color:rgba(0,0,0,.07);}
[data-theme="light"] .streak-card{background:rgba(255,255,255,.75);border-color:rgba(0,0,0,.07);}
[data-theme="light"] .hb-cell{background:rgba(245,247,255,.9);}
[data-theme="light"] .wr-box{background:rgba(255,255,255,.75);border-color:rgba(0,0,0,.07);}
[data-theme="light"] .hist-item{background:rgba(255,255,255,.65);border-color:rgba(0,0,0,.07);}
[data-theme="light"] .hist-item:hover{background:rgba(255,255,255,.85);}
[data-theme="light"] .history-section{background:rgba(255,255,255,.55);border-color:rgba(0,0,0,.07);}
[data-theme="light"] .history-header{border-bottom-color:rgba(0,0,0,.07);}
[data-theme="light"] .mode-toggle{background:rgba(255,255,255,.6);border-color:rgba(0,0,0,.09);}
[data-theme="light"] .modal-overlay{background:rgba(240,244,252,.88);}
[data-theme="light"] .lb2-shell{
  background:linear-gradient(160deg,rgba(245,247,255,.99),rgba(240,244,252,.99));
  border-color:rgba(40,116,194,.1);
  box-shadow:0 10px 30px rgba(0, 0, 0, 0.08), 0 1px 3px rgba(0, 0, 0, 0.02);
}
[data-theme="light"] .lb2-head{background:rgba(40,116,194,.03);border-bottom-color:rgba(0,0,0,.07);}
[data-theme="light"] .lb2-head-eyebrow{color:rgba(26,29,46,.65);}
[data-theme="light"] .lb2-head-title{color:var(--color-gold);text-shadow:none;}
[data-theme="light"] .lb2-head-sub{color:rgba(26,29,46,.65);}
[data-theme="light"] .lb2-close-btn{background:rgba(0,0,0,.03);border-color:rgba(0,0,0,.09);color:rgba(26,29,46,.5);}
[data-theme="light"] .lb2-close-btn:hover{background:rgba(255,77,77,.08);border-color:rgba(255,77,77,.3);color:#c0200f;}
[data-theme="light"] .lb2-col-head{color:rgba(26,29,46,.68);border-bottom-color:rgba(0,0,0,.08);}
[data-theme="light"] .lb2-row:hover{background:rgba(40,116,194,.04);border-color:rgba(40,116,194,.12);}
[data-theme="light"] .lb2-row-active{background:rgba(40,116,194,.08) !important;border-color:rgba(40,116,194,.3) !important;}
[data-theme="light"] .lb2-row-active::before{background:#2874c2 !important;}
[data-theme="light"] .lb2-me{background:rgba(40,116,194,.05);border-color:rgba(40,116,194,.12);}
[data-theme="light"] .lb2-me.lb2-row-active{background:rgba(40,116,194,.1) !important;border-color:rgba(40,116,194,.3) !important;}
[data-theme="light"] .lb2-pos-num{text-shadow:none !important;color:rgba(26,29,46,.65);}
[data-theme="light"] .lb2-r-name{color:#1a1d2e;}
[data-theme="light"] .lb2-r-name-me{color:#2874c2;}
[data-theme="light"] .lb2-r-tier{color:rgba(26,29,46,.68);}
[data-theme="light"] .lb2-r-rating-val{color:var(--color-gold);text-shadow:none;}
[data-theme="light"] .lb2-r-w{color:#0f7a30;}
[data-theme="light"] .lb2-r-l{color:#c0200f;}
[data-theme="light"] .lb2-r-sep{color:rgba(26,29,46,.15);}
[data-theme="light"] .lb2-r-arrow{color:rgba(26,29,46,.25);}
[data-theme="light"] .lb2-you-badge{color:#2874c2;background:rgba(40,116,194,.1);border-color:rgba(40,116,194,.25);}
[data-theme="light"] .lb2-foot{border-top-color:rgba(0,0,0,.06);background:rgba(0,0,0,.01);}
[data-theme="light"] .lb2-foot-rank{color:rgba(26,29,46,.7);}
[data-theme="light"] .lb2-right{background:rgba(0,0,0,.01);}
[data-theme="light"] .lb2-empty{color:rgba(26,29,46,.65);}
[data-theme="light"] .lb2-empty-title{color:#1a1d2e;}
[data-theme="light"] .lb2-empty-sub{color:rgba(26,29,46,.7);}
[data-theme="light"] .lb2-prof-hero{background:rgba(40,116,194,.02);}
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
[data-theme="light"] .lb2-me-note{background:rgba(40,116,194,.06);border-color:rgba(40,116,194,.2);color:#2874c2;}
[data-theme="light"] .lb2-act-profile{background:rgba(0,0,0,.04);border-color:rgba(0,0,0,.09);color:rgba(26,29,46,.68);}
[data-theme="light"] .lb2-act-profile:hover{background:rgba(40,116,194,.08);border-color:rgba(40,116,194,.25);color:#2874c2;}
[data-theme="light"] .lb2-pos-glow{opacity:.35;animation:none;}
[data-theme="light"] .lb2-gold .lb2-pos-num,
[data-theme="light"] .lb2-gold .lb2-r-pos .lb2-pos-num { color: var(--color-gold) !important; text-shadow: none !important; }
[data-theme="light"] .lb2-silver .lb2-pos-num,
[data-theme="light"] .lb2-silver .lb2-r-pos .lb2-pos-num { color: var(--color-silver) !important; text-shadow: none !important; }
[data-theme="light"] .lb2-bronze .lb2-pos-num,
[data-theme="light"] .lb2-bronze .lb2-r-pos .lb2-pos-num { color: var(--color-bronze) !important; text-shadow: none !important; }
[data-theme="light"] .toast{background:rgba(240,244,252,.98);border-color:rgba(40,116,194,.15);color:var(--text);}
[data-theme="light"] .btn-theme-toggle{background:transparent;border-color:rgba(40,116,194,.18);color:rgba(40,116,194,.8);}
[data-theme="light"] .btn-theme-toggle:hover{background:rgba(40,116,194,.08);border-color:rgba(40,116,194,.35);color:#2874c2;}
/* ── FIX: text & glow tabrakan ── */
[data-theme="light"] .page-title{color:#1a1d2e;text-shadow:none;}
[data-theme="light"] .page-title::after{opacity:0;}
[data-theme="light"] .page-subtitle{color:rgba(26,29,46,.45);}
[data-theme="light"] .last-update{color:rgba(26,29,46,.35);}
[data-theme="light"] .atag{color:rgba(26,29,46,.4);}
[data-theme="light"] .atag-line{background:linear-gradient(to right,transparent,rgba(40,116,194,.25),transparent);}
[data-theme="light"] .pid{color:rgba(26,29,46,.4);}
[data-theme="light"] .rank-pts{color:rgba(26,29,46,.4);}
[data-theme="light"] .stat-val{text-shadow:none;}
[data-theme="light"] .stat-lbl{color:rgba(26,29,46,.45);}
[data-theme="light"] .choice-pct{color:rgba(26,29,46,.35);}
[data-theme="light"] .streak-val{text-shadow:none;}
[data-theme="light"] .streak-lbl{color:rgba(26,29,46,.45);}
[data-theme="light"] .hb-cell{color:#1a1d2e;}
[data-theme="light"] .row-result.win{color:#0f7a30;}
[data-theme="light"] .row-result.lose{color:#c0200f;}
[data-theme="light"] .row-result.draw{color:#2874c2;}
[data-theme="light"] .live-label{color:rgba(15,122,48,.7);}
[data-theme="light"] .mode-toggle{
  background:rgba(255,255,255,.92);
  border-color:rgba(0,0,0,.1);
  box-shadow:0 2px 8px rgba(0,0,0,.08);
}
[data-theme="light"] .mode-btn{
  color:rgba(26,29,46,.6);
  background:transparent;
}
[data-theme="light"] .mode-btn:not(.active-ranked):not(.active-ai):hover{
  background:rgba(0,0,0,.05);
  color:rgba(26,29,46,.85);
}
[data-theme="light"] .mode-btn.active-ranked{
  background:rgba(192,32,15,.12);
  border-color:rgba(192,32,15,.35);
  color:#c0200f;
  box-shadow:none;
}
[data-theme="light"] .mode-btn.active-ai{
  background:rgba(26,95,168,.12);
  border-color:rgba(26,95,168,.35);
  color:#1a5fa8;
  box-shadow:none;
}
/* ── FIX KRITIS: page-title gradient → hapus shadow di light mode ── */
[data-theme="light"] .page-title{
  background:none;
  -webkit-text-fill-color:var(--text);
  color:var(--text);
  text-shadow:none;
}
[data-theme="light"] .page-title::before{color:#c0200f;opacity:.35;}
[data-theme="light"] .page-title::after{color:#1060b0;opacity:.35;}
[data-theme="light"] .atag{color:rgba(26,29,46,.5);}
[data-theme="light"] .atag-line{opacity:.4;background:linear-gradient(to right,transparent,rgba(16,96,176,.5));}

/* Light Mode overrides for Rank panels, progress elements, and badges */
[data-theme="light"] .rating-prog {
  background: rgba(255, 255, 255, 0.85);
  border-color: var(--rc);
  box-shadow: 0 0 15px var(--rg), inset 0 0 30px rgba(255, 255, 255, 0.5);
}
[data-theme="light"] .rating-prog:hover {
  box-shadow: 0 0 25px var(--rg), 0 6px 20px rgba(26, 29, 46, 0.15);
}
[data-theme="light"] .rp-left {
  border-right-color: var(--border);
}
[data-theme="light"] .rp-icon {
  filter: drop-shadow(0 2px 6px var(--rg));
}
[data-theme="light"] .rp-pts {
  color: rgba(26, 29, 46, 0.6);
}
[data-theme="light"] .rp-bar-track {
  background: rgba(0, 0, 0, 0.08);
}
[data-theme="light"] .rp-bar-fill {
  background: var(--rc);
  box-shadow: none;
}
[data-theme="light"] .rp-bar-dot {
  background: var(--rc);
  box-shadow: none;
}
[data-theme="light"] .rp-bar-head {
  color: rgba(26, 29, 46, 0.6);
}
[data-theme="light"] .rp-mini-tier {
  border-color: var(--rtc);
}
[data-theme="light"] .rp-mini-reached {
  background: rgba(0, 0, 0, 0.08);
}
[data-theme="light"] .rp-tiers {
  border-right-color: var(--border);
}
[data-theme="light"] .rp-rank-label {
  color: rgba(26, 29, 46, 0.6);
}
[data-theme="light"] .rp-rank-num {
  text-shadow: none;
}
[data-theme="light"] .rp-lihat-ring {
  background: rgba(0, 0, 0, 0.03);
  border-color: var(--rc);
  box-shadow: none;
}
[data-theme="light"] .rating-prog:hover .rp-lihat-ring {
  background: var(--rc);
  box-shadow: 0 0 15px var(--rg);
}
[data-theme="light"] .rp-lihat-txt {
  color: var(--rc);
}
[data-theme="light"] .rating-prog:hover .rp-lihat-txt {
  color: #ffffff;
}
[data-theme="light"] .hb-win {
  background: rgba(26, 153, 64, 0.08);
  color: #1a9940;
  border-color: rgba(26, 153, 64, 0.25);
}
[data-theme="light"] .hb-lose {
  background: rgba(217, 48, 48, 0.08);
  color: #d93030;
  border-color: rgba(217, 48, 48, 0.25);
}
[data-theme="light"] .hb-draw {
  background: rgba(85, 119, 170, 0.08);
  color: #5577aa;
  border-color: rgba(85, 119, 170, 0.25);
}
[data-theme="light"] .badge-win {
  background: rgba(26, 153, 64, 0.08);
  color: #1a9940;
  border-color: rgba(26, 153, 64, 0.25);
}
[data-theme="light"] .badge-lose {
  background: rgba(217, 48, 48, 0.08);
  color: #d93030;
  border-color: rgba(217, 48, 48, 0.25);
}
[data-theme="light"] .badge-draw {
  background: rgba(85, 119, 170, 0.08);
  color: #5577aa;
  border-color: rgba(85, 119, 170, 0.25);
}
[data-theme="light"] .streak-live {
  background: rgba(180, 83, 9, 0.08);
  border-color: rgba(180, 83, 9, 0.25);
  color: #b45309;
  animation: streak-pulse-light 2s ease-in-out infinite alternate;
}
@keyframes streak-pulse-light {
  from { box-shadow: none; }
  to { box-shadow: 0 0 12px rgba(180, 83, 9, 0.15); }
}

/* ── LIGHT MODE: 3 Kartu Favorit ── */
[data-theme="light"] .fav-card{
  background:rgba(255,255,255,.9) !important;
  border-color:rgba(0,0,0,.12) !important;
  box-shadow:0 2px 10px rgba(0,0,0,.07);
}
[data-theme="light"] .fav-card:hover{
  box-shadow:0 8px 24px rgba(0,0,0,.14), 0 0 12px var(--rarity-glow);
}
[data-theme="light"] .fav-card-empty{
  background:rgba(255,255,255,.7);
  border-color:rgba(0,0,0,.18);
}
[data-theme="light"] .fav-empty-icon{opacity:.4;}
[data-theme="light"] .fav-empty-text{color:rgba(26,29,46,.55);}
[data-theme="light"] .fav-rarity{color:var(--rarity-color);}
[data-theme="light"] .fav-name{color:#1a1d2e;}
[data-theme="light"] .fav-desc{color:rgba(26,29,46,.55);}
[data-theme="light"] .fav-uses-bar-wrap{background:rgba(0,0,0,.09);}
[data-theme="light"] .fav-uses-bar{box-shadow:none;}
[data-theme="light"] .fav-uses-count{text-shadow:none;}
[data-theme="light"] .fav-uses-lbl{color:rgba(26,29,46,.5);}
[data-theme="light"] .fav-rank-badge{background:rgba(255,255,255,.85) !important;}
[data-theme="light"] .fav-corner{color:var(--rarity-color);opacity:.6;}
/* light rarity borders — stronger contrast */
[data-theme="light"] .rarity-common{--rarity-border:rgba(75,85,99,.35);}
[data-theme="light"] .rarity-rare{--rarity-border:rgba(16,96,176,.35);}
[data-theme="light"] .rarity-epic{--rarity-border:rgba(124,58,237,.35);}
[data-theme="light"] .rarity-legend{--rarity-border:rgba(180,83,9,.38);}
/* Section title line */
[data-theme="light"] .section-line{background:rgba(0,0,0,.12);}
[data-theme="light"] .section-title{color:rgba(26,29,46,.5);}

body,.pbar,.stat-card,.card,.choice-card,.streak-card,.hist-item,.history-section,.mode-toggle,.toast,.btn-back,.fav-card,.fav-card-empty{
  transition:background .4s ease,border-color .4s ease,color .4s ease;
}
</style>
<style>
/* ══ UNIVERSAL BUTTON STYLES (Default & Hover) ══ */
button:not(.btn-theme-toggle):not(.btn-setting):not(.m-close):not(.btn-close-modal):not(.lb2-close-btn):not(.btn-close):not(.ss-mute-btn):not(.xbtn-cancel):not(.exit-btn-cancel):not(.nav-btn.danger):not(.av-edit-btn):not(.edit-name-btn):not(.avm-refresh-btn):not(.toggle-pw):not(.btn-cancel-card):not(.btn-quit):not(.modal-tab):not(.mode-btn),
.btn, .cta, .btn-submit, .btn-to-login,
.nav-btn:not(.danger),
.exit-btn-confirm, a.btn, .xbtn-battle, .lb2-act-btn, .btn-save, .chat-send-btn, .btn-continue, .btn-rematch, .btn-use-card, .btn-confirm-card {
  background: var(--text) !important;
  color: var(--dark) !important;
  border-color: var(--border) !important;
}
button:not(.btn-theme-toggle):not(.btn-setting):not(.m-close):not(.btn-close-modal):not(.lb2-close-btn):not(.btn-close):not(.ss-mute-btn):not(.xbtn-cancel):not(.exit-btn-cancel):not(.nav-btn.danger):not(.av-edit-btn):not(.edit-name-btn):not(.avm-refresh-btn):not(.toggle-pw):not(.btn-cancel-card):not(.btn-quit):not(.modal-tab):not(.mode-btn):hover,
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

<!-- BACKGROUND LAYERS (identik main_menu) -->
<canvas id="bg"></canvas>
<div class="hex-layer"></div>
<div class="noise"></div>
<div class="elines" id="EL"></div>
<div class="scanline"></div>
<div class="vignette"></div>
<div class="particles" id="PT"></div>
<div class="corner c-tl"></div><div class="corner c-tr"></div>
<div class="corner c-bl"></div><div class="corner c-br"></div>

<!-- TOPBAR (gaya player bar main_menu) -->
<div class="pbar">
  <a class="pinfo" href="profile.php">
    <div class="pav"><?php echo htmlspecialchars($nav_avatar) ?></div>
    <div>
      <div class="pname"><?php echo $nav_dispname ?></div>
      <div class="pid">@<?php echo htmlspecialchars($player['username']) ?></div>
    </div>
  </a>
  <div class="tb-right">
    <div class="live-dot"></div>
    <span class="live-label">LIVE</span>
    <a class="btn-back" href="main_menu.php">← Menu</a>
    <button class="btn-theme-toggle" id="btnThemeToggle" title="Ganti Tema"><span class="theme-icon">Light Mode</span></button>
  </div>
</div>

<!-- MAIN -->
<div class="main-content">

  <!-- PAGE HEADER (title bergaya Bebas Neue / glitch seperti main_menu) -->
  <div class="page-header">
    <div class="atag">
      <div class="atag-line"></div>
      ✦ Battle Arena ✦
      <div class="atag-line" style="background:linear-gradient(to left,transparent,var(--paper));opacity:.5"></div>
    </div>
    <div class="page-title" data-text="STATISTIK">STATISTIK</div>
    <div class="page-subtitle">Semua data pertarunganmu · Diperbarui otomatis</div>
    <div class="last-update" id="lastUpdate">Terakhir diperbarui: baru saja</div>
  </div>

  <!-- MODE TOGGLE -->
  <div class="mode-toggle" role="tablist" aria-label="Mode Statistik">
    <button class="mode-btn active-ranked" id="btn-pvp" onclick="switchMode('pvp')" role="tab" aria-selected="true">⚔️ PvP Ranked</button>
    <button class="mode-btn" id="btn-ai" onclick="switchMode('ai')" role="tab" aria-selected="false">🤖 VS AI</button>
  </div>

  <!-- ══════════════════════════════════════
       PANEL: PvP RANKED
  ══════════════════════════════════════ -->
  <div id="panel-pvp">

    <!-- HERO BAR (PvP only) -->
    <div class="hero-bar">
      <div class="hb-cell">
        <div class="hb-val c-rating" id="stat-rating"><?php echo number_format($rating) ?></div>
        <div class="hb-lbl">Rating ERP</div>
      </div>
      <div class="hb-cell">
        <div class="hb-val c-win" id="hb-pvp-wins"><?php echo $wins ?></div>
        <div class="hb-lbl">Total Menang</div>
      </div>
      <div class="hb-cell">
        <div class="hb-val c-lose" id="hb-pvp-losses"><?php echo $losses ?></div>
        <div class="hb-lbl">Total Kalah</div>
      </div>
      <div class="hb-cell">
        <div class="hb-val c-streak" id="stat-streak"><?php echo $cur_streak ?></div>
        <div class="hb-lbl">Streak Aktif</div>
      </div>
      <div class="hb-cell">
        <div class="hb-val c-rank" id="stat-rank">#<?php echo $rank ?></div>
        <div class="hb-lbl">Peringkat</div>
      </div>
    </div>

    <!-- RATING PROGRESS (PvP only) — gaya lb-cta dari lobby_pvp -->
    <div class="rating-prog" id="pvp-rank-panel" onclick="openLbModal('pvp')" style="cursor:pointer" title="Tap untuk lihat Leaderboard PvP">
      <div class="rp-shimmer"></div>
      <div class="rp-topbar"></div>

      <!-- Left: rank name + progress bar -->
      <div class="rp-left">
        <div class="rp-tier">
          <div class="rp-icon" id="rp-icon"><?php echo $tier['icon'] ?></div>
          <div>
            <div class="rp-name" id="rp-name"><?php echo $tier['name'] ?></div>
            <div class="rp-pts" id="rp-pts"><?php echo number_format($rating) ?> PTS</div>
          </div>
        </div>
        <div class="rp-bar-wrap">
          <div class="rp-bar-track">
            <div class="rp-bar-fill" id="tierBar" style="width:0">
              <div class="rp-bar-dot"></div>
            </div>
          </div>
          <div class="rp-bar-head" id="rp-head-l">
            <?php if($tier['name'] !== 'GRANDMASTER'): ?>
              <?php echo $tier_pct ?>% menuju <strong style="color:var(--rc)"><?php echo $next_tier_name ?></strong>
            <?php else: ?>
              ✦ Rank Tertinggi
            <?php endif ?>
          </div>
        </div>
      </div>

      <!-- Center: tier mini-map -->
      <div class="rp-tiers">
        <?php foreach($all_tiers_map as $tm):
          $is_active  = ($tier['name'] === $tm['name']);
          $is_reached = ($rating >= $tm['min']);
          $tc         = $tm['color'];
          $cls = $is_active ? 'rp-mini-active' : ($is_reached ? 'rp-mini-reached' : 'rp-mini-locked');
        ?>
        <div class="rp-mini-tier <?php echo $cls ?>"
             style="--rtc:<?php echo $is_active||$is_reached ? $tc : 'rgba(238,240,255,.15)' ?>"></div>
        <?php endforeach ?>
      </div>

      <!-- Right: rank arena + LIHAT -->
      <div class="rp-right">
        <div class="rp-rank-no">
          <span class="rp-rank-label">RANK ARENA</span>
          <span class="rp-rank-num" id="rp-rank-num">#<?php echo $rank ?></span>
        </div>
        <div class="rp-lihat-ring">
          <span class="rp-lihat-txt">PVP</span>
        </div>
      </div>
    </div>

    <!-- SECTION: PvP Stats -->
    <div class="section-row"><div class="section-line"></div><div class="section-title">⚔️ Statistik PvP Ranked</div><div class="section-line"></div></div>

    <div class="card cv-pvp" style="margin-bottom:1.5rem">
      <div class="card-ttl">⚔️ Win / Loss / Draw</div>
      <div class="wr-grid">
        <div class="wr-box"><div class="wr-box-val c-win" id="pvp-wins"><?php echo $wins ?></div><div class="wr-box-lbl">Menang</div></div>
        <div class="wr-box"><div class="wr-box-val c-lose" id="pvp-losses"><?php echo $losses ?></div><div class="wr-box-lbl">Kalah</div></div>
        <div class="wr-box"><div class="wr-box-val c-draw" id="pvp-draws"><?php echo $draws ?></div><div class="wr-box-lbl">Seri</div></div>
      </div>
      <div class="wr-track">
        <?php if($total_pvp>0): ?>
          <div class="wr-seg-w" id="pvp-bar-w" style="width:<?php echo round($wins/$total_pvp*100) ?>%"></div>
          <div class="wr-seg-d" id="pvp-bar-d" style="width:<?php echo round($draws/$total_pvp*100) ?>%"></div>
          <div class="wr-seg-l" id="pvp-bar-l" style="width:<?php echo round($losses/$total_pvp*100) ?>%"></div>
        <?php endif ?>
      </div>
      <div class="wr-legend">
        <div class="wr-it"><div class="wr-dot" style="background:var(--win)"></div><span id="pvp-wr"><?php echo $winrate_pvp ?>%</span> Winrate</div>
        <div class="wr-it"><div class="wr-dot" style="background:var(--muted)"></div><span id="pvp-total"><?php echo $total_pvp ?></span> Match</div>
      </div>
      <?php if($cur_streak>=2): ?>
        <div class="streak-live" id="streakBadge">🔥 Streak <?php echo $cur_streak ?> Aktif!</div>
      <?php else: ?>
        <div class="streak-live" id="streakBadge" style="display:none">🔥 Streak <span id="streakNum"><?php echo $cur_streak ?></span> Aktif!</div>
      <?php endif ?>
    </div>

    <!-- SECTION: 3 KARTU FAVORIT PVP -->
    <div class="section-row"><div class="section-line"></div><div class="section-title">🃏 3 Kartu Favorit PvP</div><div class="section-line"></div></div>

    <div class="fav-cards-grid" id="fav-cards-grid">
      <?php
      $rank_labels = ['#1 PALING SERING','#2 FAVORIT','#3 TERPILIH'];
      $max_uses = !empty($fav_cards) ? ($fav_cards[0]['uses'] ?? 1) : 1;

      for ($fi = 0; $fi < 3; $fi++):
        if (!empty($fav_cards[$fi])):
          $fc  = $fav_cards[$fi];
          $rm  = $rarity_meta[$fc['rarity']] ?? $rarity_meta['common'];
          $bar_w = $max_uses > 0 ? round($fc['uses'] / $max_uses * 100) : 0;
      ?>
      <div class="fav-card rarity-<?php echo $fc['rarity'] ?>">
        <div class="shine"></div>
        <div class="fav-corner fav-tl"></div>
        <div class="fav-corner fav-br"></div>
        <div class="fav-rank-badge"><?php echo $rank_labels[$fi] ?></div>
        <span class="fav-icon"><?php echo $fc['icon'] ?></span>
        <div class="fav-rarity"><?php echo $rm['label'] ?></div>
        <div class="fav-name"><?php echo htmlspecialchars($fc['name']) ?></div>
        <div class="fav-desc"><?php echo htmlspecialchars($fc['desc']) ?></div>
        <div class="fav-uses-bar-wrap">
          <div class="fav-uses-bar" style="width:<?php echo $bar_w ?>%"></div>
        </div>
        <div class="fav-uses-count"><?php echo $fc['uses'] ?></div>
        <div class="fav-uses-lbl">kali dipakai</div>
      </div>
      <?php else: ?>
      <div class="fav-card-empty">
        <span class="fav-empty-icon">🃏</span>
        <span class="fav-empty-text">Belum ada data<br>kartu #<?php echo $fi+1 ?></span>
      </div>
      <?php endif ?>
      <?php endfor ?>

      <?php if(empty($fav_cards)): ?>
      <!-- Belum ada match sama sekali — override seluruh grid dengan pesan -->
      <style>
        #fav-cards-grid{display:block!important}
        .fav-card-empty{grid-column:1/-1}
      </style>
      <div style="text-align:center;padding:36px 20px;font-family:'Rajdhani',sans-serif;color:var(--muted);font-size:.78rem;font-weight:600;letter-spacing:.1em;grid-column:1/-1">
        <div style="font-size:2.4rem;margin-bottom:12px;opacity:.3">🃏</div>
        Mainkan beberapa pertandingan PvP untuk melihat<br>kartu-kartu favorit yang kamu gunakan
      </div>
      <?php endif ?>
    </div>

    <!-- SECTION: RIWAYAT PvP -->
    <div class="section-row"><div class="section-line"></div><div class="section-title">📜 10 Match PvP Terakhir</div><div class="section-line"></div></div>
    <div class="card cv-hist-pvp" style="margin-bottom:1.5rem">
      <div class="card-ttl">⚔️ Riwayat PvP</div>
      <?php if(empty($pvp_matches)): ?>
        <div class="empty-state"><span class="empty-icon">⚔️</span>Belum ada pertandingan PvP.</div>
      <?php else: ?>
        <div class="hist-list">
          <?php foreach($pvp_matches as $m):
            $res  = $m['result']??'draw';
            $opp  = ($m['player1_id']===$player_id)?($m['player2_name']??'Lawan'):($m['player1_name']??'Lawan');
            $rb   = ($m['player1_id']===$player_id)?($m['player1_rating_before']??null):($m['player2_rating_before']??null);
            $ra   = ($m['player1_id']===$player_id)?($m['player1_rating_after']??null):($m['player2_rating_after']??null);
            $delta= ($rb!==null&&$ra!==null)?($ra-$rb):null;
            $myRW = ($m['player1_id']===$player_id)?(int)($m['player1_round_wins']??0):(int)($m['player2_round_wins']??0);
            $opRW = ($m['player1_id']===$player_id)?(int)($m['player2_round_wins']??0):(int)($m['player1_round_wins']??0);
            $bc   = $res==='won'?'hb-win':($res==='lost'?'hb-lose':'hb-draw');
            $bl   = $res==='won'?'MENANG':($res==='lost'?'KALAH':'SERI');
            $dur  = (int)($m['duration_sec']??0);
          ?>
          <div class="hist-item">
            <div class="hist-badge <?php echo $bc ?>"><?php echo $bl ?></div>
            <div class="hist-vs">
              <div class="hist-opp">vs <?php echo htmlspecialchars($opp) ?></div>
              <div class="hist-meta"><?php echo date('d M, H:i',strtotime($m['played_at']??'now')) ?> · <?php echo $dur ?>d</div>
            </div>
            <div class="hist-score"><?php echo $myRW ?>-<?php echo $opRW ?></div>
            <?php if($delta!==null): ?>
              <div class="hist-delta <?php echo $delta>0?'d-up':($delta<0?'d-dn':'d-nu') ?>">
                <?php echo $delta>0?'+':'' ?><?php echo $delta ?>
              </div>
            <?php else: ?>
              <div class="hist-delta d-nu">—</div>
            <?php endif ?>
          </div>
          <?php endforeach ?>
        </div>
      <?php endif ?>
    </div>

    <!-- SECTION: STREAK PvP -->
    <div class="section-row"><div class="section-line"></div><div class="section-title">⚡ Streak &amp; Beruntun</div><div class="section-line"></div></div>
    <div class="streak-section">
      <div class="streak-card">
        <div class="streak-icon">🔥</div>
        <div>
          <div class="streak-value"><?php echo $cur_streak ?><span>menang</span></div>
          <div class="streak-label">Streak Menang Saat Ini</div>
        </div>
      </div>
      <div class="streak-card">
        <div class="streak-icon">⚡</div>
        <div>
          <div class="streak-value"><?php echo $max_streak ?><span>terbanyak</span></div>
          <div class="streak-label">Rekor Streak Menang</div>
        </div>
      </div>
    </div>

  </div><!-- /panel-pvp -->

  <!-- ══════════════════════════════════════
       PANEL: VS AI
  ══════════════════════════════════════ -->
  <div id="panel-ai" style="display:none">

    <!-- HERO BAR AI (identik PvP) -->
    <div class="hero-bar">
      <div class="hb-cell">
        <div class="hb-val c-rating" id="ai-stat-rating"><?php echo number_format($ai_rating) ?></div>
        <div class="hb-lbl">Rating AI</div>
      </div>
      <div class="hb-cell">
        <div class="hb-val c-win" id="hb-ai-wins"><?php echo $ai_wins ?></div>
        <div class="hb-lbl">Total Menang</div>
      </div>
      <div class="hb-cell">
        <div class="hb-val c-lose" id="hb-ai-losses"><?php echo $ai_losses ?></div>
        <div class="hb-lbl">Total Kalah</div>
      </div>
      <div class="hb-cell">
        <div class="hb-val c-draw" id="hb-ai-draws"><?php echo $ai_draws ?></div>
        <div class="hb-lbl">Total Seri</div>
      </div>
      <div class="hb-cell">
        <div class="hb-val c-rank" id="ai-stat-rank">#<?php echo $ai_rank ?></div>
        <div class="hb-lbl">Peringkat AI</div>
      </div>
    </div>

    <!-- RATING PROGRESS AI — struktur & class identik PvP -->
    <div class="rating-prog" id="ai-rank-panel"
         onclick="openLbModal('ai')" title="Tap untuk lihat Leaderboard VS AI"
         style="cursor:pointer;--rc:var(--color-<?php echo strtolower($ai_tier['name']) ?>);--rg:var(--glow-<?php echo strtolower($ai_tier['name']) ?>)">
      <div class="rp-shimmer"></div>
      <div class="rp-topbar"></div>

      <!-- Left: rank name + progress bar -->
      <div class="rp-left">
        <div class="rp-tier">
          <div class="rp-icon" id="ai-rp-icon"><?php echo $ai_tier['icon'] ?></div>
          <div>
            <div class="rp-name" id="ai-rp-name"><?php echo $ai_tier['name'] ?></div>
            <div class="rp-pts" id="ai-rp-pts"><?php echo number_format($ai_rating) ?> RAI</div>
          </div>
        </div>
        <div class="rp-bar-wrap">
          <div class="rp-bar-track">
            <div class="rp-bar-fill" id="aiTierBar" style="width:0">
              <div class="rp-bar-dot"></div>
            </div>
          </div>
          <div class="rp-bar-head" id="ai-rp-head-l">
            <?php if($ai_tier['name'] !== 'GRANDMASTER'): ?>
              <?php echo $ai_tier_pct ?>% ke tier berikutnya · Butuh <strong style="color:var(--rc)"><?php echo max(0,$ai_tier['next']-$ai_rating) ?> RAI</strong>
            <?php else: ?>
              ✦ Rank Tertinggi 👑
            <?php endif ?>
          </div>
        </div>
      </div>

      <!-- Center: tier mini-map -->
      <div class="rp-tiers">
        <?php
        $ai_tiers_minimap = [
          ['name'=>'BRONZE',     'min'=>0,    'color'=>'#cd7f32'],
          ['name'=>'SILVER',     'min'=>950,  'color'=>'#c0c0c0'],
          ['name'=>'GOLD',       'min'=>1100, 'color'=>'#f5c842'],
          ['name'=>'PLATINUM',   'min'=>1300, 'color'=>'#7dff4d'],
          ['name'=>'DIAMOND',    'min'=>1500, 'color'=>'#4da6ff'],
          ['name'=>'MASTER',     'min'=>1700, 'color'=>'#c084fc'],
          ['name'=>'GRANDMASTER','min'=>2000, 'color'=>'#ffd700'],
        ];
        foreach($ai_tiers_minimap as $tm):
          $is_active  = ($ai_tier['name'] === $tm['name']);
          $is_reached = ($ai_rating >= $tm['min']);
          $tc         = $tm['color'];
          $cls = $is_active ? 'rp-mini-active' : ($is_reached ? 'rp-mini-reached' : 'rp-mini-locked');
        ?>
        <div class="rp-mini-tier <?php echo $cls ?>"
             style="--rtc:<?php echo $is_active||$is_reached ? $tc : 'rgba(238,240,255,.15)' ?>"></div>
        <?php endforeach ?>
      </div>

      <!-- Right: rank VS AI + LB button -->
      <div class="rp-right">
        <div class="rp-rank-no">
          <span class="rp-rank-label">RANK VS AI</span>
          <span class="rp-rank-num" id="ai-stat-rank-display">#<?php echo $ai_rank ?></span>
        </div>
        <div class="rp-lihat-ring">
          <span class="rp-lihat-txt">VS AI</span>
        </div>
      </div>
    </div>

    <!-- SECTION: AI Stats -->
    <div class="section-row"><div class="section-line"></div><div class="section-title">🤖 Statistik VS AI</div><div class="section-line"></div></div>

    <div class="card cv-ai" style="margin-bottom:1.5rem">
      <div class="card-ttl">🤖 Win / Loss / Draw VS AI</div>
      <div class="wr-grid">
        <div class="wr-box"><div class="wr-box-val c-win" id="ai-wins"><?php echo $ai_wins ?></div><div class="wr-box-lbl">Menang</div></div>
        <div class="wr-box"><div class="wr-box-val c-lose" id="ai-losses"><?php echo $ai_losses ?></div><div class="wr-box-lbl">Kalah</div></div>
        <div class="wr-box"><div class="wr-box-val c-draw" id="ai-draws"><?php echo $ai_draws ?></div><div class="wr-box-lbl">Seri</div></div>
      </div>
      <div class="wr-track">
        <?php if($total_ai>0): ?>
          <div class="wr-seg-w" id="ai-bar-w" style="width:<?php echo round($ai_wins/$total_ai*100) ?>%"></div>
          <div class="wr-seg-d" id="ai-bar-d" style="width:<?php echo round($ai_draws/$total_ai*100) ?>%"></div>
          <div class="wr-seg-l" id="ai-bar-l" style="width:<?php echo round($ai_losses/$total_ai*100) ?>%"></div>
        <?php endif ?>
      </div>
      <div class="wr-legend">
        <div class="wr-it"><div class="wr-dot" style="background:var(--win)"></div><span id="ai-wr"><?php echo $winrate_ai ?>%</span> Winrate</div>
        <div class="wr-it"><div class="wr-dot" style="background:var(--muted)"></div><span id="ai-total"><?php echo $total_ai ?></span> Match AI</div>
      </div>
    </div>

    <!-- AI: 3 Kartu Favorit VS AI -->
    <div class="section-row"><div class="section-line"></div><div class="section-title">🃏 3 Kartu Favorit VS AI</div><div class="section-line"></div></div>

    <div class="fav-cards-grid" id="ai-fav-cards-grid">
      <?php
      $ai_rank_labels = ['#1 PALING SERING','#2 FAVORIT','#3 TERPILIH'];
      $ai_max_uses = !empty($ai_fav_cards) ? ($ai_fav_cards[0]['uses'] ?? 1) : 1;

      for ($afi = 0; $afi < 3; $afi++):
        if (!empty($ai_fav_cards[$afi])):
          $afc  = $ai_fav_cards[$afi];
          $arm  = $rarity_meta[$afc['rarity']] ?? $rarity_meta['common'];
          $abar_w = $ai_max_uses > 0 ? round($afc['uses'] / $ai_max_uses * 100) : 0;
      ?>
      <div class="fav-card rarity-<?php echo $afc['rarity'] ?>">
        <div class="shine"></div>
        <div class="fav-corner fav-tl"></div>
        <div class="fav-corner fav-br"></div>
        <div class="fav-rank-badge"><?php echo $ai_rank_labels[$afi] ?></div>
        <span class="fav-icon"><?php echo $afc['icon'] ?></span>
        <div class="fav-rarity"><?php echo $arm['label'] ?></div>
        <div class="fav-name"><?php echo htmlspecialchars($afc['name']) ?></div>
        <div class="fav-desc"><?php echo htmlspecialchars($afc['desc']) ?></div>
        <div class="fav-uses-bar-wrap">
          <div class="fav-uses-bar" style="width:<?php echo $abar_w ?>%"></div>
        </div>
        <div class="fav-uses-count"><?php echo $afc['uses'] ?></div>
        <div class="fav-uses-lbl">kali dipakai</div>
      </div>
      <?php else: ?>
      <div class="fav-card-empty">
        <span class="fav-empty-icon">🃏</span>
        <span class="fav-empty-text">Belum ada data<br>kartu #<?php echo $afi+1 ?></span>
      </div>
      <?php endif ?>
      <?php endfor ?>

      <?php if(empty($ai_fav_cards)): ?>
      <style>#ai-fav-cards-grid{display:block!important}</style>
      <div style="text-align:center;padding:36px 20px;font-family:'Rajdhani',sans-serif;color:var(--muted);font-size:.78rem;font-weight:600;letter-spacing:.1em;grid-column:1/-1">
        <div style="font-size:2.4rem;margin-bottom:12px;opacity:.3">🃏</div>
        Mainkan beberapa pertandingan VS AI untuk melihat<br>kartu-kartu favorit yang kamu gunakan
      </div>
      <?php endif ?>
    </div>

    <!-- SECTION: RIWAYAT AI -->
    <div class="section-row"><div class="section-line"></div><div class="section-title">📜 10 Match AI Terakhir</div><div class="section-line"></div></div>
    <div class="card cv-hist-ai" style="margin-bottom:1.5rem">
      <div class="card-ttl">🤖 Riwayat VS AI</div>
      <?php if(empty($ai_matches)): ?>
        <div class="empty-state"><span class="empty-icon">🤖</span>Belum ada pertandingan VS AI.</div>
      <?php else: ?>
        <div class="hist-list">
          <?php foreach($ai_matches as $m):
            $res  = $m['result']??'draw';
            $bc   = $res==='won'?'hb-win':($res==='lost'?'hb-lose':'hb-draw');
            $bl   = $res==='won'?'MENANG':($res==='lost'?'KALAH':'SERI');
            $myRW = (int)($m['player_round_wins']??0);
            $aiRW = (int)($m['ai_round_wins']??0);
            $dur  = (int)($m['duration_sec']??0);
            $rock =(int)($m['choice_rock_count']??0);$paper=(int)($m['choice_paper_count']??0);$sci=(int)($m['choice_scissors_count']??0);
            $maxC =max($rock,$paper,$sci);
            $fav  =$maxC===$rock?'🪨':($maxC===$paper?'📄':'✂️');
          ?>
          <div class="hist-item">
            <div class="hist-badge <?php echo $bc ?>"><?php echo $bl ?></div>
            <div class="hist-vs">
              <div class="hist-opp">vs Computer AI</div>
              <div class="hist-meta"><?php echo date('d M, H:i',strtotime($m['played_at']??'now')) ?> · <?php echo $dur ?>d · <?php echo $fav ?></div>
            </div>
            <div class="hist-score"><?php echo $myRW ?>-<?php echo $aiRW ?></div>
            <div class="hist-delta d-nu">AI</div>
          </div>
          <?php endforeach ?>
        </div>
      <?php endif ?>
    </div>

  </div><!-- /panel-ai -->

  <!-- SECTION: PERFORMA & EFISIENSI -->
  <div class="section-row"><div class="section-line"></div><div class="section-title">📊 Performa &amp; Efisiensi</div><div class="section-line"></div></div>

  <div class="grid-2">
    <div class="card cv-session">
      <div class="card-ttl">⏱️ Statistik Sesi</div>
      <div class="sess-grid">
        <div class="sess-box"><div class="sess-val" style="color:#ff9060"><?php echo $total_all ?></div><div class="sess-lbl">Total Match</div></div>
        <div class="sess-box"><div class="sess-val" style="color:var(--paper)"><?php echo $max_streak ?></div><div class="sess-lbl">Best Streak</div></div>
        <div class="sess-box"><div class="sess-val" style="color:var(--scissors)"><?php echo $avg_pvp_duration>0?$avg_pvp_duration.'d':'—' ?></div><div class="sess-lbl">Rata Durasi PvP</div></div>
        <div class="sess-box"><div class="sess-val" style="color:var(--muted)"><?php echo $avg_ai_duration>0?$avg_ai_duration.'d':'—' ?></div><div class="sess-lbl">Rata Durasi AI</div></div>
      </div>
    </div>
    <div class="card cv-combo">
      <div class="card-ttl">💡 Efisiensi Bertarung</div>
      <?php
      $total_wr_combined = $total_all>0 ? round(($wins+$ai_wins)/$total_all*100,1) : 0;
      $rounds_per_match  = ($total_pvp>0 && $total_rounds_pvp>0)
          ? round($total_rounds_pvp/count($pvp_matches),1) : '—';
      ?>
      <div class="combo-row">
        <div><div class="combo-lbl">Winrate Gabungan</div><div class="combo-sub">PvP + VS AI</div></div>
        <div class="combo-val" style="color:var(--win)"><?php echo $total_wr_combined ?>%</div>
      </div>
      <div class="combo-row">
        <div><div class="combo-lbl">Best Win Streak</div><div class="combo-sub">Kemenangan beruntun terbanyak</div></div>
        <div class="combo-val" style="color:#ff9060"><?php echo $max_streak ?>🔥</div>
      </div>
      <div class="combo-row">
        <div><div class="combo-lbl">Peringkat Global</div><div class="combo-sub">Posisi di antara semua pemain</div></div>
        <div class="combo-val" style="color:var(--rc)">#<?php echo $rank ?></div>
      </div>
      <div class="combo-row">
        <div><div class="combo-lbl">Ronde per Match PvP</div><div class="combo-sub">Rata-rata dari 10 match terakhir</div></div>
        <div class="combo-val" style="color:var(--paper)"><?php echo $rounds_per_match ?></div>
      </div>
    </div>
  </div>


</div><!-- /main-content -->

<div class="toast" id="toast"></div>

<script>
/* ── MODE SWITCH ── */
let currentMode = 'pvp';
function switchMode(mode) {
  currentMode = mode;
  const pvpPanel = document.getElementById('panel-pvp');
  const aiPanel  = document.getElementById('panel-ai');
  const btnPvp   = document.getElementById('btn-pvp');
  const btnAi    = document.getElementById('btn-ai');

  if (mode === 'pvp') {
    pvpPanel.style.display = '';
    aiPanel.style.display  = 'none';
    btnPvp.classList.add('active-ranked');
    btnPvp.classList.remove('active-ai');
    btnAi.classList.remove('active-ranked','active-ai');
  } else {
    pvpPanel.style.display = 'none';
    aiPanel.style.display  = '';
    btnAi.classList.add('active-ai');
    btnAi.classList.remove('active-ranked');
    btnPvp.classList.remove('active-ranked','active-ai');
    // Trigger animasi AI tier bar
    setTimeout(()=>{
      const atb = document.getElementById('aiTierBar');
      if(atb) atb.style.width = '<?php echo $ai_tier_pct ?>%';
    }, 120);
  }
}

/* ── CANVAS NODE NETWORK (identik main_menu) ── */
const cv=document.getElementById('bg'),cx=cv.getContext('2d');
let W,H,NS=[];
const COLS=['rgba(255,77,77,','rgba(77,166,255,','rgba(125,255,77,'];
function rsz(){W=cv.width=innerWidth;H=cv.height=innerHeight}
function mkN(){NS=Array.from({length:55},()=>({
  x:Math.random()*W,y:Math.random()*H,
  vx:(Math.random()-.5)*.45,vy:(Math.random()-.5)*.45,
  r:Math.random()*2+.7,col:COLS[Math.floor(Math.random()*3)],
  a:Math.random()*.5+.08,maxA:Math.random()*.5+.1,da:.002
}))}
function frame(){
  cx.clearRect(0,0,W,H);
  const g=cx.createRadialGradient(W/2,H*.4,0,W/2,H*.4,Math.max(W,H)*.72);
  g.addColorStop(0,'rgba(12,15,30,.97)');g.addColorStop(1,'rgba(5,6,13,1)');
  cx.fillStyle=g;cx.fillRect(0,0,W,H);
  for(const n of NS){
    n.x+=n.vx;n.y+=n.vy;
    if(n.x<0||n.x>W)n.vx*=-1;if(n.y<0||n.y>H)n.vy*=-1;
    n.a+=n.da;if(n.a>n.maxA||n.a<.05)n.da*=-1;
    for(const m of NS){
      const d=Math.hypot(n.x-m.x,n.y-m.y);
      if(d<160){cx.beginPath();cx.moveTo(n.x,n.y);cx.lineTo(m.x,m.y);
        cx.strokeStyle=n.col+(1-d/160)*.065+')';cx.lineWidth=.5;cx.stroke();}
    }
    cx.beginPath();cx.arc(n.x,n.y,n.r,0,Math.PI*2);
    cx.fillStyle=n.col+n.a+')';cx.fill();
    if(n.r>1.8){cx.beginPath();cx.arc(n.x,n.y,n.r*2.5,0,Math.PI*2);
      cx.fillStyle=n.col+n.a*.18+')';cx.fill();}
  }
  for(let i=0;i<120;i++){
    const sx=(i*137.5)%W,sy=(i*93.7)%H;
    const sa=.07+.4*Math.abs(Math.sin(Date.now()*.0007+i));
    cx.beginPath();cx.arc(sx,sy,.6,0,Math.PI*2);
    cx.fillStyle=`rgba(238,240,255,${sa})`;cx.fill();
  }
  requestAnimationFrame(frame);
}
window.addEventListener('resize',()=>{rsz();mkN()});rsz();mkN();frame();

/* ── ENERGY LINES (identik main_menu) ── */
const ELC=document.getElementById('EL');
for(let i=0;i<8;i++){
  const e=document.createElement('div');e.className='el';
  e.style.cssText=`left:${Math.random()*100}%;height:${Math.random()*50+20}px;animation-duration:${Math.random()*9+5}s;animation-delay:${Math.random()*9}s;opacity:.35;`;
  ELC.appendChild(e);}

/* ── PARTICLES (identik main_menu) ── */
const PC=document.getElementById('PT');
const PC2=['rgba(255,77,77,','rgba(77,166,255,','rgba(125,255,77,'];
for(let i=0;i<25;i++){
  const p=document.createElement('div');p.className='p';
  const s=Math.random()*4+1,col=PC2[i%3];
  p.style.cssText=`left:${Math.random()*100}%;width:${s}px;height:${s}px;background:${col}${Math.random()*.5+.2});box-shadow:0 0 ${s*3}px ${col}.5);animation-duration:${Math.random()*18+10}s;animation-delay:${Math.random()*16}s;`;
  PC.appendChild(p);}

/* ── TIER DATA (cocok dengan PHP getRankTier) ── */
const TIERS=[
  {min:0,    name:'BRONZE',     icon:'🥉', col:'#cd7f32', next:950},
  {min:950,  name:'SILVER',     icon:'🥈', col:'#c0c0c0', next:1100},
  {min:1100, name:'GOLD',       icon:'🥇', col:'#f5c842', next:1300},
  {min:1300, name:'PLATINUM',   icon:'🪙', col:'#7dff4d', next:1500},
  {min:1500, name:'DIAMOND',    icon:'🔷', col:'#4da6ff', next:1700},
  {min:1700, name:'MASTER',     icon:'💎', col:'#c084fc', next:2000},
  {min:2000, name:'GRANDMASTER',icon:'👑', col:'#ffd700', next:9999},
];
function getTier(r){return [...TIERS].reverse().find(t=>r>=t.min)||TIERS[0];}
function getNextTierName(t){
  const idx=TIERS.findIndex(x=>x.name===t.name);
  return idx<TIERS.length-1?TIERS[idx+1].name:'MAX';
}
function updateTierUI(rating){
  const t    = getTier(rating);
  const next = TIERS.find(x=>x.min>t.min);
  const pct  = next?Math.min(100,Math.round((rating-t.min)/(next.min-t.min)*100)):100;
  const el   = id=>document.getElementById(id);
  if(el('rp-icon')) el('rp-icon').textContent=t.icon;
  if(el('rp-name')) el('rp-name').textContent=t.name;
  if(el('rp-pts'))  el('rp-pts').textContent=Number(rating).toLocaleString('id-ID')+' PTS';
  if(el('tierBar')) el('tierBar').style.width=pct+'%';
  if(el('rp-head-l')){
    if(next) el('rp-head-l').innerHTML=pct+'% menuju <strong style="color:var(--rc)">'+next.name+'</strong>';
    else el('rp-head-l').innerHTML='✦ Rank Tertinggi';
  }
}
function updateAiTierUI(rating){
  const t    = getTier(rating);
  const next = TIERS.find(x=>x.min>t.min);
  const pct  = next?Math.min(100,Math.round((rating-t.min)/(next.min-t.min)*100)):100;
  const el   = id=>document.getElementById(id);
  if(el('ai-rp-icon')) el('ai-rp-icon').textContent=t.icon;
  if(el('ai-rp-name')) el('ai-rp-name').textContent=t.name;
  if(el('ai-rp-pts'))  el('ai-rp-pts').textContent=Number(rating).toLocaleString('id-ID')+' RAI';
  if(el('aiTierBar'))  el('aiTierBar').style.width=pct+'%';
  if(el('ai-rp-head-l')){
    if(next) el('ai-rp-head-l').innerHTML=pct+'% ke tier berikutnya &nbsp;·&nbsp; Butuh <strong style="color:var(--rc)">'+( next.min-rating)+' RAI</strong>';
    else el('ai-rp-head-l').innerHTML='✦ Rank Tertinggi 👑';
  }
  // Update --rc/--rg on AI panel (same vars as PvP panel)
  const panel=document.getElementById('ai-rank-panel');
  if(panel){
    const hexToRgba=(hex,a)=>{const r=parseInt(hex.slice(1,3),16),g=parseInt(hex.slice(3,5),16),b=parseInt(hex.slice(5,7),16);return`rgba(${r},${g},${b},${a})`;};
    panel.style.setProperty('--rc',t.col);
    panel.style.setProperty('--rg',hexToRgba(t.col,.5));
  }
}

/* ── TOAST ── */
function showToast(msg,dur=2800){
  const t=document.getElementById('toast');t.textContent=msg;t.classList.add('show');
  clearTimeout(t._t);t._t=setTimeout(()=>t.classList.remove('show'),dur);
}

/* ── ENTRANCE ANIMATIONS ── */
window.addEventListener('DOMContentLoaded',()=>{
  // Rating bar
  setTimeout(()=>{
    document.getElementById('tierBar').style.width='<?php echo $tier_pct ?>%';
    // AI tier bar (animasi saat panel AI aktif)
    const aiBar=document.getElementById('aiTierBar');
    if(aiBar) aiBar.style.width='<?php echo $ai_tier_pct ?>%';
    document.querySelectorAll('.ch-fill[data-target]').forEach(el=>{
      el.style.width=el.dataset.target;
    });
  },400);

  // Card stagger
  document.querySelectorAll('.card,.hero-bar,.rating-prog,.fav-card,.streak-card').forEach((el,i)=>{
    el.style.opacity='0';el.style.transform='translateY(22px)';
    setTimeout(()=>{
      el.style.transition='opacity .5s ease,transform .5s ease';
      el.style.opacity='1';el.style.transform='translateY(0)';
    },100+i*65);
  });
});

/* ─────────────────────────────────────────────
   REAL-TIME POLLING (setiap 15 detik)
───────────────────────────────────────────── */
let pollTimer,lastPollData=null;

async function fetchStats(){
  try{
    const res=await fetch('../Api/statistik_api.php',{cache:'no-store'});
    if(!res.ok)return;
    const d=await res.json();
    if(!d||d.error)return;

    const now=new Date();
    const ts=now.toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
    document.getElementById('lastUpdate').textContent=`Terakhir diperbarui: ${ts}`;

    if(lastPollData){
      if(d.rating!==lastPollData.rating){
        const delta=d.rating-lastPollData.rating;
        showToast(delta>0?`📈 Rating naik +${delta}!`:`📉 Rating turun ${delta}`);
      }
      if(d.wins!==lastPollData.wins) showToast('🏆 Kemenangan baru tercatat!');
      if(d.current_win_streak>(lastPollData.current_win_streak||0)&&d.current_win_streak>=2)
        showToast(`🔥 Streak ${d.current_win_streak} aktif!`);
    }
    lastPollData=d;

    // Basic stats (hero bar PvP)
    setText('stat-rating', fmt(d.rating));
    setText('stat-streak', d.current_win_streak);
    setText('stat-rank',   '#'+d.rank);

    // Update tier progress bar real-time
    updateTierUI(d.rating);

    // PvP stats
    setText('pvp-wins',d.wins);setText('pvp-losses',d.losses);setText('pvp-draws',d.draws);
    setText('hb-pvp-wins',d.wins);setText('hb-pvp-losses',d.losses);
    const tPvp=d.wins+d.losses+d.draws;
    const wrPvp=tPvp>0?(d.wins/tPvp*100).toFixed(1):0;
    setText('pvp-wr',wrPvp+'%');setText('pvp-total',tPvp+' Match');
    if(tPvp>0){
      setWidth('pvp-bar-w',Math.round(d.wins/tPvp*100)+'%');
      setWidth('pvp-bar-d',Math.round(d.draws/tPvp*100)+'%');
      setWidth('pvp-bar-l',Math.round(d.losses/tPvp*100)+'%');
    }

    // AI stats
    setText('ai-wins',d.ai_wins);setText('ai-losses',d.ai_losses);setText('ai-draws',d.ai_draws);
    setText('hb-ai-wins',d.ai_wins);setText('hb-ai-losses',d.ai_losses);setText('hb-ai-draws',d.ai_draws);
    const tAi=d.ai_wins+d.ai_losses+d.ai_draws;
    const wrAi=tAi>0?(d.ai_wins/tAi*100).toFixed(1):0;
    setText('ai-wr',wrAi+'%');setText('ai-total',tAi+' Match AI');
    if(tAi>0){
      setWidth('ai-bar-w',Math.round(d.ai_wins/tAi*100)+'%');
      setWidth('ai-bar-d',Math.round(d.ai_draws/tAi*100)+'%');
      setWidth('ai-bar-l',Math.round(d.ai_losses/tAi*100)+'%');
    }
    // Update AI rating & tier bar real-time
    // Pakai ai_rating langsung dari server (kolom terpisah dari rating PvP)
    const aiRating = d.ai_rating || 1000;
    setText('ai-stat-rating',Number(aiRating).toLocaleString('id-ID'));
    updateAiTierUI(aiRating);
    if(d.peak_ai_rating) setText('ai-stat-peak-rating',Number(d.peak_ai_rating).toLocaleString('id-ID'));
    if(d.ai_rank){ setText('ai-stat-rank','#'+d.ai_rank); setText('ai-stat-rank-display','#'+d.ai_rank); }

    // Choice bars
    const tCh=(d.total_rock||0)+(d.total_paper||0)+(d.total_scissors||0);
    if(tCh>0){
      const rPct=Math.round(d.total_rock/tCh*100);
      const pPct=Math.round(d.total_paper/tCh*100);
      const sPct=Math.round(d.total_scissors/tCh*100);
      setWidth('bar-rock',rPct+'%');setWidth('bar-paper',pPct+'%');setWidth('bar-scissors',sPct+'%');
      setText('pct-rock',rPct+'%');setText('pct-paper',pPct+'%');setText('pct-scissors',sPct+'%');
    }

    // Streak badge
    const sb=document.getElementById('streakBadge');
    if(sb){
      if(d.current_win_streak>=2){sb.style.display='';const sn=sb.querySelector('#streakNum');if(sn)sn.textContent=d.current_win_streak;else sb.textContent='🔥 Streak '+d.current_win_streak+' Aktif!';}
      else sb.style.display='none';
    }

  }catch(e){console.warn('Poll error:',e);}
}

function setText(id,val){const el=document.getElementById(id);if(el&&el.textContent!=String(val))el.textContent=val;}
function setWidth(id,val){const el=document.getElementById(id);if(el)el.style.width=val;}
function fmt(n){return Number(n).toLocaleString('id-ID');}

fetchStats();
pollTimer=setInterval(fetchStats,15000);
document.addEventListener('visibilitychange',()=>{
  if(document.hidden){clearInterval(pollTimer);}
  else{fetchStats();pollTimer=setInterval(fetchStats,15000);}
});
</script>
<script src="assets/sound_system.js"></script>

<!-- ════════ LEADERBOARD MODAL: PvP ════════ -->
<div class="modal-overlay" id="lb-pvp-modal" onclick="handleLbOverlay(event,'lb-pvp-modal')">
  <div class="lb2-shell">
    <div class="lb2-left">
      <div class="lb2-head">
        <div class="lb2-head-left">
          <div class="lb2-head-eyebrow">⚔️ PvP Ranked Arena</div>
          <div class="lb2-head-title">LEADERBOARD</div>
          <div class="lb2-head-sub">Top 10 Petarung PvP Terbaik</div>
        </div>
        <button class="lb2-close-btn" onclick="closeLbModal('lb-pvp-modal')">✕</button>
      </div>
      <div class="lb2-col-head">
        <span class="lbc-pos">#</span>
        <span class="lbc-player">Petarung</span>
        <span class="lbc-rating">PTS</span>
        <span class="lbc-wr">W/L</span>
      </div>
      <div class="lb2-list" id="lb-pvp-list">
        <?php
        $pvp_tiers_data = [
            [2000,'GRANDMASTER','#ffd700','rgba(255,215,0,.5)','👑','Sang Legenda Hidup'],
            [1700,'MASTER',     '#c084fc','rgba(192,132,252,.5)','💎','Maestro Pertempuran'],
            [1500,'DIAMOND',    '#4da6ff','rgba(77,166,255,.5)','🔷','Ahli Sejati'],
            [1300,'PLATINUM',   '#7dff4d','rgba(125,255,77,.5)','🪙','Veteran Lapangan'],
            [1100,'GOLD',       '#f5c842','rgba(245,200,66,.5)','🥇','Petarung Handal'],
            [950, 'SILVER',     '#c0c0c0','rgba(192,192,192,.5)','🥈','Pejuang Muda'],
            [0,   'BRONZE',     '#cd7f32','rgba(205,127,50,.5)', '🥉','Pemula Berbakat'],
        ];
        $pvp_lb_json = [];
        foreach($lb_pvp as $pe):
            $isMe   = ($pe['id'] === $player_id);
            $posNum = (int)$pe['rank'];
            $e_rating = (int)($pe['rating'] ?? 0);
            $e_wins   = (int)($pe['wins']   ?? 0);
            $e_losses = (int)($pe['losses'] ?? 0);
            $e_draws  = (int)($pe['draws']  ?? 0);
            $e_streak = (int)($pe['current_win_streak'] ?? 0);
            $e_avatar = htmlspecialchars($pe['avatar'] ?? '⚔️');
            $e_username = htmlspecialchars($pe['username']);
            $e_id = htmlspecialchars($pe['id']);
            $e_tier='BRONZE';$e_col='#cd7f32';$e_icon='🥉';$e_desc='Pemula Berbakat';
            foreach($pvp_tiers_data as [$min,$name,$col,$glow,$icon,$desc]){
                if($e_rating>=$min){$e_tier=$name;$e_col=$col;$e_icon=$icon;$e_desc=$desc;break;}
            }
            $e_total = $e_wins+$e_losses+$e_draws;
            $e_wr    = $e_total>0 ? round($e_wins/$e_total*100) : 0;
            $pos_cls = match($posNum){1=>'lb2-gold',2=>'lb2-silver',3=>'lb2-bronze',default=>''};
            $pos_lbl = str_pad($posNum,2,'0',STR_PAD_LEFT);
            $pvp_lb_json[] = ['id'=>$pe['id'],'username'=>$e_username,'avatar'=>$e_avatar,'rating'=>$e_rating,'wins'=>$e_wins,'losses'=>$e_losses,'draws'=>$e_draws,'streak'=>$e_streak,'tier'=>$e_tier,'tierCol'=>$e_col,'tierIcon'=>$e_icon,'tierDesc'=>$e_desc,'rank'=>$posNum,'winrate'=>$e_wr,'isMe'=>$isMe];
        ?>
        <div class="lb2-row <?= $isMe?'lb2-me':'' ?> <?= $pos_cls ?>"
             onclick="showLbProfile('pvp',<?= $posNum-1 ?>)"
             data-idx="<?= $posNum-1 ?>">
          <div class="lb2-r-pos">
            <span class="lb2-pos-num"><?= $pos_lbl ?></span>
            <?php if($posNum<=3): ?><div class="lb2-pos-glow" style="background:var(--color-<?= strtolower($e_tier) ?>)"></div><?php endif ?>
          </div>
          <div class="lb2-r-player">
            <div class="lb2-r-av" style="border-color:var(--color-<?= strtolower($e_tier) ?>);box-shadow:0 0 8px var(--glow-<?= strtolower($e_tier) ?>)"><?= $e_avatar ?></div>
            <div class="lb2-r-info">
              <div class="lb2-r-name <?= $isMe?'lb2-r-name-me':'' ?>"><?= $e_username ?></div>
              <div class="lb2-r-id" style="font-size:0.58rem;color:var(--muted);margin-top:1px;font-family:'Rajdhani',sans-serif;font-weight:600;">@<?= $e_username ?></div>
              <div class="lb2-r-tier" style="color:var(--color-<?= strtolower($e_tier) ?>)"><?= $e_icon ?> <?= $e_tier ?></div>
            </div>
            <?php if($isMe): ?><div class="lb2-you-badge">YOU</div><?php endif ?>
          </div>
          <div class="lb2-r-rating"><span class="lb2-r-rating-val"><?= $e_rating ?></span></div>
          <div class="lb2-r-wl"><span class="lb2-r-w"><?= $e_wins ?>W</span><span class="lb2-r-sep">/</span><span class="lb2-r-l"><?= $e_losses ?>L</span></div>
          <div class="lb2-r-arrow">›</div>
        </div>
        <?php endforeach ?>
        <?php if(empty($lb_pvp)): ?>
        <div style="text-align:center;padding:32px 16px;opacity:.4;font-family:'Rajdhani',sans-serif;font-size:.72rem;color:var(--muted)">Belum ada data leaderboard</div>
        <?php endif ?>
      </div>
      <div class="lb2-foot">
        <div class="lb2-foot-rank">Rank kamu saat ini: <strong style="color:var(--scissors)">#<?= $rank ?></strong><?= $rank>10?' · Belum masuk top 10':'' ?></div>
      </div>
    </div>
    <!-- Right panel -->
    <div class="lb2-right" id="lb-pvp-right">
      <div class="lb2-empty" id="lb-pvp-empty">
        <div class="lb2-empty-icon">👆</div>
        <div class="lb2-empty-title">Pilih Petarung</div>
        <div class="lb2-empty-sub">Klik pemain untuk lihat profil lengkap</div>
      </div>
      <div class="lb2-profile" id="lb-pvp-profile" style="display:none">
        <div class="lb2-prof-hero" id="pvpProfHero">
          <div class="lb2-prof-pos-tag" id="pvpProfPosTag"></div>
          <div class="lb2-prof-av-wrap">
            <div class="lb2-prof-av" id="pvpProfAv"></div>
            <div class="lb2-prof-av-ring" id="pvpProfAvRing"></div>
          </div>
          <div class="lb2-prof-name" id="pvpProfName"></div>
          <div class="lb2-prof-id" id="pvpProfId"></div>
          <div class="lb2-prof-tier-badge" id="pvpProfTierBadge"></div>
        </div>
        <div class="lb2-prof-stats">
          <div class="lb2-stat-card lb2-stat-rating"><div class="lb2-stat-label">Rating</div><div class="lb2-stat-val" id="pvpProfRating">—</div></div>
          <div class="lb2-stat-card lb2-stat-wr"><div class="lb2-stat-label">Win Rate</div><div class="lb2-stat-val" id="pvpProfWR">—</div></div>
          <div class="lb2-stat-card lb2-stat-streak"><div class="lb2-stat-label">Streak 🔥</div><div class="lb2-stat-val" id="pvpProfStreak">—</div></div>
        </div>
        <div class="lb2-wdl-section">
          <div class="lb2-wdl-label-row">
            <span class="lb2-wdl-tag wdl-w" id="pvpProfWins">—W</span>
            <span class="lb2-wdl-tag wdl-d" id="pvpProfDraws">—D</span>
            <span class="lb2-wdl-tag wdl-l" id="pvpProfLosses">—L</span>
          </div>
          <div class="lb2-wdl-bar">
            <div class="lb2-wdl-seg wdl-seg-w" id="pvpWdlW"></div>
            <div class="lb2-wdl-seg wdl-seg-d" id="pvpWdlD"></div>
            <div class="lb2-wdl-seg wdl-seg-l" id="pvpWdlL"></div>
          </div>
        </div>
        <div class="lb2-tier-progress" id="pvpProfTierSection">
          <div class="lb2-tp-label">
            <span id="pvpProfTierLabel">—</span>
            <span class="lb2-tp-next" id="pvpProfTierNext">—</span>
          </div>
          <div class="lb2-tp-track"><div class="lb2-tp-fill" id="pvpProfTierFill"></div></div>
        </div>
        <div class="lb2-prof-actions">
          <a class="lb2-act-btn lb2-act-profile" id="pvpProfViewBtn" href="#">👤 Lihat Profil</a>
        </div>
        <div class="lb2-me-note" id="pvpProfMeNote" style="display:none">✦ Ini profil kamu ✦</div>
      </div>
    </div>
  </div>
</div>

<!-- ════════ LEADERBOARD MODAL: VS AI ════════ -->
<div class="modal-overlay" id="lb-ai-modal" onclick="handleLbOverlay(event,'lb-ai-modal')">
  <div class="lb2-shell">
    <div class="lb2-left">
      <div class="lb2-head">
        <div class="lb2-head-left">
          <div class="lb2-head-eyebrow">🤖 VS AI Arena</div>
          <div class="lb2-head-title">LEADERBOARD</div>
          <div class="lb2-head-sub">Top 10 Petarung Terbaik AI</div>
        </div>
        <button class="lb2-close-btn" onclick="closeLbModal('lb-ai-modal')">✕</button>
      </div>
      <div class="lb2-col-head">
        <span class="lbc-pos">#</span>
        <span class="lbc-player">Petarung</span>
        <span class="lbc-rating">RAI</span>
        <span class="lbc-wr">W/L</span>
      </div>
      <div class="lb2-list" id="lb-ai-list">
        <?php
        $ai_lb_json = [];
        foreach($lb_ai as $ae):
            $isMe   = ($ae['id'] === $player_id);
            $posNum = (int)$ae['rank'];
            $e_rating = (int)($ae['rating'] ?? 0);
            $e_wins   = (int)($ae['wins']   ?? 0);
            $e_losses = (int)($ae['losses'] ?? 0);
            $e_draws  = (int)($ae['draws']  ?? 0);
            $e_streak = (int)($ae['current_win_streak'] ?? 0);
            $e_avatar = htmlspecialchars($ae['avatar'] ?? '⚔️');
            $e_username = htmlspecialchars($ae['username']);
            $e_id = htmlspecialchars($ae['id']);
            $e_tier='BRONZE';$e_col='#cd7f32';$e_icon='🥉';$e_desc='Pemula Berbakat';
            foreach($pvp_tiers_data as [$min,$name,$col,$glow,$icon,$desc]){
                if($e_rating>=$min){$e_tier=$name;$e_col=$col;$e_icon=$icon;$e_desc=$desc;break;}
            }
            $e_total = $e_wins+$e_losses+$e_draws;
            $e_wr    = $e_total>0 ? round($e_wins/$e_total*100) : 0;
            $pos_cls = match($posNum){1=>'lb2-gold',2=>'lb2-silver',3=>'lb2-bronze',default=>''};
            $pos_lbl = str_pad($posNum,2,'0',STR_PAD_LEFT);
            $ai_lb_json[] = ['id'=>$ae['id'],'username'=>$e_username,'avatar'=>$e_avatar,'rating'=>$e_rating,'wins'=>$e_wins,'losses'=>$e_losses,'draws'=>$e_draws,'streak'=>$e_streak,'tier'=>$e_tier,'tierCol'=>$e_col,'tierIcon'=>$e_icon,'tierDesc'=>$e_desc,'rank'=>$posNum,'winrate'=>$e_wr,'isMe'=>$isMe];
        ?>
        <div class="lb2-row <?= $isMe?'lb2-me':'' ?> <?= $pos_cls ?>"
             onclick="showLbProfile('ai',<?= $posNum-1 ?>)"
             data-idx="<?= $posNum-1 ?>">
          <div class="lb2-r-pos">
            <span class="lb2-pos-num"><?= $pos_lbl ?></span>
            <?php if($posNum<=3): ?><div class="lb2-pos-glow" style="background:var(--color-<?= strtolower($e_tier) ?>)"></div><?php endif ?>
          </div>
          <div class="lb2-r-player">
            <div class="lb2-r-av" style="border-color:var(--color-<?= strtolower($e_tier) ?>);box-shadow:0 0 8px var(--glow-<?= strtolower($e_tier) ?>)"><?= $e_avatar ?></div>
            <div class="lb2-r-info">
              <div class="lb2-r-name <?= $isMe?'lb2-r-name-me':'' ?>"><?= $e_username ?></div>
              <div class="lb2-r-id" style="font-size:0.58rem;color:var(--muted);margin-top:1px;font-family:'Rajdhani',sans-serif;font-weight:600;">@<?= $e_username ?></div>
              <div class="lb2-r-tier" style="color:var(--color-<?= strtolower($e_tier) ?>)"><?= $e_icon ?> <?= $e_tier ?></div>
            </div>
            <?php if($isMe): ?><div class="lb2-you-badge">YOU</div><?php endif ?>
          </div>
          <div class="lb2-r-rating"><span class="lb2-r-rating-val"><?= $e_rating ?></span></div>
          <div class="lb2-r-wl"><span class="lb2-r-w"><?= $e_wins ?>W</span><span class="lb2-r-sep">/</span><span class="lb2-r-l"><?= $e_losses ?>L</span></div>
          <div class="lb2-r-arrow">›</div>
        </div>
        <?php endforeach ?>
        <?php if(empty($lb_ai)): ?>
        <div style="text-align:center;padding:32px 16px;opacity:.4;font-family:'Rajdhani',sans-serif;font-size:.72rem;color:var(--muted)">Belum ada data leaderboard</div>
        <?php endif ?>
      </div>
      <div class="lb2-foot">
        <div class="lb2-foot-rank">Rank AI kamu: <strong style="color:var(--scissors)">#<?= $ai_rank ?></strong><?= $ai_rank>10?' · Belum masuk top 10':'' ?></div>
      </div>
    </div>
    <!-- Right panel -->
    <div class="lb2-right" id="lb-ai-right">
      <div class="lb2-empty" id="lb-ai-empty">
        <div class="lb2-empty-icon">👆</div>
        <div class="lb2-empty-title">Pilih Petarung</div>
        <div class="lb2-empty-sub">Klik pemain untuk lihat profil lengkap</div>
      </div>
      <div class="lb2-profile" id="lb-ai-profile" style="display:none">
        <div class="lb2-prof-hero" id="aiProfHero">
          <div class="lb2-prof-pos-tag" id="aiProfPosTag"></div>
          <div class="lb2-prof-av-wrap">
            <div class="lb2-prof-av" id="aiProfAv"></div>
            <div class="lb2-prof-av-ring" id="aiProfAvRing"></div>
          </div>
          <div class="lb2-prof-name" id="aiProfName"></div>
          <div class="lb2-prof-id" id="aiProfId"></div>
          <div class="lb2-prof-tier-badge" id="aiProfTierBadge"></div>
        </div>
        <div class="lb2-prof-stats">
          <div class="lb2-stat-card"><div class="lb2-stat-label">Rating AI</div><div class="lb2-stat-val" id="aiProfRating">—</div></div>
          <div class="lb2-stat-card"><div class="lb2-stat-label">Win Rate</div><div class="lb2-stat-val" id="aiProfWR">—</div></div>
          <div class="lb2-stat-card"><div class="lb2-stat-label">Streak 🔥</div><div class="lb2-stat-val" id="aiProfStreak">—</div></div>
        </div>
        <div class="lb2-wdl-section">
          <div class="lb2-wdl-label-row">
            <span class="lb2-wdl-tag wdl-w" id="aiProfWins">—W</span>
            <span class="lb2-wdl-tag wdl-d" id="aiProfDraws">—D</span>
            <span class="lb2-wdl-tag wdl-l" id="aiProfLosses">—L</span>
          </div>
          <div class="lb2-wdl-bar">
            <div class="lb2-wdl-seg wdl-seg-w" id="aiWdlW"></div>
            <div class="lb2-wdl-seg wdl-seg-d" id="aiWdlD"></div>
            <div class="lb2-wdl-seg wdl-seg-l" id="aiWdlL"></div>
          </div>
        </div>
        <div class="lb2-tier-progress">
          <div class="lb2-tp-label">
            <span id="aiProfTierLabel">—</span>
            <span class="lb2-tp-next" id="aiProfTierNext">—</span>
          </div>
          <div class="lb2-tp-track"><div class="lb2-tp-fill" id="aiProfTierFill"></div></div>
        </div>
        <div class="lb2-prof-actions">
          <a class="lb2-act-btn lb2-act-profile" id="aiProfViewBtn" href="#">👤 Lihat Profil</a>
        </div>
        <div class="lb2-me-note" id="aiProfMeNote" style="display:none">✦ Ini profil kamu ✦</div>
      </div>
    </div>
  </div>
</div>

<script>
/* ════════ LEADERBOARD MODAL JS ════════ */
const LB_DATA = {
  pvp: <?= json_encode(array_values($pvp_lb_json)) ?>,
  ai:  <?= json_encode(array_values($ai_lb_json)) ?>,
};

const STAT_TIERS=[
  {min:0,    name:'BRONZE',     icon:'🥉',col:'#cd7f32',next:950},
  {min:950,  name:'SILVER',     icon:'🥈',col:'#c0c0c0',next:1100},
  {min:1100, name:'GOLD',       icon:'🥇',col:'#f5c842',next:1300},
  {min:1300, name:'PLATINUM',   icon:'🪙',col:'#7dff4d',next:1500},
  {min:1500, name:'DIAMOND',    icon:'🔷',col:'#4da6ff',next:1700},
  {min:1700, name:'MASTER',     icon:'💎',col:'#c084fc',next:2000},
  {min:2000, name:'GRANDMASTER',icon:'👑',col:'#ffd700',next:9999},
];
function statGetTier(r){return [...STAT_TIERS].reverse().find(t=>r>=t.min)||STAT_TIERS[0];}

function openLbModal(mode){
  const id = mode==='pvp' ? 'lb-pvp-modal' : 'lb-ai-modal';
  document.getElementById(id).classList.add('show');
  document.body.style.overflow='hidden';
  // reset right panel
  const pfx = mode==='pvp' ? 'pvp' : 'ai';
  const empty   = document.getElementById('lb-'+mode+'-empty');
  const profile = document.getElementById('lb-'+mode+'-profile');
  if(empty)   empty.style.display='';
  if(profile) profile.style.display='none';
  // remove active row
  document.querySelectorAll('#lb-'+mode+'-list .lb2-row').forEach(r=>r.classList.remove('lb2-row-active'));
}
function closeLbModal(id){
  document.getElementById(id).classList.remove('show');
  document.body.style.overflow='';
}
function handleLbOverlay(e,id){if(e.target===document.getElementById(id))closeLbModal(id);}

function showLbProfile(mode,idx){
  const data = LB_DATA[mode][idx];
  if(!data) return;
  const pfx = mode==='pvp' ? 'pvp' : 'ai';
  const t = statGetTier(data.rating);
  const next = STAT_TIERS.find(x=>x.min>t.min);
  const pct  = next ? Math.min(100,Math.round((data.rating-t.min)/(next.min-t.min)*100)) : 100;
  const tierNameLower = t.name.toLowerCase();

  // Highlight row
  document.querySelectorAll('#lb-'+mode+'-list .lb2-row').forEach(r=>r.classList.remove('lb2-row-active'));
  const row = document.querySelector('#lb-'+mode+'-list .lb2-row[data-idx="'+idx+'"]');
  if(row) row.classList.add('lb2-row-active');

  // Show profile
  const empty   = document.getElementById('lb-'+mode+'-empty');
  const profile = document.getElementById('lb-'+mode+'-profile');
  if(empty)   empty.style.display='none';
  if(profile){
    profile.style.display='flex';
    profile.style.setProperty('--tier-col', 'var(--color-' + tierNameLower + ')');
    profile.style.setProperty('--tier-glow', 'var(--glow-' + tierNameLower + ')');
  }

  const posLabels=['#1 PERTAMA','#2 KEDUA','#3 KETIGA'];
  const posLabel  = idx<3 ? posLabels[idx] : '#'+(idx+1)+' PERINGKAT';

  // Hero section
  const hero = document.getElementById(pfx+'ProfHero');
  if(hero){
    hero.style.borderColor='var(--tier-col)';
    hero.style.setProperty('--prof-col', 'var(--tier-glow)');
  }
  const ptag=document.getElementById(pfx+'ProfPosTag');
  if(ptag){
    ptag.textContent=posLabel;
    ptag.style.borderColor='color-mix(in srgb, var(--tier-col) 33%, transparent)';
    ptag.style.color='var(--tier-col)';
    ptag.style.background='color-mix(in srgb, var(--tier-col) 7%, transparent)';
  }
  const pav=document.getElementById(pfx+'ProfAv');
  if(pav) pav.textContent=data.avatar;
  const pavr=document.getElementById(pfx+'ProfAvRing');
  if(pavr) pavr.style.borderColor='var(--tier-col)';
  const pname=document.getElementById(pfx+'ProfName');
  if(pname){pname.textContent=data.username;pname.style.color=data.isMe?'var(--color-diamond)':'var(--text)';}
  const pid=document.getElementById(pfx+'ProfId');
  if(pid) pid.textContent='@'+data.username;
  const ptb=document.getElementById(pfx+'ProfTierBadge');
  if(ptb){
    ptb.textContent=t.icon+' '+t.name;
    ptb.style.borderColor='color-mix(in srgb, var(--tier-col) 40%, transparent)';
    ptb.style.color='var(--tier-col)';
    ptb.style.background='color-mix(in srgb, var(--tier-col) 9%, transparent)';
  }

  // Stats
  const pr=document.getElementById(pfx+'ProfRating');
  if(pr){pr.textContent=data.rating;pr.style.color='var(--tier-col)';}
  const pw=document.getElementById(pfx+'ProfWR');
  if(pw){
    pw.textContent=data.winrate+'%';
    pw.style.color = data.winrate>=60?'var(--win)':data.winrate>=40?'var(--gold)':'var(--lose)';
  }
  const ps=document.getElementById(pfx+'ProfStreak');
  if(ps){
    ps.textContent=(data.streak||0)+'🔥';
    ps.style.color = data.streak>=3?'var(--color-grandmaster)':data.streak>0?'var(--color-gold)':'var(--muted)';
  }

  // WDL
  const tot=data.wins+data.draws+data.losses;
  const wr=(tot>0?Math.round(data.wins/tot*100):0);
  const dr=(tot>0?Math.round(data.draws/tot*100):0);
  const lr=(tot>0?Math.round(data.losses/tot*100):0);
  const winsEl=document.getElementById(pfx+'ProfWins');
  const drawsEl=document.getElementById(pfx+'ProfDraws');
  const lossesEl=document.getElementById(pfx+'ProfLosses');
  if(winsEl) winsEl.textContent=data.wins+'W';
  if(drawsEl) drawsEl.textContent=data.draws+'D';
  if(lossesEl) lossesEl.textContent=data.losses+'L';
  setTimeout(()=>{
    const ww=document.getElementById(pfx+'WdlW');
    const wd=document.getElementById(pfx+'WdlD');
    const wl=document.getElementById(pfx+'WdlL');
    if(ww) ww.style.width=wr+'%';
    if(wd) wd.style.width=dr+'%';
    if(wl) wl.style.width=lr+'%';
  },80);

  // Tier progress
  const tl=document.getElementById(pfx+'ProfTierLabel');
  const tn=document.getElementById(pfx+'ProfTierNext');
  const tf=document.getElementById(pfx+'ProfTierFill');
  if(tl){tl.textContent=t.icon+' '+t.name;tl.style.color='var(--tier-col)';}
  if(tn) tn.textContent=next?'→ '+next.name:'Max Tier 👑';
  setTimeout(()=>{
    if(tf){
      tf.style.width=pct+'%';
      tf.style.background='var(--tier-col)';
      tf.style.boxShadow='0 0 8px var(--tier-glow)';
    }
  },80);

  // View profile button
  const vb=document.getElementById(pfx+'ProfViewBtn');
  if(vb) vb.href='profile.php?id='+encodeURIComponent(data.id);

  // Me note
  const mn=document.getElementById(pfx+'ProfMeNote');
  if(mn) mn.style.display=data.isMe?'flex':'none';
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
// ── ESC key closes any open leaderboard modal ──
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    ['lb-pvp-modal','lb-ai-modal'].forEach(id => {
      const el = document.getElementById(id);
      if (el && el.classList.contains('show')) closeLbModal(id);
    });
  }
});
</script>
</body>
</html>