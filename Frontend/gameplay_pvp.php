<?php
// ══════════════════════════════════════════════
//  GAMEPLAY PvP — Real-Time Battle
//  Data match dikirim dari lobby_pvp via sessionStorage
// ══════════════════════════════════════════════
session_start();
if (!isset($_SESSION['player_id'])) {
    header('Location: Landing_page.php');
    exit;
}

// ── FIX: ISOLASI IDENTITY PER TAB ──────────────────────────────────────
// Gunakan ?pid= dari URL jika ada & valid, agar identity tidak tertimpa
// oleh session player lain yang login di browser yang sama.
$pid_from_url = trim($_GET['pid'] ?? '');
$allowed_ids  = $_SESSION['allowed_player_ids'] ?? [$_SESSION['player_id']];

if ($pid_from_url !== '' && in_array($pid_from_url, $allowed_ids)) {
    $player_id   = $pid_from_url;
    $player_name = $_SESSION['player_names'][$player_id] ?? strtoupper($player_id);
} else {
    // Fallback ke session aktif (akses langsung tanpa pid)
    $player_id   = $_SESSION['player_id'];
    $player_name = $_SESSION['player_name'] ?? strtoupper($player_id);
}
// ───────────────────────────────────────────────────────────────────────

// ── FIX CROSS-DEVICE: match_data via URL query param ──────────────────
// Ketika lobby mengirim player ke gameplay_pvp, lobby menyimpan match_data
// di sessionStorage (hanya tersedia di tab/browser yang sama).
// Untuk device berbeda, match_data dikirim via ?md= di URL (base64+json)
// dan juga disimpan ke PHP session agar bisa diambil ulang.
$php_match_data_json = 'null'; // default: null (akan diisi JS dari sessionStorage)

// Jika ada ?md= di URL, decode dan simpan ke session
if (!empty($_GET['md'])) {
    $decoded = base64_decode($_GET['md'], true);
    if ($decoded !== false) {
        $parsed = json_decode($decoded, true);
        if ($parsed && isset($parsed['room_id'])) {
            // Simpan ke session berindeks per room_id
            $_SESSION['match_data'][$parsed['room_id']] = $parsed;
            $php_match_data_json = json_encode($parsed);
        }
    }
}

// Jika tidak ada ?md= tapi ada room_id di session, ambil dari session
if ($php_match_data_json === 'null' && !empty($_GET['room_id'])) {
    $rid = trim($_GET['room_id']);
    if (!empty($_SESSION['match_data'][$rid])) {
        $php_match_data_json = json_encode($_SESSION['match_data'][$rid]);
    }
}
// ───────────────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PvP Battle – Batu Gunting Kertas</title>
<link href="https://fonts.googleapis.com/css2?family=Luckiest+Guy&family=Poppins:wght@400;600;700;800&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
<style>
:root {
    --bg:          #05060d;
    --dark:        #05060d;
    --mid:         #0b0d1a;
    --card:        rgba(22,27,34,0.88);
    --inner:       #1c2330;
    --accent:      #ffd700;
    --blue:        #4facfe;
    --purple:      #f093fb;
    --green:       #4affbb;
    --red:         #ff5e5e;
    --orange:      #ffa94d;
    --border:      rgba(48,54,61,0.7);
    --text:        #e6edf3;
    --muted:       rgba(230,237,243,0.45);
    --p1-color:    #4facfe;
    --p2-color:    #f093fb;
    --hp-green:    #4affbb;
    --hp-mid:      #f7c948;
    --hp-low:      #ff5e5e;
    /* BG layer refs */
    --line:        rgba(240,244,255,.06);
    --glass:       rgba(240,244,255,.04);
    --faint:       rgba(240,244,255,.08);
}
*{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;}
body{
    background:var(--dark);
    color:var(--text);
    font-family:'Poppins',sans-serif;
    display:flex;justify-content:center;align-items:center;
    min-height:100vh;
    padding:20px 0;
}

/* ════ BACKGROUND LAYERS (animated — dari pvp_edit) ════ */
canvas#bg{position:fixed;inset:0;z-index:0;}
.hex-layer{
    position:fixed;inset:0;z-index:1;pointer-events:none;opacity:.04;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='60' height='104'%3E%3Cpolygon points='30,2 58,17 58,47 30,62 2,47 2,17' fill='none' stroke='%234da6ff' stroke-width='0.8'/%3E%3Cpolygon points='30,52 58,67 58,97 30,112 2,97 2,67' fill='none' stroke='%234da6ff' stroke-width='0.8'/%3E%3C/svg%3E");
    background-size:60px 104px;
}
.noise{
    position:fixed;inset:0;z-index:2;pointer-events:none;opacity:.025;
    background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
    background-size:200px 200px;
}
.elines{position:fixed;inset:0;z-index:3;pointer-events:none;overflow:hidden;}
.el{
    position:absolute;width:1px;
    background:linear-gradient(to bottom,transparent,rgba(77,166,255,.35),transparent);
    animation:elfall linear infinite;
}
@keyframes elfall{from{transform:translateY(-100vh);opacity:0}10%,90%{opacity:1}to{transform:translateY(100vh);opacity:0}}
.scanline{
    position:fixed;inset:0;z-index:4;pointer-events:none;
    background:repeating-linear-gradient(to bottom,transparent 0,transparent 3px,rgba(0,0,0,.04) 3px,rgba(0,0,0,.04) 4px);
}
.vignette{
    position:fixed;inset:0;z-index:4;pointer-events:none;
    background:radial-gradient(ellipse at center,transparent 40%,rgba(0,0,0,.5) 100%);
}
.bparticles{position:fixed;inset:0;z-index:3;pointer-events:none;overflow:hidden;}
.bp{position:absolute;border-radius:50%;animation:bpfloat linear infinite;}
@keyframes bpfloat{from{transform:translateY(110vh) rotate(0deg);opacity:0}10%,90%{opacity:1}to{transform:translateY(-10vh) rotate(360deg);opacity:0}}
.corner{position:fixed;z-index:6;pointer-events:none;}
.corner::before,.corner::after{content:'';position:absolute;background:rgba(79,172,254,.55);box-shadow:0 0 6px rgba(79,172,254,.6);}
.corner::before{width:2px;height:40px;}.corner::after{width:40px;height:2px;}
.corner::before,.corner::after{top:0;left:0;}
.c-tl{top:16px;left:16px;}.c-tr{top:16px;right:16px;transform:scaleX(-1);}
.c-bl{bottom:16px;left:16px;transform:scaleY(-1);}.c-br{bottom:16px;right:16px;transform:scale(-1);}

/* ── CONTAINER ── */
.game-wrap{
    position:relative;z-index:10;
    width:94%;max-width:560px;
    background:
        linear-gradient(160deg, rgba(18,24,42,.96) 0%, rgba(10,14,28,.98) 100%);
    border-radius:28px;
    padding:0 0 22px;
    /* Multi-layer border: outer glow + inner glass line */
    box-shadow:
        0 0 0 1px rgba(79,172,254,.18),          /* neon blue rim */
        0 0 0 2px rgba(255,255,255,.04),           /* soft white outer */
        0 28px 70px rgba(0,0,0,.85),
        0 0 60px rgba(79,172,254,.09),
        0 0 120px rgba(240,147,251,.05),
        inset 0 1px 0 rgba(255,255,255,.08),
        inset 0 0 40px rgba(79,172,254,.03);
    backdrop-filter:blur(24px);
    overflow:hidden;
}

/* Animated corner scanline accent */
.game-wrap::before{
    content:'';
    position:absolute;inset:0;z-index:0;pointer-events:none;
    background:
        linear-gradient(180deg,
            rgba(79,172,254,.05) 0%,
            transparent 18%,
            transparent 82%,
            rgba(240,147,251,.04) 100%);
    border-radius:28px;
}

/* Top edge glow line */
.game-wrap::after{
    content:'';
    position:absolute;top:0;left:10%;right:10%;height:1px;z-index:1;pointer-events:none;
    background:linear-gradient(90deg,
        transparent 0%,
        rgba(79,172,254,.6) 30%,
        rgba(255,255,255,.8) 50%,
        rgba(240,147,251,.5) 70%,
        transparent 100%);
    filter:blur(.5px);
}

/* ── HEADER ── */
.game-header{
    display:flex;align-items:center;justify-content:space-between;
    padding:16px 22px 15px;
    background:linear-gradient(135deg,
        rgba(79,172,254,.1) 0%,
        rgba(30,38,60,.6) 50%,
        rgba(240,147,251,.07) 100%);
    border-bottom:1px solid rgba(79,172,254,.15);
    position:relative;z-index:1;
}
.game-header::after{
    content:'';position:absolute;bottom:-1px;left:16px;right:16px;height:1px;
    background:linear-gradient(90deg,
        transparent,
        rgba(79,172,254,.5),
        rgba(255,255,255,.3),
        rgba(240,147,251,.4),
        transparent);
}
.game-title{
    font-family:'Luckiest Guy',cursive;
    font-size:1.55rem;color:var(--accent);
    letter-spacing:3px;
    text-shadow:
        0 0 20px rgba(255,215,0,.6),
        0 0 50px rgba(255,215,0,.25),
        0 2px 0 rgba(0,0,0,.6);
    display:flex;align-items:center;gap:10px;
}
.live-badge-merged{
    display:inline-flex;align-items:center;gap:5px;
    font-size:.48rem;font-weight:700;letter-spacing:.18em;font-family:'Poppins',sans-serif;
    padding:3px 8px;border-radius:20px;
    background:rgba(255,94,94,.14);border:1px solid rgba(255,94,94,.35);color:#f87171;
    vertical-align:middle;
    box-shadow:0 0 10px rgba(255,94,94,.15);
}
.live-dot-merged{
    width:5px;height:5px;border-radius:50%;background:#f87171;
    animation:livepulse .9s ease-in-out infinite alternate;
    box-shadow:0 0 6px #f87171;
}
@keyframes livepulse{from{opacity:.4;transform:scale(.8)}to{opacity:1;transform:scale(1.2)}}
.btn-quit{
    font-size:.65rem;font-weight:700;letter-spacing:.12em;
    text-transform:uppercase;color:rgba(230,237,243,.5);
    background:rgba(255,94,94,.07);border:1px solid rgba(255,94,94,.2);
    border-radius:10px;padding:7px 16px;cursor:pointer;
    transition:all .22s;
    box-shadow:0 2px 8px rgba(0,0,0,.3);
}
.btn-quit:hover{
    background:rgba(255,94,94,.18);border-color:rgba(255,94,94,.55);
    color:var(--red);transform:scale(1.03);
    box-shadow:0 0 14px rgba(255,94,94,.25);
}

/* ── PLAYERS SECTION ── */
.players-row{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;
    padding:14px 20px 12px;margin-bottom:0;
    position:relative;}

/* Subtle divider after players row */
.players-row::after{
    content:'';position:absolute;bottom:0;left:16px;right:16px;height:1px;
    background:linear-gradient(90deg,transparent,rgba(79,172,254,.15),rgba(240,147,251,.12),transparent);
}

.pc{flex:1;display:flex;flex-direction:column;gap:6px;}
.pc.right{align-items:flex-end;}

.pc-info{display:flex;align-items:center;gap:8px;}
.right .pc-info{flex-direction:row;justify-content:flex-end;}
.pc-avatar{
    width:40px;height:40px;border-radius:50%;
    display:flex;align-items:center;justify-content:center;
    font-size:1.25rem;border:2px solid;flex-shrink:0;
    transition:box-shadow .3s;
    position:relative;
}
/* Outer ring pulse on avatar */
.pc:not(.right) .pc-avatar{
    border-color:var(--p1-color);
    background:radial-gradient(circle, rgba(79,172,254,.22) 0%, rgba(79,172,254,.05) 100%);
    box-shadow:0 0 0 3px rgba(79,172,254,.12), 0 0 18px rgba(79,172,254,.3);
}
.right .pc-avatar{
    border-color:var(--p2-color);
    background:radial-gradient(circle, rgba(240,147,251,.22) 0%, rgba(240,147,251,.05) 100%);
    box-shadow:0 0 0 3px rgba(240,147,251,.12), 0 0 18px rgba(240,147,251,.3);
}
.pc-name{font-size:.72rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;}
.pc:not(.right) .pc-name{color:var(--p1-color);text-shadow:0 0 12px rgba(79,172,254,.4);}
.pc-you{font-size:.58rem;color:var(--muted);font-style:italic;}
.pc-id{font-size:.58rem;color:var(--muted);letter-spacing:0.5px;margin-bottom:2px;}

/* ── HP BAR — CRYSTAL THEME ── */
.hp-section{display:flex;flex-direction:column;gap:4px;width:100%;}
.hp-row{display:flex;justify-content:flex-end;align-items:center;gap:6px;}
.right .hp-row{justify-content:flex-start;}
.hp-label{
    font-size:.44rem;font-weight:700;letter-spacing:4px;
    color:rgba(255,255,255,.18);text-transform:uppercase;
}

/* Hex-frame wrapper for HP number */
.hp-val-wrap{
    position:relative;display:inline-flex;align-items:center;justify-content:center;
    min-width:58px;height:22px;
}
.hp-val-wrap::before{
    content:'';position:absolute;inset:0;
    background:rgba(0,0,0,.6);
    clip-path:polygon(8px 0%,calc(100% - 8px) 0%,100% 50%,calc(100% - 8px) 100%,8px 100%,0% 50%);
}
.hp-val-wrap::after{
    content:'';position:absolute;inset:0;
    background:linear-gradient(135deg,rgba(255,255,255,.14) 0%,transparent 50%,rgba(255,255,255,.06) 100%);
    clip-path:polygon(8px 0%,calc(100% - 8px) 0%,100% 50%,calc(100% - 8px) 100%,8px 100%,0% 50%);
}
.hp-val{
    font-size:.78rem;font-weight:900;color:var(--hp-green);
    transition:color .6s ease;
    font-family:'Orbitron',sans-serif;
    letter-spacing:1px;position:relative;z-index:1;
}
.right .hp-val{color:var(--p2-color);}

/* HP Track — crystal shell */
/* HP Track — skewed futuristic neon container */
.hp-track{
    width:100%;height:12px;
    background:rgba(5, 6, 13, 0.85);
    border:1px solid rgba(255, 255, 255, 0.08);
    border-radius:4px;overflow:hidden;
    position:relative;
    box-shadow: 
        0 4px 12px rgba(0, 0, 0, 0.5),
        inset 0 1px 3px rgba(0, 0, 0, 0.8);
    transform:skewX(-12deg);
}

/* Subtle segment tick marks */
.hp-track::before{
    content:'';
    position:absolute;inset:0;z-index:4;pointer-events:none;
    background:repeating-linear-gradient(
        90deg,
        transparent 0px,
        transparent calc(10% - 1px),
        rgba(255, 255, 255, 0.08) calc(10% - 1px),
        rgba(255, 255, 255, 0.08) 10%
    );
}

.hp-track::after{display:none !important;}

/* P1 Green fill — horizontal cyan-green neon gradient */
.hp-fill{
    height:100%;border-radius:2px;
    position:relative;overflow:hidden;
    transition:width 1.1s cubic-bezier(0.19, 1, 0.22, 1), background .6s ease;
    background:linear-gradient(90deg, #00f2fe 0%, #4affbb 100%);
    box-shadow:
        0 0 12px rgba(74, 255, 187, 0.4),
        inset 0 1px 1px rgba(255, 255, 255, 0.3);
}

.hp-fill::before,
.hp-fill::after{display:none !important;}

/* P2 track: fill dari kanan ke kiri */
.right .hp-track{
    direction:rtl;
    transform:skewX(12deg);
}

/* P2 Green fill — horizontal cyan-green neon gradient */
.right .hp-fill{
    background:linear-gradient(90deg, #00f2fe 0%, #4affbb 100%);
    box-shadow:
        0 0 12px rgba(74, 255, 187, 0.4),
        inset 0 1px 1px rgba(255, 255, 255, 0.3);
}

/* Mid HP — amber/yellow horizontal gradient */
.hp-mid .hp-fill{
    background:linear-gradient(90deg, #f0b400 0%, #ffe066 100%)!important;
    box-shadow:
        0 0 12px rgba(240, 180, 0, 0.45),
        inset 0 1px 1px rgba(255, 255, 255, 0.3)!important;
    animation:midPulse 2.4s ease-in-out infinite!important;
}
@keyframes midPulse{
    0%,100%{filter:brightness(1);}
    50%{filter:brightness(1.15);}
}

/* Low HP — danger neon red/orange horizontal gradient with faster pulse */
.hp-low .hp-fill{
    background:linear-gradient(90deg, #ff0000 0%, #ff5e62 100%)!important;
    box-shadow:
        0 0 16px rgba(255, 50, 50, 0.6),
        inset 0 1px 1px rgba(255, 255, 255, 0.4)!important;
    animation:lowPulse 0.8s ease-in-out infinite!important;
}
@keyframes lowPulse{
    0%,100%{filter:brightness(1);}
    50%{filter:brightness(1.3);}
}
@keyframes crystLowPulse{
    0%,100%{filter:brightness(1) saturate(1);}
    50%{filter:brightness(1.3) saturate(1.4);}
}

/* ── VS BADGE ── */
.vs-badge{display:flex;flex-direction:column;align-items:center;gap:4px;padding-top:4px;}
.vs-text{
    font-family:'Luckiest Guy',cursive;font-size:1.05rem;letter-spacing:2px;
    color:transparent;
    background:linear-gradient(180deg,rgba(255,255,255,.35),rgba(255,255,255,.1));
    -webkit-background-clip:text;background-clip:text;
}

/* ── ROUND DOTS ── */
.rounds-row{
    display:flex;justify-content:space-between;align-items:center;
    margin:0 20px 12px;padding:9px 16px;
    background:linear-gradient(135deg,rgba(79,172,254,.05) 0%, rgba(0,0,0,.3) 100%);
    border:1px solid rgba(79,172,254,.12);
    border-radius:14px;
    box-shadow:inset 0 1px 0 rgba(255,255,255,.05), 0 2px 12px rgba(0,0,0,.3);
}
.dots{display:flex;gap:7px;}
.dot{
    width:12px;height:12px;border-radius:4px;
    background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);
    transition:all .4s cubic-bezier(.175,.885,.32,1.275);
}
.dot.p1{
    background:var(--p1-color);border-color:var(--p1-color);
    box-shadow:0 0 10px rgba(79,172,254,.7),0 0 22px rgba(79,172,254,.25);
    border-radius:4px;
    transform:scale(1.15);
}
.dot.p2{
    background:var(--p2-color);border-color:var(--p2-color);
    box-shadow:0 0 10px rgba(240,147,251,.7),0 0 22px rgba(240,147,251,.25);
    border-radius:4px;
    transform:scale(1.15);
}
.round-center{display:flex;flex-direction:column;align-items:center;gap:1px;}
.round-num{font-size:.68rem;font-weight:700;color:var(--accent);letter-spacing:1px;text-shadow:0 0 10px rgba(255,215,0,.4);}
.round-lbl{font-size:.52rem;letter-spacing:2.5px;color:rgba(255,255,255,.18);text-transform:uppercase;}

/* ── BATTLE AREA ── */
.battle-area{
    display:flex;justify-content:space-around;align-items:center;
    height:160px;margin:4px 20px 4px;position:relative;
    background:
        radial-gradient(ellipse at 20% 50%, rgba(79,172,254,.06) 0%, transparent 60%),
        radial-gradient(ellipse at 80% 50%, rgba(240,147,251,.05) 0%, transparent 60%),
        rgba(0,0,0,.22);
    border-radius:18px;
    border:1px solid rgba(79,172,254,.1);
    box-shadow:inset 0 0 30px rgba(0,0,0,.2), 0 4px 20px rgba(0,0,0,.3);
}
.hand-wrap{display:flex;flex-direction:column;align-items:center;gap:8px;}
.hand{width:120px;height:120px;object-fit:contain;filter:drop-shadow(0 8px 24px rgba(0,0,0,.8));}
#p2-hand{transform:scaleX(-1);}

/* ── TIMER ── */
.timer-wrap{display:flex;flex-direction:column;align-items:center;gap:3px;}
.timer-ring{width:50px;height:50px;position:relative;}
.timer-ring svg{transform:rotate(-90deg);}
circle.track{fill:none;stroke:rgba(255,255,255,.06);stroke-width:4;}
circle.prog{fill:none;stroke:var(--accent);stroke-width:4;stroke-linecap:round;stroke-dasharray:138.2;stroke-dashoffset:0;transition:stroke-dashoffset 1s linear,stroke .3s;filter:drop-shadow(0 0 4px rgba(255,215,0,.6));}
.timer-num{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-family:'Luckiest Guy',cursive;font-size:1.15rem;color:var(--accent);text-shadow:0 0 10px rgba(255,215,0,.5);}
.urgent circle.prog{stroke:var(--hp-low);filter:drop-shadow(0 0 5px rgba(255,94,94,.7));}
.urgent .timer-num{color:var(--hp-low);text-shadow:0 0 10px rgba(255,94,94,.6);}
#timeout-msg{font-size:.68rem;color:var(--hp-low);font-weight:700;letter-spacing:1px;min-height:16px;text-align:center;}

/* ── SHAKE ANIM ── */
@keyframes shakeP{0%,100%{transform:rotate(0);}50%{transform:rotate(-18deg);}}
@keyframes shakeP2{0%,100%{transform:scaleX(-1) rotate(0);}50%{transform:scaleX(-1) rotate(-18deg);}}
.sh-p1{animation:shakeP .45s ease-in-out infinite!important;}
.sh-p2{animation:shakeP2 .45s ease-in-out infinite!important;}

/* ── CHOICE BADGE (shows after opponent picks) ── */
.choice-badge{
    position:absolute;top:8px;
    font-size:.65rem;font-weight:700;letter-spacing:.1em;
    background:rgba(74,255,187,.1);border:1px solid rgba(74,255,187,.3);
    color:var(--green);border-radius:20px;padding:3px 10px;
    box-shadow:0 0 10px rgba(74,255,187,.15);
}

/* ── STATUS MESSAGES ── */
#status-bar{
    text-align:center;font-size:.75rem;color:var(--muted);
    min-height:22px;margin:6px 20px 6px;letter-spacing:.04em;
    padding:4px 0;
}
#status-bar.green{color:var(--green);text-shadow:0 0 10px rgba(74,255,187,.4);}
#status-bar.red{color:var(--red);text-shadow:0 0 10px rgba(255,94,94,.4);}
#status-bar.yellow{color:var(--accent);text-shadow:0 0 10px rgba(255,215,0,.3);}
#status-bar.blue{color:var(--blue);text-shadow:0 0 10px rgba(79,172,254,.4);}

hr{border:0;height:1px;margin:8px 20px;
   background:linear-gradient(90deg,transparent,rgba(79,172,254,.2),rgba(240,147,251,.15),transparent);}

/* ── CHOICE SCREEN ── */
.instruction{
    color:rgba(255,255,255,.3);font-size:.68rem;letter-spacing:2px;
    text-transform:uppercase;text-align:center;margin-bottom:12px;
}
.choices{display:flex;justify-content:center;gap:14px;margin-bottom:8px;padding:0 20px;}
.choice{
    cursor:pointer;
    background:linear-gradient(145deg,rgba(30,38,54,.9),rgba(18,24,38,.95));
    padding:14px 10px;
    border-radius:16px;width:100px;border:1.5px solid rgba(255,255,255,.08);
    transition:all .3s cubic-bezier(.175,.885,.32,1.275);
    text-align:center;position:relative;overflow:hidden;
    box-shadow:0 4px 16px rgba(0,0,0,.4);
}
/* Shine sweep on hover */
.choice .cshine{
    position:absolute;top:0;left:-100%;width:50%;height:100%;
    background:linear-gradient(90deg,transparent,rgba(255,255,255,.07),transparent);
    transform:skewX(-15deg);transition:left .5s ease;pointer-events:none;
}
.choice:hover .cshine{left:160%;}
.choice:hover{transform:translateY(-8px);}
.choice:hover .choice-label{color:var(--accent);}
.choice-rock:hover{background:rgba(255,94,94,0.08);border-color:rgba(255,94,94,0.6);box-shadow:0 8px 24px rgba(255,94,94,0.25);}
.choice-rock:hover .choice-label{color:var(--red) !important;}
.choice-scissors:hover{background:rgba(79,172,254,0.08);border-color:rgba(79,172,254,0.6);box-shadow:0 8px 24px rgba(79,172,254,0.25);}
.choice-scissors:hover .choice-label{color:var(--blue) !important;}
.choice-paper:hover{background:rgba(74,255,187,0.08);border-color:rgba(74,255,187,0.6);box-shadow:0 8px 24px rgba(74,255,187,0.2);}
.choice-paper:hover .choice-label{color:var(--green) !important;}
.choice img{width:74px;height:74px;object-fit:contain;filter:drop-shadow(0 4px 14px rgba(0,0,0,.6));transition:transform .28s ease;}
.choice:hover img{transform:scale(1.12) translateY(-3px);}
.choice-label{display:block;margin-top:8px;font-weight:700;font-size:.66rem;text-transform:uppercase;color:rgba(230,237,243,.45);letter-spacing:1.5px;transition:color .2s;}
.choice.disabled{opacity:.35;pointer-events:none;}

/* ── WAITING SCREEN ── */
.waiting-box{text-align:center;padding:16px 0;}
.waiting-icon{font-size:2rem;margin-bottom:10px;animation:pulse 1.2s ease-in-out infinite;}
@keyframes pulse{0%,100%{transform:scale(1);}50%{transform:scale(1.1);}}
.waiting-title{font-size:.85rem;font-weight:700;color:var(--green);margin-bottom:4px;}
.waiting-sub{font-size:.7rem;color:var(--muted);}

/* ── RESULT SCREEN ── */
#result-screen{text-align:center;display:none;padding:6px 20px 10px;}
#result-screen.show{display:block;}
#result-text{
    font-family:'Luckiest Guy',cursive;font-size:1.5rem;letter-spacing:3px;
    min-height:38px;margin:6px 0 10px;
    animation:popIn .4s ease;
}
@keyframes popIn{0%{transform:scale(.5);opacity:0;}70%{transform:scale(1.1);}100%{transform:scale(1);opacity:1;}}
.btn-continue{
    background:rgba(255,215,0,0.08) !important;
    border:1.5px solid rgba(255,215,0,0.45) !important;
    color:#ffd700 !important;
    padding:12px 32px;border-radius:24px;font-size:.82rem;
    cursor:pointer;font-weight:800;text-transform:uppercase;
    font-family:'Poppins',sans-serif;letter-spacing:1.5px;
    transition:all .22s;
    box-shadow:0 4px 15px rgba(255,215,0,0.15), inset 0 1px 0 rgba(255,255,255,0.05);
}
.btn-continue:hover{
    background:rgba(255,215,0,0.22) !important;
    border-color:#ffd700 !important;
    color:#ffffff !important;
    box-shadow:0 0 25px rgba(255,215,0,0.5), inset 0 1px 0 rgba(255,255,255,0.1);
    transform:scale(1.06) translateY(-2px);
}

/* ── MATCH OVER SCREEN ── */
#match-over{display:none;text-align:center;padding:10px 0;}
#match-over.show{display:block;}
.match-over-title{
    font-family:'Luckiest Guy',cursive;font-size:2.2rem;letter-spacing:4px;
    animation:popIn .5s ease;
}
.match-over-sub{font-size:.78rem;color:var(--muted);letter-spacing:1px;margin:6px 0 14px;}
.match-over-btns{display:flex;justify-content:center;gap:10px;}
.btn-rematch{
    background:linear-gradient(135deg,#2affa0,var(--green),#00d97e);color:#051a0f;border:none;
    padding:11px 26px;border-radius:24px;font-size:.78rem;
    cursor:pointer;font-weight:800;text-transform:uppercase;
    font-family:'Poppins',sans-serif;letter-spacing:1px;
    transition:all .22s;box-shadow:0 4px 18px rgba(74,255,187,.35), inset 0 1px 0 rgba(255,255,255,.2);
}
.btn-rematch:hover{transform:scale(1.06) translateY(-2px);box-shadow:0 8px 28px rgba(74,255,187,.6);}
.btn-menu{
    background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.12);color:var(--muted);
    padding:11px 22px;border-radius:24px;font-size:.78rem;
    cursor:pointer;font-weight:700;text-transform:uppercase;
    font-family:'Poppins',sans-serif;letter-spacing:1px;
    transition:all .22s;
}
.btn-menu:hover{border-color:rgba(255,255,255,.3);color:var(--text);background:rgba(255,255,255,.09);}

/* ── GAME MAIN WRAPPER ── */
#game-main{padding:0;}
#connect-screen{
    text-align:center;padding:50px 20px;
    display:flex;flex-direction:column;align-items:center;gap:14px;
}
.connect-spinner{
    width:48px;height:48px;border-radius:50%;
    border:4px solid rgba(79,172,254,.2);
    border-top-color:var(--blue);
    animation:spin .7s linear infinite;
}
@keyframes spin{to{transform:rotate(360deg);}}
.connect-title{font-size:.85rem;font-weight:700;color:var(--blue);}
.connect-sub{font-size:.7rem;color:var(--muted);}

/* ── HP DAMAGE FLASH ── */
@keyframes hpFlash{0%{opacity:1;}25%{opacity:.2;filter:brightness(1.5);background:rgba(255,94,94,.5);}70%{opacity:.8;}100%{opacity:1;filter:brightness(1);}}
.hp-damaged{animation:hpFlash .75s cubic-bezier(.4,0,.2,1);}

/* ── HP HEAL FLASH ── */
@keyframes hpHealFlash{0%{filter:brightness(1);}30%{filter:brightness(2) drop-shadow(0 0 12px #4affbb);}65%{filter:brightness(1.3) drop-shadow(0 0 4px #4affbb);}100%{filter:brightness(1);}}
.hp-healed{animation:hpHealFlash 1s cubic-bezier(.4,0,.2,1);}


/* ── OPPONENT CHOSE INDICATOR ── */
.opp-badge{
    display:none;font-size:.65rem;letter-spacing:.1em;font-weight:700;
    color:var(--orange);background:rgba(255,169,77,.1);
    border:1px solid rgba(255,169,77,.35);border-radius:20px;padding:2px 10px;
    margin-top:4px;
}
.opp-badge.show{display:inline-block;}

/* ── DAMAGE FLOAT NUMBER ── */
.damage-float{
    position:absolute;
    font-family:'Luckiest Guy',cursive;
    font-size:2rem;
    font-weight:900;
    pointer-events:none;
    z-index:200;
    text-shadow:0 2px 12px rgba(0,0,0,.9), 0 0 20px currentColor;
    animation:dmgFloat 1.4s cubic-bezier(.22,.61,.36,1) forwards;
}
.damage-float.dmg-p1{color:#ff3c3c;left:8%;}
.damage-float.dmg-p2{color:#ff3c3c;right:8%;}
.damage-float.dmg-heal{color:var(--green);}
@keyframes dmgFloat{
    0%  {transform:translateY(0)    scale(1.4);opacity:1;}
    15% {transform:translateY(-18px) scale(1.7);opacity:1;}
    65% {transform:translateY(-55px) scale(1.1);opacity:.95;}
    100%{transform:translateY(-90px) scale(.85);opacity:0;}
}

/* ── HEAL FLOAT NUMBER ── */
.heal-float{
    position:absolute;
    font-family:'Luckiest Guy',cursive;
    font-size:2rem;
    font-weight:900;
    color:#4affbb;
    pointer-events:none;
    z-index:200;
    text-shadow:0 2px 12px rgba(0,0,0,.9), 0 0 24px #4affbb;
    animation:healFloat 1.4s cubic-bezier(.22,.61,.36,1) forwards;
}
@keyframes healFloat{
    0%  {transform:translateY(0)    scale(1.4);opacity:1;}
    15% {transform:translateY(-18px) scale(1.7);opacity:1;}
    65% {transform:translateY(-55px) scale(1.1);opacity:.95;}
    100%{transform:translateY(-90px) scale(.85);opacity:0;}
}

/* ── HEAL ORB PARTICLES ── */
.heal-orb{
    position:absolute;
    width:8px;height:8px;
    border-radius:50%;
    background:radial-gradient(circle, #4affbb, #00c97a);
    pointer-events:none;
    z-index:199;
    box-shadow:0 0 6px #4affbb;
    animation:orbFloat .9s ease-out forwards;
}
@keyframes orbFloat{
    0%  {transform:translate(0,0) scale(1);  opacity:1;}
    100%{transform:translate(var(--tx,0px), var(--ty,-40px)) scale(0);opacity:0;}
}

/* ── HAND REVEAL ANIMATIONS ── */
@keyframes slideInLeft{
    0%  {transform:translateX(-90px) scale(.6);opacity:0;}
    60% {transform:translateX(10px)  scale(1.08);opacity:1;}
    100%{transform:translateX(0)     scale(1);opacity:1;}
}
@keyframes slideInRight{
    0%  {transform:scaleX(-1) translateX(-90px) scale(.6);opacity:0;}
    60% {transform:scaleX(-1) translateX(10px)  scale(1.08);opacity:1;}
    100%{transform:scaleX(-1) translateX(0)     scale(1);opacity:1;}
}
.hand-reveal-p1{animation:slideInLeft  .55s cubic-bezier(.175,.885,.32,1.275) forwards!important;}
.hand-reveal-p2{animation:slideInRight .55s cubic-bezier(.175,.885,.32,1.275) forwards!important;}

/* ── CLASH IMPACT ── */
@keyframes clashPulse{
    0%  {transform:scale(1);}
    25% {transform:scale(1.25);filter:drop-shadow(0 0 18px var(--accent)) brightness(1.8);}
    50% {transform:scale(.95);}
    100%{transform:scale(1);}
}
@keyframes clashPulseP2{
    0%  {transform:scaleX(-1) scale(1);}
    25% {transform:scaleX(-1) scale(1.25);filter:drop-shadow(0 0 18px var(--accent)) brightness(1.8);}
    50% {transform:scaleX(-1) scale(.95);}
    100%{transform:scaleX(-1) scale(1);}
}
.hand-clash{animation:clashPulse .45s ease!important;}
.hand-clash-p2{animation:clashPulseP2 .45s ease!important;transform-origin:center;}

/* ── WIN GLOW ── */
@keyframes winGlow{
    0%,100%{filter:drop-shadow(0 0 8px var(--green));}
    50%{filter:drop-shadow(0 0 24px var(--green)) brightness(1.3);}
}
.hand-win{animation:winGlow 1s ease-in-out 2!important;}

/* ── LOSE GLOW (merah) ── */
@keyframes loseGlow{
    0%,100%{filter:drop-shadow(0 0 8px var(--red)) brightness(.7);}
    50%{filter:drop-shadow(0 0 20px var(--red)) brightness(.5);}
}
@keyframes loseGlowP2{
    0%,100%{transform:scaleX(-1);filter:drop-shadow(0 0 8px var(--red)) brightness(.7);}
    50%{transform:scaleX(-1);filter:drop-shadow(0 0 20px var(--red)) brightness(.5);}
}
.hand-lose{animation:loseGlow 1s ease-in-out 2!important;}
.hand-lose-p2{animation:loseGlowP2 1s ease-in-out 2!important;}

/* ── DRAW GLOW (kuning, 3x loop = lebih lama) ── */
@keyframes drawGlowP1{
    0%,100%{filter:drop-shadow(0 0 12px var(--accent)) brightness(1);}
    50%{filter:drop-shadow(0 0 28px var(--accent)) brightness(1.35);}
}
@keyframes drawGlowP2{
    0%,100%{transform:scaleX(-1);filter:drop-shadow(0 0 12px var(--accent)) brightness(1);}
    50%{transform:scaleX(-1);filter:drop-shadow(0 0 28px var(--accent)) brightness(1.35);}
}
.hand-draw-p1{animation:drawGlowP1 1s ease-in-out 3!important;}
.hand-draw-p2{animation:drawGlowP2 1s ease-in-out 3!important;}

/* ── WAITING WEAPON DISPLAY ── */
.waiting-weapon{
    display:flex;flex-direction:column;align-items:center;gap:6px;
    margin-top:10px;
}
.waiting-weapon img{
    width:82px;height:82px;object-fit:contain;
    filter:drop-shadow(0 8px 16px rgba(0,0,0,.5));
    animation:weaponFloat 2s ease-in-out infinite;
}
.waiting-weapon-label{
    font-size:.68rem;font-weight:700;letter-spacing:2px;
    text-transform:uppercase;color:var(--accent);
}
.waiting-weapon-tag{
    font-size:.6rem;color:var(--muted);letter-spacing:1px;
}
@keyframes weaponFloat{
    0%,100%{transform:translateY(0) rotate(-3deg);}
    50%{transform:translateY(-10px) rotate(3deg);}
}

/* ── CHOICE SELECTED ANIMATION ── */
@keyframes choiceSelect{
    0%  {transform:translateY(0) scale(1);}
    20% {transform:translateY(-18px) scale(1.25);filter:drop-shadow(0 0 20px var(--accent));}
    40% {transform:translateY(-12px) scale(1.15);filter:drop-shadow(0 0 30px var(--accent)) brightness(1.5);}
    60% {transform:translateY(-16px) scale(1.2);}
    80% {transform:translateY(-10px) scale(1.1);}
    100%{transform:translateY(-14px) scale(1.18);filter:drop-shadow(0 0 22px var(--accent)) brightness(1.3);}
}
@keyframes choiceSelectBorder{
    0%,100%{border-color:var(--accent);box-shadow:0 0 12px rgba(255,215,0,.4),inset 0 0 8px rgba(255,215,0,.1);}
    50%{border-color:#fff;box-shadow:0 0 28px rgba(255,215,0,.8),inset 0 0 16px rgba(255,215,0,.2);}
}
.choice.selected{
    animation:choiceSelectBorder 1s ease-in-out infinite!important;
    background:rgba(255,215,0,0.12)!important;
    border-color:var(--accent)!important;
    pointer-events:none;
}
.choice.selected img{
    animation:choiceSelect 1s ease-in-out infinite!important;
    transform-origin:center bottom;
}
.choice.selected .choice-label{color:var(--accent)!important;}

/* ── FIGHT REVEAL OVERLAY ── */
#fight-overlay{
    position:fixed;inset:0;z-index:999;
    display:none;flex-direction:column;align-items:center;justify-content:center;
    background:radial-gradient(ellipse at center,rgba(13,17,23,.95) 0%,rgba(0,0,0,.98) 100%);
    backdrop-filter:blur(4px);
}
#fight-overlay.show{display:flex;}

/* ── FIGHT BANNER ── */
@keyframes fightBannerIn{
    0%{transform:scale(3) rotate(-5deg);opacity:0;}
    40%{transform:scale(0.9) rotate(1deg);opacity:1;}
    60%{transform:scale(1.05) rotate(-1deg);}
    100%{transform:scale(1) rotate(0);}
}
@keyframes fightBannerShake{
    0%,100%{transform:scale(1) rotate(0);}
    25%{transform:scale(1.03) rotate(-1.5deg);}
    75%{transform:scale(1.03) rotate(1.5deg);}
}
#fight-banner{
    font-family:'Luckiest Guy',cursive;
    font-size:clamp(2.8rem,10vw,5rem);
    color:var(--accent);
    letter-spacing:12px;
    text-shadow:0 0 40px rgba(255,215,0,.8),0 0 80px rgba(255,215,0,.4),4px 4px 0 #8a6a00;
    margin-bottom:30px;
    animation:fightBannerIn .6s cubic-bezier(.175,.885,.32,1.275) forwards;
}
#fight-banner.shake{animation:fightBannerShake .3s ease-in-out infinite;}

/* ── FIGHT ARENA (weapon reveal area) ── */
.fight-arena{
    display:flex;align-items:center;justify-content:center;
    gap:20px;width:100%;max-width:480px;
    padding:0 20px;
}
.fight-player-col{display:flex;flex-direction:column;align-items:center;gap:10px;flex:1;}
.fight-player-name{
    font-size:.72rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;
}
.fight-player-name.p1{color:var(--p1-color);}
.fight-player-name.p2{color:var(--p2-color);}

@keyframes fightWeaponRevealLeft{
    0%  {transform:translateX(-200px) rotate(-25deg) scale(.3);opacity:0;}
    50% {transform:translateX(20px) rotate(5deg) scale(1.2);opacity:1;}
    70% {transform:translateX(-8px) rotate(-2deg) scale(1.05);}
    100%{transform:translateX(0) rotate(0) scale(1);opacity:1;}
}
@keyframes fightWeaponRevealRight{
    0%  {transform:translateX(200px) rotate(25deg) scale(.3) scaleX(-1);opacity:0;}
    50% {transform:translateX(-20px) rotate(-5deg) scale(1.2) scaleX(-1);opacity:1;}
    70% {transform:translateX(8px) rotate(2deg) scale(1.05) scaleX(-1);}
    100%{transform:translateX(0) rotate(0) scale(1) scaleX(-1);opacity:1;}
}
@keyframes fightWeaponWin{
    0%,100%{filter:drop-shadow(0 0 16px var(--green)) brightness(1.2);}
    50%{filter:drop-shadow(0 0 36px var(--green)) brightness(1.6);}
}
@keyframes fightWeaponWinP2{
    0%,100%{transform:scaleX(-1);filter:drop-shadow(0 0 16px var(--green)) brightness(1.2);}
    50%{transform:scaleX(-1);filter:drop-shadow(0 0 36px var(--green)) brightness(1.6);}
}
@keyframes fightWeaponLose{
    0%,100%{filter:drop-shadow(0 0 8px var(--red)) brightness(.7);}
    50%{filter:drop-shadow(0 0 20px var(--red)) brightness(.5);}
}
@keyframes fightWeaponLoseP2{
    0%,100%{transform:scaleX(-1);filter:drop-shadow(0 0 8px var(--red)) brightness(.7);}
    50%{transform:scaleX(-1);filter:drop-shadow(0 0 20px var(--red)) brightness(.5);}
}
@keyframes fightWeaponDraw{
    0%,100%{filter:drop-shadow(0 0 12px var(--accent)) brightness(1);}
    50%{filter:drop-shadow(0 0 24px var(--accent)) brightness(1.3);}
}
@keyframes fightWeaponDrawP2{
    0%,100%{transform:scaleX(-1);filter:drop-shadow(0 0 12px var(--accent)) brightness(1);}
    50%{transform:scaleX(-1);filter:drop-shadow(0 0 24px var(--accent)) brightness(1.3);}
}
.fight-weapon{
    width:110px;height:110px;object-fit:contain;
    opacity:0;
}
.fight-weapon.p2{
    transform:scaleX(-1);
}
.fight-weapon.animating-p1{
    animation:fightWeaponRevealLeft .7s cubic-bezier(.175,.885,.32,1.275) forwards;
}
.fight-weapon.animating-p2{
    animation:fightWeaponRevealRight .7s cubic-bezier(.175,.885,.32,1.275) forwards;
}
.fight-weapon.revealed{opacity:1!important;}
.fight-weapon.revealed.win{
    animation:fightWeaponWin 1s ease-in-out infinite!important;
    opacity:1!important;
}
.fight-weapon.p2.revealed.win{
    animation:fightWeaponWinP2 1s ease-in-out infinite!important;
    opacity:1!important;
}
.fight-weapon.revealed.lose{
    animation:fightWeaponLose 1s ease-in-out infinite!important;
    opacity:1!important;
}
.fight-weapon.p2.revealed.lose{
    animation:fightWeaponLoseP2 1s ease-in-out infinite!important;
    opacity:1!important;
}
.fight-weapon.revealed.draw{
    animation:fightWeaponDraw 1s ease-in-out infinite!important;
    opacity:1!important;
}
.fight-weapon.p2.revealed.draw{
    animation:fightWeaponDrawP2 1s ease-in-out infinite!important;
    opacity:1!important;
}

/* ── VS CLASH ICON ── */
@keyframes vsClashPop{
    0%  {transform:scale(0) rotate(-180deg);opacity:0;}
    60% {transform:scale(1.3) rotate(10deg);opacity:1;}
    80% {transform:scale(.95) rotate(-5deg);}
    100%{transform:scale(1) rotate(0);}
}
#fight-vs{
    font-family:'Luckiest Guy',cursive;font-size:2.5rem;
    color:var(--red);text-shadow:0 0 20px rgba(255,94,94,.7),3px 3px 0 #800000;
    animation:vsClashPop .5s cubic-bezier(.175,.885,.32,1.275) 1.4s both;
    opacity:0;
}

/* ── FIGHT RESULT BANNER ── */
@keyframes resultBannerIn{
    0%{transform:translateY(60px) scale(.8);opacity:0;}
    60%{transform:translateY(-10px) scale(1.05);}
    100%{transform:translateY(0) scale(1);opacity:1;}
}
#fight-result-text{
    font-family:'Luckiest Guy',cursive;
    font-size:clamp(1.4rem,5vw,2.4rem);
    letter-spacing:4px;text-align:center;
    margin-top:22px;
    min-height:60px;
    opacity:0;transition:opacity .3s;
}
#fight-result-text.show{
    opacity:1;
    animation:resultBannerIn .5s cubic-bezier(.175,.885,.32,1.275) forwards;
}
#fight-winner-detail{
    font-size:.8rem;font-weight:600;color:var(--muted);
    letter-spacing:1.5px;text-align:center;margin-top:8px;
    opacity:0;transition:opacity .5s .3s;
}
#fight-winner-detail.show{opacity:1;}

/* ── CONFETTI PARTICLES ── */
@keyframes confettiFall{
    0%{transform:translateY(-20px) rotate(0deg);opacity:1;}
    100%{transform:translateY(300px) rotate(720deg);opacity:0;}
}
.confetti-piece{
    position:absolute;width:8px;height:8px;
    border-radius:2px;
    animation:confettiFall 1.5s ease-in forwards;
    pointer-events:none;
}

/* ── WEAPON LABEL IN FIGHT ── */
.fight-weapon-label{
    font-size:.65rem;font-weight:700;letter-spacing:2px;
    text-transform:uppercase;margin-top:4px;
    opacity:0;transition:opacity .4s .5s;
}
.fight-weapon-label.show{opacity:1;}
.fight-player-col.p1-col .fight-weapon-label{color:var(--p1-color);}
.fight-player-col.p2-col .fight-weapon-label{color:var(--p2-color);}

/* ══════════════════════════════════════════════════════════
   SPELL / ABILITY CARD SYSTEM
══════════════════════════════════════════════════════════ */

/* ── CARD PICK OVERLAY ── */
#card-pick-overlay{
    position:fixed;inset:0;z-index:998;
    display:none;flex-direction:column;align-items:center;justify-content:center;
    background:
        radial-gradient(ellipse at 25% 20%, rgba(79,172,254,.09) 0%, transparent 45%),
        radial-gradient(ellipse at 75% 80%, rgba(240,147,251,.09) 0%, transparent 45%),
        radial-gradient(ellipse at 50% 50%, rgba(255,215,0,.03) 0%, transparent 70%),
        rgba(3,5,14,.97);
    backdrop-filter:blur(14px);
    padding:20px;
    overflow:hidden;
}
/* Animated grid lines background */
#card-pick-overlay::before{
    content:'';position:absolute;inset:0;z-index:0;pointer-events:none;
    background-image:
        linear-gradient(rgba(79,172,254,.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(79,172,254,.04) 1px, transparent 1px);
    background-size:48px 48px;
    animation:gridMove 18s linear infinite;
}
@keyframes gridMove{0%{background-position:0 0;}100%{background-position:48px 48px;}}
#card-pick-overlay.show{display:flex;}

/* ── ROUND HEADER inside overlay ── */
.cpo-header{
    display:flex;align-items:center;gap:12px;margin-bottom:14px;
    position:relative;z-index:2;
    animation:fadeSlideDown .45s cubic-bezier(.175,.885,.32,1.275) both;
}
@keyframes fadeSlideDown{
    0%{transform:translateY(-20px);opacity:0;}
    100%{transform:translateY(0);opacity:1;}
}

/* Game number badge */
.card-pick-game-badge{
    font-size:.6rem;font-weight:800;letter-spacing:3px;text-transform:uppercase;
    color:var(--orange);
    background:linear-gradient(135deg,rgba(255,169,77,.15),rgba(255,169,77,.05));
    border:1px solid rgba(255,169,77,.4);border-radius:30px;
    padding:5px 16px;
    box-shadow:0 0 14px rgba(255,169,77,.12),inset 0 1px 0 rgba(255,255,255,.05);
    animation:popIn .5s .1s cubic-bezier(.175,.885,.32,1.275) both;
    display:flex;align-items:center;gap:6px;
    position:relative;z-index:2;
}
.card-pick-game-badge::before{
    content:'';width:6px;height:6px;border-radius:50%;
    background:var(--orange);box-shadow:0 0 8px var(--orange);
    animation:livepulse .9s ease-in-out infinite alternate;
}

.card-pick-title{
    font-family:'Luckiest Guy',cursive;
    font-size:clamp(1.35rem,4.5vw,1.85rem);
    color:transparent;
    background:linear-gradient(135deg,#fff8d6 0%,var(--accent) 40%,#ffb300 100%);
    -webkit-background-clip:text;background-clip:text;
    letter-spacing:6px;
    filter:drop-shadow(0 0 18px rgba(255,215,0,.5));
    margin-bottom:5px;
    animation:titleGlow 2.5s ease-in-out infinite, fadeSlideDown .4s ease both;
    position:relative;z-index:2;
}
@keyframes titleGlow{
    0%,100%{filter:drop-shadow(0 0 18px rgba(255,215,0,.45));}
    50%{filter:drop-shadow(0 0 36px rgba(255,215,0,.9)) drop-shadow(0 0 60px rgba(255,215,0,.3));}
}

/* Divider line below title */
.cpo-divider{
    width:200px;height:1px;margin-bottom:18px;
    background:linear-gradient(90deg,transparent,rgba(255,215,0,.3),rgba(79,172,254,.25),transparent);
    animation:fadeSlideDown .4s .12s ease both;
    position:relative;z-index:2;
}

.card-pick-sub{
    font-size:.7rem;color:rgba(230,237,243,.5);letter-spacing:1.2px;
    margin-bottom:20px;text-align:center;
    animation:fadeSlideDown .4s .08s ease both;
    display:flex;align-items:center;gap:8px;justify-content:center;flex-wrap:wrap;
    position:relative;z-index:2;
}
.card-pick-sub span{
    color:var(--orange);font-weight:800;
    background:rgba(255,169,77,.1);padding:1px 8px;border-radius:10px;
    border:1px solid rgba(255,169,77,.25);
}

.card-pick-row{
    display:flex;gap:14px;justify-content:center;
    flex-wrap:nowrap;margin-bottom:18px;
    max-width:580px;width:100%;
    perspective:1200px;
    animation:fadeSlideDown .4s .15s ease both;
    padding-top:28px;
    overflow:visible;
    position:relative;z-index:1;
}

/* ── INDIVIDUAL SPELL CARD ── */
.spell-card{
    width:150px;flex-shrink:0;
    border-radius:20px;padding:18px 13px 16px;
    cursor:pointer;position:relative;overflow:hidden;
    border:2px solid transparent;
    display:flex;flex-direction:column;align-items:center;gap:8px;
    transition:all .32s cubic-bezier(.175,.885,.32,1.275);
    background:#0a1018;
    animation:cardDeal .6s cubic-bezier(.175,.885,.32,1.275) both;
    transform-style:preserve-3d;
    box-shadow:0 8px 32px rgba(0,0,0,.7);
    z-index:1;
}
.spell-card:nth-child(1){animation-delay:.1s;}
.spell-card:nth-child(2){animation-delay:.22s;}
.spell-card:nth-child(3){animation-delay:.34s;}
@keyframes cardDeal{
    0%{transform:translateY(100px) rotateX(45deg) rotateY(-8deg) scale(.6);opacity:0;}
    55%{transform:translateY(-12px) rotateX(-4deg) rotateY(1deg) scale(1.05);}
    100%{transform:translateY(0) rotateX(0) rotateY(0) scale(1);opacity:1;}
}

/* Rarity backgrounds */
.spell-card.common{
    border-color:rgba(180,180,180,.35);
    background:linear-gradient(160deg,#1a2030 0%,#111826 60%,#0d1420 100%);
    box-shadow:0 6px 24px rgba(0,0,0,.6),inset 0 1px 0 rgba(255,255,255,.04);
}
.spell-card.rare{
    border-color:rgba(79,172,254,.4);
    background:linear-gradient(160deg,#0b1928 0%,#08111e 60%,#050d18 100%);
    box-shadow:0 6px 24px rgba(0,0,0,.6),0 0 20px rgba(79,172,254,.08),inset 0 1px 0 rgba(79,172,254,.08);
}
.spell-card.epic{
    border-color:rgba(168,85,247,.45);
    background:linear-gradient(160deg,#180c2e 0%,#0f0920 60%,#0b0718 100%);
    box-shadow:0 6px 24px rgba(0,0,0,.6),0 0 24px rgba(168,85,247,.1),inset 0 1px 0 rgba(168,85,247,.08);
}
.spell-card.legend{
    border-color:rgba(255,215,0,.6);
    background:linear-gradient(160deg,#1e1500 0%,#140e00 60%,#0c0900 100%);
    box-shadow:0 6px 28px rgba(0,0,0,.65),0 0 30px rgba(255,215,0,.12),inset 0 1px 0 rgba(255,215,0,.1);
}

/* Hover states */
.spell-card:hover{transform:translateY(-16px) scale(1.07) rotateX(3deg);}
.spell-card.common:hover{
    border-color:rgba(220,220,220,.9);
    box-shadow:0 24px 56px rgba(0,0,0,.85),0 0 32px rgba(180,180,180,.18);
}
.spell-card.rare:hover{
    border-color:var(--blue);
    box-shadow:0 24px 56px rgba(0,0,0,.85),0 0 44px rgba(79,172,254,.35),inset 0 0 24px rgba(79,172,254,.06);
}
.spell-card.epic:hover{
    border-color:#a855f7;
    box-shadow:0 24px 56px rgba(0,0,0,.85),0 0 44px rgba(168,85,247,.4),inset 0 0 24px rgba(168,85,247,.06);
}
.spell-card.legend:hover{
    border-color:var(--accent);
    box-shadow:0 24px 56px rgba(0,0,0,.85),0 0 56px rgba(255,215,0,.5),inset 0 0 28px rgba(255,215,0,.09);
}

/* Selected states */
.spell-card.selected-card{
    transform:translateY(-20px) scale(1.11) rotateX(2deg)!important;
    pointer-events:none;
    margin: 0 8px;
}
.spell-card.common.selected-card{
    border-color:#ddd!important;
    box-shadow:0 0 46px rgba(200,200,200,.6),0 20px 50px rgba(0,0,0,.7)!important;
    background:linear-gradient(160deg,#242e42,#1a2234)!important;
}
.spell-card.rare.selected-card{
    border-color:var(--blue)!important;
    box-shadow:0 0 52px rgba(79,172,254,.75),0 20px 50px rgba(0,0,0,.7)!important;
    background:linear-gradient(160deg,#102038,#0c1a2c)!important;
}
.spell-card.epic.selected-card{
    border-color:#a855f7!important;
    box-shadow:0 0 52px rgba(168,85,247,.8),0 20px 50px rgba(0,0,0,.7)!important;
    background:linear-gradient(160deg,#200e3a,#160b2c)!important;
}
.spell-card.legend.selected-card{
    border-color:var(--accent)!important;
    box-shadow:0 0 65px rgba(255,215,0,.95),0 20px 50px rgba(0,0,0,.7)!important;
    background:linear-gradient(160deg,#231a00,#160f00)!important;
}
.spell-card.dimmed{opacity:.2;transform:scale(.86) translateY(6px)!important;pointer-events:none;filter:grayscale(.7) blur(.5px);transition:all .25s;}

/* Rarity badge */
.card-rarity{
    font-size:.46rem;font-weight:800;letter-spacing:3px;
    text-transform:uppercase;padding:3px 10px;border-radius:30px;
    margin-bottom:1px;
    display:flex;align-items:center;gap:4px;
}
.card-rarity::before{content:'';width:5px;height:5px;border-radius:50%;flex-shrink:0;}
.common  .card-rarity{background:rgba(180,180,180,.1);color:#c0c0c0;border:1px solid rgba(180,180,180,.25);}
.common  .card-rarity::before{background:#c0c0c0;box-shadow:0 0 5px #c0c0c0;}
.rare    .card-rarity{background:rgba(79,172,254,.1);color:var(--blue);border:1px solid rgba(79,172,254,.35);}
.rare    .card-rarity::before{background:var(--blue);box-shadow:0 0 6px var(--blue);}
.epic    .card-rarity{background:rgba(168,85,247,.1);color:#c084fc;border:1px solid rgba(168,85,247,.35);}
.epic    .card-rarity::before{background:#c084fc;box-shadow:0 0 6px #c084fc;}
.legend  .card-rarity{background:rgba(255,215,0,.1);color:var(--accent);border:1px solid rgba(255,215,0,.45);}
.legend  .card-rarity::before{background:var(--accent);box-shadow:0 0 7px var(--accent);animation:starPulse 1.2s ease-in-out infinite alternate;}
@keyframes starPulse{from{opacity:.6;transform:scale(.8);}to{opacity:1;transform:scale(1.2);}}

/* Counter badge */
.card-counter-badge{
    font-size:.42rem;font-weight:800;letter-spacing:1.5px;
    text-transform:uppercase;padding:1px 7px;border-radius:10px;
    background:rgba(255,94,94,.15);color:var(--red);
    border:1px solid rgba(255,94,94,.35);
    margin-bottom:1px;
}

/* Card icon */
.card-icon{
    font-size:2.6rem;
    filter:drop-shadow(0 4px 14px rgba(0,0,0,.7));
    margin:6px 0 2px;
    transition:transform .3s ease;
    line-height:1;
}
.spell-card:hover .card-icon{transform:scale(1.18) translateY(-4px);}
.legend .card-icon{animation:legendFloat 2.5s ease-in-out infinite;}
@keyframes legendFloat{
    0%,100%{filter:drop-shadow(0 4px 14px rgba(255,215,0,.4)) brightness(1);}
    50%{filter:drop-shadow(0 10px 28px rgba(255,215,0,.85)) brightness(1.25);}
}
.epic .card-icon{animation:epicFloat 3s ease-in-out infinite;}
@keyframes epicFloat{
    0%,100%{filter:drop-shadow(0 4px 14px rgba(168,85,247,.4));}
    50%{filter:drop-shadow(0 8px 22px rgba(168,85,247,.8));}
}
.rare .card-icon{animation:rareFloat 3.5s ease-in-out infinite;}
@keyframes rareFloat{
    0%,100%{filter:drop-shadow(0 4px 12px rgba(79,172,254,.3));}
    50%{filter:drop-shadow(0 6px 18px rgba(79,172,254,.65));}
}

/* Card name */
.card-name{
    font-family:'Luckiest Guy',cursive;
    font-size:.82rem;letter-spacing:1.5px;text-align:center;
    line-height:1.25;min-height:30px;display:flex;align-items:center;justify-content:center;
}
.common .card-name{color:#e5e5e5;}
.rare   .card-name{color:var(--blue);}
.epic   .card-name{color:#c084fc;}
.legend .card-name{
    color:transparent;
    background:linear-gradient(135deg,#ffe066 0%,var(--accent) 50%,#ffa500 100%);
    -webkit-background-clip:text;background-clip:text;
}

/* Card description */
.card-desc{
    font-size:.56rem;color:rgba(230,237,243,.5);text-align:center;
    line-height:1.55;flex:1;padding:0 2px;
}

/* Card type label */
.card-timing-label{
    font-size:.42rem;font-weight:800;letter-spacing:1.2px;text-transform:uppercase;
    padding:3px 9px;border-radius:10px;
    border:1px solid;display:flex;align-items:center;gap:4px;
    margin-top:2px;
}
.card-timing-label.next-round{
    background:rgba(255,169,77,.08);color:var(--orange);border-color:rgba(255,169,77,.28);
}
.card-timing-label.instant{
    background:rgba(74,255,187,.07);color:var(--green);border-color:rgba(74,255,187,.25);
}
.card-timing-label.instant::before{content:'⚡';font-size:.5rem;}

/* Card divider */
.card-divider{
    width:90%;height:1px;margin:5px 0 3px;
    background:linear-gradient(90deg,transparent,rgba(255,255,255,.1),transparent);
}

/* Legend shimmer */
.spell-card.legend::before{
    content:'';position:absolute;inset:0;
    background:linear-gradient(115deg,transparent 30%,rgba(255,215,0,.08) 50%,transparent 70%);
    background-size:300% 300%;
    animation:legendShimmer 2s linear infinite;
    pointer-events:none;border-radius:18px;z-index:0;
}
@keyframes legendShimmer{
    0%{background-position:200% 200%;}
    100%{background-position:-200% -200%;}
}

/* Epic shimmer */
.spell-card.epic::before{
    content:'';position:absolute;inset:0;
    background:linear-gradient(115deg,transparent 35%,rgba(168,85,247,.07) 50%,transparent 65%);
    background-size:300% 300%;
    animation:epicShimmer 2.5s linear infinite;
    pointer-events:none;border-radius:18px;z-index:0;
}
@keyframes epicShimmer{
    0%{background-position:200% 200%;}
    100%{background-position:-200% -200%;}
}

/* Legend corner star */
.spell-card.legend::after{
    content:'✦';position:absolute;top:7px;right:9px;
    font-size:.5rem;color:rgba(255,215,0,.5);
    animation:starSpin 5s linear infinite;
    text-shadow:0 0 8px rgba(255,215,0,.8);
    z-index:1;
}
@keyframes starSpin{0%{transform:rotate(0) scale(1);}50%{transform:rotate(180deg) scale(1.3);}100%{transform:rotate(360deg) scale(1);}}

/* Skip card button */
.btn-skip-card{
    background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);
    color:rgba(230,237,243,.35);font-size:.64rem;font-weight:600;
    padding:9px 22px;border-radius:22px;cursor:pointer;
    letter-spacing:1px;text-transform:uppercase;
    transition:all .22s;
}
.btn-skip-card:hover{background:rgba(255,255,255,.09);color:rgba(230,237,243,.75);border-color:rgba(255,255,255,.22);}

/* ── CARD PICK WAITING STATE ── */
#card-pick-waiting{
    display:none;flex-direction:column;align-items:center;justify-content:center;
    gap:16px;padding:34px 24px;text-align:center;
    animation:popIn .4s ease;
    position:relative;z-index:2;
}
#card-pick-waiting.show{display:flex;}
.cpw-spinner{
    width:52px;height:52px;border-radius:50%;
    border:3px solid rgba(255,215,0,.12);
    border-top-color:var(--accent);
    animation:cpwSpin 1s linear infinite;
    box-shadow:0 0 20px rgba(255,215,0,.12);
}
@keyframes cpwSpin{0%{transform:rotate(0)}100%{transform:rotate(360deg)}}
.cpw-icon{font-size:2.8rem;animation:cpwBounce 1.6s ease-in-out infinite;}
@keyframes cpwBounce{
    0%,100%{transform:translateY(0) scale(1);}
    50%{transform:translateY(-10px) scale(1.08);}
}
.cpw-title{
    font-family:'Luckiest Guy',cursive;font-size:1.2rem;
    color:transparent;
    background:linear-gradient(135deg,#fff8d6,var(--accent));
    -webkit-background-clip:text;background-clip:text;
    letter-spacing:3px;
    filter:drop-shadow(0 0 16px rgba(255,215,0,.5));
}
.cpw-sub{font-size:.7rem;color:rgba(230,237,243,.45);line-height:1.7;max-width:300px;}
.cpw-cards-preview{
    display:flex;gap:8px;justify-content:center;margin-top:4px;flex-wrap:wrap;
}
.cpw-card-chip{
    display:flex;align-items:center;gap:6px;
    background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);
    border-radius:22px;padding:5px 14px;
    font-size:.68rem;font-weight:700;color:var(--text);
    box-shadow:0 2px 8px rgba(0,0,0,.3);
}
.cpw-card-chip.common{border-color:rgba(180,180,180,.35);color:#ccc;}
.cpw-card-chip.rare{border-color:rgba(79,172,254,.5);color:var(--blue);background:rgba(79,172,254,.06);}
.cpw-card-chip.epic{border-color:rgba(168,85,247,.5);color:#c084fc;background:rgba(168,85,247,.06);}
.cpw-card-chip.legend{border-color:rgba(255,215,0,.6);color:var(--accent);background:rgba(255,215,0,.06);}
.cpw-opp-status{
    font-size:.65rem;color:rgba(230,237,243,.5);display:flex;align-items:center;gap:8px;
    background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);
    border-radius:14px;padding:7px 16px;
}
.cpw-opp-dot{
    width:8px;height:8px;border-radius:50%;background:var(--orange);
    animation:cpwPulse 1.2s ease-in-out infinite;
    box-shadow:0 0 6px var(--orange);
}
.cpw-opp-dot.ready{background:var(--green);animation:none;box-shadow:0 0 6px var(--green);}
@keyframes cpwPulse{
    0%,100%{opacity:1;transform:scale(1);}
    50%{opacity:.35;transform:scale(.65);}
}

/* ── CARD PICK ACTIONS ROW ── */
.card-pick-actions{
    display:flex;gap:12px;align-items:center;margin-top:14px;
    flex-wrap:wrap;justify-content:center;
    animation:fadeSlideDown .4s .2s ease both;
    position:relative;z-index:2;
}

/* ── CARD TIMER BAR ── */
.card-timer-bar{
    width:100%;max-width:420px;
    height:7px;background:rgba(255,255,255,.05);
    border-radius:4px;margin-top:14px;overflow:hidden;
    border:1px solid rgba(255,255,255,.06);
    box-shadow:inset 0 1px 3px rgba(0,0,0,.4);
    position:relative;z-index:2;
}
.card-timer-fill{
    height:100%;width:100%;
    background:linear-gradient(90deg,var(--accent),var(--orange),#ffe066);
    border-radius:4px;
    transition:width linear;
    box-shadow:0 0 8px rgba(255,215,0,.35);
    position:relative;overflow:hidden;
}
.card-timer-fill::after{
    content:'';position:absolute;top:0;left:-50%;width:40%;height:100%;
    background:linear-gradient(90deg,transparent,rgba(255,255,255,.3),transparent);
    animation:timerShine 1.5s linear infinite;
}
@keyframes timerShine{0%{left:-50%;}100%{left:140%;}}
.card-timer-fill.urgent{
    background:linear-gradient(90deg,#cc2200,var(--red),#ff6b35)!important;
    box-shadow:0 0 12px rgba(255,94,94,.5)!important;
    animation:timerUrgentPulse .5s ease-in-out infinite alternate;
}
@keyframes timerUrgentPulse{
    from{box-shadow:0 0 8px rgba(255,94,94,.4);}
    to{box-shadow:0 0 20px rgba(255,94,94,.8);}
}

/* ── CARD CONFIRM BUTTON ── */
.btn-confirm-card{
    background:linear-gradient(135deg,#2affa0,var(--green),#00d97e);
    color:#061810;border:none;
    padding:12px 32px;border-radius:28px;font-size:.82rem;
    cursor:pointer;font-weight:800;text-transform:uppercase;
    font-family:'Poppins',sans-serif;letter-spacing:1.5px;
    transition:all .22s;
    box-shadow:0 4px 20px rgba(74,255,187,.3),0 1px 0 rgba(255,255,255,.15) inset;
    opacity:.35;pointer-events:none;
    display:flex;align-items:center;gap:9px;
}
.btn-confirm-card.ready{
    opacity:1;pointer-events:auto;
    animation:confirmPulse 1.2s ease-in-out infinite;
}
.btn-confirm-card.ready:hover{
    transform:scale(1.07) translateY(-2px);
    box-shadow:0 8px 32px rgba(74,255,187,.65);
}
@keyframes confirmPulse{
    0%,100%{box-shadow:0 4px 20px rgba(74,255,187,.3),inset 0 1px 0 rgba(255,255,255,.15);}
    50%{box-shadow:0 4px 30px rgba(74,255,187,.7),inset 0 1px 0 rgba(255,255,255,.25);}
}
.confirm-counter{
    background:rgba(0,0,0,.2);border-radius:14px;
    padding:2px 9px;font-size:.76rem;letter-spacing:0;
    border:1px solid rgba(255,255,255,.15);
}

/* Responsive mobile */
@media(max-width:420px){
    .card-pick-row{gap:8px;}
    .spell-card{width:112px;padding:12px 8px 12px;}
    .spell-card.selected-card{margin: 0 4px;}
    .card-icon{font-size:2rem;}
    .card-name{font-size:.72rem;}
    .card-desc{font-size:.53rem;}
    .card-pick-title{font-size:1.2rem;}
    .cpo-divider{width:140px;}
}

/* ── ACTIVE CARDS HAND (bottom of game) ── */
#card-hand{
    display:flex;gap:8px;justify-content:center;align-items:flex-end;
    margin-top:10px;padding:12px 20px 4px;
    min-height:52px;
    border-top:1px solid rgba(79,172,254,.12);
    position:relative;
    background:linear-gradient(180deg, rgba(79,172,254,.03) 0%, transparent 100%);
}
.card-hand-label{
    position:absolute;top:-9px;left:50%;transform:translateX(-50%);
    font-size:.5rem;letter-spacing:2px;color:rgba(79,172,254,.5);
    text-transform:uppercase;
    background:linear-gradient(135deg,#0c1022,#0a0e1c);
    padding:0 10px;
    white-space:nowrap;border-radius:4px;
    border:1px solid rgba(79,172,254,.15);
}

/* Mini card in hand */
.hand-card{
    width:58px;height:78px;border-radius:10px;
    cursor:pointer;border:1.5px solid transparent;
    display:flex;flex-direction:column;align-items:center;justify-content:center;
    gap:3px;position:relative;overflow:hidden;
    transition:all .28s cubic-bezier(.175,.885,.32,1.275);
    background:#0e1520;
    box-shadow:0 4px 12px rgba(0,0,0,.5);
}
.hand-card:hover{transform:translateY(-8px) scale(1.05);z-index:10;}
.hand-card.common{
    border-color:rgba(180,180,180,.45);
    background:linear-gradient(155deg,#1c2232,#121826);
}
.hand-card.rare{
    border-color:rgba(79,172,254,.5);
    background:linear-gradient(155deg,#0d1a2e,#08111e);
}
.hand-card.epic{
    border-color:rgba(168,85,247,.5);
    background:linear-gradient(155deg,#1a0d30,#0e0820);
}
.hand-card.legend{
    border-color:rgba(255,215,0,.6);
    background:linear-gradient(155deg,#1e1600,#100c00);
    animation:legendHandGlow 2s ease-in-out infinite;
}
@keyframes legendHandGlow{
    0%,100%{box-shadow:0 4px 12px rgba(0,0,0,.5),0 0 8px rgba(255,215,0,.2);}
    50%{box-shadow:0 4px 12px rgba(0,0,0,.5),0 0 18px rgba(255,215,0,.45);}
}

.hand-card.common:hover{box-shadow:0 12px 28px rgba(0,0,0,.7),0 0 15px rgba(180,180,180,.2);}
.hand-card.rare:hover{  box-shadow:0 12px 28px rgba(0,0,0,.7),0 0 20px rgba(79,172,254,.35);}
.hand-card.epic:hover{  box-shadow:0 12px 28px rgba(0,0,0,.7),0 0 20px rgba(168,85,247,.4);}
.hand-card.legend:hover{box-shadow:0 12px 28px rgba(0,0,0,.7),0 0 28px rgba(255,215,0,.55);}

.hand-card .hc-icon{font-size:1.4rem;line-height:1;}
.hand-card .hc-name{
    font-size:.4rem;font-weight:700;text-align:center;letter-spacing:.5px;
    line-height:1.2;padding:0 3px;
}
.hand-card.common .hc-name{color:#bbb;}
.hand-card.rare   .hc-name{color:var(--blue);}
.hand-card.epic   .hc-name{color:#c084fc;}
.hand-card.legend .hc-name{color:var(--accent);}

/* Counter indicator on hand card */
.hand-card .hc-counter{
    font-size:.35rem;font-weight:800;color:var(--red);
    background:rgba(255,94,94,.15);border:1px solid rgba(255,94,94,.3);
    border-radius:4px;padding:0px 4px;letter-spacing:.5px;
}

/* Empty hand slot */
.hand-card-empty{
    width:58px;height:78px;border-radius:10px;
    border:1.5px dashed rgba(79,172,254,.1);
    display:flex;align-items:center;justify-content:center;
    font-size:1.3rem;color:rgba(255,255,255,.04);
    background:rgba(79,172,254,.02);
}

/* Used card overlay */
.hand-card.used::after{
    content:'USED';position:absolute;inset:0;
    background:rgba(0,0,0,.72);border-radius:9px;
    display:flex;align-items:center;justify-content:center;
    font-size:.48rem;font-weight:800;color:rgba(255,255,255,.25);
    letter-spacing:1px;
}
.hand-card.used{opacity:.45;cursor:not-allowed;transform:none!important;}

/* ── CARD USE TOOLTIP / POPUP ── */
#card-use-popup{
    position:fixed;inset:0;z-index:997;
    display:none;align-items:center;justify-content:center;
    background:rgba(0,0,0,.6);backdrop-filter:blur(3px);
}
#card-use-popup.show{display:flex;}
.card-popup-box{
    background:var(--card);border:1px solid var(--border);
    border-radius:18px;padding:22px 24px;
    max-width:320px;width:90%;text-align:center;
    animation:popIn .3s ease;
}
.popup-card-icon{font-size:2.8rem;margin-bottom:8px;}
.popup-card-name{
    font-family:'Luckiest Guy',cursive;font-size:1.3rem;
    letter-spacing:3px;margin-bottom:6px;
}
.popup-card-desc{font-size:.75rem;color:var(--muted);line-height:1.5;margin-bottom:16px;}
.popup-rarity-tag{
    display:inline-block;font-size:.6rem;font-weight:700;
    letter-spacing:2px;text-transform:uppercase;
    padding:3px 10px;border-radius:20px;margin-bottom:14px;
}
.popup-btns{display:flex;gap:10px;justify-content:center;}
.btn-use-card{
    background:var(--green);color:#0a1410;border:none;
    padding:10px 22px;border-radius:20px;font-size:.78rem;
    cursor:pointer;font-weight:800;text-transform:uppercase;
    font-family:'Poppins',sans-serif;letter-spacing:1px;
    transition:all .2s;
}
.btn-use-card:hover{transform:scale(1.05);box-shadow:0 0 20px rgba(74,255,187,.4);}
.btn-cancel-card{
    background:var(--inner);border:1px solid var(--border);color:var(--muted);
    padding:10px 18px;border-radius:20px;font-size:.78rem;
    cursor:pointer;font-weight:700;text-transform:uppercase;
    font-family:'Poppins',sans-serif;letter-spacing:1px;transition:all .2s;
}
.btn-cancel-card:hover{border-color:rgba(255,255,255,.3);color:var(--text);}

/* ── CARD EFFECT NOTIFICATIONS ── */
/* ── CARD EFFECT NOTIFICATIONS — UPGRADED ── */
#card-effect-banner{
    position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
    z-index:1100;pointer-events:none;
    opacity:0;
    text-align:center;
    background:linear-gradient(160deg,rgba(4,8,20,.95) 0%,rgba(2,5,14,.98) 100%);
    border:1.5px solid rgba(255,255,255,.1);
    border-radius:24px;
    padding:20px 34px 18px;
    backdrop-filter:blur(20px) saturate(1.5);
    box-shadow:0 24px 70px rgba(0,0,0,.9),inset 0 1px 0 rgba(255,255,255,.07);
    min-width:230px;
    will-change:transform,opacity;
}
#card-effect-banner.show{
    animation:bannerReveal .28s cubic-bezier(.34,1.56,.64,1) forwards;
}
#card-effect-banner.banner-hide{
    animation:bannerHide .2s cubic-bezier(.55,0,1,.45) forwards;
}
@keyframes bannerReveal{
    0%  {transform:translate(-50%,-50%) scale(.4) translateY(24px) rotateX(20deg);opacity:0;filter:brightness(2);}
    50% {transform:translate(-50%,-50%) scale(1.06) translateY(-5px) rotateX(-3deg);opacity:1;filter:brightness(1.3);}
    100%{transform:translate(-50%,-50%) scale(1) translateY(0) rotateX(0);opacity:1;filter:brightness(1);}
}
@keyframes bannerHide{
    0%  {transform:translate(-50%,-50%) scale(1) translateY(0);opacity:1;filter:brightness(1);}
    40% {transform:translate(-50%,-50%) scale(1.04) translateY(-4px);opacity:.8;filter:brightness(1.4);}
    100%{transform:translate(-50%,-50%) scale(.6) translateY(-20px);opacity:0;filter:brightness(2);}
}
/* Rarity-specific border glow pulse */
#card-effect-banner.rarity-common { border-color:rgba(200,200,200,.5); box-shadow:0 24px 70px rgba(0,0,0,.9),0 0 28px rgba(180,180,180,.22),inset 0 1px 0 rgba(255,255,255,.07); }
#card-effect-banner.rarity-rare   { border-color:rgba(79,172,254,.65);  box-shadow:0 24px 70px rgba(0,0,0,.9),0 0 40px rgba(79,172,254,.4),0 0 80px rgba(79,172,254,.15),inset 0 1px 0 rgba(79,172,254,.14); animation:bannerReveal .28s cubic-bezier(.34,1.56,.64,1) forwards,rarePulse .9s .28s ease-in-out infinite; }
#card-effect-banner.rarity-epic   { border-color:rgba(168,85,247,.7);   box-shadow:0 24px 70px rgba(0,0,0,.9),0 0 46px rgba(168,85,247,.5),0 0 90px rgba(168,85,247,.2),inset 0 1px 0 rgba(168,85,247,.14);  animation:bannerReveal .28s cubic-bezier(.34,1.56,.64,1) forwards,epicPulse .8s .28s ease-in-out infinite; }
#card-effect-banner.rarity-legend { border-color:rgba(255,215,0,.8);    box-shadow:0 24px 70px rgba(0,0,0,.9),0 0 55px rgba(255,215,0,.6),0 0 100px rgba(255,215,0,.25),inset 0 1px 0 rgba(255,215,0,.18);     animation:bannerReveal .28s cubic-bezier(.34,1.56,.64,1) forwards,legendPulse .75s .28s ease-in-out infinite; }
@keyframes rarePulse  {0%,100%{box-shadow:0 24px 70px rgba(0,0,0,.9),0 0 30px rgba(79,172,254,.28);}  50%{box-shadow:0 24px 70px rgba(0,0,0,.9),0 0 60px rgba(79,172,254,.7),0 0 90px rgba(79,172,254,.25);}}
@keyframes epicPulse  {0%,100%{box-shadow:0 24px 70px rgba(0,0,0,.9),0 0 35px rgba(168,85,247,.35);}  50%{box-shadow:0 24px 70px rgba(0,0,0,.9),0 0 65px rgba(168,85,247,.75),0 0 100px rgba(168,85,247,.3);}}
@keyframes legendPulse{0%,100%{box-shadow:0 24px 70px rgba(0,0,0,.9),0 0 45px rgba(255,215,0,.45);}   50%{box-shadow:0 24px 70px rgba(0,0,0,.9),0 0 80px rgba(255,215,0,.85),0 0 120px rgba(255,215,0,.35);}}
.ceb-icon{font-size:3rem;display:block;animation:cebBounce .28s cubic-bezier(.34,1.56,.64,1);}
.ceb-text{
    font-family:'Luckiest Guy',cursive;
    font-size:clamp(.95rem,4vw,1.45rem);
    letter-spacing:4px;
    text-shadow:0 0 28px currentColor,0 0 60px currentColor;
    margin-top:7px;
    animation:cebSlideIn .25s .06s ease both;display:block;
}
.ceb-sub{font-size:.68rem;font-weight:600;color:rgba(255,255,255,.55);margin-top:5px;letter-spacing:.8px;display:block;animation:cebSlideIn .25s .11s ease both;}
.ceb-timing{font-size:.6rem;font-weight:700;margin-top:7px;padding:3px 10px;border-radius:10px;border:1px solid;display:inline-block;animation:cebSlideIn .25s .15s ease both;}
.ceb-timing.instant{color:var(--red);background:rgba(255,94,94,.1);border-color:rgba(255,94,94,.3);}
.ceb-timing.next{color:var(--orange);background:rgba(255,169,77,.1);border-color:rgba(255,169,77,.3);}
@keyframes cebBounce{0%{transform:scale(0) rotate(-15deg);filter:brightness(2);}55%{transform:scale(1.3) rotate(5deg);filter:brightness(1.4);}100%{transform:scale(1) rotate(0);filter:brightness(1);}}
@keyframes cebSlideIn{0%{transform:translateY(10px);opacity:0;}100%{transform:translateY(0);opacity:1;}}

/* Active effect indicators (above HP bars) */
.active-effects{
    display:flex;gap:4px;flex-wrap:wrap;margin-top:3px;
    min-height:18px;
}
.effect-chip{
    font-size:.5rem;font-weight:700;letter-spacing:.5px;
    padding:2px 6px;border-radius:10px;
    border:1px solid;white-space:nowrap;
    animation:chipAppear .3s ease;
}
@keyframes chipAppear{0%{transform:scale(0);opacity:0;}100%{transform:scale(1);opacity:1;}}
.effect-chip.common{ background:rgba(150,150,150,.1);color:#aaa;border-color:rgba(150,150,150,.3);}
.effect-chip.rare{   background:rgba(79,172,254,.1);color:var(--blue);border-color:rgba(79,172,254,.3);}
.effect-chip.epic{   background:rgba(168,85,247,.1);color:#c084fc;border-color:rgba(168,85,247,.3);}
.effect-chip.legend{ background:rgba(255,215,0,.1);color:var(--accent);border-color:rgba(255,215,0,.4);}

/* Pending effect chip */
.effect-chip.pending-chip{
    background:rgba(255,169,77,.07);color:var(--orange);
    border-color:rgba(255,169,77,.3);
    animation:pendingPulse 1.5s ease-in-out infinite;
}
@keyframes pendingPulse{0%,100%{opacity:.5;}50%{opacity:1;}}

/* Pending badge on used hand card */
.pending-badge{
    position:absolute;bottom:2px;left:50%;transform:translateX(-50%);
    font-size:.38rem;font-weight:800;letter-spacing:.5px;
    background:var(--orange);color:#0d1117;
    border-radius:6px;padding:1px 5px;white-space:nowrap;
}

/* Game number badge in round indicator */
.game-number-badge{
    display:inline-block;
    font-size:.58rem;font-weight:800;letter-spacing:1.5px;
    text-transform:uppercase;padding:2px 10px;border-radius:20px;
    background:rgba(255,215,0,.1);color:var(--accent);
    border:1px solid rgba(255,215,0,.3);margin-left:6px;vertical-align:middle;
    animation:gameNumPop .4s cubic-bezier(.175,.885,.32,1.275);
}
@keyframes gameNumPop{0%{transform:scale(0);}60%{transform:scale(1.2);}100%{transform:scale(1);}}

/* Shield indicator */
.shield-bar{
    height:5px;background:#0d1117;border-radius:3px;
    overflow:hidden;border:1px solid rgba(79,172,254,.2);
    margin-top:2px;display:none;
}
.shield-bar.show{display:block;}
.shield-fill{
    height:100%;background:linear-gradient(90deg,var(--blue),#7fd4ff);
    border-radius:3px;transition:width .4s ease;
}

/* ── SHIELD SECTION (di bawah HP bar — sinkron player & lawan) ── */
.shield-section {
    display: none;
    width: 100%;
    margin-top: 3px;
    animation: chipAppear .35s ease;
}
.shield-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2px;
}
.shield-label {
    font-size: .58rem;
    font-weight: 700;
    letter-spacing: 1px;
    color: var(--blue);
    text-transform: uppercase;
}
.shield-val {
    font-size: .68rem;
    font-weight: 800;
    color: var(--blue);
    transition: color .3s;
}
.shield-track {
    width: 100%;
    height: 6px;
    background: rgba(5, 6, 13, 0.7);
    border: 1px solid rgba(79, 172, 254, 0.25);
    border-radius: 3px;
    overflow: hidden;
    transform: skewX(-12deg);
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.6);
}
.opp-shield .shield-track {
    transform: skewX(12deg);
}
.shield-fill {
    height: 100%;
    border-radius: 2px;
    background: linear-gradient(90deg, var(--blue), #7fd4ff);
    transition: width .5s cubic-bezier(.19, 1, 0.22, 1);
    position: relative;
    box-shadow: 0 0 10px rgba(79,172,254,.45);
}
.shield-fill::after {
    display: none !important;
}
/* Shield bar di sisi lawan (p2) — arah terbalik */
.opp-shield .shield-fill,
.p2-fill {
    float: right;
    background: linear-gradient(270deg, var(--blue), #7fd4ff) !important;
}
/* Efek flash saat shield menyerap damage */
@keyframes shieldAbsorb {
    0%   { filter: brightness(1); box-shadow: 0 0 8px rgba(79,172,254,.4); }
    40%  { filter: brightness(2); box-shadow: 0 0 24px rgba(79,172,254,.9), 0 0 40px rgba(79,172,254,.5); }
    100% { filter: brightness(1); box-shadow: 0 0 8px rgba(79,172,254,.4); }
}
.shield-fill.absorbing {
    animation: shieldAbsorb .5s ease;
}


.card-timer-bar{
    width:100%;max-width:400px;
    height:6px;background:rgba(255,255,255,.05);
    border-radius:3px;margin-top:14px;overflow:hidden;
}
.card-timer-fill{
    height:100%;width:100%;
    background:linear-gradient(90deg,var(--accent),var(--orange));
    border-radius:3px;
    transition:width linear;
}
.card-timer-fill.urgent{
    background:linear-gradient(90deg,var(--red),#ff8c00)!important;
}

/* ── CARD CONFIRM BUTTON ── */
.btn-confirm-card{
    background:var(--green);color:#0a1410;border:none;
    padding:11px 30px;border-radius:24px;font-size:.82rem;
    cursor:pointer;font-weight:800;text-transform:uppercase;
    font-family:'Poppins',sans-serif;letter-spacing:1.5px;
    transition:all .2s;box-shadow:0 0 20px rgba(74,255,187,.35);
    opacity:.4;pointer-events:none;
    display:flex;align-items:center;gap:8px;
}
.btn-confirm-card.ready{
    opacity:1;pointer-events:auto;
    animation:confirmPulse 1s ease-in-out infinite;
}
.btn-confirm-card.ready:hover{transform:scale(1.06);box-shadow:0 0 30px rgba(74,255,187,.6);}
@keyframes confirmPulse{
    0%,100%{box-shadow:0 0 20px rgba(74,255,187,.35);}
    50%{box-shadow:0 0 35px rgba(74,255,187,.65);}
}
.confirm-counter{
    background:rgba(10,20,16,.5);border-radius:12px;
    padding:1px 8px;font-size:.75rem;letter-spacing:0;
}

/* ── CARD PICK ACTIONS ROW ── */
.card-pick-actions{
    display:flex;gap:12px;align-items:center;margin-top:12px;
    flex-wrap:wrap;justify-content:center;
}

/* ── CARD NOTIFICATION TOAST ── */
.card-toast{
    position:fixed;top:16px;left:50%;transform:translateX(-50%);
    z-index:1200;
    background:var(--card);border:1px solid var(--border);
    border-radius:12px;padding:10px 18px;
    font-size:.72rem;font-weight:700;letter-spacing:1px;
    display:flex;align-items:center;gap:8px;
    animation:toastIn .4s ease, toastOut .4s ease 2.6s forwards;
    white-space:nowrap;
    box-shadow:0 10px 30px rgba(0,0,0,.6);
}
@keyframes toastIn{ 0%{top:-60px;opacity:0;}100%{top:16px;opacity:1;}}
@keyframes toastOut{0%{top:16px;opacity:1;}100%{top:-60px;opacity:0;}}

/* ── CARD RARITY COLORS FOR POPUP ── */
.popup-rarity-tag.common{ background:rgba(150,150,150,.15);color:#ccc;border:1px solid rgba(150,150,150,.3);}
.popup-rarity-tag.rare{   background:rgba(79,172,254,.15);color:var(--blue);border:1px solid rgba(79,172,254,.4);}
.popup-rarity-tag.epic{   background:rgba(168,85,247,.15);color:#c084fc;border:1px solid rgba(168,85,247,.4);}
.popup-rarity-tag.legend{ background:rgba(255,215,0,.15);color:var(--accent);border:1px solid rgba(255,215,0,.5);}

/* Responsive for mobile */
@media(max-width:420px){
    .card-pick-row{gap:8px;}
    .spell-card{width:115px;padding:10px 8px 10px;}
    .spell-card.selected-card{margin: 0 4px;}
    .card-icon{font-size:1.8rem;}
    .card-name{font-size:.7rem;}
    .card-desc{font-size:.54rem;}
}

/* ══════════════════════════════════════════════════════════
   CARD ACTIVATION ANIMATIONS
══════════════════════════════════════════════════════════ */

/* Kartu terbang dari tangan ke tengah layar — UPGRADED */
@keyframes cardThrow{
    0%  {transform:translate(0,0) scale(1) rotate(0deg);opacity:1;filter:brightness(1);}
    20% {transform:translate(calc(var(--tx)*.4),calc(var(--ty)*.4)) scale(1.6) rotate(var(--rot));opacity:1;filter:brightness(1.5);}
    50% {transform:translate(var(--tx),var(--ty)) scale(2.2) rotate(calc(var(--rot)*0.3));opacity:1;filter:brightness(3) saturate(2);}
    70% {transform:translate(var(--tx),var(--ty)) scale(1.8) rotate(0deg);opacity:.85;filter:brightness(2.5);}
    100%{transform:translate(var(--tx),var(--ty)) scale(0) rotate(540deg);opacity:0;filter:brightness(4);}
}
.card-throwing{
    position:fixed!important;z-index:2000;pointer-events:none;
    animation:cardThrow .48s cubic-bezier(.22,.68,0,1.2) forwards;
    will-change:transform,opacity,filter;
}

/* Screen flash overlay saat kartu aktif — UPGRADED */
#card-activate-flash{
    position:fixed;inset:0;z-index:1999;pointer-events:none;
    opacity:0;border-radius:0;
    will-change:opacity;
}
#card-activate-flash.flash-common{
    background:radial-gradient(ellipse at center,rgba(220,220,220,.5) 0%,transparent 60%),radial-gradient(ellipse at 30% 40%,rgba(180,180,180,.2) 0%,transparent 50%);
    animation:screenFlash .35s ease-out forwards;
}
#card-activate-flash.flash-rare{
    background:radial-gradient(ellipse at center,rgba(79,172,254,.65) 0%,transparent 55%),radial-gradient(ellipse at 70% 30%,rgba(79,172,254,.3) 0%,transparent 50%);
    animation:screenFlash .38s ease-out forwards;
}
#card-activate-flash.flash-epic{
    background:radial-gradient(ellipse at center,rgba(168,85,247,.7) 0%,transparent 55%),radial-gradient(ellipse at 30% 70%,rgba(240,147,251,.35) 0%,transparent 50%);
    animation:screenFlashEpic .42s ease-out forwards;
}
#card-activate-flash.flash-legend{
    background:radial-gradient(ellipse at center,rgba(255,215,0,.85) 0%,transparent 50%),radial-gradient(ellipse at 50% 30%,rgba(255,255,200,.4) 0%,transparent 55%),radial-gradient(ellipse at 50% 70%,rgba(255,140,0,.35) 0%,transparent 55%);
    animation:screenFlashLegend .45s ease-out forwards;
}
@keyframes screenFlash{0%{opacity:0;}15%{opacity:1;}45%{opacity:.5;}100%{opacity:0;}}
@keyframes screenFlashEpic{0%{opacity:0;transform:scale(1);}12%{opacity:1;transform:scale(1.02);}40%{opacity:.6;transform:scale(1);}100%{opacity:0;}}
@keyframes screenFlashLegend{0%{opacity:0;transform:scale(1);}10%{opacity:1;transform:scale(1.03);}20%{opacity:.7;transform:scale(1.01);}50%{opacity:.4;}100%{opacity:0;}}

/* Particle burst — UPGRADED */
.card-particle{
    position:fixed;z-index:1998;pointer-events:none;
    border-radius:50%;
    animation:particleBurst var(--dur,.4s) cubic-bezier(.22,.61,.36,1) forwards;
    will-change:transform,opacity;
}
@keyframes particleBurst{
    0%  {transform:translate(-50%,-50%) scale(1.5);opacity:1;}
    30% {opacity:1;}
    100%{transform:translate(calc(-50% + var(--px)),calc(-50% + var(--py))) scale(0);opacity:0;}
}

/* Ring expand burst — UPGRADED */
.card-ring-burst{
    position:fixed;z-index:1997;pointer-events:none;
    border-radius:50%;border:3px solid;
    width:30px;height:30px;
    animation:ringExpand .4s cubic-bezier(.22,.68,0,1.2) forwards;
    will-change:transform,opacity;
}
.card-ring-burst-2{
    position:fixed;z-index:1997;pointer-events:none;
    border-radius:50%;border:1.5px solid;
    width:20px;height:20px;
    animation:ringExpand2 .38s cubic-bezier(.22,.68,0,1.2) .06s forwards;
    will-change:transform,opacity;
    opacity:0;
}
@keyframes ringExpand{0%{transform:translate(-50%,-50%) scale(0);opacity:1;}70%{opacity:.6;}100%{transform:translate(-50%,-50%) scale(8);opacity:0;}}
@keyframes ringExpand2{0%{transform:translate(-50%,-50%) scale(0);opacity:.8;}100%{transform:translate(-50%,-50%) scale(5);opacity:0;}}

/* Hand card "used" shake + shrink */
@keyframes cardUsedShake{
    0%  {transform:translateY(0)  scale(1);}
    15% {transform:translateY(-10px) scale(1.08) rotate(-5deg);}
    30% {transform:translateY(-14px) scale(1.12) rotate(4deg);}
    50% {transform:translateY(-10px) scale(1.08) rotate(-2deg);}
    70% {transform:translateY(-5px)  scale(1.02) rotate(1deg);}
    100%{transform:translateY(0)    scale(1)   rotate(0);}
}
.hand-card.activating{
    animation:cardUsedShake .45s cubic-bezier(.175,.885,.32,1.275)!important;
    z-index:50;
}

/* Legend activation: golden ripple screen border — UPGRADED */
#card-legend-border{
    position:fixed;inset:0;z-index:1996;pointer-events:none;
    border:0px solid rgba(255,215,0,0);
    opacity:0;
    will-change:opacity,border-color,box-shadow;
}
#card-legend-border.show{
    animation:legendBorderPulse .6s cubic-bezier(.22,.68,0,1.2) forwards;
}
@keyframes legendBorderPulse{
    0%  {opacity:0;border-width:0px;border-color:rgba(255,215,0,0);box-shadow:inset 0 0 0 rgba(255,215,0,0),0 0 0 rgba(255,215,0,0);}
    18% {opacity:1;border-width:6px;border-color:rgba(255,215,0,1);box-shadow:inset 0 0 120px rgba(255,215,0,.45),0 0 120px rgba(255,215,0,.6),inset 0 0 200px rgba(255,200,0,.2);}
    45% {opacity:.7;border-width:3px;border-color:rgba(255,215,0,.7);}
    100%{opacity:0;border-width:1px;border-color:rgba(255,215,0,0);box-shadow:inset 0 0 0 rgba(255,215,0,0);}
}

/* Epic activation: purple vortex pulse — UPGRADED */
#card-epic-vortex{
    position:fixed;top:50%;left:50%;z-index:1996;pointer-events:none;
    width:180px;height:180px;border-radius:50%;
    transform:translate(-50%,-50%) scale(0);
    background:radial-gradient(ellipse at center,rgba(168,85,247,.65) 0%,rgba(200,100,255,.35) 35%,rgba(168,85,247,0) 70%);
    opacity:0;
    will-change:transform,opacity;
}
#card-epic-vortex.show{
    animation:epicVortex .5s cubic-bezier(.22,.68,0,1.2) forwards;
}
@keyframes epicVortex{
    0%  {transform:translate(-50%,-50%) scale(0) rotate(0deg);opacity:0;box-shadow:0 0 0 rgba(168,85,247,0);}
    20% {transform:translate(-50%,-50%) scale(1.2) rotate(-30deg);opacity:1;box-shadow:0 0 60px rgba(168,85,247,.8),0 0 120px rgba(168,85,247,.4);}
    55% {transform:translate(-50%,-50%) scale(2.8) rotate(-60deg);opacity:.5;}
    100%{transform:translate(-50%,-50%) scale(5) rotate(-90deg);opacity:0;box-shadow:0 0 0 rgba(168,85,247,0);}
}

/* Banner enhancement: shake untuk counter cards */
#card-effect-banner.counter-active{
    animation:bannerReveal .28s cubic-bezier(.34,1.56,.64,1) forwards,
              bannerShake  .12s ease-in-out 0.28s 3!important;
}
@keyframes bannerShake{
    0%,100%{transform:translate(-50%,-50%) rotate(0);}
    25%{transform:translate(-50%,-50%) rotate(-2.5deg) scale(1.02);}
    75%{transform:translate(-50%,-50%) rotate(2.5deg) scale(1.02);}
}

/* ══════════════════════════════════════════════════════════
   LIGHT MODE THEME
══════════════════════════════════════════════════════════ */

[data-theme="light"]{
    --bg:#f0f2f8;--dark:#f0f2f8;--mid:#e4e8f0;--card:rgba(255,255,255,0.92);--inner:#e8ecf4;
    --accent:#d4a000;--blue:#2874c2;--purple:#9b40d0;--green:#1a9960;--red:#d93636;--orange:#d48400;
    --border:rgba(0,0,0,0.1);--text:#1a1d26;--muted:rgba(26,29,38,0.5);
    --p1-color:#2874c2;--p2-color:#9b40d0;--hp-green:#1a9960;--hp-mid:#c49600;--hp-low:#d93636;
    --line:rgba(0,20,60,.06);--glass:rgba(0,20,60,.03);--faint:rgba(0,20,60,.05);
}
[data-theme="light"] body{background:#f0f2f8;color:var(--text);}
[data-theme="light"] canvas#bg{opacity:.15;}
[data-theme="light"] .hex-layer{opacity:.02;filter:invert(1);}
[data-theme="light"] .noise{opacity:.015;}
[data-theme="light"] .elines{opacity:.3;}
[data-theme="light"] .el{background:linear-gradient(to bottom,transparent,rgba(40,116,194,.25),transparent);}
[data-theme="light"] .scanline{opacity:.03;}
[data-theme="light"] .vignette{background:radial-gradient(ellipse at center,transparent 50%,rgba(0,0,0,.08) 100%);}
[data-theme="light"] .corner::before,[data-theme="light"] .corner::after{background:rgba(40,116,194,.3);}
[data-theme="light"] .game-wrap{background:linear-gradient(160deg,rgba(255,255,255,.95) 0%,rgba(240,242,248,.98) 100%);box-shadow:0 0 0 1px rgba(40,116,194,.12),0 20px 60px rgba(0,0,0,.1);}
[data-theme="light"] .game-wrap::before{background:linear-gradient(180deg,rgba(40,116,194,.04) 0%,transparent 18%,transparent 82%,rgba(155,64,208,.03) 100%);}
[data-theme="light"] .game-wrap::after{background:linear-gradient(90deg,transparent,rgba(40,116,194,.3),rgba(155,64,208,.25),transparent);}
[data-theme="light"] .game-header{background:linear-gradient(135deg,rgba(40,116,194,.06) 0%,rgba(240,242,248,.8) 50%,rgba(155,64,208,.04) 100%);border-bottom:1px solid rgba(40,116,194,.1);}
[data-theme="light"] .game-header::after{background:linear-gradient(90deg,transparent,rgba(40,116,194,.25),rgba(155,64,208,.2),transparent);}
[data-theme="light"] .game-title{text-shadow:0 0 12px rgba(212,160,0,.3),0 1px 0 rgba(0,0,0,.15);}
[data-theme="light"] .btn-quit{color:rgba(26,29,38,.5);background:rgba(217,54,54,.05);border-color:rgba(217,54,54,.15);}
[data-theme="light"] .btn-quit:hover{background:rgba(217,54,54,.12);color:var(--red);}
[data-theme="light"] .btn-theme-toggle{background:transparent;border-color:rgba(40,116,194,.18);color:rgba(40,116,194,.8);}
[data-theme="light"] .btn-theme-toggle:hover{background:rgba(40,116,194,.08);border-color:rgba(40,116,194,.35);color:#2874c2;}
[data-theme="light"] .players-row::after{background:linear-gradient(90deg,transparent,rgba(40,116,194,.1),rgba(155,64,208,.08),transparent);}
[data-theme="light"] .pc:not(.right) .pc-avatar{background:radial-gradient(circle,rgba(40,116,194,.12) 0%,rgba(40,116,194,.03) 100%);box-shadow:0 0 0 3px rgba(40,116,194,.08),0 0 12px rgba(40,116,194,.15);}
[data-theme="light"] .right .pc-avatar{background:radial-gradient(circle,rgba(155,64,208,.12) 0%,rgba(155,64,208,.03) 100%);box-shadow:0 0 0 3px rgba(155,64,208,.08),0 0 12px rgba(155,64,208,.15);}
[data-theme="light"] .pc:not(.right) .pc-name{text-shadow:none;}
[data-theme="light"] .right .pc-name{text-shadow:none;}
[data-theme="light"] .hp-label{color:rgba(0,0,0,.2);}
[data-theme="light"] .hp-val-wrap::before{background:rgba(255,255,255,.85);}
[data-theme="light"] .hp-track {
    background: rgba(0, 0, 0, 0.05);
    border: 1px solid rgba(0, 0, 0, 0.08);
    box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.08);
}
[data-theme="light"] .hp-track::before {
    background: repeating-linear-gradient(
        90deg,
        transparent 0px,
        transparent calc(10% - 1px),
        rgba(0, 0, 0, 0.08) calc(10% - 1px),
        rgba(0, 0, 0, 0.08) 10%
    );
}
[data-theme="light"] .hp-track::after { display: none !important; }

[data-theme="light"] .hp-fill {
    background: linear-gradient(90deg, #00b0ff 0%, #00e676 100%);
    box-shadow: 0 0 8px rgba(0, 230, 118, 0.25), inset 0 1px 1px rgba(255, 255, 255, 0.7);
}
[data-theme="light"] .right .hp-fill {
    background: linear-gradient(90deg, #00b0ff 0%, #00e676 100%);
    box-shadow: 0 0 8px rgba(0, 230, 118, 0.25), inset 0 1px 1px rgba(255, 255, 255, 0.7);
}
[data-theme="light"] .hp-mid .hp-fill {
    background: linear-gradient(90deg, #ff9100 0%, #ffea00 100%) !important;
    box-shadow: 0 0 8px rgba(255, 145, 0, 0.25) !important;
}
[data-theme="light"] .hp-low .hp-fill {
    background: linear-gradient(90deg, #d50000 0%, #ff1744 100%) !important;
    box-shadow: 0 0 10px rgba(213, 0, 0, 0.3) !important;
}
[data-theme="light"] .battle-area{background:radial-gradient(ellipse at 20% 50%,rgba(40,116,194,.04) 0%,transparent 60%),radial-gradient(ellipse at 80% 50%,rgba(155,64,208,.03) 0%,transparent 60%),rgba(245,247,252,.8);border-color:rgba(40,116,194,.08);box-shadow:inset 0 0 20px rgba(0,0,0,.03),0 2px 12px rgba(0,0,0,.05);}
[data-theme="light"] .hand{filter:drop-shadow(0 6px 16px rgba(0,0,0,.15));}
[data-theme="light"] .rounds-row{background:linear-gradient(135deg,rgba(40,116,194,.04) 0%,rgba(255,255,255,.6) 100%);border-color:rgba(40,116,194,.08);box-shadow:inset 0 1px 0 rgba(255,255,255,.8),0 2px 8px rgba(0,0,0,.05);}
[data-theme="light"] .dot{background:rgba(0,0,0,.06);border-color:rgba(0,0,0,.1);}
[data-theme="light"] .dot.p1{
    background:var(--p1-color) !important;
    border-color:var(--p1-color) !important;
    box-shadow:0 0 8px var(--p1-color) !important;
    transform:scale(1.15) !important;
}
[data-theme="light"] .dot.p2{
    background:var(--p2-color) !important;
    border-color:var(--p2-color) !important;
    box-shadow:0 0 8px var(--p2-color) !important;
    transform:scale(1.15) !important;
}
[data-theme="light"] .round-num{text-shadow:none;}
[data-theme="light"] .round-lbl{color:rgba(0,0,0,.2);}
[data-theme="light"] #status-bar.green,[data-theme="light"] #status-bar.red,[data-theme="light"] #status-bar.yellow,[data-theme="light"] #status-bar.blue{text-shadow:none;}
[data-theme="light"] hr{background:linear-gradient(90deg,transparent,rgba(40,116,194,.12),rgba(155,64,208,.08),transparent);}
[data-theme="light"] .instruction{color:rgba(0,0,0,.35);}
[data-theme="light"] .choice{background:linear-gradient(145deg,rgba(255,255,255,.95),rgba(240,243,250,.98));border-color:rgba(0,0,0,.08);box-shadow:0 4px 16px rgba(0,0,0,.06);}
[data-theme="light"] .choice-rock:hover{background:rgba(217,54,54,0.05);border-color:rgba(217,54,54,0.5);box-shadow:0 8px 24px rgba(217,54,54,0.15);}
[data-theme="light"] .choice-scissors:hover{background:rgba(40,116,194,0.05);border-color:rgba(40,116,194,0.5);box-shadow:0 8px 24px rgba(40,116,194,0.15);}
[data-theme="light"] .choice-paper:hover{background:rgba(26,153,96,0.05);border-color:rgba(26,153,96,0.5);box-shadow:0 8px 24px rgba(26,153,96,0.12);}
[data-theme="light"] .choice img{filter:drop-shadow(0 4px 10px rgba(0,0,0,.12));}
[data-theme="light"] .choice-label{color:rgba(26,29,38,.5);}
[data-theme="light"] .choice.selected{background:rgba(212,160,0,0.08)!important;}
[data-theme="light"] .btn-continue{
    background:rgba(212,160,0,0.06) !important;
    border:1.5px solid rgba(212,160,0,0.5) !important;
    color:#b38600 !important;
    box-shadow:0 4px 12px rgba(212,160,0,0.1), inset 0 1px 0 rgba(255,255,255,0.8) !important;
}
[data-theme="light"] .btn-continue:hover{
    background:rgba(212,160,0,0.16) !important;
    border-color:#d4a000 !important;
    color:#8c6600 !important;
    box-shadow:0 0 20px rgba(212,160,0,0.35) !important;
}
[data-theme="light"] .btn-menu{background:rgba(0,0,0,.04);border-color:rgba(0,0,0,.1);color:var(--muted);}
[data-theme="light"] .spell-card.common{background:linear-gradient(160deg,#fafbfc 0%,#f0f2f5 100%);}
[data-theme="light"] .spell-card.rare{background:linear-gradient(160deg,#f0f7ff 0%,#e8f0fa 100%);}
[data-theme="light"] .spell-card.epic{background:linear-gradient(160deg,#f8f0ff 0%,#f0e8fa 100%);}
[data-theme="light"] .spell-card.legend{background:linear-gradient(160deg,#fffbf0 0%,#fff5e0 100%);}
[data-theme="light"] .hand-card.common{background:linear-gradient(155deg,#fafbfc,#f0f2f5);}
[data-theme="light"] .hand-card.rare{background:linear-gradient(155deg,#f0f7ff,#e8f0fa);}
[data-theme="light"] .hand-card.epic{background:linear-gradient(155deg,#f8f0ff,#f0e8fa);}
[data-theme="light"] .hand-card.legend{background:linear-gradient(155deg,#fffbf0,#fff5e0);}
[data-theme="light"] #card-hand{border-top-color:rgba(40,116,194,.08);background:linear-gradient(180deg,rgba(40,116,194,.02) 0%,transparent 100%);}
[data-theme="light"] .card-hand-label{background:linear-gradient(135deg,#f5f7fb,#eef1f7);border-color:rgba(40,116,194,.1);color:rgba(40,116,194,.4);}
[data-theme="light"] .hand-card-empty{border-color:rgba(40,116,194,.08);background:rgba(40,116,194,.02);color:rgba(0,0,0,.06);}
[data-theme="light"] .hand-card.used::after{background:rgba(255,255,255,.75);color:rgba(0,0,0,.3);}
[data-theme="light"] #card-pick-overlay{background:radial-gradient(ellipse at 25% 20%,rgba(40,116,194,.06) 0%,transparent 45%),radial-gradient(ellipse at 75% 80%,rgba(155,64,208,.06) 0%,transparent 45%),rgba(240,242,248,.97);}
[data-theme="light"] .card-popup-box{background:rgba(255,255,255,.95);border-color:rgba(0,0,0,.1);}
[data-theme="light"] #card-use-popup{background:rgba(0,0,0,.2);}
[data-theme="light"] .btn-cancel-card{background:rgba(0,0,0,.04);border-color:rgba(0,0,0,.1);color:var(--muted);}
[data-theme="light"] .card-toast{background:rgba(255,255,255,.95);border-color:rgba(0,0,0,.08);box-shadow:0 8px 24px rgba(0,0,0,.1);}
[data-theme="light"] .effect-chip.common{background:rgba(150,150,150,.08);}
[data-theme="light"] .effect-chip.rare{background:rgba(40,116,194,.08);}
[data-theme="light"] .effect-chip.epic{background:rgba(155,64,208,.08);}
[data-theme="light"] .effect-chip.legend{background:rgba(212,160,0,.08);}
[data-theme="light"] #fight-overlay{background:radial-gradient(ellipse at center,rgba(240,242,248,.96) 0%,rgba(230,233,240,.98) 100%);}
[data-theme="light"] #fight-banner{text-shadow:0 0 30px rgba(212,160,0,.5),0 0 60px rgba(212,160,0,.2),3px 3px 0 rgba(138,106,0,.3);}
[data-theme="light"] #card-effect-banner{background:linear-gradient(160deg,rgba(255,255,255,.96) 0%,rgba(245,247,252,.98) 100%);border-color:rgba(0,0,0,.1);box-shadow:0 16px 50px rgba(0,0,0,.12);}
[data-theme="light"] .shield-track {
    background: rgba(0, 0, 0, 0.04);
    border-color: rgba(40, 116, 194, 0.25);
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05);
}
[data-theme="light"] circle.track{stroke:rgba(0,0,0,.08);}
[data-theme="light"] .timer-num{text-shadow:0 0 8px rgba(212,160,0,.3);}
body,.game-wrap,.game-header,.battle-area,.choice,.hand-card,.spell-card,.hp-track,.rounds-row,#card-hand,.card-hand-label,#card-pick-overlay,#fight-overlay,#card-effect-banner,.card-popup-box,.card-toast,.effect-chip,.btn-quit,.btn-theme-toggle,.dot,.hand-card-empty{transition:background .4s ease,border-color .4s ease,box-shadow .4s ease,color .4s ease;}

/* ==========================================
   EXIT CONFIRMATION MODAL
   ========================================== */
.exit-overlay{position:fixed;inset:0;z-index:9500;
  background:rgba(0,0,0,.8);backdrop-filter:blur(10px);
  display:none;align-items:center;justify-content:center;padding:20px;}
.exit-overlay.open{display:flex;}
.exit-modal{
  position:relative;width:min(400px,92vw);
  background:linear-gradient(155deg,rgba(8,10,22,.98),rgba(5,6,13,1));
  border:1px solid rgba(255,77,77,.2);
  clip-path:polygon(16px 0%,100% 0%,calc(100% - 16px) 100%,0% 100%);
  box-shadow:0 0 60px rgba(255,77,77,.1),0 24px 80px rgba(0,0,0,.8);
  padding:36px 32px 28px;text-align:center;
  opacity:0;transform:scale(.9) translateY(20px);
  transition:opacity .28s cubic-bezier(.34,1.2,.64,1),transform .28s cubic-bezier(.34,1.2,.64,1);
}
.exit-modal.show{opacity:1;transform:scale(1) translateY(0);}
.exit-modal-topbar{
  position:absolute;top:0;left:16px;right:16px;height:2px;
  background:linear-gradient(90deg,transparent,rgba(255,77,77,.8),rgba(245,200,66,.6),transparent);
  border-radius:0 0 2px 2px;
}
.exit-icon{font-size:2.4rem;margin-bottom:12px;
  filter:drop-shadow(0 0 14px rgba(245,200,66,.5));
  animation:exit-icon-pulse 2s ease-in-out infinite;}
@keyframes exit-icon-pulse{0%,100%{filter:drop-shadow(0 0 10px rgba(245,200,66,.4))}50%{filter:drop-shadow(0 0 22px rgba(245,200,66,.7))}}
.exit-title{
  font-family:'Orbitron',sans-serif;font-size:1.5rem;
  letter-spacing:.22em;color:#ff4d4d;
  text-shadow:0 0 20px rgba(255,77,77,.5);
  margin-bottom:12px;
}
.exit-desc{
  font-family:'Poppins',sans-serif;font-size:.88rem;
  color:rgba(238,240,255,.55);letter-spacing:.06em;
  line-height:1.7;margin-bottom:28px;
}
.exit-actions{display:flex;gap:12px;justify-content:center;}
.exit-btn{
  font-family:'Orbitron',sans-serif;font-size:.72rem;
  letter-spacing:.16em;text-transform:uppercase;
  padding:11px 28px;cursor:pointer;border:1px solid;
  clip-path:polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%);
  transition:all .2s;position:relative;overflow:hidden;
}
.exit-btn::before{content:'';position:absolute;top:0;left:-100%;width:60%;height:100%;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,.07),transparent);
  transform:skewX(-20deg);transition:left .35s;}
.exit-btn:hover::before{left:160%;}
.exit-btn-cancel{
  background:rgba(238,240,255,.05) !important;border-color:rgba(238,240,255,.15) !important;
  color:rgba(238,240,255,.6) !important;
}
.exit-btn-cancel:hover{
  background:rgba(238,240,255,.1) !important;border-color:rgba(238,240,255,.35) !important;
  color:var(--text) !important;
}
.exit-btn-confirm{
  background:rgba(255,77,77,.1) !important;border-color:rgba(255,77,77,.3) !important;
  color:#ff4d4d !important;
}
.exit-btn-confirm:hover{
  background:linear-gradient(135deg, #ff4d4d 0%, #cc1111 100%) !important;
  color:#fff !important;border-color:transparent !important;
  box-shadow:0 4px 15px rgba(255,77,77,0.4) !important;
  transform:translateY(-2px) scale(1.02) !important;
}

/* LIGHT MODE — EXIT MODAL */
[data-theme="light"] .exit-overlay{background:rgba(200,210,230,.75);}
[data-theme="light"] .exit-modal{
  background:linear-gradient(155deg,rgba(250,252,255,.99),rgba(240,244,252,1));
  border-color:rgba(217,48,48,.2);
  box-shadow:0 0 40px rgba(217,48,48,.08),0 24px 60px rgba(0,0,0,.15);
}
[data-theme="light"] .exit-title{color:#c0200f;text-shadow:none;}
[data-theme="light"] .exit-desc{color:rgba(26,29,46,.55);}
[data-theme="light"] .exit-btn-cancel{
  background:rgba(0,0,0,.04) !important;border-color:rgba(0,0,0,.12) !important;color:rgba(26,29,46,.6) !important;
}
[data-theme="light"] .exit-btn-cancel:hover{
  background:rgba(0,0,0,.08) !important;border-color:rgba(0,0,0,.2) !important;color:#1a1d2e !important;
}
[data-theme="light"] .exit-btn-confirm{
  background:rgba(217,48,48,.06) !important;border-color:rgba(217,48,48,.25) !important;
  color:#c0200f !important;
}
[data-theme="light"] .exit-btn-confirm:hover{
  background:linear-gradient(135deg, #e53e3e 0%, #b81414 100%) !important;
  color:#fff !important;border-color:transparent !important;
  box-shadow:0 4px 15px rgba(229,62,62,0.25) !important;
  transform:translateY(-2px) scale(1.02) !important;
}

/* ==========================================
   AFK NOTIFICATION MODAL
   ========================================== */
.afk-overlay{position:fixed;inset:0;z-index:9999;
  background:rgba(5, 6, 13, 0.85);backdrop-filter:blur(15px);
  display:none;align-items:center;justify-content:center;padding:20px;}
.afk-overlay.open{display:flex;}
.afk-modal{
  position:relative;width:min(420px, 94vw);
  background:linear-gradient(155deg,rgba(15,18,36,.98),rgba(8,9,18,1));
  border:1px solid rgba(255,169,77,.25);
  clip-path:polygon(20px 0%,100% 0%,calc(100% - 20px) 100%,0% 100%);
  box-shadow:0 0 60px rgba(255,169,77,.12),0 24px 80px rgba(0,0,0,.9);
  padding:40px 32px 32px;text-align:center;
  opacity:0;transform:scale(.85) translateY(30px);
  transition:opacity .35s cubic-bezier(.34,1.25,.64,1),transform .35s cubic-bezier(.34,1.25,.64,1);
}
.afk-modal.show{opacity:1;transform:scale(1) translateY(0);}
.afk-modal-topbar{
  position:absolute;top:0;left:20px;right:20px;height:3px;
  background:linear-gradient(90deg,transparent,rgba(255,169,77,.85),rgba(255,94,94,.85),transparent);
  border-radius:0 0 3px 3px;
}
.afk-icon{font-size:3rem;margin-bottom:16px;
  filter:drop-shadow(0 0 16px rgba(255,169,77,.6));
  animation:afk-icon-bounce 1.5s ease-in-out infinite alternate;}
@keyframes afk-icon-bounce{
  0%{transform:translateY(0);filter:drop-shadow(0 0 10px rgba(255,169,77,.4));}
  100%{transform:translateY(-8px);filter:drop-shadow(0 0 24px rgba(255,169,77,.8));}
}
.afk-title{
  font-family:'Orbitron',sans-serif;font-size:1.6rem;font-weight:900;
  letter-spacing:.2em;color:#ffa94d;
  text-shadow:0 0 25px rgba(255,169,77,.4);
  margin-bottom:14px;
}
.afk-desc{
  font-family:'Poppins',sans-serif;font-size:.92rem;
  color:rgba(238,240,255,.75);letter-spacing:.06em;
  line-height:1.75;margin-bottom:32px;
}
.afk-desc strong{color:#ff5e5e;}
.afk-actions{display:flex;justify-content:center;}
.afk-btn{
  font-family:'Orbitron',sans-serif;font-size:.78rem;font-weight:700;
  letter-spacing:.18em;text-transform:uppercase;
  padding:12px 36px;cursor:pointer;border:1px solid;
  background:rgba(255,169,77,.1) !important;border-color:rgba(255,169,77,.35) !important;
  color:#ffa94d !important;
  clip-path:polygon(10px 0%,100% 0%,calc(100% - 10px) 100%,0% 100%);
  transition:all .25s ease;position:relative;overflow:hidden;
}
.afk-btn::before{content:'';position:absolute;top:0;left:-100%;width:60%;height:100%;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,.15),transparent);
  transform:skewX(-20deg);transition:left .4s;}
.afk-btn:hover::before{left:160%;}
.afk-btn:hover{
  background:linear-gradient(135deg, #ffa94d 0%, #ff5e5e 100%) !important;
  color:#fff !important;border-color:transparent !important;
  box-shadow:0 4px 20px rgba(255,169,77,0.5) !important;
  transform:translateY(-2px) scale(1.03) !important;
}

/* LIGHT MODE — AFK MODAL */
[data-theme="light"] .afk-overlay{background:rgba(230,235,245,.82);}
[data-theme="light"] .afk-modal{
  background:linear-gradient(155deg,rgba(255,255,255,.99),rgba(242,246,253,1));
  border-color:rgba(255,169,77,.4);
  box-shadow:0 0 40px rgba(255,169,77,.15),0 24px 60px rgba(0,0,0,.15);
}
[data-theme="light"] .afk-title{color:#e67e22;text-shadow:none;}
[data-theme="light"] .afk-desc{color:rgba(26,29,46,.75);}
[data-theme="light"] .afk-btn{
  background:rgba(255,169,77,.08) !important;border-color:rgba(255,169,77,.4) !important;
  color:#e67e22 !important;
}
[data-theme="light"] .afk-btn:hover{
  background:linear-gradient(135deg, #e67e22 0%, #d35400 100%) !important;
  color:#fff !important;
}

</style>
<style>
/* ══ UNIVERSAL BUTTON STYLES (Default & Hover) ══ */
button:not(.btn-theme-toggle):not(.btn-setting):not(.m-close):not(.btn-close-modal):not(.lb2-close-btn):not(.btn-close):not(.ss-mute-btn):not(.xbtn-cancel):not(.exit-btn-cancel):not(.exit-btn-confirm):not(.nav-btn.danger):not(.av-edit-btn):not(.edit-name-btn):not(.avm-refresh-btn):not(.toggle-pw):not(.btn-cancel-card):not(.btn-quit):not(.modal-tab):not(.btn-continue),
.btn, .mbtn, .cta, .btn-submit, .btn-to-login,
.nav-btn:not(.danger),
a.btn, .xbtn-battle, .lb2-act-btn, .btn-save, .chat-send-btn, .btn-rematch, .btn-use-card, .btn-confirm-card {
  background: var(--text) !important;
  color: var(--dark) !important;
  border-color: var(--border) !important;
}
button:not(.btn-theme-toggle):not(.btn-setting):not(.m-close):not(.btn-close-modal):not(.lb2-close-btn):not(.btn-close):not(.ss-mute-btn):not(.xbtn-cancel):not(.exit-btn-cancel):not(.exit-btn-confirm):not(.nav-btn.danger):not(.av-edit-btn):not(.edit-name-btn):not(.avm-refresh-btn):not(.toggle-pw):not(.btn-cancel-card):not(.btn-quit):not(.modal-tab):not(.btn-continue):hover,
.btn:hover, .mbtn:hover, .cta:hover, .btn-submit:hover, .btn-to-login:hover,
.nav-btn:not(.danger):hover,
a.btn:hover, .xbtn-battle:hover, .lb2-act-btn:hover, .btn-save:hover, .chat-send-btn:hover, .btn-rematch:hover, .btn-use-card:hover, .btn-confirm-card:hover {
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

<!-- ══ CARD ACTIVATION EFFECT ELEMENTS ══ -->
<div id="card-activate-flash"></div>
<div id="card-legend-border"></div>
<div id="card-epic-vortex"></div>

<!-- ══ BACKGROUND LAYERS (animated) ══ -->
<canvas id="bg"></canvas>
<div class="hex-layer"></div>
<div class="noise"></div>
<div class="elines" id="EL"></div>
<div class="scanline"></div>
<div class="vignette"></div>
<div class="bparticles" id="PT"></div>
<div class="corner c-tl"></div><div class="corner c-tr"></div>
<div class="corner c-bl"></div><div class="corner c-br"></div>

<div class="game-wrap" id="gameWrap">

    <!-- ══ CONNECTING SCREEN ══ -->
    <div id="connect-screen">
        <div class="connect-spinner"></div>
        <div class="connect-title">Menghubungkan ke server...</div>
        <div class="connect-sub">Menyiapkan arena pertempuran</div>
    </div>

    <!-- ══ MAIN GAME (hidden until match found) ══ -->
    <div id="game-main" style="display:none">

        <!-- HEADER -->
        <div class="game-header">
            <div class="game-title">Lucky Battle</div>
            <button class="btn-quit" id="btnQuit">✕ Keluar</button>
        </div>

        <!-- PLAYERS + HP -->
        <div class="players-row">
            <!-- Player 1 (kamu) -->
            <div class="pc" id="p1-card">
                <div class="pc-info">
                    <div class="pc-avatar">🧑</div>
                    <div>
                        <div class="pc-name" id="p1-name">PLAYER</div>
                        <div class="pc-id" id="p1-id">@player</div>
                        <div class="pc-you">(Kamu)</div>
                    </div>
                </div>
                <div class="hp-section">
                    <div class="hp-row">
                        <div class="hp-val-wrap"><span class="hp-val" id="p1-hp-val">100</span></div>
                    </div>
                    <div class="hp-track"><div class="hp-fill" id="p1-hp-bar" style="width:100%"></div></div>
                </div>

            </div>

            <!-- VS -->
            <div class="vs-badge"><span class="vs-text">VS</span></div>

            <!-- Player 2 (lawan) -->
            <div class="pc right" id="p2-card">
                <div class="pc-info">
                    <div>
                        <div class="pc-name" id="p2-name">LAWAN</div>
                        <div class="pc-id" id="p2-id">@opponent</div>
                        <div class="pc-you">(Musuh)</div>
                    </div>
                    <div class="pc-avatar" style="border-color:var(--p2-color);background:rgba(240,147,251,.15);">🤺</div>
                </div>
                <div class="hp-section">
                    <div class="hp-row">
                        <div class="hp-val-wrap"><span class="hp-val" id="p2-hp-val">100</span></div>
                    </div>
                    <div class="hp-track"><div class="hp-fill" id="p2-hp-bar" style="width:100%"></div></div>
                </div>
            </div>
        </div>

        <!-- ROUND DOTS -->
        <div class="rounds-row">
            <div class="dots" id="p1-dots">
                <div class="dot" id="pd-0"></div>
                <div class="dot" id="pd-1"></div>
            </div>
            <div class="round-center">
                <span class="round-num" id="round-label">RONDE 1</span>
                <span class="round-lbl">BEST OF 3</span>
            </div>
            <div class="dots" id="p2-dots">
                <div class="dot" id="cd-0"></div>
                <div class="dot" id="cd-1"></div>
            </div>
        </div>

        <!-- BATTLE AREA -->
        <div class="battle-area">
            <div class="hand-wrap">
                <img id="p1-hand" src="assets/Rock.png" class="hand" alt="p1">
                <span class="opp-badge" id="p1-chose-badge">✅ Sudah pilih</span>
            </div>

            <div class="timer-wrap">
                <div class="timer-ring" id="timer-ring">
                    <svg width="50" height="50" viewBox="0 0 50 50">
                        <circle class="track" cx="25" cy="25" r="22"/>
                        <circle class="prog" id="timer-circle" cx="25" cy="25" r="22"/>
                    </svg>
                    <span class="timer-num" id="timer-num">8</span>
                </div>
                <div id="timeout-msg"></div>
            </div>

            <div class="hand-wrap">
                <img id="p2-hand" src="assets/Question.svg" class="hand" alt="p2">
                <span class="opp-badge" id="p2-chose-badge">✅ Sudah pilih</span>
            </div>
        </div>

        <!-- STATUS BAR -->
        <div id="status-bar">Menunggu lawan...</div>

        <hr>

        <!-- CHOICE SCREEN -->
        <div id="selection-screen">
            <p class="instruction">⏱ Pilih senjatamu sekarang!</p>
            <div class="choices">
                <div class="choice choice-rock" onclick="sendChoice('rock')">
                    <div class="cshine"></div>
                    <img src="assets/Rock.png" alt="Batu">
                    <span class="choice-label">Batu</span>
                </div>
                <div class="choice choice-scissors" onclick="sendChoice('scissors')">
                    <div class="cshine"></div>
                    <img src="assets/Scissors.png" alt="Gunting">
                    <span class="choice-label">Gunting</span>
                </div>
                <div class="choice choice-paper" onclick="sendChoice('paper')">
                    <div class="cshine"></div>
                    <img src="assets/Paper.png" alt="Kertas">
                    <span class="choice-label">Kertas</span>
                </div>
            </div>
        </div>

        <!-- WAITING SCREEN (setelah player pilih) -->
        <div id="waiting-screen" style="display:none">
            <div class="waiting-box">
                <div class="waiting-icon">⏳</div>
                <div class="waiting-title">Sudah memilih! Menunggu lawan...</div>
                <div class="waiting-sub" id="waiting-sub">Lawan sedang berpikir...</div>
                <div class="waiting-weapon" id="waiting-weapon">
                    <img id="waiting-weapon-img" src="assets/Rock.png" alt="pilihan">
                    <div class="waiting-weapon-label" id="waiting-weapon-label">BATU</div>
                    <div class="waiting-weapon-tag">✅ Senjatamu</div>
                </div>
            </div>
        </div>

        <!-- RESULT SCREEN -->
        <div id="result-screen">
            <h2 id="result-text"></h2>
            <button class="btn-continue" id="btn-continue">LANJUTKAN</button>
        </div>

        <!-- MATCH OVER SCREEN -->
        <div id="match-over">
            <div class="match-over-title" id="match-over-title"></div>
            <div class="match-over-sub" id="match-over-sub"></div>
            <div class="match-over-btns">
                <button class="btn-rematch" id="btn-rematch" onclick="sendRematch()">🔄 Rematch</button>
                <button class="btn-menu" onclick="goMenu()">↩ Menu</button>
            </div>
            <div style="font-size:.65rem;color:var(--muted);text-align:center;margin-top:8px;" id="rematch-status"></div>
        </div>

        <!-- CARD HAND (kartu aktif yang dipegang player) -->
        <div id="card-hand">
            <div class="card-hand-label">✦ KARTU RONDE</div>
            <div class="hand-card-empty">🂠</div>
            <div class="hand-card-empty">🂠</div>
        </div>

    </div><!-- #game-main -->

</div><!-- .game-wrap -->

<!-- ══ CARD PICK OVERLAY ══ -->
<div id="card-pick-overlay">
    <div class="cpo-header">
        <div class="card-pick-game-badge" id="card-pick-game-badge">GAME 1</div>
    </div>
    <div class="card-pick-title">✦ PILIH KARTUMU ✦</div>
    <div class="cpo-divider"></div>
    <div class="card-pick-sub">Pilih <span id="cpo-pick-count">2 dari 3</span> kartu · Konfirmasi sebelum waktu habis</div>
    <div class="card-pick-row" id="card-pick-row">
        <!-- Cards injected by JS -->
    </div>
    <div class="card-timer-bar"><div class="card-timer-fill" id="card-timer-fill"></div></div>
    <div class="card-pick-actions" id="card-pick-actions">
        <button class="btn-confirm-card" id="btn-confirm-card" onclick="confirmCardPick()">
            ✅ Konfirmasi <span class="confirm-counter" id="confirm-counter">0/2</span>
        </button>
        <button class="btn-skip-card" onclick="skipCardPick()">Lewati</button>
    </div>

    <!-- Waiting for opponent to pick cards -->
    <div id="card-pick-waiting">
        <div class="cpw-spinner"></div>
        <div class="cpw-title">Menunggu Lawan...</div>
        <div class="cpw-cards-preview" id="cpw-cards-preview"></div>
        <div class="cpw-sub">Pilihanmu sudah dikunci.<br>Menunggu lawan selesai memilih kartu.</div>
        <div class="cpw-opp-status">
            <div class="cpw-opp-dot" id="cpw-opp-dot"></div>
            <span id="cpw-opp-label">Lawan sedang memilih kartu...</span>
        </div>
    </div>
</div>

<!-- ══ CARD USE POPUP ══ -->
<div id="card-use-popup">
    <div class="card-popup-box">
        <div class="popup-card-icon" id="popup-icon">⚡</div>
        <div class="popup-card-name" id="popup-name">NAMA KARTU</div>
        <div class="popup-rarity-tag" id="popup-rarity">COMMON</div>
        <div class="popup-card-desc" id="popup-desc">Deskripsi efek kartu</div>
        <div class="popup-btns">
            <button class="btn-use-card" id="btn-use-card-confirm">⚡ GUNAKAN!</button>
            <button class="btn-cancel-card" onclick="closeCardPopup()">✕ Batal</button>
        </div>
    </div>
</div>

<!-- ══ CARD EFFECT BANNER ══ -->
<div id="card-effect-banner">
    <span class="ceb-icon" id="ceb-icon">⚡</span>
    <span class="ceb-text" id="ceb-text">KARTU AKTIF!</span>
    <span class="ceb-sub" id="ceb-sub"></span>
    <span class="ceb-timing" id="ceb-timing"></span>
</div>

<!-- ══ FIGHT OVERLAY (full-screen weapon clash reveal) ══ -->
<div id="fight-overlay">
    <div id="fight-banner">⚔️ FIGHT!</div>
    <div class="fight-arena">
        <div class="fight-player-col p1-col">
            <div class="fight-player-name p1" id="fight-p1-name">PLAYER 1</div>
            <img class="fight-weapon p1" id="fight-weapon-p1" src="assets/Rock.png" alt="p1 weapon">
            <div class="fight-weapon-label" id="fight-label-p1">BATU</div>
        </div>
        <div style="display:flex;flex-direction:column;align-items:center;gap:6px;">
            <div id="fight-vs">VS</div>
        </div>
        <div class="fight-player-col p2-col">
            <div class="fight-player-name p2" id="fight-p2-name">PLAYER 2</div>
            <img class="fight-weapon p2" id="fight-weapon-p2" src="assets/Rock.png" alt="p2 weapon">
            <div class="fight-weapon-label" id="fight-label-p2">BATU</div>
        </div>
    </div>
    <div id="fight-result-text"></div>
    <div id="fight-winner-detail"></div>
</div>

<script>
// ═══════════════════════════════════════════════════════════
//  CONFIG & CONSTANTS
// ═══════════════════════════════════════════════════════════
// ── CONFIG & CONSTANTS
// ═══════════════════════════════════════════════════════════
// MY_ID dan MY_NAME TIDAK lagi diambil dari PHP session di sini.
// PHP session tidak aman digunakan di gameplay karena bisa tertimpa
// jika dua akun berbeda login di browser yang sama.
// Nilai asli dari PHP session hanya dipakai sebagai fallback darurat.
const _PHP_PLAYER_ID   = <?= json_encode($player_id) ?>;
const _PHP_PLAYER_NAME = <?= json_encode($player_name) ?>;

// MY_ID dan MY_NAME akan di-set setelah matchData dibaca dari sessionStorage
// Prioritas: matchData._my_player_id > sessionStorage.lobby_player_id > PHP player_id
let MY_ID   = _PHP_PLAYER_ID;
let MY_NAME = _PHP_PLAYER_NAME;

const CIRC       = 138.2;  // 2π × r22
const HP_MAX     = 100;
const TIMER_SECS = 8;

const HAND_IMG = {
    rock:     'assets/Rock.png',
    paper:    'assets/Paper.png',
    scissors: 'assets/Scissors.png',
};

const HAND_LABEL = {
    rock:     '🪨 BATU',
    paper:    '📄 KERTAS',
    scissors: '✂️ GUNTING',
};

// ═══════════════════════════════════════════════════════════
//  STATE
// ═══════════════════════════════════════════════════════════
let ws        = null;
let roomId    = null;
let matchData = null;   // disimpan global agar connectWS bisa akses rating dll
let opponentId   = null;
let opponentName = 'Lawan';

let myHp    = HP_MAX;
let oppHp   = HP_MAX;
let myWins  = 0;
let oppWins = 0;
let round   = 1;
let drawStreak = 0;

let locked         = false;
let matchOver      = false;
let fightAnimating = false;   // true selama animasi fight overlay aktif

// Multiplier god_attack lawan — di-set saat hp_sync god_attack terpicu (gl===0)
let _oppGodAtkMultiplier  = 1;
let _oppGodAtk2Multiplier = 1;
let _oppGodAtk3Multiplier = 1;
let pendingRoundMsg = null;   // simpan round_start/next_turn jika datang saat animasi aktif
let timerInt       = null;
let timerLeft      = TIMER_SECS;
let myLastChoice   = 'rock';
// Saat lawan punya barrier: hp_sync dari lawan kirim HP yang sudah dikoreksi 50%.
// Simpan di sini agar Phase-4 bisa menimpa oppHp yang salah (dari kalkulasi server).
let _barrierCorrectedOppHp = null;
// Burn Attack dari lawan: simpan jumlah burn yang pending untuk diterapkan ke myHp
// setelah applyRoundStart menerapkan HP dari server
let _pendingCritFromOpp = 0;
// Reverse Result dari lawan: flag bahwa lawan memicu reverse saat fight overlay masih aktif
// Diset di opponent_hp_sync handler, dibaca di Phase 4 fight overlay untuk update result screen
let _pendingReverseFromOpp = false;
let _pendingReverseFromOppGl = 0; // games_left sisa
// HP override dari reverse_result lawan — jika hp_sync datang SEBELUM Phase 4 fight overlay
// Phase 4 akan menimpa myHp/oppHp dengan nilai draw (server). Simpan di sini agar Phase 4
// menggunakan nilai yang benar setelah pengesetan dari hp_sync.
let _reverseOppOverrideMyHp  = null;  // HP kita yang benar (berkurang 20)
let _reverseOppOverrideOppHp = null;  // HP lawan yang benar (tidak berubah)
let _godAttackOverrideMyHp   = null;  // HP kita setelah shield serap god_attack (override Phase 4)
let _pendingReverseGl        = -1;    // gamesLeft chip reverse_result lawan yang belum diterapkan
let _justStartedNewRound     = false; // true setelah new_round, agar applyRoundStart tidak tolak HP=100 dari server
// Track semua setTimeout dalam showFightOverlay agar bisa dibatalkan (mis: saat game_repeated)
let _fightTimeouts = [];

// ═══════════════════════════════════════════════════════════
//  INIT — Ambil data match dari sessionStorage (dari lobby_pvp)
// ═══════════════════════════════════════════════════════════
window.addEventListener('load', () => {
    const matchDataRaw = sessionStorage.getItem('match_data');

    // ── FIX CROSS-DEVICE: wsUrl selalu pakai hostname dari browser ──────
    // sessionStorage.ws_url hanya tersedia di tab asal (device yang sama).
    // window.location.hostname otomatis berisi IP server yang sedang diakses,
    // sehingga bekerja untuk device/browser manapun di jaringan yang sama.
    const wsUrl = 'ws://' + window.location.hostname + ':8080';
    // ────────────────────────────────────────────────────────────────────

    // ── FIX CROSS-DEVICE: fallback match_data dari PHP session via URL ──
    // Jika sessionStorage kosong (device lain / tab baru), gunakan data
    // yang sudah di-inject PHP dari ?md= query param atau session server.
    const _phpMatchData = <?= $php_match_data_json ?>;

    if (!matchDataRaw && !_phpMatchData) {
        document.getElementById('connect-screen').querySelector('.connect-title').textContent =
            '❌ Tidak ada data match';
        document.getElementById('connect-screen').querySelector('.connect-sub').textContent =
            'Kembali ke lobby dalam 2 detik...';
        setTimeout(() => window.location.href = 'lobby_pvp.php', 2000);
        return;
    }

    matchData = matchDataRaw ? JSON.parse(matchDataRaw) : _phpMatchData;
    roomId = matchData.room_id;

    // ── KUNCI FIX ──────────────────────────────────────────────
    // Urutan prioritas untuk menentukan identity tab ini:
    // 1. matchData._my_player_id  → disimpan lobby saat match_found (paling akurat)
    // 2. sessionStorage lobby_player_id → disimpan lobby saat halaman load
    // 3. _PHP_PLAYER_ID → dari ?pid= di URL yang sudah di-resolve PHP
    // Semua lebih aman dari $_SESSION['player_id'] mentah karena
    // session PHP bisa tertimpa oleh tab lain.
    if (matchData._my_player_id) {
        MY_ID   = matchData._my_player_id;
        MY_NAME = matchData._my_player_name || MY_NAME;
    } else {
        // Fallback: ambil dari sessionStorage yang disimpan lobby
        const ssId   = sessionStorage.getItem('lobby_player_id');
        const ssName = sessionStorage.getItem('lobby_player_name');
        if (ssId) { MY_ID   = ssId; }
        if (ssName){ MY_NAME = ssName; }
    }
    // ───────────────────────────────────────────────────────────

    // Set player info dari matchData
    const players = matchData.players || [];
    const me  = players.find(p => p.id === MY_ID);
    const opp = players.find(p => p.id !== MY_ID);

    // Validasi: pastikan ada lawan yang berbeda
    if (!opp || !me) {
        document.getElementById('connect-screen').querySelector('.connect-title').textContent =
            '❌ Data match tidak valid';
        document.getElementById('connect-screen').querySelector('.connect-sub').textContent =
            'MY_ID=' + MY_ID + ' | Players: ' + players.map(p=>p.id).join(', ') + ' | Kembali ke lobby...';
        setTimeout(() => window.location.href = 'lobby_pvp.php', 3000);
        return;
    }

    // me dan opp sudah divalidasi tidak null di atas
    myHp         = me.hp || HP_MAX;
    opponentId   = opp.id;
    opponentName = opp.name || 'Lawan';
    oppHp        = opp.hp || HP_MAX;

    // Render UI awal
    document.getElementById('p1-name').textContent = MY_NAME;
    document.getElementById('p2-name').textContent = opponentName;
    document.getElementById('p1-id').textContent   = '@' + MY_NAME;
    document.getElementById('p2-id').textContent   = '@' + opponentName;

    // Connect WebSocket — kirim room_id agar server tahu ini rejoin
    connectWS(wsUrl);

    // ── APPLY THEME FROM PREVIOUS PAGE ──
    const savedTheme = localStorage.getItem('rps_theme') || 'dark';
    if (savedTheme === 'light') {
        document.documentElement.setAttribute('data-theme', 'light');
    }
});

// ═══════════════════════════════════════════════════════════
//  WEBSOCKET
// ═══════════════════════════════════════════════════════════
function connectWS(url) {
    ws = new WebSocket(url);

    ws.onopen = () => {
        // Re-auth dengan server — sertakan room_id agar server tahu kita rejoin room
        // MY_ID dan MY_NAME sudah di-resolve dari sessionStorage, bukan PHP session
        const myRating = (matchData && matchData._my_rating) ? matchData._my_rating : 1000;
        wsSend({
            type:        'auth',
            player_id:   MY_ID,
            player_name: MY_NAME,
            rating:      myRating,
            room_id:     roomId      // ← kunci: server akan taruh kita ke room ini
        });
    };

    ws.onmessage = (e) => {
        try {
            const msg = JSON.parse(e.data);
            handleMsg(msg);
        } catch(err) {
            console.error('[WS] Parse error:', err);
        }
    };

    ws.onerror = () => {
        setStatus('❌ Tidak bisa terhubung ke server game!', 'red');
        document.getElementById('connect-screen').querySelector('.connect-title').textContent =
            '❌ Gagal terhubung ke server';
        document.getElementById('connect-screen').querySelector('.connect-sub').textContent =
            'Pastikan server.php sudah dijalankan: php server.php';
    };

    ws.onclose = () => {
        if (!matchOver) {
            setStatus('⚠️ Koneksi terputus.', 'red');
            stopTimer();
        }
    };
}

function wsSend(obj) {
    if (ws && ws.readyState === WebSocket.OPEN) {
        ws.send(JSON.stringify(obj));
    }
}

// ═══════════════════════════════════════════════════════════
//  MESSAGE HANDLER
// ═══════════════════════════════════════════════════════════
function handleMsg(msg) {
    switch (msg.type) {

        case 'auth_ok':
            // Auth + rejoin OK — tampilkan game, tunggu round_start dari server
            showGame();
            setStatus('🟢 Terhubung! Menunggu lawan siap...', 'green');
            break;

        case 'opponent_continue_ready':
            // Lawan sudah klik Lanjutkan — update info di result screen
            setStatus('✅ Lawan sudah siap! Klik LANJUTKAN untuk mulai.', 'green');
            {
                const btn = document.getElementById('btn-continue');
                if (btn && !btn.disabled) {
                    btn.textContent = '▶ LANJUTKAN (Lawan siap!)';
                    btn.style.animation = 'pulse 0.6s ease-in-out 3';
                }
            }
            break;

        case 'continue_countdown': {
            const duration = msg.duration;
            const waitingFor = msg.waiting_for;

            stopContinueTimer();

            let timeRemaining = duration;
            const btn = document.getElementById('btn-continue');

            const updateUI = () => {
                if (waitingFor === MY_ID) {
                    if (btn && !btn.disabled) {
                        btn.textContent = `▶ LANJUTKAN (${timeRemaining}s)`;
                    }
                    setStatus(`🚨 Lawan sudah siap! Klik LANJUTKAN dalam ${timeRemaining} detik atau kalah AFK!`, 'red');
                } else {
                    setStatus(`⏳ Menunggu lawan... (AFK dalam ${timeRemaining} detik)`, 'yellow');
                }
            };

            updateUI();

            window.continueTimerInt = setInterval(() => {
                timeRemaining--;
                if (timeRemaining <= 0) {
                    stopContinueTimer();
                } else {
                    updateUI();
                }
            }, 1000);
            break;
        }

        case 'opponent_card_picked': {
            // Lawan sudah selesai memilih kartu
            oppCardPickDone = true;   // <-- catat bahwa lawan sudah selesai
            const dot   = document.getElementById('cpw-opp-dot');
            const label = document.getElementById('cpw-opp-label');
            if (dot)   { dot.className = 'cpw-opp-dot ready'; }
            if (label) { label.textContent = '✅ Lawan sudah selesai memilih!'; }
            // Jika Block One pending (kita pengaktif, kita sudah selesai pilih juga) — kirim strike
            if (window._blockOneStrikePending && !cardPickPending) {
                window._blockOneStrikePending = false;
                window._blockOneOwner = false;
                wsSend({ type: 'block_one_strike' });
            }
            // Jika player ini JUGA sudah confirm → kedua selesai, lanjut SEKARANG
            if (!cardPickPending && waitingForOpponentCard) {
                clearTimeout(_cardPickWaitingTimeout);
                waitingForOpponentCard = false;
                document.getElementById('card-pick-overlay').classList.remove('show');
                renderCardHand();
                // _pickWatcher akan mendeteksi kondisi dan memanggil _origApplyRoundStart
            }
            break;
        }

        case 'cards_ready': {
            // SERVER: kedua player sudah selesai memilih kartu
            // Batalkan semua fallback timer card pick
            clearTimeout(_cardPickWaitingTimeout);
            clearTimeout(cardPickAutoCloseTimer);   // batalkan auto-close 15s juga
            cardPickAutoCloseTimer = null;
            // Tutup overlay & update state
            waitingForOpponentCard = false;
            cardPickPending = false;
            document.getElementById('card-pick-overlay').classList.remove('show');
            renderCardHand();
            // Jika Block One pending (kita pengaktif, musuh baru saja selesai pilih) — kirim strike sekarang
            if (window._blockOneStrikePending) {
                window._blockOneStrikePending = false;
                window._blockOneOwner = false;
                wsSend({ type: 'block_one_strike' });
            }
            // _pickWatcher akan langsung mendeteksi kondisi dan memanggil _origApplyRoundStart
            break;
        }

        case 'round_start':
        case 'next_turn': {
            stopContinueTimer();
            // Server sudah menunggu kedua player klik Lanjutkan sebelum mengirim ini
            // Paksa reset state kritis agar tidak stuck
            locked         = false;
            fightAnimating = false;
            document.getElementById('result-screen').classList.remove('show');
            document.getElementById('fight-overlay')?.classList.remove('show');
            // Jika ini dari pengulangan Repeat — pastikan HP bar kedua player = 100
            if (msg.from_repeat) {
                myHp  = 100;
                oppHp = 100;
                updateHPBar('p1', 100);
                updateHPBar('p2', 100);
            }
            // Jika ini dari Absolute Reset — reset semua state lokal dulu (hanya jika belum dilakukan via absolute_reset_triggered)
            if (msg.absolute_reset) {
                if (!window._absoluteResetPending) {
                    // Pengirim kartu: reset lokal sudah dilakukan sebelum wsSend, tapi pastikan bersih
                    _doAbsoluteResetLocal(false);
                }
                window._absoluteResetPending = false;
                setStatus('♾️ Absolute Reset! Match kembali ke Ronde 1 Game 1!', 'yellow');
            }
            applyRoundStart(msg);
            break;
        }

        case 'choice_confirmed':
            document.getElementById('p1-chose-badge').classList.add('show');
            setStatus('✅ Pilihan dikirim! Menunggu lawan...', 'green');
            showWaitingScreen();
            break;

        case 'opponent_chose':
            document.getElementById('p2-chose-badge').classList.add('show');
            document.getElementById('waiting-sub').textContent = '✅ Lawan sudah memilih!';
            break;

        case 'round_result':
            stopTimer();
            fightAnimating = true;   // ← blokir timer sampai animasi selesai
            // Block One habis setelah ronde ini selesai — reset agar tidak terbawa ke turn berikutnya
            blockOneActive  = false;
            blockOneAsOwner = false;
            window._blockOneStrikePending = false;
            window._blockOneOwner = false;
            // Hapus chip block_one_pending (lawan tidak punya kartu) — ronde sudah selesai
            activeEffects = activeEffects.filter(e => e.cardId !== 'block_one_pending');
            oppActiveEffects = oppActiveEffects.filter(e => e.effect_id !== 'block_one');
            renderActiveEffects();
            handleRoundResult(msg);
            break;

        case 'new_round': {
            stopContinueTimer();
            // JANGAN update HP bar di sini — biarkan tampilan HP tetap seperti akhir ronde
            // (mis: 0 untuk yang kalah). HP bar baru di-reset ke 100 saat round_start diterima
            // oleh applyRoundStart, yaitu setelah KEDUA player klik Lanjutkan.
            // Variabel internal myHp/oppHp JUGA tidak diubah agar applyRoundStart bisa
            // mendeteksi _justStartedNewRound dan langsung pakai nilai server (100).
            _justStartedNewRound = true; // tandai agar applyRoundStart bypass guard HP stale
            gameNumber++;    // Naik ke game berikutnya
            roundInGame = 0;
            cardPickedThisRound = false;
            drawStreak = 0;
            oppCardPickDone = false;   // reset untuk game baru
            window._lastKnownRound = null; // reset agar ronde pertama game baru terdeteksi
            pendingEffects = []; // Hapus pending efek dari game sebelumnya
            // FIX: Clear kartu hand — kartu ronde lama tidak boleh terbawa ke ronde baru
            myHandCards = [];
            // Pertahankan shield effects yang masih punya HP — hanya hapus efek non-shield
            // God Attack I juga di-expire saat ronde baru (HP reset = sesi baru)
            // Repeat juga di-expire saat ronde baru (pemenang ronde sudah ditentukan)
            // Block One juga di-expire saat ronde baru
            blockOneActive  = false;
            blockOneAsOwner = false;
            window._blockOneStrikePending = false;
            window._blockOneOwner = false;
            activeEffects = activeEffects.filter(e => (e.shield && myShield > 0));
            // Hapus sisa chip block_one_pending jika ada
            activeEffects = activeEffects.filter(e => e.cardId !== 'block_one_pending');
            // FIX: Hanya pertahankan shield lawan yang masih aktif — HAPUS semua efek lain termasuk reverse_result
            oppActiveEffects = oppActiveEffects.filter(e =>
                e.effect_id === 'shield1' || e.effect_id === 'shield2' || e.effect_id === 'shield3' ||
                e.effect_id === 'steal_hp' || e.effect_id === 'steal_hp2'    // steal_hp shield persisten
            );
            // myShield TIDAK di-reset: shield tetap aktif sampai HP shield habis
            _barrierCorrectedOppHp = null;
            // Critical Attack berakhir saat ronde (set) baru dimulai
            criticalAttackActive = false;
            _pendingCritFromOpp = 0;
            // Reset flag kartu 1x per ronde — bisa digunakan lagi di ronde berikutnya
            barrierUsed   = false;
            barrier2Used  = false;
            godAttackUsed = false;
            gambling2Used = false;
            gambling3Used = false;
            blockOneUsed  = false;
            fullDamageUsed = false;
            updateShieldDisplay();
            // Shield lawan: pertahankan jika masih ada (akan di-update via hp_sync dari lawan)
            // Tidak reset oppShield karena kita tidak tahu nilainya, biarkan lawan kirim update
            renderActiveEffects();
            const isP1nr = (msg.p1_id === MY_ID);
            myWins  = isP1nr ? (msg.p1_wins || 0) : (msg.p2_wins || 0);
            oppWins = isP1nr ? (msg.p2_wins || 0) : (msg.p1_wins || 0);
            updateDots();
            // round_start akan datang segera setelah ini dari server
            break;
        }

        case 'match_over': {
            stopContinueTimer();
            if (msg.reason === 'afk' && msg.afk_player === MY_ID) {
                showAfkModal();
                break;
            }
            // Update wins dari server sebelum tampilkan skor (ronde terakhir sudah di-increment)
            const isP1mo = (msg.p1_id === MY_ID);
            if (msg.p1_wins !== undefined && msg.p2_wins !== undefined) {
                myWins  = isP1mo ? (msg.p1_wins || 0) : (msg.p2_wins || 0);
                oppWins = isP1mo ? (msg.p2_wins || 0) : (msg.p1_wins || 0);
            }
            updateDots();
            handleMatchOver(msg);
            break;
        }

        case 'rematch_vote':
            if (msg.player_id !== MY_ID) {
                document.getElementById('rematch-status').textContent =
                    '✅ Lawan mau rematch! Tekan Rematch juga.';
            }
            break;

        case 'rematch_start':
            myHp = oppHp = HP_MAX;
            myWins = oppWins = 0;
            round  = 1;
            matchOver = false;
            locked    = false;
            gameNumber = 1;
            roundInGame = 0;
            activeEffects = [];
            pendingEffects = [];
            oppActiveEffects = [];
            myShield = 0;
            myShieldMax = 30;
            oppShieldMax = 30;
            oppLastChoice = null;
            oppCardPickDone = false;
            cardPickedThisRound = false;
            window._lastKnownRound = null;
            _barrierCorrectedOppHp   = null;
            _pendingReverseFromOpp   = false;
            _pendingReverseFromOppGl = 0;
            _pendingReverseGl        = -1;
            _reverseOppOverrideMyHp  = null;
            _reverseOppOverrideOppHp = null;
            _godAttackOverrideMyHp   = null;
            barrierUsed   = false;
            barrier2Used  = false;
            godAttackUsed = false;
            gambling2Used = false;
            gambling3Used = false;
            blockOneUsed  = false;
            fullDamageUsed = false;
            criticalAttackActive = false;
            _pendingCritFromOpp = 0;
            blockOneActive  = false;
            blockOneAsOwner = false;
            window._blockOneStrikePending = false;
            window._blockOneOwner = false;
            document.getElementById('match-over').classList.remove('show');
            updateHPBar('p1', HP_MAX);
            updateHPBar('p2', HP_MAX);
            updateShieldDisplay();
            updateOppShieldDisplay(0);
            updateDots();
            setStatus('🔄 Rematch dimulai!', 'green');
            break;

        case 'opponent_repeat_active': {
            // Lawan mengaktifkan kartu Repeat — tampilkan chip di sisi p2
            oppActiveEffects = oppActiveEffects.filter(e => e.effect_id !== 'repeat');
            oppActiveEffects.push({ effect_id: 'repeat', label: '🔁 Repeat', rarity: 'rare', gamesLeft: 999 });
            renderActiveEffects();
            showCardToast('🔁 Lawan mengaktifkan Repeat! Jika lawan kalah game ini, game akan diulang.', 'rare');
            setStatus('🔁 Waspada! Lawan punya Repeat — kekalahan lawan akan mengulang game.', 'yellow');
            break;
        }

        case 'game_repeated': {
            stopContinueTimer();
            // Batalkan semua animasi fight overlay yang masih berjalan
            cancelFightOverlay();
            stopTimer();

            const repeatOwnerIsMe = (msg.repeat_owner === MY_ID);

            // Reset HP variabel lokal ke 100
            myHp  = 100;
            oppHp = 100;

            // Update HP bar kedua player ke 100 (full)
            updateHPBar('p1', 100);
            updateHPBar('p2', 100);

            // Hapus chip Repeat dari kedua sisi (kartu habis setelah digunakan sekali)
            activeEffects    = activeEffects.filter(e => e.effect !== 'repeat');
            oppActiveEffects = oppActiveEffects.filter(e => e.effect_id !== 'repeat');
            // Reset Block One juga (game diulang = state bersih)
            blockOneActive  = false;
            blockOneAsOwner = false;
            activeEffects   = activeEffects.filter(e => e.cardId !== 'block_one' && e.cardId !== 'block_one_received');
            oppActiveEffects = oppActiveEffects.filter(e => e.effect_id !== 'block_one');
            drawStreak = 0;
            renderActiveEffects();

            // Bersihkan semua layar — tampilkan result screen repeat dengan bersih
            document.getElementById('fight-overlay')?.classList.remove('show');
            document.getElementById('selection-screen').style.display = 'none';
            document.getElementById('waiting-screen').style.display   = 'none';
            document.getElementById('result-screen').classList.add('show');

            // Tampilkan pesan repeat
            const resElRep = document.getElementById('result-text');
            resElRep.textContent = '🔁 Game Diulang! (Kartu Repeat)';
            resElRep.style.color = 'var(--orange)';

            setStatus(
                repeatOwnerIsMe
                    ? '🔁 Repeat menyelamatkanmu! Game diulang — HP kedua player kembali 100!'
                    : '🔁 Repeat lawan aktif! Game diulang — HP kedua player kembali 100!',
                'yellow'
            );
            showCardToast(
                repeatOwnerIsMe
                    ? '🔁 Repeat berhasil! Game diulang, HP-mu kembali penuh!'
                    : '🔁 Repeat lawan terpicu! Game diulang, HP semua kembali 100!',
                'rare'
            );

            // Tombol Lanjutkan untuk mulai game ulang
            const btnRep = document.getElementById('btn-continue');
            btnRep.disabled    = false;
            btnRep.textContent = 'LANJUTKAN ▶';
            btnRep.onclick = () => {
                btnRep.disabled    = true;
                btnRep.textContent = '⏳ Menunggu lawan...';
                setStatus('⏳ Menunggu lawan klik Lanjutkan...', 'yellow');
                wsSend({ type: 'continue_ready' });
            };
            break;
        }

        case 'opponent_effect_active': {
            // Lawan mengaktifkan efek kartu — tampilkan di sisi p2
            const { effect_id, label, rarity, games_left } = msg;
            // Repeat sudah ditangani via opponent_repeat_active — skip duplikasi
            if (effect_id === 'repeat') break;
            oppActiveEffects = oppActiveEffects.filter(e => e.effect_id !== effect_id);
            oppActiveEffects.push({ effect_id, label, rarity, gamesLeft: games_left });
            renderActiveEffects();
            if (effect_id === 'shield1' || effect_id === 'shield2' || effect_id === 'shield3') {
                // Gunakan shield_hp dari msg jika ada, fallback ke nilai default per jenis
                const shieldDefaults = { shield1: 30, shield2: 60, shield3: 100 };
                const initialHp  = msg.shield_hp  || shieldDefaults[effect_id] || 30;
                const initialMax = msg.shield_max || initialHp;
                // Set oppShieldMax agar bar tampil full 100%
                oppShieldMax = initialMax;
                updateOppShieldDisplay(initialHp, initialMax);
                const shieldNames = { shield1: 'Shield I (+30)', shield2: 'Shield II (+60)', shield3: 'Shield III (+100)' };
                showCardToast(`🛡️ Lawan mengaktifkan ${shieldNames[effect_id] || 'Shield'}! HP mereka dilindungi shield dulu.`, rarity);
                setStatus('🛡️ Lawan punya Shield! Habiskan shield mereka dulu sebelum HP berkurang.', 'blue');
            } else if (effect_id === 'steal_hp') {
                // Steal HP 1: lawan mendapat shield — tampilkan shield bar di sisi p2
                const shieldHpVal  = msg.shield_hp  || 20;
                const shieldMaxVal = msg.shield_max || shieldHpVal;
                oppShieldMax = shieldMaxVal;
                updateOppShieldDisplay(shieldHpVal, shieldMaxVal);
                showCardToast('💉 Lawan menggunakan Steal HP 1! -20 HP-mu → +20 Shield mereka!', rarity);
                setStatus('💉 Lawan punya Shield dari Steal HP 1! Shield mereka aktif.', 'blue');
            } else if (effect_id === 'steal_hp2') {
                // Steal HP 2: lawan mendapat shield 50 — tampilkan shield bar di sisi p2
                const shieldHpVal2  = msg.shield_hp  || 50;
                const shieldMaxVal2 = msg.shield_max || shieldHpVal2;
                oppShieldMax = shieldMaxVal2;
                updateOppShieldDisplay(shieldHpVal2, shieldMaxVal2);
                showCardToast('🩻 Lawan menggunakan Steal HP 2! -50 HP-mu → +50 Shield mereka!', rarity);
                setStatus('🩻 Lawan punya Shield dari Steal HP 2! Shield lebih besar aktif.', 'blue');
            } else if (effect_id === 'gambling1') {
                showCardToast('🎲 Lawan: THE GAMBLING I aktif! Menang +10 dmg · Kalah +10 dmg ke kamu!', rarity);
                setStatus('🎲 Lawan: Gambling I aktif! Taruhan damage dimulai!', 'yellow');
            } else if (effect_id === 'gambling2') {
                showCardToast('🃏 Lawan: THE GAMBLING II aktif! Menang +30 dmg · Kalah +30 dmg ke kamu!', rarity);
                setStatus('🃏 Lawan: Gambling II aktif! Taruhan +30 damage dimulai!', 'yellow');
            } else if (effect_id === 'gambling3') {
                showCardToast('🎰 Lawan: THE GAMBLING III aktif! Menang +50 dmg · Kalah +20 dmg ke kamu!', rarity);
                setStatus('🎰 Lawan: Gambling III aktif! Taruhan besar dimulai!', 'yellow');
            } else if (effect_id === 'god_attack1') {
                showCardToast('⚡ Lawan: God Attack I standby! Serangan pertama mereka jadi 2× damage (5% chance 3×)!', rarity);
                setStatus('⚡ Waspada! God Attack I lawan aktif — menang pertama mereka = 2× damage!', 'yellow');
            } else if (effect_id === 'god_attack2') {
                showCardToast('⚔️ Lawan: God Attack II standby! Serangan pertama mereka jadi 2× damage (20% chance 3×)!', rarity);
                setStatus('⚔️ Waspada! God Attack II lawan aktif — menang pertama mereka = 2× damage!', 'yellow');
            } else if (effect_id === 'god_attack3') {
                showCardToast('💀 Lawan: God Attack III standby! Serangan pertama mereka jadi 2× damage (50% chance 3×)!', rarity);
                setStatus('💀 Waspada! God Attack III lawan aktif — menang pertama mereka = 2× atau 3× damage!', 'yellow');
            } else if (effect_id === 'critical_attack') {
                showCardToast('⚡ Lawan: Critical Attack aktif! 50% chance +30 damage ekstra saat mereka menang!', rarity);
                setStatus('⚡ Waspada! Critical Attack lawan aktif — ada risiko +30 damage ekstra!', 'yellow');
            } else if (effect_id === 'reverse_result') {
                showCardToast(`🔄 Lawan: Reverse Result aktif! Kalah/Seri → Menang. 3 kesempatan, berkurang setiap terpicu.`, rarity);
                setStatus('🔄 Waspada! Lawan punya Reverse Result — kekalahan/seri mereka bisa jadi kemenangan!', 'yellow');
            } else if (effect_id === 'drain_life') {
                showCardToast('🩸 Lawan: Drain Life 1 aktif! Setiap menang mereka +10 HP. Aktif 3 game.', rarity);
                setStatus('🩸 Waspada! Drain Life 1 lawan aktif — mereka pulihkan 10 HP tiap menang!', 'yellow');
            } else if (effect_id === 'drain_life_2') {
                showCardToast('🩸 Lawan: Drain Life 2 aktif! Setiap menang: mereka +25 HP & kamu -10 HP ekstra. Aktif 3 game.', 'epic');
                setStatus('🩸 Waspada! Drain Life 2 lawan aktif — menang mereka = +25 HP bagi mereka & -10 HP ekstra bagimu!', 'yellow');
            } else if (effect_id === 'barrier') {
                showCardToast('🔮 Lawan: Barrier 1 aktif! Kekalahan pertama mereka diserap 50%.', rarity);
                setStatus('🔮 Waspada! Lawan punya Barrier 1 — damage pertama yang mereka terima dikurangi 50%!', 'yellow');
            } else if (effect_id === 'double_damage') {
                showCardToast('🔮 Lawan: Barrier 2 aktif! Kekalahan pertama mereka diserap 75%.', rarity);
                setStatus('🔮 Waspada! Lawan punya Barrier 2 — damage pertama yang mereka terima dikurangi 75%!', 'yellow');
            } else if (effect_id === 'full_damage') {
                showCardToast('💥 Lawan: Full Damage standby! Serangan pertama menang mereka = 5× damage (100 dmg)!', rarity);
                setStatus('💥 Waspada! Full Damage lawan aktif — menang pertama mereka = 100 damage!', 'yellow');
            } else {
                showCardToast(`⚔️ Lawan: ${label} aktif!`, rarity);
            }
            break;
        }

        case 'opponent_block_one': {
            // Lawan mengaktifkan Block One — tampilkan chip di sisi lawan (p2)
            oppActiveEffects = oppActiveEffects.filter(e => e.effect_id !== 'block_one');
            oppActiveEffects.push({ effect_id: 'block_one', label: '🚫 Block One', rarity: 'rare', gamesLeft: 1 });
            renderActiveEffects();
            showCardToast('🚫 Lawan mengaktifkan Block One! 1 kartu randamu akan diblokir!', 'rare');
            setStatus('🚫 Block One! Lawan memblokir 1 kartu randamu sekarang!', 'red');
            break;
        }

        case 'block_one_strike': {
            // Lawan mengaktifkan Block One dan memblok 1 kartu acak dari hand kita
            // myHandCards sudah terisi karena kita sudah konfirmasi kartu dari overlay
            applyBlockOneStrike();
            break;
        }

        case 'block_one_confirmed': {
            // Konfirmasi dari server: Block One kita berhasil terdaftar
            break;
        }

        case 'opponent_hp_sync': {
            // Lawan melakukan perubahan HP — update UI kita sesuai
            // their_hp = HP BARU lawan kita (p2 dari sudut pandang kita)
            // my_hp    = HP kita sendiri yang berubah (mis: steal_hp)
            // SENTINEL: nilai -1 berarti hp_sync ini hanya untuk sync chip/efek — SKIP update HP bar
            const _hpSyncIsChipOnly = (msg.their_hp === -1 && msg.my_hp === -1);
            const newOppHp = _hpSyncIsChipOnly ? oppHp : Math.max(0, Math.min(200, msg.their_hp ?? oppHp));  // allow >100 for steal_hp2
            const prevOppHp = oppHp;

            if (!_hpSyncIsChipOnly && newOppHp !== oppHp) {
                oppHp = newOppHp;
                updateHPBar('p2', oppHp);
                // flash damage selalu untuk p2 (HP lawan berkurang karena steal)
                if (newOppHp > prevOppHp) {
                    flashHeal('p2-hp-bar');
                } else {
                    flashDamage('p2-hp-bar');
                }
            }

            // ── Shield absorb untuk God Attack: terapkan damage penuh sebelum my_hp diproses ──
            // FIX: Setiap god attack mengirim wsSend terpisah dengan total oppDmg aktual.
            // Ambil nilai terbesar dari ketiga field (hanya 1 yang non-zero per pesan)
            // karena setiap wsSend hanya membawa 1 field aktif, menjumlahkan ketiganya aman.
            const _godActualDmg = Math.max(
                msg.god_attack_actual_dmg || 0,
                msg.god_atk2_actual_dmg   || 0,
                msg.god_atk3_actual_dmg   || 0
            );
            if (_godActualDmg > 0) {
                // Hitung HP kita setelah shield menyerap god_attack.
                // Simpan hasilnya di _godAttackOverrideMyHp agar Phase 4 (5 detik kemudian)
                // menggunakan nilai ini, bukan nilai server yang hanya -20.
                // Penting: JANGAN set myHp di sini — Phase 4 yang akan set myHp final.
                const _currentHp = myHp;  // HP sebelum damage ronde ini diterapkan Phase 4
                if (myShield > 0) {
                    const _absorbed = Math.min(myShield, _godActualDmg);
                    myShield -= _absorbed;
                    updateShieldDisplay();
                    const _fillEl = document.getElementById('p1-shield-fill');
                    if (_fillEl) {
                        _fillEl.classList.remove('absorbing'); void _fillEl.offsetWidth;
                        _fillEl.classList.add('absorbing');
                        setTimeout(() => _fillEl.classList.remove('absorbing'), 600);
                    }
                    spawnDamageFloat(document.querySelector('.battle-area'), `🛡️-${_absorbed}`, 'dmg-heal');
                    showCardToast(`🛡️ Shield menyerap ${_absorbed} dari God Attack! Sisa Shield: ${myShield}`, 'common');
                    if (myShield <= 0) {
                        activeEffects = activeEffects.filter(e => !e.shield);
                        renderActiveEffects();
                        showCardToast('🛡️ Shield habis!', 'common');
                    }
                    const _remainDmg = Math.max(0, _godActualDmg - _absorbed);
                    // Simpan HP override: HP sekarang dikurangi sisa damage setelah shield
                    _godAttackOverrideMyHp = Math.max(0, _currentHp - _remainDmg);
                    wsSend({ type:'hp_sync', my_hp: _godAttackOverrideMyHp, their_hp: oppHp, shield_hp: myShield, shield_max: myShieldMax, shield_broke: myShield <= 0 });
                } else {
                    // Tidak ada shield — seluruh god_attack damage ke HP
                    _godAttackOverrideMyHp = Math.max(0, _currentHp - _godActualDmg);
                }
            }

            // Perbarui HP kita sendiri jika berubah karena kartu lawan
            // HANYA untuk hp_sync yang memang mempengaruhi HP kita:
            // steal_hp, tie_breaker, reverse_result, safe_play, god_attack, drain_life_2
            // Abaikan hp_sync chip-sync biasa (critical, drain_life, gambling, barrier, dll)
            // yang membawa my_hp = HP pengirim sendiri (bukan HP kita)
            const _affectsMyHp = msg.tie_breaker_triggered
                               || msg.reverse_result_triggered
                               || msg.steal_shield
                               || (msg.safe_play_games_left  !== undefined)
                               || (msg.safe_play2_games_left !== undefined)
                               || (msg.dmg_amount > 0 && !msg.drain_life_games_left && !msg.critical_games_left)
                               || msg.steal_shield_hp;
            if (!_hpSyncIsChipOnly && _affectsMyHp && msg.my_hp !== null && msg.my_hp !== undefined
                && !_godActualDmg) {  // Abaikan my_hp dari god_attack hp_sync — sudah ditangani di atas
                let newMyHp  = Math.max(0, Math.min(200, msg.my_hp));  // allow >100 for steal_hp2
                const prevMyHp = myHp;
                if (newMyHp !== myHp) {
                    // Jika HP kita berkurang (damage dari kartu lawan), routing melalui shield
                    // Shield harus menyerap seluruh delta damage sebelum HP asli berkurang.
                    if (newMyHp < prevMyHp && myShield > 0
                        && !msg.steal_shield && !msg.reverse_result_triggered && !msg.tie_breaker_triggered) {
                        const deltaDmg = prevMyHp - newMyHp;
                        const absorbed = Math.min(myShield, deltaDmg);
                        myShield = Math.max(0, myShield - absorbed);
                        const remainAfterShield = Math.max(0, deltaDmg - absorbed);
                        newMyHp = Math.max(0, prevMyHp - remainAfterShield); // HP setelah shield
                        updateShieldDisplay();
                        const _sFill = document.getElementById('p1-shield-fill');
                        if (_sFill) { _sFill.classList.remove('absorbing'); void _sFill.offsetWidth; _sFill.classList.add('absorbing'); setTimeout(()=>_sFill.classList.remove('absorbing'),600); }
                        const _sBa = document.querySelector('.battle-area');
                        if (_sBa) spawnDamageFloat(_sBa, '🛡️-'+absorbed, 'dmg-heal');
                        const _shMsg = myShield > 0
                            ? '🛡️ Shield menyerap ' + absorbed + ' damage! Sisa Shield: ' + myShield
                            : '🛡️ Shield menyerap ' + absorbed + ' damage — Shield habis!';
                        showCardToast(_shMsg, 'common');
                        if (myShield <= 0) {
                            activeEffects = activeEffects.filter(e => !e.shield);
                            renderActiveEffects();
                        }
                        wsSend({ type:'hp_sync', my_hp:newMyHp, their_hp:oppHp, shield_hp:myShield, shield_max:myShieldMax, shield_broke:myShield<=0 });
                    }
                    myHp = newMyHp;
                    updateHPBar('p1', myHp);
                    if (newMyHp < prevMyHp) {
                        const dmgRecv = prevMyHp - newMyHp;
                        // Jika reverse_result: damage float & flash ditangani di blok reverse_result di bawah
                        if (!msg.reverse_result_triggered) {
                            flashDamage('p1-hp-bar');
                            spawnDamageFloat(document.querySelector('.battle-area'), '-' + dmgRecv, 'dmg-p1');
                        }
                        // Label berbeda untuk masing-masing sumber damage
                        if (msg.tie_breaker_triggered) {
                            showCardToast('⚖️ Tie Breaker lawan terpicu! Seri → mereka menang, -' + dmgRecv + ' HP!', 'common');
                            // Update result screen: ganti SERI → teks kalah sesuai format baru
                            const resEl = document.getElementById('result-text');
                            if (resEl) {
                                resEl.textContent = `❌ Anda kalah dari ${opponentName} di ronde ini (Tie Breaker ⚖️)`;
                                resEl.style.color  = 'var(--red)';
                            }
                            // Hapus chip ⚖️ dari display lawan
                            oppActiveEffects = oppActiveEffects.filter(e => e.effect_id !== 'tie_breaker');
                            renderActiveEffects();
                        } else if (msg.critical_attack_dmg && msg.critical_attack_dmg > 0) {
                            // Critical Attack: ditangani di blok critical_attack
                        } else if (msg.gambling_extra_dmg && msg.gambling_extra_dmg !== 0) {
                            const extraAbs = Math.abs(msg.gambling_extra_dmg);
                            const gambIcon = extraAbs >= 30 ? '🃏' : '🎲';
                            showCardToast(gambIcon + ' Gambling lawan! Kamu kalah dan menerima +' + extraAbs + ' damage ekstra!', 'rare');
                        } else if (msg.steal_shield && msg.steal_shield_hp === 50) {
                            showCardToast('🩻 Lawan menggunakan Steal HP 2! -' + dmgRecv + ' HP-mu → +50 Shield mereka!', 'epic');
                        } else if (msg.steal_shield) {
                            showCardToast('💉 Lawan mencuri ' + dmgRecv + ' HP-mu dan mengubahnya jadi Shield mereka!', 'rare');
                        }
                        // Catatan: HP berkurang dari full_damage/efek lain tidak perlu toast di sini
                        // karena sudah ditangani di blok full_damage_games_left di bawah
                    }
                }
            }

            // ── Drain Life heal di sisi lawan — visual + toast ──
            if ((msg.heal_amount ?? 0) > 0) {
                const battleArea = document.querySelector('.battle-area');
                flashHeal('p2-hp-bar');
                spawnHealFloat(battleArea, '+' + msg.heal_amount + ' 🩸');
                const drainLabel = msg.heal_amount >= 25 ? 'Drain Life 2' : 'Drain Life 1';
                showCardToast('🩸 Lawan memulihkan ' + msg.heal_amount + ' HP! (' + drainLabel + ')', 'common');
            }

            // ── Effect Card damage ke kita — toast info ──
            if ((msg.dmg_amount ?? 0) > 0 && (msg.heal_amount ?? 0) === 0) {
                showCardToast('⚡ Efek kartu lawan (Critical Attack): -' + msg.dmg_amount + ' HP!', 'common');
            }

            // ── Sinkronisasi chip Gambling lawan — persis seperti pola drain_life ──
            if (msg.gambling_games_left !== undefined) {
                const gl = msg.gambling_games_left;
                if (gl > 0) {
                    oppActiveEffects = oppActiveEffects.map(e =>
                        (e.effect_id === 'gambling1' || e.effect_id === 'gambling2' || e.effect_id === 'gambling3')
                            ? { ...e, gamesLeft: gl } : e
                    );
                } else if (gl === 0) {
                    const hadG3 = oppActiveEffects.some(e => e.effect_id === 'gambling3');
                    const hadG2 = oppActiveEffects.some(e => e.effect_id === 'gambling2');
                    const hadGambling = oppActiveEffects.some(e => e.effect_id === 'gambling1' || e.effect_id === 'gambling2' || e.effect_id === 'gambling3');
                    if (hadGambling) {
                        oppActiveEffects = oppActiveEffects.filter(e => e.effect_id !== 'gambling1' && e.effect_id !== 'gambling2' && e.effect_id !== 'gambling3');
                        showCardToast(hadG3 ? '🎰 Gambling III lawan — efek berakhir' : hadG2 ? '🃏 Gambling II lawan — efek berakhir' : '🎲 Gambling lawan — efek berakhir', hadG3 ? 'epic' : 'rare');
                    }
                }
                // ── Tampilkan efek gambling extra damage secara visual ──
                // Deteksi jenis kartu berdasarkan magnitude unik:
                // |10| = Gambling I, |30| = Gambling II, |50| atau |20| = Gambling III
                const extraDmg = msg.gambling_extra_dmg ?? 0;
                if (extraDmg > 0) {
                    // Lawan menang dengan gambling → HP kita berkurang ekstra
                    // CATATAN: damage gambling dari lawan sudah termasuk dalam their_hp yang diterima
                    // dari hp_sync penyerang (their_hp = HP kita yang baru setelah dikurangi lawan).
                    // Shield sudah dihandle di applyActiveEffectsToResult sisi lawan (myDmgOut).
                    // Di sini kita hanya tampilkan notifikasi visual saja — HP sudah diupdate via _affectsMyHp.
                    const absExtra = extraDmg;
                    const isG3 = absExtra === 50;
                    const isG2 = absExtra === 30;
                    const gambIcon = isG3 ? '🎰' : (isG2 ? '🃏' : '🎲');
                    setStatus(gambIcon + ' Gambling lawan! Kamu terkena +' + extraDmg + ' damage ekstra!', 'red');
                    const battleArea = document.querySelector('.battle-area');
                    if (battleArea) spawnDamageFloat(battleArea, '-' + extraDmg + ' ' + gambIcon, 'dmg-p1');
                } else if (extraDmg < 0) {
                    // Lawan kalah dengan gambling → HP lawan berkurang ekstra
                    const absExtra = Math.abs(extraDmg);
                    const isG3 = absExtra === 20;
                    const isG2 = absExtra === 30;
                    const gambIcon = isG3 ? '🎰' : (isG2 ? '🃏' : '🎲');
                    setStatus(gambIcon + ' Gambling lawan terkena +' + absExtra + ' damage ekstra!', 'green');
                    const battleArea = document.querySelector('.battle-area');
                    if (battleArea) spawnDamageFloat(battleArea, '-' + absExtra + ' ' + gambIcon, 'dmg-p2');
                }
                // Gambling positif (extraDmg > 0): lawan menang, kita kena damage.
                // Shield sudah ditangani di applyActiveEffectsToResult melalui myDmgOut.
                // Gambling negatif (extraDmg < 0): lawan kalah, lawan sendiri kena damage.
                // CATATAN: gambling positif yang dikirim via opponent_hp_sync (my_hp berubah)
                // sudah melewati blok _affectsMyHp di atas — shield lawan dihandle di sisi mereka.
                renderActiveEffects();
            }

            // ── Sinkronisasi chip Drain Life 1 & Drain Life 2 lawan ──
            if (msg.drain_life_games_left !== undefined) {
                const gl = msg.drain_life_games_left;
                if (gl > 0) {
                    // Update gamesLeft chip lawan ke nilai yang akurat
                    oppActiveEffects = oppActiveEffects.map(e =>
                        (e.effect_id === 'drain_life' || e.effect_id === 'drain_life_2') ? { ...e, gamesLeft: gl } : e
                    );
                } else if (gl === 0) {
                    // Baru habis — hapus chip lawan (drain_life_1)
                    const hadDrain1 = oppActiveEffects.some(e => e.effect_id === 'drain_life');
                    if (hadDrain1) {
                        oppActiveEffects = oppActiveEffects.filter(e => e.effect_id !== 'drain_life');
                        showCardToast('🩸 Efek Drain Life 1 lawan berakhir!', 'common');
                    }
                    // Hapus chip lawan (drain_life_2)
                    const hadDrain2 = oppActiveEffects.some(e => e.effect_id === 'drain_life_2');
                    if (hadDrain2) {
                        oppActiveEffects = oppActiveEffects.filter(e => e.effect_id !== 'drain_life_2');
                        showCardToast('🩸 Efek Drain Life 2 lawan berakhir!', 'epic');
                    }
                }
                renderActiveEffects();
            }

            // ── Sinkronisasi Barrier 1 & Barrier 2 lawan: HP + chip ──
            if (msg.barrier_broke !== undefined || msg.barrier_active !== undefined) {
                // msg.their_hp = HP pemilik barrier (lawan kita = p2), sudah dikurangi di sisi mereka
                if (msg.their_hp !== undefined) {
                    const corrected = Math.max(0, Math.min(100, msg.their_hp));
                    // Simpan selalu — Phase-4 akan pakai ini untuk menimpa nilai server yang salah
                    _barrierCorrectedOppHp = corrected;
                    // Langsung update bar dan variabel global
                    const prev = oppHp;
                    oppHp = corrected;
                    updateHPBar('p2', oppHp);
                    if (oppHp < prev) flashDamage('p2-hp-bar');
                }

                if (msg.barrier_broke) {
                    // Hapus barrier1 dan barrier2 (double_damage) dari chip lawan
                    oppActiveEffects = oppActiveEffects.filter(e => e.effect_id !== 'barrier' && e.effect_id !== 'double_damage');
                    // Tentukan teks toast berdasarkan kartu mana yang hancur
                    const wasBarrier2 = msg.barrier2_broke === true;
                    showCardToast(wasBarrier2
                        ? '🔮 Barrier 2 lawan hancur! Damage dikurangi 75%.'
                        : '🔮 Barrier 1 lawan hancur! Damage dikurangi 50%.', wasBarrier2 ? 'epic' : 'common');
                    renderActiveEffects();
                    document.querySelectorAll('.effect-chip').forEach(chip => {
                        if (chip.textContent.includes('Barrier')) chip.remove();
                    });
                } else if (msg.barrier_active) {
                    if (!oppActiveEffects.some(e => e.effect_id === 'barrier')) {
                        oppActiveEffects.push({ effect_id: 'barrier', label: '🔮 Barrier 1 🛡', rarity: 'common', gamesLeft: 999 });
                        renderActiveEffects();
                    }
                }
            }

            // ── Safe Play: koreksi HP kita ke nilai yang sudah dimodifikasi lawan ──
            if (msg.safe_play_games_left !== undefined) {
                const gl          = msg.safe_play_games_left;
                const battleArea  = document.querySelector('.battle-area');

                // msg.my_hp = HP kita yang seharusnya (dihitung lawan setelah safe_play)
                if (msg.my_hp !== null && msg.my_hp !== undefined) {
                    const corrected = Math.max(0, Math.min(100, msg.my_hp));
                    if (corrected !== myHp) {
                        const diff = corrected - myHp;   // positif = lebih sehat dari perkiraan
                        myHp = corrected;
                        updateHPBar('p1', myHp);
                        if (diff > 0) {
                            // Damage yang diterima lebih kecil dari perkiraan → visual shield absorb
                            flashHeal('p1-hp-bar');
                            spawnHealFloat(battleArea, '🛡 +' + diff);
                        } else if (diff < 0) {
                            flashDamage('p1-hp-bar');
                            spawnDamageFloat(battleArea, '-' + Math.abs(diff), 'dmg-p1');
                        }
                        showCardToast('🛡 Safe Play lawan aktif! Damage dikurangi.', 'common');
                    }
                }

                // Chip sync safe_play lawan
                if (gl > 0) {
                    oppActiveEffects = oppActiveEffects.map(e =>
                        e.effect_id === 'safe_play1' ? { ...e, gamesLeft: gl } : e
                    );
                } else {
                    oppActiveEffects = oppActiveEffects.filter(e => e.effect_id !== 'safe_play1');
                }
                renderActiveEffects();
            }

            // ── Safe Play II: koreksi HP kita ke nilai yang sudah dimodifikasi lawan ──
            if (msg.safe_play2_games_left !== undefined) {
                const gl         = msg.safe_play2_games_left;
                const battleArea = document.querySelector('.battle-area');

                // Koreksi HP kita jika lawan memakai Safe Play II (kalah = 0 dmg ke kita)
                if (msg.my_hp !== null && msg.my_hp !== undefined) {
                    const corrected = Math.max(0, Math.min(100, msg.my_hp));
                    if (corrected !== myHp) {
                        const diff = corrected - myHp;
                        myHp = corrected;
                        updateHPBar('p1', myHp);
                        if (diff > 0) {
                            flashHeal('p1-hp-bar');
                            spawnHealFloat(battleArea, '🛡 +' + diff);
                        } else if (diff < 0) {
                            flashDamage('p1-hp-bar');
                            spawnDamageFloat(battleArea, '-' + Math.abs(diff), 'dmg-p1');
                        }
                        showCardToast('🛡 Safe Play II lawan aktif! Kamu tidak menerima damage saat kalah.', 'rare');
                    }
                }

                // Chip sync safe_play2 lawan
                if (gl > 0) {
                    if (!oppActiveEffects.some(e => e.effect_id === 'safe_play2')) {
                        oppActiveEffects.push({ effect_id: 'safe_play2', label: '🛡 Safe Play II', rarity: 'rare', gamesLeft: gl });
                    } else {
                        oppActiveEffects = oppActiveEffects.map(e =>
                            e.effect_id === 'safe_play2' ? { ...e, gamesLeft: gl } : e
                        );
                    }
                } else {
                    oppActiveEffects = oppActiveEffects.filter(e => e.effect_id !== 'safe_play2');
                }
                renderActiveEffects();
            }
            // ── Sinkronisasi chip God Attack I lawan ──
            if (msg.god_attack_games_left !== undefined) {
                const gl = msg.god_attack_games_left;
                if (gl > 0) {
                    // Masih standby — pastikan chip tampil di lawan
                    if (!oppActiveEffects.some(e => e.effect_id === 'god_attack1')) {
                        oppActiveEffects.push({ effect_id: 'god_attack1', label: '⚡ God Atk I', rarity: 'common', gamesLeft: 999 });
                    }
                } else if (gl === 0) {
                    // Baru saja terpicu (menang) — hapus chip dari KEDUA sisi (pemilik & penerima)
                    const hadGodAtk = oppActiveEffects.some(e => e.effect_id === 'god_attack1');
                    // SELALU hapus dari oppActiveEffects meskipun hadGodAtk false (race condition fix)
                    oppActiveEffects = oppActiveEffects.filter(e => e.effect_id !== 'god_attack1');
                    // Hapus juga dari activeEffects sisi penerima (jika terdaftar sebagai efek kita sendiri)
                    activeEffects = activeEffects.filter(e => e.effect !== 'god_attack1');
                    if (hadGodAtk) {
                        const multi = msg.god_attack_multiplier ?? 1;
                        if (multi === 3) {
                            showCardToast('⚡🍀 God Attack I lawan LUCKY — serangan 3× damage! Efek berakhir.', 'common');
                        } else {
                            showCardToast('⚡ God Attack I lawan aktif — serangan 2× damage ke kamu! Efek berakhir.', 'common');
                        }
                    }
                }
                renderActiveEffects();
            }

            // ── Sinkronisasi chip God Attack II lawan ──
            if (msg.god_atk2_games_left !== undefined) {
                const gl2 = msg.god_atk2_games_left;
                if (gl2 > 0) {
                    if (!oppActiveEffects.some(e => e.effect_id === 'god_attack2')) {
                        oppActiveEffects.push({ effect_id: 'god_attack2', label: '⚔️ God Atk II', rarity: 'rare', gamesLeft: 999 });
                    }
                } else if (gl2 === 0) {
                    // Baru saja terpicu (menang) — hapus chip dari KEDUA sisi (pemilik & penerima)
                    const hadGodAtk2 = oppActiveEffects.some(e => e.effect_id === 'god_attack2');
                    // SELALU hapus dari oppActiveEffects meskipun hadGodAtk2 false (race condition fix)
                    oppActiveEffects = oppActiveEffects.filter(e => e.effect_id !== 'god_attack2');
                    // Hapus juga dari activeEffects sisi penerima (jika terdaftar sebagai efek kita sendiri)
                    activeEffects = activeEffects.filter(e => e.effect !== 'god_attack2');
                    if (hadGodAtk2) {
                        const multi2 = msg.god_atk2_multiplier ?? 1;
                        if (multi2 === 3) {
                            showCardToast('⚔️🍀 God Attack II lawan LUCKY — serangan 3× damage! Efek berakhir.', 'rare');
                        } else {
                            showCardToast('⚔️ God Attack II lawan aktif — serangan 2× damage ke kamu! Efek berakhir.', 'rare');
                        }
                    }
                }
                renderActiveEffects();
            }

            // ── Sinkronisasi chip God Attack III lawan ──
            if (msg.god_atk3_games_left !== undefined) {
                const gl3 = msg.god_atk3_games_left;
                if (gl3 > 0) {
                    if (!oppActiveEffects.some(e => e.effect_id === 'god_attack3')) {
                        oppActiveEffects.push({ effect_id: 'god_attack3', label: '💀 God Atk III', rarity: 'epic', gamesLeft: 999 });
                    }
                } else if (gl3 === 0) {
                    // Baru saja terpicu (menang) — hapus chip dari KEDUA sisi (pemilik & penerima)
                    const hadGodAtk3 = oppActiveEffects.some(e => e.effect_id === 'god_attack3');
                    // SELALU hapus dari oppActiveEffects meskipun hadGodAtk3 false (race condition fix)
                    oppActiveEffects = oppActiveEffects.filter(e => e.effect_id !== 'god_attack3');
                    // Hapus juga dari activeEffects sisi penerima (jika terdaftar sebagai efek kita sendiri)
                    activeEffects = activeEffects.filter(e => e.effect !== 'god_attack3');
                    if (hadGodAtk3) {
                        const multi3 = msg.god_atk3_multiplier ?? 1;
                        if (multi3 === 3) {
                            showCardToast('💀🍀 God Attack III lawan LUCKY — serangan 3× damage! Efek berakhir.', 'epic');
                        } else {
                            showCardToast('💀 God Attack III lawan aktif — serangan 2× damage ke kamu! Efek berakhir.', 'epic');
                        }
                    }
                }
                renderActiveEffects();
            }

            // ── Sinkronisasi chip Full Damage lawan ──
            if (msg.full_damage_games_left !== undefined) {
                const glFD = msg.full_damage_games_left;
                if (glFD > 0) {
                    if (!oppActiveEffects.some(e => e.effect_id === 'full_damage')) {
                        oppActiveEffects.push({ effect_id: 'full_damage', label: '💥 Full Damage', rarity: 'legend', gamesLeft: 999 });
                    }
                } else if (glFD === 0) {
                    const hadFD = oppActiveEffects.some(e => e.effect_id === 'full_damage');
                    oppActiveEffects = oppActiveEffects.filter(e => e.effect_id !== 'full_damage');
                    activeEffects    = activeEffects.filter(e => e.effect !== 'full_damage');
                    if (hadFD) {
                        showCardToast('💥 Anda terkena ×5 damage dari kartu Full Damage lawan! (100 damage)', 'legend');
                    }
                }
                renderActiveEffects();
            }

            // ── Sinkronisasi Shield lawan — render shield bar di sisi p2 ──
            if (msg.shield_hp !== undefined) {
                updateOppShieldDisplay(msg.shield_hp, msg.shield_max ?? null);
                // KRUSIAL: update oppHp ke nilai benar dari lawan (HP lawan tidak berkurang karena shield)
                // their_hp dari lawan = HP lawan yang sudah diperhitungkan shield absorb
                if (msg.their_hp !== undefined) {
                    const correctedOppHp = Math.max(0, Math.min(100, msg.their_hp));
                    if (correctedOppHp !== oppHp) {
                        oppHp = correctedOppHp;
                        updateHPBar('p2', oppHp);
                    }
                }
                if (msg.shield_broke) {
                    // Hapus semua chip shield dari efek lawan (shield1/2/3, steal_hp, steal_hp2)
                    oppActiveEffects = oppActiveEffects.filter(e =>
                        e.effect_id !== 'shield1' && e.effect_id !== 'shield2'
                        && e.effect_id !== 'shield3' && e.effect_id !== 'steal_hp'
                        && e.effect_id !== 'steal_hp2'
                    );
                    showCardToast('🛡️ Shield lawan habis! HP lawan tidak lagi terlindungi.', 'common');
                    renderActiveEffects();
                }
            }

            // ── Sinkronisasi Steal HP 1 / Steal HP 2 (Shield) dari lawan — tampilkan chip di sisi p2 ──
            if (msg.steal_shield && msg.shield_hp !== undefined) {
                // Pass shield_max agar oppShieldMax di-set benar -> bar tampil full
                updateOppShieldDisplay(msg.shield_hp, msg.shield_max ?? null);
                // Bedakan Steal HP 1 (20) vs Steal HP 2 (50) berdasarkan steal_shield_hp
                const isSteal2 = (msg.steal_shield_hp ?? 0) >= 50;
                if (isSteal2) {
                    // Steal HP 2 — hapus chip steal_hp1 jika ada, tambah chip steal_hp2
                    oppActiveEffects = oppActiveEffects.filter(e => e.effect_id !== 'steal_hp' && e.effect_id !== 'steal_hp2');
                    oppActiveEffects.push({ effect_id: 'steal_hp2', label: '🩻 Steal HP 2 Shield', rarity: 'epic', gamesLeft: 999 });
                } else {
                    // Steal HP 1 — tambah chip steal_hp jika belum ada
                    if (!oppActiveEffects.some(e => e.effect_id === 'steal_hp')) {
                        oppActiveEffects.push({ effect_id: 'steal_hp', label: '💉 Steal HP 1 Shield', rarity: 'rare', gamesLeft: 999 });
                    }
                }
                renderActiveEffects();
            }

            // ── Sinkronisasi Critical Attack dari lawan ──
            if (msg.critical_attack_dmg != null && msg.critical_attack_dmg > 0) {
                _pendingCritFromOpp = msg.critical_attack_dmg;
                if (!cardPickPending && !waitingForOpponentCard && !fightAnimating) {
                    _applyPendingCritFromOpp();
                }
                // Jika critical_games_left=0 dikirim bersama (crit berhasil = efek langsung habis),
                // jangan push chip baru -- biarkan blok critical_games_left=0 di bawah yang hapus
                if ((msg.critical_games_left ?? 2) > 0) {
                    if (!oppActiveEffects.some(e => e.effect_id === 'critical_attack')) {
                        oppActiveEffects.push({ effect_id: 'critical_attack', label: '⚡ Critical Atk', rarity: 'common', gamesLeft: msg.critical_games_left ?? 2 });
                        renderActiveEffects();
                    }
                }
            }
            // critical_games_left: -1 = tidak aktif, 0 = habis, 1/2 = sisa game
            if (msg.critical_games_left !== undefined && msg.critical_games_left !== -1) {
                const cgl = msg.critical_games_left;
                if (cgl > 0) {
                    // Sinkronkan angka gamesLeft chip lawan
                    oppActiveEffects = oppActiveEffects.map(e =>
                        e.effect_id === 'critical_attack' ? { ...e, gamesLeft: cgl } : e
                    );
                } else if (cgl === 0) {
                    // cgl = 0: efek habis -- hapus chip lawan SEKARANG dari kedua sisi
                    const hadCrit = oppActiveEffects.some(e => e.effect_id === 'critical_attack');
                    oppActiveEffects = oppActiveEffects.filter(e => e.effect_id !== 'critical_attack');
                    if (hadCrit) showCardToast('⚡ Critical Attack lawan -- efek berakhir.', 'common');
                }
                renderActiveEffects();
            }

            // ── Sinkronisasi Reverse Result dari lawan ──
            if (msg.reverse_result_triggered) {
                const gl = msg.reverse_result_games_left ?? 0;

                // Hitung HP yang benar dari nilai sync:
                // my_hp (server relay) = HP penerima setelah kena 20 damage
                // their_hp (server relay) = HP pengirim (tidak berubah)
                const correctMyHp  = Math.max(0, Math.min(100, msg.my_hp  ?? myHp));
                const correctOppHp = Math.max(0, Math.min(100, msg.their_hp ?? oppHp));

                // Simpan nilai benar ke override — Phase 4 akan pakai ini
                _pendingReverseFromOpp   = true;
                _pendingReverseFromOppGl = gl;
                _reverseOppOverrideMyHp  = correctMyHp;
                _reverseOppOverrideOppHp = correctOppHp;
                _pendingReverseGl        = gl;

                showCardToast(`🔄 Lawan membalik hasil dengan Reverse Result! (${gl} tersisa)`, 'epic');
                setStatus('🔄 Reverse Result lawan terpicu! Hasil ronde ini: KAMU KALAH.', 'red');

                // Paksa update HP global dan bar — tanpa syarat (override nilai stale dari server)
                myHp  = correctMyHp;
                oppHp = correctOppHp;
                updateHPBar('p1', myHp);
                updateHPBar('p2', oppHp);

                // Override disimpan — Phase 4 fight overlay (5000ms) akan membacanya
                // dan menerapkan sekali secara authoritative. Tidak perlu polling/delay tambahan
                // karena Phase 4 SELALU membaca _reverseOppOverrideMyHp sebelum set myHp.

                // ── Tampilkan damage float ke HP kita (penerima kena 20 damage) ──
                flashDamage('p1-hp-bar');
                spawnDamageFloat(document.querySelector('.battle-area'), '-20 🔄', 'dmg-p1');

                // ── Paksa update FIGHT OVERLAY (jika masih tampil) ──
                const fightOverlay = document.getElementById('fight-overlay');
                if (fightOverlay && fightOverlay.classList.contains('show')) {
                    const fightResText = document.getElementById('fight-result-text');
                    if (fightResText) {
                        fightResText.textContent = `💀 REVERSE RESULT! ${opponentName} membalik hasil!`;
                        fightResText.style.color = 'var(--red)';
                        fightResText.classList.add('show');
                    }
                    const fightResDet = document.getElementById('fight-winner-detail');
                    if (fightResDet) {
                        fightResDet.textContent = `🔄 Kekalahan kamu dibalik oleh ${opponentName} · kamu -20 HP`;
                        fightResDet.classList.add('show');
                    }
                    const fw1 = document.getElementById('fight-weapon-p1');
                    const fw2 = document.getElementById('fight-weapon-p2');
                    if (fw1) { fw1.classList.remove('win','draw'); fw1.classList.add('lose'); }
                    if (fw2) { fw2.classList.remove('lose','draw'); fw2.classList.add('win'); }
                }

                // ── Paksa update RESULT SCREEN → KALAH ──
                // Gunakan delay agar bisa menimpa SETELAH Phase 4 fight overlay selesai render
                const _setLoseScreen = () => {
                    const resElNow = document.getElementById('result-text');
                    if (resElNow && document.getElementById('result-screen').classList.contains('show')) {
                        resElNow.textContent = `❌ Anda kalah! Lawan membalik hasil dengan Reverse Result 🔄`;
                        resElNow.style.color = 'var(--red)';
                    }
                };
                // Jalankan segera dan lagi setelah Phase 4 fight overlay kita selesai (5200ms)
                // hp_sync sekarang dikirim awal (sebelum fight overlay pengirim) jadi
                // override sudah tersimpan sebelum Phase 4 kita berjalan
                _setLoseScreen();
                setTimeout(_setLoseScreen, 5200); // setelah fight overlay kita (5s) selesai render result screen

                // Update chip lawan dengan sisa charges yang benar
                if (gl > 0) {
                    // Perbarui gamesLeft chip lawan ke nilai authoritative dari pengirim
                    const existing = oppActiveEffects.find(e => e.effect_id === 'reverse_result');
                    if (existing) {
                        existing.gamesLeft = gl;
                        oppActiveEffects = [...oppActiveEffects]; // trigger re-ref
                    } else {
                        oppActiveEffects.push({ effect_id: 'reverse_result', label: '🔄 Reverse', rarity: 'epic', gamesLeft: gl });
                    }
                } else {
                    const hadRR = oppActiveEffects.some(e => e.effect_id === 'reverse_result');
                    if (hadRR) {
                        oppActiveEffects = oppActiveEffects.filter(e => e.effect_id !== 'reverse_result');
                        showCardToast('🔄 Reverse Result lawan — efek habis!', 'epic');
                    }
                }
                // FIX: Update HP bar SETELAH chip diupdate agar DOM order konsisten
                // (shield section → effects section harus urut di bawah HP bar)
                updateHPBar('p1', myHp);
                updateHPBar('p2', oppHp);
                renderActiveEffects();
            }

            break;
        }

        case 'error':
            setStatus('❌ ' + (msg.msg || 'Error tidak diketahui'), 'red');
            break;

        // ── Handler baru: relay kartu dari server (hasil fix S5) ──
        case 'opponent_card_trap': {
            // Lawan pakai Trap Card — hapus SEMUA efek aktif kita (chip + state)
            activeEffects = [];
            pendingEffects = [];
            renderActiveEffects();
            showCardToast('🕳 TRAP! Lawan membatalkan semua kartu aktifmu!', 'epic');
            setStatus('🕳 TRAP! Semua kartu aktifmu dibatalkan oleh lawan!', 'red');
            break;
        }

        case 'opponent_card_absolute_reset': {
            // Lawan pakai Absolute Reset — server akan broadcast round_start ke ronde 1
            // Kita hanya tampilkan notifikasi; state reset akan datang via absolute_reset_triggered + round_start
            showCardToast('💥 ABSOLUTE RESET! Lawan mereset match ke Ronde 1 Game 1!', 'legend');
            setStatus('💥 Absolute Reset oleh lawan — match kembali ke awal!', 'red');
            break;
        }

        case 'absolute_reset_triggered': {
            // Server menginformasi bahwa absolute reset telah terpicu (broadcast ke semua)
            // Reset state lokal — round_start akan datang tepat setelahnya dari server
            // Set flag agar round_start dengan absolute_reset:true tidak diproses ganda
            window._absoluteResetPending = true;
            _doAbsoluteResetLocal(false);
            showCardToast('♾️ ABSOLUTE RESET! Match kembali ke Ronde 1 Game 1!', 'legend');
            setStatus('♾️ Absolute Reset aktif! Mempersiapkan ulang...', 'yellow');
            break;
        }

        case 'opponent_card_invert': {
            // Lawan pakai Invert Back — tampilkan chip di sisi lawan
            oppActiveEffects = oppActiveEffects.filter(e => e.effect_id !== 'invert_back');
            oppActiveEffects.push({ effect_id: 'invert_back', label: '🔃 Invert', rarity: 'epic', gamesLeft: 1 });
            renderActiveEffects();
            showCardToast('🔃 Lawan mengaktifkan Invert Back! Efek kartumu bisa dibalikkan!', 'epic');
            setStatus('🔃 Waspada! Lawan punya Invert Back aktif!', 'yellow');
            break;
        }
    }
}

// ═══════════════════════════════════════════════════════════
//  APPLY ROUND START (dipanggil setelah animasi selesai)
// ═══════════════════════════════════════════════════════════
function applyRoundStart(msg) {
    locked = false;
    fightAnimating = false;
    pendingRoundMsg = null;
    round  = msg.round || round;
    document.getElementById('round-label').textContent = 'RONDE ' + round;

    // Show game number badge next to round label if game >= 2
    let gameBadgeEl = document.getElementById('game-num-badge');
    if (!gameBadgeEl) {
        gameBadgeEl = document.createElement('span');
        gameBadgeEl.id = 'game-num-badge';
        gameBadgeEl.className = 'game-number-badge';
        document.getElementById('round-label').after(gameBadgeEl);
    }
    gameBadgeEl.textContent = `GAME ${gameNumber}`;
    gameBadgeEl.style.display = 'inline-block';
    document.getElementById('p1-chose-badge').classList.remove('show');
    document.getElementById('p2-chose-badge').classList.remove('show');
    // Reset tombol lanjutkan untuk ronde berikutnya
    const btn = document.getElementById('btn-continue');
    if (btn) { btn.disabled = false; btn.textContent = 'LANJUTKAN ▶'; btn.style.animation = ''; }
    resetHandImages();
    showSelectionScreen();
    setStatus('⏱ Pilih sekarang!', 'blue');
    startTimer(TIMER_SECS);   // selalu 8 detik

    // ── Proses efek Block One dari server ──
    // block_one_target: player_id yang terkena efek (hanya bisa pilih 1 kartu)
    const blockOneTarget = msg.block_one_target ?? null;
    if (blockOneTarget !== null) {
        if (blockOneTarget === MY_ID) {
            // Kita yang kena efek
            blockOneActive  = true;
            blockOneAsOwner = false;
            // Tambah chip indikasi di sisi kita sendiri (p1-effects)
            activeEffects = activeEffects.filter(e => e.cardId !== 'block_one_received');
            activeEffects.push({ cardId: 'block_one_received', label: '🚫 Dibatasi 1 Kartu', rarity: 'rare', gamesLeft: 1, effect: 'block_one_received' });
        } else {
            // Lawan yang kena efek — kita adalah pengaktif
            blockOneActive  = false;
            blockOneAsOwner = true;
            // Chip di sisi kita (pengaktif) sudah ada dari saat aktivasi
            // Pastikan chip lawan (p2) juga tetap tampil
            oppActiveEffects = oppActiveEffects.filter(e => e.effect_id !== 'block_one');
            oppActiveEffects.push({ effect_id: 'block_one', label: '🚫 Block One', rarity: 'rare', gamesLeft: 1 });
        }
    } else {
        // Tidak ada efek Block One ronde ini — reset state dan hapus chip
        blockOneActive  = false;
        blockOneAsOwner = false;
        activeEffects   = activeEffects.filter(e => e.cardId !== 'block_one' && e.cardId !== 'block_one_received');
        oppActiveEffects = oppActiveEffects.filter(e => e.effect_id !== 'block_one' && e.effect_id !== 'steal_hp2');
        // Pastikan chip block_one bersih di sisi pengaktif juga
        renderActiveEffects();
    }

    // Update HP dari data server (akurat)
    if (msg.p1_id && msg.p1_hp !== undefined) {
        const isP1 = (msg.p1_id === MY_ID);
        const serverMyHp  = isP1 ? msg.p1_hp : msg.p2_hp;
        const serverOppHp = isP1 ? msg.p2_hp : msg.p1_hp;

        // Guard: jika reverse_result lawan sudah di-sync, pakai nilai hp_sync bukan server
        if (_reverseOppOverrideMyHp !== null) {
            myHp  = _reverseOppOverrideMyHp;
            oppHp = _reverseOppOverrideOppHp ?? serverOppHp ?? oppHp;
            _pendingReverseFromOpp   = false;
            _reverseOppOverrideMyHp  = null;
            _reverseOppOverrideOppHp = null;
            _godAttackOverrideMyHp   = null;
        } else {
            // Saat ronde baru (new_round baru saja diterima), SELALU pakai nilai HP dari server.
            // Guard "server HP > lokal +5" hanya berlaku dalam ronde yang sama (untuk reverse_result),
            // bukan saat HP di-reset ke 100 oleh server di awal ronde baru.
            if (_justStartedNewRound) {
                _justStartedNewRound = false; // clear flag setelah dipakai
                myHp  = serverMyHp  ?? HP_MAX;
                oppHp = serverOppHp ?? HP_MAX;
            } else {
                // FIX Bug 6: Guard reverse_result — hanya untuk ronde yang sama
                const prevKnownMyHp  = myHp;
                const prevKnownOppHp = oppHp;
                myHp  = serverMyHp  ?? myHp;
                oppHp = serverOppHp ?? oppHp;
                if (serverMyHp !== undefined && serverMyHp > prevKnownMyHp + 5) {
                    myHp = prevKnownMyHp;
                }
                if (serverOppHp !== undefined && serverOppHp > prevKnownOppHp + 5) {
                    oppHp = prevKnownOppHp;
                }
            }
        }
        updateHPBar('p1', myHp);
        updateHPBar('p2', oppHp);
    }

    // ── Terapkan burn dari lawan jika ada pending (diterima sebelum applyRoundStart) ──
    if (_pendingCritFromOpp > 0) {
        _applyPendingCritFromOpp();
    }

    // Jika masih punya shield di game baru, update tampilan dan sync ke lawan
    if (myShield > 0) {
        updateShieldDisplay();
        // Beritahu lawan bahwa shield kita masih aktif
        wsSend({
            type:      'hp_sync',
            my_hp:     myHp,
            their_hp:  oppHp,
            shield_hp: myShield,
            shield_max: myShieldMax,
        });
    } else {
        // Pastikan shield bar hilang jika tidak ada shield
        updateShieldDisplay();
    }

    // ── Terapkan gamesLeft chip reverse_result lawan jika hp_sync sudah datang ──
    if (_pendingReverseGl >= 0) {
        const gl = _pendingReverseGl;
        _pendingReverseGl = -1;
        if (gl > 0) {
            const existing = oppActiveEffects.find(e => e.effect_id === 'reverse_result');
            if (existing) {
                existing.gamesLeft = gl;
                oppActiveEffects = [...oppActiveEffects];
            }
        } else {
            oppActiveEffects = oppActiveEffects.filter(e => e.effect_id !== 'reverse_result');
        }
    }
    // renderActiveEffects SETELAH semua updateHPBar & updateShieldDisplay agar DOM order chip benar
    renderActiveEffects();
}

// ═══════════════════════════════════════════════════════════
//  GAME LOGIC
// ═══════════════════════════════════════════════════════════
function handleRoundResult(msg) {
    // Tentukan siapa p1 dan p2 dari sudut pandangku
    const isP1 = (msg.p1_id === MY_ID);

    // CATATAN: _reverseOppOverride TIDAK di-reset di sini.
    // hp_sync reverse_result dari lawan bisa datang SEBELUM round_result tiba.
    // Override hanya di-null-kan oleh Phase 4 fight overlay setelah dibaca.
    // Reset _pendingReverseGl juga TIDAK dilakukan di sini agar applyRoundStart bisa membacanya.

    const myChoice  = isP1 ? msg.p1_choice : msg.p2_choice;
    const oppChoice = isP1 ? msg.p2_choice : msg.p1_choice;
    let newMyHp   = isP1 ? msg.p1_hp : msg.p2_hp;
    let newOppHp  = isP1 ? msg.p2_hp : msg.p1_hp;

    // Simpan HP sebelum ronde ini untuk reverse_result (agar HP tidak berkurang)
    const myHpBeforeRound  = myHp;
    const oppHpBeforeRound = oppHp;

    // Simpan pilihan lawan untuk lock_choice di ronde berikutnya
    oppLastChoice = oppChoice;

    // Hitung damage dari server (SEBELUM efek kartu client-side)
    let myDmg  = Math.max(0, myHp  - newMyHp);
    let oppDmg = Math.max(0, oppHp - newOppHp);

    // FIX: Simpan raw oppDmg dari server SEBELUM di-zero-kan oleh oppHasShield.
    // Nilai ini dipakai sebagai base untuk god_attack_actual_dmg sehingga
    // shield penerima mendapat nilai damage total yang benar untuk diserap.
    const oppDmgRaw = oppDmg;

    // ── Jika lawan punya shield aktif, jangan langsung percaya HP dari server ──
    // Server tidak tahu shield client-side — hp_sync dari lawan akan mengoreksi nanti
    const oppHasShield = oppActiveEffects.some(e =>
        e.effect_id === 'shield1' || e.effect_id === 'shield2' || e.effect_id === 'shield3' ||
        e.effect_id === 'steal_hp' || e.effect_id === 'steal_hp2'
    );
    if (oppHasShield && newOppHp < oppHp) {
        // Tahan: gunakan oppHp saat ini, biarkan hp_sync dari lawan yang mengoreksi
        newOppHp = oppHp;
        oppDmg   = 0;
    }

    // Hasil dari sudut pandangku
    const result = msg.result;
    let iWon   = (result === 'p1' && isP1) || (result === 'p2' && !isP1);
    let draw   = (result === 'draw');

    // ── Terapkan efek kartu aktif ke hasil ronde ──
    const fullDmgEff = activeEffects.find(e => e.effect === 'full_damage');
    // full_damage tidak memaksa menang — ia hanya mengalikan damage ×5 (ditangani di applyActiveEffectsToResult)
    // Cukup pertahankan iWon/draw sesuai hasil server normal

    const reverseEff = activeEffects.find(e => e.effect === 'reverse_result');
    let reverseResultTriggered = false;
    let reverseResultUsedCount = 0;
    let reverseOppDmgFixed = 0; // damage pasti ke lawan saat reverse (sebelum efek lain)
    if (reverseEff) {
        const wasLosing = !iWon && !draw;
        const wasDraw   = draw;
        // Trigger on LOSS or DRAW only (tidak aktif jika sudah menang)
        if (!iWon) {
            // Transform: loss → win, draw → win
            iWon  = true;
            draw  = false;
            myDmg = 0; // HP pemilik kartu TIDAK berkurang saat reverse aktif
            reverseResultTriggered = true;
            reverseResultUsedCount = 1;
            oppDmg = 20; // lawan tetap kena 20 damage
            reverseOppDmgFixed = 20; // simpan nilai fixed sebelum applyActiveEffectsToResult

            // Charge berkurang baik saat KALAH maupun SERI
            reverseEff.gamesLeft = Math.max(0, reverseEff.gamesLeft - 1);

            if (reverseEff.gamesLeft <= 0) {
                activeEffects = activeEffects.filter(e => e.effect !== 'reverse_result');
                if (wasLosing) {
                    showCardToast('🔄 Reverse Result! Kekalahan → Menang! (Efek habis)', 'epic');
                } else {
                    showCardToast('🔄 Reverse Result! Seri → Menang! (Efek habis)', 'epic');
                }
            } else {
                if (wasLosing) {
                    showCardToast(`🔄 Reverse Result! Kalah → Menang! (${reverseEff.gamesLeft} kesempatan tersisa)`, 'epic');
                } else {
                    showCardToast(`🔄 Reverse Result! Seri → Menang! (${reverseEff.gamesLeft} kesempatan tersisa)`, 'epic');
                }
            }
            renderActiveEffects();
        }
    }

    const tieEff = activeEffects.find(e => e.effect === 'tie_breaker');
    let tieBreakerTriggered = false;
    if (tieEff && draw) {
        iWon = true;
        draw = false;
        tieBreakerTriggered = true;
        oppDmg = 20;
        activeEffects = activeEffects.filter(e => e.effect !== 'tie_breaker');
        renderActiveEffects();
        showCardToast('⚖️ Tie Breaker terpicu! Seri → Anda memenangkan ronde ini + -20 HP lawan!', 'common');
    }

    if (draw) {
        drawStreak++;
    } else {
        drawStreak = 0;
    }

    // applyActiveEffectsToResult sekarang mengembalikan drainLifeHeal & effectCardOppDmg
    const { myDmgOut, oppDmgOut, drainLifeHeal, effectCardOppDmg, drainLifeGamesLeft, gamblingExtraDmg, gamblingGamesLeft, safePlayGamesLeft, safePlay2GamesLeft, godAttackGamesLeft, godAttackMultiplier, godAtk2GamesLeft, godAtk2Multiplier, godAtk3GamesLeft, godAtk3Multiplier, barrierBroke, shieldAbsorbed, criticalGamesLeft, fullDamageGamesLeft } =
        applyActiveEffectsToResult(myDmg, oppDmg, iWon, draw);
    myDmg  = myDmgOut;
    oppDmg = oppDmgOut;

    // ── Hitung HP akhir: damage dari server + efek kartu client-side ──
    // Guard reverse_result: pemilik kartu TIDAK menerima damage apapun
    if (reverseResultTriggered) { myDmg = 0; }
    // newMyHp  = (HP sebelum) - (damage) + (drain_life heal jika menang)
    // Gunakan myHpBeforeRound (bukan myHp) agar tidak terpengaruh update HP server sebelumnya
    newMyHp  = reverseResultTriggered
        ? myHpBeforeRound  // HP pemilik TIDAK berubah -- gunakan HP sebelum ronde
        : Math.min(100, Math.max(0, myHp  - myDmg) + drainLifeHeal);
    // newOppHp = (HP lawan sebelum) - (damage lawan) - (critical_attack extra dmg jika berhasil)
    // FIX: Saat reverseResultTriggered, gunakan oppHpBeforeRound - reverseOppDmgFixed (20)
    // BUKAN oppDmgOut yang mungkin sudah dimodifikasi efek kartu lain (god_attack, dll)
    // Ini memastikan HP yang di-display dan di-sync ke lawan IDENTIK
    newOppHp = reverseResultTriggered
        ? Math.max(0, oppHpBeforeRound - reverseOppDmgFixed)  // damage pasti 20 ke lawan
        : Math.max(0, oppHp - oppDmgOut - effectCardOppDmg);

    // CATATAN: Shield TIDAK di-tick per ronde — shield habis hanya saat HP shield = 0 (diserap damage)

    // ══════════════════════════════════════════════════════
    //  KIRIM HP SYNC REVERSE RESULT SEGERA (sebelum fight overlay)
    //  Agar penerima mendapat nilai HP yang benar SEBELUM Phase 4 mereka berjalan
    // ══════════════════════════════════════════════════════
    if (reverseResultTriggered) {
        const rrEffEarly = activeEffects.find(e => e.effect === 'reverse_result');
        const syncOppHpEarly = Math.max(0, oppHpBeforeRound - reverseOppDmgFixed);
        const syncGlEarly    = rrEffEarly ? rrEffEarly.gamesLeft : 0;
        // Kirim SEGERA agar penerima dapat hp_sync sebelum fight overlay 5 detik
        wsSend({
            type:                       'hp_sync',
            my_hp:                      myHpBeforeRound,   // HP pengirim tidak berubah
            their_hp:                   syncOppHpEarly,    // HP lawan sudah dikurangi 20
            reverse_result_triggered:   true,
            reverse_result_games_left:  syncGlEarly,
        });
    }

    // ══════════════════════════════════════════════════════
    //  KIRIM HP SYNC GOD ATTACK SEGERA (sebelum fight overlay)
    //  Dikirim di sini agar penerima dapat god_attack_actual_dmg ~5 detik sebelum
    //  Phase 4 mereka berjalan — cukup waktu untuk set _godAttackOverrideMyHp.
    //  oppDmgRaw = base 20 dari server (sebelum di-zero oleh oppHasShield).
    // ══════════════════════════════════════════════════════
    if (godAttackGamesLeft >= 0) {
        wsSend({
            type:                    'hp_sync',
            my_hp:                   myHp,
            their_hp:                oppHp,
            god_attack_games_left:   godAttackGamesLeft,
            god_attack_multiplier:   godAttackMultiplier,
            god_attack_actual_dmg:   godAttackGamesLeft === 0 ? Math.floor(oppDmgRaw * godAttackMultiplier) : 0,
        });
    }
    if (godAtk2GamesLeft >= 0) {
        wsSend({
            type:                    'hp_sync',
            my_hp:                   myHp,
            their_hp:                oppHp,
            god_atk2_games_left:     godAtk2GamesLeft,
            god_atk2_multiplier:     godAtk2Multiplier,
            god_atk2_actual_dmg:     godAtk2GamesLeft === 0 ? Math.floor(oppDmgRaw * godAtk2Multiplier) : 0,
        });
    }
    if (godAtk3GamesLeft >= 0) {
        wsSend({
            type:                    'hp_sync',
            my_hp:                   myHp,
            their_hp:                oppHp,
            god_atk3_games_left:     godAtk3GamesLeft,
            god_atk3_multiplier:     godAtk3Multiplier,
            god_atk3_actual_dmg:     godAtk3GamesLeft === 0 ? Math.floor(oppDmgRaw * godAtk3Multiplier) : 0,
        });
    }

    // ══════════════════════════════════════════════════════
    //  FIGHT OVERLAY — Dramatic weapon reveal sequence
    // ══════════════════════════════════════════════════════
    // Tampilkan tangan asli di main battle area menggantikan tanda tanya segera
    const p1h = document.getElementById('p1-hand');
    const p2h = document.getElementById('p2-hand');
    if (p1h) p1h.src = HAND_IMG[myChoice] || HAND_IMG.rock;
    if (p2h) p2h.src = HAND_IMG[oppChoice] || HAND_IMG.rock;

    setStatus(`Lawan memilih ${oppChoice.toUpperCase()}! Bersiap bertarung...`, 'blue');

    // Beri jeda 1000ms agar player sempat melihat pilihan lawan sebelum overlay muncul
    _fightTimeouts.push(setTimeout(() => {
        showFightOverlay(myChoice, oppChoice, iWon, draw, newMyHp, newOppHp, msg, isP1, myDmg, oppDmg,
                         drainLifeHeal, effectCardOppDmg, drainLifeGamesLeft, gamblingGamesLeft, safePlayGamesLeft, safePlay2GamesLeft, barrierBroke, tieBreakerTriggered, shieldAbsorbed, godAttackGamesLeft, godAttackMultiplier, criticalGamesLeft, gamblingExtraDmg, godAtk2GamesLeft, godAtk2Multiplier, godAtk3GamesLeft, godAtk3Multiplier, reverseResultTriggered, fullDamageGamesLeft);
    }, 1000));
}

// ── Show fight overlay with animated weapon clash reveal ──
function cancelFightOverlay() {
    // Batalkan semua setTimeout fight overlay yang masih pending
    _fightTimeouts.forEach(id => clearTimeout(id));
    _fightTimeouts = [];
    fightAnimating = false;
    locked = false;
    document.getElementById('fight-overlay')?.classList.remove('show');
}

function showFightOverlay(myChoice, oppChoice, iWon, draw, newMyHp, newOppHp, msg, isP1, myDmg, oppDmg,
                         drainLifeHeal = 0, effectCardOppDmg = 0, drainLifeGamesLeft = -1, gamblingGamesLeft = -1, safePlayGamesLeft = -1, safePlay2GamesLeft = -1, barrierBroke = false, tieBreakerTriggered = false, shieldAbsorbed = 0, godAttackGamesLeft = -1, godAttackMultiplier = 1, criticalGamesLeft = -1, gamblingExtraDmg = 0, godAtk2GamesLeft = -1, godAtk2Multiplier = 1, godAtk3GamesLeft = -1, godAtk3Multiplier = 1, reverseResultTriggered = false, fullDamageGamesLeft = -1) {
    const overlay = document.getElementById('fight-overlay');
    const banner  = document.getElementById('fight-banner');
    const p1Img   = document.getElementById('fight-weapon-p1');
    const p2Img   = document.getElementById('fight-weapon-p2');
    const p1Name  = document.getElementById('fight-p1-name');
    const p2Name  = document.getElementById('fight-p2-name');
    const p1Lbl   = document.getElementById('fight-label-p1');
    const p2Lbl   = document.getElementById('fight-label-p2');
    const resText = document.getElementById('fight-result-text');
    const resDet  = document.getElementById('fight-winner-detail');

    // Set names
    p1Name.textContent = MY_NAME;
    p2Name.textContent = opponentName;

    // Set weapon images
    p1Img.src = HAND_IMG[myChoice]  || HAND_IMG.rock;
    p2Img.src = HAND_IMG[oppChoice] || HAND_IMG.rock;
    p1Lbl.textContent = HAND_LABEL[myChoice]  || myChoice.toUpperCase();
    p2Lbl.textContent = HAND_LABEL[oppChoice] || oppChoice.toUpperCase();

    // Reset semua — hapus class animasi, set opacity 0
    p1Img.className  = 'fight-weapon p1';
    p2Img.className  = 'fight-weapon p2';
    p1Img.style.opacity = '0';
    p2Img.style.opacity = '0';
    p1Lbl.classList.remove('show');
    p2Lbl.classList.remove('show');
    resText.className    = 'fight-result-text';
    resText.textContent  = '';
    resDet.className     = '';
    resDet.textContent   = '';

    // Re-trigger banner animation
    banner.className = 'fight-banner';
    void banner.offsetWidth;
    banner.textContent = '⚔️ FIGHT!';

    // Show overlay
    overlay.classList.add('show');

    // Reset tracked timeouts untuk fight overlay ini
    _fightTimeouts.forEach(id => clearTimeout(id));
    _fightTimeouts = [];

    // ── FREEZE TIMER saat animasi fight aktif ──
    // Timer sudah di-stop (stopTimer dipanggil di handleMsg 'round_result'),
    // tapi kita bekukan juga tampilan timer agar tidak membingungkan
    {
        const timerRing = document.getElementById('timer-ring');
        const timerNum  = document.getElementById('timer-num');
        timerRing.classList.remove('urgent');
        timerNum.textContent = '⚔️';
        document.getElementById('timer-circle').style.strokeDashoffset = CIRC;
    }

    // ── Phase 1 (0.8s delay): Trigger weapon reveal via JS ──
    // P1 weapon slide in dari kiri
    _fightTimeouts.push(setTimeout(() => {
        p1Img.style.opacity = '';   // lepas inline override
        void p1Img.offsetWidth;     // force reflow
        p1Img.classList.add('animating-p1');
    }, 800));

    // P2 weapon slide in dari kanan (150ms setelah p1)
    _fightTimeouts.push(setTimeout(() => {
        p2Img.style.opacity = '';
        void p2Img.offsetWidth;
        p2Img.classList.add('animating-p2');
    }, 950));

    // ── Phase 2 (2.0s): Show weapon labels after reveal animation finishes ──
    _fightTimeouts.push(setTimeout(() => {
        p1Lbl.classList.add('show');
        p2Lbl.classList.add('show');
    }, 2000));

    // ── Phase 3 (2.5s): Apply win/lose/draw glow + banner shake ──
    _fightTimeouts.push(setTimeout(() => {
        banner.classList.add('shake');

        const p1Result = iWon ? 'win' : draw ? 'draw' : 'lose';
        const p2Result = iWon ? 'lose' : draw ? 'draw' : 'win';

        // Pastikan senjata tetap terlihat sebelum glow animation dimulai
        p1Img.style.opacity = '1';
        p2Img.style.opacity = '1';
        void p1Img.offsetWidth;
        void p2Img.offsetWidth;
        p1Img.classList.add('revealed', p1Result);
        p2Img.classList.add('revealed', p2Result);

        // Determine winner explanation text
        let resultHTML  = '';
        let detailText  = '';
        const winnerName   = iWon ? MY_NAME : opponentName;
        const loserName    = iWon ? opponentName : MY_NAME;
        const weaponWinner = iWon ? HAND_LABEL[myChoice]  : HAND_LABEL[oppChoice];
        const weaponLoser  = iWon ? HAND_LABEL[oppChoice] : HAND_LABEL[myChoice];

        // Hitung damage aktual dari selisih HP sebelum vs sesudah ronde
        const actualDmgToOpp = Math.max(0, oppHp - newOppHp);
        const actualDmgToMe  = Math.max(0, myHp  - newMyHp);

        if (draw) {
            // Cek apakah lawan punya reverse_result yang terpicu (hp_sync sudah diterima)
            if (_pendingReverseFromOpp) {
                // Dari perspektif kita: seri tapi lawan punya reverse → lawan menang, kita kena -20 HP
                resultHTML = `🔄 REVERSE RESULT! ${opponentName} menang!`;
                resText.style.color = 'var(--red)';
                detailText = `🔄 Seri dibalik oleh ${opponentName} · ${MY_NAME} -20 HP`;
                // Glow: kita lose, lawan win
                p1Img.classList.remove('draw'); p1Img.classList.add('lose');
                p2Img.classList.remove('draw'); p2Img.classList.add('win');
            } else {
                resultHTML = '🤝 SERI!';
                resText.style.color = 'var(--accent)';
                if (drawStreak >= 3) {
                    detailText = `${HAND_LABEL[myChoice]} vs ${HAND_LABEL[oppChoice]} · 💥 Seri ${drawStreak}x! HP berkurang 10!`;
                } else {
                    detailText = `${HAND_LABEL[myChoice]} vs ${HAND_LABEL[oppChoice]} — HP tidak berkurang`;
                }
            }
        } else if (iWon) {
            // Cek apakah lawan punya reverse_result yang terpicu (menang kita dibalik jadi kalah)
            if (_pendingReverseFromOpp) {
                resultHTML = `🔄 REVERSE RESULT! ${opponentName} menang!`;
                resText.style.color = 'var(--red)';
                detailText = `🔄 Kemenangan dibalik oleh ${opponentName} · ${MY_NAME} -20 HP`;
                // Glow: kita lose, lawan win
                p1Img.classList.remove('win', 'draw'); p1Img.classList.add('lose');
                p2Img.classList.remove('lose', 'draw'); p2Img.classList.add('win');
            } else if (reverseResultTriggered) {
                resultHTML = `🔄 REVERSE RESULT! Kamu menang!`;
                resText.style.color = 'var(--purple)';
                detailText = `🔄 Kekalahan dibalik jadi menang · ${opponentName} -${oppDmg > 0 ? oppDmg : 20} HP`;
                spawnConfetti(overlay);
            } else {
                resultHTML = `🏆 Kamu memenangkan ronde ini!`;
                resText.style.color = 'var(--green)';
                const gambIcon = gamblingExtraDmg === 50 ? '🎰' : (gamblingExtraDmg === 30 ? '🃏' : (gamblingExtraDmg === 10 ? '🎲' : '🎰'));
                const gambBonus = gamblingExtraDmg > 0 ? ` (+${gamblingExtraDmg} ${gambIcon})` : '';
                detailText = `${weaponWinner} mengalahkan ${weaponLoser} · ${opponentName} -${actualDmgToOpp > 0 ? actualDmgToOpp : oppDmg} HP${gambBonus}`;
                spawnConfetti(overlay);
            }
        } else {
            // Cek apakah lawan punya reverse_result yang terpicu (hp_sync sudah diterima)
            if (_pendingReverseFromOpp) {
                // Dari perspektif kita: kalah tapi lawan punya reverse → lawan menang, kita kena -20 HP
                resultHTML = `🔄 REVERSE RESULT! ${opponentName} menang!`;
                resText.style.color = 'var(--red)';
                detailText = `🔄 Kekalahan dibalik oleh ${opponentName} · ${MY_NAME} -20 HP`;
                // Glow: kita lose, lawan win (tetap sama karena memang kalah)
                p1Img.classList.remove('win', 'draw'); p1Img.classList.add('lose');
                p2Img.classList.remove('lose', 'draw'); p2Img.classList.add('win');
            } else {
                resultHTML = `💀 Anda kalah dari ${opponentName} di ronde ini!`;
                resText.style.color = 'var(--red)';
                const gambPenaltyAbs = Math.abs(gamblingExtraDmg);
                const gambPenaltyIcon = gambPenaltyAbs === 20 ? '🎰' : (gambPenaltyAbs === 30 ? '🃏' : (gambPenaltyAbs === 10 ? '🎲' : '🎰'));
                const gambPenalty = gamblingExtraDmg < 0 ? ` (+${gambPenaltyAbs} ${gambPenaltyIcon})` : '';
                detailText = `${weaponWinner} mengalahkan ${weaponLoser} · ${MY_NAME} -${actualDmgToMe > 0 ? actualDmgToMe : oppDmg} HP${gambPenalty}`;
            }
        }

        resText.textContent = resultHTML;
        resText.classList.add('show');

        _fightTimeouts.push(setTimeout(() => {
            resDet.textContent = detailText;
            resDet.classList.add('show');
        }, 400));
    }, 2500));

    // ── Phase 4 (5.0s): Close overlay, update game state ──
    _fightTimeouts.push(setTimeout(() => {
        overlay.classList.remove('show');
        fightAnimating = false;   // ← animasi selesai
        locked = false;           // ← pastikan player bisa pilih lagi

        // Now update the main battle area
        const p1h = document.getElementById('p1-hand');
        const p2h = document.getElementById('p2-hand');

        p1h.className = 'hand';
        p2h.className = 'hand';
        void p1h.offsetWidth;
        void p2h.offsetWidth;

        p1h.src = HAND_IMG[myChoice]  || HAND_IMG.rock;
        p2h.src = HAND_IMG[oppChoice] || HAND_IMG.rock;

        p1h.classList.add('hand-reveal-p1');
        p2h.classList.add('hand-reveal-p2');

        _fightTimeouts.push(setTimeout(() => {
            if (draw) {
                p1h.classList.add('hand-draw-p1');
                p2h.classList.add('hand-draw-p2');
            } else if (iWon) {
                p1h.classList.add('hand-win');
                p2h.classList.add('hand-lose-p2');
            } else {
                p2h.classList.add('hand-win');
                p1h.classList.add('hand-lose');
            }
        }, 600));

        // Update HP — gunakan newMyHp/newOppHp yang sudah termasuk efek kartu client-side
        const prevMyHp  = myHp;
        const prevOppHp = oppHp;
        // ── Guard: jika hp_sync reverse_result dari lawan sudah datang (sebelum ATAU sesudah round_result),
        // gunakan nilai authoritative dari hp_sync — JANGAN timpa dengan nilai stale dari server ──
        // _reverseOppOverrideMyHp bisa diset dari opponent_hp_sync yang tiba kapan saja
        // FIX: Jika god_attack hp_sync sudah menetapkan HP override (shield absorb sudah dihitung),
        // gunakan itu. Jika reverse_result override ada, itu yang prioritas. Fallback ke server value.
        const finalMyHp  = (_reverseOppOverrideMyHp !== null) ? _reverseOppOverrideMyHp
                         : (_godAttackOverrideMyHp  !== null) ? _godAttackOverrideMyHp
                         : newMyHp;
        const finalOppHp = (_reverseOppOverrideOppHp !== null) ? _reverseOppOverrideOppHp : newOppHp;
        // Null-kan override setelah dibaca
        _reverseOppOverrideMyHp  = null;
        _reverseOppOverrideOppHp = null;
        _godAttackOverrideMyHp   = null;  // reset setelah Phase 4 membacanya
        _pendingReverseFromOpp   = false;
        const hpChanged = (finalMyHp !== prevMyHp || finalOppHp !== prevOppHp);
        myHp  = finalMyHp;
        oppHp = finalOppHp;

        // Selisih HP aktual (bisa berbeda dari myDmg/oppDmg karena heal/efek kartu)
        const actualMyDmg  = Math.max(0, prevMyHp  - myHp);   // HP saya berkurang
        const actualOppDmg = Math.max(0, prevOppHp - oppHp);  // HP lawan berkurang
        const actualMyHeal = Math.max(0, myHp  - prevMyHp);   // HP saya bertambah

        if (hpChanged) {
            if (actualMyDmg  > 0) flashDamage('p1-hp-bar');
            if (actualOppDmg > 0) flashDamage('p2-hp-bar');
            if (actualMyHeal > 0) flashHeal('p1-hp-bar');
        }

        _fightTimeouts.push(setTimeout(() => {
            updateHPBar('p1', myHp);
            updateHPBar('p2', oppHp);
            // Render chip setelah HP update agar efek di bawah HP bar selalu sinkron
            renderActiveEffects();

            const battleArea = document.querySelector('.battle-area');
            // Float angka sesuai selisih HP aktual
            // Jika ada effectCardOppDmg (critical/efek kartu), kurangi dari actualOppDmg
            // agar tidak muncul angka ganda (mis: -60 dan -30☠ sekaligus)
            const actualOppDmgDisplay = Math.max(0, actualOppDmg - effectCardOppDmg);
            if (actualMyDmg  > 0) spawnDamageFloat(battleArea, '-' + actualMyDmg,  'dmg-p1');
            if (actualOppDmgDisplay > 0) spawnDamageFloat(battleArea, '-' + actualOppDmgDisplay, 'dmg-p2');

            // ── Heal float: hanya untuk drain_life (ada heal nyata) ──
            if (drainLifeHeal > 0) {
                spawnHealFloat(battleArea, '+' + drainLifeHeal + ' 🩸');
            }

            // ── Effect Card: damage visual ke lawan ──
            if (effectCardOppDmg > 0) {
                spawnDamageFloat(battleArea, '-' + effectCardOppDmg + ' ☠', 'dmg-p2');
            }

            // ── Gambling extra damage float ──
            if (gamblingExtraDmg > 0) {
                const gIcon = gamblingExtraDmg === 50 ? '🎰' : (gamblingExtraDmg === 30 ? '🃏' : '🎲');
                spawnDamageFloat(battleArea, '-' + gamblingExtraDmg + ' ' + gIcon, 'dmg-p2');
            } else if (gamblingExtraDmg < 0) {
                const gAbsIcon = Math.abs(gamblingExtraDmg) === 20 ? '🎰' : (Math.abs(gamblingExtraDmg) === 30 ? '🃏' : '🎲');
                spawnDamageFloat(battleArea, '-' + Math.abs(gamblingExtraDmg) + ' ' + gAbsIcon, 'dmg-p1');
            }

            // ── HP Sync drain_life & critical_attack ──
            if (drainLifeHeal > 0 || effectCardOppDmg > 0 || drainLifeGamesLeft >= 0) {
                wsSend({
                    type:                    'hp_sync',
                    my_hp:                   myHp,
                    their_hp:                oppHp,
                    heal_amount:             drainLifeHeal,
                    dmg_amount:              effectCardOppDmg,
                    drain_life_games_left:   drainLifeGamesLeft,
                    suppress_heal_float:     true,   // jangan spawn heal float di penerima
                });
            }

            // ── HP Sync gambling — kirim gambling_games_left seperti pola drain_life ──
            if (gamblingGamesLeft >= 0) {
                wsSend({
                    type:                 'hp_sync',
                    my_hp:                myHp,
                    their_hp:             oppHp,
                    gambling_games_left:  gamblingGamesLeft,
                    gambling_extra_dmg:   gamblingExtraDmg,  // +30 menang / -30 kalah / 0 draw
                });
            }

            // ── HP Sync safe_play1 — sinkronisasi HP karena damage dimodifikasi ──
            if (safePlayGamesLeft >= 0) {
                wsSend({
                    type:                  'hp_sync',
                    my_hp:                 myHp,
                    their_hp:              oppHp,
                    safe_play_games_left:  safePlayGamesLeft,
                });
            }

            // ── HP Sync safe_play2 — sinkronisasi HP karena damage dimodifikasi ──
            if (safePlay2GamesLeft >= 0) {
                wsSend({
                    type:                   'hp_sync',
                    my_hp:                  myHp,
                    their_hp:               oppHp,
                    safe_play2_games_left:  safePlay2GamesLeft,
                });
            }

            // ── HP Sync Critical Attack — sinkronisasi ke lawan ──
            if (criticalGamesLeft >= 0) {
                wsSend({
                    type:                 'hp_sync',
                    my_hp:                myHp,
                    their_hp:             oppHp,
                    critical_games_left:  criticalGamesLeft,
                    // Jika effectCardOppDmg > 0 berarti crit berhasil — kirim ke lawan
                    critical_attack_dmg:  effectCardOppDmg > 0 ? effectCardOppDmg : 0,
                });
            }

            // ── HP Sync Full Damage — sinkronisasi chip ke lawan ──
            if (fullDamageGamesLeft >= 0) {
                wsSend({
                    type:                    'hp_sync',
                    my_hp:                   myHp,
                    their_hp:                oppHp,
                    full_damage_games_left:  fullDamageGamesLeft,
                });
            }

            const barrierStillActive  = activeEffects.some(e => e.effect === 'barrier');
            const barrier2StillActive = activeEffects.some(e => e.effect === 'double_damage');
            // Determine which barrier broke: check if double_damage was the one that triggered barrierBroke
            // We check by looking at whether barrier2 chip was removed (not in activeEffects any more)
            const barrier2JustBroke = barrierBroke && !barrier2StillActive && !barrierStillActive
                                      && !activeEffects.some(e => e.effect === 'barrier');
            if (barrierBroke || barrierStillActive || barrier2StillActive) {
                wsSend({
                    type:               'hp_sync',
                    my_hp:              myHp,
                    their_hp:           oppHp,
                    barrier_broke:      barrierBroke,
                    barrier2_broke:     barrierBroke && !barrierStillActive && !barrier2StillActive,
                    barrier_active:     barrierStillActive || barrier2StillActive,
                });
            }

            // ── HP Sync Tie Breaker ──
            if (tieBreakerTriggered) {
                wsSend({
                    type:                  'hp_sync',
                    my_hp:                 myHp,
                    their_hp:              oppHp,
                    tie_breaker_triggered: true,
                });
            }

            // hp_sync reverse_result sudah dikirim SEGERA di handleRoundResult (sebelum fight overlay)
            // Tidak perlu dikirim ulang di sini agar tidak menimpa state penerima yang sudah benar

            // ── HP Sync Shield — kirim setelah myHp final, agar lawan tahu HP kita benar ──
            // shieldAbsorbed > 0 = shield menyerap damage ronde ini
            if (shieldAbsorbed > 0) {
                wsSend({
                    type:         'hp_sync',
                    my_hp:        myHp,        // HP kita yang sudah benar (tidak berkurang karena shield)
                    their_hp:     oppHp,
                    shield_hp:    myShield,    // Sisa HP shield setelah serap
                    shield_max:   myShieldMax,
                    shield_broke: myShield <= 0,
                });
            }

            // ── Terapkan koreksi barrier dari lawan (jika sudah datang) ──
            // opponent_hp_sync barrier menyimpan HP yang benar ke _barrierCorrectedOppHp.
            // Kita terapkan DI SINI, setelah oppHp = newOppHp di atas agar tidak tertimpa.
            if (_barrierCorrectedOppHp !== null) {
                oppHp = _barrierCorrectedOppHp;
                updateHPBar('p2', oppHp);
                _barrierCorrectedOppHp = null;
            }

            // Perbarui chip efek (gamesLeft mungkin sudah berubah)
            renderActiveEffects();
        }, 100));

        // Show result screen
        let resultText = '';
        let resultColor= '';
        if (draw) {
            resultText  = '🤝 SERI! HP Tidak Berkurang.';
            resultColor = 'var(--accent)';
        } else if (iWon) {
            resultText  = reverseResultTriggered
                ? `🔄 Reverse Result! Kamu memenangkan ronde ini`
                : `🏆 Kamu memenangkan ronde ini`;
            resultColor = reverseResultTriggered ? 'var(--purple)' : 'var(--green)';
        } else {
            resultText  = `❌ Anda kalah dari ${opponentName} di ronde ini`;
            resultColor = 'var(--red)';
        }

        const resEl = document.getElementById('result-text');
        resEl.textContent = resultText;
        resEl.style.color  = resultColor;

        document.getElementById('selection-screen').style.display = 'none';
        document.getElementById('waiting-screen').style.display   = 'none';
        document.getElementById('result-screen').classList.add('show');
        document.getElementById('btn-continue').textContent = 'LANJUTKAN ▶';
        document.getElementById('btn-continue').disabled = false;

        // ── Cek jika hp_sync reverse_result dari lawan sudah datang duluan ──
        // _pendingReverseFromOpp sudah di-null oleh Phase 4 (5s), jadi cek via _reverseOppOverrideMyHp
        // (Phase 4 belum jalan saat ini — kita di timeout yang sama 5000ms)
        // Jika lawan reverse sudah datang SEBELUM Phase 4 selesai, override sudah diterapkan ke myHp/oppHp
        // di handler opponent_hp_sync — cukup re-render HP bar dari nilai global terkini
        updateHPBar('p1', myHp);
        updateHPBar('p2', oppHp);
        renderActiveEffects();
        document.getElementById('btn-continue').onclick = () => {
            const btn = document.getElementById('btn-continue');
            btn.disabled = true;
            btn.textContent = '⏳ Menunggu lawan...';
            setStatus('⏳ Menunggu lawan klik Lanjutkan...', 'yellow');
            stopContinueTimer();

            // Kirim sinyal ke server bahwa player ini siap lanjut
            wsSend({ type: 'continue_ready' });
        };
        // Fight overlay selesai — clear tracked timeout list
        _fightTimeouts = [];
    }, 5000));
}

// ── Spawn confetti for win celebration ──
function spawnConfetti(parent) {
    const colors = ['#ffd700','#4affbb','#4facfe','#f093fb','#ff5e5e','#fff'];
    for (let i = 0; i < 30; i++) {
        setTimeout(() => {
            const el = document.createElement('div');
            el.className = 'confetti-piece';
            el.style.left = (10 + Math.random() * 80) + '%';
            el.style.top  = (Math.random() * 30) + '%';
            el.style.background = colors[Math.floor(Math.random() * colors.length)];
            el.style.animationDuration = (.8 + Math.random() * .8) + 's';
            el.style.animationDelay = (Math.random() * .4) + 's';
            el.style.borderRadius = Math.random() > .5 ? '50%' : '2px';
            parent.appendChild(el);
            setTimeout(() => el.remove(), 1600);
        }, Math.random() * 500);
    }
}

// ── Spawn floating damage number ──
function spawnDamageFloat(parent, text, cls) {
    const el = document.createElement('div');
    el.className = 'damage-float ' + cls;
    el.textContent = text;
    // Posisi vertikal acak sedikit supaya tidak tumpang tindih
    el.style.top  = (30 + Math.random() * 20) + 'px';
    parent.style.position = 'relative';
    parent.appendChild(el);
    // Hapus elemen setelah animasi selesai
    setTimeout(() => el.remove(), 1400);
}

function handleMatchOver(msg) {
    matchOver = true;
    stopTimer();
    stopContinueTimer();

    const iWon = (msg.winner_id === MY_ID);
    const title = document.getElementById('match-over-title');
    const sub   = document.getElementById('match-over-sub');

    if (msg.reason === 'disconnect') {
        title.textContent = iWon ? '🏆 KAMU MENANG!' : '💀 LAWAN DISCONNECT';
        title.style.color = iWon ? 'var(--green)' : 'var(--orange)';
        sub.textContent   = iWon ? 'Lawan keluar dari game!' : 'Kamu disconnect dari server.';
    } else if (msg.reason === 'afk') {
        title.textContent = iWon ? '🏆 KAMU MENANG!' : '💀 KAMU KALAH (AFK)';
        title.style.color = iWon ? 'var(--green)' : 'var(--red)';
        sub.textContent   = iWon ? 'Lawan dianggap kalah karena AFK!' : 'Kamu dianggap kalah karena AFK!';
    } else if (!msg.winner_id) {
        title.textContent = '🤝 SERI!';
        title.style.color = 'var(--accent)';
        sub.textContent   = 'Pertandingan berakhir imbang.';
    } else if (iWon) {
        title.textContent = '🏆 KAMU MENANG MATCH!';
        title.style.color = 'var(--green)';
        sub.textContent   = `Skor Ronde: ${myWins} – ${oppWins}`;
    } else {
        title.textContent = '💀 KAMU KALAH!';
        title.style.color = 'var(--red)';
        sub.textContent   = `Skor Ronde: ${myWins} – ${oppWins}`;
    }

    // Hapus sessionStorage match data
    sessionStorage.removeItem('match_data');

    // Sembunyikan semua layar lain, tampilkan match over
    document.getElementById('selection-screen').style.display = 'none';
    document.getElementById('waiting-screen').style.display   = 'none';
    document.getElementById('result-screen').classList.remove('show');
    // Kosongkan teks ronde agar tidak ikut tampil di bawah match-over
    const resTextEl = document.getElementById('result-text');
    if (resTextEl) { resTextEl.textContent = ''; resTextEl.classList.remove('show'); }
    // Sembunyikan tombol lanjutkan agar tidak muncul bersamaan dengan match-over
    const btnCont = document.getElementById('btn-continue');
    if (btnCont) { btnCont.style.display = 'none'; }
    document.getElementById('match-over').classList.add('show');
}

// ═══════════════════════════════════════════════════════════
//  SEND CHOICE
// ═══════════════════════════════════════════════════════════
function sendChoice(choice) {
    if (locked || matchOver) return;
    locked = true;
    myLastChoice = choice;
    stopTimer();
    // Hapus timeout msg
    const msgEl = document.getElementById('timeout-msg');
    if (msgEl) msgEl.textContent = '';

    // ── WEAPON SELECTION ANIMATION ──
    // Highlight selected card, dim others
    document.querySelectorAll('.choice').forEach(c => {
        const cChoice = c.getAttribute('onclick')?.match(/sendChoice\('(\w+)'\)/)?.[1];
        if (cChoice === choice) {
            c.classList.add('selected');
        } else {
            c.classList.add('disabled');
            c.style.opacity = '0.25';
            c.style.transform = 'scale(0.85)';
            c.style.transition = 'all 0.3s';
        }
    });

    // Update waiting weapon display
    const wImg = document.getElementById('waiting-weapon-img');
    const wLbl = document.getElementById('waiting-weapon-label');
    if (wImg) wImg.src = HAND_IMG[choice];
    if (wLbl) wLbl.textContent = HAND_LABEL[choice] || choice.toUpperCase();

    // Shake animation on battle hands
    const p1h = document.getElementById('p1-hand');
    const p2h = document.getElementById('p2-hand');
    p1h.classList.add('sh-p1');
    p2h.classList.add('sh-p2');
    p1h.src = 'assets/Rock.png';
    p2h.src = 'assets/Question.svg';

    setTimeout(() => {
        p1h.classList.remove('sh-p1');
        p2h.classList.remove('sh-p2');
        p1h.src = HAND_IMG[choice];

        wsSend({ type: 'choice', choice: choice });
    }, 900);
}

// ═══════════════════════════════════════════════════════════
//  TIMER
// ═══════════════════════════════════════════════════════════
function startTimer(secs) {
    // Jangan mulai timer jika animasi fight sedang berlangsung
    if (fightAnimating) return;

    timerLeft = secs || TIMER_SECS;
    const numEl  = document.getElementById('timer-num');
    const ringEl = document.getElementById('timer-ring');
    const circEl = document.getElementById('timer-circle');
    const msgEl  = document.getElementById('timeout-msg');

    numEl.textContent             = timerLeft;
    circEl.style.strokeDashoffset = 0;
    ringEl.classList.remove('urgent');
    msgEl.textContent = '';

    clearInterval(timerInt);
    timerInt = setInterval(() => {
        timerLeft--;
        numEl.textContent = timerLeft;
        circEl.style.strokeDashoffset = CIRC * (1 - timerLeft / (secs || TIMER_SECS));

        if (timerLeft <= 2) ringEl.classList.add('urgent');

        if (timerLeft <= 0) {
            clearInterval(timerInt);
            if (!locked && !matchOver) {
                msgEl.textContent = '⏰ WAKTU HABIS!';
                locked = true;
                const choices = ['rock', 'paper', 'scissors'];
                const autoChoice = choices[Math.floor(Math.random()*3)];
                myLastChoice = autoChoice;
                wsSend({ type: 'choice', choice: autoChoice });
            }
        }
    }, 1000);
}

function stopTimer() {
    clearInterval(timerInt);
    const circEl = document.getElementById('timer-circle');
    circEl.style.strokeDashoffset = 0;
    document.getElementById('timer-ring').classList.remove('urgent');
    document.getElementById('timer-num').textContent = '-';
}

function stopContinueTimer() {
    if (window.continueTimerInt) {
        clearInterval(window.continueTimerInt);
        window.continueTimerInt = null;
    }
}

function showAfkModal() {
    const overlay = document.getElementById('afkOverlay');
    const modal = document.getElementById('afkModal');
    if (overlay && modal) {
        overlay.classList.add('open');
        setTimeout(() => modal.classList.add('show'), 10);
    }
    if (window.LuckySound && typeof window.LuckySound.playMatchLose === 'function') {
        window.LuckySound.playMatchLose();
    }
}

// ═══════════════════════════════════════════════════════════
//  UI HELPERS
// ═══════════════════════════════════════════════════════════
function showGame() {
    document.getElementById('connect-screen').style.display = 'none';
    document.getElementById('game-main').style.display      = 'block';
    // Render HP dari state (sudah diset dari matchData)
    updateHPBar('p1', myHp);
    updateHPBar('p2', oppHp);
    updateDots();
    // Status diset oleh caller, jangan override di sini
}

function showSelectionScreen() {
    // Pastikan locked selalu false saat selection screen muncul
    locked = false;
    document.getElementById('selection-screen').style.display = 'block';
    document.getElementById('waiting-screen').style.display   = 'none';
    document.getElementById('result-screen').classList.remove('show');
    // Reset semua pilihan (hapus animasi selected)
    document.querySelectorAll('.choice').forEach(c => {
        c.classList.remove('disabled', 'selected');
        c.style.opacity      = '';
        c.style.transform    = '';
        c.style.transition   = '';
        c.style.pointerEvents = '';
    });
    // Reset badge "sudah pilih"
    document.getElementById('p1-chose-badge').classList.remove('show');
    document.getElementById('p2-chose-badge').classList.remove('show');
    // Reset timeout msg
    const msgEl = document.getElementById('timeout-msg');
    if (msgEl) msgEl.textContent = '';
}

function showWaitingScreen() {
    document.getElementById('selection-screen').style.display = 'none';
    document.getElementById('waiting-screen').style.display   = 'block';
}

function resetHandImages() {
    document.getElementById('p1-hand').src = 'assets/Rock.png';
    document.getElementById('p2-hand').src = 'assets/Question.svg';
}

function updateHPBar(who, hp) {
    const rawHp = Math.max(0, hp);
    const pct   = Math.min(rawHp, HP_MAX); // bar visual max 100 width
    const bar   = document.getElementById(who + '-hp-bar');
    const val   = document.getElementById(who + '-hp-val');
    const card  = document.getElementById(who + '-card');

    card.classList.remove('hp-mid', 'hp-low');
    if (pct <= 40)       card.classList.add('hp-low');
    else if (pct <= 60)  card.classList.add('hp-mid');

    bar.style.width   = Math.min(pct, HP_MAX) + '%';
    val.textContent   = rawHp;  // tampilkan angka asli (bisa > 100)
    if (pct <= 40) {
        val.style.color = 'var(--hp-low)';
    } else if (pct <= 60) {
        val.style.color = 'var(--hp-mid)';
    } else if (rawHp > 100) {
        val.style.color = 'var(--accent)';
    } else {
        // Reset ke warna default CSS (p1=hijau, p2=ungu via .right .hp-val)
        val.style.color = '';
    }
}

function updateHP(msg) {
    const isP1 = (msg.p1_id === MY_ID);
    const newMyHp  = isP1 ? msg.p1_hp : msg.p2_hp;
    const newOppHp = isP1 ? msg.p2_hp : msg.p1_hp;
    myHp  = newMyHp  ?? myHp;
    oppHp = newOppHp ?? oppHp;
    updateHPBar('p1', myHp);
    updateHPBar('p2', oppHp);
}

function flashDamage(barId) {
    const bar = document.getElementById(barId);
    bar.classList.remove('hp-damaged');
    void bar.offsetWidth;
    bar.classList.add('hp-damaged');
}

// ── Flash green heal animation on HP bar ──
function flashHeal(barId) {
    const bar = document.getElementById(barId);
    bar.classList.remove('hp-healed');
    void bar.offsetWidth;
    bar.classList.add('hp-healed');
    setTimeout(() => bar.classList.remove('hp-healed'), 700);
}

// ── Spawn heal float (green text rising up) ──
function spawnHealFloat(parent, text, cls = '') {
    const el = document.createElement('div');
    el.className = 'heal-float' + (cls ? ' ' + cls : '');
    el.textContent = text;
    el.style.left = (15 + Math.random() * 35) + '%';
    el.style.top  = (15 + Math.random() * 20) + 'px';
    parent.style.position = 'relative';
    parent.appendChild(el);
    // Spawn orb particles
    for (let i = 0; i < 6; i++) {
        const orb = document.createElement('div');
        orb.className = 'heal-orb';
        const angle = (i / 6) * Math.PI * 2;
        const dist  = 25 + Math.random() * 30;
        orb.style.left  = (40 + Math.cos(angle) * 15) + '%';
        orb.style.top   = (40 + Math.sin(angle) * 15) + 'px';
        orb.style.setProperty('--tx', Math.round(Math.cos(angle) * dist) + 'px');
        orb.style.setProperty('--ty', Math.round(Math.sin(angle) * dist - 50) + 'px');
        orb.style.animationDelay = (Math.random() * 0.15) + 's';
        parent.appendChild(orb);
        setTimeout(() => orb.remove(), 1100);
    }
    setTimeout(() => el.remove(), 1500);
}

function updateDots() {
    for (let i = 0; i < 2; i++) {
        document.getElementById('pd-' + i).className = 'dot' + (i < myWins  ? ' p1' : '');
        document.getElementById('cd-' + i).className = 'dot' + (i < oppWins ? ' p2' : '');
    }
}

function setStatus(msg, cls = '') {
    const el = document.getElementById('status-bar');
    el.textContent = msg;
    el.className   = cls;
}

// ═══════════════════════════════════════════════════════════
//  ACTIONS
// ═══════════════════════════════════════════════════════════
function sendRematch() {
    wsSend({ type: 'rematch' });
    document.getElementById('btn-rematch').disabled = true;
    document.getElementById('btn-rematch').textContent = '⏳ Menunggu lawan...';
    document.getElementById('rematch-status').textContent = 'Permintaan rematch dikirim!';
}

function goMenu() {
    wsSend({ type: 'leave_room' });
    sessionStorage.removeItem('match_data');
    window.location.href = 'main_menu.php';
}

function showExitModal() {
    const overlay = document.getElementById('exitOverlay');
    const modal = document.getElementById('exitModal');
    overlay.classList.add('open');
    setTimeout(() => modal.classList.add('show'), 10);
}
function closeExitModal() {
    const overlay = document.getElementById('exitOverlay');
    const modal = document.getElementById('exitModal');
    modal.classList.remove('show');
    setTimeout(() => overlay.classList.remove('open'), 280);
}

document.getElementById('btnQuit').addEventListener('click', () => {
    showExitModal();
});

// ═══════════════════════════════════════════════════════════
//  ✦ SPELL / ABILITY CARD SYSTEM ✦
// ═══════════════════════════════════════════════════════════

// ── CARD DATABASE (25 total) ──
const CARD_DB = {
    // ══ COMMON (8) ══
    drain_life:     { id:'drain_life',    rarity:'common', icon:'🩸', name:'Drain Life 1',     desc:'Setiap menang +10 HP. Aktif 3 game.', fullDesc:'Setiap kali menang game (ronde), kamu memulihkan 10 HP. Kartu tetap aktif selama 3 game. Jika dalam 3 game tidak ada kemenangan, HP tidak dipulihkan dan efek kartu tetap habis setelah 3 game.' },
    gambling1:      { id:'gambling1',     rarity:'common', icon:'🎲', name:'The Gambling I',   desc:'Menang +10 dmg, kalah +10 dmg diterima.', fullDesc:'Jika menang: damage yang diberikan +10 dari normal. Jika kalah: damage yang diterima juga +10.' },
    safe_play1:     { id:'safe_play1',    rarity:'common', icon:'🛡', name:'Safe Play I',       desc:'Kalah = 0 dmg, Menang = 50% dmg. 1 game.', fullDesc:'Jika kalah: tidak menerima damage. Jika menang: hanya memberikan 50% damage normal. Berlaku 1 game saja.' },
    barrier:        { id:'barrier',       rarity:'common', icon:'🔮', name:'Barrier 1',          desc:'Kalah = 50% damage. Aktif sampai kamu kalah 1x.', fullDesc:'Jika kamu kalah game, damage yang diterima dikurangi menjadi 50% dari normal. Efek ini bertahan permanen sampai kamu mendapatkan 1 kekalahan — setelah itu Barrier 1 hancur secara otomatis. Hanya bisa digunakan 1 kali per ronde, bisa digunakan lagi di ronde berikutnya.' },
    critical_attack: { id:'critical_attack', rarity:'common', icon:'⚡', name:'Critical Attack',    desc:'50% chance +30 dmg saat menang. Aktif 2 game atau sampai berhasil.', fullDesc:'Saat kamu menang game, ada kemungkinan 50% serangan memberikan +30 damage tambahan kepada musuh. Efek ini aktif selama 2 game saja. Jika kesempatan 50% berhasil dan damage ekstra sudah diberikan, efek kartu ini langsung habis secara otomatis.' },
    tie_breaker:    { id:'tie_breaker',   rarity:'common', icon:'⚖️', name:'Tie Breaker',       desc:'Seri jadi menang untukmu.', fullDesc:'Ketika aktif, mengubah hasil game seri menjadi kemenangan bagimu dalam ronde ini.' },
    shield1:        { id:'shield1',       rarity:'common', icon:'🛡️', name:'Shield I',          desc:'+30 HP shield. Menyerap damage musuh sampai habis.', fullDesc:'Mendapatkan 30 poin Shield HP. Ketika terkena serangan dari musuh, Shield HP menyerap damage terlebih dahulu sebelum HP asli berkurang. Shield aktif sampai Shield HP habis terserap — tidak ada batas jumlah game.' },
    god_attack1:    { id:'god_attack1',   rarity:'common', icon:'⚡', name:'God Attack I',      desc:'2× damage saat menang (5% chance 3×). Aktif hingga pertama kali menang.', fullDesc:'Efek aktif selama kamu belum menang. Saat menang pertama kali, serangan menjadi 2× damage (ada 5% kemungkinan menjadi 3× LUCKY!). Setelah digunakan sekali menang, efek berakhir. Juga berakhir saat ronde baru dimulai. Hanya bisa diaktifkan 1x per ronde, bisa digunakan lagi di ronde berikutnya.' },
    // ══ RARE (7) ══
    gambling2:      { id:'gambling2',     rarity:'rare',   icon:'🃏', name:'The Gambling II',  desc:'Menang +30 dmg, kalah +30 dmg diterima. 1x per ronde.', fullDesc:'Jika menang: damage yang diberikan +30 dari normal. Jika kalah: damage yang diterima juga +30. Efek aktif 1 game saja. Bisa digunakan lagi di ronde berikutnya.' },
    block_one:      { id:'block_one',     rarity:'rare',   icon:'🚫', name:'Block One',         desc:'Lawan hanya bisa pakai 1 kartu ronde ini.', fullDesc:'Lawan hanya dapat menggunakan 1 kartu saat ronde di mana kartu ini diaktifkan.' },
    steal_hp:       { id:'steal_hp',      rarity:'rare',   icon:'💉', name:'Steal HP 1',         desc:'-20 HP lawan → +20 Shield kamu.', fullDesc:'Mengurangi 20 HP lawan dan mengonversinya menjadi +20 Shield HP untuk dirimu sendiri. Shield menyerap damage musuh sebelum HP asli berkurang.' },
    repeat:         { id:'repeat',        rarity:'rare',   icon:'🔁', name:'Repeat',            desc:'Jika kalah, ronde diulang.', fullDesc:'Jika kamu kalah ronde ini, ronde akan diulang dari awal tanpa HP berubah.' },
    safe_play2:     { id:'safe_play2',    rarity:'rare',   icon:'🛡', name:'Safe Play II',      desc:'Kalah = 0 dmg, Menang = 20 dmg normal. 1 game.', fullDesc:'Jika kalah: tidak menerima damage sama sekali. Jika menang: memberikan damage normal penuh (20). Berlaku 1 game saja.' },
    god_attack2:    { id:'god_attack2',   rarity:'rare',   icon:'⚔️', name:'God Attack II',     desc:'2× damage saat menang (20% chance 3×). Aktif hingga pertama kali menang.', fullDesc:'Efek aktif selama kamu belum menang. Saat menang pertama kali, serangan menjadi 2× damage (ada 20% kemungkinan menjadi 3× damage!). Setelah digunakan sekali menang, efek berakhir. Juga berakhir saat ronde baru dimulai. Hanya bisa diaktifkan 1x per ronde, bisa digunakan lagi di ronde berikutnya.' },
    shield2:        { id:'shield2',       rarity:'rare',   icon:'🔷', name:'Shield II',         desc:'+60 HP shield. Menyerap damage musuh sampai habis.', fullDesc:'Mendapatkan 60 poin Shield HP. Ketika terkena serangan dari musuh, Shield HP menyerap damage terlebih dahulu sebelum HP asli berkurang. Shield aktif sampai Shield HP habis terserap — tidak ada batas jumlah game.' },
    // ══ EPIC (6) ══
    gambling3:      { id:'gambling3',     rarity:'epic',   icon:'🎰', name:'The Gambling III', desc:'Menang +50 dmg, kalah +20 dmg diterima. 1x per ronde.', fullDesc:'Jika menang: damage yang diberikan +50 dari normal. Jika kalah: damage yang diterima +20. Efek aktif 1 game saja. Bisa digunakan lagi di ronde berikutnya.' },
    reverse_result: { id:'reverse_result',rarity:'epic',   icon:'🔄', name:'Reverse Result',    desc:'Kalah/Seri → Menang. 3 kesempatan, berkurang saat kalah atau seri.', fullDesc:'Mengubah hasil kalah atau seri menjadi kemenangan bagimu. Efek ini bisa digunakan sebanyak 3 kali — hitungan berkurang setiap kali efek terpicu (baik saat kalah maupun seri). Efek berakhir setelah 3 kali terpicu.' },
    god_attack3:    { id:'god_attack3',   rarity:'epic',   icon:'💀', name:'God Attack III',    desc:'2× damage saat menang (50% chance 3×). Aktif hingga pertama kali menang.', fullDesc:'Efek aktif selama kamu belum menang. Saat menang pertama kali, serangan menjadi 2× damage (ada 50% kemungkinan menjadi 3× damage!). Setelah digunakan sekali menang, efek berakhir. Juga berakhir saat ronde baru dimulai. Hanya bisa diaktifkan 1x per ronde, bisa digunakan lagi di ronde berikutnya.' },
    drain_life_2:   { id:'drain_life_2',  rarity:'epic',   icon:'🩸', name:'Drain Life 2',      desc:'Setiap menang: musuh -10 HP & kamu +25 HP. Aktif 3 game.', fullDesc:'Sama seperti Drain Life 1: setiap kali menang game, musuh kehilangan 10 HP ekstra. Ditambah: setiap kali menang, kamu memulihkan 25 HP. Kartu aktif selama 3 game.' },
    steal_hp2:      { id:'steal_hp2',     rarity:'epic',   icon:'🩻', name:'Steal HP 2',         desc:'-50 HP lawan → +50 Shield kamu.', fullDesc:'Mengurangi 50 HP lawan dan mengonversinya menjadi +50 Shield HP untuk dirimu sendiri. Shield menyerap damage musuh sebelum HP asli berkurang. Versi lebih kuat dari Steal HP 1.' },
    double_damage:  { id:'double_damage', rarity:'epic',   icon:'🔮', name:'Barrier 2',         desc:'Kalah = 25% damage. Aktif sampai kamu kalah 1x.', fullDesc:'Jika kamu kalah game, damage yang diterima dikurangi menjadi 25% dari normal. Efek ini bertahan permanen sampai kamu mendapatkan 1 kekalahan — setelah itu Barrier 2 hancur secara otomatis. Versi lebih kuat dari Barrier 1. Hanya bisa digunakan 1 kali per ronde, bisa digunakan lagi di ronde berikutnya.' },
    // ══ LEGEND (4) ══
    full_damage:    { id:'full_damage',   rarity:'legend', icon:'💥', name:'Full Damage',        desc:'Damage ×5 (total 100)! Aktif hingga pertama kali menang.', fullDesc:'Efek aktif selama kamu belum menang. Saat menang pertama kali, serangan menjadi 5× damage normal (20 × 5 = 100 damage) — cukup untuk mengalahkan lawan seketika! Setelah kemenangan pertama, efek berakhir secara otomatis dan chip di bawah HP bar hilang.' },
    shield3:        { id:'shield3',       rarity:'legend', icon:'🌟', name:'Shield III',        desc:'+100 shield besar!', fullDesc:'Mendapatkan 100 poin shield besar yang menyerap seluruh damage sebelum HP berkurang.' },
    absolute_reset: { id:'absolute_reset',rarity:'legend', icon:'♾️', name:'Absolute Reset',    desc:'Reset match ke ronde 1 game 1!', fullDesc:'Mereset seluruh match kembali ke ronde pertama, game pertama, dengan semua HP dan skor dikembalikan.' },
};

// Rarity weights for random selection
const RARITY_WEIGHTS = { common: 0, rare: 100, epic: 0, legend: 0 };
const RARITY_POOL = {
    common: ['drain_life','gambling1','safe_play1','barrier','critical_attack','tie_breaker','shield1','god_attack1'],
    rare:   ['gambling2','block_one','steal_hp','repeat','safe_play2','god_attack2','shield2'],
    epic:   ['gambling3','reverse_result','god_attack3','drain_life_2','steal_hp2','double_damage'],
    legend: ['full_damage','shield3','absolute_reset'],
};

// ── CARD STATE ──
let myHandCards  = [];        // kartu yang dipilih player untuk ronde ini (max 2)
let activeEffects = [];       // efek yang sedang aktif { cardId, gamesLeft, ... }
let pendingEffects = [];      // efek yang menunggu aktivasi di ronde berikutnya
let oppActiveEffects = [];    // efek LAWAN yang terlihat oleh player ini
let myShield     = 0;
let barrierUsed     = false;       // Barrier 1 hanya bisa dipakai 1x per ronde
let barrier2Used    = false;       // Barrier 2 hanya bisa dipakai 1x per ronde
let godAttackUsed   = false;       // God Attack I hanya bisa dipakai 1x per ronde
let gambling2Used   = false;       // Gambling II hanya bisa dipakai 1x per ronde
let gambling3Used   = false;       // Gambling III hanya bisa dipakai 1x per ronde
let blockOneUsed    = false;       // Block One hanya bisa dipakai 1x per ronde
let fullDamageUsed  = false;       // Full Damage hanya bisa dipakai 1x per ronde
let criticalAttackActive = false;  // Critical Attack: flag apakah efek aktif
let blockOneActive  = false;  // true jika ronde ini player KITA terkena efek Block One (hanya boleh pilih 1 kartu)
let blockOneAsOwner = false;  // true jika ronde ini player KITA yang mengaktifkan Block One (chip di sisi kita)
let cardPickPending = false;
let waitingForOpponentCard = false;  // true saat player sudah pilih, tunggu lawan
let oppCardPickDone = false;         // true saat lawan sudah kirim card_picked duluan
let _cardPickWaitingTimeout = null;  // fallback timeout jika cards_ready terlambat
let _roundStartPending = null;       // msg untuk round start, menunggu kartu selesai
let cardPickedThisRound = false; // flag: sudah pilih kartu di ronde ini, tidak muncul lagi
let pendingCardSlot = -1;     // index slot mana yg sedang dipilih (untuk popup)
let drainLife2Active = false; // drain_life_2: flag aktif
let gameNumber   = 1;         // game ke-berapa (reset tiap set baru)
let roundInGame  = 0;         // ronde dalam game ini
let oppLastChoice = null;     // pilihan lawan di ronde sebelumnya (untuk lock_choice)

// Semua kartu langsung aktif di ronde yang sama saat diaktifkan
// COUNTER_CARDS dipertahankan untuk kompatibilitas label UI saja
const COUNTER_CARDS = new Set([
    'steal_hp',
    'steal_hp2',
    'reverse_result',
    'god_attack3',
    'barrier',
    'repeat',
    'block_one',
    'absolute_reset',
    'full_damage',
    'drain_life_2',
    // Duration cards yang kini juga langsung aktif:
    'drain_life', 'gambling1', 'safe_play1', 'critical_attack',
    'tie_breaker', 'shield1', 'god_attack1', 'gambling2',
    'block_one', 'safe_play2', 'god_attack2', 'shield2',
    'gambling3', 'double_damage', 'shield3',
]);

// ── GENERATE 3 RANDOM CARDS ──
function generateCardOffer() {
    const offers = [];
    const usedIds = new Set();
    while (offers.length < 3) {
        const rarity = rollRarity();
        const pool   = RARITY_POOL[rarity].filter(id => !usedIds.has(id));
        if (!pool.length) continue;
        const id  = pool[Math.floor(Math.random() * pool.length)];
        usedIds.add(id);
        offers.push(Object.assign({}, CARD_DB[id])); // copy agar tidak mutasi CARD_DB
    }
    return offers;
}

function rollRarity() {
     //── MODE TES: selalu keluarkan kartu Legend ──
     //Untuk kembali ke normal, ganti return di bawah dengan logika asli:
     const roll = Math.random() * 100;
     if (roll < 5)  return 'legend';
     if (roll < 20) return 'epic';
     if (roll < 50) return 'rare';
     return 'common';
    // return 'legend';
}

// ── SHOW CARD PICK OVERLAY ──
let cardPickSelected = [];  // kartu yang dipilih sementara (sebelum dikonfirmasi)
let cardPickAutoCloseTimeout = null;   // urgent CSS timer
let cardPickAutoCloseTimer   = null;   // timer auto-close 15s (GANTI cardPickPending._timeout)

let cardPickStartTime = 0;  // waktu mulai card pick (untuk hitung sisa waktu)

function showCardPick() {
    if (matchOver) return;
    cardPickPending = true;
    cardPickStartTime = Date.now();  // catat waktu mulai
    cardPickSelected = [];
    const maxPick = blockOneActive ? 1 : 2;

    const offers  = generateCardOffer();
    const overlay = document.getElementById('card-pick-overlay');
    const row     = document.getElementById('card-pick-row');

    // Update game badge
    const gameBadge = document.getElementById('card-pick-game-badge');
    if (gameBadge) gameBadge.textContent = `GAME ${gameNumber}`;

    // Update subtitle
    const subEl = overlay.querySelector('.card-pick-sub');
    if (subEl) {
        if (blockOneActive) {
            subEl.innerHTML = `<span style="color:#ff6b6b;background:rgba(255,107,107,.1);padding:1px 8px;border-radius:10px;border:1px solid rgba(255,107,107,.25)">🚫 Block One!</span> Pilih <span id="cpo-pick-count">1 dari 3</span> kartu · Ronde ini dibatasi`;
        } else {
            subEl.innerHTML = `Pilih <span id="cpo-pick-count">${maxPick} dari 3</span> kartu · Konfirmasi sebelum waktu habis`;
        }
    }

    row.innerHTML = '';
    offers.forEach((card) => {
        const timingLabel = '<div class="card-timing-label instant">Langsung Aktif</div>';
        const counterBadge = '';

        const el = document.createElement('div');
        el.className = `spell-card ${card.rarity}`;
        el.dataset.cardId = card.id;
        el.innerHTML = `
            <div class="card-rarity">${card.rarity.toUpperCase()}</div>
            ${counterBadge}
            <div class="card-icon">${card.icon}</div>
            <div class="card-name">${card.name}</div>
            <div class="card-divider"></div>
            <div class="card-desc">${card.desc}</div>
            ${timingLabel}
        `;
        el.addEventListener('click', () => {
            // Toggle pilihan
            if (el.classList.contains('selected-card')) {
                // Batal pilih
                el.classList.remove('selected-card');
                cardPickSelected = cardPickSelected.filter(c => c.id !== card.id);
                row.querySelectorAll('.spell-card').forEach(c => {
                    if (cardPickSelected.length < maxPick) c.classList.remove('dimmed');
                });
            } else {
                if (cardPickSelected.length >= maxPick) return; // sudah penuh
                el.classList.add('selected-card');
                cardPickSelected.push(Object.assign({}, card, { _used: false })); // copy fresh, reset _used
                // Dim yang belum dipilih jika sudah mencapai max
                if (cardPickSelected.length >= maxPick) {
                    row.querySelectorAll('.spell-card:not(.selected-card)').forEach(c => c.classList.add('dimmed'));
                }
            }
            updateConfirmButton(maxPick);
        });
        row.appendChild(el);
    });

    // Reset confirm button
    updateConfirmButton(maxPick);

    // Sembunyikan waiting state, tampilkan form pilih kartu
    document.getElementById('card-pick-waiting').classList.remove('show');
    document.getElementById('card-pick-actions').style.display = '';
    document.getElementById('card-pick-row').style.display = '';
    document.querySelector('#card-pick-overlay .card-pick-sub').style.display = '';

    // Timer 15 detik
    const fill = document.getElementById('card-timer-fill');
    fill.style.transition = 'none';
    fill.style.width = '100%';
    fill.classList.remove('urgent');
    void fill.offsetWidth;
    fill.style.transition = 'width 15s linear';
    fill.style.width = '0%';

    // Urgent warning at 3s remaining
    cardPickAutoCloseTimeout = setTimeout(() => {
        fill.classList.add('urgent');
    }, 12000);

    // Auto close after 15s — disimpan di variabel dedicated (bukan di primitive boolean)
    clearTimeout(cardPickAutoCloseTimer);
    cardPickAutoCloseTimer = setTimeout(() => {
        confirmCardPick(true); // force close
    }, 15000);

    overlay.classList.add('show');
}

function updateConfirmButton(maxPick) {
    const btn = document.getElementById('btn-confirm-card');
    const counter = document.getElementById('confirm-counter');
    const count = cardPickSelected.length;
    if (counter) counter.textContent = `${count}/${maxPick}`;
    // Aktifkan tombol jika sudah pilih minimal 1 kartu
    if (count >= 1) {
        btn.classList.add('ready');
    } else {
        btn.classList.remove('ready');
    }
}

function confirmCardPick(isAuto = false) {
    // Batalkan timer auto-close 15s dan timer urgent CSS
    clearTimeout(cardPickAutoCloseTimer);
    cardPickAutoCloseTimer = null;
    clearTimeout(cardPickAutoCloseTimeout);
    drainLife2Active = false;

    if (isAuto) {
        // Auto-close (waktu habis / dipanggil paksa)
        // Hanya simpan myHandCards jika player BELUM manual confirm
        if (!waitingForOpponentCard) {
            myHandCards = [...cardPickSelected];
        }
        // Tutup overlay
        clearTimeout(_cardPickWaitingTimeout);
        _cardPickWaitingTimeout = null;
        document.getElementById('card-pick-overlay').classList.remove('show');
        cardPickPending = false;
        cardPickSelected = [];
        renderCardHand();
        // Beritahu server bahwa player ini sudah selesai (agar server bisa kirim cards_ready)
        if (!waitingForOpponentCard) {
            const autoPickedIds = myHandCards.filter(c => c && c.id).map(c => c.id);
            wsSend({ type: 'card_picked', card_ids: autoPickedIds });
            waitingForOpponentCard = true;  // tunggu cards_ready dari server
        }
    } else {
        // Manual confirm: tampilkan waiting state, tunggu lawan selesai
        // ← SIMPAN kartu SEBELUM cardPickSelected dikosongkan
        myHandCards = [...cardPickSelected];
        cardPickSelected = [];

        // Render chips kartu yang dipilih di waiting screen
        const preview = document.getElementById('cpw-cards-preview');
        preview.innerHTML = '';
        myHandCards.forEach(card => {
            const chip = document.createElement('div');
            chip.className = `cpw-card-chip ${card.rarity}`;
            chip.innerHTML = `${card.icon} ${card.name}`;
            preview.appendChild(chip);
        });
        if (myHandCards.length === 0) {
            const chip = document.createElement('div');
            chip.className = 'cpw-card-chip';
            chip.textContent = '🚫 Tanpa Kartu';
            preview.appendChild(chip);
        }

        // Reset status lawan
        const dot   = document.getElementById('cpw-opp-dot');
        const label = document.getElementById('cpw-opp-label');
        if (dot)   { dot.className = 'cpw-opp-dot'; }
        if (label) { label.textContent = 'Lawan sedang memilih kartu...'; }

        // Sembunyikan form, tampilkan waiting panel
        document.getElementById('card-pick-actions').style.display = 'none';
        document.getElementById('card-pick-row').style.display = 'none';
        document.querySelector('#card-pick-overlay .card-pick-sub').style.display = 'none';
        document.getElementById('card-pick-waiting').classList.add('show');

        // ── TRACK CARD USAGE: kirim ke server via card_picked ──
        // Server akan menyimpan ke tabel player_card_usage di DB
        const pickedCardIds = myHandCards
            .filter(c => c && c.id)
            .map(c => c.id);

        // Kirim sinyal ke server bahwa kartu sudah dipilih + daftar kartu
        wsSend({ type: 'card_picked', card_ids: pickedCardIds });

        // Tandai player sudah selesai, tunggu lawan
        cardPickPending = false;
        waitingForOpponentCard = true;

        // Jika lawan sudah selesai duluan, langsung tutup overlay (tidak perlu tunggu)
        if (oppCardPickDone) {
            clearTimeout(_cardPickWaitingTimeout);
            waitingForOpponentCard = false;
            document.getElementById('card-pick-overlay').classList.remove('show');
            // _pickWatcher akan mendeteksi dan lanjut
        } else {
            // Fallback: jika server lambat kirim cards_ready, paksa lanjut setelah sisa timer
            const elapsed   = Date.now() - cardPickStartTime;
            const remaining = Math.max(2000, 15000 - elapsed);
            _cardPickWaitingTimeout = setTimeout(() => {
                if (waitingForOpponentCard) {
                    waitingForOpponentCard = false;
                    document.getElementById('card-pick-overlay').classList.remove('show');
                    renderCardHand();
                }
            }, remaining);
        }
    }
}

function skipCardPick() {
    myHandCards = [];
    confirmCardPick(true);
}

// ── RENDER CARD HAND (bottom panel) ──
function renderCardHand() {
    const hand = document.getElementById('card-hand');
    hand.innerHTML = '<div class="card-hand-label">✦ KARTU RONDE</div>';

    // Show 2 slots
    for (let i = 0; i < 2; i++) {
        if (i < myHandCards.length) {
            const card = myHandCards[i];
            const isCounter = COUNTER_CARDS.has(card.id);
            const div  = document.createElement('div');
            div.className = `hand-card ${card.rarity}${card._used ? ' used' : ''}`;
            div.dataset.slot = i;
            div.innerHTML = `
                <div class="hc-icon">${card.icon}</div>
                <div class="hc-name">${card.name}</div>
                ${isCounter ? '<div class="hc-counter">COUNTER</div>' : ''}
            `;
            if (!card._used) {
                div.addEventListener('click', () => openCardPopup(i));
            }
            hand.appendChild(div);
        } else {
            const empty = document.createElement('div');
            empty.className = 'hand-card-empty';
            empty.innerHTML = '<span style="font-size:.6rem;color:#333;">SLOT ' + (i+1) + '</span>';
            hand.appendChild(empty);
        }
    }
}

// ── CARD USE POPUP ──
function openCardPopup(slotIdx) {
    const card = myHandCards[slotIdx];
    if (!card) return; // null = blocked atau kosong

    const handEl = document.querySelector(`#card-hand .hand-card[data-slot="${slotIdx}"]`);
    if (handEl && handEl.classList.contains('used')) return;

    pendingCardSlot = slotIdx;

    const isCounter = COUNTER_CARDS.has(card.id);

    document.getElementById('popup-icon').textContent  = card.icon;
    document.getElementById('popup-name').textContent  = card.name;

    const timingNote = '<br><span style="color:var(--red);font-weight:800;">⚡ Langsung aktif di ronde ini!</span>';
    document.getElementById('popup-desc').innerHTML  = card.fullDesc + timingNote;

    const tag = document.getElementById('popup-rarity');
    tag.textContent = card.rarity.toUpperCase();
    tag.className   = `popup-rarity-tag ${card.rarity}`;

    const popup = document.getElementById('card-use-popup');
    popup.classList.add('show');

    const btnUse = document.getElementById('btn-use-card-confirm');
    btnUse.textContent = '⚡ GUNAKAN SEKARANG!';
    btnUse.onclick = () => {
        popup.classList.remove('show');
        activateCard(pendingCardSlot);
    };
}

function closeCardPopup() {
    document.getElementById('card-use-popup').classList.remove('show');
    pendingCardSlot = -1;
}

// ── APPLY BLOCK ONE STRIKE: blokir 1 kartu dari myHandCards ──
function applyBlockOneStrike() {
    // Kumpulkan slot kartu yang tersedia (belum digunakan dan tidak null)
    const availableSlots = myHandCards
        .map((card, i) => ({ card, i }))
        .filter(({ card, i }) => {
            if (!card) return false; // slot kosong / sudah diblokir
            if (card._used) return false; // sudah digunakan di ronde ini
            const el = document.querySelector(`#card-hand .hand-card[data-slot="${i}"]`);
            return !el || !el.classList.contains('used');
        });

    // === KASUS: Lawan tidak punya kartu sama sekali ===
    if (availableSlots.length === 0) {
        // Tambahkan chip "Block One" ke activeEffects kita sendiri (sisi penerima)
        // Chip akan tetap tampil sampai ronde selesai (ada pemenang)
        activeEffects = activeEffects.filter(e => e.cardId !== 'block_one_pending');
        activeEffects.push({
            cardId:    'block_one_pending',
            label:     '🚫 Block One (Menunggu)',
            rarity:    'rare',
            gamesLeft: 999,
            effect:    'block_one_pending'
        });
        renderActiveEffects();
        showCardToast('🚫 Block One aktif! Lawan tidak punya kartu — efek menunggu sampai ada pemenang.', 'rare');
        setStatus('🚫 Block One aktif! Efek akan tetap sampai ronde selesai.', 'red');
        return;
    }

    // === KASUS: Lawan hanya punya 1 kartu — langsung blokir kartu itu ===
    // === KASUS: Lawan punya 2 kartu — pilih 1 secara acak ===
    const target = availableSlots.length === 1
        ? availableSlots[0]                                          // satu-satunya, langsung blokir
        : availableSlots[Math.floor(Math.random() * availableSlots.length)]; // acak

    const blockedCard = target.card;
    const blockedSlot = target.i;

    // Set slot menjadi null (kartu tidak bisa digunakan)
    myHandCards[blockedSlot] = null;

    // Re-render hand dengan kartu yang terblokir
    renderCardHandWithBlock(blockedSlot, blockedCard);

    const reason = availableSlots.length === 1 ? ' (satu-satunya kartumu)' : '';
    showCardToast(`🚫 Block One! Kartu "${blockedCard.name}"${reason} diblokir oleh lawan!`, 'rare');
    setStatus(`🚫 "${blockedCard.name}" diblokir! Kartu itu tidak bisa digunakan ronde ini.`, 'red');

    // Hapus chip block_one dari sisi lawan (efek sudah terpicu)
    oppActiveEffects = oppActiveEffects.filter(e => e.effect_id !== 'block_one');
    renderActiveEffects();
}

// ── RENDER CARD HAND dengan 1 slot terblokir ──
function renderCardHandWithBlock(blockedSlot, blockedCard) {
    const hand = document.getElementById('card-hand');
    hand.innerHTML = '<div class="card-hand-label">✦ KARTU RONDE</div>';

    for (let i = 0; i < 2; i++) {
        if (i === blockedSlot) {
            // Slot ini terblokir — tampilkan kartu tapi dengan state blocked
            const div = document.createElement('div');
            div.className = `hand-card ${blockedCard.rarity} blocked-card`;
            div.dataset.slot = i;
            div.style.opacity = '0.35';
            div.style.filter = 'grayscale(1)';
            div.style.cursor = 'not-allowed';
            div.style.border = '2px solid #ff5e5e';
            div.style.position = 'relative';
            div.innerHTML = `
                <div class="hc-icon">${blockedCard.icon}</div>
                <div class="hc-name">${blockedCard.name}</div>
                <div class="hc-counter" style="background:rgba(255,94,94,.3);color:#ff5e5e;border-color:rgba(255,94,94,.5)">🚫 BLOCKED</div>
            `;
            // Tidak tambahkan event listener (kartu tidak bisa diklik)
            hand.appendChild(div);
        } else if (myHandCards[i]) {
            const card = myHandCards[i];
            const isCounter = COUNTER_CARDS.has(card.id);
            const div = document.createElement('div');
            div.className = `hand-card ${card.rarity}${card._used ? ' used' : ''}`;
            div.dataset.slot = i;
            div.innerHTML = `
                <div class="hc-icon">${card.icon}</div>
                <div class="hc-name">${card.name}</div>
                ${isCounter ? '<div class="hc-counter">COUNTER</div>' : ''}
            `;
            if (!card._used) {
                div.addEventListener('click', () => openCardPopup(i));
            }
            hand.appendChild(div);
        } else {
            const empty = document.createElement('div');
            empty.className = 'hand-card-empty';
            empty.innerHTML = '<span style="font-size:.6rem;color:#333;">SLOT ' + (i+1) + '</span>';
            hand.appendChild(empty);
        }
    }
}

// ── ACTIVATE CARD EFFECT ──
function activateCard(slotIdx) {
    const card = myHandCards[slotIdx];
    if (!card) return;

    // Barrier 1 hanya bisa digunakan 1x per ronde
    if (card.id === 'barrier' && barrierUsed) {
        showCardToast('🔮 Barrier 1 sudah digunakan di ronde ini!', 'common');
        return;
    }

    // Barrier 2 hanya bisa digunakan 1x per ronde
    if (card.id === 'double_damage' && barrier2Used) {
        showCardToast('🔮 Barrier 2 sudah digunakan di ronde ini!', 'epic');
        return;
    }

    // God Attack I hanya bisa digunakan 1x per ronde
    if (card.id === 'god_attack1' && godAttackUsed) {
        showCardToast('⚡ God Attack I sudah digunakan di ronde ini!', 'common');
        return;
    }

    // God Attack II hanya bisa digunakan 1x per ronde
    if (card.id === 'god_attack2' && godAttackUsed) {
        showCardToast('⚔️ God Attack II sudah digunakan di ronde ini!', 'rare');
        return;
    }

    // Gambling II hanya bisa digunakan 1x per ronde
    if (card.id === 'gambling2' && gambling2Used) {
        showCardToast('🃏 The Gambling II sudah digunakan di ronde ini! Tunggu ronde berikutnya.', 'rare');
        return;
    }

    // Gambling III hanya bisa digunakan 1x per ronde
    if (card.id === 'gambling3' && gambling3Used) {
        showCardToast('🎰 The Gambling III sudah digunakan di ronde ini! Tunggu ronde berikutnya.', 'epic');
        return;
    }

    // Block One hanya bisa digunakan 1x per ronde
    if (card.id === 'block_one' && blockOneUsed) {
        showCardToast('🚫 Block One sudah digunakan di ronde ini! Tunggu ronde berikutnya.', 'rare');
        return;
    }

    // Full Damage hanya bisa digunakan 1x per ronde
    if (card.id === 'full_damage' && fullDamageUsed) {
        showCardToast('💥 Full Damage sudah digunakan di ronde ini! Tunggu ronde berikutnya.', 'legend');
        return;
    }

    const isCounter = COUNTER_CARDS.has(card.id);

    // Mark as used in UI
    const handEl = document.querySelector(`#card-hand .hand-card[data-slot="${slotIdx}"]`);

    // ── PLAY ACTIVATION ANIMATION ──
    playCardActivationAnim(card, handEl, isCounter, () => {
        // Callback setelah animasi utama (~650ms)
        if (handEl) handEl.classList.add('used');
        // Tandai di data agar Block One tahu kartu ini sudah dipakai
        if (myHandCards[slotIdx]) myHandCards[slotIdx]._used = true; // hanya pada objek copy, bukan CARD_DB

        // Show effect banner
        showCardEffectBanner(card, isCounter);

        // Semua kartu langsung aktif di ronde saat ini
        applyCardEffect(card, true);
        showCardToast(`${card.icon} ${card.name} aktif sekarang!`, card.rarity);

        // Send to WS
        wsSend({ type: 'card_used', card_id: card.id, slot: slotIdx, is_counter: true });
    });
}

// ── CARD ACTIVATION ANIMATION SYSTEM — UPGRADED ──
function playCardActivationAnim(card, handEl, isCounter, onDone) {
    const rarityColors = {
        common: '#cccccc', rare: '#4facfe', epic: '#c084fc', legend: '#ffd700',
    };
    const color = rarityColors[card.rarity] || '#fff';

    if (handEl) {
        const rect = handEl.getBoundingClientRect();
        const cx = rect.left + rect.width / 2;
        const cy = rect.top  + rect.height / 2;

        handEl.classList.add('activating');
        setTimeout(() => handEl.classList.remove('activating'), 350);

        // Delay lebih singkat: 120ms → 60ms
        setTimeout(() => {
            const clone = handEl.cloneNode(true);
            clone.classList.add('card-throwing');
            clone.style.left   = rect.left + 'px';
            clone.style.top    = rect.top  + 'px';
            clone.style.width  = rect.width + 'px';
            clone.style.height = rect.height + 'px';
            clone.style.margin = '0';

            const tx = (window.innerWidth  / 2 - cx);
            const ty = (window.innerHeight / 2 - cy);
            const rot = (Math.random() - 0.5) * 50 + (isCounter ? -30 : 25);
            clone.style.setProperty('--tx', tx + 'px');
            clone.style.setProperty('--ty', ty + 'px');
            clone.style.setProperty('--rot', rot + 'deg');
            document.body.appendChild(clone);

            // Impact lebih cepat: 420ms → 280ms
            setTimeout(() => {
                spawnCardParticles(window.innerWidth / 2, window.innerHeight / 2, card.rarity, color);
                triggerScreenFlash(card.rarity);
                if (card.rarity === 'legend') triggerLegendBorder();
                if (card.rarity === 'epic')   triggerEpicVortex();
                clone.remove();
            }, 280);
        }, 60);

        spawnRingBurst(cx, cy, color);
        // Extra ring untuk epic/legend
        if (card.rarity === 'epic' || card.rarity === 'legend') {
            setTimeout(() => spawnRingBurst(cx, cy, color), 80);
        }
    } else {
        triggerScreenFlash(card.rarity);
        spawnCardParticles(window.innerWidth / 2, window.innerHeight / 2, card.rarity, color);
        if (card.rarity === 'legend') triggerLegendBorder();
        if (card.rarity === 'epic')   triggerEpicVortex();
    }

    // onDone lebih cepat: 680ms → 460ms
    setTimeout(onDone, 460);
}

function spawnCardParticles(cx, cy, rarity, color) {
    const count = { common: 14, rare: 22, epic: 30, legend: 42 }[rarity] || 16;
    const sz    = { common: 5,  rare: 7,  epic: 9,  legend: 11 }[rarity] || 6;

    for (let i = 0; i < count; i++) {
        const el = document.createElement('div');
        el.className = 'card-particle';

        const angle = (i / count) * Math.PI * 2 + Math.random() * 0.5;
        const dist  = 50 + Math.random() * (rarity === 'legend' ? 160 : rarity === 'epic' ? 130 : rarity === 'rare' ? 100 : 70);
        const pSize = sz * (0.5 + Math.random() * 0.8);
        const isSquare = rarity === 'legend' && Math.random() > 0.6;

        let pColor = color;
        if (rarity === 'legend' && Math.random() > 0.45) pColor = '#fff';
        if (rarity === 'legend' && Math.random() > 0.75) pColor = '#ffd700';
        if (rarity === 'epic'   && Math.random() > 0.55) pColor = '#f093fb';
        if (rarity === 'rare'   && Math.random() > 0.6)  pColor = '#7fd4ff';

        el.style.cssText += `
            left:${cx}px;top:${cy}px;
            width:${pSize}px;height:${pSize}px;
            background:${pColor};
            box-shadow:0 0 ${pSize*1.5}px ${pColor},0 0 ${pSize*3}px ${pColor}50;
            --px:${Math.cos(angle)*dist}px;--py:${Math.sin(angle)*dist}px;
            --dur:${0.28 + Math.random() * 0.32}s;
            animation-delay:${Math.random() * 0.06}s;
            transform:translate(-50%,-50%);
            ${isSquare ? 'border-radius:2px;' : ''}
        `;
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 500);
    }
    // Extra sparkle burst untuk legend
    if (rarity === 'legend') {
        for (let i = 0; i < 8; i++) {
            const el = document.createElement('div'); el.className = 'card-particle';
            const angle = Math.random() * Math.PI * 2;
            const dist = 20 + Math.random() * 50;
            el.style.cssText += `left:${cx}px;top:${cy}px;width:14px;height:3px;background:linear-gradient(90deg,${color},transparent);border-radius:2px;--px:${Math.cos(angle)*dist}px;--py:${Math.sin(angle)*dist}px;--dur:${0.22+Math.random()*.18}s;animation-delay:${Math.random()*.05}s;transform:translate(-50%,-50%) rotate(${angle}rad);`;
            document.body.appendChild(el); setTimeout(() => el.remove(), 400);
        }
    }
}

function spawnRingBurst(cx, cy, color) {
    const ring = document.createElement('div');
    ring.className = 'card-ring-burst';
    ring.style.left = cx + 'px'; ring.style.top = cy + 'px';
    ring.style.borderColor = color;
    ring.style.boxShadow = `0 0 14px ${color},0 0 28px ${color}50`;
    document.body.appendChild(ring);
    setTimeout(() => ring.remove(), 450);

    const ring2 = document.createElement('div');
    ring2.className = 'card-ring-burst-2';
    ring2.style.left = cx + 'px'; ring2.style.top = cy + 'px';
    ring2.style.borderColor = color;
    ring2.style.boxShadow = `0 0 8px ${color}`;
    document.body.appendChild(ring2);
    setTimeout(() => ring2.remove(), 450);
}

function triggerScreenFlash(rarity) {
    const el = document.getElementById('card-activate-flash');
    el.className = '';
    void el.offsetWidth;
    el.className = `flash-${rarity}`;
    setTimeout(() => { el.className = ''; }, 500);
}

function triggerLegendBorder() {
    const el = document.getElementById('card-legend-border');
    el.classList.remove('show');
    void el.offsetWidth;
    el.classList.add('show');
    setTimeout(() => el.classList.remove('show'), 650);
}

function triggerEpicVortex() {
    const el = document.getElementById('card-epic-vortex');
    el.classList.remove('show');
    void el.offsetWidth;
    el.classList.add('show');
    setTimeout(() => el.classList.remove('show'), 550);
}


// Antri efek kartu untuk ronde berikutnya
function queuePendingEffect(card) {
    const durationMap = {
        drain_life:   { gamesLeft: 3,  label: '🩸 Drain Life 1' },
        drain_life_2: { gamesLeft: 3,  label: '🩸 Drain Life 2' },
        gambling1:    { gamesLeft: 1,  label: '🎲 Gambling I' },
        safe_play1:   { gamesLeft: 1,  label: '🛡 Safe Play' },
        barrier:      { gamesLeft: 999, label: '🔮 Barrier 1' },
        critical_attack: { gamesLeft: 2, label: '⚡ Critical Atk' },
        tie_breaker:  { gamesLeft: 999, label: '⚖️ Tie Breaker' },
        god_attack1:  { gamesLeft: 999, label: '⚡ God Atk I' },
        gambling2:    { gamesLeft: 1,  label: '🃏 Gambling II' },
        block_one:    { gamesLeft: 1,  label: '🚫 Block One' },
        safe_play2:   { gamesLeft: 1,  label: '🛡 Safe Play II' },
        god_attack2:  { gamesLeft: 999, label: '⚔️ God Atk II' },
        gambling3:    { gamesLeft: 1,  label: '🎰 Gambling III' },
        reverse_result:{ gamesLeft: 3, label: '🔄 Reverse' },
        god_attack3:  { gamesLeft: 999, label: '💀 God Atk III' },
        steal_hp2:    { gamesLeft: 1,  label: '🩻 Steal HP 2' },
        double_damage:{ gamesLeft: 1,  label: '🔮 Barrier 2' },
        shield1:      { gamesLeft: 3,  label: '🛡 Shield I', shield: true, shieldAmt: 10 },
        shield2:      { gamesLeft: 999, label: '🔷 Shield II', shield: true, shieldAmt: 60 },
        shield3:      { gamesLeft: 999,label: '🌟 Shield III', shield: true, shieldAmt: 100 },
    };

    const cfg = durationMap[card.id];
    if (!cfg) return;

    const effect = {
        cardId: card.id,
        effect: card.id,
        label: cfg.label,
        rarity: card.rarity,
        gamesLeft: cfg.gamesLeft,
        isPending: true,
        ...(cfg.shield ? { shield: true, shieldAmt: cfg.shieldAmt } : {}),
    };
    // Remove duplicate pending
    pendingEffects = pendingEffects.filter(e => e.cardId !== card.id);
    pendingEffects.push(effect);

    // Tampilkan indikator "menunggu"
    showPendingIndicator(card, cfg.label);
}

// ── CARD EFFECT BANNER ANIMATION — UPGRADED ──
function showCardEffectBanner(card, isInstant) {
    const banner = document.getElementById('card-effect-banner');
    const rarityColors = {
        common: '#aaa', rare: 'var(--blue)', epic: '#c084fc', legend: 'var(--accent)'
    };
    document.getElementById('ceb-icon').textContent = card.icon;
    document.getElementById('ceb-text').textContent = card.name.toUpperCase();
    document.getElementById('ceb-text').style.color = rarityColors[card.rarity] || '#fff';
    document.getElementById('ceb-sub').textContent  = card.desc;

    const timingEl = document.getElementById('ceb-timing');
    timingEl.textContent = '⚡ Aktif Sekarang!';
    timingEl.className = 'ceb-timing instant';
    timingEl.style.display = 'inline-block';

    banner.classList.remove('show', 'banner-hide', 'counter-active', 'rarity-common', 'rarity-rare', 'rarity-epic', 'rarity-legend');
    void banner.offsetWidth;
    banner.classList.add('show', 'rarity-' + (card.rarity || 'common'));
    if (isInstant) {
        setTimeout(() => banner.classList.add('counter-active'), 10);
    }
    // Waktu tampil diperpendek: 1500ms → 900ms, hide lebih cepat: 280ms → 200ms
    clearTimeout(banner._hideTimer);
    banner._hideTimer = setTimeout(() => {
        banner.classList.add('banner-hide');
        setTimeout(() => {
            banner.classList.remove('show', 'banner-hide', 'counter-active', 'rarity-common', 'rarity-rare', 'rarity-epic', 'rarity-legend');
        }, 200);
    }, 900);
}

// ── APPLY CARD LOGIC (client-side simulation) ──
function applyCardEffect(card, isInstant = false) {
    const id = card.id;

    // === COUNTER CARDS (instant) ===
    if (id === 'steal_hp') {
        // Kurangi 20 HP lawan
        oppHp = Math.max(0, oppHp - 20);
        updateHPBar('p2', oppHp);
        flashDamage('p2-hp-bar');
        spawnDamageFloat(document.querySelector('.battle-area'), '-20', 'dmg-p2');

        // Konversi 20 HP lawan menjadi +20 Shield untuk player
        myShield += 20;
        // Set myShieldMax = nilai shield saat ini agar bar selalu full 100%
        myShieldMax = myShield;
        updateShieldDisplay();
        spawnDamageFloat(document.querySelector('.battle-area'), '+20🛡', 'dmg-heal');
        showCardToast('💉 Steal HP 1! -20 HP lawan → +20 Shield kamu!', 'rare');

        // Tambahkan chip efek steal_hp di bawah p1
        if (!activeEffects.some(e => e.cardId === 'steal_hp')) {
            addActiveEffect({ cardId: 'steal_hp', label: '💉 Steal HP 1 Shield', rarity: 'rare', gamesLeft: 999, shield: true });
        }

        // Sync ke lawan:
        //   my_hp          = HP kita (tidak berubah)
        //   their_hp       = HP lawan setelah dikurangi 20
        //   steal_shield   = flag bahwa kita dapat shield dari steal
        //   steal_shield_hp= jumlah shield yang kita dapat
        //   shield_hp      = sisa shield kita (agar bar shield kita tampil di sisi lawan)
        wsSend({
            type:             'hp_sync',
            my_hp:            myHp,
            their_hp:         oppHp,
            dmg_amount:       20,
            steal_shield:     true,
            steal_shield_hp:  20,
            shield_hp:        myShield,
            shield_max:       myShieldMax,  // kirim max agar bar lawan tampil full
        });
        // Kirim card_effect_notify agar chip steal_hp dan shield bar muncul di sisi lawan segera
        wsSend({
            type:       'card_effect_notify',
            effect_id:  'steal_hp',
            label:      '💉 Steal HP 1 Shield',
            rarity:     'rare',
            games_left: 999,
            shield_hp:  myShield,
            shield_max: myShieldMax,
        });
        return;
    }
    if (id === 'steal_hp2') {
        // Kurangi 50 HP lawan
        const stealAmt = 50;
        oppHp = Math.max(0, oppHp - stealAmt);
        updateHPBar('p2', oppHp);
        flashDamage('p2-hp-bar');
        spawnDamageFloat(document.querySelector('.battle-area'), '-' + stealAmt, 'dmg-p2');

        // Konversi 50 HP lawan menjadi +50 Shield untuk player (sama seperti Steal HP 1 tapi 50)
        myShield += stealAmt;
        myShieldMax = myShield;
        updateShieldDisplay();
        spawnDamageFloat(document.querySelector('.battle-area'), '+' + stealAmt + '🛡', 'dmg-heal');
        showCardToast('🩻 Steal HP 2! -50 HP lawan → +50 Shield kamu!', 'epic');

        // Tambahkan chip efek steal_hp2 di bawah p1 (shield chip)
        if (!activeEffects.some(e => e.cardId === 'steal_hp2')) {
            addActiveEffect({ cardId: 'steal_hp2', label: '🩻 Steal HP 2 Shield', rarity: 'epic', gamesLeft: 999, shield: true });
        }

        // Sync ke lawan: HP mereka berkurang 50, kita dapat shield 50
        wsSend({
            type:             'hp_sync',
            my_hp:            myHp,
            their_hp:         oppHp,
            dmg_amount:       stealAmt,
            steal_shield:     true,
            steal_shield_hp:  stealAmt,
            shield_hp:        myShield,
            shield_max:       myShieldMax,
        });
        // Kirim card_effect_notify agar chip steal_hp2 dan shield bar muncul di sisi lawan segera
        wsSend({
            type:       'card_effect_notify',
            effect_id:  'steal_hp2',
            label:      '🩻 Steal HP 2 Shield',
            rarity:     'epic',
            games_left: 999,
            shield_hp:  myShield,
            shield_max: myShieldMax,
        });
        return;
    }
    if (id === 'full_damage') {
        fullDamageUsed = true;
        addActiveEffect({ cardId: id, label: '💥 Full Damage', rarity: 'legend', gamesLeft: 999, effect: 'full_damage' });
        wsSend({
            type:       'card_effect_notify',
            effect_id:  'full_damage',
            label:      '💥 Full Damage',
            rarity:     'legend',
            games_left: 999,
        });
        setStatus('💥 FULL DAMAGE aktif! Menang pertama = 5× damage (100 dmg) → lawan langsung kalah!', 'yellow');
        return;
    }
    if (id === 'absolute_reset') {
        showCardEffectBanner({icon:'♾️', name:'ABSOLUTE RESET', desc:'Mereset match ke Ronde 1 Game 1!', rarity:'legend'});
        // Reset lokal DULU agar state bersih sebelum round_start dari server tiba
        // Delay sedikit agar banner sempat tampil, tapi selesai sebelum round_start
        setTimeout(() => {
            _doAbsoluteResetLocal(true);
            // Baru kirim ke server — server akan reset room state lalu broadcast round_start
            // round_start tiba setelah ini dan akan memicu showCardPick via applyRoundStart
            wsSend({ type: 'card_absolute_reset', card_id: 'absolute_reset' });
        }, 800);
        return;
    }
    if (id === 'reverse_result') {
        addActiveEffect({ cardId: id, label: '🔄 Reverse', rarity: 'epic', gamesLeft: 3, effect: 'reverse_result' });
        wsSend({
            type:       'card_effect_notify',
            effect_id:  'reverse_result',
            label:      '🔄 Reverse',
            rarity:     'epic',
            games_left: 3,
        });
        setStatus('🔄 Reverse Result aktif! Kekalahan/Seri → Menang. 3 kesempatan, berkurang setiap terpicu.', 'yellow');
        return;
    }
    if (id === 'drain_life_2') {
        // Handled via durationMap below — fall through
    }
    if (id === 'trap_card') {
        // Hapus semua active effects lawan (visual saja dari sisi client)
        wsSend({ type: 'card_trap', card_id: 'trap_card' });
        setStatus('🕳 TRAP! Semua kartu lawan dibatalkan!', 'red');
        addActiveEffect({ cardId: id, label: '🕳 Trap Active', rarity: 'epic', gamesLeft: 1, effect: 'trap_card' });
        return;
    }
    if (id === 'block_one') {
        blockOneUsed = true; // tandai sudah digunakan ronde ini, tidak bisa pakai lagi
        wsSend({ type: 'card_block_one', card_id: 'block_one' });
        // Langsung blok 1 kartu acak dari hand musuh yang sudah dipilih di ronde ini
        window._blockOneOwner = true;
        window._blockOneStrikePending = true;
        setStatus('🚫 Block One! Memblok 1 kartu acak musuh sekarang!', 'red');
        showCardToast('🚫 Block One digunakan! Tidak bisa dipakai lagi ronde ini.', 'rare');
        // Tidak tambah chip ke activeEffects -- kartu langsung habis setelah digunakan
        // Jika musuh sudah selesai pilih kartu, langsung kirim strike
        if (oppCardPickDone) {
            wsSend({ type: 'block_one_strike' });
            window._blockOneStrikePending = false;
            window._blockOneOwner = false;
        }
        return;
    }
    if (id === 'god_attack3') {
        // Handled via durationMap below — fall through
    }

    // === SHIELD CARDS (instant apply from pending) ===
    if (id === 'shield3') {
        myShield += 100;
        myShieldMax = myShield; // set max = nilai awal shield
        addActiveEffect({ cardId: id, label: `🌟 Shield III`, rarity: 'legend', gamesLeft: 999, shield: true, shieldAmt: 100, effect: 'shield3' });
        updateShieldDisplay();
        wsSend({
            type:       'card_effect_notify',
            effect_id:  'shield3',
            label:      '🌟 Shield III',
            rarity:     'legend',
            games_left: 999,
            shield_hp:  myShield,
            shield_max: myShieldMax,
        });
        showCardToast(`🌟 Shield III aktif! +100 Shield HP menyerap damage musuh.`, 'legend');
        return;
    }
    if (id === 'shield1') {
        myShield += 30;
        myShieldMax = myShield; // set max = nilai awal shield
        addActiveEffect({ cardId: id, label: `🛡️ Shield`, rarity: 'common', gamesLeft: 999, shield: true, shieldAmt: 30, effect: 'shield1' });
        updateShieldDisplay();
        // Notify opponent so their display syncs
        wsSend({
            type:          'card_effect_notify',
            effect_id:     'shield1',
            label:         '🛡️ Shield',
            rarity:        'common',
            games_left:    999,
            shield_hp:     myShield,
            shield_max:    myShieldMax,
        });
        showCardToast(`🛡️ Shield I aktif! +30 Shield HP menyerap damage musuh.`, 'common');
        return;
    }
    if (id === 'shield2') {
        myShield += 60;
        myShieldMax = myShield; // set max = nilai awal shield
        addActiveEffect({ cardId: id, label: `🔷 Shield II`, rarity: 'rare', gamesLeft: 999, shield: true, shieldAmt: 60, effect: 'shield2' });
        updateShieldDisplay();
        // Notify opponent so their display syncs
        wsSend({
            type:          'card_effect_notify',
            effect_id:     'shield2',
            label:         '🔷 Shield II',
            rarity:        'rare',
            games_left:    999,
            shield_hp:     myShield,
            shield_max:    myShieldMax,
        });
        showCardToast(`🔷 Shield II aktif! +60 Shield HP menyerap damage musuh.`, 'rare');
        return;
    }

    // === REPEAT CARD ===
    if (id === 'repeat') {
        addActiveEffect({ cardId: 'repeat', label: '🔁 Repeat', rarity: 'rare', gamesLeft: 999, effect: 'repeat' });
        // Beritahu server bahwa Repeat aktif untuk player ini
        wsSend({ type: 'repeat_activate' });
        // Beritahu lawan agar chip Repeat muncul di sisi mereka
        wsSend({
            type:       'card_effect_notify',
            effect_id:  'repeat',
            label:      '🔁 Repeat',
            rarity:     'rare',
            games_left: 999,
        });
        showCardToast('🔁 Repeat aktif! Jika kamu kalah game ini, game akan diulang.', 'rare');
        setStatus('🔁 Repeat aktif! Kekalahan game ini akan membuat game diulang.', 'yellow');
        return;
    }

    // === DURATION EFFECTS ===
    const durationMap = {
        drain_life:    { gamesLeft: 3,  label: '🩸 Drain Life 1', effect: 'drain_life' },
        drain_life_2:  { gamesLeft: 3,  label: '🩸 Drain Life 2', effect: 'drain_life_2' },
        gambling1:     { gamesLeft: 1,  label: '🎲 Gambling I',  effect: 'gambling1' },
        safe_play1:    { gamesLeft: 1,  label: '🛡 Safe Play',   effect: 'safe_play1' },
        barrier:       { gamesLeft: 999, label: '🔮 Barrier 1',     effect: 'barrier' },
        critical_attack: { gamesLeft: 2,   label: '⚡ Critical Atk', effect: 'critical_attack' },
        tie_breaker:   { gamesLeft: 999, label: '⚖️ Tie Breaker', effect: 'tie_breaker' },
        god_attack1:   { gamesLeft: 999, label: '⚡ God Atk I',  effect: 'god_attack1' },
        gambling2:     { gamesLeft: 1,  label: '🃏 Gambling II', effect: 'gambling2' },
        safe_play2:    { gamesLeft: 1,  label: '🛡 Safe Play II', effect: 'safe_play2' },
        god_attack2:   { gamesLeft: 999, label: '⚔️ God Atk II', effect: 'god_attack2' },
        god_attack3:   { gamesLeft: 999, label: '💀 God Atk III', effect: 'god_attack3' },
        gambling3:     { gamesLeft: 1,  label: '🎰 Gambling III', effect: 'gambling3' },
        double_damage: { gamesLeft: 999, label: '🔮 Barrier 2',     effect: 'double_damage' },
    };

    if (durationMap[id]) {
        const cfg = durationMap[id];
        addActiveEffect({
            cardId: id,
            label: cfg.label,
            rarity: card.rarity,
            gamesLeft: cfg.gamesLeft,
            effect: cfg.effect,
        });
        if (id === 'drain_life' || id === 'drain_life_2' || id === 'barrier' || id === 'critical_attack' ||
            id === 'god_attack1' || id === 'god_attack2' || id === 'god_attack3' || id === 'double_damage' || id === 'gambling1' ||
            id === 'gambling2' || id === 'gambling3' || id === 'shield1' || id === 'shield2' || id === 'tie_breaker') {
            wsSend({
                type:       'card_effect_notify',
                effect_id:  id,
                label:      cfg.label,
                rarity:     card.rarity,
                games_left: cfg.gamesLeft,
            });
        }
        // Set barrierUsed agar tidak bisa dipakai lagi di ronde ini
        if (id === 'barrier') barrierUsed = true;
        // Set barrier2Used agar tidak bisa dipakai lagi di ronde ini
        if (id === 'double_damage') barrier2Used = true;
        // Set godAttackUsed agar tidak bisa dipakai lagi di ronde ini
        if (id === 'god_attack1') godAttackUsed = true;
        // God Attack II juga hanya bisa dipakai 1x per ronde
        if (id === 'god_attack2') godAttackUsed = true;
        // God Attack III juga hanya bisa dipakai 1x per ronde
        if (id === 'god_attack3') godAttackUsed = true;
        // Set gambling2Used agar tidak bisa dipakai lagi di ronde ini
        if (id === 'gambling2') gambling2Used = true;
        // Set gambling3Used agar tidak bisa dipakai lagi di ronde ini
        if (id === 'gambling3') gambling3Used = true;
        // Set criticalAttackActive agar efek crit chance aktif
        if (id === 'critical_attack') criticalAttackActive = true;
        // Drain Life: also notify right away so opponent chip shows ×3
        // (already included above — no extra step needed)
    }
}

// ── APPLY CARD EFFECTS DURING ROUND RESULT ──
// Dipanggil saat menghitung hasil ronde — efek mempengaruhi damage/outcome
// Returns { myDmgOut, oppDmgOut, drainLifeHeal, effectCardOppDmg, drainLifeGamesLeft, gamblingExtraDmg }
function applyActiveEffectsToResult(baseMyDmg, baseOppDmg, iWon, draw) {
    let myDmgOut         = baseMyDmg;
    let oppDmgOut        = baseOppDmg;
    let drainLifeHeal    = 0;
    let effectCardOppDmg = 0;
    let drainLifeGamesLeft  = -1;  // -1 = tidak aktif
    let gamblingExtraDmg    = 0;
    let gamblingGamesLeft   = -1;
    let safePlayGamesLeft   = -1;
    let safePlay2GamesLeft  = -1;
    let godAttackGamesLeft  = -1;  // sisa game god_attack1 setelah tick (-1 = tidak aktif)
    let godAttackMultiplier = 1;   // 1=normal, 2=2x, 3=lucky 3x
    let godAtk2GamesLeft    = -1;  // sisa game god_attack2 setelah tick (-1 = tidak aktif)
    let godAtk2Multiplier   = 1;   // 1=normal, 2=2x, 3=lucky 3x (20%)
    let godAtk3GamesLeft    = -1;  // sisa game god_attack3 setelah tick (-1 = tidak aktif)
    let godAtk3Multiplier   = 1;   // 1=normal, 2=2x, 3=lucky 3x (50%)
    let barrierBroke        = false;
    let shieldAbsorbed      = 0;
    let criticalGamesLeft   = -1;  // sisa game critical_attack setelah tick (-1 = tidak aktif)
    let fullDamageGamesLeft = -1;  // sisa game full_damage setelah tick (-1 = tidak aktif)

    for (const eff of activeEffects) {
        switch (eff.effect) {
            case 'barrier':
                // Barrier hanya aktif saat kalah (bukan draw)
                if (!iWon && !draw) {
                    myDmgOut = Math.floor(myDmgOut * 0.5);
                    eff.gamesLeft = 0;
                    barrierBroke  = true;
                    showCardToast('🔮 Barrier 1 hancur! Kekalahan diserap 50%.', 'common');
                }
                break;
            case 'safe_play1':
                if (!iWon && !draw) myDmgOut = 0;
                if (iWon)  oppDmgOut = Math.floor(oppDmgOut * 0.5);
                eff.gamesLeft = Math.max(0, eff.gamesLeft - 1);
                safePlayGamesLeft = eff.gamesLeft;
                if (eff.gamesLeft <= 0) showCardToast('🛡 Safe Play I — efek berakhir', 'common');
                break;
            case 'safe_play2':
                // Kalah = tidak menerima damage sama sekali; Menang = damage normal (tidak diubah)
                if (!iWon && !draw) myDmgOut = 0;
                // Saat menang: oppDmgOut tetap 100% (tidak ada perubahan)
                eff.gamesLeft = Math.max(0, eff.gamesLeft - 1);
                safePlay2GamesLeft = eff.gamesLeft;
                if (eff.gamesLeft <= 0) showCardToast('🛡 Safe Play II — efek berakhir', 'rare');
                break;
            case 'god_attack1':
                if (iWon) {
                    // 5% chance 3x, sisanya 2x
                    // Jika oppDmgOut = 0 (lawan punya shield), selalu 2x — jangan roll lucky
                    // karena actual damage dihitung dari oppDmgRaw bukan oppDmgOut.
                    const lucky = (oppDmgOut > 0) && (Math.random() < 0.05);
                    const multi = lucky ? 3 : 2;
                    godAttackMultiplier = multi;
                    oppDmgOut = Math.floor(oppDmgOut * multi);
                    // Habis setelah satu kemenangan
                    eff.gamesLeft = 0;
                    godAttackGamesLeft = 0;
                    // Hapus juga dari oppActiveEffects sisi pemilik (bersihkan chip lawan jika ada)
                    oppActiveEffects = oppActiveEffects.filter(e => e.effect_id !== 'god_attack1');
                    if (lucky) {
                        showCardToast('⚡🍀 LUCKY! God Attack I — SERANGAN 3× DAMAGE! Efek berakhir.', 'common');
                    } else {
                        showCardToast('⚡ God Attack I — Serangan 2× damage! Efek berakhir.', 'common');
                    }
                } else {
                    // Belum menang — efek tetap aktif, tidak countdown
                    godAttackGamesLeft = eff.gamesLeft; // tetap 1 (standby)
                }
                break;
            case 'god_attack2':
                if (iWon) {
                    // 20% chance 3x, sisanya 2x
                    // Jika oppDmgOut = 0 (lawan punya shield), selalu 2x — jangan roll lucky
                    const lucky2 = (oppDmgOut > 0) && (Math.random() < 0.20);
                    const multi2 = lucky2 ? 3 : 2;
                    godAtk2Multiplier = multi2;
                    oppDmgOut = Math.floor(oppDmgOut * multi2);
                    // Habis setelah satu kemenangan
                    eff.gamesLeft = 0;
                    godAtk2GamesLeft = 0;
                    // Hapus juga dari oppActiveEffects sisi pemilik (bersihkan chip lawan jika ada)
                    oppActiveEffects = oppActiveEffects.filter(e => e.effect_id !== 'god_attack2');
                    if (lucky2) {
                        showCardToast('⚔️🍀 LUCKY! God Attack II — SERANGAN 3× DAMAGE! Efek berakhir.', 'rare');
                    } else {
                        showCardToast('⚔️ God Attack II — Serangan 2× damage! Efek berakhir.', 'rare');
                    }
                } else {
                    // Belum menang — efek tetap aktif, tidak countdown
                    godAtk2GamesLeft = eff.gamesLeft; // tetap standby
                }
                break;
            case 'god_attack3':
                if (iWon) {
                    // 50% chance 3x, sisanya 2x
                    // Jika oppDmgOut = 0 (lawan punya shield), selalu 2x — jangan roll lucky
                    const lucky3 = (oppDmgOut > 0) && (Math.random() < 0.50);
                    const multi3 = lucky3 ? 3 : 2;
                    godAtk3Multiplier = multi3;
                    oppDmgOut = Math.floor(oppDmgOut * multi3);
                    // Habis setelah satu kemenangan
                    eff.gamesLeft = 0;
                    godAtk3GamesLeft = 0;
                    // Hapus juga dari oppActiveEffects sisi pemilik (bersihkan chip lawan jika ada)
                    oppActiveEffects = oppActiveEffects.filter(e => e.effect_id !== 'god_attack3');
                    if (lucky3) {
                        showCardToast('💀🍀 LUCKY! God Attack III — SERANGAN 3× DAMAGE! Efek berakhir.', 'epic');
                    } else {
                        showCardToast('💀 God Attack III — Serangan 2× damage! Efek berakhir.', 'epic');
                    }
                } else {
                    // Belum menang — efek tetap aktif, tidak countdown
                    godAtk3GamesLeft = eff.gamesLeft; // tetap standby
                }
                break;
            case 'full_damage':
                if (iWon) {
                    // 5× damage normal (20 × 5 = 100) — cukup untuk kill langsung
                    oppDmgOut = oppDmgOut * 5;
                    eff.gamesLeft = 0;
                    fullDamageGamesLeft = 0; // sinyal ke lawan bahwa efek habis
                    // Hapus chip dari kedua sisi
                    activeEffects    = activeEffects.filter(e => e.effect !== 'full_damage');
                    oppActiveEffects = oppActiveEffects.filter(e => e.effect_id !== 'full_damage');
                    showCardToast('💥 FULL DAMAGE terpicu! 5× damage = 100 dmg ke lawan! Efek berakhir.', 'legend');
                    renderActiveEffects();
                } else {
                    // Belum menang — efek tetap standby
                    fullDamageGamesLeft = eff.gamesLeft; // masih 999 (standby)
                }
                break;
            case 'gambling1':
                if (iWon)           { oppDmgOut += 10; gamblingExtraDmg = +10; }
                if (!iWon && !draw) { myDmgOut  += 10; gamblingExtraDmg = -10; }
                eff.gamesLeft = Math.max(0, eff.gamesLeft - 1);
                gamblingGamesLeft = eff.gamesLeft;   // ← tangkap SETELAH decrement, seperti drain_life
                if (eff.gamesLeft <= 0) showCardToast('🎲 Gambling I — efek berakhir', 'common');
                break;
            case 'gambling2':
                if (iWon)           { oppDmgOut += 30; gamblingExtraDmg = +30; showCardToast('🃏 Gambling II — Menang! +30 bonus damage ke lawan!', 'rare'); }
                if (!iWon && !draw) { myDmgOut  += 30; gamblingExtraDmg = -30; showCardToast('🃏 Gambling II — Kalah! +30 damage ekstra ke kamu!', 'rare'); }
                eff.gamesLeft = Math.max(0, eff.gamesLeft - 1);
                gamblingGamesLeft = eff.gamesLeft;
                if (eff.gamesLeft <= 0) showCardToast('🃏 Gambling II — efek berakhir', 'rare');
                break;
            case 'gambling3':
                if (iWon)           { oppDmgOut += 50; gamblingExtraDmg = +50; showCardToast('🎰 Gambling III — Menang! +50 bonus damage ke lawan!', 'epic'); }
                if (!iWon && !draw) { myDmgOut  += 20; gamblingExtraDmg = -20; showCardToast('🎰 Gambling III — Kalah! +20 damage ekstra ke kamu!', 'epic'); }
                eff.gamesLeft = Math.max(0, eff.gamesLeft - 1);
                gamblingGamesLeft = eff.gamesLeft;
                if (eff.gamesLeft <= 0) showCardToast('🎰 Gambling III — efek berakhir', 'epic');
                break;
            case 'double_damage':
                // Barrier 2: saat kalah, damage dikurangi menjadi 25%, lalu hancur
                if (!iWon && !draw) {
                    myDmgOut = Math.floor(myDmgOut * 0.25);
                    eff.gamesLeft = 0;
                    barrierBroke  = true; // gunakan flag barrierBroke agar chip terhapus
                    showCardToast('🔮 Barrier 2 hancur! Kekalahan diserap 75%.', 'epic');
                }
                break;
            case 'drain_life':
                // Heal terkumpul, diterapkan di Phase-4 setelah damage
                if (iWon) drainLifeHeal += 10;
                // Selalu kurangi gamesLeft tiap game (menang atau kalah)
                eff.gamesLeft = Math.max(0, eff.gamesLeft - 1);
                drainLifeGamesLeft = eff.gamesLeft;   // ← tangkap SETELAH decrement
                if (eff.gamesLeft <= 0) {
                    showCardToast('🩸 Drain Life 1 — efek berakhir', 'common');
                }
                break;
            case 'drain_life_2':
                // Heal 25 HP untuk diri sendiri + damage 10 ekstra ke lawan saat menang
                if (iWon) {
                    drainLifeHeal += 25;
                    oppDmgOut += 10;
                }
                // Selalu kurangi gamesLeft tiap game (menang atau kalah)
                eff.gamesLeft = Math.max(0, eff.gamesLeft - 1);
                drainLifeGamesLeft = eff.gamesLeft;   // gunakan field yang sama agar sync ke lawan
                if (eff.gamesLeft <= 0) {
                    showCardToast('🩸 Drain Life 2 — efek berakhir', 'epic');
                }
                break;
            case 'critical_attack': {
                // Critical Attack: 50% chance +30 damage ekstra saat menang. Aktif 2 game atau sampai berhasil.
                eff.gamesLeft = Math.max(0, eff.gamesLeft - 1);
                if (iWon) {
                    const critRoll = Math.random() < 0.5;
                    if (critRoll) {
                        effectCardOppDmg += 30;
                        eff.gamesLeft = 0; // langsung habis setelah berhasil
                        showCardToast('⚡ CRITICAL ATTACK! +30 damage ekstra berhasil! Efek berakhir.', 'common');
                    } else {
                        showCardToast('⚡ Critical Attack: Tidak crit kali ini... (' + eff.gamesLeft + ' game tersisa)', 'common');
                    }
                }
                if (eff.gamesLeft <= 0) {
                    criticalAttackActive = false;
                    // Hapus chip dari activeEffects SEKARANG (tidak tunggu tickEffects)
                    activeEffects = activeEffects.filter(e => e.effect !== 'critical_attack');
                    renderActiveEffects(); // langsung update UI chip
                    showCardToast('⚡ Critical Attack — efek berakhir.', 'common');
                    // Kirim hp_sync SEGERA agar chip di sisi lawan langsung hilang
                    // my_hp = -1 / their_hp = -1 sebagai sentinel agar penerima TIDAK update HP bar
                    // (HP yang benar akan dikirim via hp_sync Phase 4 fight overlay)
                    wsSend({
                        type:                'hp_sync',
                        my_hp:               -1,
                        their_hp:            -1,
                        critical_games_left: 0,
                        critical_attack_dmg: effectCardOppDmg > 0 ? effectCardOppDmg : 0,
                    });
                }
                criticalGamesLeft = eff.gamesLeft; // dikirim ke lawan via hp_sync (Phase 4)
                break;
            }
            case 'shield':
                // Shield absorb ditangani di blok setelah loop
                // agar shieldAbsorbed ter-set dan hp_sync terkirim dengan benar.
                // Jangan lakukan absorb di sini agar tidak double-absorb.
                break;
        }
    }

    // Shield absorb (dari shield cards) — terapkan SEBELUM HP berkurang
    // Catatan: untuk god_attack, shield sudah ditangani via _godAttackOverrideMyHp di opponent_hp_sync.
    // applyActiveEffectsToResult berjalan saat kita KALAH dari lawan yang TIDAK punya god_attack,
    // atau saat damage berasal dari kartu lain (gambling, dll). Shield absorb di sini untuk kasus itu.
    // Jika lawan punya god_attack aktif, Phase 4 akan pakai _godAttackOverrideMyHp (bukan newMyHp dari server),
    // sehingga shield yang sudah diserap di _godActualDmg block tidak akan di-absorb lagi di sini.
    if (myShield > 0 && myDmgOut > 0) {
        const absorbed = Math.min(myShield, myDmgOut);
        shieldAbsorbed = absorbed;
        myShield = Math.max(0, myShield - absorbed);
        myDmgOut = Math.max(0, myDmgOut - absorbed);
        updateShieldDisplay();
        // Flash glow biru pada shield bar
        const fillEl = document.getElementById('p1-shield-fill');
        if (fillEl) {
            fillEl.classList.remove('absorbing');
            void fillEl.offsetWidth;
            fillEl.classList.add('absorbing');
            setTimeout(() => fillEl.classList.remove('absorbing'), 600);
        }
        if (absorbed > 0) {
            spawnDamageFloat(document.querySelector('.battle-area'), `🛡️-${absorbed}`, 'dmg-heal');
            showCardToast(`🛡️ Shield menyerap ${absorbed} damage! Sisa Shield: ${myShield}`, 'common');
            // wsSend dilakukan di Phase 4 setelah myHp final diperbarui
        }
        if (myShield <= 0) {
            // Shield habis — hapus semua efek shield
            activeEffects = activeEffects.filter(e => !e.shield);
            renderActiveEffects();
            showCardToast('🛡️ Shield habis! HP asli tidak lagi terlindungi.', 'common');
            // wsSend dilakukan di Phase 4 setelah myHp final diperbarui
        }
    }

    // ── Hapus barrier / barrier2 langsung setelah hancur ──
    if (barrierBroke) {
        activeEffects = activeEffects.filter(e => e.effect !== 'barrier' && e.effect !== 'double_damage');
        oppActiveEffects = oppActiveEffects.filter(e => e.effect_id !== 'barrier' && e.effect_id !== 'double_damage');
        // Paksa render ulang chip sekarang juga
        renderActiveEffects();
        // Safety: paksa hapus semua chip barrier dari DOM secara langsung
        document.querySelectorAll('.effect-chip').forEach(chip => {
            if (chip.textContent.includes('Barrier')) chip.remove();
        });
    }
    // Hapus drain_life / drain_life_2 yang sudah habis
    activeEffects = activeEffects.filter(e => {
        if (e.effect === 'drain_life') return e.gamesLeft > 0;
        if (e.effect === 'drain_life_2') return e.gamesLeft > 0;
        return true;
    });
    // Hapus gambling yang sudah habis (terpisah, tidak mempengaruhi drain_life)
    activeEffects = activeEffects.filter(e => {
        if (e.effect === 'gambling1') return e.gamesLeft > 0;
        if (e.effect === 'gambling2') return e.gamesLeft > 0;
        if (e.effect === 'gambling3') return e.gamesLeft > 0;
        return true;
    });
    // Hapus safe_play1 yang sudah habis (1 game saja)
    activeEffects = activeEffects.filter(e => {
        if (e.effect === 'safe_play1') return e.gamesLeft > 0;
        return true;
    });
    // Hapus safe_play2 yang sudah habis (1 game saja)
    activeEffects = activeEffects.filter(e => {
        if (e.effect === 'safe_play2') return e.gamesLeft > 0;
        return true;
    });
    // Hapus god_attack1 yang sudah terpicu (gamesLeft === 0 = baru saja menang)
    activeEffects = activeEffects.filter(e => {
        if (e.effect === 'god_attack1') return e.gamesLeft > 0;
        return true;
    });
    // Hapus god_attack2 yang sudah terpicu (gamesLeft === 0 = baru saja menang)
    activeEffects = activeEffects.filter(e => {
        if (e.effect === 'god_attack2') return e.gamesLeft > 0;
        return true;
    });
    // Hapus god_attack3 yang sudah terpicu (gamesLeft === 0 = baru saja menang)
    activeEffects = activeEffects.filter(e => {
        if (e.effect === 'god_attack3') return e.gamesLeft > 0;
        return true;
    });

    // Hapus full_damage yang sudah terpicu (gamesLeft === 0 = baru saja menang)
    activeEffects = activeEffects.filter(e => {
        if (e.effect === 'full_damage') return e.gamesLeft > 0;
        return true;
    });

    // Perbarui chip display setelah gamesLeft berubah
    renderActiveEffects();

    return { myDmgOut, oppDmgOut, drainLifeHeal, effectCardOppDmg, drainLifeGamesLeft, gamblingExtraDmg, gamblingGamesLeft, safePlayGamesLeft, safePlay2GamesLeft, godAttackGamesLeft, godAttackMultiplier, godAtk2GamesLeft, godAtk2Multiplier, godAtk3GamesLeft, godAtk3Multiplier, barrierBroke, shieldAbsorbed, criticalGamesLeft, fullDamageGamesLeft };
}

function addActiveEffect(effect) {
    // Remove duplicate
    activeEffects = activeEffects.filter(e => e.cardId !== effect.cardId);
    activeEffects.push(effect);
    renderActiveEffects();
}

function renderActiveEffects() {
    // ── KRUSIAL: pastikan shield section p1 dan p2 sudah ada di DOM SEBELUM chip di-render ──
    // Ini menjamin urutan DOM: hp-section → shield-section → effects
    // Shield p1 (milik kita)
    if (myShield > 0) {
        updateShieldDisplay();
    }
    // Shield p2 (lawan) — pastikan section sudah ada jika oppShield pernah aktif
    const p2ShieldVal = document.getElementById('p2-shield-val');
    const p2ShieldHpNum = p2ShieldVal ? parseInt(p2ShieldVal.textContent || '0') : 0;
    if (p2ShieldHpNum > 0 || document.getElementById('p2-shield-section')) {
        // Tidak update nilai, hanya pastikan urutan DOM benar
        const p2ss = document.getElementById('p2-shield-section');
        const p2eff = document.getElementById('p2-effects');
        if (p2ss && p2eff && p2ss.nextSibling !== p2eff) {
            p2ss.after(p2eff);
        }
    }

    // ── Render active effects di bawah p1 (player sendiri) ──
    // Urutan DOM yang benar: hp-section → p1-shield-section (jika ada) → p1-effects
    let container = document.getElementById('p1-effects');
    if (!container) {
        container = document.createElement('div');
        container.id = 'p1-effects';
        container.className = 'active-effects';
        document.getElementById('p1-card').appendChild(container);
    }
    // SELALU reposisi ke tempat yang benar — setelah shield-section jika ada, atau setelah hp-section
    const p1Card     = document.getElementById('p1-card');
    const p1Shield   = document.getElementById('p1-shield-section');
    const p1Anchor   = p1Shield || p1Card.querySelector('.hp-section');
    if (p1Anchor && p1Anchor.nextSibling !== container) {
        p1Anchor.after(container);
    }
    container.innerHTML = '';
    activeEffects.forEach(e => {
        const chip = document.createElement('div');
        chip.className = `effect-chip ${e.rarity}`;
        if (e.effect === 'barrier') {
            chip.textContent = `${e.label} 🛡`;
            chip.title = 'Barrier 1 aktif sampai kamu kalah 1x — damage dikurangi 50%';
        } else if (e.effect === 'double_damage') {
            chip.textContent = `${e.label} 🛡`;
            chip.title = 'Barrier 2 aktif sampai kamu kalah 1x — damage dikurangi 75%';
        } else if (e.effect === 'repeat') {
            chip.textContent = `${e.label}`;
            chip.title = 'Repeat aktif — jika kamu kalah game ini, game diulang';
        } else if (e.effect === 'reverse_result') {
            chip.textContent = `${e.label} ×${e.gamesLeft}`;
            chip.title = `Reverse Result aktif — ${e.gamesLeft} kesempatan tersisa. Kalah/Seri → Menang, hanya berkurang saat kalah.`;
        } else if (e.effect === 'block_one_received') {
            chip.textContent = `${e.label}`;
            chip.title = 'Block One! Ronde ini kamu hanya bisa pilih 1 kartu';
            chip.style.animation = 'pulse 1s ease-in-out infinite';
        } else if (e.effect === 'block_one_pending') {
            chip.textContent = `${e.label}`;
            chip.title = 'Block One aktif — kamu tidak punya kartu, efek berlaku sampai ada pemenang ronde ini';
            chip.style.animation = 'pulse 1s ease-in-out infinite';
            chip.style.borderColor = '#ff5e5e';
            chip.style.color = '#ff5e5e';
        } else if (e.effect === 'block_one') {
            chip.textContent = `${e.label}`;
            chip.title = 'Block One aktif — lawan hanya bisa pilih 1 kartu ronde ini';
        } else if (e.effect === 'full_damage') {
            chip.textContent = `${e.label} ⚡`;
            chip.title = 'Full Damage standby — menang pertama = 5× damage (100 dmg)!';
            chip.style.animation = 'legendHandGlow 1.5s ease-in-out infinite';
        } else if (e.shield) {
            chip.textContent = `${e.label}`;
            chip.title = `Shield aktif (${myShield} HP tersisa)`;
        } else {
            chip.textContent = e.gamesLeft < 999 ? `${e.label} ×${e.gamesLeft}` : e.label;
        }
        container.appendChild(chip);
    });
    // Pending effects
    pendingEffects.forEach(e => {
        const chip = document.createElement('div');
        chip.className = `effect-chip pending-chip`;
        chip.textContent = `⏳ ${e.label} (ronde berikutnya)`;
        container.appendChild(chip);
    });

    // ── Render efek LAWAN yang terlihat di bawah p2 ──
    // Urutan DOM yang benar: hp-section → p2-shield-section (jika ada) → p2-effects
    let oppContainer = document.getElementById('p2-effects');
    if (!oppContainer) {
        oppContainer = document.createElement('div');
        oppContainer.id = 'p2-effects';
        oppContainer.className = 'active-effects opp-effects';
        document.getElementById('p2-card').appendChild(oppContainer);
    }
    // SELALU reposisi ke tempat yang benar
    const p2Card   = document.getElementById('p2-card');
    const p2Shield = document.getElementById('p2-shield-section');
    const p2Anchor = p2Shield || p2Card.querySelector('.hp-section');
    if (p2Anchor && p2Anchor.nextSibling !== oppContainer) {
        p2Anchor.after(oppContainer);
    }
    oppContainer.innerHTML = '';
    oppActiveEffects.forEach(e => {
        const chip = document.createElement('div');
        chip.className = `effect-chip ${e.rarity} opp-effect-chip`;
        if (e.effect_id === 'barrier') {
            chip.textContent = `${e.label}`;
            chip.title = 'Barrier 1 lawan aktif — kekalahan pertama mereka diserap 50%';
        } else if (e.effect_id === 'double_damage') {
            chip.textContent = `${e.label}`;
            chip.title = 'Barrier 2 lawan aktif — kekalahan pertama mereka diserap 75%';
        } else if (e.effect_id === 'repeat') {
            chip.textContent = `${e.label}`;
            chip.title = 'Repeat lawan aktif — jika lawan kalah game ini, game diulang';
        } else if (e.effect_id === 'reverse_result') {
            chip.textContent = `${e.label} ×${e.gamesLeft}`;
            chip.title = `Reverse Result lawan — ${e.gamesLeft} kesempatan tersisa. Kalah/Seri lawan → Menang!`;
        } else if (e.effect_id === 'shield1' || e.effect_id === 'shield2' || e.effect_id === 'shield3') {
            chip.textContent = `${e.label}`;
            chip.title = 'Shield lawan aktif';
        } else if (e.effect_id === 'steal_hp') {
            chip.textContent = `${e.label}`;
            chip.title = 'Lawan mengaktifkan Steal HP 1 — Shield aktif dari HP yang dicuri';
        } else if (e.effect_id === 'steal_hp2') {
            chip.textContent = `${e.label}`;
            chip.title = 'Lawan menggunakan Steal HP 2 — mereka mendapat +50 Shield dari HP-mu!';
        } else if (e.effect_id === 'block_one') {
            chip.textContent = `${e.label}`;
            chip.title = 'Block One aktif — lawan hanya bisa pilih 1 kartu ronde ini';
        } else if (e.effect_id === 'full_damage') {
            chip.textContent = `${e.label} ⚡`;
            chip.title = 'Full Damage lawan standby — menang pertama mereka = 100 damage!';
            chip.style.animation = 'legendHandGlow 1.5s ease-in-out infinite';
        } else {
            chip.textContent = e.gamesLeft < 999 ? `${e.label} ×${e.gamesLeft}` : e.label;
        }
        chip.title = chip.title || 'Efek aktif lawan';
        oppContainer.appendChild(chip);
    });
}

// Tampilkan indikator pending di hand card area
function showPendingIndicator(card, label) {
    const hand = document.getElementById('card-hand');
    // Visual badge on hand card
    const usedEls = hand.querySelectorAll('.hand-card.used');
    usedEls.forEach(el => {
        const badge = document.createElement('div');
        badge.className = 'pending-badge';
        badge.textContent = '⏳ Next';
        el.appendChild(badge);
    });
    renderActiveEffects();
}

// myShieldMax: nilai HP shield kita saat pertama aktif (untuk hitung persentase bar)
let myShieldMax = 30;
function updateShieldDisplay() {
    // Update shield bar di bawah HP bar player (p1)
    let shieldSection = document.getElementById('p1-shield-section');
    const isNew = !shieldSection;
    if (isNew) {
        shieldSection = document.createElement('div');
        shieldSection.id = 'p1-shield-section';
        shieldSection.className = 'shield-section';
        shieldSection.innerHTML = `
            <div class="shield-row">
                <span class="shield-label">🛡️ SHIELD</span>
                <span class="shield-val" id="p1-shield-val">0</span>
            </div>
            <div class="shield-track"><div class="shield-fill" id="p1-shield-fill" style="width:0%"></div></div>
        `;
        const p1HpSection = document.getElementById('p1-card').querySelector('.hp-section');
        p1HpSection.after(shieldSection);
    }

    const shieldVal  = document.getElementById('p1-shield-val');
    const shieldFill = document.getElementById('p1-shield-fill');
    if (myShield > 0) {
        shieldSection.style.display = 'block';
        shieldVal.textContent = myShield;
        const pct = Math.max(0, Math.min(100, myShield));
        shieldFill.style.width = pct + '%';
    } else {
        shieldSection.style.display = 'none';
        if (shieldVal)  shieldVal.textContent  = '0';
        if (shieldFill) shieldFill.style.width = '0%';
    }

    // Pastikan p1-effects (chip) tetap di bawah shield section
    const effectsEl = document.getElementById('p1-effects');
    if (effectsEl && shieldSection && shieldSection.nextSibling !== effectsEl) {
        shieldSection.after(effectsEl);
    }
}

// ── Update shield bar LAWAN (p2) — dipanggil saat terima hp_sync dari lawan ──
function updateOppShieldDisplay(shieldHp, shieldMax) {
    let shieldSection = document.getElementById('p2-shield-section');
    const isNew = !shieldSection;
    if (isNew) {
        shieldSection = document.createElement('div');
        shieldSection.id = 'p2-shield-section';
        shieldSection.className = 'shield-section opp-shield';
        shieldSection.innerHTML = `
            <div class="shield-row" style="flex-direction:row-reverse;">
                <span class="shield-label">🛡️ SHIELD</span>
                <span class="shield-val" id="p2-shield-val">0</span>
            </div>
            <div class="shield-track"><div class="shield-fill p2-fill" id="p2-shield-fill" style="width:0%"></div></div>
        `;
        const p2HpSection = document.getElementById('p2-card').querySelector('.hp-section');
        p2HpSection.after(shieldSection);
    }

    const shieldVal  = document.getElementById('p2-shield-val');
    const shieldFill = document.getElementById('p2-shield-fill');
    if (shieldHp > 0) {
        shieldSection.style.display = 'block';
        if (shieldVal)  shieldVal.textContent  = shieldHp;
        const pct = Math.max(0, Math.min(100, shieldHp));
        if (shieldFill) shieldFill.style.width = pct + '%';
    } else {
        shieldSection.style.display = 'none';
        if (shieldVal)  shieldVal.textContent  = '0';
        if (shieldFill) shieldFill.style.width = '0%';
    }

    // Pastikan p2-effects (chip) tetap di bawah shield section lawan
    const oppEffectsEl = document.getElementById('p2-effects');
    if (oppEffectsEl && shieldSection && shieldSection.nextSibling !== oppEffectsEl) {
        shieldSection.after(oppEffectsEl);
    }
}

// ── ABSOLUTE RESET — Reset lokal state ke kondisi awal ──
// isSelf: true jika player ini yang mengaktifkan kartu, false jika menerima dari lawan
function _doAbsoluteResetLocal(isSelf) {
    // Hentikan timer yang sedang berjalan
    stopTimer();
    // Tutup fight overlay jika terbuka
    const fightOv = document.getElementById('fight-overlay');
    if (fightOv) fightOv.classList.remove('show');
    // Tutup card pick overlay jika sedang terbuka
    clearTimeout(cardPickAutoCloseTimeout);
    clearTimeout(cardPickAutoCloseTimer);
    const cardPickOv = document.getElementById('card-pick-overlay');
    if (cardPickOv) cardPickOv.classList.remove('show');

    // Reset semua variabel state
    myHp = oppHp = 100;
    myWins = oppWins = 0;
    round = 1;
    gameNumber = 1;
    roundInGame = 0;
    matchOver = false;
    locked = false;
    activeEffects = [];
    pendingEffects = [];
    oppActiveEffects = [];
    myShield = 0;
    myShieldMax = 30;
    oppShieldMax = 30;
    oppLastChoice = null;
    oppCardPickDone = false;
    cardPickedThisRound = false;
    cardPickPending = false;
    waitingForOpponentCard = false;
    window._lastKnownRound = null;
    barrierUsed   = false;
    barrier2Used  = false;
    godAttackUsed = false;
    gambling2Used = false;
    gambling3Used = false;
    blockOneUsed  = false;
    fullDamageUsed = false;
    criticalAttackActive = false;
    blockOneActive  = false;
    blockOneAsOwner = false;
    window._blockOneStrikePending = false;
    window._blockOneOwner = false;
    _pendingCritFromOpp = 0;
    fightAnimating = false;
    pendingRoundMsg = null;
    myHandCards = [];
    renderCardHand();

    // Update tampilan
    updateHPBar('p1', 100);
    updateHPBar('p2', 100);
    updateDots();
    updateShieldDisplay();
    updateOppShieldDisplay(0);
    renderActiveEffects();

    // Sembunyikan shield section lawan jika ada
    const oppShieldSec = document.getElementById('p2-shield-section');
    if (oppShieldSec) oppShieldSec.style.display = 'none';

    // Update round label
    const roundLabel = document.getElementById('round-label');
    if (roundLabel) roundLabel.textContent = 'RONDE 1';
    const gameBadge = document.getElementById('game-num-badge');
    if (gameBadge) { gameBadge.textContent = 'GAME 1'; gameBadge.style.display = 'inline-block'; }

    if (isSelf) {
        setStatus('♾️ Absolute Reset! Match direset ke Ronde 1 Game 1. Menunggu round_start...', 'yellow');
    }
    // round_start akan datang dari server dan memicu showCardPick via applyRoundStart
}

function tickEffects(isNewRound = false) {
    activeEffects = activeEffects.filter(e => {
        // FIX (17 Mei): Ronde baru (HP direset) → HAPUS SEMUA efek kecuali shield yang masih punya HP
        if (isNewRound) {
            if (e.shield && myShield > 0) return true;
            return false;
        }
        if (e.gamesLeft >= 999) return true; // permanent shields etc
        // drain_life & gambling dikelola di applyActiveEffectsToResult — skip agar tidak double-decrement
        if (e.effect === 'drain_life') return true;  // drain_life: skip (sudah decrement di applyFn)
        if (e.effect === 'drain_life_2') return true; // drain_life_2: skip (sudah decrement di applyFn)
        if (e.effect === 'barrier') return e.gamesLeft > 0; // barrier1: hanya pertahankan jika belum hancur
        if (e.effect === 'double_damage') return e.gamesLeft > 0; // barrier2: hanya pertahankan jika belum hancur
        if (e.effect === 'repeat') return true;  // repeat: jangan di-tick, berakhir via server event
        if (e.effect === 'reverse_result') return e.gamesLeft > 0;  // reverse_result: dikelola di handleRoundResult, jangan di-tick
        if (e.effect === 'gambling1' || e.effect === 'gambling2' || e.effect === 'gambling3') return e.gamesLeft > 0;  // gambling: hapus jika habis
            // Critical Attack: hapus jika habis
        if (e.effect === 'critical_attack') return e.gamesLeft > 0;
        // God Attack I/II/III: hapus jika sudah terpicu (gamesLeft === 0), pertahankan jika masih standby
        if (e.effect === 'god_attack1') return e.gamesLeft > 0;
        if (e.effect === 'god_attack2') return e.gamesLeft > 0;
        if (e.effect === 'god_attack3') return e.gamesLeft > 0;
        // Full Damage: hapus jika sudah terpicu (gamesLeft === 0), pertahankan jika masih standby
        if (e.effect === 'full_damage') return e.gamesLeft > 0;
    // Shield effects: JANGAN dikurangi gamesLeft — shield habis hanya saat HP shield = 0
        // Shield tetap aktif melewati game baru selama myShield > 0
        if (e.shield) {
            if (myShield <= 0) {
                showCardToast(`${e.label} — Shield habis`, e.rarity || 'common');
                return false;
            }
            return true;
        }
        e.gamesLeft--;
        if (e.gamesLeft <= 0) {
            showCardToast(`${e.label} — efek berakhir`, e.rarity || 'common');
        }
        return e.gamesLeft > 0;
    });
    // FIX (17 Mei): Ronde baru → bersihkan juga oppActiveEffects dari efek non-shield
    // Ini mencegah reverse_result dan efek lawan lain bocor ke ronde berikutnya
    if (isNewRound) {
        oppActiveEffects = oppActiveEffects.filter(e =>
            e.effect_id === 'shield1' || e.effect_id === 'shield2' || e.effect_id === 'shield3' ||
            e.effect_id === 'steal_hp' || e.effect_id === 'steal_hp2'  // hanya shield persisten
            // HAPUS: reverse_result lawan tidak boleh dipertahankan saat ronde baru
        );
    }
    updateShieldDisplay();
    renderActiveEffects();
}

// ── CARD TOAST NOTIFICATION ──
function showCardToast(msg, rarity) {
    // Remove existing toasts
    document.querySelectorAll('.card-toast').forEach(t => t.remove());
    const rarityColors = {
        common: '#aaa', rare: 'var(--blue)', epic: '#c084fc', legend: 'var(--accent)'
    };
    const toast = document.createElement('div');
    toast.className = 'card-toast';
    toast.style.color = rarityColors[rarity] || 'var(--text)';
    toast.style.borderColor = rarityColors[rarity] || 'var(--border)';
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

// ── TERAPKAN BURN dari LAWAN ke HP KITA (sisi penerima) ──
// Dipanggil setelah applyRoundStart menerapkan HP dari server
function _applyPendingCritFromOpp() {
    if (_pendingCritFromOpp <= 0) return;
    const critDmg = _pendingCritFromOpp;
    _pendingCritFromOpp = 0;

    const battleArea = document.querySelector('.battle-area');
    let remainDmg = critDmg;

    // Shield menyerap total critDmg (Critical Attack lawan) terlebih dahulu
    if (myShield > 0 && remainDmg > 0) {
        const absorbed = Math.min(myShield, remainDmg);
        myShield = Math.max(0, myShield - absorbed);
        remainDmg = Math.max(0, remainDmg - absorbed);
        updateShieldDisplay();
        const fillEl = document.getElementById('p1-shield-fill');
        if (fillEl) {
            fillEl.classList.remove('absorbing'); void fillEl.offsetWidth;
            fillEl.classList.add('absorbing');
            setTimeout(() => fillEl.classList.remove('absorbing'), 600);
        }
        if (battleArea) spawnDamageFloat(battleArea, '🛡️-' + absorbed, 'dmg-heal');
        showCardToast('🛡️ Shield menyerap ' + absorbed + ' dari Critical Attack! Sisa Shield: ' + myShield, 'common');
        if (myShield <= 0) {
            activeEffects = activeEffects.filter(e => !e.shield);
            renderActiveEffects();
            showCardToast('🛡️ Shield habis!', 'common');
        }
        wsSend({ type: 'hp_sync', my_hp: myHp, their_hp: oppHp, shield_hp: myShield, shield_max: myShieldMax, shield_broke: myShield <= 0 });
    }

    if (remainDmg <= 0) return;

    const prevMyHp = myHp;
    myHp = Math.max(0, myHp - remainDmg);
    if (myHp === prevMyHp) return;

    updateHPBar('p1', myHp);
    flashDamage('p1-hp-bar');
    if (battleArea) spawnDamageFloat(battleArea, '-' + remainDmg + ' ⚡', 'dmg-p1');
    showCardToast(`⚡ Lawan: Critical Attack berhasil! Kamu terkena +${critDmg} damage ekstra!`, 'common');
    setStatus('⚡ Critical Attack lawan berhasil! HP kamu berkurang akibat crit.', 'red');
}

// (Burn Attack dihapus — diganti Critical Attack)
function _applyBurnAttackTick(msg) { return false; }

// ── HOOK INTO ROUND START to show card pick ──
const _origApplyRoundStart = applyRoundStart;
// We patch applyRoundStart to show card pick first, ONCE per round only
window.applyRoundStart = function(msg) {
    const isNewRound = (msg.round !== undefined && msg.round !== window._lastKnownRound);

    // Only on a genuinely new round: reset the pick flag and hand cards
    if (isNewRound) {
        window._lastKnownRound = msg.round;
        cardPickedThisRound = false;
        oppCardPickDone     = false;
        myHandCards = [];
        renderCardHand();
        // Reset flag kartu 1x per game (setiap game baru dalam ronde yang sama)
        blockOneUsed  = false;
        barrierUsed   = false;
        barrier2Used  = false;
        godAttackUsed = false;
        gambling2Used = false;
        gambling3Used = false;
        fullDamageUsed = false;
        tickEffects(true);  // FIX (17 Mei): isNewRound=true → hapus semua efek non-shield
        activatePendingEffects();
        roundInGame++;

        // ── BURN ATTACK: modifikasi HP di msg SEBELUM _origApplyRoundStart ──
        // Ini agar HP dari server langsung sudah dikurangi 5, tidak perlu setTimeout
        if (false) { // Critical attack tidak butuh tick per ronde
            /* no-op */
        }
    }

    // Card pick: muncul di SETIAP ronde, termasuk ronde pertama
    const shouldShowCardPick = !cardPickedThisRound;

    if (shouldShowCardPick) {
        cardPickedThisRound = true;
        showCardPick();

        const _pickWatcher = setInterval(() => {
            if (!cardPickPending && !waitingForOpponentCard) {
                clearInterval(_pickWatcher);
                _origApplyRoundStart(msg);
                renderCardHand();
            }
        }, 150);
    } else {
        _origApplyRoundStart(msg);
    }
};

// Aktivasi pending effects ke activeEffects
function activatePendingEffects() {
    if (pendingEffects.length === 0) return;
    pendingEffects.forEach(effect => {
        activeEffects = activeEffects.filter(e => e.cardId !== effect.cardId);
        activeEffects.push(effect);
        // Visual notif
        showCardToast(`⚡ ${effect.label} aktif sekarang!`, effect.rarity || 'common');
    });
    pendingEffects = [];
    renderActiveEffects();
}

/* ══ ANIMATED BACKGROUND — Node network + twinkling stars ══ */
(function() {
    const cv = document.getElementById('bg');
    if (!cv) return;
    const cx = cv.getContext('2d');
    let W, H, NS = [];
    const COLS = ['rgba(255,77,77,','rgba(79,172,254,','rgba(125,255,77,'];
    function rsz(){W=cv.width=innerWidth;H=cv.height=innerHeight;}
    function mkN(){NS=Array.from({length:65},()=>({
        x:Math.random()*W,y:Math.random()*H,
        vx:(Math.random()-.5)*.5,vy:(Math.random()-.5)*.5,
        r:Math.random()*2.2+.7,col:COLS[Math.floor(Math.random()*3)],
        a:Math.random()*.5+.1,maxA:Math.random()*.5+.1,da:.002
    }));}
    function frame(){
        cx.clearRect(0,0,W,H);
        const g=cx.createRadialGradient(W/2,H*.45,0,W/2,H*.45,Math.max(W,H)*.72);
        g.addColorStop(0,'rgba(14,17,36,.97)');g.addColorStop(1,'rgba(5,6,13,1)');
        cx.fillStyle=g;cx.fillRect(0,0,W,H);
        for(const n of NS){
            n.x+=n.vx;n.y+=n.vy;
            if(n.x<0||n.x>W)n.vx*=-1;if(n.y<0||n.y>H)n.vy*=-1;
            n.a+=n.da;if(n.a>n.maxA||n.a<.05)n.da*=-1;
            for(const m of NS){
                const d=Math.hypot(n.x-m.x,n.y-m.y);
                if(d<160){
                    cx.beginPath();cx.moveTo(n.x,n.y);cx.lineTo(m.x,m.y);
                    cx.strokeStyle=n.col+(1-d/160)*.065+')';cx.lineWidth=.45;cx.stroke();
                }
            }
            cx.beginPath();cx.arc(n.x,n.y,n.r,0,Math.PI*2);
            cx.fillStyle=n.col+n.a+')';cx.fill();
            if(n.r>1.7){cx.beginPath();cx.arc(n.x,n.y,n.r*2.4,0,Math.PI*2);
                cx.fillStyle=n.col+n.a*.18+')';cx.fill();}
        }
        /* twinkling stars */
        for(let i=0;i<130;i++){
            const sx=(i*137.5)%W,sy=(i*93.7)%H;
            const sa=.06+.42*Math.abs(Math.sin(Date.now()*.0008+i));
            cx.beginPath();cx.arc(sx,sy,.55,0,Math.PI*2);
            cx.fillStyle=`rgba(238,240,255,${sa})`;cx.fill();
        }
        requestAnimationFrame(frame);
    }
    window.addEventListener('resize',()=>{rsz();mkN();});
    rsz();mkN();frame();

    /* energy lines */
    const ELC = document.getElementById('EL');
    if(ELC){for(let i=0;i<8;i++){
        const e=document.createElement('div');e.className='el';
        e.style.cssText=`left:${Math.random()*100}%;height:${Math.random()*50+20}px;animation-duration:${Math.random()*9+6}s;animation-delay:${Math.random()*9}s;opacity:.32;`;
        ELC.appendChild(e);
    }}

    /* floating particles */
    const PC = document.getElementById('PT');
    const PC2=['rgba(255,77,77,','rgba(79,172,254,','rgba(125,255,77,'];
    if(PC){for(let i=0;i<25;i++){
        const p=document.createElement('div');p.className='bp';
        const s=Math.random()*4+1,col=PC2[i%3];
        p.style.cssText=`left:${Math.random()*100}%;width:${s}px;height:${s}px;background:${col}${Math.random()*.45+.2});box-shadow:0 0 ${s*3}px ${col}.5);animation-duration:${Math.random()*16+9}s;animation-delay:${Math.random()*16}s;`;
        PC.appendChild(p);
    }}
})();

</script>

<!-- EXIT CONFIRMATION MODAL -->
<div class="exit-overlay" id="exitOverlay" onclick="if(event.target===this)closeExitModal()">
  <div class="exit-modal" id="exitModal">
    <div class="exit-modal-topbar"></div>
    <div class="exit-icon">⚠️</div>
    <div class="exit-title">KELUAR GAME?</div>
    <div class="exit-desc" id="exitDesc">Kamu akan dianggap kalah.<br>Yakin ingin keluar dari Battle Arena?</div>
    <div class="exit-actions">
      <button class="exit-btn exit-btn-cancel" onclick="closeExitModal()">✕ Batal</button>
      <button class="exit-btn exit-btn-confirm" onclick="goMenu()">⏏ Ya, Keluar</button>
    </div>
  </div>
</div>

<!-- AFK NOTIFICATION MODAL -->
<div class="afk-overlay" id="afkOverlay">
  <div class="afk-modal" id="afkModal">
    <div class="afk-modal-topbar"></div>
    <div class="afk-icon">🚨</div>
    <div class="afk-title">LOGOUT AFK</div>
    <div class="afk-desc">Kamu dikeluarkan dari game karena <strong>AFK</strong> (Away From Keyboard).</div>
    <div class="afk-actions">
      <button class="afk-btn" onclick="window.location.href='main_menu.php'">Kembali ke Menu</button>
    </div>
  </div>
</div>

<script src="assets/sound_system.js"></script>
</body>
</html>