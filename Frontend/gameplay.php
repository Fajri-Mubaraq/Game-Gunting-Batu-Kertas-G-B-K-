<?php
session_start();
if (!isset($_SESSION['player_id'])) {
    header('Location: Landing_page.php');
    exit;
}
$player_name = $_SESSION['player_name'] ?? 'PLAYER';
$player_id   = $_SESSION['player_id']   ?? 'player';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>VS AI – Batu Gunting Kertas</title>
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
    --p2-color:    #ff5e5e;
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

/* ════ BACKGROUND LAYERS — identik dengan lobby ════ */
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
.corner{position:fixed;z-index:6;pointer-events:none}
.corner::before,.corner::after{content:'';position:absolute;background:rgba(77,166,255,.5)}
.corner::before{width:2px;height:50px}.corner::after{width:50px;height:2px}
.c-tl{top:20px;left:20px}.c-tr{top:20px;right:20px;transform:scaleX(-1)}
.c-bl{bottom:20px;left:20px;transform:scaleY(-1)}.c-br{bottom:20px;right:20px;transform:scale(-1)}
.corner::before,.corner::after{top:0;left:0}

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
        0 0 120px rgba(255,94,94,.05),
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
            rgba(255,94,94,.04) 100%);
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
        rgba(255,94,94,.5) 70%,
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
        rgba(255,94,94,.07) 100%);
    border-bottom:1px solid rgba(79,172,254,.15);
    position:relative;z-index:1;
}
.game-header::after{
    content:'';position:absolute;bottom:-1px;left:16px;right:16px;height:1px;
    background:linear-gradient(90deg,
        transparent,
        rgba(79,172,254,.5),
        rgba(255,255,255,.3),
        rgba(255,94,94,.4),
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
    background:linear-gradient(90deg,transparent,rgba(79,172,254,.15),rgba(255,94,94,.12),transparent);
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
    background:radial-gradient(circle, rgba(255,94,94,.22) 0%, rgba(255,94,94,.05) 100%);
    box-shadow:0 0 0 3px rgba(255,94,94,.12), 0 0 18px rgba(255,94,94,.3);
}
.pc-name{font-size:.72rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;}
.pc:not(.right) .pc-name{color:var(--p1-color);text-shadow:0 0 12px rgba(79,172,254,.4);}
.right .pc-name{color:var(--p2-color);text-shadow:0 0 12px rgba(255,94,94,.4);}
.pc-you{font-size:.58rem;color:var(--muted);font-style:italic;}
.pc-id{font-size:.58rem;color:var(--muted);letter-spacing:0.5px;margin-bottom:2px;}

/* ── HP BAR — CLEAN MODERN ── */
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

/* P2 Red fill — horizontal coral-red neon gradient */
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
    box-shadow:0 0 10px rgba(255,94,94,.7),0 0 22px rgba(255,94,94,.25);
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
        radial-gradient(ellipse at 80% 50%, rgba(255,94,94,.05) 0%, transparent 60%),
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
   background:linear-gradient(90deg,transparent,rgba(79,172,254,.2),rgba(255,94,94,.15),transparent);}

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
        radial-gradient(ellipse at 75% 80%, rgba(255,94,94,.09) 0%, transparent 45%),
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
    animation:bannerReveal .32s cubic-bezier(.34,1.56,.64,1) forwards;
}
#card-effect-banner.banner-hide{
    animation:bannerHide .22s cubic-bezier(.4,0,1,1) forwards;
}
@keyframes bannerReveal{
    0%  {transform:translate(-50%,-50%) scale(.75) translateY(16px);opacity:0;}
    60% {transform:translate(-50%,-50%) scale(1.03) translateY(-3px);opacity:1;}
    100%{transform:translate(-50%,-50%) scale(1) translateY(0);opacity:1;}
}
@keyframes bannerHide{
    0%  {transform:translate(-50%,-50%) scale(1) translateY(0);opacity:1;}
    100%{transform:translate(-50%,-50%) scale(.88) translateY(-12px);opacity:0;}
}
/* Rarity-specific border glow pulse */
#card-effect-banner.rarity-common { border-color:rgba(200,200,200,.45); box-shadow:0 20px 60px rgba(0,0,0,.85),0 0 20px rgba(180,180,180,.12),inset 0 1px 0 rgba(255,255,255,.07); }
#card-effect-banner.rarity-rare   { border-color:rgba(79,172,254,.6);  box-shadow:0 20px 60px rgba(0,0,0,.85),0 0 28px rgba(79,172,254,.28),inset 0 1px 0 rgba(79,172,254,.1); animation:bannerReveal .32s cubic-bezier(.34,1.56,.64,1) forwards,rarePulse 1s .32s ease-in-out infinite; }
#card-effect-banner.rarity-epic   { border-color:rgba(168,85,247,.65);  box-shadow:0 20px 60px rgba(0,0,0,.85),0 0 32px rgba(168,85,247,.32),inset 0 1px 0 rgba(168,85,247,.1); animation:bannerReveal .32s cubic-bezier(.34,1.56,.64,1) forwards,epicPulse .9s .32s ease-in-out infinite; }
#card-effect-banner.rarity-legend { border-color:rgba(255,215,0,.75);   box-shadow:0 20px 60px rgba(0,0,0,.85),0 0 38px rgba(255,215,0,.4),inset 0 1px 0 rgba(255,215,0,.12); animation:bannerReveal .32s cubic-bezier(.34,1.56,.64,1) forwards,legendPulse .85s .32s ease-in-out infinite; }
@keyframes rarePulse  {0%,100%{box-shadow:0 20px 60px rgba(0,0,0,.85),0 0 22px rgba(79,172,254,.2);}  50%{box-shadow:0 20px 60px rgba(0,0,0,.85),0 0 40px rgba(79,172,254,.45);}}
@keyframes epicPulse  {0%,100%{box-shadow:0 20px 60px rgba(0,0,0,.85),0 0 24px rgba(168,85,247,.22);}  50%{box-shadow:0 20px 60px rgba(0,0,0,.85),0 0 46px rgba(168,85,247,.5);}}
@keyframes legendPulse{0%,100%{box-shadow:0 20px 60px rgba(0,0,0,.85),0 0 30px rgba(255,215,0,.3);}   50%{box-shadow:0 20px 60px rgba(0,0,0,.85),0 0 55px rgba(255,215,0,.6);}}
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
@keyframes cebBounce{0%{transform:scale(0) rotate(-8deg);opacity:0;}55%{transform:scale(1.15) rotate(3deg);}100%{transform:scale(1) rotate(0);opacity:1;}}
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

/* Kartu terbang dari tangan ke tengah layar — lebih bersih */
@keyframes cardThrow{
    0%  {transform:translate(0,0) scale(1) rotate(0deg);opacity:1;}
    25% {transform:translate(calc(var(--tx)*.45),calc(var(--ty)*.45)) scale(1.5) rotate(var(--rot));opacity:1;}
    55% {transform:translate(var(--tx),var(--ty)) scale(2) rotate(calc(var(--rot)*0.3));opacity:1;}
    75% {transform:translate(var(--tx),var(--ty)) scale(1.6) rotate(0deg);opacity:.8;}
    100%{transform:translate(var(--tx),var(--ty)) scale(0) rotate(360deg);opacity:0;}
}
/* Trail glow effect */
@keyframes cardThrowTrail{
    0%  {transform:translate(0,0) scale(1.1);opacity:.35;filter:blur(4px);}
    100%{transform:translate(var(--tx),var(--ty)) scale(0);opacity:0;filter:blur(10px);}
}
.card-throwing{
    position:fixed!important;z-index:2000;pointer-events:none;
    animation:cardThrow .48s cubic-bezier(.22,.68,0,1.2) forwards;
    will-change:transform,opacity,filter;
}
.card-throwing::after{
    content:'';position:absolute;inset:-6px;border-radius:inherit;
    background:inherit;opacity:.4;filter:blur(8px);
    animation:cardThrowTrail .48s cubic-bezier(.22,.68,0,1.2) forwards;
    z-index:-1;
}

/* Screen flash overlay saat kartu aktif — lebih halus, tidak menyilaukan */
#card-activate-flash{
    position:fixed;inset:0;z-index:1999;pointer-events:none;
    opacity:0;border-radius:0;
    will-change:opacity;
}
#card-activate-flash.flash-common{
    background:radial-gradient(ellipse at center,rgba(200,200,200,.25) 0%,transparent 65%);
    animation:screenFlash .35s ease-out forwards;
}
#card-activate-flash.flash-rare{
    background:radial-gradient(ellipse at center,rgba(79,172,254,.3) 0%,transparent 60%);
    animation:screenFlash .38s ease-out forwards;
}
#card-activate-flash.flash-epic{
    background:radial-gradient(ellipse at center,rgba(168,85,247,.35) 0%,transparent 60%);
    animation:screenFlash .42s ease-out forwards;
}
#card-activate-flash.flash-legend{
    background:radial-gradient(ellipse at center,rgba(255,215,0,.4) 0%,transparent 58%);
    animation:screenFlash .45s ease-out forwards;
}
@keyframes screenFlash{
    0%  {opacity:0;}
    18% {opacity:1;}
    55% {opacity:.45;}
    100%{opacity:0;}
}

/* Particle burst — UPGRADED: lebih eksplosif, lebih cepat */
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
    0%  {opacity:0;border-width:0px;border-color:rgba(255,215,0,0);box-shadow:inset 0 0 0 rgba(255,215,0,0);}
    18% {opacity:1;border-width:4px;border-color:rgba(255,215,0,.9);box-shadow:inset 0 0 60px rgba(255,215,0,.2),0 0 60px rgba(255,215,0,.3);}
    45% {opacity:.6;border-width:2px;border-color:rgba(255,215,0,.55);}
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
    box-shadow:0 0 0 rgba(168,85,247,0);
}
#card-epic-vortex.show{
    animation:epicVortex .5s cubic-bezier(.22,.68,0,1.2) forwards;
}
@keyframes epicVortex{
    0%  {transform:translate(-50%,-50%) scale(0) rotate(0deg);opacity:0;box-shadow:0 0 0 rgba(168,85,247,0);}
    22% {transform:translate(-50%,-50%) scale(1.1) rotate(-25deg);opacity:.85;box-shadow:0 0 35px rgba(168,85,247,.5),0 0 70px rgba(168,85,247,.2);}
    55% {transform:translate(-50%,-50%) scale(2.5) rotate(-55deg);opacity:.4;}
    100%{transform:translate(-50%,-50%) scale(5) rotate(-90deg);opacity:0;box-shadow:0 0 0 rgba(168,85,247,0);}
}

/* Banner enhancement: shake untuk counter cards */
#card-effect-banner.counter-active{
    animation:bannerReveal .4s cubic-bezier(.175,.885,.32,1.275) forwards,
              bannerShake  .15s ease-in-out 0.4s 2!important;
}
@keyframes bannerShake{
    0%,100%{transform:translate(-50%,-50%) rotate(0);}
    25%{transform:translate(-50%,-50%) rotate(-2deg) scale(1.02);}
    75%{transform:translate(-50%,-50%) rotate(2deg) scale(1.02);}
}

/* ══════════════════════════════════════════════════════════
   LIGHT MODE THEME
══════════════════════════════════════════════════════════ */



/* ── Light Mode Variables ── */
[data-theme="light"]{
    --bg:          #f0f2f8;
    --dark:        #f0f2f8;
    --mid:         #e4e8f0;
    --card:        rgba(255,255,255,0.92);
    --inner:       #e8ecf4;
    --accent:      #d4a000;
    --blue:        #2874c2;
    --purple:      #9b40d0;
    --green:       #1a9960;
    --red:         #d93636;
    --orange:      #d48400;
    --border:      rgba(0,0,0,0.1);
    --text:        #1a1d26;
    --muted:       rgba(26,29,38,0.5);
    --p1-color:    #2874c2;
    --p2-color:    #d93636;
    --hp-green:    #1a9960;
    --hp-mid:      #c49600;
    --hp-low:      #d93636;
    --line:        rgba(0,20,60,.06);
    --glass:       rgba(0,20,60,.03);
    --faint:       rgba(0,20,60,.05);
}

