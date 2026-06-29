<?php
// ══════════════════════════════════════════════
//  Api/avatar_mission_get.php
//  Endpoint AJAX: Status misi avatar player
//  Method : GET
//  Auth   : session
//  Returns: JSON { success, missions[], unlocked_count }
// ══════════════════════════════════════════════
declare(strict_types=1);
ob_start();
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

session_start();
require_once __DIR__ . '/../Backend/database.php';

if (!isset($_SESSION['player_id'])) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$player_id = $_SESSION['player_id'];

try {
    $db = getDB();

    // ── Pastikan tabel avatar_unlocks ada ──
    $db->exec("CREATE TABLE IF NOT EXISTS avatar_unlocks (
        id            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
        player_id     VARCHAR(20)      NOT NULL,
        avatar_index  TINYINT UNSIGNED NOT NULL COMMENT 'Index avatar 0-11',
        unlocked_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE  KEY uq_av_player_index (player_id, avatar_index),
        INDEX        idx_av_player     (player_id),
        CONSTRAINT   fk_av_player_mu
            FOREIGN KEY (player_id) REFERENCES players (id)
            ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── Ambil data player ──
    $stmt = $db->prepare("SELECT * FROM players WHERE id = ? LIMIT 1");
    $stmt->execute([$player_id]);
    $p = $stmt->fetch();

    if (!$p) {
        ob_clean();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Player tidak ditemukan']);
        exit;
    }

    // ── Hitung nilai stats yang dibutuhkan ──
    $wins       = (int)($p['wins']           ?? 0);
    $losses     = (int)($p['losses']         ?? 0);
    $draws      = (int)($p['draws']          ?? 0);
    $ai_wins    = (int)($p['ai_wins']        ?? 0);
    $streak     = (int)($p['max_win_streak'] ?? 0);
    $rating     = (int)($p['rating']         ?? 1000);
    $has_bio    = !empty($p['bio']);
    $total_wins = $wins + $ai_wins;
    $total_pvp  = $wins + $losses + $draws;

    // ── Definisi misi (harus sama persis dengan profile.php & server.php) ──
    $AVATARS = ['⚔️','🛡️','🔥','💎','🌪️','👑','🤖','🐉','⚡','🌙','🗡️','🔮'];

    $MISSION_DEFS = [
        0  => ['label' => 'Default',       'desc' => 'Avatar awal, selalu terbuka',               'cond' => true,              'cur' => 1,                        'max' => 1],
        1  => ['label' => '5 Menang',      'desc' => 'Menangkan 5 pertandingan (PvP atau VS AI)',  'cond' => $total_wins >= 5,  'cur' => min($total_wins, 5),      'max' => 5],
        2  => ['label' => '10 Menang',     'desc' => 'Menangkan 10 pertandingan (PvP atau VS AI)', 'cond' => $total_wins >= 10, 'cur' => min($total_wins, 10),     'max' => 10],
        3  => ['label' => '1 Match PvP',   'desc' => 'Mainkan 1 pertandingan PvP',                 'cond' => $total_pvp >= 1,   'cur' => min($total_pvp, 1),       'max' => 1],
        4  => ['label' => '5 Menang VS AI','desc' => 'Kalahkan AI sebanyak 5 kali',                'cond' => $ai_wins >= 5,     'cur' => min($ai_wins, 5),         'max' => 5],
        5  => ['label' => 'Streak 3',      'desc' => 'Raih 3 kemenangan beruntun',                 'cond' => $streak >= 3,      'cur' => min($streak, 3),          'max' => 3],
        6  => ['label' => '10 Menang VS AI','desc'=> 'Kalahkan AI sebanyak 10 kali',               'cond' => $ai_wins >= 10,    'cur' => min($ai_wins, 10),        'max' => 10],
        7  => ['label' => 'Rating 1100',   'desc' => 'Capai rating 1100 atau lebih',               'cond' => $rating >= 1100,   'cur' => min($rating, 1100),       'max' => 1100],
        8  => ['label' => '20 Menang',     'desc' => 'Menangkan 20 pertandingan (PvP atau VS AI)', 'cond' => $total_wins >= 20, 'cur' => min($total_wins, 20),     'max' => 20],
        9  => ['label' => 'Tulis Bio',     'desc' => 'Isi bio profil kamu',                        'cond' => $has_bio,          'cur' => $has_bio ? 1 : 0,         'max' => 1],
        10 => ['label' => 'Streak 5',      'desc' => 'Raih 5 kemenangan beruntun',                 'cond' => $streak >= 5,      'cur' => min($streak, 5),          'max' => 5],
        11 => ['label' => '30 Menang',     'desc' => 'Menangkan 30 pertandingan (PvP atau VS AI)', 'cond' => $total_wins >= 30, 'cur' => min($total_wins, 30),     'max' => 30],
    ];

    // ── Ambil avatar yang sudah di-unlock di DB ──
    $existStmt = $db->prepare("SELECT avatar_index FROM avatar_unlocks WHERE player_id = ?");
    $existStmt->execute([$player_id]);
    $db_unlocked = array_column($existStmt->fetchAll(\PDO::FETCH_ASSOC), 'avatar_index');
    $db_unlocked = array_map('intval', $db_unlocked);

    // ── Auto-unlock & build response ──
    $insertStmt = $db->prepare(
        "INSERT IGNORE INTO avatar_unlocks (player_id, avatar_index) VALUES (?, ?)"
    );

    $missions_out   = [];
    $unlocked_count = 0;
    $newly_unlocked = [];

    foreach ($MISSION_DEFS as $idx => $m) {
        $cond_met = (bool)$m['cond'];

        // Auto-unlock ke DB jika misi terpenuhi tapi belum tercatat
        if ($cond_met && !in_array($idx, $db_unlocked)) {
            $insertStmt->execute([$player_id, $idx]);
            $db_unlocked[] = $idx;
            $newly_unlocked[] = $idx;
        }

        $is_unlocked = $cond_met || in_array($idx, $db_unlocked);
        if ($is_unlocked) $unlocked_count++;

        $pct = $m['max'] > 0 ? round($m['cur'] / $m['max'] * 100) : 100;

        $missions_out[] = [
            'index'    => $idx,
            'emoji'    => $AVATARS[$idx],
            'label'    => $m['label'],
            'desc'     => $m['desc'],
            'unlocked' => $is_unlocked,
            'cur'      => $m['cur'],
            'max'      => $m['max'],
            'pct'      => $pct,
        ];
    }

    ob_clean();
    echo json_encode([
        'success'         => true,
        'missions'        => $missions_out,
        'unlocked_count'  => $unlocked_count,
        'total'           => count($MISSION_DEFS),
        'newly_unlocked'  => $newly_unlocked,
        // Stats untuk update progress bar tanpa reload
        'stats' => [
            'wins'       => $wins,
            'losses'     => $losses,
            'draws'      => $draws,
            'ai_wins'    => $ai_wins,
            'streak'     => $streak,
            'rating'     => $rating,
            'has_bio'    => $has_bio,
            'total_wins' => $total_wins,
            'total_pvp'  => $total_pvp,
        ],
    ]);

} catch (\Throwable $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}