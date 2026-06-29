<?php
// ══════════════════════════════════════════════
//  Api/profile_update.php
//  Endpoint AJAX: Update avatar, bio, display_name
//  Method : POST
//  Auth   : session
//  Returns: JSON
// ══════════════════════════════════════════════
declare(strict_types=1);

// Tangkap semua output agar error PHP tidak merusak JSON
ob_start();
error_reporting(0);

header('Content-Type: application/json; charset=utf-8');

session_start();
// Path: Api/ → naik 1 level → lucky battle/ → masuk Backend/
require_once __DIR__ . '/../Backend/database.php';

// ── Helpers ──────────────────────────────────
function jsonError(int $code, string $msg): never {
    ob_clean();
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

// ── Auth ──────────────────────────────────────
if (!isset($_SESSION['player_id'])) jsonError(401, 'Belum login.');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError(405, 'Method not allowed.');

$player_id = $_SESSION['player_id'];

try {
    $db = getDB();
} catch (Throwable $e) {
    jsonError(500, 'Koneksi database gagal: ' . $e->getMessage());
}

// ── Auto-migrate: pastikan kolom tersedia ────
$migration_cols = [
    'avatar_choice'    => "ALTER TABLE players ADD COLUMN avatar_choice TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER avatar",
    'bio'              => "ALTER TABLE players ADD COLUMN bio VARCHAR(160) DEFAULT NULL AFTER avatar_choice",
    'display_name'     => "ALTER TABLE players ADD COLUMN display_name VARCHAR(30) DEFAULT NULL AFTER username",
    'username_changes' => "ALTER TABLE players ADD COLUMN username_changes TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER max_win_streak",
];
foreach ($migration_cols as $col => $sql) {
    try {
        $check = $db->query("SHOW COLUMNS FROM players LIKE '$col'")->fetchAll();
        if (empty($check)) $db->exec($sql);
    } catch (Throwable) {}
}

// ── Baca action ───────────────────────────────
$action = trim($_POST['action'] ?? '');

// ══════════════════════════════════════════════
//  ACTION: update_profile (avatar + bio)
// ══════════════════════════════════════════════
if ($action === 'update_profile') {

    $avatar_choice = (int)($_POST['avatar_choice'] ?? 0);
    $avatar_choice = max(0, min($avatar_choice, 11));

    $AVATARS = ['⚔️','🛡️','🔥','💎','🌪️','👑','🤖','🐉','⚡','🌙','🗡️','🔮'];

    // ── Validasi: apakah avatar sudah di-unlock player? ──
    // Auto-create table jika belum ada
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS avatar_unlocks(
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            player_id VARCHAR(20) NOT NULL,
            avatar_index TINYINT UNSIGNED NOT NULL,
            unlocked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            UNIQUE KEY uq_av(player_id,avatar_index),
            INDEX idx_av_p(player_id)
        )ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable) {}

    if ($avatar_choice !== 0) {
        // Cek apakah ada di avatar_unlocks
        try {
            $chkAv = $db->prepare("SELECT 1 FROM avatar_unlocks WHERE player_id=? AND avatar_index=? LIMIT 1");
            $chkAv->execute([$player_id, $avatar_choice]);
            if (!$chkAv->fetch()) {
                jsonError(403, 'Avatar ini belum terbuka. Selesaikan misinya terlebih dahulu!');
            }
        } catch (Throwable $e) {
            jsonError(500, 'Gagal memvalidasi avatar: ' . $e->getMessage());
        }
    }

    $avatar_emoji = $AVATARS[$avatar_choice];

    $bio = trim($_POST['bio'] ?? '');
    $bio = mb_substr($bio, 0, 160, 'UTF-8');
    if ($bio === '') $bio = null;

    try {
        $stmt = $db->prepare("
            UPDATE players
            SET avatar        = ?,
                avatar_choice = ?,
                bio           = ?,
                updated_at    = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$avatar_emoji, $avatar_choice, $bio, $player_id]);
    } catch (Throwable $e) {
        jsonError(500, 'Gagal menyimpan: ' . $e->getMessage());
    }

    // Baca ulang
    try {
        $fresh = $db->prepare("SELECT avatar, avatar_choice, bio, display_name, username FROM players WHERE id = ?");
        $fresh->execute([$player_id]);
        $p = $fresh->fetch();
    } catch (Throwable $e) {
        jsonError(500, 'Gagal membaca data: ' . $e->getMessage());
    }

    ob_clean();
    echo json_encode([
        'success'       => true,
        'avatar'        => $p['avatar'] ?? $avatar_emoji,
        'avatar_choice' => (int)($p['avatar_choice'] ?? $avatar_choice),
        'bio'           => $p['bio'] ?? '',
        'display_name'  => $p['display_name'] ?? $p['username'] ?? '',
    ]);
    exit;
}

