<?php
// ══════════════════════════════════════════════
//  DATABASE CONNECTION & HELPER FUNCTIONS
//  Lucky Battle — Batu Gunting Kertas
// ══════════════════════════════════════════════

define('DB_HOST',    'localhost');
define('DB_NAME',    'lucky_battle');
define('DB_USER',    'root');
define('DB_PASS',    '');            // Ganti sesuai password MySQL kamu
define('DB_CHARSET', 'utf8mb4');

// ──────────────────────────────────────────────
//  KONEKSI PDO (Singleton)
// ──────────────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            $msg = 'Database connection failed: ' . $e->getMessage();
            if (php_sapi_name() === 'cli') {
                // Dipanggil dari WebSocket server (CLI) — lempar exception agar bisa di-catch
                throw new \RuntimeException($msg);
            }
            http_response_code(500);
            die(json_encode(['error' => $msg]));
        }
    }
    return $pdo;
}

// ══════════════════════════════════════════════
//  PLAYER — CRUD
// ══════════════════════════════════════════════

/**
 * Ambil data player berdasarkan ID
 */
function getPlayerData(string $player_id): ?array {
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM players WHERE id = ? LIMIT 1");
    $stmt->execute([$player_id]);
    return $stmt->fetch() ?: null;
}

/**
 * Ambil data player berdasarkan username
 */
function getPlayerByUsername(string $username): ?array {
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM players WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    return $stmt->fetch() ?: null;
}

/**
 * Update stats player setelah match PvP selesai
 * $result: 'win' | 'loss' | 'draw'
 */
function updatePlayerStats(string $player_id, string $result): void {
    $db  = getDB();
    $col = match($result) {
        'win'  => 'wins',
        'loss' => 'losses',
        'draw' => 'draws',
        default => throw new \InvalidArgumentException("Invalid result: $result"),
    };
    $ratingDelta = match($result) {
        'win'  => +25,
        'loss' => -20,
        'draw' => 0,
    };

    // Update streak
    if ($result === 'win') {
        $streakSQL = "current_win_streak = current_win_streak + 1,
                      max_win_streak = GREATEST(max_win_streak, current_win_streak + 1),";
    } elseif ($result === 'loss') {
        $streakSQL = "current_win_streak = 0,";
    } else {
        $streakSQL = "";
    }

    $stmt = $db->prepare("
        UPDATE players
        SET {$col} = {$col} + 1,
            {$streakSQL}
            rating     = GREATEST(0, rating + ?),
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$ratingDelta, $player_id]);
}

/**
 * Update stats player setelah match VS AI
 * Termasuk update rating: +25 menang, -20 kalah, 0 draw
 */
function updateAIStats(string $player_id, string $result, array $choiceCounts = []): void {
    $db  = getDB();
    $col = match($result) {
        'won'  => 'ai_wins',
        'lost' => 'ai_losses',
        'draw' => 'ai_draws',
        default => throw new \InvalidArgumentException("Invalid result: $result"),
    };

    // ── Rating delta KHUSUS VS AI (kolom ai_rating, TIDAK menyentuh rating PvP) ──
    $ratingDelta = match($result) {
        'won'  => +25,
        'lost' => -20,
        'draw' => 0,
    };

    $rock     = (int)($choiceCounts['rock']     ?? 0);
    $paper    = (int)($choiceCounts['paper']    ?? 0);
    $scissors = (int)($choiceCounts['scissors'] ?? 0);

    if ($result === 'won') {
        $streakSQL = "current_win_streak = current_win_streak + 1,
                      max_win_streak = GREATEST(max_win_streak, current_win_streak + 1),";
    } elseif ($result === 'lost') {
        $streakSQL = "current_win_streak = 0,";
    } else {
        $streakSQL = "";
    }

    // UPDATE ai_rating & peak_ai_rating — TIDAK menyentuh kolom rating PvP
    $stmt = $db->prepare("
        UPDATE players
        SET {$col}           = {$col} + 1,
            {$streakSQL}
            total_rock       = total_rock + ?,
            total_paper      = total_paper + ?,
            total_scissors   = total_scissors + ?,
            ai_rating        = GREATEST(0, COALESCE(ai_rating, 1000) + ?),
            peak_ai_rating   = GREATEST(COALESCE(peak_ai_rating, 0), GREATEST(0, COALESCE(ai_rating, 1000) + ?)),
            updated_at       = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$rock, $paper, $scissors, $ratingDelta, $ratingDelta, $player_id]);
}


