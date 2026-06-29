<?php
// ══════════════════════════════════════════════
//  Api/collection_api.php
//  Endpoint AJAX: Ambil usage kartu + senjata gabungan AI & PvP
//  Method : GET  |  Auth: session  |  Returns: JSON
//  DIPANGGIL OLEH: collection.php (fetch GET ke '../Api/collection_api.php')
// ══════════════════════════════════════════════
declare(strict_types=1);
ob_start();
error_reporting(0);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

session_start();

// ── Require database helper ──
// Api/ dan Backend/ sejajar di dalam project root
require_once __DIR__ . '/../Backend/database.php';

// ── Auth guard ─────────────────────────────────
if (!isset($_SESSION['player_id'])) {
    ob_clean(); http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$player_id = $_SESSION['player_id'];

try {
    $db = getDB();

    // ── Pastikan tabel ada (auto-create) ─────────
    $db->exec("
        CREATE TABLE IF NOT EXISTS player_card_usage (
            id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            player_id VARCHAR(20)    NOT NULL,
            card_id   VARCHAR(40)    NOT NULL,
            use_count INT UNSIGNED   NOT NULL DEFAULT 1,
            last_used DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_player_card (player_id, card_id),
            INDEX idx_pcu_player (player_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS ai_card_usage (
            id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            player_id VARCHAR(20)    NOT NULL,
            card_id   VARCHAR(40)    NOT NULL,
            use_count INT UNSIGNED   NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_ai_player_card (player_id, card_id),
            INDEX idx_acu_player (player_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // ── Ambil PvP card usage ─────────────────────
    $stmtPvp = $db->prepare(
        "SELECT card_id, use_count FROM player_card_usage WHERE player_id = ?"
    );
    $stmtPvp->execute([$player_id]);
    $pvpUsage = [];
    foreach ($stmtPvp->fetchAll() as $row) {
        $pvpUsage[$row['card_id']] = (int)$row['use_count'];
    }

    // ── Ambil AI card usage ──────────────────────
    $stmtAi = $db->prepare(
        "SELECT card_id, use_count FROM ai_card_usage WHERE player_id = ?"
    );
    $stmtAi->execute([$player_id]);
    $aiUsage = [];
    foreach ($stmtAi->fetchAll() as $row) {
        $aiUsage[$row['card_id']] = (int)$row['use_count'];
    }

    // ── Gabung PvP + AI ──────────────────────────
    $allIds   = array_unique(array_merge(array_keys($pvpUsage), array_keys($aiUsage)));
    $combined = [];
    foreach ($allIds as $id) {
        $combined[$id] = ($pvpUsage[$id] ?? 0) + ($aiUsage[$id] ?? 0);
    }

    // ── Senjata: total_rock/paper/scissors dari players ─
    $stmtW = $db->prepare("
        SELECT
            COALESCE(total_rock, 0)     AS total_rock,
            COALESCE(total_paper, 0)    AS total_paper,
            COALESCE(total_scissors, 0) AS total_scissors
        FROM players WHERE id = ? LIMIT 1
    ");
    $stmtW->execute([$player_id]);
    $w = $stmtW->fetch() ?: ['total_rock' => 0, 'total_paper' => 0, 'total_scissors' => 0];

    // Touch last_seen
    if (function_exists('touchPlayerLastSeen')) {
        touchPlayerLastSeen($player_id);
    } else {
        try {
            $db->prepare("UPDATE players SET last_seen = NOW() WHERE id = ?")
               ->execute([$player_id]);
        } catch (Throwable) {}
    }

    ob_clean();
    echo json_encode([
        'success'        => true,
        'card_usage'     => $combined,
        'pvp_usage'      => $pvpUsage,
        'ai_usage'       => $aiUsage,
        'total_rock'     => (int)$w['total_rock'],
        'total_paper'    => (int)$w['total_paper'],
        'total_scissors' => (int)$w['total_scissors'],
        'ts'             => time(),
    ]);

} catch (Throwable $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}