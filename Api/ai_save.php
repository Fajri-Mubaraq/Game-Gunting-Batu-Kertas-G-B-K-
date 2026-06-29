<?php
// ══════════════════════════════════════════════════════════
//  Api/ai_save.php — Endpoint simpan hasil match VS AI
//  DIPANGGIL OLEH: gameplay.php (fetch POST ke '../Api/ai_save.php')
//
//  Menyimpan ke:
//    - tabel players  (ai_wins/losses/draws, rating, streak, total_rock/paper/scissors)
//    - tabel ai_match_history (rekap per match)
//    - tabel ai_card_usage (kartu yang dipakai)
// ══════════════════════════════════════════════════════════
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Access-Control-Allow-Methods: POST');

// ── Auth guard ──
if (!isset($_SESSION['player_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// ── Require database helper ──
// Api/ berada satu level di dalam project root, Backend/ sejajar dengan Api/
require_once __DIR__ . '/../Backend/database.php';

$player_id = $_SESSION['player_id'];

// ── Parse JSON body ──
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!$body || !isset($body['result'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing result field']);
    exit;
}

$result = $body['result']; // 'won' | 'lost' | 'draw'

if (!in_array($result, ['won', 'lost', 'draw'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid result value: ' . htmlspecialchars($result)]);
    exit;
}

$playerRoundWins = (int)($body['player_round_wins'] ?? 0);
$aiRoundWins     = (int)($body['ai_round_wins']     ?? 0);
$durationSec     = (int)($body['duration_sec']      ?? 0);

$choiceCounts = [
    'rock'     => (int)($body['choice_rock']     ?? 0),
    'paper'    => (int)($body['choice_paper']    ?? 0),
    'scissors' => (int)($body['choice_scissors'] ?? 0),
];

// cards_used: {card_id: count} — kartu yang dipakai player selama match VS AI
$cardsUsed = [];
if (!empty($body['cards_used']) && is_array($body['cards_used'])) {
    foreach ($body['cards_used'] as $cardId => $count) {
        $cardId = preg_replace('/[^a-z0-9_]/', '', strtolower((string)$cardId));
        $count  = max(1, (int)$count);
        if ($cardId !== '') $cardsUsed[$cardId] = $count;
    }
}

try {
    // ── 1. Update stats player di tabel players ──
    updateAIStats($player_id, $result, $choiceCounts);

    // ── 2. Simpan rekap match ke tabel ai_match_history ──
    saveAIMatchHistory([
        'player_id'         => $player_id,
        'result'            => $result,
        'player_round_wins' => $playerRoundWins,
        'ai_round_wins'     => $aiRoundWins,
        'choice_rock'       => $choiceCounts['rock'],
        'choice_paper'      => $choiceCounts['paper'],
        'choice_scissors'   => $choiceCounts['scissors'],
        'duration_sec'      => $durationSec,
    ]);

    // ── 3. Simpan pemakaian kartu VS AI ke tabel ai_card_usage ──
    if (!empty($cardsUsed)) {
        saveAICardUsage($player_id, $cardsUsed);
    }

    // ── 4. Ambil data terbaru untuk dikirim balik ke client ──
    $player  = getPlayerData($player_id);
    $aiRank  = getPlayerAIRank($player_id);
    $aiScore = getPlayerAIScore($player_id);

    echo json_encode([
        'ok'                 => true,
        'result'             => $result,
        'ai_wins'            => (int)($player['ai_wins']            ?? 0),
        'ai_losses'          => (int)($player['ai_losses']          ?? 0),
        'ai_draws'           => (int)($player['ai_draws']           ?? 0),
        'ai_score'           => $aiScore,
        'ai_rank'            => $aiRank,
        'rating'             => (int)($player['rating']             ?? 0),
        'current_win_streak' => (int)($player['current_win_streak'] ?? 0),
        'max_win_streak'     => (int)($player['max_win_streak']     ?? 0),
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB error: ' . $e->getMessage()]);
}