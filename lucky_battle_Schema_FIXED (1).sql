-- ══════════════════════════════════════════════════════════════════════════════
--  LUCKY BATTLE — DATABASE SCHEMA LENGKAP (FIXED v2)
--  Game: Batu Gunting Kertas (Rock Paper Scissors)
--  Engine: InnoDB | Charset: utf8mb4_unicode_ci
--
--  URUTAN PEMBUATAN TABEL (dependency-safe):
--  1.  achievements          — tidak ada FK
--  2.  cards                 — tidak ada FK
--  3.  game_config           — tidak ada FK
--  4.  rank_tiers            — tidak ada FK
--  5.  players               — tidak ada FK  ← HARUS SEBELUM semua tabel yang merujuknya
--  6.  ai_card_usage         — FK → players
--  7.  ai_match_history      — FK → players
--  8.  avatar_unlocks        — FK → players
--  9.  leaderboard_snapshots — FK → players
--  10. lobby_chat_log        — FK → players
--  11. match_history         — FK → players
--  12. player_achievements   — FK → players, achievements
--  13. player_card_usage     — FK → players
--  14. username_history      — FK → players
--  15. player_stats_view     — VIEW dari players
--  16. Stored Procedures
--
--  CHANGELOG DARI VERSI SEBELUMNYA:
--  [FIX 1] player_card_usage  — Tipe player_id & card_id diperbaiki INT → VARCHAR
--  [FIX 2] players            — Ditambah kolom ai_rating & peak_ai_rating
--  [FIX 3] player_achievements— Tabel baru (dibutuhkan database.php)
--  [FIX 4] ai_card_usage      — Kolom updated_at diseragamkan
--  [FIX 5] Urutan tabel       — players dibuat LEBIH DULU dari semua tabel
--                               yang punya FK ke players (error #1824 fixed)
-- ══════════════════════════════════════════════════════════════════════════════

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO';

CREATE DATABASE IF NOT EXISTS `lucky_battle`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `lucky_battle`;


-- ──────────────────────────────────────────────────────────────────────────────
--  1. ACHIEVEMENTS  (tidak ada FK — aman dibuat pertama)
-- ──────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `achievements` (
    `id`          VARCHAR(40)  NOT NULL,
    `name`        VARCHAR(80)  NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `icon`        VARCHAR(16)  NOT NULL,
    `category`    ENUM('pvp','ai','rating','streak','weapon','profile','social') NOT NULL DEFAULT 'pvp',
    `is_hidden`   TINYINT(1)   NOT NULL DEFAULT 0,
    `sort_order`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `achievements` (`id`, `name`, `description`, `icon`, `category`, `sort_order`) VALUES
('first_win',      'Kemenangan Pertama', 'Menangkan pertandingan pertamamu!',             '🏆', 'pvp',     1),
('first_pvp',      'Petarung Sejati',    'Mainkan pertandingan PvP pertamamu.',            '🥊', 'pvp',     2),
('win_10',         'Pejuang',            'Menangkan 10 pertandingan ranked.',              '⚔️','pvp',     3),
('win_50',         'Veteran',            'Menangkan 50 pertandingan ranked.',              '🎖️','pvp',    4),
('win_100',        'Legenda',            'Menangkan 100 pertandingan ranked.',             '👑', 'pvp',     5),
('streak_5',       'Di Zona',            'Raih 5 kemenangan beruntun.',                   '🔥', 'streak',  6),
('streak_10',      'Tak Terkalahkan',    'Raih 10 kemenangan beruntun.',                  '⚡', 'streak',  7),
('rating_1200',    'Naik Peringkat',     'Capai rating 1200+.',                           '📈', 'rating',  8),
('rating_1500',    'Elite',              'Capai rating 1500+.',                           '💎', 'rating',  9),
('ai_master',      'Pembunuh AI',        'Kalahkan AI sebanyak 20 kali.',                 '🤖', 'ai',     10),
('rock_master',    'Rock Master',        'Gunakan Batu lebih dari 100 kali.',             '🪨', 'weapon', 11),
('paper_master',   'Paper Tactician',    'Gunakan Kertas lebih dari 100 kali.',           '📄', 'weapon', 12),
('scissor_master', 'Scissor Ninja',      'Gunakan Gunting lebih dari 100 kali.',          '✂️','weapon', 13),
('pacifist',       'Diplomat',           'Raih 10+ hasil seri.',                          '🤝', 'pvp',    14),
('customizer',     'Expresi Diri',       'Atur avatar dan bio profilmu.',                 '🎨', 'profile',15);


-- ──────────────────────────────────────────────────────────────────────────────
--  2. CARDS  (tidak ada FK)
-- ──────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `cards` (
    `id`          VARCHAR(40)      NOT NULL,
    `name`        VARCHAR(60)      NOT NULL,
    `rarity`      ENUM('common','rare','epic','legend') NOT NULL,
    `icon`        VARCHAR(32)      NOT NULL,
    `description` TEXT             NOT NULL,
    `tip`         TEXT             DEFAULT NULL,
    `is_active`   TINYINT(1)       NOT NULL DEFAULT 1,
    `sort_order`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_cards_rarity` (`rarity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `cards` (`id`, `name`, `rarity`, `icon`, `description`, `tip`, `sort_order`) VALUES
('drain_life',     'Drain Life 1',     'common', '🩸',
 'Setiap menang game: +10 HP. Aktif 3 game.',
 'Terbaik saat kamu dominan di awal — snowball HP advantage.', 1),
('gambling1',      'The Gambling I',   'common', '🎲',
 'Menang: +10 damage diberikan. Kalah: +10 damage diterima. Aktif 1 game.',
 'Risiko rendah. Entry-level gambling tanpa kehilangan besar.', 2),
('safe_play1',     'Safe Play I',      'common', '🛡',
 'Kalah = 0 damage diterima. Menang = hanya 50% damage normal. Berlaku 1 game.',
 'Pakai saat HP rendah untuk bertahan sambil mencuri momen balik.', 3),
('barrier',        'Barrier 1',        'common', '🔮',
 'Kalah = damage dikurangi 50%. Bertahan sampai 1 kekalahan lalu hancur.',
 'Pasang di awal ronde sebagai lapisan pertahanan pertama.', 4),
('critical_attack','Critical Attack',  'common', '⚡',
 'Saat menang: 50% chance +30 damage ekstra. Aktif 2 game.',
 'High roll potential. Pasang saat yakin menang 1-2 game berikutnya.', 5),
('tie_breaker',    'Tie Breaker',      'common', '⚖️',
 'Mengubah hasil seri menjadi kemenangan bagimu selama ronde ini aktif.',
 'Wajib di situasi yang sering seri — ubah draw jadi poin.', 6),
('shield1',        'Shield I',         'common', '🛡️',
 '+30 Shield HP yang menyerap damage musuh sebelum HP asli berkurang.',
 'Buffer 30 HP gratis — pakai untuk bertahan dari serangan deras.', 7),
('god_attack1',    'God Attack I',     'common', '⚡',
 'Menang pertama kali: damage 2x (5% chance 3x LUCKY!). Berakhir setelah 1 kemenangan.',
 'Low roll rate tapi cukup untuk membalikkan keadaan dengan double damage.', 8),
('gambling2',      'The Gambling II',  'rare',   '🀄',
 'Menang: +30 damage. Kalah: +30 damage diterima. 1 game per ronde.',
 'Risiko sedang. Reward 30 dmg sangat signifikan.', 9),
('block_one',      'Block One',        'rare',   '🚫',
 'Lawan hanya bisa menggunakan 1 kartu pada ronde ini.',
 'Weapon disruption terkuat — kupas lapisan pertahanan combo lawan.', 10),
('steal_hp',       'Steal HP 1',       'rare',   '🩹',
 '-20 HP lawan secara langsung + +20 Shield HP untuk kamu.',
 'Double efek: melemahkan lawan sekaligus memperkuat dirimu.', 11),
('repeat',         'Repeat',           'rare',   '🔁',
 'Jika kamu kalah ronde ini, ronde diulang dari awal tanpa perubahan HP.',
 'Kartu insurance terbaik saat situasi ronde tampak tidak menguntungkan.', 12),
('safe_play2',     'Safe Play II',     'rare',   '🛡',
 'Kalah = 0 damage. Menang = damage normal penuh (20). Berlaku 1 game.',
 'Upgrade Safe Play I — menang tetap full damage, kalah tetap aman.', 13),
('god_attack2',    'God Attack II',    'rare',   '⚔️',
 'Menang pertama kali: damage 2x (20% chance 3x). Berakhir setelah 1 kemenangan.',
 '20% chance triple damage — jauh lebih baik dari God Attack I.', 14),
('shield2',        'Shield II',        'rare',   '🔷',
 '+60 Shield HP yang menyerap damage sebelum HP asli berkurang.',
 '60 HP buffer — setara 3 serangan normal.', 15),
('gambling3',      'The Gambling III', 'epic',   '🎯',
 'Menang: +50 damage. Kalah: +20 damage diterima. 1 game per ronde.',
 'Asimetris terbaik — reward menang (+50) jauh lebih besar dari risiko kalah (+20).', 16),
('reverse_result', 'Reverse Result',   'epic',   '🔄',
 'Kalah atau seri jadi menang. Bisa digunakan 3 kali — berkurang setiap terpicu.',
 'Counter keras untuk God Attack lawan.', 17),
('god_attack3',    'God Attack III',   'epic',   '💀',
 'Menang pertama kali: damage 2x (50% chance 3x!). Berakhir setelah 1 kemenangan.',
 '50% chance triple damage — tertinggi di kelas God Attack.', 18),
('drain_life_2',   'Drain Life 2',     'epic',   '🩸',
 'Setiap menang: kamu +25 HP dan lawan -10 HP ekstra. Aktif 3 game.',
 'Sustain + pressure sekaligus.', 19),
('steal_hp2',      'Steal HP 2',       'epic',   '💉',
 '-50 HP lawan secara langsung + +50 Shield HP untuk kamu.',
 'Swing HP terbesar di permainan.', 20),
('double_damage',  'Barrier 2',        'epic',   '🔮',
 'Kalah = damage dikurangi menjadi 25%. Bertahan sampai 1 kekalahan lalu hancur.',
 'Hanya 25% damage masuk saat kalah.', 21),
('full_damage',    'Full Damage',      'legend', '💥',
 'Menang pertama kali: damage 5x normal (100 damage — one-hit kill!).',
 'Kartu paling mematikan — 1 menang = match over.', 22),
('shield3',        'Shield III',       'legend', '⭐',
 '+100 Shield HP besar yang menyerap seluruh damage sebelum HP berkurang.',
 'Tembok tak tertembus — 5 serangan normal tidak menyentuh HP aslimu.', 23),
('absolute_reset', 'Absolute Reset',   'legend', '♾️',
 'Mereset seluruh match ke Ronde 1 Game 1. Semua HP, skor, dan efek kartu kembali ke awal.',
 'Kartu last resort — gunakan saat hampir kalah.', 24);


-- ──────────────────────────────────────────────────────────────────────────────
--  3. GAME_CONFIG  (tidak ada FK)
-- ──────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `game_config` (
    `config_key`   VARCHAR(60)  NOT NULL,
    `config_value` VARCHAR(255) NOT NULL,
    `description`  VARCHAR(255) DEFAULT NULL,
    `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `game_config` (`config_key`, `config_value`, `description`) VALUES
('hp_max',               '100',  'HP maksimal setiap player per game'),
('hp_damage',            '20',   'Damage dasar setiap kemenangan game'),
('round_time_sec',       '5',    'Durasi countdown pilihan senjata (detik)'),
('rounds_to_win',        '3',    'Jumlah ronde kemenangan untuk memenangkan match'),
('ws_port',              '8080', 'Port WebSocket server'),
('rating_win_delta',     '10',   'Kenaikan rating saat menang match PvP'),
('rating_loss_delta',    '10',   'Penurunan rating saat kalah match PvP'),
('ai_rating_win_delta',  '25',   'Kenaikan rating saat menang VS AI'),
('ai_rating_loss_delta', '20',   'Penurunan rating saat kalah VS AI'),
('max_username_changes', '3',    'Maksimal berapa kali player bisa ganti username'),
('max_bio_length',       '160',  'Maksimal karakter bio profil'),
('chat_history_buffer',  '50',   'Jumlah pesan chat lobby yang di-buffer server'),
('chat_max_msg_length',  '200',  'Maksimal karakter pesan chat lobby');


-- ──────────────────────────────────────────────────────────────────────────────
--  4. RANK_TIERS  (tidak ada FK)
-- ──────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `rank_tiers` (
    `id`          TINYINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(20)       NOT NULL,
    `min_rating`  SMALLINT UNSIGNED NOT NULL,
    `max_rating`  SMALLINT UNSIGNED DEFAULT NULL,
    `icon`        VARCHAR(16)       DEFAULT NULL,
    `color_hex`   VARCHAR(7)        DEFAULT NULL,
    `rating_win`  TINYINT UNSIGNED  NOT NULL DEFAULT 10,
    `rating_loss` TINYINT UNSIGNED  NOT NULL DEFAULT 10,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_rt_name` (`name`),
    INDEX `idx_rt_min_rating` (`min_rating`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `rank_tiers` (`name`, `min_rating`, `max_rating`, `icon`, `color_hex`) VALUES
('BRONZE',      0,    949,  '🥉', '#cd7f32'),
('SILVER',      950,  1099, '🥈', '#c0c0c0'),
('GOLD',        1100, 1299, '🥇', '#ffd700'),
('PLATINUM',    1300, 1499, '💠', '#00e5ff'),
('DIAMOND',     1500, 1699, '💎', '#b9f2ff'),
('MASTER',      1700, 1999, '🏆', '#ff6b6b'),
('GRANDMASTER', 2000, NULL, '👑', '#ffd700');


-- ──────────────────────────────────────────────────────────────────────────────
--  5. PLAYERS  ← DIBUAT SEBELUM SEMUA TABEL YANG MERUJUKNYA  [FIX 5]
--  [FIX 2] Ditambah kolom ai_rating & peak_ai_rating
-- ──────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `players` (
    `id`                 VARCHAR(20)        NOT NULL,
    `username`           VARCHAR(30)        NOT NULL,
    `display_name`       VARCHAR(30)        DEFAULT NULL,
    `email`              VARCHAR(100)       NOT NULL,
    `password`           VARCHAR(255)       NOT NULL,

    -- Avatar & Profil
    `avatar`             VARCHAR(16)        DEFAULT '⚔️',
    `avatar_choice`      TINYINT UNSIGNED   NOT NULL DEFAULT 0,
    `bio`                VARCHAR(160)       DEFAULT NULL,

    -- Rating PvP
    `rating`             SMALLINT UNSIGNED  NOT NULL DEFAULT 1000,
    `peak_rating`        SMALLINT UNSIGNED  NOT NULL DEFAULT 1000,

    -- Rating VS AI  [FIX 2]
    `ai_rating`          SMALLINT UNSIGNED  NOT NULL DEFAULT 1000,
    `peak_ai_rating`     SMALLINT UNSIGNED  NOT NULL DEFAULT 1000,

    -- Statistik PvP
    `wins`               MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
    `losses`             MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
    `draws`              MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
    `current_win_streak` SMALLINT UNSIGNED  NOT NULL DEFAULT 0,
    `max_win_streak`     SMALLINT UNSIGNED  NOT NULL DEFAULT 0,

    -- Statistik VS AI
    `ai_wins`            MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
    `ai_losses`          MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
    `ai_draws`           MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,

    -- Statistik Senjata
    `total_rock`         INT UNSIGNED       NOT NULL DEFAULT 0,
    `total_paper`        INT UNSIGNED       NOT NULL DEFAULT 0,
    `total_scissors`     INT UNSIGNED       NOT NULL DEFAULT 0,

    -- Riwayat Username
    `username_changes`   TINYINT UNSIGNED   NOT NULL DEFAULT 0,

    -- Timestamps
    `created_at`         DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `last_seen`          DATETIME           DEFAULT NULL,
    `last_login`         DATETIME           DEFAULT NULL,

    -- Status Akun
    `is_banned`          TINYINT(1)         NOT NULL DEFAULT 0,
    `ban_reason`         VARCHAR(255)       DEFAULT NULL,
    `ban_expires_at`     DATETIME           DEFAULT NULL,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_username`          (`username`),
    UNIQUE KEY `uq_email`             (`email`),
    INDEX `idx_players_rating`        (`rating` DESC),
    INDEX `idx_players_ai_rating`     (`ai_rating` DESC),
    INDEX `idx_players_last_seen`     (`last_seen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────────────────────────────────────────────
--  6. AI_CARD_USAGE  (FK → players)
--  [FIX 4] Kolom updated_at diseragamkan antara schema dan PHP auto-create
-- ──────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ai_card_usage` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `player_id`  VARCHAR(20)     NOT NULL,
    `card_id`    VARCHAR(40)     NOT NULL,
    `use_count`  INT UNSIGNED    NOT NULL DEFAULT 1,
    `updated_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ai_card`    (`player_id`, `card_id`),
    INDEX `idx_acu_player`     (`player_id`),
    CONSTRAINT `fk_acu_player`
        FOREIGN KEY (`player_id`) REFERENCES `players`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────────────────────────────────────────────
--  7. AI_MATCH_HISTORY  (FK → players)
-- ──────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ai_match_history` (
    `id`                    BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `player_id`             VARCHAR(20)      NOT NULL,
    `result`                ENUM('won','lost','draw') NOT NULL,
    `player_round_wins`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `ai_round_wins`         TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `choice_rock_count`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `choice_paper_count`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `choice_scissors_count` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `duration_sec`          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `played_at`             DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_amh_player` (`player_id`, `played_at` DESC),
    CONSTRAINT `fk_amh_player`
        FOREIGN KEY (`player_id`) REFERENCES `players`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────────────────────────────────────────────
--  8. AVATAR_UNLOCKS  (FK → players)
-- ──────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `avatar_unlocks` (
    `id`           BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `player_id`    VARCHAR(20)      NOT NULL,
    `avatar_index` TINYINT UNSIGNED NOT NULL,
    `unlocked_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_av_player_index` (`player_id`, `avatar_index`),
    INDEX `idx_av_player` (`player_id`),
    CONSTRAINT `fk_av_player`
        FOREIGN KEY (`player_id`) REFERENCES `players`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────────────────────────────────────────────
--  9. LEADERBOARD_SNAPSHOTS  (FK → players)
-- ──────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `leaderboard_snapshots` (
    `id`          BIGINT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `player_id`   VARCHAR(20)        NOT NULL,
    `rank_pos`    SMALLINT UNSIGNED  NOT NULL,
    `rating`      SMALLINT UNSIGNED  NOT NULL,
    `wins`        MEDIUMINT UNSIGNED NOT NULL,
    `losses`      MEDIUMINT UNSIGNED NOT NULL,
    `draws`       MEDIUMINT UNSIGNED NOT NULL,
    `period`      ENUM('daily','weekly','monthly','alltime') NOT NULL DEFAULT 'daily',
    `snapshot_at` DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_lb_player` (`player_id`),
    INDEX `idx_lb_period` (`period`, `snapshot_at` DESC),
    CONSTRAINT `fk_lb_player`
        FOREIGN KEY (`player_id`) REFERENCES `players`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────────────────────────────────────────────
--  10. LOBBY_CHAT_LOG  (FK → players, nullable)
-- ──────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `lobby_chat_log` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `player_id`   VARCHAR(20)     DEFAULT NULL,
    `player_name` VARCHAR(30)     NOT NULL,
    `avatar`      VARCHAR(16)     DEFAULT '⚔️',
    `message`     VARCHAR(200)    NOT NULL,
    `type`        ENUM('chat','system') NOT NULL DEFAULT 'chat',
    `sent_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_lcl_sent`   (`sent_at` DESC),
    INDEX `idx_lcl_player` (`player_id`),
    CONSTRAINT `fk_lcl_player`
        FOREIGN KEY (`player_id`) REFERENCES `players`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────────────────────────────────────────────
--  11. MATCH_HISTORY  (FK → players x3)
-- ──────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `match_history` (
    `id`                    BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `player1_id`            VARCHAR(20)       NOT NULL,
    `player2_id`            VARCHAR(20)       NOT NULL,
    `winner_id`             VARCHAR(20)       DEFAULT NULL,
    `player1_round_wins`    TINYINT UNSIGNED  NOT NULL DEFAULT 0,
    `player2_round_wins`    TINYINT UNSIGNED  NOT NULL DEFAULT 0,
    `rounds_data`           JSON              DEFAULT NULL,
    `player1_rating_before` SMALLINT UNSIGNED DEFAULT NULL,
    `player1_rating_after`  SMALLINT UNSIGNED DEFAULT NULL,
    `player2_rating_before` SMALLINT UNSIGNED DEFAULT NULL,
    `player2_rating_after`  SMALLINT UNSIGNED DEFAULT NULL,
    `duration_sec`          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `ended_by`              ENUM('normal','disconnect','timeout') NOT NULL DEFAULT 'normal',
    `played_at`             DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_mh_p1`     (`player1_id`, `played_at` DESC),
    INDEX `idx_mh_p2`     (`player2_id`, `played_at` DESC),
    INDEX `idx_mh_winner` (`winner_id`),
    CONSTRAINT `fk_mh_p1`
        FOREIGN KEY (`player1_id`) REFERENCES `players`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_mh_p2`
        FOREIGN KEY (`player2_id`) REFERENCES `players`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_mh_winner`
        FOREIGN KEY (`winner_id`)  REFERENCES `players`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────────────────────────────────────────────
--  12. PLAYER_ACHIEVEMENTS  ← [FIX 3] TABEL BARU
--      Dibutuhkan: checkAndUnlockAchievement() & getPlayerAchievements()
-- ──────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `player_achievements` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `player_id`      VARCHAR(20)     NOT NULL,
    `achievement_id` VARCHAR(40)     NOT NULL,
    `unlocked_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_pa` (`player_id`, `achievement_id`),
    INDEX `idx_pa_player`      (`player_id`),
    INDEX `idx_pa_achievement` (`achievement_id`),
    CONSTRAINT `fk_pa_player`
        FOREIGN KEY (`player_id`)      REFERENCES `players`(`id`)      ON DELETE CASCADE,
    CONSTRAINT `fk_pa_achievement`
        FOREIGN KEY (`achievement_id`) REFERENCES `achievements`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────────────────────────────────────────────
--  13. PLAYER_CARD_USAGE  (FK → players)
--  [FIX 1] Tipe player_id VARCHAR(20) & card_id VARCHAR(40) — sebelumnya INT
-- ──────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `player_card_usage` (
    `id`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `player_id` VARCHAR(20)    NOT NULL,
    `card_id`   VARCHAR(40)    NOT NULL,
    `use_count` INT UNSIGNED   NOT NULL DEFAULT 0,
    `last_used` DATETIME       DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_player_card` (`player_id`, `card_id`),
    INDEX `idx_pcu_player` (`player_id`),
    CONSTRAINT `fk_pcu_player`
        FOREIGN KEY (`player_id`) REFERENCES `players`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────────────────────────────────────────────
--  14. USERNAME_HISTORY  (FK → players)
-- ──────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `username_history` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `player_id`    VARCHAR(20)     NOT NULL,
    `old_username` VARCHAR(30)     NOT NULL,
    `new_username` VARCHAR(30)     NOT NULL,
    `changed_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_uh_player` (`player_id`),
    CONSTRAINT `fk_uh_player`
        FOREIGN KEY (`player_id`) REFERENCES `players`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────────────────────────────────────────────
--  15. PLAYER_STATS_VIEW  (dibuat setelah semua tabel siap)
--  [FIX 2] Ditambah ai_rating, peak_ai_rating, ai_rank_tier
-- ──────────────────────────────────────────────────────────────────────────────
CREATE OR REPLACE VIEW `player_stats_view` AS
SELECT
    p.id,
    p.username,
    p.display_name,
    p.avatar,
    p.avatar_choice,
    p.bio,
    p.rating,
    p.peak_rating,
    p.ai_rating,
    p.peak_ai_rating,
    p.wins,
    p.losses,
    p.draws,
    (p.wins + p.losses + p.draws)                                    AS total_pvp_games,
    CASE
        WHEN (p.wins + p.losses + p.draws) = 0 THEN 0
        ELSE ROUND(p.wins / (p.wins + p.losses + p.draws) * 100, 1)
    END                                                              AS win_rate,
    p.current_win_streak,
    p.max_win_streak,
    p.ai_wins,
    p.ai_losses,
    p.ai_draws,
    (p.ai_wins + p.ai_losses + p.ai_draws)                          AS total_ai_games,
    p.total_rock,
    p.total_paper,
    p.total_scissors,
    (p.total_rock + p.total_paper + p.total_scissors)               AS total_choices,
    p.username_changes,
    (3 - p.username_changes)                                        AS username_changes_left,
    p.created_at,
    p.last_seen,
    p.last_login,
    -- Tier berdasarkan rating PvP
    CASE
        WHEN p.rating >= 2000 THEN 'GRANDMASTER'
        WHEN p.rating >= 1700 THEN 'MASTER'
        WHEN p.rating >= 1500 THEN 'DIAMOND'
        WHEN p.rating >= 1300 THEN 'PLATINUM'
        WHEN p.rating >= 1100 THEN 'GOLD'
        WHEN p.rating >= 950  THEN 'SILVER'
        ELSE 'BRONZE'
    END                                                             AS rank_tier,
    -- Tier berdasarkan peak_rating
    CASE
        WHEN p.peak_rating >= 2000 THEN 'GRANDMASTER'
        WHEN p.peak_rating >= 1700 THEN 'MASTER'
        WHEN p.peak_rating >= 1500 THEN 'DIAMOND'
        WHEN p.peak_rating >= 1300 THEN 'PLATINUM'
        WHEN p.peak_rating >= 1100 THEN 'GOLD'
        WHEN p.peak_rating >= 950  THEN 'SILVER'
        ELSE 'BRONZE'
    END                                                             AS peak_rank_tier,
    -- Tier berdasarkan ai_rating  [FIX 2]
    CASE
        WHEN p.ai_rating >= 2000 THEN 'GRANDMASTER'
        WHEN p.ai_rating >= 1700 THEN 'MASTER'
        WHEN p.ai_rating >= 1500 THEN 'DIAMOND'
        WHEN p.ai_rating >= 1300 THEN 'PLATINUM'
        WHEN p.ai_rating >= 1100 THEN 'GOLD'
        WHEN p.ai_rating >= 950  THEN 'SILVER'
        ELSE 'BRONZE'
    END                                                             AS ai_rank_tier,
    -- Bintang berdasarkan rating
    CASE
        WHEN p.rating >= 1400 THEN 5
        WHEN p.rating >= 1250 THEN 4
        WHEN p.rating >= 1100 THEN 3
        WHEN p.rating >= 950  THEN 2
        ELSE 1
    END                                                             AS rating_stars
FROM players p;


-- ──────────────────────────────────────────────────────────────────────────────
--  16. STORED PROCEDURES
-- ──────────────────────────────────────────────────────────────────────────────
DELIMITER $$

DROP PROCEDURE IF EXISTS `touch_last_seen`$$
CREATE PROCEDURE `touch_last_seen`(IN p_player_id VARCHAR(20))
BEGIN
    UPDATE players SET last_seen = NOW() WHERE id = p_player_id;
END$$

DROP PROCEDURE IF EXISTS `get_player_rank`$$
CREATE PROCEDURE `get_player_rank`(IN p_player_id VARCHAR(20), OUT p_rank INT)
BEGIN
    SELECT COUNT(*) + 1 INTO p_rank
    FROM players
    WHERE rating > (SELECT rating FROM players WHERE id = p_player_id);
END$$

DELIMITER ;


-- ──────────────────────────────────────────────────────────────────────────────
SET FOREIGN_KEY_CHECKS = 1;
-- ──────────────────────────────────────────────────────────────────────────────

-- ══════════════════════════════════════════════════════════════════════════════
--  RINGKASAN TABEL (15 tabel + 1 view + 2 stored procedure)
-- ══════════════════════════════════════════════════════════════════════════════
-- 1.  achievements          — Master 15 achievement (tidak ada FK)
-- 2.  cards                 — Master 24 kartu (tidak ada FK)
-- 3.  game_config           — Parameter game dinamis (tidak ada FK)
-- 4.  rank_tiers            — 7 tier BRONZE s/d GRANDMASTER (tidak ada FK)
-- 5.  players               — Akun & statistik  [FIX 2: +ai_rating, +peak_ai_rating]
-- 6.  ai_card_usage         — Pemakaian kartu VS AI  [FIX 4: +updated_at]
-- 7.  ai_match_history      — Riwayat match VS AI
-- 8.  avatar_unlocks        — Avatar yang sudah di-unlock
-- 9.  leaderboard_snapshots — Snapshot leaderboard periodik
-- 10. lobby_chat_log        — Log pesan lobby chat
-- 11. match_history         — Riwayat match PvP
-- 12. player_achievements   — Achievement player  [FIX 3: TABEL BARU]
-- 13. player_card_usage     — Pemakaian kartu PvP [FIX 1: tipe VARCHAR + FK]
-- 14. username_history      — Riwayat ganti username
-- 15. player_stats_view     — VIEW agregat statistik  [FIX 2: +ai_rank_tier]
-- ══════════════════════════════════════════════════════════════════════════════
