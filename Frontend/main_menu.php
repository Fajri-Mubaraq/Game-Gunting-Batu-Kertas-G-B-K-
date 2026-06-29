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
    [2000,'GRANDMASTER','#ffd700'],
    [1700,'MASTER',     '#c084fc'],
    [1500,'DIAMOND',    '#4da6ff'],
    [1300,'PLATINUM',   '#7dff4d'],
    [1100,'GOLD',       '#f5c842'],
    [950, 'SILVER',     '#c0c0c0'],
    [0,   'BRONZE',     '#cd7f32'],
];
$tier_name='BRONZE'; $tier_col='#cd7f32';
foreach($rank_tiers as [$min,$name,$col]){if($p_rating>=$min){$tier_name=$name;$tier_col=$col;break;}}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RPS Battle – Main Menu</title>
<link rel="stylesheet" href="assets/main_menu_scifi.css?v=<?php echo time(); ?>">
<style>
/* ══ UNIVERSAL BUTTON STYLES (Default & Hover) ══ */
button:not(.btn-theme-toggle):not(.btn-setting):not(.mbtn):not(.m-close):not(.btn-close-modal):not(.lb2-close-btn):not(.btn-close):not(.ss-mute-btn):not(.xbtn-cancel):not(.exit-btn-cancel):not(.exit-btn-confirm):not(.nav-btn.danger):not(.av-edit-btn):not(.edit-name-btn):not(.avm-refresh-btn):not(.toggle-pw):not(.btn-cancel-card):not(.btn-quit):not(.modal-tab),
.btn, .cta, .btn-submit, .btn-to-login,
.nav-btn:not(.danger),
a.btn, .xbtn-battle, .lb2-act-btn, .btn-save, .chat-send-btn, .btn-continue, .btn-rematch, .btn-use-card, .btn-confirm-card {
  background: var(--text) !important;
  color: var(--dark) !important;
  border-color: var(--border) !important;
}
button:not(.btn-theme-toggle):not(.btn-setting):not(.mbtn):not(.m-close):not(.btn-close-modal):not(.lb2-close-btn):not(.btn-close):not(.ss-mute-btn):not(.xbtn-cancel):not(.exit-btn-cancel):not(.exit-btn-confirm):not(.nav-btn.danger):not(.av-edit-btn):not(.edit-name-btn):not(.avm-refresh-btn):not(.toggle-pw):not(.btn-cancel-card):not(.btn-quit):not(.modal-tab):hover,
.btn:hover, .cta:hover, .btn-submit:hover, .btn-to-login:hover,
.nav-btn:not(.danger):hover,
a.btn:hover, .xbtn-battle:hover, .lb2-act-btn:hover, .btn-save:hover, .chat-send-btn:hover, .btn-continue:hover, .btn-rematch:hover, .btn-use-card:hover, .btn-confirm-card:hover {
  background: linear-gradient(135deg, #2874c2 0%, #1a9940 100%) !important;
  color: #fff !important;
  border-color: transparent !important;
  box-shadow: 0 4px 15px rgba(26,153,64,0.4) !important;
  transform: translateY(-2px) scale(1.02);
}
.cta::before, .btn-submit::after, .exit-btn::before,
.cta:hover::before, .btn-submit:hover::after, .exit-btn:hover::before {
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
  <a class="pinfo" href="profile.php?from=main_menu.php">
    <div class="pav"><?php echo $menu_avatar?></div>
    <div>
      <div class="pname"><?php echo $menu_dispname?></div>
      <div class="pid">@<?php echo htmlspecialchars($player_name)?></div>
    </div>
  </a>
  <div class="rank-pill">
    <span style="font-size:14px">🏆</span>
    <div>
      <div class="rank-name-lbl" style="color:<?php echo $tier_col?>"><?php echo $tier_name?></div>
      <div class="rank-pts"><?php echo $p_rating?> pts</div>
    </div>
  </div>
  <div class="pbar-right">
    <button class="btn-theme-toggle" id="btnThemeToggle" title="Ganti Tema"><span class="theme-icon">Light Mode</span></button>
    <button class="btn-setting" onclick="openSoundSettings()">⚙ Suara</button>
  </div>
</div>

<!-- MAIN STAGE -->
<div class="stage" id="stage">

  <div class="arena-tag">
    <div class="arena-tag-line"></div>
    <span>✦ Battle Arena ✦</span>
    <div class="arena-tag-line"></div>
  </div>

  <div class="main-title">
    <span class="word-rock">BATU</span>
    <span class="word-sep"> · </span>
    <span class="word-scissors">GUNTING</span>
    <span class="word-sep"> · </span>
    <span class="word-paper">KERTAS</span>
  </div>
  <div class="title-sub">Pilih mode pertarunganmu</div>

  <div class="weapons-row">
    <div class="weapon weapon-rock">
      <div class="w-corner w-tl"></div><div class="w-corner w-br"></div>
      <div class="weapon-icon"><img src="assets/Rock.png" alt="Batu" onerror="this.parentElement.innerHTML='🪨'"></div>
      <div class="weapon-name">Batu</div>
    </div>
    <div class="weapon weapon-scissors">
      <div class="w-corner w-tl"></div><div class="w-corner w-br"></div>
      <div class="weapon-icon"><img src="assets/Scissors.png" alt="Gunting" onerror="this.parentElement.innerHTML='✂️'"></div>
      <div class="weapon-name">Gunting</div>
    </div>
    <div class="weapon weapon-paper">
      <div class="w-corner w-tl"></div><div class="w-corner w-br"></div>
      <div class="weapon-icon"><img src="assets/Paper.png" alt="Kertas" onerror="this.parentElement.innerHTML='📄'"></div>
      <div class="weapon-name">Kertas</div>
    </div>
  </div>

  <div class="menu-btns">
    <button class="mbtn mbtn-ai" onclick="showToast('🤖 Memuat VS A.I…');setTimeout(()=>location.href='lobby.php',750)">⚔ Vs A.I</button>
    <button class="mbtn mbtn-pvp" onclick="location.href='lobby_pvp.php'">⚡ PvP Ranked</button>
    <button class="mbtn mbtn-history" onclick="showToast('🃏 Membuka Collection…');setTimeout(()=>location.href='collection.php',750)">🃏 Collection</button>
    <button class="mbtn mbtn-statistik" onclick="showToast('📊 Membuka Statistik…');setTimeout(()=>location.href='statistik.php',750)">📊 Statistik</button>
    <button class="mbtn mbtn-exit" onclick="showExitModal()">✕ Exit</button>
  </div>

</div>

<!-- BOTTOM BAR -->
<div class="bottom-bar">
  <div class="online-status"><span class="online-dot"></span> Online</div>
  <div class="version">v1.0.1 ✦</div>
</div>

<!-- TOAST -->
<div class="toast" id="toast"></div>

<!-- EXIT CONFIRMATION MODAL -->
<div class="exit-overlay" id="exitOverlay" onclick="if(event.target===this)closeExitModal()">
  <div class="exit-modal" id="exitModal">
    <div class="exit-modal-topbar"></div>
    <div class="exit-icon">⚠️</div>
    <div class="exit-title">KELUAR GAME?</div>
    <div class="exit-desc">Sesimu akan diakhiri.<br>Yakin ingin keluar dari Battle Arena?</div>
    <div class="exit-actions">
      <button class="exit-btn exit-btn-cancel" onclick="closeExitModal()">✕ Batal</button>
      <button class="exit-btn exit-btn-confirm" onclick="location.href='main_menu.php?logout=1'">⏏ Ya, Keluar</button>
    </div>
  </div>
</div>

<!-- SOUND SETTINGS MODAL -->
<div class="ss-overlay" id="ss-overlay" onclick="if(event.target===this)closeSoundSettings()">
  <div class="ss-modal">
    <button class="ss-close" onclick="closeSoundSettings()">✕</button>
    <div class="ss-title">⚙ &nbsp;Pengaturan Suara</div>
    <div class="ss-sub">Berlaku di semua halaman</div>
    <div class="ss-row">
      <div class="ss-label"><span>🎵</span> BGM Menu</div>
      <div class="ss-slider-wrap">
        <input type="range" class="ss-slider" id="ss-bgm-vol" min="0" max="1" step="0.05" value="0.4">
        <div class="ss-val" id="ss-bgm-val">40</div>
      </div>
      <button class="ss-mute-btn" id="ss-bgm-mute" onclick="toggleSsMute('bgm')">🔊</button>
    </div>
    <div class="ss-row">
      <div class="ss-label"><span>🎮</span> BGM Gameplay</div>
      <div class="ss-slider-wrap">
        <input type="range" class="ss-slider" id="ss-gbgm-vol" min="0" max="1" step="0.05" value="0.38">
        <div class="ss-val" id="ss-gbgm-val">38</div>
      </div>
      <button class="ss-mute-btn" id="ss-gbgm-mute" onclick="toggleSsMute('gbgm')">🔊</button>
    </div>
    <div class="ss-row">
      <div class="ss-label"><span>⚔️</span> Fight Music</div>
      <div class="ss-slider-wrap">
        <input type="range" class="ss-slider" id="ss-fight-vol" min="0" max="1" step="0.05" value="0.7">
        <div class="ss-val" id="ss-fight-val">70</div>
      </div>
      <button class="ss-mute-btn" id="ss-fight-mute" onclick="toggleSsMute('fight')">🔊</button>
    </div>
    <div class="ss-divider"></div>
    <div class="ss-row">
      <div class="ss-label"><span>🖱️</span> Klik SFX</div>
      <div class="ss-slider-wrap">
        <input type="range" class="ss-slider" id="ss-click-vol" min="0" max="1" step="0.05" value="0.85">
        <div class="ss-val" id="ss-click-val">85</div>
      </div>
      <button class="ss-mute-btn" id="ss-click-mute" onclick="toggleSsMute('click')">🔊</button>
    </div>
    <div class="ss-footer">
      <button class="ss-apply" onclick="closeSoundSettings()">✔ Simpan & Tutup</button>
    </div>
  </div>
</div>

<script>
// ── CANVAS BACKGROUND ──
(function(){
  const c=document.getElementById('bg');
  const ctx=c.getContext('2d');
  let W,H,stars=[];
  function resize(){W=c.width=window.innerWidth;H=c.height=window.innerHeight;stars=[];
    for(let i=0;i<120;i++)stars.push({x:Math.random()*W,y:Math.random()*H,r:Math.random()*1.2+.2,s:Math.random()*.4+.1,o:Math.random()});}
  function draw(){ctx.clearRect(0,0,W,H);
    stars.forEach(s=>{s.o+=s.s*.008;if(s.o>1)s.o=0;
      ctx.beginPath();ctx.arc(s.x,s.y,s.r,0,Math.PI*2);
      ctx.fillStyle=`rgba(238,240,255,${s.o*.7})`;ctx.fill();});
    requestAnimationFrame(draw);}
  window.addEventListener('resize',resize);resize();draw();
})();

// ── ENERGY LINES ──
(function(){
  const el=document.getElementById('EL');
  for(let i=0;i<10;i++){
    const d=document.createElement('div');d.className='el';
    const dur=8+Math.random()*12,h=60+Math.random()*200;
    d.style.cssText=`left:${Math.random()*100}%;height:${h}px;animation-duration:${dur}s;animation-delay:${-Math.random()*dur}s;opacity:${.2+Math.random()*.3}`;
    el.appendChild(d);}
})();

// ── PARTICLES ──
(function(){
  const pt=document.getElementById('PT');
  const colors=['rgba(77,166,255,.6)','rgba(125,255,77,.5)','rgba(255,77,77,.5)','rgba(245,200,66,.5)'];
  for(let i=0;i<18;i++){
    const d=document.createElement('div');d.className='pt';
    const sz=2+Math.random()*4,dur=10+Math.random()*20;
    d.style.cssText=`width:${sz}px;height:${sz}px;left:${Math.random()*100}%;top:${70+Math.random()*30}%;background:${colors[Math.floor(Math.random()*colors.length)]};animation-duration:${dur}s;animation-delay:${-Math.random()*dur}s;`;
    pt.appendChild(d);}
})();

// ── READY ──
setTimeout(()=>document.getElementById('stage').classList.add('ready'),100);

// ── TOAST ──
function showToast(m,d=2200){const t=document.getElementById('toast');t.textContent=m;t.classList.add('show');setTimeout(()=>t.classList.remove('show'),d);}

// ── SOUND SETTINGS ──
const SS_KEYS={
  bgm:{vol:'gbk_bgm_volume',mute:'gbk_bgm_muted',def:0.4},
  gbgm:{vol:'ls_gbgm_volume',mute:'ls_gbgm_muted',def:0.38},
  fight:{vol:'lucky_sound_vol',mute:'lucky_sound_mute',def:0.7},
  click:{vol:'gbk_click_vol',mute:'gbk_click_mute',def:0.85},
};
function _ssGet(k,d){const v=localStorage.getItem(k);return v!==null?parseFloat(v):d;}
function _ssMuteGet(k){return localStorage.getItem(k)==='true';}
function _applyBgm(v,m){localStorage.setItem('gbk_bgm_volume',v);localStorage.setItem('gbk_bgm_enabled',m?'false':'true');window.dispatchEvent(new StorageEvent('storage',{key:'gbk_bgm_volume',newValue:String(v)}));window.dispatchEvent(new StorageEvent('storage',{key:'gbk_bgm_enabled',newValue:m?'false':'true'}));}
function _applyGbgm(v,m){localStorage.setItem('ls_gbgm_volume',v);localStorage.setItem('ls_gbgm_muted',m?'true':'false');window.dispatchEvent(new StorageEvent('storage',{key:'ls_gbgm_volume',newValue:String(v)}));}
function _applyFight(v,m){localStorage.setItem('lucky_sound_vol',v);localStorage.setItem('lucky_sound_mute',m?'true':'false');if(window.LuckySound){window.LuckySound.setVolume(v);if(m!==window.LuckySound.isMuted)window.LuckySound.toggle();}}
function _applyClick(v,m){localStorage.setItem('gbk_click_vol',v);localStorage.setItem('gbk_click_mute',m?'true':'false');}
const _applyFns={bgm:_applyBgm,gbgm:_applyGbgm,fight:_applyFight,click:_applyClick};
function openSoundSettings(){
  ['bgm','gbgm','fight','click'].forEach(k=>{
    const cfg=SS_KEYS[k],vol=_ssGet(cfg.vol,cfg.def),muted=_ssMuteGet(cfg.mute);
    const sl=document.getElementById('ss-'+k+'-vol'),vl=document.getElementById('ss-'+k+'-val'),mb=document.getElementById('ss-'+k+'-mute');
    if(!sl)return;sl.value=vol;vl.textContent=Math.round(vol*100);mb.textContent=muted?'🔇':'🔊';mb.classList.toggle('muted',muted);
  });
  document.getElementById('ss-overlay').classList.add('open');
}
function closeSoundSettings(){document.getElementById('ss-overlay').classList.remove('open');}
function toggleSsMute(k){
  const cfg=SS_KEYS[k],wasMuted=_ssMuteGet(cfg.mute),nowMuted=!wasMuted,vol=parseFloat(document.getElementById('ss-'+k+'-vol').value);
  localStorage.setItem(cfg.mute,nowMuted);_applyFns[k](vol,nowMuted);
  const btn=document.getElementById('ss-'+k+'-mute');btn.textContent=nowMuted?'🔇':'🔊';btn.classList.toggle('muted',nowMuted);
}
['bgm','gbgm','fight','click'].forEach(k=>{
  const sl=document.getElementById('ss-'+k+'-vol'),vl=document.getElementById('ss-'+k+'-val');
  if(!sl)return;
  sl.addEventListener('input',()=>{const v=parseFloat(sl.value);vl.textContent=Math.round(v*100);const m=_ssMuteGet(SS_KEYS[k].mute);_applyFns[k](v,m);if(v>0&&m){localStorage.setItem(SS_KEYS[k].mute,'false');const b=document.getElementById('ss-'+k+'-mute');b.textContent='🔊';b.classList.remove('muted');}});
});
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeSoundSettings();});

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

// ── EXIT MODAL ──
function showExitModal(){
  const ov=document.getElementById('exitOverlay');
  ov.classList.add('open');
  setTimeout(()=>document.getElementById('exitModal').classList.add('show'),10);
}
function closeExitModal(){
  const md=document.getElementById('exitModal');
  md.classList.remove('show');
  setTimeout(()=>document.getElementById('exitOverlay').classList.remove('open'),280);
}
document.addEventListener('keydown',e=>{
  if(e.key==='Escape'){
    closeExitModal();
    closeSoundSettings();
  }
});
</script>
<script src="assets/sound_system.js"></script>
</body>
</html>