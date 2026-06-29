<?php
session_start();
require_once __DIR__ . '/../Backend/database.php';
if (!isset($_SESSION['player_id'])) { header('Location: Landing_page.php'); exit; }
$player_id   = $_SESSION['player_id'];
$player_name = htmlspecialchars($_SESSION['player_name'] ?? strtoupper($player_id));
if (isset($_GET['logout'])) { session_destroy(); header('Location: Landing_page.php'); exit; }
$AVATARS_LIST = ['⚔️','🛡️','🔥','💎','🌪️','👑','🤖','🐉','⚡','🌙','🗡️','🔮'];
$menu_avatar   = '⚔️';
$menu_dispname = $player_name;
$p_rating = 1000; $p_wins = 0; $p_losses = 0;
try {
    $db = getDB();
    $sp = $db->prepare("SELECT username, avatar, avatar_choice, display_name, rating, wins, losses FROM players WHERE id = ? LIMIT 1");
    $sp->execute([$player_id]);
    $pr = $sp->fetch();
    if ($pr) {
        $menu_avatar   = htmlspecialchars($pr['avatar'] ?? ($AVATARS_LIST[(int)($pr['avatar_choice']??0)] ?? '⚔️'));
        $menu_dispname = htmlspecialchars($pr['display_name'] ?? $player_name);
        $player_name   = htmlspecialchars($pr['username'] ?? $player_name);
        $p_rating = (int)($pr['rating'] ?? 1000);
        $p_wins   = (int)($pr['wins']   ?? 0);
        $p_losses = (int)($pr['losses'] ?? 0);
    }
} catch (Throwable) {}
$rank_tiers = [
    [2000,'GRANDMASTER','#ffd700','rgba(255,215,0,.55)'],
    [1700,'MASTER',     '#c084fc','rgba(192,132,252,.55)'],
    [1500,'DIAMOND',    '#4da6ff','rgba(77,166,255,.55)'],
    [1300,'PLATINUM',   '#7dff4d','rgba(125,255,77,.55)'],
    [1100,'GOLD',       '#f5c842','rgba(245,200,66,.55)'],
    [950, 'SILVER',     '#c0c0c0','rgba(192,192,192,.55)'],
    [0,   'BRONZE',     '#cd7f32','rgba(205,127,50,.55)'],
];
$tier_name='BRONZE'; $tier_col='#cd7f32'; $tier_glow='rgba(205,127,50,.55)';
foreach($rank_tiers as [$min,$name,$col,$glow]){if($p_rating>=$min){$tier_name=$name;$tier_col=$col;$tier_glow=$glow;break;}}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Battle Arena – Koleksi Kartu</title>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Bebas+Neue&family=Russo+One&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --rock:#ff4d4d;--paper:#4da6ff;--scissors:#7dff4d;
  --gr:rgba(255,77,77,.6);--gp:rgba(77,166,255,.6);--gs:rgba(125,255,77,.6);
  --dark:#05060d;--mid:#0b0d1a;--card:rgba(255,255,255,.028);
  --text:#eef0ff;--muted:rgba(238,240,255,0.72);--border:rgba(238,240,255,.07);
  --rc:<?php echo $tier_col?>;--rg:<?php echo $tier_glow?>;
  --c-common:#aaaaaa;--c-rare:#4da6ff;--c-epic:#c084fc;--c-legend:#ffd700;
  --cc-win-bg:rgba(125,255,77,.08);--cc-win-border:rgba(125,255,77,.22);--cc-win-color:#a8e860;
  --cc-lose-bg:rgba(255,77,77,.08);--cc-lose-border:rgba(255,77,77,.22);--cc-lose-color:#ff9090;
}
html,body{width:100%;min-height:100%;background:var(--dark);font-family:'Rajdhani',sans-serif;overflow-x:hidden}
canvas#bg{position:fixed;inset:0;z-index:0;pointer-events:none}
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
/* PLAYER BAR - identical to main_menu */
.pbar{position:fixed;top:0;left:0;right:0;z-index:30;display:flex;align-items:center;justify-content:space-between;
  padding:10px 28px;background:linear-gradient(180deg,rgba(5,6,13,.92) 0%,rgba(5,6,13,.6) 100%);
  border-bottom:1px solid var(--border);backdrop-filter:blur(24px)}
.pinfo{display:flex;align-items:center;gap:11px;text-decoration:none;cursor:pointer;
  padding:5px 14px 5px 5px;border:1px solid transparent;transition:all .25s;
  clip-path:polygon(10px 0%,100% 0%,calc(100% - 10px) 100%,0% 100%)}
.pinfo:hover{background:rgba(77,166,255,.07);border-color:rgba(77,166,255,.22)}
.pinfo:hover .pav{box-shadow:0 0 30px var(--rg),0 0 0 2px var(--rc)}
.pav{width:42px;height:42px;font-size:20px;background:linear-gradient(135deg,rgba(77,166,255,.18),rgba(125,255,77,.1));
  border:1.5px solid var(--rc);display:flex;align-items:center;justify-content:center;
  box-shadow:0 0 20px var(--rg);transition:all .25s;clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%)}
.pname{font-family:'Russo One',sans-serif;font-size:.76rem;color:var(--text);letter-spacing:.1em}
.pid{font-family:'Rajdhani',sans-serif;font-size:.68rem;color:var(--muted);letter-spacing:.06em;margin-top:1px}
.phint{font-size:.58rem;color:rgba(77,166,255,.55);letter-spacing:.1em;margin-top:1px;font-weight:600}
.rank-pill{display:flex;align-items:center;gap:8px;border:1px solid var(--rc);padding:6px 16px;
  background:linear-gradient(135deg,rgba(5,6,13,.8),rgba(13,15,26,.9));box-shadow:0 0 18px var(--rg);
  clip-path:polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%)}
.rank-icon{font-size:18px}.rank-info{display:flex;flex-direction:column;gap:1px}
.rank-name-lbl{font-family:'Russo One',sans-serif;font-size:.62rem;letter-spacing:.2em;color:var(--rc)}
.rank-pts{font-family:'Bebas Neue',sans-serif;font-size:.82rem;letter-spacing:.08em;color:var(--muted)}
.pstats{display:flex;gap:6px;align-items:center}
.ps{display:flex;align-items:center;gap:6px;padding:6px 12px;background:rgba(238,240,255,.03);
  border:1px solid var(--border);clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%)}
.ps-img{width:20px;height:20px;object-fit:contain}
.ps-lbl{font-size:9px;letter-spacing:.22em;text-transform:uppercase;color:var(--muted);font-weight:600}
.btn-out{font-family:'Rajdhani',sans-serif;font-size:.7rem;font-weight:700;letter-spacing:.18em;
  text-transform:uppercase;color:rgba(255,77,77,.65);background:rgba(255,77,77,.06);
  border:1px solid rgba(255,77,77,.22);padding:8px 20px;cursor:pointer;transition:all .2s;
  text-decoration:none;display:inline-flex;align-items:center;gap:6px;
  clip-path:polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%)}
