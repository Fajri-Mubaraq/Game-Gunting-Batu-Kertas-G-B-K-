<?php
// ══════════════════════════════════════════════
//  Api/statistik_api.php — Real-time polling endpoint
//  DIPANGGIL OLEH: statistik.php setiap 15 detik
//  Mengembalikan JSON berisi stats terkini player
// ══════════════════════════════════════════════
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
    $db = getDB();

    // Ambil data player terbaru dari DB
    $stmt = $db->prepare("
        SELECT
            id, username, display_name, avatar, avatar_choice,
            wins, losses, draws,
            ai_wins, ai_losses, ai_draws,
            rating,
            COALESCE(ai_rating, 1000)    AS ai_rating,
            COALESCE(peak_rating, 0)     AS peak_rating,
            COALESCE(peak_ai_rating, 0)  AS peak_ai_rating,
            current_win_streak, max_win_streak,
            total_rock, total_paper, total_scissors,
            last_seen, updated_at
        FROM players
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$player_id]);
    $p = $stmt->fetch();

    if (!$p) {
        echo json_encode(['error' => 'Player not found']);
        exit;
    }

    // Hitung rank PvP dan rank VS AI
    $rank    = getPlayerRank($player_id);
    $ai_rank = getPlayerAIRank($player_id);

    // Touch last_seen
    touchPlayerLastSeen($player_id);

    // Response
    echo json_encode([
        'rating'              => (int)$p['rating'],
        'wins'                => (int)$p['wins'],
        'losses'              => (int)$p['losses'],
        'draws'               => (int)$p['draws'],
        'ai_wins'             => (int)$p['ai_wins'],
        'ai_losses'           => (int)$p['ai_losses'],
        'ai_draws'            => (int)$p['ai_draws'],
        'ai_rating'           => (int)$p['ai_rating'],
        'peak_ai_rating'      => (int)$p['peak_ai_rating'],
        'peak_rating'         => (int)$p['peak_rating'],
        'current_win_streak'  => (int)$p['current_win_streak'],
        'max_win_streak'      => (int)$p['max_win_streak'],
        'total_rock'          => (int)$p['total_rock'],
        'total_paper'         => (int)$p['total_paper'],
        'total_scissors'      => (int)$p['total_scissors'],
        'rank'                => $rank,
        'ai_rank'             => $ai_rank,
        'ts'                  => time(),
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}