function saveMatchHistory(array $data): void {
    $db   = getDB();
    $stmt = $db->prepare("
        INSERT INTO match_history
            (player1_id, player2_id, winner_id,
             player1_round_wins, player2_round_wins,
             rounds_data, duration_sec,
             player1_rating_before, player2_rating_before,
             player1_rating_after,  player2_rating_after,
             played_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $data['player1_id'],
        $data['player2_id'],
        $data['winner_id'] ?? null,
        $data['player1_round_wins'] ?? 0,
        $data['player2_round_wins'] ?? 0,
        json_encode($data['rounds'] ?? []),
        $data['duration_sec']        ?? 0,
        $data['player1_rating_before'] ?? null,
        $data['player2_rating_before'] ?? null,
        $data['player1_rating_after']  ?? null,
        $data['player2_rating_after']  ?? null,
    ]);
}

/**
 * Simpan history match VS AI
 */
function saveAIMatchHistory(array $data): void {
    $db   = getDB();
    $stmt = $db->prepare("
        INSERT INTO ai_match_history
            (player_id, result,
             player_round_wins, ai_round_wins,
             choice_rock_count, choice_paper_count, choice_scissors_count,
             duration_sec, played_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $data['player_id'],
        $data['result'],
        $data['player_round_wins'] ?? 0,
        $data['ai_round_wins']     ?? 0,
        $data['choice_rock']       ?? 0,
        $data['choice_paper']      ?? 0,
        $data['choice_scissors']   ?? 0,
        $data['duration_sec']      ?? 0,
    ]);
}

/**
 * Simpan / update pemakaian kartu VS AI ke tabel ai_card_usage
 * Dipanggil oleh ai_save.php setelah setiap match VS AI selesai
 * $cardsUsed = ['shield1' => 2, 'god_attack1' => 1, ...]
 */
function saveAICardUsage(string $player_id, array $cardsUsed): void {
    if (empty($cardsUsed)) return;
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO ai_card_usage (player_id, card_id, use_count)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE use_count = use_count + VALUES(use_count)
    ");
    foreach ($cardsUsed as $cardId => $count) {
        $stmt->execute([$player_id, $cardId, max(1, (int)$count)]);
    }
}


/**
 * Ambil history PvP match untuk player tertentu (10 terakhir)
 */
function getPlayerMatchHistory(string $player_id, int $limit = 10): array {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT
            mh.id,
            mh.player1_id,
            mh.player2_id,
            mh.winner_id,
            mh.player1_round_wins,
            mh.player2_round_wins,
            mh.duration_sec,
            mh.played_at,
            mh.player1_rating_before,
            mh.player2_rating_before,
            mh.player1_rating_after,
            mh.player2_rating_after,
            p1.username AS player1_name,
            p2.username AS player2_name,
            CASE
                WHEN mh.winner_id = ? THEN 'won'
                WHEN mh.winner_id IS NULL THEN 'draw'
                ELSE 'lost'
            END AS result
        FROM match_history mh
        JOIN players p1 ON p1.id = mh.player1_id
        JOIN players p2 ON p2.id = mh.player2_id
        WHERE mh.player1_id = ? OR mh.player2_id = ?
        ORDER BY mh.played_at DESC
        LIMIT ?
    ");
    $stmt->execute([$player_id, $player_id, $player_id, $limit]);
    return $stmt->fetchAll();
}

/**
 * Ambil history VS AI untuk player tertentu (10 terakhir)
 */
function getPlayerAIHistory(string $player_id, int $limit = 10): array {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT *
        FROM ai_match_history
        WHERE player_id = ?
        ORDER BY played_at DESC
        LIMIT ?
    ");
    $stmt->execute([$player_id, $limit]);
    return $stmt->fetchAll();
}

// ══════════════════════════════════════════════
//  LEADERBOARD & RANK
// ══════════════════════════════════════════════

/**
 * Ambil leaderboard (top N berdasarkan rating)
 */
