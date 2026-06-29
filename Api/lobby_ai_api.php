<?php
// ══════════════════════════════════════════════════════════
//  Api/lobby_ai_api.php — Real-time polling endpoint untuk lobby VS AI
//  Mengembalikan leaderboard terbaru + stats player saat ini
//  DIPANGGIL OLEH: lobby.php setiap 30 detik
// ══════════════════════════════════════════════════════════
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

if (!isset($_SESSION['player_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// ── Require database helper ──
require_once __DIR__ . '/../Backend/database.php';

$player_id = $_SESSION['player_id'];

try {
    // ── Leaderboard top 10 VS AI ──
    $leaderboard = getAILeaderboard(10);

    // ── Stats player sendiri ──
    $my_rank  = getPlayerAIRank($player_id);
    $my_score = getPlayerAIScore($player_id);
    $player   = getPlayerData($player_id);

    $my_wins   = (int)($player['ai_wins']   ?? 0);
    $my_losses = (int)($player['ai_losses'] ?? 0);
    $my_draws  = (int)($player['ai_draws']  ?? 0);

    // ── Format leaderboard untuk JS ──
    $lb = [];
    foreach ($leaderboard as $e) {
        $lb[] = [
            'id'      => $e['id'],
            'username'=> htmlspecialchars($e['username'] ?? ''),
            'avatar'  => htmlspecialchars($e['avatar']   ?? '⚔️'),
            'rating'  => (int)($e['rating']              ?? 0),
            'wins'    => (int)($e['wins']                ?? 0),
            'losses'  => (int)($e['losses']              ?? 0),
            'draws'   => (int)($e['draws']               ?? 0),
            'streak'  => (int)($e['current_win_streak']  ?? 0),
            'rank'    => (int)($e['rank']                ?? 0),
            'isMe'    => ($e['id'] === $player_id),
        ];
    }

    // Touch last_seen
    touchPlayerLastSeen($player_id);

    echo json_encode([
        'leaderboard' => $lb,
        'my_rank'     => $my_rank,
        'my_score'    => $my_score,
        'my_wins'     => $my_wins,
        'my_losses'   => $my_losses,
        'my_draws'    => $my_draws,
        'ts'          => time(),
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}