.btn-out:hover{background:rgba(255,77,77,.15);border-color:rgba(255,77,77,.5);color:#ff9090}
.toast{position:fixed;bottom:34px;left:50%;transform:translateX(-50%) translateY(24px);z-index:99;
  background:rgba(5,6,13,.96);border:1px solid rgba(238,240,255,.1);padding:11px 28px;
  font-family:'Rajdhani',sans-serif;font-size:.85rem;font-weight:700;color:var(--text);
  letter-spacing:.07em;backdrop-filter:blur(16px);box-shadow:0 8px 40px rgba(0,0,0,.7);
  opacity:0;pointer-events:none;transition:opacity .3s,transform .3s;
  clip-path:polygon(12px 0%,100% 0%,calc(100% - 12px) 100%,0% 100%)}
.toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
/* CONTENT */
.content-wrap{position:relative;z-index:10;padding:100px 28px 80px;max-width:1100px;margin:0 auto}
.page-top{text-align:center;margin-bottom:2.8rem;opacity:0;transform:translateY(18px);transition:opacity .7s ease,transform .7s ease}
.page-top.show{opacity:1;transform:translateY(0)}
.atag{display:flex;align-items:center;justify-content:center;gap:14px;font-family:'Rajdhani',sans-serif;
  font-size:11px;font-weight:700;letter-spacing:.55em;text-transform:uppercase;color:var(--paper);margin-bottom:.9rem}
.atag-line{width:44px;height:1px;background:linear-gradient(to right,transparent,var(--paper));opacity:.5}
.atag-line.rev{background:linear-gradient(to left,transparent,var(--paper))}
.ptitle{font-family:'Bebas Neue',sans-serif;font-size:clamp(2.4rem,8vw,5rem);line-height:.88;
  position:relative;
  background:linear-gradient(135deg,#ff4d4d 0%,#eef0ff 40%,#4da6ff 70%,#7dff4d 100%);
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
}
.ptitle::before,.ptitle::after{content:attr(data-text);position:absolute;inset:0;
  font-family:'Bebas Neue',sans-serif;font-size:inherit;letter-spacing:inherit;pointer-events:none;
  -webkit-background-clip:unset;-webkit-text-fill-color:transparent;background-clip:unset;
}
.ptitle::before{color:var(--rock);clip-path:polygon(0 20%,100% 20%,100% 38%,0 38%);animation:g1 5s infinite steps(1);opacity:.55}
.ptitle::after{color:var(--paper);clip-path:polygon(0 62%,100% 62%,100% 76%,0 76%);animation:g2 5s infinite steps(1);opacity:.55}
@keyframes g1{0%,93%{transform:none;opacity:0}94%{transform:translateX(-4px);opacity:.55}95%{transform:translateX(4px) skewX(6deg);opacity:.55}96%{transform:none;opacity:0}}
@keyframes g2{0%,95%{transform:none;opacity:0}96%{transform:translateX(4px);opacity:.55}97%{transform:translateX(-4px) skewX(-4deg);opacity:.55}98%{transform:none;opacity:0}}
.tw-k,.tw-o,.tw-l{text-shadow:none;}
.psub{font-family:'Rajdhani',sans-serif;font-size:clamp(.72rem,1.4vw,.88rem);color:var(--muted);
  font-weight:600;letter-spacing:.22em;text-transform:uppercase;margin-top:.4rem}
.btn-back{font-family:'Rajdhani',sans-serif;font-size:.7rem;font-weight:700;letter-spacing:.18em;
  text-transform:uppercase;
  color:rgba(77,166,255,.85);
  background:transparent;
  border:1px solid rgba(77,166,255,.2);
  padding:8px 20px;cursor:pointer;transition:all .2s;
  text-decoration:none;display:inline-flex;align-items:center;gap:6px;
  clip-path:polygon(8px 0%,100% 0%,calc(100% - 8px) 100%,0% 100%)}
.btn-back:hover{background:rgba(77,166,255,.18);border-color:rgba(77,166,255,.45);color:#4da6ff}
.sec-div{display:flex;align-items:center;gap:16px;margin:2.4rem 0 1.4rem}
.sec-line{flex:1;height:1px;background:linear-gradient(to right,transparent,var(--border),transparent)}
.sec-lbl{font-family:'Rajdhani',sans-serif;font-size:11px;font-weight:700;letter-spacing:.42em;color:var(--muted);text-transform:uppercase;white-space:nowrap}
/* RARITY HEADERS */
.rarity-header{display:flex;align-items:center;gap:12px;margin:1.8rem 0 .9rem}
.rarity-line{flex:1;height:1px}
.rarity-lbl{font-family:'Russo One',sans-serif;font-size:.65rem;letter-spacing:.22em;text-transform:uppercase;
  white-space:nowrap;padding:4px 14px;clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%)}
.rh-common .rarity-line{background:rgba(170,170,170,.18)}.rh-common .rarity-lbl{color:var(--c-common);background:rgba(170,170,170,.07);border:1px solid rgba(170,170,170,.22)}
.rh-rare   .rarity-line{background:rgba(77,166,255,.18)}.rh-rare   .rarity-lbl{color:var(--c-rare);background:rgba(77,166,255,.07);border:1px solid rgba(77,166,255,.28)}
.rh-epic   .rarity-line{background:rgba(192,132,252,.18)}.rh-epic   .rarity-lbl{color:var(--c-epic);background:rgba(192,132,252,.07);border:1px solid rgba(192,132,252,.28)}
.rh-legend .rarity-line{background:rgba(255,215,0,.18)}.rh-legend .rarity-lbl{color:var(--c-legend);background:rgba(255,215,0,.07);border:1px solid rgba(255,215,0,.32)}
/* CARD GRID */
.card-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:clamp(.7rem,1.5vw,1rem)}
/* SPELL CARD — mirrors mbtn DNA from main_menu */
.sc{position:relative;overflow:hidden;display:flex;flex-direction:column;
  padding:clamp(.9rem,1.8vw,1.2rem);border:1px solid;cursor:default;backdrop-filter:blur(12px);
  background:var(--card);clip-path:polygon(14px 0%,100% 0%,calc(100% - 14px) 100%,0% 100%);
  transition:transform .32s cubic-bezier(.34,1.56,.64,1),box-shadow .28s ease;opacity:0;transform:translateY(20px)}
.sc.show{opacity:1;transform:translateY(0)}
.sc::before{content:'';position:absolute;inset:0;opacity:0;transition:opacity .3s;pointer-events:none}
.sc::after{content:'';position:absolute;top:0;left:0;right:0;height:1.5px;opacity:0;transition:opacity .3s}
.sc:hover::before{opacity:1}.sc:hover::after{opacity:1}
.sc:hover{transform:translateY(-2px)}.sc:hover .sbc{opacity:.8}
.sc .shine{position:absolute;top:0;left:-100%;width:55%;height:100%;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,.06),transparent);
  transform:skewX(-15deg);transition:left .6s ease;pointer-events:none}
.sc:hover .shine{left:160%}
.sbc{position:absolute;width:16px;height:16px;opacity:0;transition:opacity .3s;pointer-events:none}
.sbc::before,.sbc::after{content:'';position:absolute;background:currentColor}
.sbc::before{width:1.5px;height:12px}.sbc::after{width:12px;height:1.5px}
.sbc-tl{top:8px;left:8px}.sbc-br{bottom:8px;right:8px;transform:scale(-1)}
.sc-common{border-color:rgba(170,170,170,.28)}
.sc-common::before{background:radial-gradient(ellipse at top left,rgba(170,170,170,.09),transparent)}
.sc-common::after{background:linear-gradient(90deg,var(--c-common),transparent)}
.sc-common .sbc{color:var(--c-common)}
.sc-common:hover{box-shadow:0 20px 55px rgba(170,170,170,.2),0 0 0 1px rgba(170,170,170,.28)}
.sc-rare{border-color:rgba(77,166,255,.32)}
.sc-rare::before{background:radial-gradient(ellipse at top left,rgba(77,166,255,.1),transparent)}
.sc-rare::after{background:linear-gradient(90deg,var(--c-rare),transparent)}
.sc-rare .sbc{color:var(--c-rare)}
.sc-rare:hover{box-shadow:0 20px 55px rgba(77,166,255,.28),0 0 0 1px rgba(77,166,255,.32)}
.sc-epic{border-color:rgba(192,132,252,.32)}
.sc-epic::before{background:radial-gradient(ellipse at top left,rgba(192,132,252,.1),transparent)}
.sc-epic::after{background:linear-gradient(90deg,var(--c-epic),transparent)}
.sc-epic .sbc{color:var(--c-epic)}
.sc-epic:hover{box-shadow:0 20px 55px rgba(192,132,252,.25),0 0 0 1px rgba(192,132,252,.32)}
.sc-legend{border-color:rgba(255,215,0,.35)}
.sc-legend::before{background:radial-gradient(ellipse at top left,rgba(255,215,0,.1),transparent)}
.sc-legend::after{background:linear-gradient(90deg,var(--c-legend),transparent)}
.sc-legend .sbc{color:var(--c-legend)}
.sc-legend:hover{box-shadow:0 20px 55px rgba(255,215,0,.28),0 0 0 1px rgba(255,215,0,.35)}
.sc-tag{position:absolute;top:10px;right:10px;font-family:'Rajdhani',sans-serif;font-size:9px;font-weight:700;
  letter-spacing:.2em;text-transform:uppercase;padding:3px 10px;z-index:1;
  clip-path:polygon(4px 0%,100% 0%,calc(100% - 4px) 100%,0% 100%)}
.tag-common{background:rgba(170,170,170,.1);border:1px solid rgba(170,170,170,.28);color:var(--c-common)}
.tag-rare{background:rgba(77,166,255,.1);border:1px solid rgba(77,166,255,.32);color:var(--c-rare)}
.tag-epic{background:rgba(192,132,252,.1);border:1px solid rgba(192,132,252,.32);color:var(--c-epic)}
.tag-legend{background:rgba(255,215,0,.1);border:1px solid rgba(255,215,0,.35);color:var(--c-legend)}
.sc-icon{font-size:clamp(24px,3.2vw,36px);position:relative;z-index:1;margin-bottom:.4rem;
  transition:transform .3s ease;line-height:1;filter:drop-shadow(0 2px 10px rgba(0,0,0,.55))}
.sc:hover .sc-icon{transform:scale(1.02)}
.sc-name{font-family:'Russo One',sans-serif;font-size:clamp(.66rem,1.1vw,.78rem);letter-spacing:.08em;
  text-transform:uppercase;position:relative;z-index:1;margin-bottom:3px}
.sc-common .sc-name{color:var(--c-common)}.sc-rare .sc-name{color:var(--c-rare)}
.sc-epic .sc-name{color:var(--c-epic)}.sc-legend .sc-name{color:var(--c-legend)}
.sc-div{width:100%;height:1px;background:var(--border);margin:.5rem 0;position:relative;z-index:1}
.sc-desc{font-family:'Rajdhani',sans-serif;font-size:clamp(.62rem,.85vw,.7rem);font-weight:600;
  color:var(--muted);line-height:1.42;letter-spacing:.02em;position:relative;z-index:1;flex:1;margin-bottom:.6rem}