function getLeaderboard(int $limit = 10): array {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT id, username, avatar, wins, losses, draws, rating,
               current_win_streak,
               RANK() OVER (ORDER BY rating DESC) AS `rank`
        FROM players
        ORDER BY rating DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

/**
 * Ambil rank player tertentu (PvP — berdasarkan rating umum)
 */
function getPlayerRank(string $player_id): int {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT COUNT(*) + 1 AS player_rank
        FROM players
        WHERE rating > (SELECT rating FROM players WHERE id = ?)
    ");
    $stmt->execute([$player_id]);
    $row = $stmt->fetch();
    return (int)($row['player_rank'] ?? 999);
}

// ══════════════════════════════════════════════
//  LEADERBOARD & RANK — KHUSUS VS AI
// ══════════════════════════════════════════════

/**
 * Ambil leaderboard khusus VS AI (top N berdasarkan ai_rating).
 * ai_rating dihitung dari ai_wins*25 - ai_losses*20, min 0.
 * Kolom ini di-SELECT as "rating" agar kompatibel dengan tampilan lobby.php.
 */
function getAILeaderboard(int $limit = 10): array {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT
            id,
            username,
            avatar,
            ai_wins    AS wins,
            ai_losses  AS losses,
            ai_draws   AS draws,
            current_win_streak,
            COALESCE(ai_rating, 1000) AS rating,
            RANK() OVER (
                ORDER BY COALESCE(ai_rating, 1000) DESC,
                         ai_wins DESC
            ) AS `rank`
        FROM players
        ORDER BY COALESCE(ai_rating, 1000) DESC,
                 ai_wins DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

/**
 * Ambil rank VS AI player tertentu (berdasarkan ai_rating score).
 */
function getPlayerAIRank(string $player_id): int {
    $db   = getDB();
    $me = $db->prepare("
        SELECT COALESCE(ai_rating, 1000) AS ai_score
        FROM players WHERE id = ? LIMIT 1
    ");
    $me->execute([$player_id]);
    $row = $me->fetch();
    if (!$row) return 999;

    $my_score = (float)$row['ai_score'];

    $stmt = $db->prepare("
        SELECT COUNT(*) + 1 AS player_rank
        FROM players
        WHERE COALESCE(ai_rating, 1000) > ?
    ");
    $stmt->execute([$my_score]);
    $row2 = $stmt->fetch();
    return (int)($row2['player_rank'] ?? 999);
}

/**
 * Ambil ai_rating (score) player untuk ditampilkan di lobby.
 * Score = max(0, ai_wins*25 - ai_losses*20)
 */
function getPlayerAIScore(string $player_id): int {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT COALESCE(ai_rating, 1000) AS ai_score
        FROM players WHERE id = ? LIMIT 1
    ");
    $stmt->execute([$player_id]);
    $row = $stmt->fetch();
    return (int)($row['ai_score'] ?? 0);
}

/**
 * Ambil statistik lengkap player dari view
 */
function getPlayerFullStats(string $player_id): ?array {
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM player_stats_view WHERE id = ? LIMIT 1");
    $stmt->execute([$player_id]);
    return $stmt->fetch() ?: null;
}

// ══════════════════════════════════════════════
//  ACHIEVEMENTS
// ══════════════════════════════════════════════

/**
 * Cek & unlock achievement untuk player
 */
function checkAndUnlockAchievement(string $player_id, string $achievement_id): bool {
    $db = getDB();

    // Cek sudah punya belum
    $chk = $db->prepare("SELECT 1 FROM player_achievements WHERE player_id=? AND achievement_id=?");
    $chk->execute([$player_id, $achievement_id]);
    if ($chk->fetch()) return false; // sudah punya

    // Unlock
    $ins = $db->prepare("
        INSERT IGNORE INTO player_achievements (player_id, achievement_id, unlocked_at)
        VALUES (?, ?, NOW())
    ");
    $ins->execute([$player_id, $achievement_id]);
    return true;
}

/**
 * Ambil semua achievement player
 */
function getPlayerAchievements(string $player_id): array {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT a.*, pa.unlocked_at
        FROM achievements a
        JOIN player_achievements pa ON pa.achievement_id = a.id
        WHERE pa.player_id = ?
        ORDER BY pa.unlocked_at DESC
    ");
    $stmt->execute([$player_id]);
    return $stmt->fetchAll();
}

/**
 * Helper: hitung stars berdasarkan rating
 */
function getRatingStars(int $rating): int {
    return match(true) {
        $rating >= 1400 => 5,
        $rating >= 1250 => 4,
        $rating >= 1100 => 3,
        $rating >= 950  => 2,
        default         => 1,
    };
}