/**
 * ══════════════════════════════════════════════════════════════
 *  SOUND SYSTEM — Gunting Batu Kertas Battle Arena
 *
 *  SEMUA HALAMAN (menu, lobby, profil, gameplay, dll):
 *    → BGM Backsound.mp3 seamless loop, posisi lanjut
 *      saat pindah halaman
 *    → Click SFX (Click.mp3) pada semua tombol & link
 *
 *  Halaman GAMEPLAY SAJA (gameplay.php & gameplay_pvp.php):
 *    → LuckySound: musik fight, SFX senjata, countdown,
 *      kartu, damage, heal, win/lose — semua prosedural
 *    → Widget BGM digeser ke KIRI bawah agar tidak
 *      bertabrakan dengan tombol mute LuckySound (kanan bawah)
 * ══════════════════════════════════════════════════════════════
 */

/* ─────────────────────────────────────────────────────────────
   DETEKSI HALAMAN
───────────────────────────────────────────────────────────── */
const _currentPage = window.location.pathname.split('/').pop().toLowerCase();
const _isGameplay  = _currentPage === 'gameplay.php' || _currentPage === 'gameplay_pvp.php';

/* ══════════════════════════════════════════════════════════════
   BAGIAN 1 — GAMEPLAY: LuckySound
   Hanya aktif di gameplay.php dan gameplay_pvp.php
══════════════════════════════════════════════════════════════ */
if (_isGameplay) {

const LuckySound = (() => {
    'use strict';

    let ctx = null;
    let masterGain = null;
    let _volume = 0.7;
    let _muted = false;
    let _fightMusicNodes = null;
    let _winMusicNodes   = null;
    let _bgLoopId        = null;
    let _fightMusicGain  = null;
    let _winBuffer       = null;
    let _loseBuffer      = null;
    let _resultSrcNode   = null;

    // ── Gameplay BGM (Gbacksound.mp3) ──
    const GBGM_SRC       = 'assets/Gbacksound.mp3';
    const GBGM_VOL_DEFAULT = 0.38;
    const GBGM_DUCK_RATIO  = 0.16;  // duck ke 16% dari volume normal
    // Baca volume dari localStorage (di-set oleh settings panel)
    function _gbgmGetVol() {
        const v = parseFloat(localStorage.getItem('ls_gbgm_volume'));
        return isNaN(v) ? GBGM_VOL_DEFAULT : Math.max(0, Math.min(1, v));
    }
    function _gbgmIsMuted() { return localStorage.getItem('ls_gbgm_muted') === 'true'; }
    let GBGM_VOL       = _gbgmGetVol();
    let GBGM_VOL_DUCK  = GBGM_VOL * GBGM_DUCK_RATIO;
    let _gbgmAudio       = null;   // HTMLAudioElement
    let _gbgmDucked      = false;
    let _gbgmStarted     = false;

    const STORAGE_KEY = 'lucky_sound_vol';
    const WIN_SFX_URL  = 'assets/Win.mp3';
    const LOSE_SFX_URL = 'assets/Lose.mp3';

    function _initCtx() {
        if (ctx) return;
        try {
            ctx = new (window.AudioContext || window.webkitAudioContext)();
            masterGain = ctx.createGain();
            masterGain.connect(ctx.destination);
            // Baca mute state dari settings panel
            const savedMute = localStorage.getItem('lucky_sound_mute') === 'true';
            if (savedMute) _muted = true;
            masterGain.gain.value = _muted ? 0 : _volume;
            try {
                const saved = parseFloat(localStorage.getItem(STORAGE_KEY));
                if (!isNaN(saved)) { _volume = Math.max(0, Math.min(1, saved)); masterGain.gain.value = _muted ? 0 : _volume; }
            } catch(e) {}
        } catch(e) {
            console.warn('[LuckySound] Web Audio API tidak tersedia:', e);
        }
    }

    function _resume() {
        if (!ctx) _initCtx();
        if (!ctx) return false;
        if (ctx.state === 'suspended') ctx.resume();
        return true;
    }

    function _osc(type, freq, startTime, duration, gainVal, endGain, dest) {
        const o = ctx.createOscillator();
        const g = ctx.createGain();
        o.type = type;
        o.frequency.setValueAtTime(freq, startTime);
        g.gain.setValueAtTime(gainVal, startTime);
        g.gain.linearRampToValueAtTime(endGain ?? 0, startTime + duration);
        o.connect(g); g.connect(dest || masterGain);
        o.start(startTime); o.stop(startTime + duration + 0.01);
        return { osc: o, gain: g };
    }

    function _noise(startTime, duration, gainVal, dest) {
        const bufSize  = Math.max(1, Math.floor(ctx.sampleRate * duration));
        const buffer   = ctx.createBuffer(1, bufSize, ctx.sampleRate);
        const data     = buffer.getChannelData(0);
        for (let i = 0; i < bufSize; i++) data[i] = Math.random() * 2 - 1;
        const src = ctx.createBufferSource();
        src.buffer = buffer;
        const g = ctx.createGain();
        g.gain.setValueAtTime(gainVal, startTime);
        g.gain.linearRampToValueAtTime(0, startTime + duration);
        src.connect(g); g.connect(dest || masterGain);
        src.start(startTime); src.stop(startTime + duration + 0.01);
        return { src, gain: g };
    }

    function _stopNodes(arr) {
        if (!arr) return;
        arr.forEach(n => { try { if (n && n.stop) n.stop(); } catch(e){} });
    }

    async function _loadAudioBuffer(url) {
        try {
            if (!ctx) _initCtx();
            if (!ctx) return null;
            const res = await fetch(url);
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const arrayBuf = await res.arrayBuffer();
            return await ctx.decodeAudioData(arrayBuf);
        } catch(e) {
            console.warn(`[LuckySound] Gagal load ${url}:`, e);
            return null;
        }
    }

    function _preloadResultSounds() {
        if (_winBuffer && _loseBuffer) return;
        _loadAudioBuffer(WIN_SFX_URL).then(buf  => { if (buf) _winBuffer  = buf; });
        _loadAudioBuffer(LOSE_SFX_URL).then(buf => { if (buf) _loseBuffer = buf; });
    }

    function _playResultSound(buffer) {
        if (!buffer || !ctx || _muted) return;
        try { if (_resultSrcNode) { _resultSrcNode.stop(); _resultSrcNode = null; } } catch(e) {}
        const src = ctx.createBufferSource();
        src.buffer = buffer;
        const g = ctx.createGain();
        g.gain.value = _volume;
        src.connect(g); g.connect(ctx.destination);
        src.start();
        _resultSrcNode = src;
        src.onended = () => { _resultSrcNode = null; };
    }

    function _stopResultSound() {
        try { if (_resultSrcNode) { _resultSrcNode.stop(); _resultSrcNode = null; } } catch(e) {}
    }

    // ══ GAMEPLAY BGM ══
    function _gbgmFadeTo(targetVol, durationMs) {
        if (!_gbgmAudio) return;
        const startVol = _gbgmAudio.volume;
        const diff     = targetVol - startVol;
        const steps    = Math.max(1, Math.round(durationMs / 16)); // ~60fps
        let step = 0;
        clearInterval(_gbgmAudio._fadeTimer);
        _gbgmAudio._fadeTimer = setInterval(() => {
            step++;
            _gbgmAudio.volume = Math.max(0, Math.min(1, startVol + diff * (step / steps)));
            if (step >= steps) {
                clearInterval(_gbgmAudio._fadeTimer);
                _gbgmAudio.volume = Math.max(0, Math.min(1, targetVol));
            }
        }, durationMs / steps);
    }

    function _gbgmInit() {
        if (_gbgmAudio) return;
        _gbgmAudio = new Audio(GBGM_SRC);
        _gbgmAudio.loop   = true;
        _gbgmAudio.volume = 0;

        // Pause/resume saat tab tersembunyi — di sini _gbgmAudio dalam scope
        document.addEventListener('visibilitychange', () => {
            if (!_gbgmAudio) return;
            if (document.hidden) {
                _gbgmAudio.pause();
            } else if (_gbgmStarted && !_muted) {
                _gbgmAudio.play().catch(() => {});
            }
        });
    }

    function _gbgmStart() {
        if (_gbgmStarted || !_gbgmAudio) return;
        if (_muted || _gbgmIsMuted()) return;
        GBGM_VOL = _gbgmGetVol(); GBGM_VOL_DUCK = GBGM_VOL * GBGM_DUCK_RATIO;
        _gbgmAudio.play().then(() => {
            _gbgmStarted = true;
            _gbgmFadeTo(_gbgmDucked ? GBGM_VOL_DUCK : GBGM_VOL, 800);
        }).catch(() => {});
    }

    function _gbgmDuck() {
        if (_gbgmDucked) return;
        _gbgmDucked = true;
        GBGM_VOL = _gbgmGetVol(); GBGM_VOL_DUCK = GBGM_VOL * GBGM_DUCK_RATIO;
        _gbgmFadeTo((_muted || _gbgmIsMuted()) ? 0 : GBGM_VOL_DUCK, 350);
    }

    function _gbgmUnduck() {
        if (!_gbgmDucked) return;
        _gbgmDucked = false;
        GBGM_VOL = _gbgmGetVol(); GBGM_VOL_DUCK = GBGM_VOL * GBGM_DUCK_RATIO;
        _gbgmFadeTo((_muted || _gbgmIsMuted()) ? 0 : GBGM_VOL, 600);
    }

    function _gbgmSyncMute() {
        if (!_gbgmAudio) return;
        GBGM_VOL = _gbgmGetVol(); GBGM_VOL_DUCK = GBGM_VOL * GBGM_DUCK_RATIO;
        const eff = (_muted || _gbgmIsMuted()) ? 0 : (_gbgmDucked ? GBGM_VOL_DUCK : GBGM_VOL);
        _gbgmFadeTo(eff, 200);
        if (!_gbgmIsMuted() && !_muted && !_gbgmStarted && _gbgmAudio) {
            _gbgmAudio.play().then(() => { _gbgmStarted = true; _gbgmFadeTo(eff, 400); }).catch(()=>{});
        }
        if (_gbgmIsMuted() && _gbgmAudio) {
            setTimeout(() => { if (_gbgmIsMuted()) _gbgmAudio.pause(); }, 250);
        }
    }

    // ══ FIGHT MUSIC ══
    function playFightMusic() {
        _gbgmDuck();         // turunkan gameplay BGM saat fight overlay aktif
        if (!_resume()) return;
        stopFightMusic();

        const t = ctx.currentTime + 0.05;
        const nodes = [];

        const convolver = ctx.createConvolver();
        const irLen = ctx.sampleRate * 1.2;
        const irBuf = ctx.createBuffer(2, irLen, ctx.sampleRate);
        for (let c = 0; c < 2; c++) {
            const d = irBuf.getChannelData(c);
            for (let i = 0; i < irLen; i++) d[i] = (Math.random()*2-1) * Math.pow(1 - i/irLen, 1.8);
        }
        convolver.buffer = irBuf;

        const reverbGain = ctx.createGain();
        reverbGain.gain.value = 0.18;

        _fightMusicGain = ctx.createGain();
        _fightMusicGain.gain.setValueAtTime(0, ctx.currentTime);
        _fightMusicGain.gain.linearRampToValueAtTime(1, ctx.currentTime + 0.05);
        _fightMusicGain.connect(masterGain);

        convolver.connect(reverbGain); reverbGain.connect(_fightMusicGain);

        const comp = ctx.createDynamicsCompressor();
        comp.threshold.value = -16; comp.knee.value = 5;
        comp.ratio.value = 5; comp.attack.value = 0.002; comp.release.value = 0.12;
        comp.connect(_fightMusicGain);
        comp.connect(convolver);

        const lpf = ctx.createBiquadFilter();
        lpf.type = 'lowpass';
        lpf.frequency.setValueAtTime(300, t);
        lpf.frequency.exponentialRampToValueAtTime(18000, t + 1.5);
        lpf.Q.value = 2.5;
        lpf.connect(comp);
        setTimeout(() => { try { lpf.frequency.value = 18000; } catch(e){} }, 1600);

        const BPM  = 145;
        const BEAT = 60 / BPM;
        const LOOP = BEAT * 32;

        const SCALE = [55, 58, 62, 65, 67, 70, 74, 77];
        const BASS  = [55, 58, 55, 62, 58, 55, 67, 58];

        function scheduleBeat(startT) {
            BASS.forEach((note, i) => {
                const freq = 440 * Math.pow(2, (note - 69) / 12) / 2;
                const bt = startT + i * BEAT;
                _osc('sawtooth', freq, bt, BEAT * 0.55, 0.28, 0, lpf);
                _osc('sine', freq / 2, bt, BEAT * 0.7, 0.22, 0, lpf);
            });

            const melody = [0,3,2,4,0,5,3,6, 2,4,1,3,5,4,6,7, 0,2,4,3,1,5,4,2, 3,6,5,4,7,5,3,4];
            melody.forEach((scaleIdx, i) => {
                if (i % 3 === 1 && Math.random() > 0.35) return;
                const freq = 440 * Math.pow(2, (SCALE[scaleIdx % SCALE.length] - 69) / 12);
                const bt   = startT + i * BEAT * 0.5;
                _osc('square', freq, bt, BEAT * 0.28, 0.11, 0, lpf);
                _osc('square', freq * 1.008, bt, BEAT * 0.28, 0.07, 0, lpf);
            });

            for (let i = 0; i < 8; i += 2) {
                const bt = startT + i * BEAT;
                const ko = ctx.createOscillator();
                const kg = ctx.createGain();
                ko.type = 'sine';
                ko.frequency.setValueAtTime(160, bt);
                ko.frequency.exponentialRampToValueAtTime(40, bt + 0.12);
                kg.gain.setValueAtTime(0.9, bt);
                kg.gain.exponentialRampToValueAtTime(0.001, bt + 0.25);
                ko.connect(kg); kg.connect(comp);
                ko.start(bt); ko.stop(bt + 0.28);
                nodes.push(ko);
                _noise(bt, 0.08, 0.35, comp);
            }

            for (let i = 1; i < 8; i += 2) {
                const bt = startT + i * BEAT;
                _noise(bt, 0.14, 0.55, comp);
                _osc('triangle', 220, bt, 0.08, 0.3, 0, comp);
            }

            for (let i = 0; i < 32; i++) {
                const bt = startT + i * BEAT * 0.25;
                const accent = i % 4 === 0 ? 0.28 : (i % 2 === 0 ? 0.16 : 0.08);
                const hf = ctx.createBiquadFilter();
                hf.type = 'highpass'; hf.frequency.value = 8000;
                hf.connect(comp);
                const nn = _noise(bt, 0.05, accent, hf);
                nodes.push(nn.src);
            }

            const chordFreqs = [
                [55,67,74], [58,70,77], [62,74,81], [55,65,70]
            ];
            chordFreqs.forEach((chord, ci) => {
                const bt = startT + ci * BEAT * 8;
                chord.forEach(note => {
                    const freq = 440 * Math.pow(2, (note - 69) / 12);
                    _osc('sawtooth', freq, bt, BEAT * 1.8, 0.08, 0, comp);
                });
            });

            return startT + LOOP;
        }

        let nextStart = scheduleBeat(t);
        const intervalMs = LOOP * 1000 - 50;
        _bgLoopId = setInterval(() => {
            if (!_fightMusicNodes) { clearInterval(_bgLoopId); return; }
            nextStart = scheduleBeat(nextStart);
        }, intervalMs);

        _fightMusicNodes = [...nodes, { stop: () => clearInterval(_bgLoopId) }];
    }

    function stopFightMusic() {
        _gbgmUnduck();       // kembalikan volume gameplay BGM setelah fight selesai
        if (_bgLoopId) { clearInterval(_bgLoopId); _bgLoopId = null; }
        if (_fightMusicGain && ctx) {
            try {
                _fightMusicGain.gain.cancelScheduledValues(ctx.currentTime);
                _fightMusicGain.gain.setValueAtTime(_fightMusicGain.gain.value, ctx.currentTime);
                _fightMusicGain.gain.linearRampToValueAtTime(0, ctx.currentTime + 0.04);
                setTimeout(() => {
                    try { _fightMusicGain.disconnect(); } catch(e) {}
                    _fightMusicGain = null;
                }, 60);
            } catch(e) {}
        }
        _stopNodes(_fightMusicNodes);
        _fightMusicNodes = null;
    }

    // ══ FIGHT BANNER ══
    function playFightBanner() {
        if (!_resume()) return;
        const t = ctx.currentTime;

        const comp = ctx.createDynamicsCompressor();
        comp.threshold.value = -10; comp.ratio.value = 6;
        comp.attack.value = 0.001; comp.release.value = 0.1;
        comp.connect(masterGain);

        const boom = ctx.createOscillator();
        const boomG = ctx.createGain();
        boom.type = 'sine';
        boom.frequency.setValueAtTime(80, t);
        boom.frequency.exponentialRampToValueAtTime(28, t + 0.55);
        boomG.gain.setValueAtTime(0, t);
        boomG.gain.linearRampToValueAtTime(0.95, t + 0.01);
        boomG.gain.exponentialRampToValueAtTime(0.001, t + 0.55);
        boom.connect(boomG); boomG.connect(comp);
        boom.start(t); boom.stop(t + 0.6);

        [[110, 0.55], [138, 0.48], [165, 0.42], [220, 0.35]].forEach(([freq, vol], i) => {
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            o.type = 'sawtooth';
            o.frequency.value = freq;
            g.gain.setValueAtTime(0, t + i * 0.01);
            g.gain.linearRampToValueAtTime(vol, t + i * 0.01 + 0.04);
            g.gain.exponentialRampToValueAtTime(0.001, t + 0.55);
            o.connect(g); g.connect(comp);
            o.start(t + i * 0.01); o.stop(t + 0.6);
        });

        const sw = ctx.createOscillator();
        const sg = ctx.createGain();
        sw.type = 'sawtooth';
        sw.frequency.setValueAtTime(60, t);
        sw.frequency.exponentialRampToValueAtTime(900, t + 0.42);
        sg.gain.setValueAtTime(0.6, t);
        sg.gain.linearRampToValueAtTime(0, t + 0.48);
        sw.connect(sg); sg.connect(comp);
        sw.start(t); sw.stop(t + 0.52);

        [2400, 3200, 4000, 5200].forEach((freq, i) => {
            _osc('sine', freq, t + 0.04 + i * 0.018, 0.55, 0.09 - i * 0.015, 0);
        });

        _noise(t, 0.06, 0.85);
        _noise(t + 0.03, 0.12, 0.5);
        _noise(t + 0.10, 0.25, 0.25);

        _osc('sine', 60, t + 0.08, 0.4, 0.45, 0, comp);
        _osc('sine', 42, t + 0.15, 0.5, 0.3, 0, comp);
    }

    // ══ WEAPON REVEAL ══
    function playWeaponReveal(isP2 = false) {
        if (!_resume()) return;
        const t = ctx.currentTime + (isP2 ? 0.15 : 0);
        const bp = ctx.createBiquadFilter();
        bp.type = 'bandpass';
        bp.frequency.setValueAtTime(isP2 ? 1200 : 800, t);
        bp.frequency.exponentialRampToValueAtTime(isP2 ? 200 : 3000, t + 0.35);
        bp.Q.value = 2;
        bp.connect(masterGain);
        _noise(t, 0.4, 0.5, bp);
        _osc('sine', isP2 ? 660 : 550, t + 0.2, 0.2, 0.2, 0);
    }

    // ══ CLASH ══
    function playClash() {
        if (!_resume()) return;
        const t = ctx.currentTime;

        const dist = ctx.createWaveShaper();
        const curve = new Float32Array(512);
        for (let i = 0; i < 512; i++) curve[i] = Math.tanh(((i/256)-1) * 12);
        dist.curve = curve;
        const distG = ctx.createGain();
        distG.gain.value = 0.85;
        dist.connect(distG); distG.connect(masterGain);

        [120, 180, 270, 380, 520].forEach((f, i) => {
            const o = ctx.createOscillator(); const g = ctx.createGain();
            o.type = 'sawtooth'; o.frequency.value = f;
            g.gain.setValueAtTime(0.32 - i * 0.04, t + i * 0.006);
            g.gain.exponentialRampToValueAtTime(0.001, t + 0.5);
            o.connect(g); g.connect(dist);
            o.start(t); o.stop(t + 0.55);
        });

        const sub = ctx.createOscillator(); const subG = ctx.createGain();
        sub.type = 'sine';
        sub.frequency.setValueAtTime(55, t);
        sub.frequency.exponentialRampToValueAtTime(22, t + 0.28);
        subG.gain.setValueAtTime(0.95, t);
        subG.gain.exponentialRampToValueAtTime(0.001, t + 0.3);
        sub.connect(subG); subG.connect(masterGain);
        sub.start(t); sub.stop(t + 0.35);

        _noise(t, 0.07, 0.85);
        _noise(t + 0.03, 0.18, 0.55);
        _noise(t + 0.12, 0.30, 0.28);
        _osc('triangle', 95, t, 0.28, 0.5, 0);

        [3500, 4800, 6200, 8400].forEach((f, i) => {
            _osc('sine', f, t + 0.04 + i * 0.012, 0.7, 0.10 - i * 0.018, 0);
        });

        const ring = ctx.createOscillator(); const ringG = ctx.createGain();
        ring.type = 'sine'; ring.frequency.value = 2800;
        ringG.gain.setValueAtTime(0.12, t + 0.05);
        ringG.gain.exponentialRampToValueAtTime(0.001, t + 0.8);
        ring.connect(ringG); ringG.connect(masterGain);
        ring.start(t + 0.05); ring.stop(t + 0.85);
    }

    // ══ ROUND WIN ══
    function playRoundWin() {
        if (!_resume()) return;
        const t = ctx.currentTime;
        const winNotes = [523, 659, 784, 1047];
        winNotes.forEach((freq, i) => {
            const bt = t + i * 0.1;
            _osc('sine',     freq,       bt, 0.35, 0.35, 0);
            _osc('triangle', freq * 2,   bt, 0.25, 0.12, 0);
            _osc('sine',     freq * 1.5, bt, 0.28, 0.08, 0);
        });
        _osc('sine', 1047, t + 0.4, 0.8, 0.18, 0);
        _osc('sine', 1318, t + 0.45, 0.7, 0.1, 0);
        _noise(t + 0.3, 0.2, 0.15);
    }

    // ══ ROUND LOSE ══
    function playRoundLose() {
        if (!_resume()) return;
        const t = ctx.currentTime;
        const loseNotes = [392, 311, 262, 196];
        loseNotes.forEach((freq, i) => {
            const bt = t + i * 0.12;
            _osc('sawtooth', freq,   bt, 0.4, 0.3, 0);
            _osc('sine',     freq/2, bt, 0.5, 0.15, 0);
        });
        _osc('sine', 60, t + 0.38, 0.6, 0.6, 0);
        _noise(t + 0.36, 0.18, 0.4);
    }

    // ══ ROUND DRAW ══
    function playRoundDraw() {
        if (!_resume()) return;
        const t = ctx.currentTime;
        _osc('triangle', 440, t,        0.3, 0.3, 0);
        _osc('triangle', 440, t + 0.18, 0.3, 0.25, 0);
        _osc('sine', 220, t, 0.35, 0.2, 0);
        _noise(t, 0.12, 0.3);
        _noise(t + 0.15, 0.1, 0.25);
    }

    // ══ MATCH WIN ══
    function playMatchWin() {
        if (!_resume()) return;
        stopFightMusic();
        _stopNodes(_winMusicNodes);
        const nodes = [];
        const t = ctx.currentTime + 0.05;

        const convWin = ctx.createConvolver();
        const irLen = ctx.sampleRate * 1.8;
        const irBuf = ctx.createBuffer(2, irLen, ctx.sampleRate);
        for (let c = 0; c < 2; c++) {
            const d = irBuf.getChannelData(c);
            for (let i = 0; i < irLen; i++) d[i] = (Math.random()*2-1) * Math.pow(1 - i/irLen, 1.4);
        }
        convWin.buffer = irBuf;
        const revG = ctx.createGain(); revG.gain.value = 0.28;
        convWin.connect(revG); revG.connect(masterGain);

        const comp = ctx.createDynamicsCompressor();
        comp.threshold.value = -10; comp.ratio.value = 3.5;
        comp.attack.value = 0.002; comp.release.value = 0.15;
        comp.connect(masterGain);
        comp.connect(convWin);

        _noise(t, 0.08, 0.9, comp);
        _noise(t + 0.05, 0.18, 0.55, comp);
        const subBoom = ctx.createOscillator();
        const subG = ctx.createGain();
        subBoom.type = 'sine';
        subBoom.frequency.setValueAtTime(110, t);
        subBoom.frequency.exponentialRampToValueAtTime(35, t + 0.55);
        subG.gain.setValueAtTime(0.9, t);
        subG.gain.exponentialRampToValueAtTime(0.001, t + 0.6);
        subBoom.connect(subG); subG.connect(comp);
        subBoom.start(t); subBoom.stop(t + 0.65);
        nodes.push(subBoom);

        const melody = [
            [523,  0.00, 0.20, 0.50],
            [659,  0.20, 0.20, 0.52],
            [784,  0.40, 0.20, 0.54],
            [1047, 0.60, 0.65, 0.65],
            [880,  0.65, 0.22, 0.32],
            [784,  0.88, 0.22, 0.32],
            [1047, 1.10, 0.22, 0.55],
            [880,  1.32, 0.22, 0.44],
            [1175, 1.55, 0.40, 0.70],
            [1047, 1.96, 0.80, 0.60],
            [784,  2.00, 0.50, 0.25],
            [659,  2.05, 0.45, 0.18],
        ];
        melody.forEach(([freq, off, dur, vol]) => {
            const bt = t + off;
            const o1 = ctx.createOscillator(); const g1 = ctx.createGain();
            o1.type = 'sine'; o1.frequency.value = freq;
            g1.gain.setValueAtTime(vol * 0.65, bt);
            g1.gain.setValueAtTime(vol * 0.65, bt + dur * 0.7);
            g1.gain.linearRampToValueAtTime(0, bt + dur);
            o1.connect(g1); g1.connect(comp);
            o1.start(bt); o1.stop(bt + dur + 0.01);
            nodes.push(o1);
            const o2 = ctx.createOscillator(); const g2 = ctx.createGain();
            o2.type = 'triangle'; o2.frequency.value = freq * 2;
            g2.gain.setValueAtTime(vol * 0.18, bt);
            g2.gain.linearRampToValueAtTime(0, bt + dur);
            o2.connect(g2); g2.connect(comp);
            o2.start(bt); o2.stop(bt + dur + 0.01);
            nodes.push(o2);
        });

        [[523,0,0.28],[659,0.20,0.26],[784,0.40,0.24],[1047,0.60,0.32]].forEach(([freq, off, dur]) => {
            const bt = t + off;
            const o = ctx.createOscillator(); const g = ctx.createGain();
            o.type = 'sawtooth'; o.frequency.value = freq;
            g.gain.setValueAtTime(0, bt);
            g.gain.linearRampToValueAtTime(0.26, bt + 0.02);
            g.gain.setValueAtTime(0.26, bt + 0.05);
            g.gain.linearRampToValueAtTime(0, bt + dur);
            o.connect(g); g.connect(comp);
            o.start(bt); o.stop(bt + dur + 0.01);
            nodes.push(o);
        });

        [[55, 0, 0.7], [55, 0.20, 0.6], [55, 0.40, 0.55], [69, 0.60, 0.8]].forEach(([note, off, vol]) => {
            const freq = 440 * Math.pow(2, (note - 69) / 12);
            const bt = t + off;
            const ko = ctx.createOscillator(); const kg = ctx.createGain();
            ko.type = 'sine';
            ko.frequency.setValueAtTime(freq * 2.5, bt);
            ko.frequency.exponentialRampToValueAtTime(freq, bt + 0.08);
            kg.gain.setValueAtTime(vol, bt);
            kg.gain.exponentialRampToValueAtTime(0.001, bt + 0.5);
            ko.connect(kg); kg.connect(comp);
            ko.start(bt); ko.stop(bt + 0.55);
            nodes.push(ko);
            _noise(bt, 0.06, vol * 0.4, comp);
        });

        for (let i = 1; i < 8; i += 2) {
            _noise(t + i * 0.2, 0.10, 0.5, comp);
            _osc('triangle', 250, t + i * 0.2, 0.07, 0.3, 0, comp);
        }

        const sw = ctx.createOscillator(); const sg = ctx.createGain();
        sw.type = 'sine';
        sw.frequency.setValueAtTime(250, t + 0.5);
        sw.frequency.exponentialRampToValueAtTime(4800, t + 2.8);
        sg.gain.setValueAtTime(0, t + 0.5);
        sg.gain.linearRampToValueAtTime(0.15, t + 0.9);
        sg.gain.linearRampToValueAtTime(0, t + 2.8);
        sw.connect(sg); sg.connect(masterGain);
        sw.start(t + 0.5); sw.stop(t + 2.9);
        nodes.push(sw);

        [2093, 2637, 3136, 4186].forEach((f, i) => {
            _osc('sine', f, t + 1.55 + i * 0.06, 0.55, 0.14 - i * 0.02, 0, comp);
        });
        [1047, 1318, 1568, 2093].forEach((f, i) => {
            _osc('sine', f, t + 2.1 + i * 0.08, 0.6, 0.08 - i * 0.01, 0);
        });

        _winMusicNodes = nodes;
        _playResultSound(_winBuffer);
    }

    // ══ MATCH LOSE ══
    function playMatchLose() {
        if (!_resume()) return;
        stopFightMusic();
        _stopNodes(_winMusicNodes);
        const t = ctx.currentTime + 0.05;

        const convLose = ctx.createConvolver();
        const irLen = ctx.sampleRate * 2.5;
        const irBuf = ctx.createBuffer(2, irLen, ctx.sampleRate);
        for (let c = 0; c < 2; c++) {
            const d = irBuf.getChannelData(c);
            for (let i = 0; i < irLen; i++) d[i] = (Math.random()*2-1) * Math.pow(1 - i/irLen, 1.1);
        }
        convLose.buffer = irBuf;
        const revG = ctx.createGain(); revG.gain.value = 0.35;
        convLose.connect(revG); revG.connect(masterGain);

        _noise(t, 0.12, 0.75);
        _noise(t + 0.06, 0.22, 0.45);

        const sub = ctx.createOscillator(); const subG = ctx.createGain();
        sub.type = 'sine';
        sub.frequency.setValueAtTime(95, t);
        sub.frequency.exponentialRampToValueAtTime(28, t + 0.65);
        subG.gain.setValueAtTime(0.88, t);
        subG.gain.exponentialRampToValueAtTime(0.001, t + 0.7);
        sub.connect(subG); subG.connect(masterGain);
        sub.connect(subG); subG.connect(convLose);
        sub.start(t); sub.stop(t + 0.75);

        const doom = [
            [392, 0.10, 0.50, 0.42],
            [370, 0.60, 0.50, 0.40],
            [349, 1.10, 0.55, 0.38],
            [311, 1.65, 0.60, 0.36],
            [277, 2.25, 0.65, 0.33],
            [247, 2.90, 0.75, 0.30],
            [220, 3.65, 1.10, 0.25],
        ];
        doom.forEach(([freq, off, dur, vol]) => {
            const bt = t + off;
            const o = ctx.createOscillator(); const g = ctx.createGain();
            o.type = 'sawtooth'; o.frequency.value = freq;
            g.gain.setValueAtTime(vol * 0.6, bt);
            g.gain.setValueAtTime(vol * 0.55, bt + dur * 0.6);
            g.gain.linearRampToValueAtTime(0, bt + dur);
            o.connect(g); g.connect(convLose);
            o.start(bt); o.stop(bt + dur + 0.01);
            _osc('sine', freq / 2, bt, dur, vol * 0.32, 0, convLose);
            _osc('sine', freq * 1.005, bt, dur, vol * 0.12, 0, convLose);
        });

        const droneFreqs = [55, 73.4, 82.4];
        droneFreqs.forEach((freq, i) => {
            const o = ctx.createOscillator(); const g = ctx.createGain();
            o.type = 'sine'; o.frequency.value = freq;
            g.gain.setValueAtTime(0, t + 0.3);
            g.gain.linearRampToValueAtTime(0.12 - i * 0.03, t + 1.2);
            g.gain.linearRampToValueAtTime(0, t + 4.8);
            o.connect(g); g.connect(masterGain);
            o.start(t + 0.3); o.stop(t + 5.0);
        });

        for (let i = 0; i < 5; i++) {
            const bt = t + 0.1 + i * 0.62;
            const ko = ctx.createOscillator(); const kg = ctx.createGain();
            ko.type = 'sine';
            ko.frequency.setValueAtTime(100, bt);
            ko.frequency.exponentialRampToValueAtTime(38, bt + 0.22);
            kg.gain.setValueAtTime(Math.max(0.38 - i * 0.06, 0.1), bt);
            kg.gain.exponentialRampToValueAtTime(0.001, bt + 0.38);
            ko.connect(kg); kg.connect(masterGain);
            ko.start(bt); ko.stop(bt + 0.4);
            _noise(bt + 0.01, 0.16, Math.max(0.22 - i * 0.03, 0.05));
        }

        _osc('sine', 1175, t + 0.5, 2.8, 0.07, 0);
        _osc('sine', 988,  t + 0.9, 2.5, 0.05, 0);
        _osc('sine', 880,  t + 1.4, 2.2, 0.04, 0);
        _noise(t + 3.2, 1.0, 0.12);

        _playResultSound(_loseBuffer);
    }

    // ══ CHOICE SELECT ══
    function playChoiceSelect() {
        if (!_resume()) return;
        const t = ctx.currentTime;
        _osc('sine',     880,  t,       0.12, 0.3, 0);
        _osc('triangle', 1320, t + 0.06, 0.15, 0.2, 0);
        _noise(t, 0.05, 0.18);
    }

    // ══ COUNTDOWN ══
    function playCountdown(seconds) {
        if (!_resume()) return;
        const t = ctx.currentTime;
        if (seconds <= 3) {
            _osc('square', 880,  t,       0.08, 0.4, 0);
            _osc('square', 1760, t + 0.04, 0.06, 0.25, 0);
            _noise(t, 0.05, 0.3);
        } else {
            _osc('sine', 440, t, 0.07, 0.22, 0);
            _noise(t, 0.03, 0.12);
        }
    }

    // ══ CARD ACTIVATE ══
    // Tiap rarity punya karakter suara yang berbeda:
    //   common  — blip kering, sangat singkat
    //   rare    — crystal shimmer, swoosh + ping
    //   epic    — power slam, bass thud + chord naik
    //   legend  — fanfare megah, sub impact + reverb + arpeggio
    function playCardActivate(rarity = 'common') {
        if (!_resume()) return;
        const t = ctx.currentTime;

        switch(rarity) {

            // ── COMMON: blip singkat & kering ──
            case 'common': {
                // Dua tone pendek naik — terasa "konfirmasi" ringan
                _osc('sine', 520, t,        0.08, 0.18, 0);
                _osc('sine', 780, t + 0.06, 0.07, 0.14, 0);
                _noise(t, 0.04, 0.10);
                break;
            }

            // ── RARE: crystal shimmer ──
            // Karakter: swoosh filter naik + ping nyaring bersih
            case 'rare': {
                // Swoosh: noise lewat high-pass yang frekuensinya naik cepat
                const hpRare = ctx.createBiquadFilter();
                hpRare.type = 'highpass';
                hpRare.frequency.setValueAtTime(400, t);
                hpRare.frequency.exponentialRampToValueAtTime(6000, t + 0.28);
                hpRare.Q.value = 1.5;
                hpRare.connect(masterGain);
                _noise(t, 0.3, 0.35, hpRare);

                // Crystal ping: sine murni + harmonik oktaf, decay panjang
                _osc('sine',     1568, t + 0.12, 0.55, 0.28, 0);
                _osc('sine',     3136, t + 0.13, 0.30, 0.10, 0);
                _osc('triangle', 784,  t + 0.11, 0.20, 0.12, 0);

                // Trailing shimmer
                _osc('sine', 2093, t + 0.35, 0.4, 0.06, 0);
                break;
            }

            // ── EPIC: power slam ──
            // Karakter: sub-bass thud keras + chord bertingkat yang naik dramatis
            case 'epic': {
                const compEpic = ctx.createDynamicsCompressor();
                compEpic.threshold.value = -12; compEpic.ratio.value = 5;
                compEpic.attack.value = 0.001; compEpic.release.value = 0.1;
                compEpic.connect(masterGain);

                // Sub thud — seperti sesuatu berat dijatuhkan
                const subE = ctx.createOscillator();
                const subGE = ctx.createGain();
                subE.type = 'sine';
                subE.frequency.setValueAtTime(120, t);
                subE.frequency.exponentialRampToValueAtTime(38, t + 0.22);
                subGE.gain.setValueAtTime(0.9, t);
                subGE.gain.exponentialRampToValueAtTime(0.001, t + 0.25);
                subE.connect(subGE); subGE.connect(compEpic);
                subE.start(t); subE.stop(t + 0.28);

                _noise(t, 0.1, 0.6, compEpic);

                // Chord naik: 4 nada dengan jeda staccato
                [[196,0], [294,0.09], [392,0.18], [523,0.27]].forEach(([freq, off]) => {
                    _osc('sawtooth', freq,       t + off, 0.22, 0.32, 0, compEpic);
                    _osc('sine',     freq * 1.5, t + off, 0.20, 0.14, 0, compEpic);
                });

                // Accent tone di puncak
                _osc('sine', 784, t + 0.38, 0.35, 0.28, 0);
                _osc('sine', 988, t + 0.40, 0.28, 0.20, 0);
                _noise(t + 0.36, 0.08, 0.3);
                break;
            }

            // ── LEGEND: fanfare megah ──
            // Karakter: sub impact + reverb panjang + arpeggio emas naik + shimmer akhir
            case 'legend': {
                // Reverb convolver
                const convL = ctx.createConvolver();
                const irLen = ctx.sampleRate * 1.6;
                const irBuf = ctx.createBuffer(2, irLen, ctx.sampleRate);
                for (let c = 0; c < 2; c++) {
                    const d = irBuf.getChannelData(c);
                    for (let i = 0; i < irLen; i++)
                        d[i] = (Math.random() * 2 - 1) * Math.pow(1 - i / irLen, 1.5);
                }
                convL.buffer = irBuf;
                const revGainL = ctx.createGain(); revGainL.gain.value = 0.3;
                convL.connect(revGainL); revGainL.connect(masterGain);

                const compL = ctx.createDynamicsCompressor();
                compL.threshold.value = -10; compL.ratio.value = 4;
                compL.attack.value = 0.001; compL.release.value = 0.12;
                compL.connect(masterGain);
                compL.connect(convL);

                // Sub-bass BOOM ganda
                const subL = ctx.createOscillator(); const subGL = ctx.createGain();
                subL.type = 'sine';
                subL.frequency.setValueAtTime(130, t);
                subL.frequency.exponentialRampToValueAtTime(32, t + 0.5);
                subGL.gain.setValueAtTime(0.95, t);
                subGL.gain.exponentialRampToValueAtTime(0.001, t + 0.55);
                subL.connect(subGL); subGL.connect(compL);
                subL.start(t); subL.stop(t + 0.6);

                _noise(t,        0.12, 0.85, compL);
                _noise(t + 0.08, 0.2,  0.45, compL);

                // Arpeggio emas naik — 6 nada, interval makin cepat
                const arpeNotes = [392, 523, 659, 784, 1047, 1318];
                const arpeGaps  = [0, 0.13, 0.24, 0.34, 0.43, 0.51];
                arpeNotes.forEach((freq, i) => {
                    const bt = t + arpeGaps[i];
                    _osc('sine',     freq,     bt, 0.38 - i * 0.03, 0.38 - i * 0.03, 0, compL);
                    _osc('triangle', freq * 2, bt, 0.28 - i * 0.02, 0.12,             0, compL);
                    if (i >= 3) _osc('sawtooth', freq, bt, 0.16, 0.2, 0, compL);
                });

                // Puncak: chord majestatik
                [784, 988, 1175, 1568].forEach((f, i) => {
                    _osc('sine', f, t + 0.56 + i * 0.02, 0.7, 0.22 - i * 0.03, 0, compL);
                });

                // Trailing shimmer panjang
                _osc('sine', 2637, t + 0.58, 1.0, 0.12, 0);
                _osc('sine', 3136, t + 0.60, 0.9, 0.07, 0);
                _osc('sine', 4186, t + 0.63, 0.8, 0.04, 0);
                break;
            }

            default: {
                // Fallback sama dengan common
                _osc('sine', 520, t,        0.08, 0.18, 0);
                _osc('sine', 780, t + 0.06, 0.07, 0.14, 0);
                _noise(t, 0.04, 0.10);
                break;
            }
        }
    }

    // ══ DAMAGE HIT ══
    function playDamageHit() {
        if (!_resume()) return;
        const t = ctx.currentTime;
        _noise(t, 0.12, 0.5);
        _osc('sawtooth', 200, t,        0.1, 0.35, 0);
        _osc('sawtooth', 150, t + 0.04, 0.12, 0.25, 0);
        _osc('sine', 80, t, 0.2, 0.4, 0);
    }

    // ══ HEAL ══
    function playHeal() {
        if (!_resume()) return;
        const t = ctx.currentTime;
        [523, 659, 784].forEach((f, i) => {
            _osc('sine', f, t + i * 0.08, 0.3, 0.25, 0);
        });
        _osc('sine', 1047, t + 0.25, 0.4, 0.18, 0);
        _noise(t + 0.1, 0.15, 0.1);
    }

    // ══ VOLUME / MUTE ══
    function setVolume(val) {
        _volume = Math.max(0, Math.min(1, val));
        if (masterGain && !_muted) masterGain.gain.value = _volume;
        try { localStorage.setItem(STORAGE_KEY, _volume); } catch(e) {}
    }

    function toggle() {
        _muted = !_muted;
        if (masterGain) masterGain.gain.value = _muted ? 0 : _volume;
        _gbgmSyncMute();     // sinkronkan mute/unmute ke gameplay BGM
        return _muted;
    }

    // ══ MUTE BUTTON UI ══
    function _injectMuteButton() {
        // UI tombol mute dihapus — kontrol suara dipusatkan di Settings Panel (main_menu.php)
    }

    // ══ AUTO-HOOK fungsi gameplay ══
    function _autoHook() {
        const hookFns = () => {
            if (typeof window.showFightOverlay === 'function' && !window._lsFightHooked) {
                const orig = window.showFightOverlay;
                window.showFightOverlay = function(myChoice, oppChoice, iWon, draw, ...rest) {
                    playFightBanner();
                    setTimeout(playFightMusic, 480);
                    setTimeout(() => playWeaponReveal(false),  820);
                    setTimeout(() => playWeaponReveal(true),  1000);
                    setTimeout(playClash, 2350);
                    setTimeout(() => {
                        stopFightMusic();
                        if (draw)       playRoundDraw();
                        else if (iWon)  _playResultSound(_winBuffer);
                        else            _playResultSound(_loseBuffer);
                    }, 2500);
                    return orig.apply(this, [myChoice, oppChoice, iWon, draw, ...rest]);
                };
                window._lsFightHooked = true;
            }

            if (typeof window.handleMatchOver === 'function' && !window._lsMatchHooked) {
                const origMO = window.handleMatchOver;
                window.handleMatchOver = function(...args) {
                    stopFightMusic();
                    _stopNodes(_winMusicNodes);
                    _winMusicNodes = null;
                    return origMO.apply(this, args);
                };
                window._lsMatchHooked = true;
            }

            if (typeof window.sendChoice === 'function' && !window._lsChoiceHooked) {
                const origSC = window.sendChoice;
                window.sendChoice = function(...args) {
                    playChoiceSelect();
                    return origSC.apply(this, args);
                };
                window._lsChoiceHooked = true;
            }

            if (typeof window.startTimer === 'function' && !window._lsTimerHooked) {
                const origTmr = window.startTimer;
                window.startTimer = function(secs, ...rest) {
                    const result = origTmr.apply(this, [secs, ...rest]);
                    if (window.timerInt) {
                        clearInterval(window.timerInt);
                        let left = secs || window.TIMER_SECS || 8;
                        const numEl  = document.getElementById('timer-num');
                        const ringEl = document.getElementById('timer-ring');
                        const circEl = document.getElementById('timer-circle');
                        const msgEl  = document.getElementById('timeout-msg');
                        const CIRC   = window.CIRC || 138.2;
                        window.timerLeft = left;
                        window.timerInt = setInterval(() => {
                            window.timerLeft--;
                            left = window.timerLeft;
                            if (numEl)  numEl.textContent = left;
                            if (circEl) circEl.style.strokeDashoffset = CIRC * (1 - left / (secs || window.TIMER_SECS || 8));
                            if (left <= 2 && ringEl) ringEl.classList.add('urgent');
                            if (left <= 3) playCountdown(left);
                            if (left <= 0) {
                                clearInterval(window.timerInt);
                                if (!window.locked && !window.matchOver) {
                                    window.locked = true;
                                    if (msgEl) msgEl.textContent = '⏰ WAKTU HABIS! KAMU KALAH RONDE!';
                                    document.querySelectorAll('.choice').forEach(c => {
                                        c.classList.add('disabled');
                                        c.style.opacity = '0.2'; c.style.transform = 'scale(0.82)';
                                        c.style.pointerEvents = 'none'; c.style.transition = 'all 0.3s';
                                    });
                                    const p1Badge = document.getElementById('p1-chose-badge');
                                    if (p1Badge) { p1Badge.textContent='❌ TIMEOUT'; p1Badge.classList.add('show'); p1Badge.style.color='var(--red)'; p1Badge.style.borderColor='var(--red)'; }
                                    if (typeof window.setStatus === 'function') window.setStatus('⏰ Waktu habis! Kamu otomatis kalah ronde ini!', 'red');
                                    setTimeout(() => {
                                        document.getElementById('p2-chose-badge')?.classList.add('show');
                                        setTimeout(() => { if (typeof window.handleTimeoutLoss==='function') window.handleTimeoutLoss(); }, 300);
                                    }, 600);
                                }
                            }
                        }, 1000);
                    }
                    return result;
                };
                window._lsTimerHooked = true;
            }

            if (typeof window.flashDamage === 'function' && !window._lsDmgHooked) {
                const origFD = window.flashDamage;
                window.flashDamage = function(...args) {
                    playDamageHit();
                    return origFD.apply(this, args);
                };
                window._lsDmgHooked = true;
            }

            if (typeof window.flashHeal === 'function' && !window._lsHealHooked) {
                const origFH = window.flashHeal;
                window.flashHeal = function(...args) {
                    playHeal();
                    return origFH.apply(this, args);
                };
                window._lsHealHooked = true;
            }

            if (typeof window.playCardActivationAnim === 'function' && !window._lsCardHooked) {
                const origCA = window.playCardActivationAnim;
                window.playCardActivationAnim = function(card, ...rest) {
                    playCardActivate(card?.rarity || 'common');
                    return origCA.apply(this, [card, ...rest]);
                };
                window._lsCardHooked = true;
            }
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => setTimeout(hookFns, 100));
        } else {
            setTimeout(hookFns, 200);
        }
    }

    // ── Init ──
    _injectMuteButton();
    _autoHook();

    // Coba langsung aktifkan Gsound tanpa menunggu interaksi user.
    // Browser modern memblokir AudioContext sebelum ada gesture, tapi
    // HTMLAudioElement kadang diizinkan jika user sudah pernah berinteraksi
    // di halaman sebelumnya (navigasi dari menu/lobby).
    function _tryAutostart() {
        _initCtx();
        _preloadResultSounds();
        _gbgmInit();
        // Coba resume AudioContext (berhasil jika sudah ada gesture sebelumnya)
        if (ctx && ctx.state === 'suspended') {
            ctx.resume().catch(() => {});
        }
        // Langsung play Gsound — akan berhasil jika browser izinkan autoplay
        if (_gbgmAudio && !_gbgmStarted && !_muted) {
            _gbgmAudio.play().then(() => {
                _gbgmStarted = true;
                _gbgmFadeTo(_gbgmDucked ? GBGM_VOL_DUCK : GBGM_VOL, 800);
                if (ctx && ctx.state === 'suspended') ctx.resume().catch(() => {});
            }).catch(() => {
                // Browser blokir autoplay — tunggu interaksi pertama
            });
        }
    }

    // Jalankan secepatnya saat DOM siap
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', _tryAutostart);
    } else {
        _tryAutostart();
    }

    // Fallback: jika autoplay diblokir browser, aktifkan saat interaksi pertama
    const _unlock = () => {
        if (!ctx) _initCtx();
        if (ctx && ctx.state === 'suspended') ctx.resume().catch(() => {});
        _preloadResultSounds();
        if (!_gbgmAudio) _gbgmInit();
        _gbgmStart();   // _gbgmStart sudah cek _gbgmStarted, aman dipanggil ulang
        document.removeEventListener('click',      _unlock, true);
        document.removeEventListener('touchstart', _unlock, true);
        document.removeEventListener('keydown',    _unlock, true);
        document.removeEventListener('mousemove',  _unlock, true);
        document.removeEventListener('pointerdown',_unlock, true);
    };
    document.addEventListener('click',       _unlock, { capture: true, once: true });
    document.addEventListener('touchstart',  _unlock, { capture: true, once: true, passive: true });
    document.addEventListener('keydown',     _unlock, { capture: true, once: true });
    document.addEventListener('mousemove',   _unlock, { capture: true, once: true, passive: true });
    document.addEventListener('pointerdown', _unlock, { capture: true, once: true });

    // ── Dengarkan perubahan dari Settings Panel (real-time) ──
    window.addEventListener('storage', (e) => {
        if (e.key === 'lucky_sound_vol') {
            const v = parseFloat(e.newValue);
            if (!isNaN(v)) setVolume(v);
        }
        if (e.key === 'lucky_sound_mute') {
            const wantMuted = e.newValue === 'true';
            if (wantMuted !== _muted) toggle();
        }
        if (e.key === 'ls_gbgm_volume' || e.key === 'ls_gbgm_muted') {
            _gbgmSyncMute();
        }
    });

    return {
        playFightMusic, stopFightMusic, playFightBanner, playWeaponReveal,
        playClash, playRoundWin, playRoundLose, playRoundDraw,
        playMatchWin, playMatchLose, playChoiceSelect, playCountdown,
        playCardActivate, playDamageHit, playHeal,
        setVolume, toggle,
        get isMuted() { return _muted; },
        get volume()  { return _volume; },
    };
})();