.sc-tip{font-family:'Rajdhani',sans-serif;font-size:.62rem;color:rgba(238,240,255,0.8);font-weight:700;
  letter-spacing:.06em;background:rgba(238,240,255,0.03);border:1px solid rgba(238,240,255,0.12);padding:4px 8px;margin-bottom:.7rem;
  position:relative;z-index:1;clip-path:polygon(4px 0%,100% 0%,calc(100% - 4px) 100%,0% 100%)}
.sc-stat{position:relative;z-index:1;border-top:1px solid var(--border);padding-top:.6rem}
.sc-stat-lbl{font-family:'Rajdhani',sans-serif;font-size:.55rem;font-weight:700;letter-spacing:.3em;
  text-transform:uppercase;color:var(--muted);margin-bottom:5px}
.sc-bar-wrap{height:5px;background:rgba(238,240,255,.06);margin-bottom:4px;
  clip-path:polygon(3px 0%,100% 0%,calc(100% - 3px) 100%,0% 100%)}
.sc-bar{height:100%;width:0%;transition:width 1.1s cubic-bezier(.25,.46,.45,.94)}
.sc-common .sc-bar{background:linear-gradient(90deg,var(--c-common),rgba(170,170,170,.25))}
.sc-rare   .sc-bar{background:linear-gradient(90deg,var(--c-rare),rgba(77,166,255,.25))}
.sc-epic   .sc-bar{background:linear-gradient(90deg,var(--c-epic),rgba(192,132,252,.25))}
.sc-legend .sc-bar{background:linear-gradient(90deg,var(--c-legend),rgba(255,215,0,.25))}
.sc-count{font-family:'Bebas Neue',sans-serif;font-size:1.1rem;letter-spacing:.08em;color:var(--text)}
.sc-count-sub{font-size:.56rem;color:var(--muted);letter-spacing:.16em;text-transform:uppercase;font-weight:600}
/* COMBO/COUNTER */
.cc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(310px,1fr));gap:clamp(.8rem,1.6vw,1.1rem)}
.cc-card{position:relative;overflow:hidden;display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:.8rem;
  padding:clamp(.9rem,1.8vw,1.2rem);border:1px solid;backdrop-filter:blur(12px);background:var(--card);
  clip-path:polygon(14px 0%,100% 0%,calc(100% - 14px) 100%,0% 100%);
  transition:transform .28s cubic-bezier(.34,1.56,.64,1),box-shadow .28s ease;opacity:0;transform:translateX(-16px)}
.cc-card.show{opacity:1;transform:translateX(0)}
.cc-card::before{content:'';position:absolute;inset:0;opacity:0;transition:opacity .3s;pointer-events:none}
.cc-card::after{content:'';position:absolute;top:0;left:0;right:0;height:1.5px;opacity:0;transition:opacity .3s}
.cc-card:hover::before{opacity:1}.cc-card:hover::after{opacity:1}
.cc-card:hover{transform:translateY(-1px)}
.cc-card .shine{position:absolute;top:0;left:-100%;width:40%;height:100%;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,.05),transparent);
  transform:skewX(-15deg);transition:left .7s ease;pointer-events:none}
.cc-card:hover .shine{left:160%}
.cc-combo{border-color:rgba(125,255,77,.28)}
.cc-combo::before{background:radial-gradient(ellipse at center,rgba(125,255,77,.06),transparent)}
.cc-combo::after{background:linear-gradient(90deg,var(--scissors),transparent)}
.cc-combo:hover{box-shadow:0 18px 50px rgba(125,255,77,.18),0 0 0 1px rgba(125,255,77,.28)}
.cc-counter{border-color:rgba(255,77,77,.28)}
.cc-counter::before{background:radial-gradient(ellipse at center,rgba(255,77,77,.06),transparent)}
.cc-counter::after{background:linear-gradient(90deg,var(--rock),transparent)}
.cc-counter:hover{box-shadow:0 18px 50px rgba(255,77,77,.18),0 0 0 1px rgba(255,77,77,.28)}
.cc-mini{display:flex;flex-direction:column;align-items:center;gap:4px;text-align:center;position:relative;z-index:1}
.cc-mini-icon{font-size:clamp(1.4rem,2.5vw,1.9rem);line-height:1;transition:transform .3s ease;filter:drop-shadow(0 2px 8px rgba(0,0,0,.5))}
.cc-card:hover .cc-mini-icon{transform:scale(1.02)}
.cc-mini-name{font-family:'Russo One',sans-serif;font-size:.58rem;letter-spacing:.07em;text-transform:uppercase;line-height:1.25}
.cc-mini-badge{font-size:.48rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;
  padding:2px 7px;clip-path:polygon(3px 0%,100% 0%,calc(100% - 3px) 100%,0% 100%)}
.badge-common{color:var(--c-common);background:rgba(170,170,170,.08);border:1px solid rgba(170,170,170,.22)}
.badge-rare{color:var(--c-rare);background:rgba(77,166,255,.08);border:1px solid rgba(77,166,255,.25)}
.badge-epic{color:var(--c-epic);background:rgba(192,132,252,.08);border:1px solid rgba(192,132,252,.25)}
.badge-legend{color:var(--c-legend);background:rgba(255,215,0,.08);border:1px solid rgba(255,215,0,.28)}
.cc-mid{display:flex;flex-direction:column;align-items:center;gap:3px;position:relative;z-index:1}
.cc-mid-type{font-size:.48rem;font-weight:700;letter-spacing:.26em;text-transform:uppercase;color:var(--muted)}
.cc-mid-arrow{font-size:1.25rem;animation:arr .9s ease-in-out infinite alternate}
@keyframes arr{from{transform:scale(.88);opacity:.7}to{transform:scale(1.12);opacity:1}}
.cc-mid-label{font-family:'Russo One',sans-serif;font-size:.56rem;letter-spacing:.07em;text-transform:uppercase;
  color:var(--text);text-align:center;line-height:1.25;margin-top:2px}
.cc-mid-desc{font-size:.56rem;color:var(--muted);font-weight:500;letter-spacing:.02em;line-height:1.35;text-align:center;max-width:105px}
.vrow{display:flex;align-items:center;gap:16px;margin-top:2.5rem}
.vline{flex:1;height:1px;background:linear-gradient(to right,transparent,var(--border),transparent)}
.vtxt{font-family:'Rajdhani',sans-serif;font-size:11px;font-weight:700;letter-spacing:.42em;color:var(--muted);text-transform:uppercase}
/* ── WEAPON GRID (Senjata Utama — 3 col) ── */
.weapon-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:clamp(.8rem,1.8vw,1.2rem)}
/* base weapon card */
.ccard{position:relative;overflow:hidden;display:flex;flex-direction:column;
  padding:clamp(1rem,2.2vw,1.5rem);border:1px solid;cursor:default;backdrop-filter:blur(12px);
  background:var(--card);clip-path:polygon(14px 0%,100% 0%,calc(100% - 14px) 100%,0% 100%);
  transition:transform .32s cubic-bezier(.34,1.56,.64,1),box-shadow .28s ease;opacity:0;transform:translateY(22px)}
.ccard.show{opacity:1;transform:translateY(0)}
.ccard::before{content:'';position:absolute;inset:0;opacity:0;transition:opacity .3s;pointer-events:none}
.ccard::after{content:'';position:absolute;top:0;left:0;right:0;height:1.5px;opacity:0;transition:opacity .3s}
.ccard:hover::before{opacity:1}.ccard:hover::after{opacity:1}
.ccard:hover{transform:translateY(-2px)}.ccard:hover .bc{opacity:.8}
.ccard .shine{position:absolute;top:0;left:-100%;width:55%;height:100%;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,.06),transparent);
  transform:skewX(-15deg);transition:left .6s ease;pointer-events:none}
.ccard:hover .shine{left:160%}
/* rock / paper / scissors variants */
.cc-r{border-color:rgba(255,77,77,.38)}.cc-r::before{background:radial-gradient(ellipse at top left,rgba(255,77,77,.12),transparent)}
.cc-r::after{background:linear-gradient(90deg,var(--rock),transparent)}.cc-r .bc{color:var(--rock)}
.cc-r:hover{box-shadow:0 22px 65px rgba(255,77,77,.35),0 0 0 1px rgba(255,77,77,.38)}
.cc-p{border-color:rgba(77,166,255,.38)}.cc-p::before{background:radial-gradient(ellipse at top left,rgba(77,166,255,.12),transparent)}
.cc-p::after{background:linear-gradient(90deg,var(--paper),transparent)}.cc-p .bc{color:var(--paper)}
.cc-p:hover{box-shadow:0 22px 65px rgba(77,166,255,.35),0 0 0 1px rgba(77,166,255,.38)}
.cc-s{border-color:rgba(125,255,77,.38)}.cc-s::before{background:radial-gradient(ellipse at top left,rgba(125,255,77,.1),transparent)}
.cc-s::after{background:linear-gradient(90deg,var(--scissors),transparent)}.cc-s .bc{color:var(--scissors)}
.cc-s:hover{box-shadow:0 22px 65px rgba(125,255,77,.28),0 0 0 1px rgba(125,255,77,.38)}
/* weapon card tags */
.cc-tag{position:absolute;top:10px;right:10px;font-family:'Rajdhani',sans-serif;font-size:9px;font-weight:700;
  letter-spacing:.2em;text-transform:uppercase;padding:3px 10px;z-index:1;
  clip-path:polygon(4px 0%,100% 0%,calc(100% - 4px) 100%,0% 100%)}
