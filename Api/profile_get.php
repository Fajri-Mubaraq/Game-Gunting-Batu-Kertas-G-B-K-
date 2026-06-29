<?php
// ══════════════════════════════════════════════
//  Api/profile_get.php
//  Endpoint AJAX: Ambil data profil player (JSON)
//  Method : GET
//  Auth   : session
// ══════════════════════════════════════════════
declare(strict_types=1);
ob_start();
error_reporting(0);

header('Content-Type: application/json; charset=utf-8');

session_start();
// Path: Api/ → naik 1 level → lucky battle/ → masuk Backend/
require_once __DIR__ . '/../Backend/database.php';

if (!isset($_SESSION['player_id'])) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$player_id = $_SESSION['player_id'];

try {
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM players WHERE id = ? LIMIT 1");
    $stmt->execute([$player_id]);
    $p = $stmt->fetch();

    if (!$p) {
        ob_clean();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Player tidak ditemukan.']);
        exit;
    }

    $rank = getPlayerRank($player_id);

    $AVATARS = ['⚔️','🛡️','🔥','💎','🌪️','👑','🤖','🐉','⚡','🌙','🗡️','🔮'];
    $avatar_choice = (int)($p['avatar_choice'] ?? 0);

    ob_clean();
    echo json_encode([
        'success'            => true,
        'id'                 => $p['id'],
        'username'           => $p['username'],
        'display_name'       => $p['display_name'] ?? $p['username'],
        'avatar'             => $p['avatar'] ?? ($AVATARS[$avatar_choice] ?? '⚔️'),
        'avatar_choice'      => $avatar_choice,
        'bio'                => $p['bio'] ?? '',
        'rating'             => (int)$p['rating'],
        'wins'               => (int)$p['wins'],
        'losses'             => (int)$p['losses'],
        'draws'              => (int)$p['draws'],
        'ai_wins'            => (int)($p['ai_wins'] ?? 0),
        'ai_losses'          => (int)($p['ai_losses'] ?? 0),
        'ai_draws'           => (int)($p['ai_draws'] ?? 0),
        'total_rock'         => (int)($p['total_rock'] ?? 0),
        'total_paper'        => (int)($p['total_paper'] ?? 0),
        'total_scissors'     => (int)($p['total_scissors'] ?? 0),
        'current_win_streak' => (int)($p['current_win_streak'] ?? 0),
        'max_win_streak'     => (int)($p['max_win_streak'] ?? 0),
        'username_changes'   => (int)($p['username_changes'] ?? 0),
        'rank'               => $rank,
        'created_at'         => $p['created_at'],
        'last_seen'          => $p['last_seen'],
    ]);

} catch (Throwable $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}