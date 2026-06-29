<?php
// ══════════════════════════════════════════════════════════
//  Api/gameplay_ai_save.php — Endpoint simpan hasil match VS AI
//  Dipanggil oleh gameplay.php saat match selesai
//  Menyimpan ke players (stats + rating) dan ai_match_history
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
require_once __DIR__ . '/../Backend/database.php';

$player_id = $_SESSION['player_id'];

// ── Parse body ──
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
    echo json_encode(['error' => 'Invalid result value: ' . $result]);
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

try {
    // ── 1. Update player stats di tabel players ──
    updateAIStats($player_id, $result, $choiceCounts);

    // ── 2. Simpan ke ai_match_history ──
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

    // ── 3. Ambil data terbaru untuk response ──
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