window.LuckySound = LuckySound;

} // end _isGameplay

/* ══════════════════════════════════════════════════════════════
   BAGIAN 2 — BGM + Click SFX
   Aktif di SEMUA halaman, termasuk gameplay
   (Di gameplay: widget BGM ada di kiri bawah, LuckySound mute
    button tetap di kanan bawah — tidak saling bertabrakan)
══════════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  const BGM_SRC   = 'assets/Backsound.mp3';
  const CLICK_SRC = 'assets/Click.mp3';

  const KEY_ENABLED  = 'gbk_bgm_enabled';
  const KEY_VOLUME   = 'gbk_bgm_volume';
  const KEY_POSITION = 'gbk_bgm_position';
  const KEY_SAVED_AT = 'gbk_bgm_savedAt';

  /* ── Shared AudioContext ── */
  let ctx         = null;
  let ctxReady    = false;
  let clickBuffer = null;

  function ensureContext() {
    if (ctx) return;
    ctx = new (window.AudioContext || window.webkitAudioContext)();
  }

  async function resumeContext() {
    ensureContext();
    if (ctx.state === 'suspended') await ctx.resume();
    ctxReady = true;
  }

  async function loadClickSfx() {
    if (clickBuffer) return;
    ensureContext();
    try {
      const res    = await fetch(CLICK_SRC);
      const arrBuf = await res.arrayBuffer();
      clickBuffer  = await ctx.decodeAudioData(arrBuf);
    } catch (e) {
      console.warn('[SFX] Gagal load Click.mp3:', e);
    }
  }

  /* ── Durasi transisi (ms) — BGM fade-out + page fade ── */
  const TRANSITION_MS = 220;

  /* ── CSS page-transition: fade out body sebelum navigate ── */
  (function injectTransitionCSS() {
    const style = document.createElement('style');
    style.textContent = `
      body { transition: opacity ${TRANSITION_MS}ms ease !important; }
      body.page-exit { opacity: 0 !important; pointer-events: none !important; }
    `;
    document.head.appendChild(style);
  })();

  /* ── Click sound untuk halaman GAMEPLAY ──
     Delegasi ke LuckySound.playChoiceSelect() agar identik
     dengan suara klik pemilihan senjata.                           */
  function _playGameplayClick() {
    if (window.LuckySound && typeof window.LuckySound.playChoiceSelect === 'function') {
      window.LuckySound.playChoiceSelect();
    }
  }

  /* ── Click sound untuk halaman NON-GAMEPLAY (fallback prosedural) ──
     Hanya aktif jika Click.mp3 belum selesai di-load.               */
  function _playMenuClickFallback() {
    if (!ctx || !ctxReady) return;
    const t = ctx.currentTime;
    const o = ctx.createOscillator();
    const g = ctx.createGain();
    o.type = 'sine';
    o.frequency.setValueAtTime(1200, t);
    o.frequency.exponentialRampToValueAtTime(600, t + 0.06);
    g.gain.setValueAtTime(0.18, t);
    g.gain.exponentialRampToValueAtTime(0.001, t + 0.07);
    o.connect(g); g.connect(ctx.destination);
    o.start(t); o.stop(t + 0.08);
  }

  function _getClickVol() {
    if (localStorage.getItem('gbk_click_mute') === 'true') return 0;
    const v = parseFloat(localStorage.getItem('gbk_click_vol'));
    return isNaN(v) ? 0.85 : Math.max(0, Math.min(1, v));
  }

  function playClick() {
    if (!ctx || !ctxReady) return;
    const vol = _getClickVol();
    if (vol <= 0) return; // muted via settings
    if (_isGameplay) {
      // Halaman gameplay: selalu pakai suara techy prosedural
      _playGameplayClick();
    } else if (clickBuffer) {
      // Halaman lain: pakai Click.mp3
      const src = ctx.createBufferSource();
      src.buffer = clickBuffer;
      const g = ctx.createGain();
      g.gain.value = vol;
      src.connect(g); g.connect(ctx.destination);
      src.start(0);
    } else {
      // Fallback jika Click.mp3 belum load
      _playMenuClickFallback();
    }
  }

  async function playClickAsync() {
    await resumeContext();
    if (!_isGameplay) await loadClickSfx(); // gameplay tidak perlu load Click.mp3
    playClick();
  }

  /* ── BGM fade-out smooth sebelum navigate ── */
  function _fadeOutBGM(durationMs) {
    if (!gainNode || !isPlaying) return;
    const dur = durationMs / 1000;
    gainNode.gain.cancelScheduledValues(ctx.currentTime);
    gainNode.gain.setValueAtTime(gainNode.gain.value, ctx.currentTime);
    gainNode.gain.linearRampToValueAtTime(0, ctx.currentTime + dur);
  }

  /* ── Click selector ── */
  const CLICK_SELECTOR = [
    'a[href]', 'button', '[onclick]', '[role="button"]',
    'input[type="submit"]', 'input[type="button"]',
    '.mbtn', '.pinfo', '.btn-back', '.btn-out', '.nav-btn',
    '.xbtn', '.lb-cta-panel', '.choice-btn',
    '.lb2-close-btn', '.btn-close', '.modal-overlay',
    '.mode-btn', '.av-opt', '.tab-btn', '.btn-save',
  ].join(',');

  document.addEventListener('click', (e) => {
    const el = e.target.closest(CLICK_SELECTOR);
    if (!el) return;

    const isNavLink = el.tagName === 'A'
      && el.href
      && !el.href.startsWith('javascript')
      && !el.href.endsWith('#')
      && el.getAttribute('href') !== '#'
      && !el.target;

    if (isNavLink) {
      e.preventDefault();
      e.stopPropagation();
      const dest = el.href;

      // 1. Mulai fade-out visual (body opacity → 0)
      document.body.classList.add('page-exit');

      // 2. Fade-out BGM + play click SFX secara bersamaan
      resumeContext().then(() => {
        loadClickSfx().then(() => playClick()).catch(() => {});
        _fadeOutBGM(TRANSITION_MS);
        // 3. Navigate setelah transisi selesai
        setTimeout(() => { window.location.href = dest; }, TRANSITION_MS);
      }).catch(() => {
        // Gagal resume context — langsung navigate
        window.location.href = dest;
      });
    } else {
      playClickAsync();
    }
  }, { capture: true });

  /* Pre-load SFX saat halaman load */
  window.addEventListener('load', async () => {
    try {
      ensureContext();
      if (ctx.state !== 'suspended') { ctxReady = true; await loadClickSfx(); }
    } catch (_) {}
  });

  /* ── BGM ── */
  let gainNode    = null;
  let sourceNode  = null;
  let audioBuffer = null;
  let startedAt   = 0;
  let offset      = 0;
  let isPlaying   = false;
  let isLoaded    = false;
  let enabled     = localStorage.getItem(KEY_ENABLED) !== 'false';
  let volume      = parseFloat(localStorage.getItem(KEY_VOLUME) ?? '0.4');

  function getRestoredOffset(duration) {
    const saved   = parseFloat(localStorage.getItem(KEY_POSITION) ?? '0');
    const savedAt = parseFloat(localStorage.getItem(KEY_SAVED_AT) ?? '0');
    if (!savedAt || isNaN(saved)) return 0;
    const elapsed = (Date.now() - savedAt) / 1000;
    return duration > 0 ? (saved + elapsed) % duration : 0;
  }

  function getCurrentPosition() {
    if (!audioBuffer || !isPlaying) return offset;
    return (offset + (ctx.currentTime - startedAt)) % audioBuffer.duration;
  }

  function savePosition() {
    const pos = getCurrentPosition();
    if (pos <= 0 && !isPlaying) return;
    localStorage.setItem(KEY_POSITION, pos);
    localStorage.setItem(KEY_SAVED_AT, Date.now());
  }

  function ensureGain() {
    if (gainNode) return;
    ensureContext();
    gainNode = ctx.createGain();
    gainNode.gain.value = volume;
    gainNode.connect(ctx.destination);
  }

  async function loadBGM() {
    if (isLoaded) return;
    ensureGain();
    try {
      const res    = await fetch(BGM_SRC);
      const arrBuf = await res.arrayBuffer();
      audioBuffer  = await ctx.decodeAudioData(arrBuf);
      isLoaded     = true;
      // Jangan mulai putar BGM di halaman gameplay — biarkan LuckySound yang pegang audio
      if (enabled && !_isGameplay) startPlayback(getRestoredOffset(audioBuffer.duration));
    } catch (e) { console.warn('[BGM] Gagal load:', e); }
  }

  function startPlayback(fromOffset) {
    // Di halaman gameplay: BGM tidak diputar agar tidak bentrok dengan fight music
    if (!ctx || !audioBuffer || !enabled || _isGameplay) return;
    stopPlayback();
    if (ctx.state === 'suspended') ctx.resume();
    ensureGain();
    const src     = ctx.createBufferSource();
    src.buffer    = audioBuffer;
    src.loop      = true;
    src.loopStart = 0;
    src.loopEnd   = audioBuffer.duration;
    src.connect(gainNode);
    const safe = ((fromOffset % audioBuffer.duration) + audioBuffer.duration) % audioBuffer.duration;
    src.start(0, safe);
    sourceNode = src; startedAt = ctx.currentTime; offset = safe; isPlaying = true;

    // Fade-in smooth dari 0 ke volume target (hindari bunyi mendadak di halaman baru)
    gainNode.gain.cancelScheduledValues(ctx.currentTime);
    gainNode.gain.setValueAtTime(0, ctx.currentTime);
    gainNode.gain.linearRampToValueAtTime(volume, ctx.currentTime + 0.4);
  }

  function stopPlayback() {
    if (!sourceNode) return;
    try { sourceNode.stop(); } catch (_) {}
    sourceNode.disconnect(); sourceNode = null; isPlaying = false;
  }

  async function initBGM() {
    await resumeContext(); ensureGain(); await loadBGM();
  }

  window.addEventListener('load', async () => {
    try {
      ensureContext();
      ensureGain();
      if (ctx.state !== 'suspended') await loadBGM();
      // Di gameplay: BGM tidak diputar, tapi posisi tetap dihitung
      // agar saat balik ke menu, musik lanjut dari titik yang benar
      if (_isGameplay) savePosition();
    } catch (_) {}
  });

  let unlocked = false;
  document.addEventListener('click', async () => {
    if (unlocked) return; unlocked = true;
    await resumeContext();
    await Promise.all([loadClickSfx(), loadBGM()]);
  }, { capture: true, once: true });

  window.addEventListener('pagehide', savePosition);
  // beforeunload: hanya untuk browser yang tidak support pagehide
  window.addEventListener('beforeunload', () => {
    // Jangan suspend ctx di sini — biarkan fade-out dari navigasi selesai dulu
    savePosition();
  });

  document.addEventListener('visibilitychange', () => {
    if (!ctx) return;
    if (document.hidden) { savePosition(); ctx.suspend(); }
    else if (enabled)    { ctx.resume(); }
  });

  setInterval(() => { if (isPlaying) savePosition(); }, 1000);

  /* ── UI Kontrol BGM ── */
  // Widget BGM dihapus — kontrol suara dipusatkan di Settings Panel (main_menu.php)
  // Storage listener tetap aktif agar volume BGM merespons perubahan dari settings
  window.addEventListener('storage', (e) => {
    if (e.key === KEY_ENABLED) {
      enabled = e.newValue !== 'false';
      if (enabled && isLoaded) startPlayback(getCurrentPosition());
      else if (!enabled) { savePosition(); stopPlayback(); }
    }
    if (e.key === KEY_VOLUME && gainNode) {
      volume = parseFloat(e.newValue ?? '0.4');
      gainNode.gain.value = volume;
    }
  });

})();