.tag-r{background:rgba(255,77,77,.18);border:1px solid rgba(255,77,77,.45);color:#ff9090}
.tag-p{background:rgba(77,166,255,.18);border:1px solid rgba(77,166,255,.45);color:#90c4ff}
.tag-s{background:rgba(125,255,77,.14);border:1px solid rgba(125,255,77,.45);color:#a8e860}
/* weapon card art */
.cc-art{width:90px;height:90px;display:flex;align-items:center;justify-content:center;
  margin:1rem auto .8rem;position:relative;z-index:1}
.cc-art img{width:80px;height:80px;object-fit:contain;filter:drop-shadow(0 4px 16px rgba(0,0,0,.5));transition:transform .35s ease}
.ccard:hover .cc-art img{transform:scale(1.02)}
/* weapon name & sub */
.cc-name{font-family:'Bebas Neue',sans-serif;font-size:clamp(1.3rem,3vw,1.7rem);letter-spacing:.1em;
  text-align:center;position:relative;z-index:1;margin-bottom:1px}
.cc-r .cc-name{color:var(--rock);text-shadow:0 0 22px var(--gr)}
.cc-p .cc-name{color:var(--paper);text-shadow:0 0 22px var(--gp)}
.cc-s .cc-name{color:var(--scissors);text-shadow:0 0 22px var(--gs)}
.cc-sub{font-family:'Rajdhani',sans-serif;font-size:.6rem;font-weight:700;letter-spacing:.35em;
  text-transform:uppercase;color:var(--muted);text-align:center;margin-bottom:.9rem;position:relative;z-index:1}
.cc-desc{font-family:'Rajdhani',sans-serif;font-size:.74rem;font-weight:500;color:var(--muted);
  line-height:1.5;letter-spacing:.02em;text-align:center;position:relative;z-index:1;
  border-top:1px solid var(--border);padding-top:.75rem;margin-bottom:.9rem}
/* matchup pills */
.cc-matchup{display:flex;flex-direction:column;gap:5px;position:relative;z-index:1;margin-bottom:.9rem}
.cc-match-row{display:flex;align-items:center;gap:7px;font-family:'Rajdhani',sans-serif;font-size:.68rem;
  font-weight:700;letter-spacing:.08em;padding:5px 10px;
  clip-path:polygon(4px 0%,100% 0%,calc(100% - 4px) 100%,0% 100%)}
.cc-match-win{background:var(--cc-win-bg);border:1px solid var(--cc-win-border);color:var(--cc-win-color)}
.cc-match-lose{background:var(--cc-lose-bg);border:1px solid var(--cc-lose-border);color:var(--cc-lose-color)}
.cc-match-row .mi{font-size:.95rem}.cc-match-row .ml{font-size:.58rem;letter-spacing:.15em;text-transform:uppercase}
.cc-match-row .mk{font-weight:700;letter-spacing:.05em}
/* weapon usage stats */
.cc-stats{position:relative;z-index:1;border-top:1px solid var(--border);padding-top:.75rem}
.cc-stat-label{font-family:'Rajdhani',sans-serif;font-size:.58rem;font-weight:700;
  letter-spacing:.3em;text-transform:uppercase;color:var(--muted);margin-bottom:6px}
.cc-stat-row{display:flex;align-items:center;gap:10px;margin-bottom:4px}
.cc-stat-bar-wrap{flex:1;height:5px;background:rgba(238,240,255,.07);
  clip-path:polygon(3px 0%,100% 0%,calc(100% - 3px) 100%,0% 100%)}
.cc-stat-bar{height:100%;width:0%;transition:width 1.2s cubic-bezier(.25,.46,.45,.94)}
.rock-bar{background:linear-gradient(90deg,var(--rock),rgba(255,77,77,.4))}
.paper-bar{background:linear-gradient(90deg,var(--paper),rgba(77,166,255,.4))}
.scissors-bar{background:linear-gradient(90deg,var(--scissors),rgba(125,255,77,.4))}
.cc-stat-num{font-family:'Bebas Neue',sans-serif;font-size:.95rem;letter-spacing:.08em;min-width:30px;text-align:right;color:var(--text)}
.cc-usage-big{font-family:'Bebas Neue',sans-serif;font-size:clamp(1.6rem,3vw,2.2rem);letter-spacing:.08em;text-align:center;margin-top:4px}
.cc-r .cc-usage-big{color:var(--rock);text-shadow:0 0 18px var(--gr)}
.cc-p .cc-usage-big{color:var(--paper);text-shadow:0 0 18px var(--gp)}
.cc-s .cc-usage-big{color:var(--scissors);text-shadow:0 0 18px var(--gs)}
.cc-usage-sub{font-size:.62rem;color:var(--muted);letter-spacing:.18em;text-transform:uppercase;text-align:center;margin-top:2px;font-weight:600}
@media(max-width:680px){.card-grid{grid-template-columns:1fr 1fr}.cc-grid{grid-template-columns:1fr}.weapon-grid{grid-template-columns:1fr}.pstats,.rank-pill{display:none}}
@media(max-width:420px){.card-grid{grid-template-columns:1fr}.content-wrap{padding-left:14px;padding-right:14px}}

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
  --text:#1a1d2e;--muted:rgba(26,29,46,.76);--border:rgba(0,0,0,.09);
  --rock:#d93030;--paper:#2874c2;--scissors:#1a9940;
  --gr:rgba(217,48,48,.45);--gp:rgba(40,116,194,.45);--gs:rgba(26,153,64,.45);
  --c-common:#888;--c-rare:#2874c2;--c-epic:#9333d4;--c-legend:#c8a000;
  --cc-win-bg:rgba(26,153,64,.08);--cc-win-border:rgba(26,153,64,.25);--cc-win-color:#1a9940;
  --cc-lose-bg:rgba(217,48,48,.08);--cc-lose-border:rgba(217,48,48,.25);--cc-lose-color:#d93030;
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
[data-theme="light"] .btn-out{background:rgba(217,48,48,.05);border-color:rgba(217,48,48,.18);color:rgba(217,48,48,.65);}
[data-theme="light"] .btn-out:hover{background:rgba(217,48,48,.12);border-color:rgba(217,48,48,.4);color:#d93030;}
[data-theme="light"] .btn-back{background:transparent;border-color:rgba(40,116,194,.18);color:rgba(40,116,194,.8);}
[data-theme="light"] .btn-back:hover{background:rgba(40,116,194,.08);border-color:rgba(40,116,194,.35);color:#2874c2;}
[data-theme="light"] .rank-pill{background:linear-gradient(135deg,rgba(240,244,252,.9),rgba(230,234,248,.95));box-shadow:0 0 12px rgba(0,0,0,.05);}
[data-theme="light"] .sc{background:rgba(255,255,255,.75);}
[data-theme="light"] .sc-desc{color:rgba(26,29,46,.76);}
[data-theme="light"] .ccard{background:rgba(255,255,255,.75);}
[data-theme="light"] .cc-card{background:rgba(255,255,255,.75);}
[data-theme="light"] .sc-tip{background:rgba(0,0,0,.03);border-color:rgba(0,0,0,.08);color:rgba(26,29,46,.78);}
[data-theme="light"] .toast{background:rgba(240,244,252,.96);border-color:rgba(40,116,194,.15);color:var(--text);}
[data-theme="light"] .btn-theme-toggle{background:transparent;border-color:rgba(40,116,194,.18);color:rgba(40,116,194,.8);}
[data-theme="light"] .btn-theme-toggle:hover{background:rgba(40,116,194,.08);border-color:rgba(40,116,194,.35);color:#2874c2;}
/* ── FIX: text & glow tabrakan ── */
[data-theme="light"] .ptitle{
  background:none;
  -webkit-text-fill-color:var(--text);
  color:var(--text);
  text-shadow:none;
}
[data-theme="light"] .ptitle::after{opacity:0;}
[data-theme="light"] .page-subtitle{color:rgba(26,29,46,.45);}
[data-theme="light"] .atag{color:rgba(26,29,46,.4);}
[data-theme="light"] .atag-line{background:linear-gradient(to right,transparent,rgba(40,116,194,.25),transparent);}
[data-theme="light"] .pid{color:rgba(26,29,46,.4);}
[data-theme="light"] .phint{color:rgba(26,29,46,.35);}
[data-theme="light"] .rank-pts{color:rgba(26,29,46,.4);}
[data-theme="light"] .sc-name{color:#1a1d2e;text-shadow:none;}
[data-theme="light"] .sc-desc{color:rgba(26,29,46,.7);}
[data-theme="light"] .sc-tip{color:rgba(26,29,46,.45);border-color:rgba(0,0,0,.06);}
[data-theme="light"] .cc-name{color:#1a1d2e;text-shadow:none;}
[data-theme="light"] .cc-usage-sub{color:rgba(26,29,46,.45);}
[data-theme="light"] .cc-sub{color:rgba(26,29,46,.55);}
[data-theme="light"] .cc-desc{color:rgba(26,29,46,.7);border-top-color:rgba(0,0,0,.07);}
[data-theme="light"] .cc-mid-desc{color:rgba(26,29,46,.7);}
[data-theme="light"] .cc-r .cc-name,
[data-theme="light"] .cc-p .cc-name,
[data-theme="light"] .cc-s .cc-name,
[data-theme="light"] .cc-r .cc-usage-big,
[data-theme="light"] .cc-p .cc-usage-big,
[data-theme="light"] .cc-s .cc-usage-big {
  text-shadow: none;
}
[data-theme="light"] .cc-r { border-color: rgba(217,48,48,.35); }
[data-theme="light"] .cc-p { border-color: rgba(40,116,194,.35); }
[data-theme="light"] .cc-s { border-color: rgba(26,153,64,.35); }
[data-theme="light"] .cc-r:hover { box-shadow: 0 12px 30px rgba(217,48,48,.12), 0 0 0 1px rgba(217,48,48,.35); }
[data-theme="light"] .cc-p:hover { box-shadow: 0 12px 30px rgba(40,116,194,.12), 0 0 0 1px rgba(40,116,194,.35); }
[data-theme="light"] .cc-s:hover { box-shadow: 0 12px 30px rgba(26,153,64,.12), 0 0 0 1px rgba(26,153,64,.35); }
[data-theme="light"] .tag-r { background: rgba(217,48,48,.08); border-color: rgba(217,48,48,.3); color: #d93030; }
[data-theme="light"] .tag-p { background: rgba(40,116,194,.08); border-color: rgba(40,116,194,.3); color: #2874c2; }
[data-theme="light"] .tag-s { background: rgba(26,153,64,.08); border-color: rgba(26,153,64,.3); color: #1a9940; }
[data-theme="light"] .cc-match-win {
  background: rgba(22, 163, 74, 0.08) !important;
  border-color: rgba(22, 163, 74, 0.25) !important;
  color: #166534 !important;
}
[data-theme="light"] .cc-match-lose {
  background: rgba(220, 38, 38, 0.08) !important;
  border-color: rgba(220, 38, 38, 0.25) !important;
  color: #991b1b !important;
}
[data-theme="light"] .cc-stat-bar-wrap { background: rgba(0,0,0,.08); }
body,html,.pbar,.sc,.ccard,.cc-card,.toast,.btn-back,.btn-out,.rank-pill{
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
<div class="hex-layer"></div><div class="noise"></div>
<div class="elines" id="EL"></div>
<div class="scanline"></div><div class="vignette"></div>
<div class="particles" id="PT"></div>
<div class="corner c-tl"></div><div class="corner c-tr"></div>
<div class="corner c-bl"></div><div class="corner c-br"></div>

<div class="pbar">
  <a class="pinfo" href="profile.php?from=collection.php">
    <div class="pav"><?php echo $menu_avatar?></div>
    <div>
      <div class="pname"><?php echo $menu_dispname?></div>
      <div class="pid">@<?php echo htmlspecialchars($player_name)?></div>
      <div class="phint">&#128100; Lihat Profil &rarr;</div>
    </div>
  </a>


  <div style="display:flex;align-items:center;gap:8px">
    <a class="btn-back" href="main_menu.php">&larr; Kembali ke Menu</a>
    <button class="btn-theme-toggle" id="btnThemeToggle" title="Ganti Tema"><span class="theme-icon">Light Mode</span></button>
  </div>
</div>

<div class="content-wrap">
  <div class="page-top" id="pageTop">
    <div class="atag"><div class="atag-line"></div>&#10022; Battle Arena &#10022;<div class="atag-line rev"></div></div>
    <div class="ptitle" data-text="KOLEKSI KARTU">
      <span class="tw-k">KOLE</span><span class="tw-o">KSI</span>&nbsp;<span class="tw-l">KAR</span><span class="tw-k">TU</span>
    </div>
    <div class="psub">24 kartu &mdash; statistik pemakaian gabungan AI &amp; PvP</div>
  </div>

  <div class="sec-div"><div class="sec-line"></div><div class="sec-lbl">&#10022; Senjata Utama (AI + PvP) &#10022;</div><div class="sec-line"></div></div>

  <div class="weapon-grid">

    <!-- BATU -->
    <div class="ccard cc-r" id="cardRock">
      <div class="shine"></div><div class="bc bc-tl"></div><div class="bc bc-br"></div>
      <span class="cc-tag tag-r">SERANGAN</span>
      <div class="cc-art"><img src="assets/Rock.png" alt="Batu"></div>
      <div class="cc-name">BATU</div>
      <div class="cc-sub">Rock &middot; Kelas Serangan</div>
      <div class="cc-desc">Senjata keras yang meremukkan Gunting dengan satu tumbukan.
        Pilihan klasik yang mengandalkan kekuatan brute force &mdash;
        sederhana, tapi mematikan jika lawan salah baca.</div>
      <div class="cc-matchup">
        <div class="cc-match-row cc-match-win"><span class="mi">&#9986;&#65039;</span><span class="ml">MENANG vs</span><span class="mk">Gunting</span></div>
        <div class="cc-match-row cc-match-lose"><span class="mi">&#128196;</span><span class="ml">KALAH vs</span><span class="mk">Kertas</span></div>
      </div>
      <div class="cc-stats">
        <div class="cc-stat-label">Total Usage (AI + PvP)</div>
        <div class="cc-stat-row">
          <div class="cc-stat-bar-wrap"><div class="cc-stat-bar rock-bar" id="barRock"></div></div>
          <div class="cc-stat-num" id="pctRock">0%</div>
        </div>
        <div class="cc-usage-big" id="cntRock">&mdash;</div>
        <div class="cc-usage-sub">kali digunakan (AI + PvP)</div>
      </div>
    </div>

    <!-- KERTAS -->
    <div class="ccard cc-p" id="cardPaper">
      <div class="shine"></div><div class="bc bc-tl"></div><div class="bc bc-br"></div>
      <span class="cc-tag tag-p">KONTROL</span>
      <div class="cc-art"><img src="assets/Paper.png" alt="Kertas"></div>
      <div class="cc-name">KERTAS</div>
      <div class="cc-sub">Paper &middot; Kelas Kontrol</div>
      <div class="cc-desc">Selimut yang membekuk Batu sepenuhnya. Kartu paling cerdas
        secara meta &mdash; memanfaatkan kecenderungan lawan memilih
        Batu sebagai pilihan default untuk membalik keadaan.</div>
      <div class="cc-matchup">
        <div class="cc-match-row cc-match-win"><span class="mi">&#129704;</span><span class="ml">MENANG vs</span><span class="mk">Batu</span></div>
        <div class="cc-match-row cc-match-lose"><span class="mi">&#9986;&#65039;</span><span class="ml">KALAH vs</span><span class="mk">Gunting</span></div>
      </div>
      <div class="cc-stats">
        <div class="cc-stat-label">Total Usage (AI + PvP)</div>
        <div class="cc-stat-row">
          <div class="cc-stat-bar-wrap"><div class="cc-stat-bar paper-bar" id="barPaper"></div></div>
          <div class="cc-stat-num" id="pctPaper">0%</div>
        </div>
        <div class="cc-usage-big" id="cntPaper">&mdash;</div>
        <div class="cc-usage-sub">kali digunakan (AI + PvP)</div>
      </div>
    </div>

    <!-- GUNTING -->
    <div class="ccard cc-s" id="cardScissors">
      <div class="shine"></div><div class="bc bc-tl"></div><div class="bc bc-br"></div>
      <span class="cc-tag tag-s">COUNTER</span>
      <div class="cc-art"><img src="assets/Scissors.png" alt="Gunting"></div>
      <div class="cc-name">GUNTING</div>
      <div class="cc-sub">Scissors &middot; Kelas Counter</div>
      <div class="cc-desc">Mata pisau yang memotong Kertas dengan presisi tinggi.
        Kartu risiko-tinggi dengan reward maksimal &mdash; sempurna
        sebagai counter untuk lawan yang terlalu agresif pakai Kertas.</div>
      <div class="cc-matchup">
        <div class="cc-match-row cc-match-win"><span class="mi">&#128196;</span><span class="ml">MENANG vs</span><span class="mk">Kertas</span></div>
        <div class="cc-match-row cc-match-lose"><span class="mi">&#129704;</span><span class="ml">KALAH vs</span><span class="mk">Batu</span></div>
      </div>
      <div class="cc-stats">
        <div class="cc-stat-label">Total Usage (AI + PvP)</div>
        <div class="cc-stat-row">
          <div class="cc-stat-bar-wrap"><div class="cc-stat-bar scissors-bar" id="barScissors"></div></div>
          <div class="cc-stat-num" id="pctScissors">0%</div>
        </div>
        <div class="cc-usage-big" id="cntScissors">&mdash;</div>
        <div class="cc-usage-sub">kali digunakan (AI + PvP)</div>
      </div>
    </div>

  </div><!-- /weapon-grid -->

  <div class="sec-div"><div class="sec-line"></div><div class="sec-lbl">&#10022; Koleksi Kartu (AI + PvP) &#10022;</div><div class="sec-line"></div></div>

  <div class="rarity-header rh-common"><div class="rarity-line"></div><div class="rarity-lbl">&#11041; Common &mdash; 8 Kartu</div><div class="rarity-line"></div></div>
  <div class="card-grid" id="grid-common"></div>

  <div class="rarity-header rh-rare"><div class="rarity-line"></div><div class="rarity-lbl">&#9672; Rare &mdash; 7 Kartu</div><div class="rarity-line"></div></div>
  <div class="card-grid" id="grid-rare"></div>

  <div class="rarity-header rh-epic"><div class="rarity-line"></div><div class="rarity-lbl">&#10022; Epic &mdash; 6 Kartu</div><div class="rarity-line"></div></div>
  <div class="card-grid" id="grid-epic"></div>

  <div class="rarity-header rh-legend"><div class="rarity-line"></div><div class="rarity-lbl">&#9733; Legend &mdash; 3 Kartu</div><div class="rarity-line"></div></div>
  <div class="card-grid" id="grid-legend"></div>

  <div class="sec-div" style="margin-top:3.5rem"><div class="sec-line"></div><div class="sec-lbl">&#10022; Combo Kartu &mdash; Terkuat &rarr; Terlemah &#10022;</div><div class="sec-line"></div></div>
  <div style="display:flex;align-items:center;gap:10px;margin-bottom:.9rem">
    <span style="font-family:'Russo One',sans-serif;font-size:.63rem;letter-spacing:.18em;color:var(--scissors);text-transform:uppercase">&#9654; Sinergi &amp; Combo</span>
    <div style="flex:1;height:1px;background:color-mix(in srgb, var(--scissors) 12%, transparent)"></div>
  </div>
  <div class="cc-grid" id="grid-combo"></div>

  <div style="display:flex;align-items:center;gap:10px;margin:2.2rem 0 .9rem">
    <span style="font-family:'Russo One',sans-serif;font-size:.63rem;letter-spacing:.18em;color:var(--rock);text-transform:uppercase">&#9654; Counter Matchup</span>
    <div style="flex:1;height:1px;background:color-mix(in srgb, var(--rock) 12%, transparent)"></div>
  </div>
  <div class="cc-grid" id="grid-counter"></div>

  <div class="vrow"><div class="vline"></div><div class="vtxt">&#10022; Batu &middot; Gunting &middot; Kertas &#10022;</div><div class="vline"></div></div>
</div>
<div class="toast" id="toast"></div>

<script>
/* CANVAS — identical to main_menu */
const cv=document.getElementById('bg'),cx=cv.getContext('2d');
let W,H,NS=[];
const COLS=['rgba(255,77,77,','rgba(77,166,255,','rgba(125,255,77,'];
function rsz(){W=cv.width=innerWidth;H=cv.height=innerHeight}
function mkN(){NS=Array.from({length:70},()=>({
  x:Math.random()*W,y:Math.random()*H,vx:(Math.random()-.5)*.55,vy:(Math.random()-.5)*.55,
  r:Math.random()*2.2+.8,col:COLS[Math.floor(Math.random()*3)],a:Math.random()*.55+.1,maxA:Math.random()*.55+.1,da:.002}))}
function frame(){
  cx.clearRect(0,0,W,H);
  const g=cx.createRadialGradient(W/2,H*.45,0,W/2,H*.45,Math.max(W,H)*.72);
  g.addColorStop(0,'rgba(15,18,38,.97)');g.addColorStop(1,'rgba(5,6,13,1)');
  cx.fillStyle=g;cx.fillRect(0,0,W,H);
  for(const n of NS){
    n.x+=n.vx;n.y+=n.vy;if(n.x<0||n.x>W)n.vx*=-1;if(n.y<0||n.y>H)n.vy*=-1;
    n.a+=n.da;if(n.a>n.maxA||n.a<.05)n.da*=-1;
    for(const m of NS){const d=Math.hypot(n.x-m.x,n.y-m.y);
      if(d<170){cx.beginPath();cx.moveTo(n.x,n.y);cx.lineTo(m.x,m.y);
        cx.strokeStyle=n.col+(1-d/170)*.07+')';cx.lineWidth=.5;cx.stroke();}}
    cx.beginPath();cx.arc(n.x,n.y,n.r,0,Math.PI*2);cx.fillStyle=n.col+n.a+')';cx.fill();
    if(n.r>1.8){cx.beginPath();cx.arc(n.x,n.y,n.r*2.5,0,Math.PI*2);cx.fillStyle=n.col+n.a*.2+')';cx.fill();}
  }
  for(let i=0;i<140;i++){const sx=(i*137.5)%W,sy=(i*93.7)%H;
    const sa=.07+.45*Math.abs(Math.sin(Date.now()*.0008+i));
    cx.beginPath();cx.arc(sx,sy,.6,0,Math.PI*2);cx.fillStyle=`rgba(238,240,255,${sa})`;cx.fill();}
  requestAnimationFrame(frame);}
window.addEventListener('resize',()=>{rsz();mkN()});rsz();mkN();frame();
const ELC=document.getElementById('EL');
for(let i=0;i<10;i++){const e=document.createElement('div');e.className='el';
  e.style.cssText=`left:${Math.random()*100}%;height:${Math.random()*50+20}px;animation-duration:${Math.random()*9+5}s;animation-delay:${Math.random()*9}s;opacity:.38;`;ELC.appendChild(e);}
const PC=document.getElementById('PT');
for(let i=0;i<30;i++){const p=document.createElement('div');p.className='p';
  const s=Math.random()*4.5+1,col=COLS[i%3];
  p.style.cssText=`left:${Math.random()*100}%;width:${s}px;height:${s}px;background:${col}${Math.random()*.5+.25});box-shadow:0 0 ${s*3}px ${col}.55);animation-duration:${Math.random()*16+9}s;animation-delay:${Math.random()*16}s;`;PC.appendChild(p);}
function showToast(m,d=2400){const t=document.getElementById('toast');t.textContent=m;t.classList.add('show');setTimeout(()=>t.classList.remove('show'),d);}

/* CARD DATABASE — 24 kartu real dari gameplay_pvp.php */
const CARD_DB=[
  /* COMMON */
  {id:'drain_life',   rarity:'common',icon:'&#129656;',name:'Drain Life 1',   desc:'Setiap menang game: +10 HP. Aktif 3 game. Jika tidak ada kemenangan dalam 3 game, efek tetap habis.',tip:'Terbaik saat kamu dominan di awal &mdash; snowball HP advantage.'},
  {id:'gambling1',    rarity:'common',icon:'&#127922;',name:'The Gambling I',  desc:'Menang: +10 damage diberikan. Kalah: +10 damage diterima. Aktif 1 game.',tip:'Risiko rendah. Entry-level gambling tanpa kehilangan besar.'},
  {id:'safe_play1',   rarity:'common',icon:'&#128737;',name:'Safe Play I',     desc:'Kalah = 0 damage diterima. Menang = hanya 50% damage normal. Berlaku 1 game.',tip:'Pakai saat HP rendah untuk bertahan sambil mencuri momen balik.'},
  {id:'barrier',      rarity:'common',icon:'&#128302;',name:'Barrier 1',       desc:'Kalah = damage dikurangi 50%. Bertahan sampai 1 kekalahan lalu hancur. 1&times; per ronde.',tip:'Pasang di awal ronde sebagai lapisan pertahanan pertama.'},
  {id:'critical_attack',rarity:'common',icon:'&#9889;',name:'Critical Attack', desc:'Saat menang: 50% chance +30 damage ekstra. Aktif 2 game atau sampai chance berhasil, mana lebih dulu.',tip:'High roll potential. Pasang saat yakin menang 1&ndash;2 game berikutnya.'},
  {id:'tie_breaker',  rarity:'common',icon:'&#9878;&#65039;',name:'Tie Breaker',desc:'Mengubah hasil seri menjadi kemenangan bagimu selama ronde ini aktif.',tip:'Wajib di situasi yang sering seri &mdash; ubah draw jadi poin.'},
  {id:'shield1',      rarity:'common',icon:'&#128737;&#65039;',name:'Shield I', desc:'+30 Shield HP yang menyerap damage musuh sebelum HP asli berkurang. Aktif sampai Shield habis.',tip:'Buffer 30 HP gratis &mdash; pakai untuk bertahan dari serangan deras.'},
  {id:'god_attack1',  rarity:'common',icon:'&#9889;',name:'God Attack I',      desc:'Menang pertama kali: damage 2&times; (5% chance 3&times; LUCKY!). Berakhir setelah 1 kemenangan. 1&times; per ronde.',tip:'Low roll rate tapi cukup untuk membalikkan keadaan dengan double damage.'},
  /* RARE */
  {id:'gambling2',    rarity:'rare',  icon:'&#127183;',name:'The Gambling II', desc:'Menang: +30 damage. Kalah: +30 damage diterima. 1 game per ronde.',tip:'Risiko sedang. Pakai saat kondisi seimbang &mdash; reward 30 dmg sangat signifikan.'},
  {id:'block_one',    rarity:'rare',  icon:'&#128683;',name:'Block One',        desc:'Lawan hanya bisa menggunakan 1 kartu pada ronde ini.',tip:'Weapon disruption terkuat &mdash; kupas lapisan pertahanan combo lawan.'},
  {id:'steal_hp',     rarity:'rare',  icon:'&#128137;',name:'Steal HP 1',      desc:'-20 HP lawan secara langsung &rarr; +20 Shield HP untuk kamu.',tip:'Double efek: melemahkan lawan sekaligus memperkuat dirimu.'},
  {id:'repeat',       rarity:'rare',  icon:'&#128257;',name:'Repeat',           desc:'Jika kamu kalah ronde ini, ronde diulang dari awal tanpa perubahan HP.',tip:'Kartu insurance terbaik saat situasi ronde tampak tidak menguntungkan.'},
  {id:'safe_play2',   rarity:'rare',  icon:'&#128737;',name:'Safe Play II',     desc:'Kalah = 0 damage. Menang = damage normal penuh (20). Berlaku 1 game.',tip:'Upgrade Safe Play I &mdash; menang tetap full damage, kalah tetap aman.'},
  {id:'god_attack2',  rarity:'rare',  icon:'&#9876;&#65039;',name:'God Attack II',desc:'Menang pertama kali: damage 2&times; (20% chance 3&times;). Berakhir setelah 1 kemenangan. 1&times; per ronde.',tip:'20% chance triple damage &mdash; jauh lebih baik dari God Attack I.'},
  {id:'shield2',      rarity:'rare',  icon:'&#128311;',name:'Shield II',        desc:'+60 Shield HP yang menyerap damage sebelum HP asli berkurang. Aktif sampai Shield habis.',tip:'60 HP buffer &mdash; setara 3 serangan normal. Waktu berharga.'},
  /* EPIC */
  {id:'gambling3',    rarity:'epic',  icon:'&#127920;',name:'The Gambling III', desc:'Menang: +50 damage. Kalah: +20 damage diterima. 1 game per ronde.',tip:'Asimetris terbaik &mdash; reward menang (+50) jauh lebih besar dari risiko kalah (+20).'},
  {id:'reverse_result',rarity:'epic', icon:'&#128260;',name:'Reverse Result',   desc:'Kalah atau seri &rarr; jadi menang. Bisa digunakan 3 kali &mdash; berkurang setiap terpicu.',tip:'Counter keras untuk God Attack lawan. Balik 3 kekalahan jadi kemenangan.'},
  {id:'god_attack3',  rarity:'epic',  icon:'&#128128;',name:'God Attack III',   desc:'Menang pertama kali: damage 2&times; (50% chance 3&times;!). Berakhir setelah 1 kemenangan. 1&times; per ronde.',tip:'50% chance triple damage &mdash; tertinggi di kelas God Attack. Very high-impact.'},
  {id:'drain_life_2', rarity:'epic',  icon:'&#129656;',name:'Drain Life 2',     desc:'Setiap menang: kamu +25 HP dan lawan -10 HP ekstra di luar damage normal. Aktif 3 game.',tip:'Sustain + pressure sekaligus. Gabung dengan kartu serangan untuk snowball cepat.'},
  {id:'steal_hp2',    rarity:'epic',  icon:'&#129659;',name:'Steal HP 2',       desc:'-50 HP lawan secara langsung &rarr; +50 Shield HP untuk kamu.',tip:'Swing HP terbesar di permainan &mdash; bisa membalik keadaan hanya dengan 1 kartu.'},
  {id:'double_damage',rarity:'epic',  icon:'&#128302;',name:'Barrier 2',        desc:'Kalah = damage dikurangi menjadi 25%. Bertahan sampai 1 kekalahan lalu hancur. 1&times; per ronde.',tip:'Hanya 25% damage masuk saat kalah. Hampir kebal 1 kekalahan.'},
  /* LEGEND */
  {id:'full_damage',  rarity:'legend',icon:'&#128165;',name:'Full Damage',      desc:'Menang pertama kali: damage 5&times; normal (20&times;5 = 100 &mdash; one-hit kill!). Berakhir setelah 1 kemenangan.',tip:'Kartu paling mematikan &mdash; 1 menang = match over. Gabung God Attack untuk efek max.'},
  {id:'shield3',      rarity:'legend',icon:'&#11088;', name:'Shield III',       desc:'+100 Shield HP besar yang menyerap seluruh damage sebelum HP berkurang. Aktif sampai Shield habis.',tip:'Tembok tak tertembus &mdash; 5 serangan normal tidak menyentuh HP aslimu.'},
  {id:'absolute_reset',rarity:'legend',icon:'&#9854;&#65039;',name:'Absolute Reset',desc:'Mereset seluruh match ke Ronde 1 Game 1. Semua HP, skor, dan efek kartu kembali ke awal.',tip:'Kartu last resort &mdash; gunakan saat hampir kalah untuk reset pertandingan sepenuhnya.'},
];

/* COMBOS — terkuat ke terlemah */
/* COMBOS — 9 sinergi terkuat */
const COMBOS=[
  {rank:1,a:{id:'full_damage',    icon:'&#128165;',name:'Full Damage',   rarity:'legend'},b:{id:'god_attack3',   icon:'&#128128;',name:'God Attack III',  rarity:'epic'},  arrow:'&#9889;',        type:'SYNERGY',label:'One-Hit Nuke',      desc:'Full Damage 100 dmg saat pertama menang. God Attack III 50% chance 3&times;. Potensi 300 dmg dalam 1 game.'},
  {rank:2,a:{id:'gambling3',      icon:'&#127920;',name:'Gambling III',   rarity:'epic'}, b:{id:'god_attack2',   icon:'&#9876;&#65039;',name:'God Attack II',rarity:'rare'}, arrow:'&#128165;',      type:'SYNERGY',label:'Damage Amplifier',  desc:'Gambling III +50 dmg. God Attack II double. Saat keduanya aktif &amp; menang: (20+50)&times;2 = 140 dmg satu game.'},
  {rank:3,a:{id:'drain_life_2',   icon:'&#129656;',name:'Drain Life 2',   rarity:'epic'}, b:{id:'double_damage', icon:'&#128302;',name:'Barrier 2',        rarity:'epic'},  arrow:'&#128737;',      type:'SYNERGY',label:'Sustain Tank',      desc:'Drain Life 2 +25 HP tiap menang. Barrier 2 hanya 25% damage saat kalah. Daya tahan tertinggi di game.'},
  {rank:4,a:{id:'shield3',        icon:'&#11088;', name:'Shield III',     rarity:'legend'},b:{id:'reverse_result',icon:'&#128260;',name:'Reverse Result',  rarity:'epic'},  arrow:'&#9854;&#65039;',type:'SYNERGY',label:'Untouchable',      desc:'Shield III serap 100 dmg. Reverse Result balik 3 kekalahan jadi kemenangan. Hampir mustahil dikalahkan.'},
  {rank:5,a:{id:'steal_hp2',      icon:'&#129659;',name:'Steal HP 2',     rarity:'epic'}, b:{id:'barrier',       icon:'&#128302;',name:'Barrier 1',        rarity:'common'},arrow:'&#128137;',      type:'SYNERGY',label:'HP Swing Defense',  desc:'Steal HP 2: lawan -50 HP, kamu +50 Shield. Barrier 1 lindungi dari 50% damage. Total swing 50 HP sebelum game.'},
  {rank:6,a:{id:'critical_attack',icon:'&#9889;',  name:'Critical Attack',rarity:'common'},b:{id:'drain_life',   icon:'&#129656;',name:'Drain Life 1',     rarity:'common'},arrow:'&#129656;',      type:'SYNERGY',label:'Snowball',          desc:'Critical Attack +30 dmg saat berhasil. Drain Life 1 +10 HP tiap menang. Keduanya aktif 3 game bersamaan.'},
  {rank:7,a:{id:'gambling2',      icon:'&#127183;',name:'Gambling II',    rarity:'rare'}, b:{id:'god_attack1',   icon:'&#9889;',  name:'God Attack I',     rarity:'common'},arrow:'&#128165;',      type:'SYNERGY',label:'Mid-Risk Burst',    desc:'Gambling II +30 dmg saat menang. God Attack I double damage pertama kali. Combo entry-level yang tetap mengancam.'},
  {rank:8,a:{id:'shield2',        icon:'&#128311;',name:'Shield II',      rarity:'rare'}, b:{id:'steal_hp',      icon:'&#128137;',name:'Steal HP 1',       rarity:'rare'},  arrow:'&#128137;',      type:'SYNERGY',label:'Defense &amp; Drain',desc:'Shield II +60 HP buffer. Steal HP 1 potong -20 HP lawan &rarr; +20 Shield. Bertahan sambil terus menggerus HP lawan.'},
  {rank:9,a:{id:'tie_breaker',    icon:'&#9878;&#65039;',name:'Tie Breaker',rarity:'common'},b:{id:'safe_play2', icon:'&#128737;',name:'Safe Play II',     rarity:'rare'},  arrow:'&#9878;&#65039;',type:'SYNERGY',label:'Draw Denial',      desc:'Tie Breaker ubah seri jadi kemenangan. Safe Play II saat kalah = 0 damage. Combo super defensif yang tidak pernah rugi.'},
];

/* COUNTERS — 9 counter matchup terkuat */
const COUNTERS=[
  {rank:1,a:{id:'double_damage',  icon:'&#128302;',name:'Barrier 2',      rarity:'epic'},  b:{id:'full_damage',   icon:'&#128165;',name:'Full Damage',   rarity:'legend'},arrow:'&#128737;&#65039;',type:'COUNTER',label:'100 &rarr; 25 dmg',    desc:'Full Damage ancam 100 dmg. Barrier 2 potong jadi 25% &mdash; hanya 25 dmg masuk. Efek Full Damage terbuang sia-sia.'},
  {rank:2,a:{id:'reverse_result', icon:'&#128260;',name:'Reverse Result', rarity:'epic'},  b:{id:'god_attack3',   icon:'&#128128;',name:'God Attack III',rarity:'epic'},  arrow:'&#128260;',        type:'COUNTER',label:'Balik Serangan',    desc:'God Attack III butuh kamu kalah dulu. Reverse Result ubah kekalahanmu jadi menang &mdash; God Attack tidak bisa terpicu.'},
  {rank:3,a:{id:'block_one',      icon:'&#128683;',name:'Block One',      rarity:'rare'},  b:{id:'gambling3',     icon:'&#127920;',name:'Gambling III',  rarity:'epic'},  arrow:'&#128683;',        type:'COUNTER',label:'Disable Combo',     desc:'Block One batasi lawan hanya 1 kartu. Gambling III butuh dikombinasikan &mdash; tanpa slot kedua, kombo lawan hancur.'},
  {rank:4,a:{id:'repeat',         icon:'&#128257;',name:'Repeat',         rarity:'rare'},  b:{id:'god_attack2',   icon:'&#9876;&#65039;',name:'God Attack II',rarity:'rare'},arrow:'&#128257;',      type:'COUNTER',label:'Nullify God Attack', desc:'God Attack II serang saat menang ronde. Jika kamu kalah, Repeat ulangi ronde &mdash; HP tidak berubah, God Attack gagal.'},
  {rank:5,a:{id:'steal_hp2',      icon:'&#129659;',name:'Steal HP 2',     rarity:'epic'},  b:{id:'shield3',       icon:'&#11088;', name:'Shield III',    rarity:'legend'},arrow:'&#128137;',        type:'COUNTER',label:'Drain Past Shield',  desc:'Shield III lindungi lawan 100 HP. Steal HP 2 potong 50 HP dari HP asli &mdash; melewati shield dan mempercepat habis.'},
  {rank:6,a:{id:'absolute_reset', icon:'&#9854;&#65039;',name:'Absolute Reset',rarity:'legend'},b:{id:'drain_life_2',icon:'&#129656;',name:'Drain Life 2',rarity:'epic'},arrow:'&#9854;&#65039;',  type:'COUNTER',label:'Hard Reset',         desc:'Drain Life 2 bangun HP advantage 3 game. Absolute Reset hapus semua progress &mdash; balik ke 100 HP vs 100 HP.'},
  {rank:7,a:{id:'barrier',        icon:'&#128302;',name:'Barrier 1',      rarity:'common'},b:{id:'gambling2',     icon:'&#127183;',name:'Gambling II',   rarity:'rare'},  arrow:'&#128302;',        type:'COUNTER',label:'Soften the Gamble',  desc:'Gambling II tambah +30 dmg risk. Barrier 1 potong damage yang masuk jadi 50% &mdash; mengcounter setengah dari ancaman Gambling II.'},
  {rank:8,a:{id:'safe_play1',     icon:'&#128737;',name:'Safe Play I',    rarity:'common'},b:{id:'god_attack1',   icon:'&#9889;',  name:'God Attack I',  rarity:'common'},arrow:'&#128737;',        type:'COUNTER',label:'Zero-Cost Defense',  desc:'God Attack I double damage saat menang pertama. Safe Play I: kalah = 0 damage, menang = 50% dmg. Buang momentum God Attack lawan.'},
  {rank:9,a:{id:'steal_hp',       icon:'&#128137;',name:'Steal HP 1',     rarity:'rare'},  b:{id:'shield2',       icon:'&#128311;',name:'Shield II',      rarity:'rare'},  arrow:'&#128137;',        type:'COUNTER',label:'Shield Erosion',    desc:'Shield II berikan lawan 60 HP buffer. Steal HP 1 langsung potong -20 HP asli lawan &mdash; bypass shield dan percepat shield habis.'},
];

const PLAYER_ID=<?php echo json_encode($player_id)?>;

function buildCard(card,count,maxCount){
  const pct=maxCount>0?Math.round(count/maxCount*100):0;
  return `<div class="sc sc-${card.rarity}">
    <div class="shine"></div><div class="sbc sbc-tl"></div><div class="sbc sbc-br"></div>
    <span class="sc-tag tag-${card.rarity}">${card.rarity.toUpperCase()}</span>
    <div class="sc-icon">${card.icon}</div>
    <div class="sc-name">${card.name}</div>
    <div class="sc-div"></div>
    <div class="sc-desc">${card.desc}</div>
    <div class="sc-tip">&#128161; ${card.tip}</div>
    <div class="sc-stat">
      <div class="sc-stat-lbl">Total Usage (AI + PvP)</div>
      <div class="sc-bar-wrap"><div class="sc-bar" data-pct="${pct}"></div></div>
      <div class="sc-count">${count>0?count.toLocaleString('id-ID'):'&mdash;'}</div>
      <div class="sc-count-sub">kali dipakai &middot; ${pct}%</div>
    </div>
  </div>`;}

function buildCC(data,isCombo){
  return `<div class="cc-card ${isCombo?'cc-combo':'cc-counter'}">
    <div class="shine"></div>
    <div class="cc-mini">
      <div class="cc-mini-icon">${data.a.icon}</div>
      <div class="cc-mini-name" style="color:var(--c-${data.a.rarity})">${data.a.name}</div>
      <span class="cc-mini-badge badge-${data.a.rarity}">${data.a.rarity.toUpperCase()}</span>
    </div>
    <div class="cc-mid">
      <div class="cc-mid-type">${data.type}</div>
      <div class="cc-mid-arrow">${data.arrow}</div>
      <div class="cc-mid-label">#${data.rank} &middot; ${data.label}</div>
      <div class="cc-mid-desc">${data.desc}</div>
    </div>
    <div class="cc-mini">
      <div class="cc-mini-icon">${data.b.icon}</div>
      <div class="cc-mini-name" style="color:var(--c-${data.b.rarity})">${data.b.name}</div>
      <span class="cc-mini-badge badge-${data.b.rarity}">${data.b.rarity.toUpperCase()}</span>
    </div>
  </div>`;}

/* load semua stats koleksi dari collection_api.php (gabungan AI + PvP) */
async function loadCollectionStats(){
  try{
    const res=await fetch('../Api/collection_api.php');
    if(res.ok){
      const j=await res.json();
      if(j.success) return j;
    }
  }catch(e){ console.warn('[collection] fetch error:', e); }
  // Fallback kosong
  return {success:false, card_usage:{}, total_rock:0, total_paper:0, total_scissors:0};
}

async function init(){
  setTimeout(()=>document.getElementById('pageTop').classList.add('show'),120);

  // Fetch semua data sekaligus dari server (AI + PvP)
  const stats = await loadCollectionStats();
  const usage = stats.card_usage || {};

  // Hitung max untuk bar relatif
  const allV=Object.values(usage);
  const maxC=allV.length?Math.max(...allV):1;

  // Render kartu per rarity
  const byR={common:[],rare:[],epic:[],legend:[]};
  CARD_DB.forEach(c=>byR[c.rarity].push(c));
  ['common','rare','epic','legend'].forEach(r=>{
    document.getElementById(`grid-${r}`).innerHTML=byR[r].map(c=>buildCard(c,usage[c.id]||0,maxC)).join('');
  });

  document.getElementById('grid-combo').innerHTML=COMBOS.map(c=>buildCC(c,true)).join('');
  document.getElementById('grid-counter').innerHTML=COUNTERS.map(c=>buildCC(c,false)).join('');

  // Animate cards
  const all=document.querySelectorAll('.sc,.cc-card,.ccard');
  all.forEach((el,i)=>setTimeout(()=>el.classList.add('show'),80+i*35));
  requestAnimationFrame(()=>requestAnimationFrame(()=>{
    document.querySelectorAll('.sc-bar').forEach(b=>b.style.width=(b.dataset.pct||0)+'%');
  }));

  // Render senjata (Batu/Kertas/Gunting) — data gabungan AI+PvP dari DB
  const rock     = stats.total_rock     || 0;
  const paper    = stats.total_paper    || 0;
  const scissors = stats.total_scissors || 0;
  const total    = rock + paper + scissors || 1;
  const wCards={
    rock:    {cnt:'cntRock',    pct:'pctRock',    bar:'barRock'},
    paper:   {cnt:'cntPaper',   pct:'pctPaper',   bar:'barPaper'},
    scissors:{cnt:'cntScissors',pct:'pctScissors',bar:'barScissors'},
  };
  const wCounts={rock, paper, scissors};
  for(const[key,ids]of Object.entries(wCards)){
    const count=wCounts[key]||0;
    const pct=Math.round(count/total*100);
    document.getElementById(ids.cnt).textContent=count>0?count.toLocaleString('id-ID'):'—';
    document.getElementById(ids.pct).textContent=pct+'%';
    requestAnimationFrame(()=>requestAnimationFrame(()=>{
      document.getElementById(ids.bar).style.width=pct+'%';
    }));
  }
}
init();

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