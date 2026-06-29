<?php
// ══════════════════════════════════════════════
//  LANDING PAGE — Gunting Batu Kertas
//  Login & Register server-side dengan database
// ══════════════════════════════════════════════
session_start();
require_once __DIR__ . '/../Backend/database.php';

if (isset($_SESSION['player_id'])) {
    header('Location: main_menu.php');
    exit;
}

$error_msg    = '';
$success_msg  = '';
$active_modal = 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $input_id = trim($_POST['inputId'] ?? '');
    $input_pw = $_POST['inputPassword'] ?? '';

    if ($input_id === '' || $input_pw === '') {
        $error_msg    = '⚠ ID Pemain dan Password tidak boleh kosong!';
        $active_modal = 'login';
    } else {
        $player = getPlayerByUsername($input_id);
        if ($player && password_verify($input_pw, $player['password'])) {
            $_SESSION['player_id']   = $player['id'];
            $_SESSION['player_name'] = $player['username'];
            // Update last_seen timestamp
            $db_ls = getDB();
            $stmt_ls = $db_ls->prepare("UPDATE players SET last_seen = NOW() WHERE id = ?");
            $stmt_ls->execute([$player['id']]);
            header('Location: main_menu.php');
            exit;
        } else {
            $error_msg    = '⚠ ID atau Password salah. Coba lagi!';
            $active_modal = 'login';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register') {
    $reg_username  = trim($_POST['regUsername'] ?? '');
    $reg_email     = trim($_POST['regEmail']    ?? '');
    $reg_pw        = $_POST['regPassword'] ?? '';
    $reg_pw_conf   = $_POST['regPasswordConfirm'] ?? '';
    $active_modal  = 'register';

    if ($reg_username === '' || $reg_pw === '' || $reg_email === '') {
        $error_msg = '⚠ Username, Email, dan Password tidak boleh kosong!';
    } elseif (strlen($reg_username) < 3 || strlen($reg_username) > 20) {
        $error_msg = '⚠ Username harus antara 3–20 karakter.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $reg_username)) {
        $error_msg = '⚠ Username hanya boleh huruf, angka, dan underscore.';
    } elseif (!filter_var($reg_email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = '⚠ Format email tidak valid!';
    } elseif (strlen($reg_email) > 100) {
        $error_msg = '⚠ Email terlalu panjang (maks. 100 karakter).';
    } elseif (strlen($reg_pw) < 6) {
        $error_msg = '⚠ Password minimal 6 karakter.';
    } elseif ($reg_pw !== $reg_pw_conf) {
        $error_msg = '⚠ Konfirmasi password tidak cocok!';
    } elseif (getPlayerByUsername($reg_username) !== null) {
        $error_msg = '⚠ Username sudah digunakan. Pilih yang lain!';
    } else {
        $db_check = getDB();
        $emailChk = $db_check->prepare("SELECT 1 FROM players WHERE email = ? LIMIT 1");
        $emailChk->execute([$reg_email]);
        if ($emailChk->fetch()) {
            $error_msg = '⚠ Email sudah digunakan. Gunakan email lain!';
        } else {
            $db      = getDB();
            $new_id  = 'p_' . bin2hex(random_bytes(8));
            $pw_hash = password_hash($reg_pw, PASSWORD_BCRYPT);
            $stmt    = $db->prepare("
                INSERT INTO players
                    (id, username, password, email, rating, wins, losses, draws,
                     ai_wins, ai_losses, ai_draws,
                     total_rock, total_paper, total_scissors,
                     current_win_streak, max_win_streak,
                     created_at, updated_at, last_seen)
                VALUES
                    (?, ?, ?, ?, 1000, 0, 0, 0,
                     0, 0, 0,
                     0, 0, 0,
                     0, 0,
                     NOW(), NOW(), NOW())
            ");
            $stmt->execute([$new_id, $reg_username, $pw_hash, $reg_email]);
            $success_msg  = $reg_username;
            $active_modal = 'success';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gunting Batu Kertas – Battle Arena</title>
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Bebas+Neue&family=Russo+One&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --rock:     #ff4d4d;
  --paper:    #4da6ff;
  --scissors: #7dff4d;
  --glow-rock:     rgba(255,77,77,0.6);
  --glow-paper:    rgba(77,166,255,0.6);
  --glow-scissors: rgba(125,255,77,0.6);
  --dark:  #05060d;
  --mid:   #0d0f1e;
  --card:  rgba(255,255,255,0.03);
  --text:  #eef0ff;
  --muted: rgba(238,240,255,0.4);
  --border: rgba(238,240,255,0.08);
}

html, body {
  width: 100%; height: 100%;
  background: var(--dark);
  overflow: hidden;
  font-family: 'Rajdhani', sans-serif;
}

/* ══ CANVAS & BG ══ */
canvas#bg { position: fixed; inset: 0; z-index: 0; }

/* ══ NOISE OVERLAY ══ */
.noise {
  position: fixed; inset: 0; z-index: 1; pointer-events: none;
  opacity: 0.035;
  background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
  background-size: 200px 200px;
}

/* ══ ENERGY LINES ══ */
.energy-lines {
  position: fixed; inset: 0; z-index: 2; pointer-events: none; overflow: hidden;
}
.eline {
  position: absolute;
  width: 1px;
  background: linear-gradient(to bottom, transparent, rgba(77,166,255,0.5), transparent);
  animation: eline-fall linear infinite;
}
@keyframes eline-fall {
  from { transform: translateY(-100vh); opacity: 0; }
  10%  { opacity: 1; }
  90%  { opacity: 1; }
  to   { transform: translateY(100vh); opacity: 0; }
}

/* ══ SCAN LINE ══ */
.scanline {
  position: fixed; inset: 0; z-index: 3; pointer-events: none;
  background: repeating-linear-gradient(
    to bottom,
    transparent 0px,
    transparent 3px,
    rgba(0,0,0,0.08) 3px,
    rgba(0,0,0,0.08) 4px
  );
}

/* ══ CORNER DECORATIONS ══ */
.corner {
  position: fixed; z-index: 5; width: 80px; height: 80px; pointer-events: none;
}
.corner::before, .corner::after {
  content: ''; position: absolute; background: rgba(77,166,255,0.5);
}
.corner::before { width: 2px; height: 40px; }
.corner::after  { width: 40px; height: 2px; }
.corner-tl { top: 24px; left: 24px; }
.corner-tr { top: 24px; right: 24px; transform: scaleX(-1); }
.corner-bl { bottom: 24px; left: 24px; transform: scaleY(-1); }
.corner-br { bottom: 24px; right: 24px; transform: scale(-1); }
.corner-tl::before, .corner-tr::before { top: 0; left: 0; }
.corner-tl::after,  .corner-tr::after  { top: 0; left: 0; }
.corner-bl::before, .corner-br::before { bottom: 0; left: 0; top: 0; }
.corner-bl::after,  .corner-br::after  { bottom: 0; left: 0; top: 0; }

/* ══ FLOATING PARTICLES ══ */
.particles { position: fixed; inset: 0; z-index: 3; pointer-events: none; overflow: hidden; }
.particle {
  position: absolute; border-radius: 50%;
  animation: particle-float linear infinite;
}
@keyframes particle-float {
  from { transform: translateY(110vh) rotate(0deg); opacity: 0; }
  10%  { opacity: 1; }
  90%  { opacity: 1; }
  to   { transform: translateY(-10vh) rotate(360deg); opacity: 0; }
}

/* ══ MAIN STAGE ══ */
.stage {
  position: fixed; inset: 0; z-index: 10;
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  gap: clamp(1rem, 2.5vh, 1.8rem);
  text-align: center; padding: 2rem;
}

/* ══ TOP TAG ══ */
.arena-tag {
  display: flex; align-items: center; gap: 12px;
  font-family: 'Rajdhani', sans-serif; font-size: 11px; font-weight: 600;
  letter-spacing: 0.5em; text-transform: uppercase; color: var(--paper);
}
.arena-tag-line { width: 40px; height: 1px; background: var(--paper); opacity: 0.5; }

/* ══ TITLE ══ */
.title-wrap { position: relative; }
.title-eyebrow {
  font-size: clamp(10px, 1.5vw, 13px); font-weight: 600;
  letter-spacing: 0.5em; text-transform: uppercase;
  color: var(--muted); margin-bottom: 8px;
}

h1 {
  font-family: 'Bebas Neue', sans-serif;
  font-size: clamp(3.5rem, 11vw, 8rem);
  line-height: 0.88;
  color: var(--text);
  letter-spacing: 0.04em;
  position: relative;
}
.word-batu     { color: var(--rock);     text-shadow: 0 0 40px var(--glow-rock),     0 0 80px rgba(255,77,77,0.2); }
.word-gunting  { color: var(--scissors); text-shadow: 0 0 40px var(--glow-scissors), 0 0 80px rgba(125,255,77,0.2); }
.word-kertas   { color: var(--paper);    text-shadow: 0 0 40px var(--glow-paper),    0 0 80px rgba(77,166,255,0.2); }
.word-sep      { color: rgba(238,240,255,0.2); font-size: 0.5em; vertical-align: middle; }

/* Glitch effect */
h1::before, h1::after {
  content: attr(data-text);
  position: absolute; inset: 0;
  font-family: 'Bebas Neue', sans-serif;
  font-size: inherit; letter-spacing: inherit; line-height: inherit;
  pointer-events: none;
}
h1::before {
  color: var(--rock); clip-path: polygon(0 20%, 100% 20%, 100% 40%, 0 40%);
  animation: glitch-1 4s infinite steps(1);
  opacity: 0.6;
}
h1::after {
  color: var(--paper); clip-path: polygon(0 60%, 100% 60%, 100% 75%, 0 75%);
  animation: glitch-2 4s infinite steps(1);
  opacity: 0.6;
}
@keyframes glitch-1 {
  0%,94%  { transform: none; opacity: 0; }
  95%     { transform: translateX(-3px); opacity: 0.6; }
  96%     { transform: translateX(3px) skewX(5deg); opacity: 0.6; }
  97%     { transform: none; opacity: 0; }
}
@keyframes glitch-2 {
  0%,96%  { transform: none; opacity: 0; }
  97%     { transform: translateX(3px); opacity: 0.6; }
  98%     { transform: translateX(-3px) skewX(-3deg); opacity: 0.6; }
  99%     { transform: none; opacity: 0; }
}

/* ══ WEAPON CARDS ══ */
.weapons-row {
  display: flex; gap: clamp(12px, 2.5vw, 24px);
  margin: 0.2rem 0;
}

.weapon {
  position: relative;
  width: clamp(90px, 13vw, 150px);
  aspect-ratio: 1;
  border-radius: 16px;
  cursor: pointer;
  overflow: hidden;
  border: 1px solid var(--border);
  background: var(--card);
  backdrop-filter: blur(10px);
  transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.3s ease;
  display: flex; flex-direction: column;
  align-items: center; justify-content: center; gap: 8px;
}

.weapon::before {
  content: '';
  position: absolute; inset: 0;
  opacity: 0; transition: opacity 0.3s;
}

.weapon::after {
  content: '';
  position: absolute; top: 0; left: -100%;
  width: 60%; height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.08), transparent);
  transition: left 0.5s ease;
  transform: skewX(-15deg);
}

.weapon:hover { transform: translateY(-10px) scale(1.05); }
.weapon:hover::before { opacity: 1; }
.weapon:hover::after { left: 150%; }

/* weapon-specific */
.weapon-rock   { border-color: rgba(255,77,77,0.35); }
.weapon-rock::before   { background: radial-gradient(ellipse at center, rgba(255,77,77,0.15), transparent); }
.weapon-rock:hover     { box-shadow: 0 20px 60px rgba(255,77,77,0.4), 0 0 0 1px rgba(255,77,77,0.4); }

.weapon-scissors { border-color: rgba(125,255,77,0.35); }
.weapon-scissors::before { background: radial-gradient(ellipse at center, rgba(125,255,77,0.15), transparent); }
.weapon-scissors:hover { box-shadow: 0 20px 60px rgba(125,255,77,0.4), 0 0 0 1px rgba(125,255,77,0.4); }

.weapon-paper  { border-color: rgba(77,166,255,0.35); }
.weapon-paper::before  { background: radial-gradient(ellipse at center, rgba(77,166,255,0.15), transparent); }
.weapon-paper:hover    { box-shadow: 0 20px 60px rgba(77,166,255,0.4), 0 0 0 1px rgba(77,166,255,0.4); }

.weapon-icon {
  font-size: clamp(32px, 5.5vw, 54px);
  position: relative; z-index: 1;
  filter: drop-shadow(0 4px 8px rgba(0,0,0,0.4));
  transition: transform 0.3s ease;
}
.weapon:hover .weapon-icon { transform: scale(1.15) rotate(-5deg); }

.weapon-icon img {
  width: clamp(44px, 7vw, 80px);
  height: clamp(44px, 7vw, 80px);
  object-fit: contain;
}

.weapon-name {
  font-size: clamp(9px, 1.2vw, 12px); font-weight: 700;
  letter-spacing: 0.2em; text-transform: uppercase;
  color: var(--text); opacity: 0.6;
  position: relative; z-index: 1;
}

/* corner accent on cards */
.weapon .w-corner {
  position: absolute; width: 16px; height: 16px; opacity: 0.5;
}
.weapon .w-corner::before, .weapon .w-corner::after { content: ''; position: absolute; background: currentColor; }
.weapon .w-corner::before { width: 1.5px; height: 12px; }
.weapon .w-corner::after  { width: 12px; height: 1.5px; }
.w-corner-tl { top: 8px; left: 8px; }
.w-corner-br { bottom: 8px; right: 8px; transform: scale(-1); }
.weapon-rock   .w-corner { color: var(--rock); }
.weapon-scissors .w-corner { color: var(--scissors); }
.weapon-paper  .w-corner { color: var(--paper); }

/* ══ DIVIDER ══ */
.divider {
  display: flex; align-items: center; gap: 16px;
  width: min(360px, 85vw);
}
.div-line { flex: 1; height: 1px; background: linear-gradient(to right, transparent, var(--border), transparent); }
.div-text {
  font-family: 'Rajdhani', sans-serif; font-size: 11px; font-weight: 600;
  letter-spacing: 0.4em; text-transform: uppercase; color: var(--muted);
}

/* ══ CTA BUTTON ══ */
.cta-wrap { position: relative; }
.cta {
  position: relative;
  font-family: 'Russo One', sans-serif;
  font-size: clamp(0.8rem, 1.6vw, 1rem);
  letter-spacing: 0.2em; text-transform: uppercase;
  color: var(--dark);
  background: var(--text);
  padding: 16px 52px;
  border-radius: 4px;
  border: none; cursor: pointer;
  overflow: hidden;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  box-shadow: 0 0 40px rgba(238,240,255,0.15), inset 0 1px 0 rgba(255,255,255,0.5);
  clip-path: polygon(12px 0%, 100% 0%, calc(100% - 12px) 100%, 0% 100%);
}
.cta::before {
  content: '';
  position: absolute; inset: 0;
  background: linear-gradient(135deg, var(--paper) 0%, var(--scissors) 100%);
  opacity: 0; transition: opacity 0.3s;
}
.cta:hover { transform: translateY(-3px) scale(1.03); box-shadow: 0 12px 40px rgba(238,240,255,0.25); }
.cta:hover::before { opacity: 1; }
.cta:active { transform: translateY(0); }
.cta span { position: relative; z-index: 1; }

/* pulse ring around CTA */
.cta-pulse {
  position: absolute; inset: -8px;
  border: 1px solid rgba(238,240,255,0.2);
  border-radius: 4px;
  animation: cta-pulse 2.5s ease-out infinite;
  pointer-events: none;
  clip-path: polygon(12px 0%, 100% 0%, calc(100% - 12px) 100%, 0% 100%);
}
@keyframes cta-pulse {
  0%   { transform: scale(1); opacity: 0.8; }
  100% { transform: scale(1.3); opacity: 0; }
}

/* ══ HUD STATS ROW ══ */
.hud-row {
  display: flex; gap: clamp(16px, 4vw, 40px);
  margin-top: -0.4rem;
}
.hud-stat {
  text-align: center;
  font-size: 10px; letter-spacing: 0.3em; text-transform: uppercase; color: var(--muted);
}
.hud-val {
  display: block;
  font-family: 'Bebas Neue', sans-serif; font-size: clamp(18px, 3vw, 28px);
  color: var(--text); letter-spacing: 0.1em; line-height: 1.2;
}


/* ══════════════════════════════
   LOGIN TRANSITION
══════════════════════════════ */
#loginTransition {
  position: fixed; inset: 0; z-index: 9999;
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  pointer-events: none; opacity: 0;
  background: radial-gradient(ellipse at center, rgba(77,166,255,0.08) 0%, var(--dark) 70%);
}
#loginTransition.active { pointer-events: all; }

.lt-ring-wrap {
  position: relative; width: 160px; height: 160px;
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 2rem;
}
.lt-ring {
  position: absolute; border-radius: 50%;
  border: 2px solid transparent;
  animation: ltSpin 1.2s linear infinite;
}
.lt-ring-1 { width: 160px; height: 160px; border-top-color: var(--paper); border-right-color: rgba(77,166,255,0.3); animation-duration: 1.2s; }
.lt-ring-2 { width: 120px; height: 120px; border-top-color: var(--scissors); border-left-color: rgba(125,255,77,0.3); animation-duration: 0.9s; animation-direction: reverse; }
.lt-ring-3 { width: 84px; height: 84px; border-top-color: var(--rock); border-bottom-color: rgba(255,77,77,0.3); animation-duration: 0.7s; }
.lt-icon { font-size: 2.2rem; animation: ltPulseIcon 1s ease-in-out infinite alternate; }
@keyframes ltSpin { to { transform: rotate(360deg); } }
@keyframes ltPulseIcon {
  from { transform: scale(0.9); filter: drop-shadow(0 0 8px rgba(77,166,255,0.4)); }
  to   { transform: scale(1.1); filter: drop-shadow(0 0 20px rgba(77,166,255,0.9)); }
}
.lt-text {
  font-family: 'Bebas Neue', sans-serif; font-size: 1.6rem;
  letter-spacing: 0.3em; text-transform: uppercase; color: var(--text);
  text-shadow: 0 0 30px rgba(77,166,255,0.6); margin-bottom: 0.5rem;
}
.lt-sub { font-size: 0.75rem; color: var(--muted); letter-spacing: 0.15em; animation: ltFadeText 1.5s ease-in-out infinite alternate; }
@keyframes ltFadeText { from { opacity: 0.4; } to { opacity: 1; } }
.lt-dots { display: flex; gap: 8px; margin-top: 1.5rem; }
.lt-dot { width: 8px; height: 8px; border-radius: 50%; animation: ltDotBounce 0.9s ease-in-out infinite; }
.lt-dot:nth-child(1) { animation-delay: 0s;    background: var(--rock); }
.lt-dot:nth-child(2) { animation-delay: 0.15s; background: var(--paper); }
.lt-dot:nth-child(3) { animation-delay: 0.3s;  background: var(--scissors); }
@keyframes ltDotBounce { 0%,80%,100%{transform:translateY(0);opacity:0.5} 40%{transform:translateY(-12px);opacity:1} }
@keyframes ltFadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes ltPageExit { 0%{opacity:1;transform:scale(1)} 100%{opacity:0;transform:scale(1.05)} }
body.login-exit { animation: ltPageExit 0.5s ease-in forwards; }


/* ══════════════════════════════
   MODAL
══════════════════════════════ */
.modal-overlay {
  position: fixed; inset: 0; z-index: 100;
  background: rgba(5,6,13,0.9);
  backdrop-filter: blur(16px);
  display: flex; align-items: center; justify-content: center;
  opacity: 0; pointer-events: none;
  transition: opacity 0.3s ease;
}
.modal-overlay.active { opacity: 1; pointer-events: all; }

.modal {
  position: relative;
  background: linear-gradient(160deg, rgba(15,17,32,0.99), rgba(8,9,20,1));
  border: 1px solid rgba(238,240,255,0.1);
  border-radius: 8px;
  padding: 2.5rem 2.8rem 2.8rem;
  width: min(440px, 93vw);
  max-height: 92vh;
  overflow-y: auto;
  box-shadow:
    0 0 0 1px rgba(238,240,255,0.04),
    0 40px 100px rgba(0,0,0,0.8),
    0 0 80px rgba(77,166,255,0.05);
  transform: translateY(40px) scale(0.95);
  transition: transform 0.4s cubic-bezier(0.34,1.56,0.64,1);
  clip-path: polygon(20px 0%, 100% 0%, calc(100% - 20px) 100%, 0% 100%);
}
.modal-overlay.active .modal { transform: translateY(0) scale(1); }

/* rainbow top border */
.modal::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0; height: 2px;
  background: linear-gradient(90deg, var(--rock), var(--paper), var(--scissors), var(--paper), var(--rock));
  background-size: 200% 100%;
  animation: rainbow-shift 4s linear infinite;
}
@keyframes rainbow-shift { from { background-position: 0% 0%; } to { background-position: 200% 0%; } }

/* corner accents on modal */
.modal-corner {
  position: absolute; width: 20px; height: 20px; z-index: 5;
}
.modal-corner::before, .modal-corner::after { content: ''; position: absolute; background: var(--paper); opacity: 0.5; }
.modal-corner::before { width: 1.5px; height: 14px; }
.modal-corner::after  { width: 14px; height: 1.5px; }
.modal-corner.tl { top: 10px; left: 10px; }
.modal-corner.tr { top: 10px; right: 10px; transform: scaleX(-1); }
.modal-corner.bl { bottom: 10px; left: 10px; transform: scaleY(-1); }
.modal-corner.br { bottom: 10px; right: 10px; transform: scale(-1); }

/* Tabs */
.modal-tabs {
  display: flex;
  background: rgba(238,240,255,0.03);
  border: 1px solid var(--border);
  border-radius: 4px;
  padding: 3px;
  margin-bottom: 2rem; gap: 3px;
}
.modal-tab {
  flex: 1; font-family: 'Rajdhani', sans-serif;
  font-size: 13px; font-weight: 700;
  letter-spacing: 0.2em; text-transform: uppercase;
  background: none; border: none;
  color: var(--muted); padding: 11px;
  border-radius: 3px; cursor: pointer;
  transition: all 0.2s;
}
.modal-tab.active {
  background: rgba(77,166,255,0.15);
  color: var(--text);
  border: 1px solid rgba(77,166,255,0.3);
}
.modal-tab:not(.active):hover {
  background: linear-gradient(135deg, #2874c2 0%, #1a9940 100%);
  color: #fff;
  border-color: transparent;
}

/* Modal header */
.modal-header { text-align: center; margin-bottom: 1.8rem; }
.modal-icon {
  width: 64px; height: 64px; border-radius: 8px;
  background: linear-gradient(135deg, rgba(77,166,255,0.2), rgba(125,255,77,0.1));
  border: 1px solid rgba(77,166,255,0.3);
  display: flex; align-items: center; justify-content: center; font-size: 28px;
  margin: 0 auto 1.2rem;
  box-shadow: 0 0 40px rgba(77,166,255,0.15), inset 0 1px 0 rgba(255,255,255,0.05);
  clip-path: polygon(8px 0%, 100% 0%, calc(100% - 8px) 100%, 0% 100%);
}
.modal-title {
  font-family: 'Bebas Neue', sans-serif; font-size: 1.6rem;
  color: var(--text); letter-spacing: 0.08em; margin-bottom: 0.4rem;
}
.modal-subtitle { font-size: 13px; color: var(--text); opacity: 0.75; letter-spacing: 0.05em; font-weight: 700; }

/* Fields */
.field-group { display: flex; flex-direction: column; gap: 1rem; margin-bottom: 1.6rem; }
.field { display: flex; flex-direction: column; gap: 6px; }
.field-label {
  font-size: 10px; font-weight: 700;
  letter-spacing: 0.3em; text-transform: uppercase; color: var(--muted);
}
.field-wrap { position: relative; display: flex; align-items: center; }
.field-icon {
  position: absolute; left: 14px; font-size: 15px;
  opacity: 1; pointer-events: none; z-index: 1;
  filter: drop-shadow(0 0 2px rgba(0,0,0,0.2));
}
.field-input {
  width: 100%;
  background: rgba(238,240,255,0.04);
  border: 1px solid var(--border);
  border-radius: 4px;
  padding: 13px 14px 13px 44px;
  font-family: 'Rajdhani', sans-serif; font-size: 15px; font-weight: 500;
  color: var(--text); outline: none;
  transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
  letter-spacing: 0.04em;
}
.field-input::placeholder { color: var(--text); opacity: 0.4; font-size: 13px; }
.field-input:focus {
  border-color: rgba(77,166,255,0.5);
  background: rgba(77,166,255,0.05);
  box-shadow: 0 0 0 3px rgba(77,166,255,0.08), inset 0 0 20px rgba(77,166,255,0.03);
}

.toggle-pw {
  position: absolute; right: 12px;
  background: none; border: none; cursor: pointer;
  color: var(--text); font-size: 16px; padding: 4px;
  opacity: 0.8; transition: opacity 0.2s, filter 0.2s; line-height: 1;
}
.toggle-pw:hover { opacity: 1; filter: drop-shadow(0 0 4px rgba(77,166,255,0.4)); }

/* Error / Success */
.error-msg {
  display: none; background: rgba(255,77,77,0.08);
  border: 1px solid rgba(255,77,77,0.25); border-radius: 4px;
  padding: 11px 14px; font-size: 13px; color: #ff8080;
  letter-spacing: 0.02em; margin-bottom: 1rem; text-align: center;
}
.error-msg.show { display: block; }

/* Submit button */
.btn-submit {
  width: 100%; font-family: 'Russo One', sans-serif;
  font-size: 13px; letter-spacing: 0.2em; text-transform: uppercase;
  color: var(--dark);
  background: linear-gradient(135deg, rgba(238,240,255,1) 0%, rgba(200,215,255,0.9) 100%);
  border: none; border-radius: 4px; padding: 15px;
  cursor: pointer;
  transition: opacity 0.2s, transform 0.2s, box-shadow 0.2s;
  box-shadow: 0 4px 24px rgba(238,240,255,0.12);
  position: relative; overflow: hidden;
  clip-path: polygon(12px 0%, 100% 0%, calc(100% - 12px) 100%, 0% 100%);
}
.btn-submit::after {
  content: ''; position: absolute; inset: 0;
  background: linear-gradient(135deg, var(--paper) 0%, var(--scissors) 100%);
  opacity: 0; transition: opacity 0.3s;
}
.btn-submit:hover { opacity: 0.92; transform: translateY(-1px); box-shadow: 0 8px 32px rgba(238,240,255,0.18); }
.btn-submit:hover::after { opacity: 1; }
.btn-submit:active { transform: translateY(0); }
.btn-submit.loading { pointer-events: none; opacity: 0.7; }
.btn-submit.loading .btn-text { opacity: 0; }
.btn-submit .btn-spinner { display: none; position: absolute; inset: 0; align-items: center; justify-content: center; }
.btn-submit.loading .btn-spinner { display: flex; }
.spinner-ring {
  width: 20px; height: 20px;
  border: 2px solid rgba(11,12,16,0.3);
  border-top-color: #0b0c10; border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.btn-close-modal {
  position: absolute; top: 14px; right: 14px;
  background: rgba(238,240,255,0.05);
  border: 1px solid var(--border);
  border-radius: 3px; width: 30px; height: 30px;
  color: var(--muted); font-size: 14px; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  transition: all 0.2s; line-height: 1;
}
.btn-close-modal:hover { background: rgba(255,77,77,0.12); color: var(--text); border-color: rgba(255,77,77,0.3); }

/* Panel visibility */
.form-panel { display: none; }
.form-panel.active { display: block; }

/* SUCCESS */
.success-panel { text-align: center; padding: 0.5rem 0 1rem; }
.success-checkmark {
  width: 80px; height: 80px;
  background: linear-gradient(135deg, color-mix(in srgb, var(--scissors) 20%, transparent), color-mix(in srgb, var(--scissors) 8%, transparent));
  border: 2px solid color-mix(in srgb, var(--scissors) 40%, transparent);
  display: flex; align-items: center; justify-content: center;
  font-size: 36px; margin: 0 auto 1.4rem;
  box-shadow: 0 0 50px color-mix(in srgb, var(--scissors) 20%, transparent);
  animation: popIn 0.5s cubic-bezier(0.34,1.56,0.64,1) both;
  clip-path: polygon(12px 0%, 100% 0%, calc(100% - 12px) 100%, 0% 100%);
}
@keyframes popIn { from{transform:scale(0);opacity:0} to{transform:scale(1);opacity:1} }
.success-title { font-family: 'Bebas Neue', sans-serif; font-size: 1.6rem; color: var(--text); letter-spacing: 0.08em; margin-bottom: 0.6rem; }
.success-desc { font-size: 14px; color: var(--muted); line-height: 1.6; margin-bottom: 0.5rem; font-weight: 500; }
.success-username-highlight { color: var(--scissors); font-weight: 700; }
.success-info-box {
  background: color-mix(in srgb, var(--scissors) 6%, transparent); border: 1px solid color-mix(in srgb, var(--scissors) 18%, transparent);
  border-radius: 4px; padding: 14px 16px; margin: 1.2rem 0 1.6rem;
  font-size: 13px; color: var(--scissors);
  display: flex; align-items: center; gap: 10px; text-align: left;
}
.success-info-box .info-icon { font-size: 18px; flex-shrink: 0; }
.btn-to-login {
  width: 100%; font-family: 'Russo One', sans-serif;
  font-size: 13px; letter-spacing: 0.2em; text-transform: uppercase;
  color: var(--dark); background: linear-gradient(135deg, var(--scissors) 0%, color-mix(in srgb, var(--scissors) 70%, #000) 100%);
  border: none; border-radius: 4px; padding: 15px; cursor: pointer;
  transition: opacity 0.2s, transform 0.2s, box-shadow 0.2s;
  box-shadow: 0 4px 24px color-mix(in srgb, var(--scissors) 25%, transparent);
  clip-path: polygon(12px 0%, 100% 0%, calc(100% - 12px) 100%, 0% 100%);
}
.btn-to-login:hover { opacity: 0.9; transform: translateY(-1px); box-shadow: 0 8px 32px color-mix(in srgb, var(--scissors) 35%, transparent); }

/* PW Strength */
.pw-strength { margin-top: 6px; display: flex; gap: 4px; align-items: center; }
.pw-bar { flex: 1; height: 3px; border-radius: 2px; background: rgba(238,240,255,0.08); transition: background 0.3s; }
.pw-label { font-size: 9px; letter-spacing: 0.15em; color: var(--muted); text-transform: uppercase; min-width: 50px; }
.pw-bar.weak   { background: var(--rock); }
.pw-bar.medium { background: #ffcc4d; }
.pw-bar.strong { background: var(--scissors); }

/* scrollbar */
.modal::-webkit-scrollbar { width: 4px; }
.modal::-webkit-scrollbar-track { background: transparent; }
.modal::-webkit-scrollbar-thumb { background: rgba(77,166,255,0.3); border-radius: 2px; }

/* ══════════════════════════════════════════════════════════
   LIGHT MODE
══════════════════════════════════════════════════════════ */
#btnThemeToggle{
  position:fixed;top:18px;right:22px;z-index:100;
  width:auto;height:34px;border-radius:0;
  border:1px solid rgba(77,166,255,.2);
  background:transparent;color:rgba(77,166,255,.85);
  clip-path:polygon(6px 0%,100% 0%,calc(100% - 6px) 100%,0% 100%);
  cursor:pointer;display:flex;align-items:center;justify-content:center;
  font-family:'Rajdhani',sans-serif;font-weight:700;font-size:.7rem;
  letter-spacing:.12em;text-transform:uppercase;padding:0 14px;
  transition:all .2s;flex-shrink:0;
}
#btnThemeToggle:hover{
  background:rgba(77,166,255,.18);
  border-color:rgba(77,166,255,.45);
  color:#4da6ff;
}
[data-theme="light"]{
  --dark:#f0f4fc;--mid:#e4e8f4;--card:rgba(255,255,255,.75);
  --text:#1a1d2e;--muted:rgba(26,29,46,.5);--border:rgba(0,0,0,.09);
  --rock:#d93030;--paper:#2874c2;--scissors:#1a9940;
  --glow-rock:rgba(217,48,48,.45);--glow-paper:rgba(40,116,194,.45);--glow-scissors:rgba(26,153,64,.45);
}
[data-theme="light"] body{background:#f0f4fc;}
[data-theme="light"] canvas#bg{opacity:.12;}
[data-theme="light"] .noise{opacity:.012;}
[data-theme="light"] .scanline{opacity:.025;}
[data-theme="light"] .corner::before,[data-theme="light"] .corner::after{background:rgba(40,116,194,.25);}
[data-theme="light"] .stage{background:transparent;}
[data-theme="light"] .modal{
  background:linear-gradient(160deg,rgba(245,247,255,.99),rgba(240,244,252,.99));
  border-color:rgba(40,116,194,.1);
}
[data-theme="light"] .modal-tabs{background:rgba(240,244,252,.8);border-bottom-color:rgba(0,0,0,.07);}
[data-theme="light"] .modal-tab{color:var(--muted);}
[data-theme="light"] .modal-tab.active{background:rgba(40,116,194,.12);color:#2874c2;border-color:rgba(40,116,194,.3);}
[data-theme="light"] .field-input{
  background:rgba(255,255,255,.95);border-color:rgba(0,0,0,.15);color:var(--text);
}
[data-theme="light"] .field-input:focus{border-color:rgba(40,116,194,.6);background:#fff;}
[data-theme="light"] .field-icon{color:var(--text);opacity:1;}
[data-theme="light"] .field-label{color:var(--text);font-weight:800;opacity:0.9;}
[data-theme="light"] .toggle-pw{color:var(--text);opacity:0.8;}
[data-theme="light"] .toggle-pw:hover{opacity:1;}
[data-theme="light"] .modal-tab:not(.active):hover{
  background:linear-gradient(135deg, #2874c2 0%, #1a9940 100%);
  color:#fff;
  border-color:transparent;
}
[data-theme="light"] .weapon{
  background:rgba(255,255,255,.15);
  border-color:rgba(0,0,0,.09);
}
[data-theme="light"] .hud-box{background:rgba(255,255,255,.15);border-color:rgba(0,0,0,.07);}
[data-theme="light"] #btnThemeToggle{background:transparent;border-color:rgba(40,116,194,.18);color:rgba(40,116,194,.8);}
[data-theme="light"] #btnThemeToggle:hover{background:rgba(40,116,194,.08);border-color:rgba(40,116,194,.35);color:#2874c2;}
[data-theme="light"] .btn-submit{
  color:#fff;
  background:linear-gradient(135deg, var(--paper) 0%, #1a7ab5 100%);
  box-shadow:0 4px 24px rgba(40,116,194,.25);
}
[data-theme="light"] .btn-submit::after{
  background:linear-gradient(135deg, #1a7ab5 0%, var(--paper) 100%);
}
[data-theme="light"] .btn-to-login{color:#fff;}
body,.modal,.weapon,.hud-box,.form-input,.btn-submit{transition:background .4s ease,border-color .4s ease,color .4s ease;}
</style>
<style>
/* ══ UNIVERSAL BUTTON STYLES (Default & Hover) ══ */
button:not(.btn-theme-toggle):not(.btn-setting):not(.m-close):not(.btn-close-modal):not(.lb2-close-btn):not(.btn-close):not(.ss-mute-btn):not(.xbtn-cancel):not(.exit-btn-cancel):not(.nav-btn.danger):not(.av-edit-btn):not(.edit-name-btn):not(.avm-refresh-btn):not(.toggle-pw):not(.btn-cancel-card):not(.btn-quit):not(.modal-tab),
.btn, .cta, .btn-submit, .btn-to-login,
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

<!-- Main animated background -->
<canvas id="bg"></canvas>
<div class="noise"></div>
<div class="scanline"></div>
<div class="energy-lines" id="energyLines"></div>
<div class="particles" id="particles"></div>

<!-- Theme Toggle Button -->
<button class="btn-theme-toggle" id="btnThemeToggle" title="Ganti Tema"><span class="theme-icon">Light Mode</span></button>

<!-- Corner decorations -->
<div class="corner corner-tl"></div>
<div class="corner corner-tr"></div>
<div class="corner corner-bl"></div>
<div class="corner corner-br"></div>

<!-- Main content -->
<div class="stage">

  <div class="arena-tag">
    <div class="arena-tag-line"></div>
    <span>✦ Battle Arena ✦</span>
    <div class="arena-tag-line"></div>
  </div>

  <div class="title-wrap">
    <div class="title-eyebrow">Siapa yang menang?</div>
    <h1 data-text="BATU · GUNTING · KERTAS">
      <span class="word-batu">BATU</span>
      <span class="word-sep"> · </span>
      <span class="word-gunting">GUNTING</span>
      <span class="word-sep"> · </span>
      <span class="word-kertas">KERTAS</span>
    </h1>
  </div>

  <div class="divider">
    <div class="div-line"></div>
    <div class="div-text">Pilih Senjatamu</div>
    <div class="div-line"></div>
  </div>

  <div class="weapons-row">
    <div class="weapon weapon-rock">
      <div class="w-corner w-corner-tl"></div>
      <div class="w-corner w-corner-br"></div>
      <div class="weapon-icon"><img src="assets/Rock.png" alt="Batu"></div>
      <div class="weapon-name">Batu</div>
    </div>
    <div class="weapon weapon-scissors">
      <div class="w-corner w-corner-tl"></div>
      <div class="w-corner w-corner-br"></div>
      <div class="weapon-icon"><img src="assets/Scissors.png" alt="Gunting"></div>
      <div class="weapon-name">Gunting</div>
    </div>
    <div class="weapon weapon-paper">
      <div class="w-corner w-corner-tl"></div>
      <div class="w-corner w-corner-br"></div>
      <div class="weapon-icon"><img src="assets/Paper.png" alt="Kertas"></div>
      <div class="weapon-name">Kertas</div>
    </div>
  </div>

  <div class="cta-wrap">
    <div class="cta-pulse"></div>
    <button class="cta" id="btnMulai"><span>⚔ Mulai Permainan</span></button>
  </div>

  <div class="hud-row">
    <div class="hud-stat"><span class="hud-val">∞</span>Pertandingan</div>
    <div class="hud-stat"><span class="hud-val">3</span>Mode</div>
    <div class="hud-stat"><span class="hud-val">24/7</span>Online</div>
  </div>

</div>

<!-- ══ MODAL ══ -->
<div class="modal-overlay" id="modalOverlay">
  <div class="modal">
    <div class="modal-corner tl"></div>
    <div class="modal-corner tr"></div>
    <div class="modal-corner bl"></div>
    <div class="modal-corner br"></div>
    <button class="btn-close-modal" id="btnCloseModal" title="Tutup">✕</button>

    <div class="modal-tabs">
      <button class="modal-tab <?php echo $active_modal === 'login'    ? 'active' : ''; ?>" id="tabLogin"    type="button">⚔ Login</button>
      <button class="modal-tab <?php echo $active_modal === 'register' ? 'active' : ''; ?>" id="tabRegister" type="button">🛡 Daftar</button>
    </div>

    <!-- LOGIN PANEL -->
    <div class="form-panel <?php echo $active_modal === 'login' ? 'active' : ''; ?>" id="panelLogin">
      <div class="modal-header">
        <div class="modal-icon">⚔️</div>
        <div class="modal-title">Masuk ke Arena</div>
        <div class="modal-subtitle">Login untuk memulai pertarungan</div>
      </div>

      <form method="POST" action="Landing_page.php" id="loginForm">
        <input type="hidden" name="action" value="login">
        <div class="field-group">
          <div class="field">
            <label class="field-label" for="inputId">Username</label>
            <div class="field-wrap">
              <span class="field-icon">👤</span>
              <input class="field-input" type="text" id="inputId" name="inputId"
                placeholder="Masukkan username kamu"
                autocomplete="username" spellcheck="false"
                value="<?php echo htmlspecialchars($_POST['inputId'] ?? ''); ?>"/>
            </div>
          </div>
          <div class="field">
            <label class="field-label" for="inputPassword">Password</label>
            <div class="field-wrap">
              <span class="field-icon">🔒</span>
              <input class="field-input" type="password" id="inputPassword" name="inputPassword"
                placeholder="Masukkan password" autocomplete="current-password"/>
              <button class="toggle-pw" id="togglePwLogin" title="Tampilkan password" type="button">👁</button>
            </div>
          </div>
        </div>

        <div class="error-msg <?php echo ($error_msg && $active_modal === 'login') ? 'show' : ''; ?>" id="loginErrorMsg">
          <?php echo ($active_modal === 'login') ? htmlspecialchars($error_msg) : ''; ?>
        </div>

        <button class="btn-submit" id="btnLogin" type="submit">
          <span class="btn-text">Masuk &amp; Bertarung</span>
          <span class="btn-spinner"><span class="spinner-ring"></span></span>
        </button>
      </form>
    </div>

    <!-- REGISTER PANEL -->
    <div class="form-panel <?php echo $active_modal === 'register' ? 'active' : ''; ?>" id="panelRegister">
      <div class="modal-header">
        <div class="modal-icon">🛡️</div>
        <div class="modal-title">Buat Akun</div>
        <div class="modal-subtitle">Daftarkan dirimu dan mulai bertarung!</div>
      </div>

      <form method="POST" action="Landing_page.php" id="registerForm">
        <input type="hidden" name="action" value="register">
        <div class="field-group">
          <div class="field">
            <label class="field-label" for="regUsername">Username</label>
            <div class="field-wrap">
              <span class="field-icon">👤</span>
              <input class="field-input" type="text" id="regUsername" name="regUsername"
                placeholder="3–20 karakter, huruf/angka/_"
                autocomplete="username" spellcheck="false" maxlength="20"
                value="<?php echo htmlspecialchars($_POST['regUsername'] ?? ''); ?>"/>
            </div>
          </div>
          <div class="field">
            <label class="field-label" for="regEmail">Email</label>
            <div class="field-wrap">
              <span class="field-icon">✉️</span>
              <input class="field-input" type="email" id="regEmail" name="regEmail"
                placeholder="contoh@email.com"
                autocomplete="email" spellcheck="false" maxlength="100"
                value="<?php echo htmlspecialchars($_POST['regEmail'] ?? ''); ?>"/>
            </div>
          </div>
          <div class="field">
            <label class="field-label" for="regPassword">Password</label>
            <div class="field-wrap">
              <span class="field-icon">🔒</span>
              <input class="field-input" type="password" id="regPassword" name="regPassword"
                placeholder="Minimal 6 karakter" autocomplete="new-password"/>
              <button class="toggle-pw" id="togglePwReg" title="Tampilkan password" type="button">👁</button>
            </div>
            <div class="pw-strength" id="pwStrength" style="display:none;">
              <div class="pw-bar" id="pwBar1"></div>
              <div class="pw-bar" id="pwBar2"></div>
              <div class="pw-bar" id="pwBar3"></div>
              <span class="pw-label" id="pwLabel">—</span>
            </div>
          </div>
          <div class="field">
            <label class="field-label" for="regPasswordConfirm">Konfirmasi Password</label>
            <div class="field-wrap">
              <span class="field-icon">🔏</span>
              <input class="field-input" type="password" id="regPasswordConfirm" name="regPasswordConfirm"
                placeholder="Ulangi password kamu" autocomplete="new-password"/>
            </div>
          </div>
        </div>

        <div class="error-msg <?php echo ($error_msg && $active_modal === 'register') ? 'show' : ''; ?>" id="registerErrorMsg">
          <?php echo ($active_modal === 'register') ? htmlspecialchars($error_msg) : ''; ?>
        </div>

        <button class="btn-submit" id="btnRegister" type="submit">
          <span class="btn-text">Buat Akun &amp; Masuk Arena</span>
          <span class="btn-spinner"><span class="spinner-ring"></span></span>
        </button>
      </form>
    </div>

    <!-- SUCCESS PANEL -->
    <div class="form-panel <?php echo $active_modal === 'success' ? 'active' : ''; ?>" id="panelSuccess">
      <div class="success-panel">
        <div class="success-checkmark">✅</div>
        <div class="success-title">Akun Berhasil Dibuat!</div>
        <div class="success-desc">
          Selamat datang di arena,
          <span class="success-username-highlight"><?php echo htmlspecialchars($success_msg ?: ''); ?></span>!<br>
          Akunmu sudah siap. Silakan login untuk mulai bertarung.
        </div>
        <div class="success-info-box">
          <span class="info-icon">💡</span>
          <span>Gunakan <strong>username</strong> dan <strong>password</strong> yang baru kamu buat untuk masuk ke arena.</span>
        </div>
        <button class="btn-to-login" id="btnGoToLogin" type="button">⚔ &nbsp;Login Sekarang</button>
      </div>
    </div>

  </div>
</div>

<!-- LOGIN TRANSITION -->
<div id="loginTransition">
  <div class="lt-ring-wrap">
    <div class="lt-ring lt-ring-1"></div>
    <div class="lt-ring lt-ring-2"></div>
    <div class="lt-ring lt-ring-3"></div>
    <div class="lt-icon">⚔️</div>
  </div>
  <div class="lt-text">Memasuki Arena</div>
  <div class="lt-sub">Memuat data pemain...</div>
  <div class="lt-dots">
    <div class="lt-dot"></div>
    <div class="lt-dot"></div>
    <div class="lt-dot"></div>
  </div>
</div>

<script>
/* ══ ANIMATED CANVAS BG ══ */
const cvs = document.getElementById('bg');
const ctx = cvs.getContext('2d');
let W, H, nodes = [];

function resize() {
  W = cvs.width  = window.innerWidth;
  H = cvs.height = window.innerHeight;
}

const COLORS = ['rgba(255,77,77,', 'rgba(77,166,255,', 'rgba(125,255,77,'];

function mkNodes() {
  nodes = Array.from({length: 80}, (_, i) => ({
    x: Math.random()*W, y: Math.random()*H,
    vx: (Math.random()-0.5)*0.4, vy: (Math.random()-0.5)*0.4,
    r: Math.random()*2+0.5,
    col: COLORS[i%3],
    a: Math.random()*0.6+0.2,
  }));
}

function drawBg() {
  ctx.clearRect(0,0,W,H);

  /* Deep space gradient */
  const grad = ctx.createRadialGradient(W*0.3, H*0.3, 0, W*0.5, H*0.5, W*0.8);
  grad.addColorStop(0, 'rgba(10,14,40,1)');
  grad.addColorStop(0.5, 'rgba(5,7,18,1)');
  grad.addColorStop(1, 'rgba(3,4,10,1)');
  ctx.fillStyle = grad;
  ctx.fillRect(0,0,W,H);

  /* Moving node connections */
  for (let i = 0; i < nodes.length; i++) {
    const n = nodes[i];
    n.x += n.vx; n.y += n.vy;
    if (n.x < 0 || n.x > W) n.vx *= -1;
    if (n.y < 0 || n.y > H) n.vy *= -1;

    for (let j = i+1; j < nodes.length; j++) {
      const m = nodes[j];
      const dx = n.x - m.x, dy = n.y - m.y;
      const dist = Math.sqrt(dx*dx + dy*dy);
      if (dist < 160) {
        ctx.beginPath();
        ctx.moveTo(n.x, n.y);
        ctx.lineTo(m.x, m.y);
        const alpha = (1 - dist/160) * 0.06;
        ctx.strokeStyle = n.col + alpha + ')';
        ctx.lineWidth = 0.5;
        ctx.stroke();
      }
    }

    /* Node dot */
    ctx.beginPath();
    ctx.arc(n.x, n.y, n.r, 0, Math.PI*2);
    ctx.fillStyle = n.col + n.a + ')';
    ctx.fill();
  }

  /* Static stars */
  for (let i = 0; i < 120; i++) {
    const sx = (i * 137.5) % W;
    const sy = (i * 93.7) % H;
    const sa = 0.1 + 0.4 * Math.abs(Math.sin(Date.now()*0.001 + i));
    ctx.beginPath(); ctx.arc(sx, sy, 0.5, 0, Math.PI*2);
    ctx.fillStyle = `rgba(238,240,255,${sa})`; ctx.fill();
  }

  requestAnimationFrame(drawBg);
}

window.addEventListener('resize', ()=>{ resize(); mkNodes(); });
resize(); mkNodes(); drawBg();


/* ══ ENERGY LINES ══ */
const elContainer = document.getElementById('energyLines');
for (let i = 0; i < 8; i++) {
  const el = document.createElement('div');
  el.className = 'eline';
  const h = Math.random()*40+20;
  el.style.cssText = `
    left: ${Math.random()*100}%;
    height: ${h}px;
    animation-duration: ${Math.random()*8+6}s;
    animation-delay: ${Math.random()*8}s;
    opacity: 0.4;
  `;
  elContainer.appendChild(el);
}


/* ══ PARTICLES ══ */
const pContainer = document.getElementById('particles');
const pColors = ['rgba(255,77,77,', 'rgba(77,166,255,', 'rgba(125,255,77,'];
for (let i = 0; i < 25; i++) {
  const p = document.createElement('div');
  p.className = 'particle';
  const size = Math.random()*4+1;
  const col = pColors[i%3];
  p.style.cssText = `
    left: ${Math.random()*100}%;
    width: ${size}px; height: ${size}px;
    background: ${col}${Math.random()*0.5+0.3});
    box-shadow: 0 0 ${size*3}px ${col}0.6);
    animation-duration: ${Math.random()*15+10}s;
    animation-delay: ${Math.random()*15}s;
  `;
  pContainer.appendChild(p);
}


/* ══ MODAL OPEN/CLOSE ══ */
const overlay  = document.getElementById('modalOverlay');
const btnMulai = document.getElementById('btnMulai');
const btnClose = document.getElementById('btnCloseModal');

function openModal()  { overlay.classList.add('active'); }
function closeModal() { overlay.classList.remove('active'); }

btnMulai.addEventListener('click', openModal);
btnClose.addEventListener('click', closeModal);
overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });


/* ══ TAB SWITCHING ══ */
const tabLogin    = document.getElementById('tabLogin');
const tabRegister = document.getElementById('tabRegister');
const panelLogin    = document.getElementById('panelLogin');
const panelRegister = document.getElementById('panelRegister');

tabLogin.addEventListener('click', function() {
  tabLogin.classList.add('active');    tabRegister.classList.remove('active');
  panelLogin.classList.add('active');  panelRegister.classList.remove('active');
  setTimeout(() => document.getElementById('inputId').focus(), 100);
});
tabRegister.addEventListener('click', function() {
  tabRegister.classList.add('active'); tabLogin.classList.remove('active');
  panelRegister.classList.add('active'); panelLogin.classList.remove('active');
  setTimeout(() => document.getElementById('regUsername').focus(), 100);
});


/* ══ TOGGLE PASSWORD ══ */
function setupTogglePw(btnId, inputId) {
  const btn = document.getElementById(btnId);
  const inp = document.getElementById(inputId);
  if (!btn || !inp) return;
  btn.addEventListener('click', function() {
    inp.type = inp.type === 'password' ? 'text' : 'password';
    btn.textContent = inp.type === 'password' ? '👁' : '🙈';
  });
}
setupTogglePw('togglePwLogin', 'inputPassword');
setupTogglePw('togglePwReg', 'regPassword');


/* ══ PASSWORD STRENGTH ══ */
const regPwInput = document.getElementById('regPassword');
const pwStrength = document.getElementById('pwStrength');
const pwBar1 = document.getElementById('pwBar1');
const pwBar2 = document.getElementById('pwBar2');
const pwBar3 = document.getElementById('pwBar3');
const pwLabel = document.getElementById('pwLabel');

regPwInput.addEventListener('input', function() {
  const val = this.value;
  if (!val) { pwStrength.style.display = 'none'; return; }
  pwStrength.style.display = 'flex';
  let score = 0;
  if (val.length >= 6)  score++;
  if (val.length >= 10) score++;
  if (/[A-Z]/.test(val) || /[0-9]/.test(val) || /[^a-zA-Z0-9]/.test(val)) score++;
  const bars  = [pwBar1, pwBar2, pwBar3];
  const cls   = ['weak','medium','strong'];
  const label = ['Lemah','Sedang','Kuat'];
  bars.forEach((b, i) => { b.className = 'pw-bar'; if (i < score) b.classList.add(cls[score-1]); });
  pwLabel.textContent = label[score-1] || '—';
  pwLabel.style.color = score===1?'#ff8080':score===2?'#ffcc4d':'#7dff4d';
});


/* ══ LOGIN TRANSITION ══ */
function showLoginTransition(callback) {
  const lt = document.getElementById('loginTransition');
  lt.style.animation = 'ltFadeIn 0.4s ease forwards';
  lt.classList.add('active');
  setTimeout(() => { document.body.classList.add('login-exit'); }, 300);
  setTimeout(callback, 900);
}

document.getElementById('loginForm').addEventListener('submit', function(e) {
  e.preventDefault();
  document.getElementById('btnLogin').classList.add('loading');
  const form = this;
  showLoginTransition(() => form.submit());
});
document.getElementById('registerForm').addEventListener('submit', function() {
  document.getElementById('btnRegister').classList.add('loading');
});


/* ══ GO TO LOGIN ══ */
const btnGoToLogin = document.getElementById('btnGoToLogin');
if (btnGoToLogin) {
  btnGoToLogin.addEventListener('click', function() {
    document.getElementById('panelSuccess').classList.remove('active');
    document.getElementById('panelRegister').classList.remove('active');
    document.getElementById('panelLogin').classList.add('active');
    tabLogin.classList.add('active');
    tabRegister.classList.remove('active');
    document.querySelector('.modal-tabs').style.display = '';
    setTimeout(() => document.getElementById('inputId').focus(), 100);
  });
}


/* ══ SHAKE ══ */
const shakeSty = document.createElement('style');
shakeSty.textContent = `
  @keyframes shake {
    0%,100%{transform:translateX(0) scale(1)}
    15%{transform:translateX(-8px)}
    30%{transform:translateX(8px)}
    45%{transform:translateX(-5px)}
    60%{transform:translateX(5px)}
    75%{transform:translateX(-2px)}
  }
  @keyframes ripple{to{transform:translate(-50%,-50%) scale(14);opacity:0}}
`;
document.head.appendChild(shakeSty);


/* ══ WEAPON CARD RIPPLE ══ */
document.querySelectorAll('.weapon').forEach(card => {
  card.addEventListener('click', function(e) {
    const ripple = document.createElement('span');
    ripple.style.cssText = `position:absolute;border-radius:50%;background:rgba(255,255,255,0.2);
      width:10px;height:10px;top:50%;left:50%;transform:translate(-50%,-50%) scale(0);
      animation:ripple 0.5s ease-out forwards;pointer-events:none;z-index:10;`;
    this.appendChild(ripple);
    setTimeout(()=>ripple.remove(), 600);
  });
});


/* ══ AUTO-OPEN & SHAKE ON ERROR ══ */
<?php if ($error_msg || $active_modal === 'success'): ?>
window.addEventListener('DOMContentLoaded', () => {
  openModal();
  <?php if ($error_msg): ?>
  const modal = overlay.querySelector('.modal');
  modal.style.animation = 'shake 0.4s ease';
  <?php endif; ?>
  <?php if ($active_modal === 'success'): ?>
  document.querySelector('.modal-tabs').style.display = 'none';
  <?php endif; ?>
});
<?php endif; ?>
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