// ══════════════════════════════════════════════
//  ACTION: update_display_name
// ══════════════════════════════════════════════
if ($action === 'update_display_name') {

    $new_name = trim($_POST['display_name'] ?? '');

    if ($new_name === '') jsonError(422, 'Nama tidak boleh kosong.');
    if (mb_strlen($new_name, 'UTF-8') < 2) jsonError(422, 'Nama minimal 2 karakter.');
    if (mb_strlen($new_name, 'UTF-8') > 30) jsonError(422, 'Nama maksimal 30 karakter.');
    if (!preg_match('/^[\p{L}\p{N}_. ]+$/u', $new_name))
        jsonError(422, 'Nama hanya boleh huruf, angka, spasi, titik, dan underscore.');

    try {
        $db->prepare("UPDATE players SET display_name=?, updated_at=NOW() WHERE id=?")
           ->execute([$new_name, $player_id]);
    } catch (Throwable $e) {
        jsonError(500, 'Gagal menyimpan: ' . $e->getMessage());
    }

    ob_clean();
    echo json_encode(['success' => true, 'display_name' => $new_name]);
    exit;
}

// ══════════════════════════════════════════════
//  ACTION: update_username
// ══════════════════════════════════════════════
if ($action === 'update_username') {

    $new_username = trim($_POST['username'] ?? '');
    $password     = $_POST['password'] ?? '';

    if ($new_username === '') jsonError(422, 'Username tidak boleh kosong.');
    if (strlen($new_username) < 3 || strlen($new_username) > 20)
        jsonError(422, 'Username harus 3–20 karakter.');
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $new_username))
        jsonError(422, 'Username hanya boleh huruf, angka, dan underscore.');

    // Cek data player saat ini
    try {
        $cur = $db->prepare("SELECT password, username, username_changes FROM players WHERE id = ?");
        $cur->execute([$player_id]);
        $curData = $cur->fetch();
    } catch (Throwable $e) {
        jsonError(500, 'Gagal membaca data: ' . $e->getMessage());
    }

    if (!$curData) jsonError(404, 'Player tidak ditemukan.');
    if (!password_verify($password, $curData['password']))
        jsonError(403, 'Password salah. Konfirmasi password diperlukan.');
    if ((int)($curData['username_changes'] ?? 0) >= 3)
        jsonError(403, 'Batas maksimal pergantian username (3×) sudah tercapai.');

    // Cek duplikat
    try {
        $dup = $db->prepare("SELECT 1 FROM players WHERE username = ? AND id != ?");
        $dup->execute([$new_username, $player_id]);
        if ($dup->fetch()) jsonError(409, 'Username tersebut sudah digunakan player lain.');
    } catch (Throwable $e) {
        jsonError(500, $e->getMessage());
    }

    $old_username = $curData['username'];

    try {
        $db->beginTransaction();
        $db->prepare("UPDATE players SET username=?, username_changes=username_changes+1, updated_at=NOW() WHERE id=?")
           ->execute([$new_username, $player_id]);

        // Tabel riwayat username
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS username_history (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                player_id VARCHAR(20) NOT NULL,
                old_username VARCHAR(30) NOT NULL,
                new_username VARCHAR(30) NOT NULL,
                changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id), INDEX idx_uh_player (player_id),
                CONSTRAINT fk_uh_p2 FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (Throwable) {}

        $db->prepare("INSERT INTO username_history (player_id, old_username, new_username) VALUES (?,?,?)")
           ->execute([$player_id, $old_username, $new_username]);

        $db->commit();
        $_SESSION['player_name'] = $new_username;

    } catch (Throwable $e) {
        $db->rollBack();
        jsonError(500, 'Gagal menyimpan: ' . $e->getMessage());
    }

    $remain = max(0, 3 - ((int)($curData['username_changes'] ?? 0) + 1));
    ob_clean();
    echo json_encode([
        'success'      => true,
        'username'     => $new_username,
        'changes_left' => $remain,
    ]);
    exit;
}

jsonError(400, 'Action tidak dikenal: ' . htmlspecialchars($action));