/* ── Light Mode Body & Background ── */
[data-theme="light"] body{background:#f0f2f8;color:var(--text);}
[data-theme="light"] canvas#bg{opacity:.15;}
[data-theme="light"] .hex-layer{opacity:.02;filter:invert(1);}
[data-theme="light"] .noise{opacity:.015;}
[data-theme="light"] .elines{opacity:.3;}
[data-theme="light"] .el{background:linear-gradient(to bottom,transparent,rgba(40,116,194,.25),transparent);}
[data-theme="light"] .scanline{opacity:.03;}
[data-theme="light"] .vignette{background:radial-gradient(ellipse at center,transparent 50%,rgba(0,0,0,.08) 100%);}
[data-theme="light"] .corner::before,[data-theme="light"] .corner::after{background:rgba(40,116,194,.3);}

/* ── Light Mode Game Container ── */
[data-theme="light"] .game-wrap{
    background:linear-gradient(160deg,rgba(255,255,255,.95) 0%,rgba(240,242,248,.98) 100%);
    box-shadow:0 0 0 1px rgba(40,116,194,.12),0 20px 60px rgba(0,0,0,.1),0 0 40px rgba(40,116,194,.06);
}
[data-theme="light"] .game-wrap::before{
    background:linear-gradient(180deg,rgba(40,116,194,.04) 0%,transparent 18%,transparent 82%,rgba(155,64,208,.03) 100%);
}
[data-theme="light"] .game-wrap::after{
    background:linear-gradient(90deg,transparent,rgba(40,116,194,.3),rgba(155,64,208,.25),transparent);
}

/* ── Light Mode Header ── */
[data-theme="light"] .game-header{
    background:linear-gradient(135deg,rgba(40,116,194,.06) 0%,rgba(240,242,248,.8) 50%,rgba(155,64,208,.04) 100%);
    border-bottom:1px solid rgba(40,116,194,.1);
}
[data-theme="light"] .game-header::after{
    background:linear-gradient(90deg,transparent,rgba(40,116,194,.25),rgba(155,64,208,.2),transparent);
}
[data-theme="light"] .game-title{text-shadow:0 0 12px rgba(212,160,0,.3),0 1px 0 rgba(0,0,0,.15);}
[data-theme="light"] .btn-quit{
    color:rgba(26,29,38,.5);background:rgba(217,54,54,.05);border-color:rgba(217,54,54,.15);
}
[data-theme="light"] .btn-quit:hover{background:rgba(217,54,54,.12);color:var(--red);}
[data-theme="light"] .btn-theme-toggle{background:transparent;border-color:rgba(40,116,194,.18);color:rgba(40,116,194,.8);}
[data-theme="light"] .btn-theme-toggle:hover{background:rgba(40,116,194,.08);border-color:rgba(40,116,194,.35);color:#2874c2;}

/* ── Light Mode Players ── */
[data-theme="light"] .players-row::after{
    background:linear-gradient(90deg,transparent,rgba(40,116,194,.1),rgba(155,64,208,.08),transparent);
}
[data-theme="light"] .pc:not(.right) .pc-avatar{
    background:radial-gradient(circle,rgba(40,116,194,.12) 0%,rgba(40,116,194,.03) 100%);
    box-shadow:0 0 0 3px rgba(40,116,194,.08),0 0 12px rgba(40,116,194,.15);
}
[data-theme="light"] .right .pc-avatar{
    background:radial-gradient(circle,rgba(155,64,208,.12) 0%,rgba(155,64,208,.03) 100%);
    box-shadow:0 0 0 3px rgba(155,64,208,.08),0 0 12px rgba(155,64,208,.15);
}
[data-theme="light"] .pc:not(.right) .pc-name{text-shadow:none;}
[data-theme="light"] .right .pc-name{text-shadow:none;}
[data-theme="light"] .hp-label{color:rgba(0,0,0,.2);}

/* ── Light Mode HP Bar ── */
[data-theme="light"] .hp-val-wrap::before{background:rgba(255,255,255,.85);}
[data-theme="light"] .hp-val-wrap::after{background:linear-gradient(135deg,rgba(0,0,0,.06) 0%,transparent 50%,rgba(0,0,0,.03) 100%);}
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

/* ── Light Mode Battle Area ── */
[data-theme="light"] .battle-area{
    background:radial-gradient(ellipse at 20% 50%,rgba(40,116,194,.04) 0%,transparent 60%),radial-gradient(ellipse at 80% 50%,rgba(155,64,208,.03) 0%,transparent 60%),rgba(245,247,252,.8);
    border-color:rgba(40,116,194,.08);box-shadow:inset 0 0 20px rgba(0,0,0,.03),0 2px 12px rgba(0,0,0,.05);
}
[data-theme="light"] .hand{filter:drop-shadow(0 6px 16px rgba(0,0,0,.15));}

/* ── Light Mode Rounds Row ── */
[data-theme="light"] .rounds-row{
    background:linear-gradient(135deg,rgba(40,116,194,.04) 0%,rgba(255,255,255,.6) 100%);
    border-color:rgba(40,116,194,.08);box-shadow:inset 0 1px 0 rgba(255,255,255,.8),0 2px 8px rgba(0,0,0,.05);
}
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

/* ── Light Mode Status ── */
[data-theme="light"] #status-bar.green{text-shadow:none;}
[data-theme="light"] #status-bar.red{text-shadow:none;}
[data-theme="light"] #status-bar.yellow{text-shadow:none;}
[data-theme="light"] #status-bar.blue{text-shadow:none;}
[data-theme="light"] hr{background:linear-gradient(90deg,transparent,rgba(40,116,194,.12),rgba(155,64,208,.08),transparent);}

/* ── Light Mode Choice Cards ── */
[data-theme="light"] .instruction{color:rgba(0,0,0,.35);}
[data-theme="light"] .choice{
    background:linear-gradient(145deg,rgba(255,255,255,.95),rgba(240,243,250,.98));
    border-color:rgba(0,0,0,.08);box-shadow:0 4px 16px rgba(0,0,0,.06);
}
[data-theme="light"] .choice:hover{box-shadow:0 12px 32px rgba(0,0,0,.1);}
[data-theme="light"] .choice-rock:hover{background:rgba(217,54,54,0.05);border-color:rgba(217,54,54,0.5);box-shadow:0 8px 24px rgba(217,54,54,0.15);}
[data-theme="light"] .choice-scissors:hover{background:rgba(40,116,194,0.05);border-color:rgba(40,116,194,0.5);box-shadow:0 8px 24px rgba(40,116,194,0.15);}
[data-theme="light"] .choice-paper:hover{background:rgba(26,153,96,0.05);border-color:rgba(26,153,96,0.5);box-shadow:0 8px 24px rgba(26,153,96,0.12);}
[data-theme="light"] .choice img{filter:drop-shadow(0 4px 10px rgba(0,0,0,.12));}
[data-theme="light"] .choice-label{color:rgba(26,29,38,.5);}
[data-theme="light"] .choice.selected{background:rgba(212,160,0,0.08)!important;}

/* ── Light Mode Buttons ── */
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
[data-theme="light"] .btn-rematch{box-shadow:0 4px 16px rgba(26,153,96,.2);}
[data-theme="light"] .btn-menu{background:rgba(0,0,0,.04);border-color:rgba(0,0,0,.1);color:var(--muted);}
[data-theme="light"] .btn-menu:hover{background:rgba(0,0,0,.07);}

/* ── Light Mode Spell Cards ── */
[data-theme="light"] .spell-card{background:#fff;box-shadow:0 4px 20px rgba(0,0,0,.08);}
[data-theme="light"] .spell-card.common{background:linear-gradient(160deg,#fafbfc 0%,#f0f2f5 100%);border-color:rgba(150,150,150,.3);}
[data-theme="light"] .spell-card.rare{background:linear-gradient(160deg,#f0f7ff 0%,#e8f0fa 100%);border-color:rgba(40,116,194,.3);}
[data-theme="light"] .spell-card.epic{background:linear-gradient(160deg,#f8f0ff 0%,#f0e8fa 100%);border-color:rgba(155,64,208,.3);}
[data-theme="light"] .spell-card.legend{background:linear-gradient(160deg,#fffbf0 0%,#fff5e0 100%);border-color:rgba(212,160,0,.4);}

/* ── Light Mode Hand Cards ── */
[data-theme="light"] .hand-card{background:#fff;box-shadow:0 3px 10px rgba(0,0,0,.08);}
[data-theme="light"] .hand-card.common{background:linear-gradient(155deg,#fafbfc,#f0f2f5);}
[data-theme="light"] .hand-card.rare{background:linear-gradient(155deg,#f0f7ff,#e8f0fa);}
[data-theme="light"] .hand-card.epic{background:linear-gradient(155deg,#f8f0ff,#f0e8fa);}
[data-theme="light"] .hand-card.legend{background:linear-gradient(155deg,#fffbf0,#fff5e0);}
[data-theme="light"] #card-hand{border-top-color:rgba(40,116,194,.08);background:linear-gradient(180deg,rgba(40,116,194,.02) 0%,transparent 100%);}
[data-theme="light"] .card-hand-label{background:linear-gradient(135deg,#f5f7fb,#eef1f7);border-color:rgba(40,116,194,.1);color:rgba(40,116,194,.4);}
[data-theme="light"] .hand-card-empty{border-color:rgba(40,116,194,.08);background:rgba(40,116,194,.02);color:rgba(0,0,0,.06);}
[data-theme="light"] .hand-card.used::after{background:rgba(255,255,255,.75);color:rgba(0,0,0,.3);}

/* ── Light Mode Card Pick Overlay ── */
[data-theme="light"] #card-pick-overlay{
    background:radial-gradient(ellipse at 25% 20%,rgba(40,116,194,.06) 0%,transparent 45%),radial-gradient(ellipse at 75% 80%,rgba(155,64,208,.06) 0%,transparent 45%),rgba(240,242,248,.97);
}
[data-theme="light"] #card-pick-overlay::before{
    background-image:linear-gradient(rgba(40,116,194,.03) 1px,transparent 1px),linear-gradient(90deg,rgba(40,116,194,.03) 1px,transparent 1px);
}

/* Game badge */
[data-theme="light"] .card-pick-game-badge{
    background:linear-gradient(135deg,rgba(200,120,0,.1),rgba(200,120,0,.05));
    border-color:rgba(200,120,0,.35);
    color:#b86a00;
    box-shadow:none;
}
[data-theme="light"] .card-pick-game-badge::before{
    background:#b86a00;box-shadow:0 0 6px #b86a00;
}

/* Title */
[data-theme="light"] .card-pick-title{
    background:linear-gradient(135deg,#a06000 0%,#c88000 50%,#8a5000 100%);
    -webkit-background-clip:text;background-clip:text;
    filter:none;
}

/* Sub text */
[data-theme="light"] .card-pick-sub{color:rgba(26,29,46,.55);}
[data-theme="light"] .card-pick-sub span{
    color:#b86a00;background:rgba(200,120,0,.1);border-color:rgba(200,120,0,.3);
}

/* Divider */
[data-theme="light"] .cpo-divider{
    background:linear-gradient(90deg,transparent,rgba(180,100,0,.25),rgba(40,116,194,.2),transparent);
}

/* ── Spell cards — white solid backgrounds ── */
[data-theme="light"] .spell-card{
    background:#fff;
    box-shadow:0 4px 18px rgba(0,0,0,.1),0 1px 3px rgba(0,0,0,.06);
}
[data-theme="light"] .spell-card.common{
    background:linear-gradient(160deg,#fafafa 0%,#f4f4f4 100%);
    border-color:rgba(120,120,120,.3);
    box-shadow:0 4px 18px rgba(0,0,0,.09),inset 0 1px 0 rgba(255,255,255,.9);
}
[data-theme="light"] .spell-card.rare{
    background:linear-gradient(160deg,#f0f7ff 0%,#e6f0fb 100%);
    border-color:rgba(40,116,194,.45);
    box-shadow:0 4px 18px rgba(0,0,0,.09),0 0 14px rgba(40,116,194,.08),inset 0 1px 0 rgba(255,255,255,.9);
}
[data-theme="light"] .spell-card.epic{
    background:linear-gradient(160deg,#f8f0ff 0%,#f0e6ff 100%);
    border-color:rgba(155,64,208,.4);
    box-shadow:0 4px 18px rgba(0,0,0,.09),0 0 14px rgba(155,64,208,.08),inset 0 1px 0 rgba(255,255,255,.9);
}
[data-theme="light"] .spell-card.legend{
    background:linear-gradient(160deg,#fffbf0 0%,#fff5d6 100%);
    border-color:rgba(200,140,0,.5);
    box-shadow:0 4px 18px rgba(0,0,0,.09),0 0 18px rgba(200,140,0,.1),inset 0 1px 0 rgba(255,255,255,.9);
}

/* Hover */
[data-theme="light"] .spell-card.common:hover{
    border-color:rgba(80,80,80,.7);
    box-shadow:0 14px 36px rgba(0,0,0,.15),0 0 20px rgba(100,100,100,.1);
}
[data-theme="light"] .spell-card.rare:hover{
    border-color:rgba(40,116,194,.85);
    box-shadow:0 14px 36px rgba(0,0,0,.15),0 0 28px rgba(40,116,194,.2);
}
[data-theme="light"] .spell-card.epic:hover{
    border-color:rgba(155,64,208,.8);
    box-shadow:0 14px 36px rgba(0,0,0,.15),0 0 28px rgba(155,64,208,.2);
}
[data-theme="light"] .spell-card.legend:hover{
    border-color:rgba(200,140,0,.9);
    box-shadow:0 14px 36px rgba(0,0,0,.15),0 0 32px rgba(200,140,0,.25);
}

/* Selected */
[data-theme="light"] .spell-card.common.selected-card{
    background:linear-gradient(160deg,#f0f0f0,#e8e8e8)!important;
    box-shadow:0 0 28px rgba(100,100,100,.35),0 12px 32px rgba(0,0,0,.12)!important;
}
[data-theme="light"] .spell-card.rare.selected-card{
    background:linear-gradient(160deg,#e0efff,#d4e8fc)!important;
    box-shadow:0 0 32px rgba(40,116,194,.4),0 12px 32px rgba(0,0,0,.12)!important;
}
[data-theme="light"] .spell-card.epic.selected-card{
    background:linear-gradient(160deg,#efe0ff,#e4d2ff)!important;
    box-shadow:0 0 32px rgba(155,64,208,.4),0 12px 32px rgba(0,0,0,.12)!important;
}
[data-theme="light"] .spell-card.legend.selected-card{
    background:linear-gradient(160deg,#fff0c0,#ffe898)!important;
    box-shadow:0 0 40px rgba(200,140,0,.55),0 12px 32px rgba(0,0,0,.12)!important;
}

/* Rarity badges */
[data-theme="light"] .common .card-rarity{background:rgba(100,100,100,.07);color:#666;border-color:rgba(100,100,100,.2);}
[data-theme="light"] .common .card-rarity::before{background:#888;box-shadow:none;}
[data-theme="light"] .rare .card-rarity{background:rgba(40,116,194,.08);color:#1a6bbf;border-color:rgba(40,116,194,.3);}
[data-theme="light"] .rare .card-rarity::before{background:#1a6bbf;box-shadow:none;}
[data-theme="light"] .epic .card-rarity{background:rgba(155,64,208,.08);color:#8b39c4;border-color:rgba(155,64,208,.3);}
[data-theme="light"] .epic .card-rarity::before{background:#8b39c4;box-shadow:none;}
[data-theme="light"] .legend .card-rarity{background:rgba(200,140,0,.08);color:#a07200;border-color:rgba(200,140,0,.35);}
[data-theme="light"] .legend .card-rarity::before{background:#c8a000;box-shadow:none;animation:none;}

/* Card names */
[data-theme="light"] .common .card-name{color:#222;}
[data-theme="light"] .rare .card-name{color:#1a5fa8;}
[data-theme="light"] .epic .card-name{color:#7c28c0;}
[data-theme="light"] .legend .card-name{
    background:linear-gradient(135deg,#a06000 0%,#c08000 50%,#8a4500 100%);
    -webkit-background-clip:text;background-clip:text;
}

/* Card desc */
[data-theme="light"] .card-desc{color:rgba(26,29,46,.55);}

/* Card divider */
[data-theme="light"] .card-divider{background:linear-gradient(90deg,transparent,rgba(0,0,0,.1),transparent);}

/* Timing label */
[data-theme="light"] .card-timing-label.instant{
    background:rgba(15,122,48,.08);color:#0f7a30;border-color:rgba(15,122,48,.25);
}
[data-theme="light"] .card-timing-label.next-round{
    background:rgba(180,100,0,.08);color:#b86a00;border-color:rgba(180,100,0,.28);
}

/* Icon drop-shadow softer */
[data-theme="light"] .card-icon{filter:drop-shadow(0 2px 6px rgba(0,0,0,.15));}
[data-theme="light"] .rare .card-icon{animation:none;}
[data-theme="light"] .epic .card-icon{animation:none;}
[data-theme="light"] .legend .card-icon{animation:none;}

/* Timer bar */
[data-theme="light"] .card-timer-bar{background:rgba(0,0,0,.06);border-color:rgba(0,0,0,.08);}

/* Skip button */
[data-theme="light"] .btn-skip-card{
    background:rgba(0,0,0,.05);border-color:rgba(0,0,0,.12);
    color:rgba(26,29,46,.6);
}
[data-theme="light"] .btn-skip-card:hover{
    background:rgba(0,0,0,.1);color:rgba(26,29,46,.85);border-color:rgba(0,0,0,.22);
}

/* Confirm button */
[data-theme="light"] .btn-confirm-card{
    background:linear-gradient(135deg,#12b06a,#0f8f52,#0a7240) !important;
    color:#fff !important;border-color:transparent !important;
    box-shadow:0 4px 16px rgba(15,122,48,.3) !important;
}
[data-theme="light"] .btn-confirm-card.ready:hover{
    box-shadow:0 8px 28px rgba(15,122,48,.45) !important;
}

/* Waiting state */
[data-theme="light"] .cpw-sub{color:rgba(26,29,46,.55);}
[data-theme="light"] .cpw-title{
    background:linear-gradient(135deg,#7a5000,#c08000);
    -webkit-background-clip:text;background-clip:text;filter:none;
}
[data-theme="light"] .cpw-spinner{border-color:rgba(180,100,0,.12);border-top-color:#c08000;box-shadow:none;}
[data-theme="light"] .cpw-opp-status{background:rgba(0,0,0,.03);border-color:rgba(0,0,0,.08);color:rgba(26,29,46,.55);}
[data-theme="light"] .cpw-card-chip{background:rgba(255,255,255,.8);border-color:rgba(0,0,0,.1);color:#1a1d2e;}
[data-theme="light"] .cpw-card-chip.rare{border-color:rgba(40,116,194,.4);color:#1a5fa8;background:rgba(40,116,194,.05);}
[data-theme="light"] .cpw-card-chip.epic{border-color:rgba(155,64,208,.4);color:#7c28c0;background:rgba(155,64,208,.05);}
[data-theme="light"] .cpw-card-chip.legend{border-color:rgba(200,140,0,.5);color:#a06000;background:rgba(200,140,0,.05);}

/* ── Light Mode Popups & Toasts ── */
[data-theme="light"] .card-popup-box{background:rgba(255,255,255,.95);border-color:rgba(0,0,0,.1);}
[data-theme="light"] #card-use-popup{background:rgba(0,0,0,.2);}
[data-theme="light"] .popup-card-desc{color:var(--muted);}
[data-theme="light"] .btn-cancel-card{background:rgba(0,0,0,.04);border-color:rgba(0,0,0,.1);color:var(--muted);}
[data-theme="light"] .card-toast{background:rgba(255,255,255,.95);border-color:rgba(0,0,0,.08);box-shadow:0 8px 24px rgba(0,0,0,.1);}

/* ── Light Mode Effect Chips ── */
[data-theme="light"] .effect-chip.common{background:rgba(150,150,150,.08);}
[data-theme="light"] .effect-chip.rare{background:rgba(40,116,194,.08);}
[data-theme="light"] .effect-chip.epic{background:rgba(155,64,208,.08);}
[data-theme="light"] .effect-chip.legend{background:rgba(212,160,0,.08);}

/* ── Light Mode Fight Overlay ── */
[data-theme="light"] #fight-overlay{
    background:radial-gradient(ellipse at center,rgba(240,242,248,.96) 0%,rgba(230,233,240,.98) 100%);
}
[data-theme="light"] #fight-banner{text-shadow:0 0 30px rgba(212,160,0,.5),0 0 60px rgba(212,160,0,.2),3px 3px 0 rgba(138,106,0,.3);}
[data-theme="light"] .fight-weapon{filter:drop-shadow(0 4px 12px rgba(0,0,0,.15));}
[data-theme="light"] #fight-result-text{text-shadow:none;}

/* ── Light Mode Effect Banner ── */
[data-theme="light"] #card-effect-banner{
    background:linear-gradient(160deg,rgba(255,255,255,.96) 0%,rgba(245,247,252,.98) 100%);
    border-color:rgba(0,0,0,.1);box-shadow:0 16px 50px rgba(0,0,0,.12);
}

/* ── Light Mode Shield ── */
[data-theme="light"] .shield-track {
    background: rgba(0, 0, 0, 0.04);
    border-color: rgba(40, 116, 194, 0.25);
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05);
}

/* ── Light Mode Waiting ── */
[data-theme="light"] .waiting-weapon img{filter:drop-shadow(0 6px 12px rgba(0,0,0,.1));}

/* ── Light Mode Timer ── */
[data-theme="light"] circle.track{stroke:rgba(0,0,0,.08);}
[data-theme="light"] .timer-num{text-shadow:0 0 8px rgba(212,160,0,.3);}

/* ── Smooth transition for theme changes ── */
body,
.game-wrap,.game-header,.battle-area,.choice,.hand-card,.spell-card,
.hp-track,.rounds-row,#card-hand,.card-hand-label,.hp-val-wrap::before,
#card-pick-overlay,#fight-overlay,#card-effect-banner,.card-popup-box,
.card-toast,.effect-chip,.btn-quit,.btn-theme-toggle,.dot,.hand-card-empty,.btn-skip-card,.btn-confirm-card{
    transition:background .4s ease,border-color .4s ease,box-shadow .4s ease,color .4s ease;
}

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
button:not(.btn-theme-toggle):not(.btn-setting):not(.m-close):not(.btn-close-modal):not(.lb2-close-btn):not(.btn-close):not(.ss-mute-btn):not(.xbtn-cancel):not(.exit-btn-cancel):not(.exit-btn-confirm):not(.nav-btn.danger):not(.av-edit-btn):not(.edit-name-btn):not(.avm-refresh-btn):not(.toggle-pw):not(.btn-cancel-card):not(.btn-quit):not(.modal-tab):not(.btn-skip-card):not(.btn-confirm-card):not(.btn-continue),
.btn, .mbtn, .cta, .btn-submit, .btn-to-login,
.nav-btn:not(.danger),
a.btn, .xbtn-battle, .lb2-act-btn, .btn-save, .chat-send-btn, .btn-rematch, .btn-use-card {
  background: var(--text) !important;
  color: var(--dark) !important;
  border-color: var(--border) !important;
}
button:not(.btn-theme-toggle):not(.btn-setting):not(.m-close):not(.btn-close-modal):not(.lb2-close-btn):not(.btn-close):not(.ss-mute-btn):not(.xbtn-cancel):not(.exit-btn-cancel):not(.exit-btn-confirm):not(.nav-btn.danger):not(.av-edit-btn):not(.edit-name-btn):not(.avm-refresh-btn):not(.toggle-pw):not(.btn-cancel-card):not(.btn-quit):not(.modal-tab):not(.btn-skip-card):not(.btn-confirm-card):not(.btn-continue):hover,
.btn:hover, .mbtn:hover, .cta:hover, .btn-submit:hover, .btn-to-login:hover,
.nav-btn:not(.danger):hover,
a.btn:hover, .xbtn-battle:hover, .lb2-act-btn:hover, .btn-save:hover, .chat-send-btn:hover, .btn-rematch:hover, .btn-use-card:hover {
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

<!-- ── BACKGROUND LAYERS identik lobby ── -->
<canvas id="bg"></canvas>
<div class="hex-layer"></div>
<div class="noise"></div>
<div class="elines" id="EL"></div>
<div class="scanline"></div>
<div class="vignette"></div>
<div class="particles" id="PT"></div>
<div class="corner c-tl"></div><div class="corner c-tr"></div>
<div class="corner c-bl"></div><div class="corner c-br"></div>

<!-- Card activation effect elements -->
<div id="card-activate-flash"></div>
<div id="card-legend-border"></div>
<div id="card-epic-vortex"></div>

<div class="game-wrap" id="gameWrap">
    <div id="game-main">

        <!-- HEADER -->
        <div class="game-header">
            <div class="game-title">🤖 VS AI</div>
            <button class="btn-quit" id="btnQuit">✕ Keluar</button>
        </div>

        <!-- PLAYERS + HP -->
        <div class="players-row">
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
                        <span class="hp-label">HP</span>
                        <span class="hp-val" id="p1-hp-val">100</span>
                    </div>
                    <div class="hp-track"><div class="hp-fill" id="p1-hp-bar" style="width:100%"></div></div>
                </div>
            </div>

            <div class="vs-badge"><span class="vs-text">VS</span></div>

            <div class="pc right" id="p2-card">
                <div class="pc-info">
                    <div>
                        <div class="pc-name" id="p2-name">COMPUTER</div>
                        <div class="pc-id" id="p2-id">@cpu</div>
                        <div class="pc-you">(AI)</div>
                    </div>
                    <div class="pc-avatar">🤖</div>
                </div>
                <div class="hp-section">
                    <div class="hp-row">
                        <span class="hp-val" id="p2-hp-val">100</span>
                        <span class="hp-label">HP</span>
                    </div>
                    <div class="hp-track"><div class="hp-fill" id="p2-hp-bar" style="width:100%"></div></div>
                </div>
            </div>
        </div>

        <!-- ROUND DOTS + GAME BADGE -->
        <div class="rounds-row">
            <div class="dots" id="p1-dots">
                <div class="dot" id="pd-0"></div>
                <div class="dot" id="pd-1"></div>
            </div>
            <div class="round-center">
                <span class="round-num" id="round-label">RONDE 1</span>
                <span class="game-number-badge" id="game-num-badge" style="display:none">GAME 1</span>
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
                <span class="opp-badge" id="p2-chose-badge">🤖 AI memilih</span>
            </div>
        </div>

        <!-- STATUS BAR -->
        <div id="status-bar">Memuat arena...</div>

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

        <!-- WAITING SCREEN -->
        <div id="waiting-screen" style="display:none">
            <div class="waiting-box">
                <div class="waiting-icon">🤖</div>
                <div class="waiting-title">Sudah memilih! AI sedang berpikir...</div>
                <div class="waiting-sub" id="waiting-sub">Menghitung strategi terbaik...</div>
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
            <button class="btn-continue" id="btn-continue">LANJUTKAN ▶</button>
        </div>

        <!-- MATCH OVER SCREEN -->
        <div id="match-over">
            <div class="match-over-title" id="match-over-title"></div>
            <div class="match-over-sub" id="match-over-sub"></div>
            <div class="match-over-btns">
                <button class="btn-rematch" onclick="resetMatch()">🔄 Main Lagi</button>
                <button class="btn-menu" onclick="goMenu()">↩ Menu</button>
            </div>
        </div>

        <!-- CARD HAND -->
        <div id="card-hand">
            <div class="card-hand-label">✦ KARTU RONDE</div>
            <div class="hand-card-empty"><span style="font-size:.6rem;color:#333;">SLOT 1</span></div>
            <div class="hand-card-empty"><span style="font-size:.6rem;color:#333;">SLOT 2</span></div>
        </div>

    </div><!-- #game-main -->
</div><!-- .game-wrap -->

<!-- CARD PICK OVERLAY -->
<div id="card-pick-overlay">
    <div class="cpo-header">
        <div class="card-pick-game-badge" id="card-pick-game-badge">GAME 1</div>
    </div>
    <div class="card-pick-title">✦ PILIH KARTUMU ✦</div>
    <div class="cpo-divider"></div>
    <div class="card-pick-sub">Pilih <span id="cpo-pick-count">2 dari 3</span> kartu · Konfirmasi sebelum waktu habis</div>
    <div class="card-pick-row" id="card-pick-row"></div>
    <div class="card-timer-bar"><div class="card-timer-fill" id="card-timer-fill"></div></div>
    <div class="card-pick-actions" id="card-pick-actions">
        <button class="btn-confirm-card" id="btn-confirm-card" onclick="confirmCardPick()">
            ✅ Konfirmasi <span class="confirm-counter" id="confirm-counter">0/2</span>
        </button>
        <button class="btn-skip-card" onclick="skipCardPick()">Lewati</button>
    </div>
    <!-- Waiting state (setelah confirm, AI langsung selesai) -->
    <div id="card-pick-waiting">
        <div class="cpw-spinner"></div>
        <div class="cpw-title">Kartu Terkunci!</div>
        <div class="cpw-cards-preview" id="cpw-cards-preview"></div>
        <div class="cpw-sub">AI sedang memilih kartu...<br>Arena siap dibuka.</div>
        <div class="cpw-opp-status">
            <div class="cpw-opp-dot" id="cpw-opp-dot"></div>
            <span id="cpw-opp-label">AI sedang memilih kartu...</span>
        </div>
    </div>
</div>

<!-- CARD USE POPUP -->
<div id="card-use-popup">
    <div class="card-popup-box">
        <div class="popup-card-icon" id="popup-icon">⚡</div>
        <div class="popup-card-name" id="popup-name">NAMA KARTU</div>
        <div class="popup-rarity-tag" id="popup-rarity">COMMON</div>
        <div class="popup-card-desc" id="popup-desc">Deskripsi efek kartu</div>
        <div class="popup-btns">
            <button class="btn-use-card" id="btn-use-card-confirm">⚡ GUNAKAN SEKARANG!</button>
            <button class="btn-cancel-card" onclick="closeCardPopup()">✕ Batal</button>
        </div>
    </div>
</div>

<!-- CARD EFFECT BANNER -->
<div id="card-effect-banner">
    <span class="ceb-icon" id="ceb-icon">⚡</span>
    <span class="ceb-text" id="ceb-text">KARTU AKTIF!</span>
    <span class="ceb-sub" id="ceb-sub"></span>
    <span class="ceb-timing" id="ceb-timing"></span>
</div>

<!-- FIGHT OVERLAY -->
<div id="fight-overlay">
    <div id="fight-banner">⚔️ FIGHT!</div>
    <div class="fight-arena">
        <div class="fight-player-col p1-col">
            <div class="fight-player-name p1" id="fight-p1-name">PLAYER</div>
            <img class="fight-weapon p1" id="fight-weapon-p1" src="assets/Rock.png" alt="">
            <div class="fight-weapon-label" id="fight-label-p1">BATU</div>
        </div>
        <div style="display:flex;flex-direction:column;align-items:center;gap:6px;">
            <div id="fight-vs">VS</div>
        </div>
        <div class="fight-player-col p2-col">
            <div class="fight-player-name p2" id="fight-p2-name">COMPUTER</div>
            <img class="fight-weapon p2" id="fight-weapon-p2" src="assets/Rock.png" alt="">
            <div class="fight-weapon-label" id="fight-label-p2">BATU</div>
        </div>
    </div>
    <div id="fight-result-text"></div>
    <div id="fight-winner-detail"></div>
</div>

<script>
// ═══════════════════════════════════════════════════════
//  CONFIG (identik dengan PvP)
// ═══════════════════════════════════════════════════════
const MY_ID    = <?= json_encode($player_id) ?>;
const MY_NAME  = <?= json_encode($player_name) ?>;
const OPP_NAME = 'COMPUTER';

const CIRC          = 138.2;
const HP_MAX        = 100;
const TIMER_SECS    = 8;
const DAMAGE        = 20;
const ROUNDS_TO_WIN = 2;

const HAND_IMG   = { rock:'assets/Rock.png', paper:'assets/Paper.png', scissors:'assets/Scissors.png' };
const HAND_LABEL = { rock:'🪨 BATU', paper:'📄 KERTAS', scissors:'✂️ GUNTING' };

// ═══════════════════════════════════════════════════════
//  CARD DATABASE (identik dengan PvP)
// ═══════════════════════════════════════════════════════
const CARD_DB = {
    drain_life:      { id:'drain_life',      rarity:'common', icon:'🩸', name:'Drain Life 1',    desc:'Setiap menang: musuh -10 HP ekstra. Aktif 3 game.',          fullDesc:'Setiap kali menang game, musuh kehilangan 10 HP ekstra. Aktif 3 game.' },
    gambling1:       { id:'gambling1',       rarity:'common', icon:'🎲', name:'The Gambling I',  desc:'Menang +10 dmg, kalah +10 dmg diterima. 1x.',               fullDesc:'Menang: damage ke lawan +10. Kalah: damage ke kamu +10. 1 game.' },
    safe_play1:      { id:'safe_play1',      rarity:'common', icon:'🛡', name:'Safe Play I',     desc:'Kalah = 0 dmg. Menang = dmg lawan ×0.5. 1x.',              fullDesc:'Jika kalah: damage ke kamu = 0. Jika menang: damage ke lawan dikurangi 50%. 1 game.' },
    barrier:         { id:'barrier',         rarity:'common', icon:'🔮', name:'Barrier 1',       desc:'Kalah = 50% dmg. Aktif sampai kamu kalah 1x.',              fullDesc:'Jika kamu kalah, damage yang diterima dikurangi 50%. Aktif sampai 1 kekalahan.' },
    critical_attack: { id:'critical_attack', rarity:'common', icon:'⚡', name:'Critical Attack', desc:'+20 damage saat menang. Aktif 2 game.',                      fullDesc:'Setiap kali menang, damage ke lawan bertambah 20. Aktif 2 game.' },
    tie_breaker:     { id:'tie_breaker',     rarity:'common', icon:'⚖️', name:'Tie Breaker',    desc:'Seri → Menang otomatis + -20 HP lawan.',                     fullDesc:'Jika hasilnya seri, kamu otomatis menang dan lawan kehilangan 20 HP.' },
    shield1:         { id:'shield1',         rarity:'common', icon:'🛡️', name:'Shield I',       desc:'+30 HP shield. Menyerap damage musuh.',                      fullDesc:'Mendapatkan 30 Shield HP yang menyerap damage sebelum HP asli berkurang.' },
    god_attack1:     { id:'god_attack1',     rarity:'common', icon:'⚡', name:'God Attack I',   desc:'2× damage saat menang (5% chance 3×). Aktif hingga menang.', fullDesc:'Saat menang pertama, serangan jadi 2× (5% chance 3×). Efek berakhir setelah 1 kemenangan.' },
    gambling2:       { id:'gambling2',       rarity:'rare',   icon:'🃏', name:'The Gambling II', desc:'Menang +30 dmg, kalah +30 dmg diterima. 1x.',               fullDesc:'Menang: +30 damage ke lawan. Kalah: +30 damage ke kamu. 1 game.' },
    block_one:       { id:'block_one',       rarity:'rare',   icon:'🚫', name:'Block One',       desc:'Batasi lawan: hanya boleh pilih 1 kartu ronde ini.',         fullDesc:'Lawan hanya bisa memilih 1 kartu dari 3 di ronde ini.' },
    steal_hp:        { id:'steal_hp',        rarity:'rare',   icon:'💉', name:'Steal HP 1',      desc:'-20 HP lawan → +20 Shield kamu.',                           fullDesc:'Kurangi 20 HP lawan, konversi menjadi +20 Shield untuk kamu.' },
    safe_play2:      { id:'safe_play2',      rarity:'rare',   icon:'🛡', name:'Safe Play II',    desc:'Kalah = 0 dmg. Menang = dmg normal. 1x.',                   fullDesc:'Jika kalah: damage ke kamu = 0. Jika menang: damage normal. 1 game.' },
    god_attack2:     { id:'god_attack2',     rarity:'rare',   icon:'⚔️', name:'God Attack II',  desc:'2× damage saat menang (20% chance 3×). Aktif hingga menang.',fullDesc:'Saat menang pertama, serangan jadi 2× (20% chance 3×). Efek berakhir setelah 1 kemenangan.' },
    shield2:         { id:'shield2',         rarity:'rare',   icon:'🔷', name:'Shield II',       desc:'+60 HP shield.',                                             fullDesc:'Mendapatkan 60 Shield HP yang menyerap damage sebelum HP asli berkurang.' },
    gambling3:       { id:'gambling3',       rarity:'epic',   icon:'🎰', name:'The Gambling III',desc:'Menang +50 dmg, kalah +20 dmg diterima. 1x.',               fullDesc:'Menang: +50 damage ke lawan. Kalah: +20 damage ke kamu. 1 game.' },
    reverse_result:  { id:'reverse_result',  rarity:'epic',   icon:'🔄', name:'Reverse Result',  desc:'Kalah/Seri → Menang. 3 kesempatan.',                         fullDesc:'Mengubah hasil kalah atau seri menjadi kemenangan. 3 kali efek.' },
    god_attack3:     { id:'god_attack3',     rarity:'epic',   icon:'💀', name:'God Attack III',  desc:'2× damage saat menang (50% chance 3×). Aktif hingga menang.',fullDesc:'Saat menang pertama, serangan jadi 2× (50% chance 3×). Efek berakhir setelah 1 kemenangan.' },
    drain_life_2:    { id:'drain_life_2',    rarity:'epic',   icon:'🩸', name:'Drain Life 2',    desc:'Setiap menang: musuh -10 HP & kamu +25 HP. Aktif 3 game.',   fullDesc:'Setiap kali menang, lawan -10 HP ekstra dan kamu +25 HP. Aktif 3 game.' },
    steal_hp2:       { id:'steal_hp2',       rarity:'epic',   icon:'🩻', name:'Steal HP 2',      desc:'-50 HP lawan → +50 Shield kamu.',                           fullDesc:'Kurangi 50 HP lawan, konversi menjadi +50 Shield untuk kamu.' },
    double_damage:   { id:'double_damage',   rarity:'epic',   icon:'🔮', name:'Barrier 2',       desc:'Kalah = 25% dmg. Aktif sampai kamu kalah 1x.',              fullDesc:'Jika kalah, damage dikurangi menjadi 25% dari normal. Aktif sampai 1 kekalahan.' },
    full_damage:     { id:'full_damage',     rarity:'legend', icon:'💥', name:'Full Damage',     desc:'Damage ×5 (total 100)! Aktif hingga pertama kali menang.',   fullDesc:'Saat menang pertama, serangan jadi 5× damage normal (100 damage). Efek berakhir setelah 1 kemenangan.' },
    shield3:         { id:'shield3',         rarity:'legend', icon:'🌟', name:'Shield III',      desc:'+100 shield besar!',                                         fullDesc:'Mendapatkan 100 Shield HP.' },
    absolute_reset:  { id:'absolute_reset',  rarity:'legend', icon:'♾️', name:'Absolute Reset',  desc:'Reset match ke ronde 1 game 1!',                            fullDesc:'Mereset seluruh match ke ronde pertama, game pertama.' },
};

const RARITY_POOL = {
    common: ['drain_life','gambling1','safe_play1','barrier','critical_attack','tie_breaker','shield1','god_attack1'],
    rare:   ['gambling2','block_one','steal_hp','safe_play2','god_attack2','shield2'],
    epic:   ['gambling3','reverse_result','god_attack3','drain_life_2','steal_hp2','double_damage'],
    legend: ['full_damage','shield3','absolute_reset'],
};
// Pool AI — tanpa kartu yang butuh interaksi player
const AI_RARITY_POOL = {
    common: ['drain_life','gambling1','safe_play1','barrier','critical_attack','tie_breaker','shield1','god_attack1'],
    rare:   ['gambling2','safe_play2','god_attack2','shield2'],
    epic:   ['gambling3','reverse_result','god_attack3','drain_life_2','double_damage'],
    legend: ['full_damage','shield3'],
};
const COUNTER_CARDS = new Set([
    'steal_hp','steal_hp2','reverse_result','god_attack3','barrier','absolute_reset',
    'full_damage','drain_life_2','drain_life','gambling1','safe_play1','critical_attack',
    'tie_breaker','shield1','god_attack1','gambling2','safe_play2','god_attack2','shield2',
    'gambling3','double_damage','shield3',
]);

// ═══════════════════════════════════════════════════════
//  STATE — identik dengan PvP
// ═══════════════════════════════════════════════════════
let myHp    = HP_MAX, oppHp   = HP_MAX;
let myWins  = 0,      oppWins = 0;
let round   = 1;
let drawStreak = 0;
let locked  = false, matchOver = false, fightAnimating = false;
let timerInt = null,  timerLeft = TIMER_SECS;
let myLastChoice = 'rock';
let choiceCounts = { rock:0, paper:0, scissors:0 };
let aiCardUsed = {};   // track kartu yang dipakai selama match VS AI {card_id: count}
let matchStartTime = Date.now();

// Card pick state (identik dengan PvP)
let gameNumber          = 1;
let roundInGame         = 0;
let cardPickedThisRound = false;   // hanya reset tiap RONDE baru (seperti PvP)
let cardPickPending     = false;
let cardPickSelected    = [];
let cardPickStartTime   = 0;
let cardPickAutoCloseTimer   = null;
let cardPickAutoCloseTimeout = null;
let pendingCardSlot     = -1;
let _lastKnownRound     = null;

// Player card effects
let myHandCards   = [];
let activeEffects = [];
let myShield = 0, myShieldMax = 30;
let barrierUsed = false, barrier2Used = false, godAttackUsed = false;
let gambling2Used = false, gambling3Used = false, fullDamageUsed = false;
let blockOneUsed  = false;

// AI card effects
let aiHandCards      = [];
let aiActiveEffects  = [];
let oppActiveEffects = [];
let aiShield = 0, aiShieldMax = 30;

// Fight overlay timeout tracking (identik PvP)
let _fightTimeouts = [];

// ═══════════════════════════════════════════════════════
//  INIT
// ═══════════════════════════════════════════════════════
window.addEventListener('load', () => {
    document.getElementById('p1-name').textContent = MY_NAME;
    document.getElementById('p2-name').textContent = OPP_NAME;
    document.getElementById('p1-id').textContent   = '@' + MY_NAME;
    document.getElementById('p2-id').textContent   = '@' + OPP_NAME.toLowerCase();
    document.getElementById('fight-p1-name').textContent = MY_NAME;
    document.getElementById('fight-p2-name').textContent = OPP_NAME;
    updateHPBar('p1', myHp);
    updateHPBar('p2', oppHp);
    updateDots();

    // ── APPLY THEME FROM PREVIOUS PAGE ──
    const savedTheme = localStorage.getItem('rps_theme') || 'dark';
    if (savedTheme === 'light') {
        document.documentElement.setAttribute('data-theme', 'light');
    }


    // Mulai ronde pertama — mirip dengan menerima round_start dari server
    simulateRoundStart(1);
});

// ═══════════════════════════════════════════════════════
//  simulateRoundStart — pengganti 'round_start' dari server
//  Logika ini persis sama dengan patched applyRoundStart di PvP
// ═══════════════════════════════════════════════════════
function simulateRoundStart(roundNum) {
    locked = false;
    fightAnimating = false;

    const isNewRound = (roundNum !== _lastKnownRound);
    if (isNewRound) {
        _lastKnownRound     = roundNum;
        cardPickedThisRound = false;       // ← reset untuk ronde baru (seperti PvP)
        myHandCards         = [];
        renderCardHand();
        barrierUsed   = false;
        barrier2Used  = false;
        godAttackUsed = false;
        gambling2Used = false;
        gambling3Used = false;
        fullDamageUsed= false;
        blockOneUsed  = false;
        tickEffects(true);  // isNewRound=true → hapus semua efek non-shield
        roundInGame = 0;
    }

    // Card pick: muncul di SETIAP ronde (bukan setiap game dalam ronde)
    const shouldShowCardPick = !cardPickedThisRound;

    if (shouldShowCardPick) {
        cardPickedThisRound = true;
        showCardPick();                    // ← identik flow PvP

        // _pickWatcher: tunggu card pick selesai, lalu lanjut ke selection screen
        const _pickWatcher = setInterval(() => {
            if (!cardPickPending) {
                clearInterval(_pickWatcher);
                _origStartRound();         // selection screen + timer
                renderCardHand();
            }
        }, 150);
    } else {
        _origStartRound();
    }
}

// ── _origStartRound: equivalent applyRoundStart setelah card pick ──
// (Di PvP ini adalah _origApplyRoundStart)
function _origStartRound() {
    document.getElementById('round-label').textContent = 'RONDE ' + round;

    // Update game badge
    let gameBadgeEl = document.getElementById('game-num-badge');
    if (gameBadgeEl) {
        gameBadgeEl.textContent     = 'GAME ' + gameNumber;
        gameBadgeEl.style.display   = 'inline-block';
    }

    const _b1 = document.getElementById('p1-chose-badge');
    const _b2 = document.getElementById('p2-chose-badge');
    _b1.classList.remove('show');
    _b2.classList.remove('show');
    // Reset style timeout badge agar tidak membekas dari ronde sebelumnya
    _b1.textContent = '✅ Memilih'; _b1.style.color = ''; _b1.style.borderColor = '';
    _b2.textContent = '🤖 AI memilih'; _b2.style.color = ''; _b2.style.borderColor = '';
    document.getElementById('timeout-msg').textContent = '';
    const btn = document.getElementById('btn-continue');
    if (btn) { btn.disabled = false; btn.textContent = 'LANJUTKAN ▶'; btn.style.animation = ''; }
    resetHandImages();
    showSelectionScreen();
    setStatus('⏱ Pilih sekarang!', 'blue');
    startTimer(TIMER_SECS);

    // Pastikan shield ditampilkan benar
    if (myShield > 0) updateShieldDisplay();
    if (aiShield > 0) updateOppShieldDisplay(aiShield, aiShieldMax);
    renderActiveEffects();
}

// ═══════════════════════════════════════════════════════
//  CARD PICK (identik dengan PvP showCardPick)
// ═══════════════════════════════════════════════════════
function showCardPick() {
    if (matchOver) return;
    cardPickPending   = true;
    cardPickStartTime = Date.now();
    cardPickSelected  = [];

    const gameBadge = document.getElementById('card-pick-game-badge');
    if (gameBadge) gameBadge.textContent = 'GAME ' + gameNumber;

    const subEl = document.querySelector('#card-pick-overlay .card-pick-sub');
    if (subEl) subEl.innerHTML = `Pilih <span id="cpo-pick-count">2 dari 3</span> kartu · Konfirmasi sebelum waktu habis`;

    const offers = generateCardOffer();
    const row    = document.getElementById('card-pick-row');
    row.innerHTML = '';
    offers.forEach(card => {
        const el = document.createElement('div');
        el.className = `spell-card ${card.rarity}`;
        el.dataset.cardId = card.id;
        const isCounter = COUNTER_CARDS.has(card.id);
        el.innerHTML = `
            <div class="card-rarity">${card.rarity.toUpperCase()}</div>
            <div class="card-icon">${card.icon}</div>
            <div class="card-name">${card.name}</div>
            <div class="card-divider"></div>
            <div class="card-desc">${card.desc}</div>
            <div class="card-timing-label instant">Langsung Aktif</div>
        `;
        el.addEventListener('click', () => {
            if (el.classList.contains('selected-card')) {
                el.classList.remove('selected-card');
                cardPickSelected = cardPickSelected.filter(c => c.id !== card.id);
                row.querySelectorAll('.spell-card').forEach(c => {
                    if (cardPickSelected.length < 2) c.classList.remove('dimmed');
                });
            } else {
                if (cardPickSelected.length >= 2) return;
                el.classList.add('selected-card');
                cardPickSelected.push(card);
                if (cardPickSelected.length >= 2) {
                    row.querySelectorAll('.spell-card:not(.selected-card)').forEach(c => c.classList.add('dimmed'));
                }
            }
            updateConfirmButton();
        });
        row.appendChild(el);
    });

    updateConfirmButton();

    // Sembunyikan waiting, tampilkan form
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
    clearTimeout(cardPickAutoCloseTimeout);
    cardPickAutoCloseTimeout = setTimeout(() => fill.classList.add('urgent'), 12000);
    clearTimeout(cardPickAutoCloseTimer);
    cardPickAutoCloseTimer = setTimeout(() => confirmCardPick(true), 15000);

    document.getElementById('card-pick-overlay').classList.add('show');
}

function updateConfirmButton() {
    const btn     = document.getElementById('btn-confirm-card');
    const counter = document.getElementById('confirm-counter');
    const count   = cardPickSelected.length;
    if (counter) counter.textContent = count + '/2';
    if (count >= 1) btn.classList.add('ready');
    else            btn.classList.remove('ready');
}

function confirmCardPick(isAuto = false) {
    clearTimeout(cardPickAutoCloseTimer);
    clearTimeout(cardPickAutoCloseTimeout);
    cardPickAutoCloseTimer = null;

    if (!isAuto) {
        // Manual confirm — tampilkan waiting state sebentar, lalu tutup
        myHandCards      = [...cardPickSelected];
        cardPickSelected = [];

        // Render preview chips
        const preview = document.getElementById('cpw-cards-preview');
        preview.innerHTML = '';
        myHandCards.forEach(card => {
            const chip = document.createElement('div');
            chip.className = `cpw-card-chip ${card.rarity}`;
            chip.textContent = card.icon + ' ' + card.name;
            preview.appendChild(chip);
        });
        if (myHandCards.length === 0) {
            const chip = document.createElement('div');
            chip.className = 'cpw-card-chip';
            chip.textContent = '🚫 Tanpa Kartu';
            preview.appendChild(chip);
        }

        // Sembunyikan form, tampilkan waiting
        document.getElementById('card-pick-actions').style.display   = 'none';
        document.getElementById('card-pick-row').style.display       = 'none';
        document.querySelector('#card-pick-overlay .card-pick-sub').style.display = 'none';
        document.getElementById('card-pick-waiting').classList.add('show');

        // Update dot: AI "instantly" done
        const dot   = document.getElementById('cpw-opp-dot');
        const label = document.getElementById('cpw-opp-label');
        if (dot)   dot.className = 'cpw-opp-dot ready';
        if (label) label.textContent = '✅ AI sudah selesai memilih!';

        // AI picks — langsung selesai (tidak perlu tunggu)
        aiPickCards();

        // Tutup overlay setelah 800ms (mirip cards_ready dari server)
        setTimeout(() => {
            document.getElementById('card-pick-overlay').classList.remove('show');
            cardPickPending = false;
            renderCardHand();
        }, 800);
    } else {
        // Auto-close (timeout)
        myHandCards      = [...cardPickSelected];
        cardPickSelected = [];
        cardPickPending  = false;
        document.getElementById('card-pick-overlay').classList.remove('show');
        aiPickCards();
        renderCardHand();
    }
}

function skipCardPick() {
    myHandCards = [];
    confirmCardPick(true);
}

// AI memilih kartu secara diam-diam (pengganti waitingForOpponentCard)
function aiPickCards() {
    aiHandCards = generateAiCardOffer();
    // Clear AI effects dari ronde ini, pertahankan shield jika masih ada
    aiActiveEffects  = aiActiveEffects.filter(e => e.shield && aiShield > 0);
    oppActiveEffects = oppActiveEffects.filter(e => {
        return ['shield1','shield2','shield3'].includes(e.effect_id) && aiShield > 0;
    });
    // Apply AI cards ke activeEffects
    aiHandCards.forEach(card => applyAiCardEffect(card));
    renderActiveEffects();
    if (aiHandCards.length > 0) {
        showCardToast('🤖 AI memilih ' + aiHandCards.length + ' kartu!', 'common');
    }
}

// ═══════════════════════════════════════════════════════
//  CARD GENERATION
// ═══════════════════════════════════════════════════════
function rollRarity(pool) {
    const roll = Math.random() * 100;
    if (roll < 5)  return 'legend';
    if (roll < 20) return 'epic';
    if (roll < 50) return 'rare';
    return 'common';
}

function generateCardOffer() {
    const offers = []; const usedIds = new Set();
    let attempts = 0;
    while (offers.length < 3 && attempts < 30) {
        attempts++;
        const rarity = rollRarity(RARITY_POOL);
        const pool   = RARITY_POOL[rarity].filter(id => !usedIds.has(id));
        if (!pool.length) continue;
        const id = pool[Math.floor(Math.random() * pool.length)];
        // Spread ke objek baru agar _used dari ronde sebelumnya tidak menular
        usedIds.add(id); offers.push({ ...CARD_DB[id] });
    }
    return offers;
}

function generateAiCardOffer() {
    const cards = []; const usedIds = new Set();
    let attempts = 0;
    while (cards.length < 2 && attempts < 20) {
        attempts++;
        const rarity = rollRarity(AI_RARITY_POOL);
        const pool   = AI_RARITY_POOL[rarity].filter(id => !usedIds.has(id));
        if (!pool.length) continue;
        const id = pool[Math.floor(Math.random() * pool.length)];
        // Spread ke objek baru agar _used tidak bocor antar ronde
        usedIds.add(id); cards.push({ ...CARD_DB[id] });
    }
    return cards;
}

// ═══════════════════════════════════════════════════════
//  AI CARD EFFECT APPLICATION
// ═══════════════════════════════════════════════════════
function applyAiCardEffect(card) {
    const id  = card.id;
    const shieldMap = { shield1:{amt:30,label:'🛡️ Shield I'}, shield2:{amt:60,label:'🔷 Shield II'}, shield3:{amt:100,label:'🌟 Shield III'} };
    if (shieldMap[id]) {
        aiShield += shieldMap[id].amt;
        if (aiShield > aiShieldMax) aiShieldMax = aiShield;
        aiActiveEffects  = aiActiveEffects.filter(e => e.cardId !== id);
        aiActiveEffects.push({ cardId:id, effect:'shield', label:shieldMap[id].label, rarity:card.rarity, gamesLeft:999, shield:true });
        oppActiveEffects = oppActiveEffects.filter(e => e.effect_id !== id);
        oppActiveEffects.push({ effect_id:id, label:shieldMap[id].label, rarity:card.rarity, gamesLeft:999 });
        updateOppShieldDisplay(aiShield, aiShieldMax);
        return;
    }
    const cfg = {
        drain_life:      {gl:3,   label:'🩸 Drain Life 1', eff:'drain_life'},
        drain_life_2:    {gl:3,   label:'🩸 Drain Life 2', eff:'drain_life_2'},
        gambling1:       {gl:1,   label:'🎲 Gambling I',   eff:'gambling1'},
        gambling2:       {gl:1,   label:'🃏 Gambling II',  eff:'gambling2'},
        gambling3:       {gl:1,   label:'🎰 Gambling III', eff:'gambling3'},
        safe_play1:      {gl:1,   label:'🛡 Safe Play I',  eff:'safe_play1'},
        safe_play2:      {gl:1,   label:'🛡 Safe Play II', eff:'safe_play2'},
        barrier:         {gl:999, label:'🔮 Barrier 1',    eff:'barrier'},
        double_damage:   {gl:999, label:'🔮 Barrier 2',    eff:'double_damage'},
        critical_attack: {gl:2,   label:'⚡ Critical Atk', eff:'critical_attack'},
        tie_breaker:     {gl:999, label:'⚖️ Tie Breaker',  eff:'tie_breaker'},
        god_attack1:     {gl:999, label:'⚡ God Atk I',    eff:'god_attack1'},
        god_attack2:     {gl:999, label:'⚔️ God Atk II',   eff:'god_attack2'},
        god_attack3:     {gl:999, label:'💀 God Atk III',  eff:'god_attack3'},
        reverse_result:  {gl:3,   label:'🔄 Reverse',      eff:'reverse_result'},
        full_damage:     {gl:999, label:'💥 Full Damage',   eff:'full_damage'},
    }[id];
    if (!cfg) return;
    aiActiveEffects  = aiActiveEffects.filter(e => e.cardId !== id);
    aiActiveEffects.push({ cardId:id, effect:cfg.eff, label:cfg.label, rarity:card.rarity, gamesLeft:cfg.gl });
    oppActiveEffects = oppActiveEffects.filter(e => e.effect_id !== id);
    oppActiveEffects.push({ effect_id:id, label:cfg.label, rarity:card.rarity, gamesLeft:cfg.gl });
}

// ═══════════════════════════════════════════════════════
//  SEND CHOICE (identik PvP)
// ═══════════════════════════════════════════════════════
//  SEND CHOICE — identik PvP:
//  selection screen TETAP tampil dengan animasi selected/disabled
//  Waiting screen TIDAK ditampilkan dulu (seperti PvP, player lihat
//  pilihan beranimasi sampai fight overlay muncul)
// ═══════════════════════════════════════════════════════
function sendChoice(choice) {
    if (locked || matchOver) return;
    locked = true;
    myLastChoice = choice;
    stopTimer();
    document.getElementById('timeout-msg').textContent = '';
    if (choiceCounts.hasOwnProperty(choice)) choiceCounts[choice]++;

    // ── Highlight pilihan terpilih, dim sisanya (identik PvP) ──
    document.querySelectorAll('.choice').forEach(c => {
        const cChoice = c.getAttribute('onclick')?.match(/sendChoice\('(\w+)'\)/)?.[1];
        if (cChoice === choice) {
            c.classList.add('selected');
        } else {
            c.classList.add('disabled');
            c.style.opacity    = '0.25';
            c.style.transform  = 'scale(0.85)';
            c.style.transition = 'all 0.3s';
        }
    });

    // ── Update waiting-weapon image (dipakai nanti jika user scroll waiting screen) ──
    const wImg = document.getElementById('waiting-weapon-img');
    const wLbl = document.getElementById('waiting-weapon-label');
    if (wImg) wImg.src = HAND_IMG[choice];
    if (wLbl) wLbl.textContent = HAND_LABEL[choice] || choice.toUpperCase();

    // ── Shake animation di battle hands (identik PvP) ──
    const p1h = document.getElementById('p1-hand');
    const p2h = document.getElementById('p2-hand');
    p1h.classList.add('sh-p1');
    p2h.classList.add('sh-p2');
    p1h.src = 'assets/Rock.png';
    p2h.src = 'assets/Question.svg';

    // ── p1-chose-badge langsung muncul (mirip choice_confirmed dari server di PvP) ──
    document.getElementById('p1-chose-badge').classList.add('show');

    // ── Status bar update (identik PvP) ──
    setStatus('✅ Pilihan dikunci! AI sedang memilih...', 'green');

    // ── Setelah 900ms shake selesai (identik timing PvP) ──
    setTimeout(() => {
        p1h.classList.remove('sh-p1');
        p2h.classList.remove('sh-p2');
        // Tampilkan tangan player (identik PvP — setelah shake, tangan ter-update)
        p1h.src = HAND_IMG[choice];

        // ── AI "berpikir" sebentar sebelum resolve ──
        setTimeout(() => {
            // Tentukan pilihan AI segera setelah selesai berpikir
            const cpuChoice = getCpuChoice();
            
            // Langsung tampilkan pilihan AI menggantikan tanda tanya di layar utama
            p2h.src = HAND_IMG[cpuChoice];

            // Badge lawan muncul
            document.getElementById('p2-chose-badge').classList.add('show');
            setStatus('🤖 AI sudah memilih! Memulai pertarungan...', 'blue');

            // Beri jeda 800ms agar player sempat melihat pilihan AI di layar utama sebelum overlay muncul
            setTimeout(() => {
                handleRoundResult(choice, cpuChoice);
            }, 800);
        }, 500);
    }, 900);
}

// ═══════════════════════════════════════════════════════
//  AI CHOICE
// ═══════════════════════════════════════════════════════
function getCpuChoice() {
    return ['rock','paper','scissors'][Math.floor(Math.random() * 3)];
}

function getBaseResult(p, c) {
    if (p === c) return 'draw';
    if ((p==='rock'&&c==='scissors')||(p==='paper'&&c==='rock')||(p==='scissors'&&c==='paper')) return 'win';
    return 'lose';
}

// ═══════════════════════════════════════════════════════
//  HANDLE ROUND RESULT (identik logika PvP)
// ═══════════════════════════════════════════════════════
function handleRoundResult(myChoice, oppChoice) {
    let result = getBaseResult(myChoice, oppChoice);
    let iWon   = result === 'win';
    let draw   = result === 'draw';

    const myHpBefore  = myHp;
    const oppHpBefore = oppHp;

    // ── AI Tie Breaker ──
    const aiTie = aiActiveEffects.find(e => e.effect === 'tie_breaker');
    if (aiTie && draw) {
        draw = false; iWon = false;
        aiTie.gamesLeft = 0;
        showCardToast('⚖️ AI: Tie Breaker terpicu! Seri → AI Menang!', 'common');
    }

    // ── AI Reverse Result ──
    const aiRev = aiActiveEffects.find(e => e.effect === 'reverse_result' && (iWon || draw));
    if (aiRev) {
        iWon = false; draw = false;
        aiRev.gamesLeft = Math.max(0, aiRev.gamesLeft - 1);
        const gl = aiRev.gamesLeft;
        if (gl <= 0) { aiActiveEffects = aiActiveEffects.filter(e => e.effect !== 'reverse_result'); }
        oppActiveEffects = oppActiveEffects.filter(e => e.effect_id !== 'reverse_result');
        if (gl > 0) oppActiveEffects.push({ effect_id:'reverse_result', label:'🔄 Reverse', rarity:'epic', gamesLeft:gl });
        showCardToast(`🔄 AI: Reverse Result! → AI Menang! (${gl} sisa)`, 'epic');
        renderActiveEffects();
    }

    // ── Player Tie Breaker ──
    let tieBreakerTriggered = false;
    const myTie = activeEffects.find(e => e.effect === 'tie_breaker');
    if (myTie && draw) {
        draw = false; iWon = true;
        tieBreakerTriggered = true;
        myTie.gamesLeft = 0;
        activeEffects = activeEffects.filter(e => e.effect !== 'tie_breaker');
        showCardToast('⚖️ Tie Breaker terpicu! Seri → Kamu Menang! -20 HP lawan.', 'common');
        renderActiveEffects();
    }

    // ── Player Reverse Result ──
    let reverseResultTriggered = false;
    const myRev = activeEffects.find(e => e.effect === 'reverse_result' && !iWon);
    if (myRev) {
        iWon = true; draw = false;
        reverseResultTriggered = true;
        myRev.gamesLeft = Math.max(0, myRev.gamesLeft - 1);
        const wasLosing = result === 'lose';
        if (myRev.gamesLeft <= 0) {
            activeEffects = activeEffects.filter(e => e.effect !== 'reverse_result');
            showCardToast(wasLosing ? '🔄 Reverse Result! Kalah → Menang! (Efek habis)' : '🔄 Reverse Result! Seri → Menang! (Efek habis)', 'epic');
        } else {
            showCardToast(`🔄 Reverse Result! → Menang! (${myRev.gamesLeft} kesempatan tersisa)`, 'epic');
        }
        renderActiveEffects();
    }

    if (draw) {
        drawStreak++;
    } else {
        drawStreak = 0;
    }

    let drawDmg = 0;
    if (draw && drawStreak >= 3) {
        drawDmg = 10;
    }

    // ── Base damage ──
    let myDmg  = iWon ? 0 : (draw ? 0 : DAMAGE);
    let oppDmg = iWon ? DAMAGE : (draw ? 0 : 0);
    if (tieBreakerTriggered) oppDmg = DAMAGE;
    if (reverseResultTriggered) { myDmg = 0; oppDmg = DAMAGE; }

    // ── Apply player effects ──
    const pr = applyActiveEffectsToResult(myDmg, oppDmg, iWon, draw);
    myDmg  = pr.myDmgOut;
    oppDmg = pr.oppDmgOut;
    if (reverseResultTriggered) myDmg = 0;

    // ── Apply AI effects ──
    const ar = applyAiEffectsToResult(myDmg, oppDmg, iWon, draw);
    myDmg  = ar.myDmgOut;
    oppDmg = ar.oppDmgOut;

    const drainLifeHeal    = pr.drainLifeHeal || 0;
    const effectCardOppDmg = pr.effectCardOppDmg || 0;

    // ── Final HP ──
    let newMyHp  = reverseResultTriggered
        ? myHpBefore
        : Math.min(100, Math.max(0, myHp  - myDmg - drawDmg) + drainLifeHeal);
    let newOppHp = reverseResultTriggered
        ? Math.max(0, oppHpBefore - DAMAGE)
        : Math.max(0, oppHp - oppDmg - effectCardOppDmg - drawDmg);

    // ── Show fight overlay (identik dengan PvP) ──
    fightAnimating = true;
    renderActiveEffects();
    showFightOverlay(myChoice, oppChoice, iWon, draw, newMyHp, newOppHp, myDmg, oppDmg,
                     drainLifeHeal, effectCardOppDmg, pr.gamblingExtraDmg||0, reverseResultTriggered);
}

// ═══════════════════════════════════════════════════════
//  PLAYER ACTIVE EFFECTS (identik PvP)
// ═══════════════════════════════════════════════════════
function applyActiveEffectsToResult(baseMyDmg, baseOppDmg, iWon, draw) {
    let myDmgOut = baseMyDmg, oppDmgOut = baseOppDmg;
    let drainLifeHeal = 0, effectCardOppDmg = 0, gamblingExtraDmg = 0;

    // Shield menyerap damage saat kalah
    if (myShield > 0 && !iWon && !draw && myDmgOut > 0) {
        const absorbed = Math.min(myShield, myDmgOut);
        myShield  = Math.max(0, myShield - absorbed);
        myDmgOut  = Math.max(0, myDmgOut - absorbed);
        const ba  = document.querySelector('.battle-area');
        if (absorbed > 0 && ba) spawnDamageFloat(ba, '🛡-' + absorbed, 'dmg-heal');
        updateShieldDisplay();
        if (myShield <= 0) {
            activeEffects = activeEffects.filter(e => !e.shield);
            showCardToast('🛡️ Shield habis!', 'common');
        }
    }

    for (const eff of activeEffects) {
        if (eff.shield) continue;
        switch (eff.effect) {
            case 'barrier':
                if (!iWon && !draw) { myDmgOut = Math.floor(myDmgOut * 0.5); eff.gamesLeft = 0; showCardToast('🔮 Barrier 1 hancur! Kekalahan diserap 50%.','common'); }
                break;
            case 'double_damage':
                if (!iWon && !draw) { myDmgOut = Math.floor(myDmgOut * 0.25); eff.gamesLeft = 0; showCardToast('🔮 Barrier 2 hancur! Kekalahan diserap 75%.','epic'); }
                break;
            case 'safe_play1':
                if (!iWon && !draw) myDmgOut = 0;
                if (iWon)  oppDmgOut = Math.floor(oppDmgOut * 0.5);
                eff.gamesLeft = Math.max(0, eff.gamesLeft - 1);
                if (!eff.gamesLeft) showCardToast('🛡 Safe Play I — efek berakhir','common');
                break;
            case 'safe_play2':
                if (!iWon && !draw) myDmgOut = 0;
                eff.gamesLeft = Math.max(0, eff.gamesLeft - 1);
                if (!eff.gamesLeft) showCardToast('🛡 Safe Play II — efek berakhir','rare');
                break;
            case 'god_attack1':
                if (iWon) { const l=(Math.random()<0.05); const m=l?3:2; oppDmgOut=Math.floor(oppDmgOut*m); eff.gamesLeft=0; showCardToast(l?'⚡🍀 LUCKY! God Atk I 3×!':'⚡ God Atk I 2×! Efek berakhir.','common'); }
                break;
            case 'god_attack2':
                if (iWon) { const l2=(Math.random()<0.20); const m2=l2?3:2; oppDmgOut=Math.floor(oppDmgOut*m2); eff.gamesLeft=0; showCardToast(l2?'⚔️🍀 LUCKY! God Atk II 3×!':'⚔️ God Atk II 2×! Efek berakhir.','rare'); }
                break;
            case 'god_attack3':
                if (iWon) { const l3=(Math.random()<0.50); const m3=l3?3:2; oppDmgOut=Math.floor(oppDmgOut*m3); eff.gamesLeft=0; showCardToast(l3?'💀🍀 LUCKY! God Atk III 3×!':'💀 God Atk III 2×! Efek berakhir.','epic'); }
                break;
            case 'full_damage':
                if (iWon) { oppDmgOut=oppDmgOut*5; eff.gamesLeft=0; activeEffects=activeEffects.filter(e=>e.effect!=='full_damage'); showCardToast('💥 FULL DAMAGE! 5× damage = '+oppDmgOut+'! Efek berakhir.','legend'); renderActiveEffects(); }
                break;
            case 'gambling1':
                if (iWon)           { oppDmgOut += 10; gamblingExtraDmg = 10; }
                if (!iWon && !draw) { myDmgOut  += 10; gamblingExtraDmg = -10; }
                eff.gamesLeft = Math.max(0, eff.gamesLeft - 1);
                if (!eff.gamesLeft) showCardToast('🎲 Gambling I — efek berakhir','common');
                break;
            case 'gambling2':
                if (iWon)           { oppDmgOut += 30; gamblingExtraDmg = 30; showCardToast('🃏 Gambling II — Menang! +30 damage!','rare'); }
                if (!iWon && !draw) { myDmgOut  += 30; gamblingExtraDmg = -30; showCardToast('🃏 Gambling II — Kalah! +30 damage!','rare'); }
                eff.gamesLeft = Math.max(0, eff.gamesLeft - 1);
                break;
            case 'gambling3':
                if (iWon)           { oppDmgOut += 50; gamblingExtraDmg = 50; showCardToast('🎰 Gambling III — Menang! +50 damage!','epic'); }
                if (!iWon && !draw) { myDmgOut  += 20; gamblingExtraDmg = -20; showCardToast('🎰 Gambling III — Kalah! +20 damage!','epic'); }
                eff.gamesLeft = Math.max(0, eff.gamesLeft - 1);
                break;
            case 'drain_life':
                if (iWon) { effectCardOppDmg += 10; }
                eff.gamesLeft = Math.max(0, eff.gamesLeft - 1);
                if (!eff.gamesLeft) showCardToast('🩸 Drain Life 1 — efek berakhir','common');
                break;
            case 'drain_life_2':
                if (iWon) { effectCardOppDmg += 10; drainLifeHeal = 25; }
                eff.gamesLeft = Math.max(0, eff.gamesLeft - 1);
                if (!eff.gamesLeft) showCardToast('🩸 Drain Life 2 — efek berakhir','epic');
                break;
            case 'critical_attack':
                if (iWon) { oppDmgOut += 20; showCardToast('⚡ Critical Attack! +20 damage ke lawan!','common'); }
                eff.gamesLeft = Math.max(0, eff.gamesLeft - 1);
                if (!eff.gamesLeft) showCardToast('⚡ Critical Attack — efek berakhir','common');
                break;
        }
    }
    activeEffects = activeEffects.filter(e => e.gamesLeft > 0 || e.shield);
    return { myDmgOut, oppDmgOut, drainLifeHeal, effectCardOppDmg, gamblingExtraDmg };
}

// ── AI effects ──
function applyAiEffectsToResult(baseMyDmg, baseOppDmg, iWon, draw) {
    let myDmgOut = baseMyDmg, oppDmgOut = baseOppDmg;
    const aiWon  = !iWon && !draw;
    const aiLost = iWon;

    // AI shield menyerap player attack
    if (aiShield > 0 && aiLost && oppDmgOut > 0) {
        const absorbed = Math.min(aiShield, oppDmgOut);
        aiShield  = Math.max(0, aiShield - absorbed);
        oppDmgOut = Math.max(0, oppDmgOut - absorbed);
        const ba  = document.querySelector('.battle-area');
        if (absorbed > 0 && ba) spawnDamageFloat(ba, '🛡-' + absorbed, 'dmg-heal');
        updateOppShieldDisplay(aiShield, aiShieldMax);
        if (aiShield <= 0) {
            aiActiveEffects  = aiActiveEffects.filter(e => !e.shield);
            oppActiveEffects = oppActiveEffects.filter(e => !['shield1','shield2','shield3'].includes(e.effect_id));
        }
    }

    for (const eff of aiActiveEffects) {
        if (eff.shield) continue;
        switch (eff.effect) {
            case 'barrier':
                if (aiLost) { oppDmgOut = Math.floor(oppDmgOut * 0.5); eff.gamesLeft = 0; showCardToast('🔮 AI: Barrier 1 aktif! Damage diserap 50%.','common'); }
                break;
            case 'double_damage':
                if (aiLost) { oppDmgOut = Math.floor(oppDmgOut * 0.25); eff.gamesLeft = 0; showCardToast('🔮 AI: Barrier 2 aktif! Damage diserap 75%.','epic'); }
                break;
            case 'safe_play1':
                if (aiLost)  oppDmgOut = 0;
                if (aiWon)   myDmgOut  = Math.floor(myDmgOut * 0.5);
                eff.gamesLeft = Math.max(0, eff.gamesLeft - 1);
                break;
            case 'safe_play2':
                if (aiLost)  oppDmgOut = 0;
                eff.gamesLeft = Math.max(0, eff.gamesLeft - 1);
                break;
            case 'god_attack1':
                if (aiWon) { const l=(Math.random()<0.05); myDmgOut=Math.floor(myDmgOut*(l?3:2)); eff.gamesLeft=0; showCardToast(l?'⚡🍀 AI: God Atk I LUCKY! 3×!':'⚡ AI: God Atk I 2×!','common'); }
                break;
            case 'god_attack2':
                if (aiWon) { const l2=(Math.random()<0.20); myDmgOut=Math.floor(myDmgOut*(l2?3:2)); eff.gamesLeft=0; showCardToast(l2?'⚔️🍀 AI: God Atk II LUCKY! 3×!':'⚔️ AI: God Atk II 2×!','rare'); }
                break;
            case 'god_attack3':
                if (aiWon) { const l3=(Math.random()<0.50); myDmgOut=Math.floor(myDmgOut*(l3?3:2)); eff.gamesLeft=0; showCardToast(l3?'💀🍀 AI: God Atk III LUCKY! 3×!':'💀 AI: God Atk III 2×!','epic'); }
                break;
            case 'full_damage':
                if (aiWon) { myDmgOut=myDmgOut*5; eff.gamesLeft=0; showCardToast('💥 AI: Full Damage! 5× damage!','legend'); }
                break;
            case 'gambling1':
                if (aiWon)  { myDmgOut  += 10; showCardToast('🎲 AI Gambling I: +10 ke kamu','common'); }
                if (aiLost) { oppDmgOut += 10; showCardToast('🎲 AI Gambling I: +10 ke AI', 'common'); }
                eff.gamesLeft = Math.max(0, eff.gamesLeft - 1);
                break;
            case 'gambling2':
                if (aiWon)  { myDmgOut  += 30; showCardToast('🃏 AI Gambling II: +30 ke kamu','rare'); }
                if (aiLost) { oppDmgOut += 30; showCardToast('🃏 AI Gambling II: +30 ke AI', 'rare'); }
                eff.gamesLeft = Math.max(0, eff.gamesLeft - 1);
                break;
            case 'gambling3':
                if (aiWon)  { myDmgOut  += 50; showCardToast('🎰 AI Gambling III: +50 ke kamu','epic'); }
                if (aiLost) { oppDmgOut += 20; showCardToast('🎰 AI Gambling III: +20 ke AI', 'epic'); }
                eff.gamesLeft = Math.max(0, eff.gamesLeft - 1);
                break;
            case 'drain_life':
            case 'drain_life_2':
                if (aiWon) { myDmgOut += 10; showCardToast('🩸 AI Drain Life! +10 ekstra ke kamu','common'); }
                eff.gamesLeft = Math.max(0, eff.gamesLeft - 1);
                break;
            case 'critical_attack':
                if (aiWon) { myDmgOut += 20; showCardToast('⚡ AI Critical Attack! +20 ke kamu','common'); }
                eff.gamesLeft = Math.max(0, eff.gamesLeft - 1);
                break;
        }
    }
    aiActiveEffects  = aiActiveEffects.filter(e => e.gamesLeft > 0 || e.shield);
    // Sync oppActiveEffects
    oppActiveEffects = oppActiveEffects.filter(e => {
        if (['shield1','shield2','shield3'].includes(e.effect_id)) return aiShield > 0;
        return aiActiveEffects.some(a => a.cardId === e.effect_id);
    });
    return { myDmgOut, oppDmgOut };
}

// ═══════════════════════════════════════════════════════
//  FIGHT OVERLAY (identik PvP)
// ═══════════════════════════════════════════════════════
function showFightOverlay(myChoice, oppChoice, iWon, draw, newMyHp, newOppHp,
                          myDmg, oppDmg, drainLifeHeal=0, effectCardOppDmg=0,
                          gamblingExtraDmg=0, reverseResultTriggered=false) {
    const overlay = document.getElementById('fight-overlay');
    const banner  = document.getElementById('fight-banner');
    const p1Img   = document.getElementById('fight-weapon-p1');
    const p2Img   = document.getElementById('fight-weapon-p2');
    const p1Lbl   = document.getElementById('fight-label-p1');
    const p2Lbl   = document.getElementById('fight-label-p2');
    const resText = document.getElementById('fight-result-text');
    const resDet  = document.getElementById('fight-winner-detail');

    document.getElementById('fight-p1-name').textContent = MY_NAME;
    document.getElementById('fight-p2-name').textContent = OPP_NAME;

    p1Img.src = HAND_IMG[myChoice]  || HAND_IMG.rock;
    p2Img.src = HAND_IMG[oppChoice] || HAND_IMG.rock;
    p1Lbl.textContent = HAND_LABEL[myChoice]  || myChoice.toUpperCase();
    p2Lbl.textContent = HAND_LABEL[oppChoice] || oppChoice.toUpperCase();

    p1Img.className = 'fight-weapon p1'; p2Img.className = 'fight-weapon p2';
    p1Img.style.opacity = '0'; p2Img.style.opacity = '0';
    p1Lbl.classList.remove('show'); p2Lbl.classList.remove('show');
    resText.className = ''; resText.textContent = '';
    resDet.className  = ''; resDet.textContent  = '';

    banner.className = ''; void banner.offsetWidth;
    banner.textContent = '⚔️ FIGHT!';
    overlay.classList.add('show');

    _fightTimeouts.forEach(id => clearTimeout(id)); _fightTimeouts = [];
    { const tr=document.getElementById('timer-ring'); tr.classList.remove('urgent'); document.getElementById('timer-num').textContent='⚔️'; document.getElementById('timer-circle').style.strokeDashoffset=CIRC; }

    // Phase 1: slide weapons in
    _fightTimeouts.push(setTimeout(() => { p1Img.style.opacity=''; void p1Img.offsetWidth; p1Img.classList.add('animating-p1'); }, 800));
    _fightTimeouts.push(setTimeout(() => { p2Img.style.opacity=''; void p2Img.offsetWidth; p2Img.classList.add('animating-p2'); }, 950));
    // Phase 2: weapon labels
    _fightTimeouts.push(setTimeout(() => { p1Lbl.classList.add('show'); p2Lbl.classList.add('show'); }, 2000));
    // Phase 3: result glow + text
    _fightTimeouts.push(setTimeout(() => {
        banner.classList.add('shake');
        const p1Result = iWon ? 'win' : draw ? 'draw' : 'lose';
        const p2Result = iWon ? 'lose': draw ? 'draw' : 'win';
        p1Img.style.opacity='1'; p2Img.style.opacity='1';
        void p1Img.offsetWidth; void p2Img.offsetWidth;
        p1Img.classList.add('revealed', p1Result);
        p2Img.classList.add('revealed', p2Result);

        let resultHTML = '', detailText = '';
        const actualDmgToOpp = Math.max(0, oppHp - newOppHp);
        const actualDmgToMe  = Math.max(0, myHp  - newMyHp);
        if (draw) {
            resultHTML = '🤝 SERI!'; resText.style.color = 'var(--accent)';
            if (drawStreak >= 3) {
                detailText = `${HAND_LABEL[myChoice]} vs ${HAND_LABEL[oppChoice]} · 💥 Seri ${drawStreak}x! HP berkurang 10!`;
            } else {
                detailText = `${HAND_LABEL[myChoice]} vs ${HAND_LABEL[oppChoice]} — HP tidak berkurang`;
            }
        } else if (iWon) {
            resultHTML = reverseResultTriggered ? `🔄 REVERSE RESULT! ${MY_NAME} menang!` : `🏆 ${MY_NAME} memenangkan ronde ini!`;
            resText.style.color = reverseResultTriggered ? 'var(--purple)' : 'var(--green)';
            const gIcon = gamblingExtraDmg===50?'🎰':(gamblingExtraDmg===30?'🃏':(gamblingExtraDmg===10?'🎲':''));
            detailText = `${HAND_LABEL[myChoice]} mengalahkan ${HAND_LABEL[oppChoice]} · ${OPP_NAME} -${actualDmgToOpp||oppDmg} HP` + (gamblingExtraDmg>0?` (+${gamblingExtraDmg} ${gIcon})`:'');
            spawnConfetti(overlay);
        } else {
            resultHTML = `💀 Kamu kalah dari ${OPP_NAME}!`; resText.style.color = 'var(--red)';
            const gAbsIcon = Math.abs(gamblingExtraDmg)===20?'🎰':(Math.abs(gamblingExtraDmg)===30?'🃏':'');
            detailText = `${HAND_LABEL[oppChoice]} mengalahkan ${HAND_LABEL[myChoice]} · ${MY_NAME} -${actualDmgToMe||myDmg} HP` + (gamblingExtraDmg<0?` (+${Math.abs(gamblingExtraDmg)} ${gAbsIcon})`:'');
        }
        resText.textContent = resultHTML; resText.classList.add('show');
        _fightTimeouts.push(setTimeout(() => { resDet.textContent = detailText; resDet.classList.add('show'); }, 400));
    }, 2500));

    // Phase 4: update state + result screen
    _fightTimeouts.push(setTimeout(() => {
        overlay.classList.remove('show');
        fightAnimating = false;
        locked = false;

        const p1h = document.getElementById('p1-hand');
        const p2h = document.getElementById('p2-hand');
        p1h.className = 'hand'; p2h.className = 'hand';
        void p1h.offsetWidth; void p2h.offsetWidth;
        p1h.src = HAND_IMG[myChoice]  || HAND_IMG.rock;
        p2h.src = HAND_IMG[oppChoice] || HAND_IMG.rock;
        p1h.classList.add('hand-reveal-p1'); p2h.classList.add('hand-reveal-p2');
        _fightTimeouts.push(setTimeout(() => {
            if (draw)      { p1h.classList.add('hand-clash');  p2h.classList.add('hand-clash-p2'); }
            else if (iWon) { p1h.classList.add('hand-win');    p2h.classList.add('hand-lose-p2'); }
            else           { p2h.classList.add('hand-win');    p1h.classList.add('hand-lose'); }
        }, 600));

        // Update HP
        const prevMyHp = myHp, prevOppHp = oppHp;
        myHp  = newMyHp;
        oppHp = newOppHp;
        if (Math.max(0,prevMyHp-myHp)  > 0) flashDamage('p1-hp-bar');
        if (Math.max(0,prevOppHp-oppHp)> 0) flashDamage('p2-hp-bar');
        if (Math.max(0,myHp-prevMyHp)  > 0) flashHeal('p1-hp-bar');
        _fightTimeouts.push(setTimeout(() => {
            updateHPBar('p1', myHp); updateHPBar('p2', oppHp);
            renderActiveEffects();
            const battleArea = document.querySelector('.battle-area');
            const actualMyDmg  = Math.max(0, prevMyHp  - myHp);
            const actualOppDmg = Math.max(0, prevOppHp - oppHp);
            const dispOppDmg   = Math.max(0, actualOppDmg - effectCardOppDmg);
            if (actualMyDmg  > 0) spawnDamageFloat(battleArea, '-'+actualMyDmg, 'dmg-p1');
            if (dispOppDmg   > 0) spawnDamageFloat(battleArea, '-'+dispOppDmg,  'dmg-p2');
            if (drainLifeHeal> 0) spawnHealFloat(battleArea, '+'+drainLifeHeal+' 🩸');
            if (effectCardOppDmg > 0) spawnDamageFloat(battleArea, '-'+effectCardOppDmg+' ☠', 'dmg-p2');
            if (gamblingExtraDmg > 0) {
                const gi = gamblingExtraDmg===50?'🎰':(gamblingExtraDmg===30?'🃏':'🎲');
                spawnDamageFloat(battleArea, '-'+gamblingExtraDmg+' '+gi, 'dmg-p2');
            } else if (gamblingExtraDmg < 0) {
                const gi2 = Math.abs(gamblingExtraDmg)===20?'🎰':(Math.abs(gamblingExtraDmg)===30?'🃏':'🎲');
                spawnDamageFloat(battleArea, '-'+Math.abs(gamblingExtraDmg)+' '+gi2, 'dmg-p1');
            }
        }, 100));

        // Result text
        let resultText = '', resultColor = '';
        if (draw)      { resultText='🤝 SERI! HP Tidak Berkurang.'; resultColor='var(--accent)'; }
        else if (iWon) { resultText=reverseResultTriggered?`🔄 Reverse Result! Kamu memenangkan ronde ini`:`🏆 ${MY_NAME} memenangkan ronde ini`; resultColor=reverseResultTriggered?'var(--purple)':'var(--green)'; }
        else           { resultText=`❌ Kamu kalah dari ${OPP_NAME}`; resultColor='var(--red)'; }

        document.getElementById('result-text').textContent  = resultText;
        document.getElementById('result-text').style.color  = resultColor;
        document.getElementById('selection-screen').style.display = 'none';
        document.getElementById('waiting-screen').style.display   = 'none';
        document.getElementById('result-screen').classList.add('show');

        const btn = document.getElementById('btn-continue');
        btn.textContent = 'LANJUTKAN ▶'; btn.disabled = false; btn.style.animation = '';

        updateHPBar('p1', myHp); updateHPBar('p2', oppHp);
        renderActiveEffects();

        // Mulai hitung mundur 10 detik Lanjutkan
        stopContinueTimer();
        let timeRemaining = 10;
        const updateUI = () => {
            btn.textContent = `LANJUTKAN ▶ (${timeRemaining}s)`;
            setStatus(`🚨 Klik LANJUTKAN dalam ${timeRemaining} detik atau kalah AFK!`, 'red');
        };
        updateUI();
        window.continueTimerInt = setInterval(() => {
            timeRemaining--;
            if (timeRemaining <= 0) {
                stopContinueTimer();
                showAfkModal();
            } else {
                updateUI();
            }
        }, 1000);

        btn.onclick = () => {
            btn.disabled = true;
            stopContinueTimer();
            // ── Cek apakah HP habis (akhir ronde) ──
            if (myHp <= 0 || oppHp <= 0) {
                if (myHp <= 0) oppWins++; else myWins++;
                updateDots();
                if (myWins >= ROUNDS_TO_WIN || oppWins >= ROUNDS_TO_WIN) {
                    handleMatchOver();
                } else {
                    // Ronde baru — identik dengan 'new_round' di PvP
                    gameNumber++;
                    roundInGame = 0;
                    _lastKnownRound = null;          // force isNewRound = true
                    round++;
                    drawStreak = 0;
                    // Reset HP
                    myHp = oppHp = HP_MAX;
                    // Clear efek (pertahankan shield jika masih ada)
                    activeEffects   = activeEffects.filter(e => e.shield && myShield > 0);
                    aiActiveEffects = aiActiveEffects.filter(e => e.shield && aiShield > 0);
                    oppActiveEffects = oppActiveEffects.filter(e => {
                        return ['shield1','shield2','shield3'].includes(e.effect_id) && aiShield > 0;
                    });
                    // FIX: Clear kartu hand — kartu ronde lama tidak boleh terbawa ke ronde baru
                    myHandCards = [];
                    aiHandCards = [];
                    document.getElementById('result-screen').classList.remove('show');
                    document.getElementById('p1-chose-badge').classList.remove('show');
                    document.getElementById('p2-chose-badge').classList.remove('show');
                    document.getElementById('timeout-msg').textContent = '';
                    updateHPBar('p1', HP_MAX); updateHPBar('p2', HP_MAX);
                    updateShieldDisplay(); updateOppShieldDisplay(aiShield, aiShieldMax);
                    resetHandImages();
                    setStatus('🥊 RONDE ' + round + ' DIMULAI!', 'blue');
                    renderActiveEffects();
                    // simulateRoundStart dengan ronde baru → akan showCardPick karena _lastKnownRound = null
                    simulateRoundStart(round);
                }
            } else {
                // Masih dalam ronde yang sama — langsung ke selection screen (TANPA card pick)
                // Identik dengan menerima round_start dengan roundNum sama di PvP
                document.getElementById('result-screen').classList.remove('show');
                document.getElementById('p1-chose-badge').classList.remove('show');
                document.getElementById('p2-chose-badge').classList.remove('show');
                document.getElementById('timeout-msg').textContent = '';
                locked = false;
                resetHandImages();
                // simulateRoundStart dengan roundNum sama → cardPickedThisRound masih true → skip card pick
                simulateRoundStart(round);
            }
        };
        _fightTimeouts = [];
    }, 5000));
}

// ═══════════════════════════════════════════════════════
//  TIMER (identik PvP)
// ═══════════════════════════════════════════════════════
function startTimer(secs) {
    if (fightAnimating) return;
    timerLeft = secs || TIMER_SECS;
    const numEl  = document.getElementById('timer-num');
    const ringEl = document.getElementById('timer-ring');
    const circEl = document.getElementById('timer-circle');
    const msgEl  = document.getElementById('timeout-msg');
    numEl.textContent = timerLeft;
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
                locked = true;
                msgEl.textContent = '⏰ WAKTU HABIS! KAMU KALAH RONDE!';

                // Disable semua tombol pilihan
                document.querySelectorAll('.choice').forEach(c => {
                    c.classList.add('disabled');
                    c.style.opacity       = '0.2';
                    c.style.transform     = 'scale(0.82)';
                    c.style.pointerEvents = 'none';
                    c.style.transition    = 'all 0.3s';
                });

                // Badge timeout di sisi player
                const p1Badge = document.getElementById('p1-chose-badge');
                if (p1Badge) {
                    p1Badge.textContent   = '❌ TIMEOUT';
                    p1Badge.classList.add('show');
                    p1Badge.style.color       = 'var(--red)';
                    p1Badge.style.borderColor = 'var(--red)';
                }
                setStatus('⏰ Waktu habis! Kamu otomatis kalah ronde ini!', 'red');

                // Jeda lalu resolve: player otomatis kalah ronde
                setTimeout(() => {
                    document.getElementById('p2-chose-badge').classList.add('show');
                    setTimeout(() => { handleTimeoutLoss(); }, 300);
                }, 600);
            }
        }
    }, 1000);
}

// ═══════════════════════════════════════════════════════
//  HANDLE TIMEOUT LOSS — player kalah ronde otomatis
//  Pakai showFightOverlay persis seperti alur normal,
//  tapi dengan pilihan dummy (rock vs paper → player kalah pasti)
// ═══════════════════════════════════════════════════════
function handleTimeoutLoss() {
    if (matchOver) return;
    stopTimer();

    // AI pilih bebas, player dianggap "rock" (dummy — hanya untuk tampilan)
    const cpuChoice    = getCpuChoice();
    const dummyChoice  = 'rock';   // ditampilkan greyed-out di overlay

    // Hitung damage standar (ikut AI double_damage jika aktif)
    let dmg = DAMAGE; // biasanya 20
    const dblAi = aiActiveEffects.find(e => e.effect === 'double_damage' && e.gamesLeft > 0);
    if (dblAi) { dmg *= 2; dblAi.gamesLeft--; }

    const newMyHp  = Math.max(0, myHp  - dmg);
    const newOppHp = oppHp; // AI tidak kena damage

    // Gunakan showFightOverlay dengan iWon=false, draw=false
    // Override teks result menjadi TIMEOUT setelah overlay tampil
    fightAnimating = true;
    showFightOverlay(
        dummyChoice, cpuChoice,
        false,  // iWon = false (player kalah)
        false,  // draw  = false
        newMyHp, newOppHp,
        dmg, 0  // myDmg, oppDmg
    );

    // Override teks result overlay agar tampil TIMEOUT (dilakukan setelah overlay muncul)
    setTimeout(() => {
        const rt = document.getElementById('fight-result-text');
        if (rt) { rt.textContent = '⏰ TIMEOUT — KALAH!'; rt.style.color = 'var(--red)'; }
        const rd = document.getElementById('fight-winner-detail');
        if (rd) { rd.textContent = 'Waktu habis! Kamu tidak memilih senjata.'; rd.classList.add('show'); }
        // Greyed-out di weapon overlay
        const p1w = document.getElementById('fight-weapon-p1');
        if (p1w) { p1w.style.opacity = '0.25'; p1w.style.filter = 'grayscale(1)'; }
    }, 2600);

    // Restore opacity setelah overlay selesai (phase 4 = 5000ms)
    setTimeout(() => {
        const p1w = document.getElementById('fight-weapon-p1');
        if (p1w) { p1w.style.opacity = ''; p1w.style.filter = ''; }
        // Juga update result-screen text agar konsisten
        const rt2 = document.getElementById('result-text');
        if (rt2) { rt2.textContent = '⏰ TIMEOUT — Kamu kalah ronde ini!'; rt2.style.color = 'var(--red)'; }
    }, 5100);
}

function stopTimer() {
    clearInterval(timerInt);
    document.getElementById('timer-circle').style.strokeDashoffset = 0;
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

// ═══════════════════════════════════════════════════════
//  tickEffects (identik PvP)
// ═══════════════════════════════════════════════════════
function tickEffects(isNewRound = false) {
    activeEffects = activeEffects.filter(e => {
        // FIX: Ronde baru (HP direset) → HAPUS SEMUA efek kecuali shield yang masih punya HP
        if (isNewRound) {
            if (e.shield && myShield > 0) return true;
            return false;
        }
        if (e.gamesLeft >= 999) return true;
        if (e.effect === 'drain_life' || e.effect === 'drain_life_2') return true;
        if (e.effect === 'barrier')       return e.gamesLeft > 0;
        if (e.effect === 'double_damage') return e.gamesLeft > 0;
        if (e.effect === 'reverse_result') return e.gamesLeft > 0;
        if (e.effect === 'gambling1' || e.effect === 'gambling2' || e.effect === 'gambling3') return e.gamesLeft > 0;
        if (e.effect === 'critical_attack') return e.gamesLeft > 0;
        if (e.effect === 'god_attack1' || e.effect === 'god_attack2' || e.effect === 'god_attack3') return e.gamesLeft > 0;
        if (e.effect === 'full_damage') return e.gamesLeft > 0;
        if (e.shield) { if (myShield <= 0) { showCardToast(e.label + ' — Shield habis', e.rarity||'common'); return false; } return true; }
        e.gamesLeft--;
        if (e.gamesLeft <= 0) showCardToast(e.label + ' — efek berakhir', e.rarity||'common');
        return e.gamesLeft > 0;
    });
    // FIX: Ronde baru → bersihkan juga efek AI
    if (isNewRound) {
        aiActiveEffects = aiActiveEffects.filter(e => e.shield && aiShield > 0);
        oppActiveEffects = oppActiveEffects.filter(e => {
            return ['shield1','shield2','shield3'].includes(e.effect_id) && aiShield > 0;
        });
    }
    updateShieldDisplay();
    renderActiveEffects();
}

// ═══════════════════════════════════════════════════════
//  MATCH OVER
// ═══════════════════════════════════════════════════════
function handleMatchOver(isAFK = false) {
    matchOver = true; stopTimer();
    stopContinueTimer();
    const title = document.getElementById('match-over-title');
    const sub   = document.getElementById('match-over-sub');
    if (isAFK) {
        title.textContent = '💀 KAMU KALAH (AFK)'; title.style.color = 'var(--red)';
        sub.textContent   = 'Kamu dianggap kalah karena AFK!';
        saveMatchResult('lost', myWins, oppWins);
    } else if (myWins >= ROUNDS_TO_WIN) {
        title.textContent = '🏆 KAMU MENANG MATCH!'; title.style.color = 'var(--green)';
        sub.textContent = `Skor Ronde: ${myWins} – ${oppWins}`;
        saveMatchResult('won', myWins, oppWins);
    } else {
        title.textContent = '🤖 KOMPUTER MENANG MATCH!'; title.style.color = 'var(--red)';
        sub.textContent = `Skor Ronde: ${myWins} – ${oppWins}`;
        saveMatchResult('lost', myWins, oppWins);
    }
    document.getElementById('selection-screen').style.display  = 'none';
    document.getElementById('waiting-screen').style.display    = 'none';
    document.getElementById('result-screen').classList.remove('show');
    document.getElementById('match-over').classList.add('show');
}

async function saveMatchResult(matchResult, playerRoundWins, aiRoundWins) {
    const durationSec = Math.round((Date.now() - matchStartTime) / 1000);

    // ── Simpan ke database via ai_save.php ──
    try {
        const res = await fetch('../Api/ai_save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                result:             matchResult,
                player_round_wins:  playerRoundWins,
                ai_round_wins:      aiRoundWins,
                choice_rock:        choiceCounts.rock,
                choice_paper:       choiceCounts.paper,
                choice_scissors:    choiceCounts.scissors,
                duration_sec:       durationSec,
                cards_used:         aiCardUsed,
            }),
        });
        if (res.ok) {
            const data = await res.json();
            if (data.ok) {
                // Kirim event agar lobby & statistik bisa auto-refresh saat player kembali
                try { localStorage.setItem('ai_match_saved', Date.now()); } catch(e) {}
            }
        }
    } catch(e) {
        console.warn('[ai_save] fetch gagal:', e);
    }

    // ── Backup ke localStorage (history lokal) ──
    try {
        const lsKey    = 'history_vs_ai_' + MY_ID;
        const existing = JSON.parse(localStorage.getItem(lsKey) || '[]');
        existing.unshift({
            id:           Date.now(),
            result:       matchResult,
            player_rounds: playerRoundWins,
            cpu_rounds:    aiRoundWins,
            played_at:     new Date().toISOString(),
            duration_sec:  durationSec,
            choice_counts: { ...choiceCounts },
        });
        localStorage.setItem(lsKey, JSON.stringify(existing.slice(0, 50)));
    } catch(e) {}
}

function resetMatch() {
    stopContinueTimer();
    myHp = oppHp = HP_MAX; myWins = oppWins = 0; round = 1; gameNumber = 1;
    matchOver = false; locked = false; fightAnimating = false;
    matchStartTime = Date.now(); choiceCounts = {rock:0,paper:0,scissors:0}; aiCardUsed = {};
    activeEffects=[]; aiActiveEffects=[]; oppActiveEffects=[];
    myShield=0; myShieldMax=30; aiShield=0; aiShieldMax=30;
    myHandCards=[]; aiHandCards=[]; cardPickedThisRound=false; _lastKnownRound=null;
    drawStreak=0;
    barrierUsed=barrier2Used=godAttackUsed=gambling2Used=gambling3Used=fullDamageUsed=blockOneUsed=false;
    _fightTimeouts.forEach(id=>clearTimeout(id)); _fightTimeouts=[];
    clearTimeout(cardPickAutoCloseTimer); clearTimeout(cardPickAutoCloseTimeout);
    document.getElementById('match-over').classList.remove('show');
    document.getElementById('round-label').textContent = 'RONDE 1';
    document.getElementById('game-num-badge').style.display = 'none';
    updateHPBar('p1',HP_MAX); updateHPBar('p2',HP_MAX);
    updateShieldDisplay(); updateOppShieldDisplay(0,30);
    updateDots(); resetHandImages();
    renderCardHand(); renderActiveEffects();
    simulateRoundStart(1);
}

function goMenu() { window.location.href = 'main_menu.php'; }
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

// ═══════════════════════════════════════════════════════
//  CARD HAND UI (identik PvP)
// ═══════════════════════════════════════════════════════
function renderCardHand() {
    const hand = document.getElementById('card-hand');
    hand.innerHTML = '<div class="card-hand-label">✦ KARTU RONDE</div>';
    for (let i = 0; i < 2; i++) {
        if (i < myHandCards.length) {
            const card = myHandCards[i];
            const isCounter = COUNTER_CARDS.has(card.id);
            const div = document.createElement('div');
            div.className = `hand-card ${card.rarity}${card._used?' used':''}`;
            div.dataset.slot = i;
            div.innerHTML = `<div class="hc-icon">${card.icon}</div><div class="hc-name">${card.name}</div>` +
                (isCounter ? '<div class="hc-counter">COUNTER</div>' : '');
            if (!card._used) div.addEventListener('click', () => openCardPopup(i));
            hand.appendChild(div);
        } else {
            const empty = document.createElement('div');
            empty.className = 'hand-card-empty';
            empty.innerHTML = '<span style="font-size:.6rem;color:#333;">SLOT '+(i+1)+'</span>';
            hand.appendChild(empty);
        }
    }
}

function openCardPopup(slotIdx) {
    if (locked || matchOver) return;
    const card = myHandCards[slotIdx];
    if (!card || card._used) return;
    const handEl = document.querySelector(`#card-hand .hand-card[data-slot="${slotIdx}"]`);
    if (handEl && handEl.classList.contains('used')) return;
    pendingCardSlot = slotIdx;
    document.getElementById('popup-icon').textContent = card.icon;
    document.getElementById('popup-name').textContent = card.name;
    const timingNote = '<br><span style="color:var(--red);font-weight:800;">⚡ Langsung aktif di ronde ini!</span>';
    document.getElementById('popup-desc').innerHTML = (card.fullDesc || card.desc) + timingNote;
    const tag = document.getElementById('popup-rarity');
    tag.textContent = card.rarity.toUpperCase(); tag.className = 'popup-rarity-tag ' + card.rarity;
    const btnUse = document.getElementById('btn-use-card-confirm');
    btnUse.textContent = '⚡ GUNAKAN SEKARANG!';
    btnUse.onclick = () => {
        document.getElementById('card-use-popup').classList.remove('show');
        activateCard(pendingCardSlot);
    };
    document.getElementById('card-use-popup').classList.add('show');
}

function closeCardPopup() { document.getElementById('card-use-popup').classList.remove('show'); pendingCardSlot=-1; }

function activateCard(slotIdx) {
    const card = myHandCards[slotIdx];
    if (!card || card._used) return;
    card._used = true;
    // Track kartu yang dipakai untuk statistik VS AI
    aiCardUsed[card.id] = (aiCardUsed[card.id] || 0) + 1;
    renderCardHand();
    const handEl = document.querySelector(`#card-hand .hand-card[data-slot="${slotIdx}"]`);
    playCardActivationAnim(card, handEl, COUNTER_CARDS.has(card.id), () => {
        showCardEffectBanner(card);
        applyCardEffect(card);
        showCardToast(card.icon + ' ' + card.name + ' aktif!', card.rarity);
    });
}

// ─── Apply card effect ───
function applyCardEffect(card) {
    const id = card.id;
    const ba = document.querySelector('.battle-area');

    if (id === 'steal_hp') {
        oppHp = Math.max(0, oppHp - 20); updateHPBar('p2',oppHp); flashDamage('p2-hp-bar');
        if (ba) spawnDamageFloat(ba, '-20','dmg-p2');
        myShield += 20; myShieldMax = Math.max(myShieldMax, myShield);
        addActiveEffect({cardId:'steal_hp',label:'💉 Steal HP 1',rarity:'rare',gamesLeft:999,shield:true,effect:'steal_hp'});
        updateShieldDisplay(); if (ba) spawnDamageFloat(ba,'+20🛡','dmg-heal');
        showCardToast('💉 Steal HP 1! -20 HP lawan → +20 Shield kamu!','rare'); return;
    }
    if (id === 'steal_hp2') {
        oppHp = Math.max(0, oppHp - 50); updateHPBar('p2',oppHp); flashDamage('p2-hp-bar');
        if (ba) spawnDamageFloat(ba,'-50','dmg-p2');
        myShield += 50; myShieldMax = Math.max(myShieldMax, myShield);
        addActiveEffect({cardId:'steal_hp2',label:'🩻 Steal HP 2',rarity:'epic',gamesLeft:999,shield:true,effect:'steal_hp2'});
        updateShieldDisplay(); if (ba) spawnDamageFloat(ba,'+50🛡','dmg-heal');
        showCardToast('🩻 Steal HP 2! -50 HP lawan → +50 Shield kamu!','epic'); return;
    }
    if (id === 'absolute_reset') {
        showCardEffectBanner({icon:'♾️',name:'ABSOLUTE RESET',desc:'Mereset match!',rarity:'legend'});
        setTimeout(() => doAbsoluteReset(), 800); return;
    }
    if (id === 'block_one') {
        // Pada VS AI: blokir 1 kartu AI secara acak
        const available = aiHandCards.filter(c => !c._used);
        if (available.length > 0) {
            const removed = available[Math.floor(Math.random() * available.length)];
            aiHandCards = aiHandCards.filter(c => c.id !== removed.id);
            aiActiveEffects  = aiActiveEffects.filter(e => e.cardId  !== removed.id);
            oppActiveEffects = oppActiveEffects.filter(e => e.effect_id !== removed.id);
            renderActiveEffects();
            showCardToast('🚫 Block One! Kartu AI "'+removed.name+'" diblokir!','rare');
        } else { showCardToast('🚫 Block One! AI tidak punya kartu.','rare'); }
        return;
    }
    if (id === 'full_damage') {
        addActiveEffect({cardId:'full_damage',label:'💥 Full Damage',rarity:'legend',gamesLeft:999,effect:'full_damage'});
        setStatus('💥 FULL DAMAGE aktif! Menang pertama = 5× damage!','yellow'); return;
    }
    if (id === 'reverse_result') {
        addActiveEffect({cardId:'reverse_result',label:'🔄 Reverse',rarity:'epic',gamesLeft:3,effect:'reverse_result'});
        setStatus('🔄 Reverse Result aktif! Kekalahan/Seri → Menang. 3 kesempatan.','yellow'); return;
    }
    if (id === 'shield1') { myShield+=30; myShieldMax=Math.max(myShieldMax,myShield); addActiveEffect({cardId:'shield1',label:'🛡️ Shield I',rarity:'common',gamesLeft:999,shield:true,effect:'shield1'}); updateShieldDisplay(); showCardToast('🛡️ Shield I aktif! +30 Shield HP.','common'); return; }
    if (id === 'shield2') { myShield+=60; myShieldMax=Math.max(myShieldMax,myShield); addActiveEffect({cardId:'shield2',label:'🔷 Shield II',rarity:'rare',gamesLeft:999,shield:true,effect:'shield2'}); updateShieldDisplay(); showCardToast('🔷 Shield II aktif! +60 Shield HP.','rare'); return; }
    if (id === 'shield3') { myShield+=100; myShieldMax=Math.max(myShieldMax,myShield); addActiveEffect({cardId:'shield3',label:'🌟 Shield III',rarity:'legend',gamesLeft:999,shield:true,effect:'shield3'}); updateShieldDisplay(); showCardToast('🌟 Shield III aktif! +100 Shield HP.','legend'); return; }

    // Duration-based effects
    const dmap = {
        drain_life:      {gl:3,   label:'🩸 Drain Life 1', eff:'drain_life'},
        drain_life_2:    {gl:3,   label:'🩸 Drain Life 2', eff:'drain_life_2'},
        gambling1:       {gl:1,   label:'🎲 Gambling I',   eff:'gambling1'},
        safe_play1:      {gl:1,   label:'🛡 Safe Play I',  eff:'safe_play1'},
        barrier:         {gl:999, label:'🔮 Barrier 1',    eff:'barrier'},
        critical_attack: {gl:2,   label:'⚡ Critical Atk', eff:'critical_attack'},
        tie_breaker:     {gl:999, label:'⚖️ Tie Breaker',  eff:'tie_breaker'},
        god_attack1:     {gl:999, label:'⚡ God Atk I',    eff:'god_attack1'},
        gambling2:       {gl:1,   label:'🃏 Gambling II',  eff:'gambling2'},
        safe_play2:      {gl:1,   label:'🛡 Safe Play II', eff:'safe_play2'},
        god_attack2:     {gl:999, label:'⚔️ God Atk II',   eff:'god_attack2'},
        gambling3:       {gl:1,   label:'🎰 Gambling III', eff:'gambling3'},
        god_attack3:     {gl:999, label:'💀 God Atk III',  eff:'god_attack3'},
        double_damage:   {gl:999, label:'🔮 Barrier 2',    eff:'double_damage'},
    };
    if (dmap[id]) {
        const cfg = dmap[id];
        addActiveEffect({cardId:id, label:cfg.label, rarity:card.rarity, gamesLeft:cfg.gl, effect:cfg.eff});
    }
}

function doAbsoluteReset() {
    stopContinueTimer();
    _fightTimeouts.forEach(id=>clearTimeout(id)); _fightTimeouts=[];
    document.getElementById('fight-overlay').classList.remove('show');
    document.getElementById('card-pick-overlay').classList.remove('show');
    clearTimeout(cardPickAutoCloseTimer); clearTimeout(cardPickAutoCloseTimeout);
    myHp=oppHp=100; myWins=oppWins=0; round=1; gameNumber=1;
    matchOver=false; locked=false; cardPickedThisRound=false; _lastKnownRound=null;
    activeEffects=[]; aiActiveEffects=[]; oppActiveEffects=[];
    myShield=0; myShieldMax=30; aiShield=0; aiShieldMax=30;
    myHandCards=[]; aiHandCards=[];
    document.getElementById('round-label').textContent='RONDE 1';
    document.getElementById('game-num-badge').style.display='none';
    updateHPBar('p1',100); updateHPBar('p2',100);
    updateDots(); resetHandImages();
    updateShieldDisplay(); updateOppShieldDisplay(0,30);
    renderCardHand(); renderActiveEffects();
    document.getElementById('result-screen').classList.remove('show');
    setStatus('♾️ Absolute Reset! Match kembali ke awal.','yellow');
    simulateRoundStart(1);
}

// ═══════════════════════════════════════════════════════
//  UI HELPERS (identik PvP)
// ═══════════════════════════════════════════════════════
function showSelectionScreen() {
    locked = false;
    document.getElementById('selection-screen').style.display = 'block';
    document.getElementById('waiting-screen').style.display   = 'none';
    document.getElementById('result-screen').classList.remove('show');
    document.querySelectorAll('.choice').forEach(c => {
        c.classList.remove('disabled','selected');
        c.style.opacity=''; c.style.transform=''; c.style.transition='';
        c.style.pointerEvents=''; // reset inline pointerEvents dari timeout
    });
}

function resetHandImages() {
    document.getElementById('p1-hand').src = 'assets/Rock.png';
    document.getElementById('p2-hand').src = 'assets/Question.svg';
}

function updateHPBar(who, hp) {
    const pct  = Math.max(0, Math.min(hp, HP_MAX));
    const bar  = document.getElementById(who+'-hp-bar');
    const val  = document.getElementById(who+'-hp-val');
    const card = document.getElementById(who+'-card');
    card.classList.remove('hp-mid','hp-low');
    if (pct <= 40) card.classList.add('hp-low');
    else if (pct <= 60) card.classList.add('hp-mid');
    bar.style.width = pct+'%';
    val.textContent = pct;
    val.style.color = pct<=40?'var(--hp-low)':pct<=60?'var(--hp-mid)':'var(--hp-green)';
}

function flashDamage(barId) {
    const bar = document.getElementById(barId);
    bar.classList.remove('hp-damaged'); void bar.offsetWidth; bar.classList.add('hp-damaged');
}
function flashHeal(barId) {
    const bar = document.getElementById(barId);
    bar.classList.remove('hp-healed'); void bar.offsetWidth; bar.classList.add('hp-healed');
}

function updateDots() {
    for (let i=0;i<2;i++) {
        document.getElementById('pd-'+i).className = 'dot'+(i<myWins?' p1':'');
        document.getElementById('cd-'+i).className = 'dot'+(i<oppWins?' p2':'');
    }
}

function setStatus(msg, cls='') {
    const el = document.getElementById('status-bar');
    el.textContent = msg; el.className = cls;
}

function addActiveEffect(effect) {
    activeEffects = activeEffects.filter(e => e.cardId !== effect.cardId);
    activeEffects.push(effect);
    renderActiveEffects();
}

function renderActiveEffects() {
    // P1 effects
    let p1Con = document.getElementById('p1-effects');
    if (!p1Con) {
        p1Con = document.createElement('div');
        p1Con.id = 'p1-effects'; p1Con.className = 'active-effects';
        document.getElementById('p1-card').appendChild(p1Con);
    }
    const p1Shield = document.getElementById('p1-shield-section');
    const p1Anchor = p1Shield || document.getElementById('p1-card').querySelector('.hp-section');
    if (p1Anchor && p1Anchor.nextSibling !== p1Con) p1Anchor.after(p1Con);
    p1Con.innerHTML = '';
    activeEffects.forEach(e => {
        const chip = document.createElement('div');
        chip.className = 'effect-chip '+(e.rarity||'common');
        if (e.effect==='full_damage') { chip.textContent=e.label+' ⚡'; chip.style.animation='legendHandGlow 1.5s ease-in-out infinite'; }
        else if (e.effect==='reverse_result') chip.textContent=e.label+' ×'+e.gamesLeft;
        else if (e.shield) chip.textContent=e.label;
        else chip.textContent=e.gamesLeft<999?e.label+' ×'+e.gamesLeft:e.label;
        p1Con.appendChild(chip);
    });

    // P2 effects
    let p2Con = document.getElementById('p2-effects');
    if (!p2Con) {
        p2Con = document.createElement('div');
        p2Con.id='p2-effects'; p2Con.className='active-effects'; p2Con.style.justifyContent='flex-end';
        document.getElementById('p2-card').appendChild(p2Con);
    }
    const p2Shield = document.getElementById('p2-shield-section');
    const p2Anchor = p2Shield || document.getElementById('p2-card').querySelector('.hp-section');
    if (p2Anchor && p2Anchor.nextSibling !== p2Con) p2Anchor.after(p2Con);
    p2Con.innerHTML = '';
    oppActiveEffects.forEach(e => {
        const chip = document.createElement('div');
        chip.className = 'effect-chip '+(e.rarity||'common');
        if (e.effect_id==='full_damage') { chip.textContent=e.label+' ⚡'; chip.style.animation='legendHandGlow 1.5s ease-in-out infinite'; }
        else if (e.effect_id==='reverse_result') chip.textContent=e.label+' ×'+e.gamesLeft;
        else chip.textContent=e.gamesLeft<999?e.label+' ×'+e.gamesLeft:e.label;
        p2Con.appendChild(chip);
    });
}

function updateShieldDisplay() {
    let ss = document.getElementById('p1-shield-section');
    if (!ss) {
        ss = document.createElement('div'); ss.id='p1-shield-section'; ss.className='shield-section';
        ss.innerHTML=`<div class="shield-row"><span class="shield-label">🛡️ SHIELD</span><span class="shield-val" id="p1-shield-val">0</span></div><div class="shield-track"><div class="shield-fill" id="p1-shield-fill" style="width:0%"></div></div>`;
        document.getElementById('p1-card').querySelector('.hp-section').after(ss);
    }
    const sv = document.getElementById('p1-shield-val');
    const sf = document.getElementById('p1-shield-fill');
    if (myShield > 0) {
        ss.style.display='block'; sv.textContent=myShield;
        sf.style.width=Math.max(0,Math.min(100,myShield))+'%';
    } else { ss.style.display='none'; sv.textContent='0'; sf.style.width='0%'; }
    const eff=document.getElementById('p1-effects');
    if (eff && ss && ss.nextSibling !== eff) ss.after(eff);
}

function updateOppShieldDisplay(shieldHp, shieldMax) {
    let ss = document.getElementById('p2-shield-section');
    if (!ss) {
        ss = document.createElement('div'); ss.id='p2-shield-section'; ss.className='shield-section opp-shield';
        ss.innerHTML=`<div class="shield-row" style="flex-direction:row-reverse;"><span class="shield-label">🛡️ SHIELD</span><span class="shield-val" id="p2-shield-val">0</span></div><div class="shield-track"><div class="shield-fill p2-fill" id="p2-shield-fill" style="width:0%"></div></div>`;
        document.getElementById('p2-card').querySelector('.hp-section').after(ss);
    }
    const sv = document.getElementById('p2-shield-val');
    const sf = document.getElementById('p2-shield-fill');
    if (shieldHp > 0) {
        ss.style.display='block'; sv.textContent=shieldHp;
        sf.style.width=Math.max(0,Math.min(100,shieldHp))+'%';
    } else { ss.style.display='none'; sv.textContent='0'; sf.style.width='0%'; }
    const eff2=document.getElementById('p2-effects');
    if (eff2 && ss && ss.nextSibling !== eff2) ss.after(eff2);
}

function spawnConfetti(parent) {
    const colors=['#ffd700','#4affbb','#4facfe','#f093fb','#ff5e5e','#fff'];
    for (let i=0;i<30;i++) setTimeout(()=>{
        const el=document.createElement('div'); el.className='confetti-piece';
        el.style.left=(10+Math.random()*80)+'%'; el.style.top=(Math.random()*30)+'%';
        el.style.background=colors[Math.floor(Math.random()*colors.length)];
        el.style.animationDuration=(.8+Math.random()*.8)+'s'; el.style.animationDelay=(Math.random()*.4)+'s';
        el.style.borderRadius=Math.random()>.5?'50%':'2px';
        parent.appendChild(el); setTimeout(()=>el.remove(),1600);
    }, Math.random()*500);
}

function spawnDamageFloat(parent, text, cls) {
    const el=document.createElement('div'); el.className='damage-float '+cls; el.textContent=text;
    el.style.top=(30+Math.random()*20)+'px'; parent.style.position='relative';
    parent.appendChild(el); setTimeout(()=>el.remove(),1400);
}

// identik PvP: heal-float class + orb particles
function spawnHealFloat(parent, text, cls='') {
    const el = document.createElement('div');
    el.className = 'heal-float' + (cls ? ' '+cls : '');
    el.textContent = text;
    el.style.left = (15 + Math.random() * 35) + '%';
    el.style.top  = (15 + Math.random() * 20) + 'px';
    parent.style.position = 'relative';
    parent.appendChild(el);
    // Orb particles (identik PvP)
    for (let i = 0; i < 6; i++) {
        const orb = document.createElement('div');
        orb.className = 'heal-orb';
        const angle = (i / 6) * Math.PI * 2;
        const dist  = 25 + Math.random() * 30;
        orb.style.left = (40 + Math.cos(angle) * 15) + '%';
        orb.style.top  = (40 + Math.sin(angle) * 15) + 'px';
        orb.style.setProperty('--tx', Math.round(Math.cos(angle) * dist) + 'px');
        orb.style.setProperty('--ty', Math.round(Math.sin(angle) * dist - 50) + 'px');
        orb.style.animationDelay = (Math.random() * 0.15) + 's';
        parent.appendChild(orb);
        setTimeout(() => orb.remove(), 1100);
    }
    setTimeout(() => el.remove(), 1500);
}

// ─── Card activation animations (identik PvP) ───
function showCardEffectBanner(card) {
    const rc={'common':'#aaa','rare':'var(--blue)','epic':'#c084fc','legend':'var(--accent)'};
    const banner=document.getElementById('card-effect-banner');
    document.getElementById('ceb-icon').textContent=card.icon;
    document.getElementById('ceb-text').textContent=card.name.toUpperCase();
    document.getElementById('ceb-text').style.color=rc[card.rarity]||'#fff';
    document.getElementById('ceb-sub').textContent=card.desc;
    document.getElementById('ceb-timing').textContent='⚡ Aktif Sekarang!';
    document.getElementById('ceb-timing').className='ceb-timing instant';
    document.getElementById('ceb-timing').style.display='inline-block';
    banner.classList.remove('show','banner-hide','rarity-common','rarity-rare','rarity-epic','rarity-legend');
    void banner.offsetWidth;
    banner.classList.add('show','rarity-'+(card.rarity||'common'));
    // Animasi keluar lebih cepat setelah 900ms (dari 1500ms)
    clearTimeout(banner._hideTimer);
    banner._hideTimer=setTimeout(()=>{
        banner.classList.add('banner-hide');
        setTimeout(()=>{
            banner.classList.remove('show','banner-hide','rarity-common','rarity-rare','rarity-epic','rarity-legend');
        },200);
    },900);
}

function showCardToast(msg, rarity) {
    document.querySelectorAll('.card-toast').forEach(t=>t.remove());
    const rc={'common':'#aaa','rare':'var(--blue)','epic':'#c084fc','legend':'var(--accent)'};
    const toast=document.createElement('div'); toast.className='card-toast';
    toast.style.color=rc[rarity]||'var(--text)'; toast.style.borderColor=rc[rarity]||'var(--border)';
    toast.textContent=msg; document.body.appendChild(toast);
    setTimeout(()=>toast.remove(),3000);
}

function playCardActivationAnim(card, handEl, isCounter, onDone) {
    const rc={'common':'#ccc','rare':'var(--blue)','epic':'#c084fc','legend':'var(--accent)'};
    const color=rc[card.rarity]||'#fff';
    if (handEl) {
        const rect=handEl.getBoundingClientRect();
        const cx=rect.left+rect.width/2, cy=rect.top+rect.height/2;
        handEl.classList.add('activating');
        setTimeout(()=>handEl.classList.remove('activating'),350);
        // Clone card + throw lebih cepat (120ms → 60ms delay)
        setTimeout(()=>{
            const clone=handEl.cloneNode(true); clone.classList.add('card-throwing');
            clone.style.left=rect.left+'px'; clone.style.top=rect.top+'px';
            clone.style.width=rect.width+'px'; clone.style.height=rect.height+'px'; clone.style.margin='0';
            const tx=window.innerWidth/2-cx, ty=window.innerHeight/2-cy;
            const rot=(Math.random()-.5)*50+(isCounter?-30:25);
            clone.style.setProperty('--tx',tx+'px'); clone.style.setProperty('--ty',ty+'px'); clone.style.setProperty('--rot',rot+'deg');
            document.body.appendChild(clone);
            // Impact effects saat tiba di tengah (420ms → 280ms)
            setTimeout(()=>{
                spawnCardParticles(window.innerWidth/2, window.innerHeight/2, card.rarity, color);
                triggerScreenFlash(card.rarity);
                if (card.rarity==='legend') triggerLegendBorder();
                if (card.rarity==='epic')   triggerEpicVortex();
                clone.remove();
            },280);
        },60);
    } else {
        triggerScreenFlash(card.rarity);
        spawnCardParticles(window.innerWidth/2, window.innerHeight/2, card.rarity, color);
        if (card.rarity==='legend') triggerLegendBorder();
        if (card.rarity==='epic')   triggerEpicVortex();
    }
    // onDone lebih cepat: 680ms → 460ms
    setTimeout(onDone, 460);
}

function spawnCardParticles(cx, cy, rarity, color) {
    const cnt={common:14,rare:22,epic:30,legend:42}[rarity]||16;
    const sz={common:5,rare:7,epic:9,legend:11}[rarity]||6;
    for (let i=0;i<cnt;i++) {
        const el=document.createElement('div'); el.className='card-particle';
        const angle=(i/cnt)*Math.PI*2+Math.random()*.5;
        const dist=50+Math.random()*(rarity==='legend'?160:rarity==='epic'?130:rarity==='rare'?100:70);
        // Shape variety: some square for extra sparkle
        const isSquare = rarity==='legend'&&Math.random()>.6;
        let pc=color;
        if (rarity==='legend'&&Math.random()>.45) pc='#fff';
        if (rarity==='legend'&&Math.random()>.75) pc='#ffd700';
        if (rarity==='epic'&&Math.random()>.55)   pc='#f093fb';
        if (rarity==='rare'&&Math.random()>.6)    pc='#7fd4ff';
        const pSz = sz*(0.5+Math.random()*.8);
        el.style.cssText+=`left:${cx}px;top:${cy}px;width:${pSz}px;height:${pSz}px;background:${pc};box-shadow:0 0 ${pSz*1.5}px ${pc},0 0 ${pSz*3}px ${pc}50;--px:${Math.cos(angle)*dist}px;--py:${Math.sin(angle)*dist}px;--dur:${(.28+Math.random()*.32)}s;animation-delay:${Math.random()*.06}s;transform:translate(-50%,-50%);${isSquare?'border-radius:2px;':''}`;
        document.body.appendChild(el); setTimeout(()=>el.remove(),500);
    }
    // Spawn extra "sparkle" burst for legend
    if (rarity==='legend') {
        for(let i=0;i<8;i++){
            const el=document.createElement('div'); el.className='card-particle';
            const angle=Math.random()*Math.PI*2;
            const dist=20+Math.random()*50;
            el.style.cssText+=`left:${cx}px;top:${cy}px;width:14px;height:3px;background:linear-gradient(90deg,${color},transparent);border-radius:2px;--px:${Math.cos(angle)*dist}px;--py:${Math.sin(angle)*dist}px;--dur:${(.22+Math.random()*.18)}s;animation-delay:${Math.random()*.05}s;transform:translate(-50%,-50%) rotate(${angle}rad);`;
            document.body.appendChild(el); setTimeout(()=>el.remove(),400);
        }
    }
}

function triggerScreenFlash(rarity) {
    const el=document.getElementById('card-activate-flash');
    el.className=''; void el.offsetWidth; el.className='flash-'+rarity;
    setTimeout(()=>el.className='',500);
}
function triggerLegendBorder() {
    const el=document.getElementById('card-legend-border');
    el.classList.remove('show'); void el.offsetWidth; el.classList.add('show');
    setTimeout(()=>el.classList.remove('show'),650);
}
function triggerEpicVortex() {
    let el=document.getElementById('card-epic-vortex');
    if (!el) { el=document.createElement('div'); el.id='card-epic-vortex'; document.body.appendChild(el); el.style.cssText='position:fixed;top:50%;left:50%;z-index:1996;pointer-events:none;width:180px;height:180px;border-radius:50%;transform:translate(-50%,-50%) scale(0);background:radial-gradient(ellipse at center,rgba(168,85,247,.65) 0%,rgba(168,85,247,0) 70%);opacity:0;'; }
    el.classList.remove('show'); void el.offsetWidth; el.classList.add('show');
    setTimeout(()=>el.classList.remove('show'),550);
}

// ── CANVAS NODE NETWORK — identik lobby ──
const cv=document.getElementById('bg'),cx=cv.getContext('2d');
let W,H,NS=[];
const LOBBY_COLS=['rgba(255,77,77,','rgba(77,166,255,','rgba(125,255,77,'];
function rsz(){W=cv.width=innerWidth;H=cv.height=innerHeight}
function mkN(){NS=Array.from({length:70},()=>({
  x:Math.random()*W,y:Math.random()*H,
  vx:(Math.random()-.5)*.55,vy:(Math.random()-.5)*.55,
  r:Math.random()*2.2+.8,col:LOBBY_COLS[Math.floor(Math.random()*3)],
  a:Math.random()*.55+.1,maxA:Math.random()*.55+.1,da:.002
}))}
function lobbyFrame(){
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
  requestAnimationFrame(lobbyFrame);
}
window.addEventListener('resize',()=>{rsz();mkN()});rsz();mkN();lobbyFrame();

// ── ENERGY LINES — identik lobby ──
const ELC=document.getElementById('EL');
for(let i=0;i<10;i++){
  const e=document.createElement('div');e.className='el';
  e.style.cssText=`left:${Math.random()*100}%;height:${Math.random()*50+20}px;animation-duration:${Math.random()*9+5}s;animation-delay:${Math.random()*9}s;opacity:.38;`;
  ELC.appendChild(e);}

// ── PARTICLES — identik lobby ──
const PC=document.getElementById('PT');
for(let i=0;i<30;i++){
  const p=document.createElement('div');p.className='p';
  const s=Math.random()*4.5+1,col=LOBBY_COLS[i%3];
  p.style.cssText=`left:${Math.random()*100}%;width:${s}px;height:${s}px;background:${col}${Math.random()*.5+.25});box-shadow:0 0 ${s*3}px ${col}.55);animation-duration:${Math.random()*16+9}s;animation-delay:${Math.random()*16}s;`;
  PC.appendChild(p);}
</script>

<!-- EXIT CONFIRMATION MODAL -->
<div class="exit-overlay" id="exitOverlay" onclick="if(event.target===this)closeExitModal()">
  <div class="exit-modal" id="exitModal">
    <div class="exit-modal-topbar"></div>
    <div class="exit-icon">⚠️</div>
    <div class="exit-title">KELUAR GAME?</div>
    <div class="exit-desc" id="exitDesc">Sesimu akan diakhiri.<br>Yakin ingin keluar dari Battle Arena?</div>
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