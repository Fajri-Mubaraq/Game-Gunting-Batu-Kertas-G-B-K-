<?php
// ══════════════════════════════════════════════
//  WEBSOCKET SERVER — Rock Paper Scissors PvP
//  Jalankan: php server.php
//  Butuh: composer require cboden/ratchet
// ══════════════════════════════════════════════

require dirname(__FILE__) . '/vendor/autoload.php';
require dirname(__FILE__) . '/../Backend/database.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

define('WS_PORT',       8080);
define('HP_MAX',        100);
define('HP_DAMAGE',     20);
define('ROUND_TIME',    5);
define('ROUNDS_TO_WIN', 2);

class RpsGameServer implements MessageComponentInterface {

    /** @var \SplObjectStorage */
    private \SplObjectStorage $clients;

    /** @var array<string, ConnectionInterface>  playerId => conn */
    private array $playerConn = [];

    /** @var array<string, array>  roomId => room data */
    private array $rooms = [];

    /** @var array<int, string>  conn->resourceId => roomId */
    private array $connRoom = [];

    /** @var ConnectionInterface[] */
    private array $matchmakingQueue = [];

    // ── LOBBY CHAT ──
    /** @var array<string, array>  playerId => {conn, name, avatar, rating} */
    private array $chatPlayers = [];

    /** @var array<int, string>  conn->resourceId => playerId  (for chat cleanup) */
    private array $chatConnMap = [];

    /** @var array  Rolling buffer of last 50 chat messages (persists across player join/leave) */
    private array $chatHistory = [];

    /** @var bool  Apakah tabel lobby_chat_log sudah siap di DB */
    private bool $dbChatReady = false;

    public function __construct() {
        $this->clients = new \SplObjectStorage();
        echo "[WS] Server started on port " . WS_PORT . "\n";
        echo "[WS] Waiting for connections...\n";
        $this->initChatTable();
    }

    /**
     * Buat tabel lobby_chat_log jika belum ada, lalu load 50 pesan terakhir ke $chatHistory.
     */
    private function initChatTable(): void {
        try {
            $db = getDB();
            $db->exec("
                CREATE TABLE IF NOT EXISTS lobby_chat_log (
                    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    player_id   VARCHAR(20)     NOT NULL,
                    player_name VARCHAR(60)     NOT NULL,
                    avatar      VARCHAR(10)     NOT NULL DEFAULT '⚔️',
                    message     TEXT            NOT NULL,
                    sent_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    INDEX idx_lcl_sent (sent_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $this->dbChatReady = true;

            // Load 50 pesan terakhir ke memory buffer
            $stmt = $db->query("
                SELECT player_id, player_name, avatar, message, UNIX_TIMESTAMP(sent_at) AS ts
                FROM lobby_chat_log
                ORDER BY sent_at DESC
                LIMIT 50
            ");
            $rows = array_reverse($stmt->fetchAll(\PDO::FETCH_ASSOC));
            foreach ($rows as $row) {
                $this->chatHistory[] = [
                    'type'        => 'chat_message',
                    'player_id'   => $row['player_id'],
                    'player_name' => $row['player_name'],
                    'avatar'      => $row['avatar'],
                    'text'        => $row['message'],
                    'ts'          => (int)$row['ts'],
                ];
            }
            echo "[Chat] Loaded " . count($this->chatHistory) . " message(s) from DB\n";
        } catch (\Throwable $e) {
            echo "[Chat] DB init error: {$e->getMessage()}\n";
        }
    }

    // ════════════════════════════════════════════
    //  Connection Lifecycle
    // ════════════════════════════════════════════

    public function onOpen(ConnectionInterface $conn): void {
        $this->clients->attach($conn);
        echo "[WS] New connection: #{$conn->resourceId}\n";
    }

    public function onClose(ConnectionInterface $conn): void {
        $this->clients->detach($conn);

        // Remove from queue
        $this->matchmakingQueue = array_values(
            array_filter($this->matchmakingQueue, fn($c) => $c !== $conn)
        );

        // Handle disconnect in room
        $connId = $conn->resourceId;
        if (isset($this->connRoom[$connId])) {
            $roomId = $this->connRoom[$connId];
            $this->handleDisconnect($conn, $roomId);
            unset($this->connRoom[$connId]);
        }

        // Remove from playerConn jika masih mapping ke koneksi ini
        $meta = null;
        try { $meta = $this->clients[$conn] ?? null; } catch (\Throwable $e) {}
        if ($meta && isset($this->playerConn[$meta['player_id']])) {
            if ($this->playerConn[$meta['player_id']] === $conn) {
                unset($this->playerConn[$meta['player_id']]);
            }
        }

        // Remove from chat players on disconnect — use chatConnMap (reliable even for chat-only conns)
        $connId = $conn->resourceId;
        if (isset($this->chatConnMap[$connId])) {
            $pid = $this->chatConnMap[$connId];
            unset($this->chatConnMap[$connId]);
            if (isset($this->chatPlayers[$pid])) {
                $name = $this->chatPlayers[$pid]['name'];
                unset($this->chatPlayers[$pid]);
                // Broadcast updated online list first, then system message
                $this->broadcastChatOnlineUpdate();
                $this->broadcastChat([
                    'type' => 'chat_system',
                    'msg'  => "⬅️ {$name} keluar dari lobby",
                ]);
                echo "[Chat] {$name} disconnected from lobby chat\n";
            }
        }

        echo "[WS] Closed: #{$conn->resourceId}\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void {
        echo "[WS] Error on #{$conn->resourceId}: {$e->getMessage()}\n";
        $conn->close();
    }

    // ════════════════════════════════════════════
    //  Message Router
    // ════════════════════════════════════════════

    public function onMessage(ConnectionInterface $from, $msg): void {
        $data = json_decode($msg, true);
        if (!$data || !isset($data['type'])) return;

        echo "[WS] MSG type={$data['type']} from=#{$from->resourceId}\n";

        switch ($data['type']) {
            case 'auth':               $this->handleAuth($from, $data);         break;
            case 'join_queue':         $this->handleJoinQueue($from);           break;
            case 'leave_queue':        $this->handleLeaveQueue($from);          break;
            case 'choice':             $this->handleChoice($from, $data);       break;
            case 'continue_ready':     $this->handleContinueReady($from);       break;
            case 'rematch':            $this->handleRematch($from);             break;
            case 'leave_room':         $this->handleLeaveRoom($from);           break;
            case 'card_picked':        $this->handleCardPicked($from, $data);   break;
            case 'card_effect_notify': $this->handleCardEffectNotify($from, $data); break;
            case 'hp_sync':            $this->handleHpSync($from, $data);           break;
            case 'repeat_activate':    $this->handleRepeatActivate($from);          break;
            case 'card_block_one':     $this->handleCardBlockOne($from);            break;
            case 'block_one_strike':   $this->handleBlockOneStrike($from);          break;
            // FIX S5: Relay card events yang hilang ke lawan
            case 'card_trap':          $this->handleCardRelay($from, 'opponent_card_trap',   $data); break;
            case 'card_absolute_reset':$this->handleCardAbsoluteReset($from);                        break;
            case 'card_invert':        $this->handleCardRelay($from, 'opponent_card_invert', $data); break;
            case 'card_used':          $this->handleCardRelay($from, 'opponent_card_used',   $data); break;
            // LOBBY CHAT
            case 'chat_auth':          $this->handleChatAuth($from, $data);    break;
            case 'chat_send':          $this->handleChatSend($from, $data);    break;
            case 'chat_leave':         $this->handleChatLeave($from, $data);   break;
        }
    }

    // ════════════════════════════════════════════
    //  Auth
    // ════════════════════════════════════════════

    private function handleAuth(ConnectionInterface $conn, array $data): void {
        $playerId   = trim($data['player_id']   ?? '');
        $playerName = trim($data['player_name'] ?? 'Unknown');
        $rating     = (int)($data['rating']     ?? 1000);
        $roomId     = trim($data['room_id']     ?? '');  // dari gameplay_pvp (reconnect)

        if ($playerId === '') {
            $conn->send(json_encode(['type' => 'error', 'msg' => 'player_id required']));
            return;
        }

        // Jika player punya koneksi lama, pindahkan room mapping ke koneksi baru
        if (isset($this->playerConn[$playerId])) {
            $oldConn   = $this->playerConn[$playerId];
            $oldConnId = $oldConn->resourceId;
            if (isset($this->connRoom[$oldConnId])) {
                $oldRoomId = $this->connRoom[$oldConnId];
                $this->connRoom[$conn->resourceId] = $oldRoomId;
                unset($this->connRoom[$oldConnId]);
                // Update pointer koneksi di dalam room
                if (isset($this->rooms[$oldRoomId]['players'][$playerId])) {
                    $this->rooms[$oldRoomId]['players'][$playerId]['conn'] = $conn;
                }
                echo "[WS] Moved room mapping for {$playerName}: #{$oldConnId} → #{$conn->resourceId}\n";
            }
        }

        // Simpan metadata
        $meta = [
            'player_id'   => $playerId,
            'player_name' => $playerName,
            'rating'      => $rating,
        ];
        $this->clients[$conn]        = $meta;
        $this->playerConn[$playerId] = $conn;

        // Kirim auth_ok
        $conn->send(json_encode([
            'type'    => 'auth_ok',
            'message' => "Selamat datang, {$playerName}!",
        ]));

        echo "[WS] Auth OK: {$playerName} ({$playerId})\n";

        // ─── Rejoin room (dari gameplay_pvp.php) ───
        if ($roomId !== '' && isset($this->rooms[$roomId])) {
            $room = &$this->rooms[$roomId];

            if (isset($room['players'][$playerId])) {
                // Update koneksi di room
                $room['players'][$playerId]['conn']          = $conn;
                $room['players'][$playerId]['ready_in_room'] = true;
                $this->connRoom[$conn->resourceId]           = $roomId;

                echo "[WS] Rejoin: {$playerName} → {$roomId}\n";

                // Cek apakah kedua player sudah ready di gameplay
                $ids      = $room['player_ids'];
                $allReady = true;
                foreach ($ids as $pid) {
                    if (empty($room['players'][$pid]['ready_in_room'])) {
                        $allReady = false;
                        break;
                    }
                }

                if ($allReady && $room['phase'] === 'choosing') {
                    // Kedua player sudah di halaman gameplay — mulai!
                    echo "[WS] Both players ready in room {$roomId}, sending round_start\n";
                    $this->broadcastRoundStart($roomId);
                }
            }
        }
    }

    // ════════════════════════════════════════════
    //  Matchmaking Queue
    // ════════════════════════════════════════════

    private function handleJoinQueue(ConnectionInterface $conn): void {
        $meta = $this->clients[$conn] ?? null;
        if (!$meta) {
            $conn->send(json_encode(['type' => 'error', 'msg' => 'Auth dulu sebelum join queue']));
            return;
        }

        $myPlayerId = $meta['player_id'];

        // Cegah double-join (cek koneksi DAN player_id)
        foreach ($this->matchmakingQueue as $qConn) {
            if ($qConn === $conn) return;
            $qMeta = $this->clients[$qConn] ?? null;
            if ($qMeta && $qMeta['player_id'] === $myPlayerId) {
                $conn->send(json_encode([
                    'type' => 'error',
                    'msg'  => 'Akun ini sudah ada di antrian. Gunakan akun berbeda untuk PvP.'
                ]));
                return;
            }
        }

        $this->matchmakingQueue[] = $conn;
        $qSize = count($this->matchmakingQueue);
        $conn->send(json_encode(['type' => 'queue_joined', 'queue_size' => $qSize]));
        echo "[WS] Queue: {$meta['player_name']} ({$myPlayerId}) joined. Total: {$qSize}\n";

        if ($qSize >= 2) {
            $p1Conn = array_shift($this->matchmakingQueue);
            $m1     = $this->clients[$p1Conn] ?? null;

            // Cari lawan dengan player_id berbeda
            $p2Conn   = null;
            $leftover = [];
            foreach ($this->matchmakingQueue as $candidate) {
                $mc = $this->clients[$candidate] ?? null;
                if ($p2Conn === null && $m1 && $mc && $m1['player_id'] !== $mc['player_id']) {
                    $p2Conn = $candidate;
                } else {
                    $leftover[] = $candidate;
                }
            }
            $this->matchmakingQueue = array_values($leftover);

            if ($p2Conn === null) {
                // Tidak ada lawan valid — kembalikan p1 ke antrian depan
                array_unshift($this->matchmakingQueue, $p1Conn);
                echo "[WS] No valid opponent (all same player_id). Waiting...\n";
            } else {
                $this->createRoom($p1Conn, $p2Conn);
            }
        }
    }

    private function handleLeaveQueue(ConnectionInterface $conn): void {
        $this->matchmakingQueue = array_values(
            array_filter($this->matchmakingQueue, fn($c) => $c !== $conn)
        );
        $conn->send(json_encode(['type' => 'queue_left']));
    }

    // ════════════════════════════════════════════
    //  Create Room
    // ════════════════════════════════════════════

    private function createRoom(ConnectionInterface $p1, ConnectionInterface $p2): void {
        $roomId = 'room_' . uniqid();
        $m1     = $this->clients[$p1] ?? null;
        $m2     = $this->clients[$p2] ?? null;

        if (!$m1 || !$m2) {
            echo "[WS] createRoom: metadata hilang, dibatalkan.\n";
            return;
        }

        // Guard: jangan buat room jika player_id sama
        if ($m1['player_id'] === $m2['player_id']) {
            echo "[WS] createRoom: self-match player_id={$m1['player_id']}, dibatalkan.\n";
            $msg = json_encode(['type' => 'error', 'msg' => 'Tidak bisa melawan diri sendiri. Gunakan akun berbeda.']);
            $p1->send($msg);
            $p2->send($msg);
            return;
        }

        $this->rooms[$roomId] = [
            'id'         => $roomId,
            'player_ids' => [$m1['player_id'], $m2['player_id']],
            'players'    => [
                $m1['player_id'] => [
                    'conn'          => $p1,
                    'id'            => $m1['player_id'],
                    'name'          => $m1['player_name'],
                    'rating'        => $m1['rating'],
                    'hp'            => HP_MAX,
                    'wins'          => 0,
                    'choice'        => null,
                    'ready_in_room' => false,
                ],
                $m2['player_id'] => [
                    'conn'          => $p2,
                    'id'            => $m2['player_id'],
                    'name'          => $m2['player_name'],
                    'rating'        => $m2['rating'],
                    'hp'            => HP_MAX,
                    'wins'          => 0,
                    'choice'        => null,
                    'ready_in_room' => false,
                ],
            ],
            'round'         => 1,
            'phase'         => 'choosing',
            'start_time'    => time(),
            'rounds_log'    => [],
            'rematch_votes' => [],
            'continue_ready'=> [],
            'pending_next'  => [],
            'round_end_resolved' => false, // guard: blokir hp_sync terlambat setelah new_round
            'cards_picked'    => [],   // tracks which players sent card_picked this round
            'cards_used'      => [],   // tracks card_id used per player_id => [card_id, ...]
            'repeat_active'   => false,  // true jika salah satu player punya kartu Repeat aktif
            'repeat_owner'    => null,   // player_id pemilik kartu Repeat yang aktif
            'block_one_next'  => null,   // (legacy: berlaku ronde berikutnya)
            'block_one_active'=> null,   // (legacy: sedang kena efek ronde ini)
            'draw_streak'     => 0,
            'block_one_owner' => null,   // pengaktif Block One di ronde ini
            'block_one_target'=> null,   // target Block One di ronde ini
        ];

        $this->connRoom[$p1->resourceId] = $roomId;
        $this->connRoom[$p2->resourceId] = $roomId;

        // Kirim match_found — client akan redirect ke gameplay_pvp.php
        $this->broadcastRoom($roomId, [
            'type'    => 'match_found',
            'room_id' => $roomId,
            'round'   => 1,
            'players' => [
                [
                    'id'     => $m1['player_id'],
                    'name'   => $m1['player_name'],
                    'rating' => $m1['rating'],
                    'hp'     => HP_MAX,
                    'wins'   => 0,
                ],
                [
                    'id'     => $m2['player_id'],
                    'name'   => $m2['player_name'],
                    'rating' => $m2['rating'],
                    'hp'     => HP_MAX,
                    'wins'   => 0,
                ],
            ],
        ]);

        echo "[WS] Room created: {$roomId} ({$m1['player_name']} vs {$m2['player_name']})\n";
        // CATATAN: round_start akan dikirim setelah KEDUA player reconnect di gameplay_pvp.php
    }

    // ════════════════════════════════════════════
    //  Handle Choice
    // ════════════════════════════════════════════

    private function handleChoice(ConnectionInterface $conn, array $data): void {
        $meta   = $this->clients[$conn] ?? null;
        $connId = $conn->resourceId;
        if (!$meta || !isset($this->connRoom[$connId])) {
            $conn->send(json_encode(['type' => 'error', 'msg' => 'Tidak dalam room']));
            return;
        }

        $roomId   = $this->connRoom[$connId];
        $room     = &$this->rooms[$roomId];
        $playerId = $meta['player_id'];
        $choice   = $data['choice'] ?? '';

        if (!in_array($choice, ['rock', 'paper', 'scissors'], true)) {
            $conn->send(json_encode(['type' => 'error', 'msg' => 'Pilihan tidak valid']));
            return;
        }
        if ($room['phase'] !== 'choosing') return;
        if ($room['players'][$playerId]['choice'] !== null) return; // sudah pilih

        $room['players'][$playerId]['choice'] = $choice;

        // Konfirmasi ke pemilih
        $conn->send(json_encode([
            'type'   => 'choice_confirmed',
            'choice' => $choice,
        ]));

        // Beritahu lawan bahwa player ini sudah memilih (tanpa reveal)
        $this->broadcastRoomExcept($roomId, [
            'type'      => 'opponent_chose',
            'player_id' => $playerId,
        ], $playerId);

        // Cek apakah kedua player sudah pilih
        $ids  = $room['player_ids'];
        $c1   = $room['players'][$ids[0]]['choice'];
        $c2   = $room['players'][$ids[1]]['choice'];

        if ($c1 !== null && $c2 !== null) {
            $this->resolveRound($roomId);
        }
    }

    // ════════════════════════════════════════════
    //  Resolve Round
    // ════════════════════════════════════════════

    private function resolveRound(string $roomId): void {
        $room = &$this->rooms[$roomId];
        $ids  = $room['player_ids'];
        $p1   = &$room['players'][$ids[0]];
        $p2   = &$room['players'][$ids[1]];

        $result = $this->getResult($p1['choice'], $p2['choice']);

        if ($result === 'draw') {
            $room['draw_streak'] = ($room['draw_streak'] ?? 0) + 1;
        } else {
            $room['draw_streak'] = 0;
        }

        // Kurangi HP berdasarkan hasil senjata
        // CATATAN: Client mungkin punya efek kartu (reverse_result, shield, dll) yang
        // akan mengoreksi HP ini via hp_sync SETELAH fight overlay selesai (~5 detik).
        // broadcastRoundStart menggunakan HP terbaru dari state server (sudah dikoreksi hp_sync).
        if ($result === 'p1') {
            $p2['hp'] = max(0, $p2['hp'] - HP_DAMAGE);
        } elseif ($result === 'p2') {
            $p1['hp'] = max(0, $p1['hp'] - HP_DAMAGE);
        } elseif ($result === 'draw' && $room['draw_streak'] >= 3) {
            $p1['hp'] = max(0, $p1['hp'] - 10);
            $p2['hp'] = max(0, $p2['hp'] - 10);
        }

        $room['rounds_log'][] = [
            'round'     => $room['round'],
            'p1_choice' => $p1['choice'],
            'p2_choice' => $p2['choice'],
            'result'    => $result,
            'p1_hp'     => $p1['hp'],
            'p2_hp'     => $p2['hp'],
        ];

        $room['phase'] = 'result';

        // Broadcast hasil
        $this->broadcastRoom($roomId, [
            'type'      => 'round_result',
            'round'     => $room['round'],
            'p1_id'     => $ids[0],
            'p2_id'     => $ids[1],
            'p1_choice' => $p1['choice'],
            'p2_choice' => $p2['choice'],
            'p1_hp'     => $p1['hp'],
            'p2_hp'     => $p2['hp'],
            'result'    => $result,
        ]);

        // Cek apakah ada yang HP-nya habis
        $roundWinner = null;
        if ($p1['hp'] <= 0 && $p2['hp'] <= 0) {
            $roundWinner = 'draw';
        } elseif ($p1['hp'] <= 0) {
            $roundWinner = 'p2';
            $p2['wins']++;
        } elseif ($p2['hp'] <= 0) {
            $roundWinner = 'p1';
            $p1['wins']++;
        }

        // KRUSIAL: reset choice setelah round selesai agar putaran berikutnya bisa pilih
        foreach ($ids as $pid) {
            $room['players'][$pid]['choice'] = null;
        }

        if ($roundWinner !== null) {
            // Tandai bahwa resolveRoundEnd sudah dijadwalkan dari resolveRound ini
            // Agar handleHpSync (reverse_result hp_sync) tidak double-call resolveRoundEnd
            $room['round_end_resolved'] = true;
            // ── Cek apakah ada kartu Repeat yang aktif ──
            // Repeat: jika pemiliknya kalah, game ini diulang (HP reset ke 100, state ulang)
            if (!empty($room['repeat_active']) && !empty($room['repeat_owner'])) {
                $repeatOwner = $room['repeat_owner'];
                $loserOfGame = null;
                if ($roundWinner === 'p1') {
                    // p1 menang → p2 kalah
                    $loserOfGame = $ids[1];
                } elseif ($roundWinner === 'p2') {
                    // p2 menang → p1 kalah
                    $loserOfGame = $ids[0];
                }
                // Jika pemilik Repeat adalah yang kalah → ulangi game ini
                if ($loserOfGame !== null && $loserOfGame === $repeatOwner) {
                    // Reset HP kedua player ke 100
                    $p1['hp']     = HP_MAX;
                    $p2['hp']     = HP_MAX;
                    $p1['choice'] = null;
                    $p2['choice'] = null;
                    $room['phase']          = 'result';
                    $room['repeat_active']  = false;  // kartu Repeat habis setelah digunakan
                    $room['repeat_owner']   = null;
                    $room['continue_ready'] = [];
                    $room['pending_next']   = ['type' => 'game_repeat'];
                    $room['draw_streak']    = 0;
                    $this->startResultPhaseTimer($roomId);

                    $this->broadcastRoom($roomId, [
                        'type'          => 'game_repeated',
                        'round'         => $room['round'],
                        'p1_id'         => $ids[0],
                        'p2_id'         => $ids[1],
                        'p1_hp'         => HP_MAX,
                        'p2_hp'         => HP_MAX,
                        'repeat_owner'  => $repeatOwner,
                    ]);

                    echo "[WS] Repeat card triggered: game repeated in {$roomId} (owner={$repeatOwner})\n";
                    return;
                }
            }
            $this->resolveRoundEnd($roomId, $roundWinner);
        } else {
            // Simpan pending_next, tunggu kedua player klik Lanjutkan
            $room['phase']        = 'result';
            $room['pending_next'] = ['type' => 'next_turn'];
            $room['continue_ready'] = [];
            $this->startResultPhaseTimer($roomId);
        }
    }

    private function resolveRoundEnd(string $roomId, string $roundWinner): void {
        $room = &$this->rooms[$roomId];
        $ids  = $room['player_ids'];
        $p1   = &$room['players'][$ids[0]];
        $p2   = &$room['players'][$ids[1]];

        $matchWinner = null;
        if ($p1['wins'] >= ROUNDS_TO_WIN) {
            $matchWinner = 'p1';
        } elseif ($p2['wins'] >= ROUNDS_TO_WIN) {
            $matchWinner = 'p2';
        }

        if ($matchWinner !== null) {
            $winnerId      = ($matchWinner === 'p1') ? $ids[0] : $ids[1];
            $room['phase'] = 'match_over';

            $this->broadcastRoom($roomId, [
                'type'      => 'match_over',
                'winner_id' => $winnerId,
                'p1_id'     => $ids[0],
                'p2_id'     => $ids[1],
                'p1_wins'   => $p1['wins'],
                'p2_wins'   => $p2['wins'],
            ]);

            $this->saveResult($roomId, $winnerId);
        } else {
            // Reset HP untuk ronde baru dalam match — tapi tunggu kedua player klik Lanjutkan
            $p1['hp']     = HP_MAX;
            $p2['hp']     = HP_MAX;
            $p1['choice'] = null;
            $p2['choice'] = null;
            $room['round']++;
            $room['phase'] = 'result';
            $room['draw_streak'] = 0;
            // Reset Repeat state saat ronde baru dimulai (pemenang ronde sudah ditentukan)
            $room['repeat_active'] = false;
            $room['repeat_owner']  = null;
            // Reset Block One state saat ronde baru dimulai
            $room['block_one_active'] = null;
            $room['block_one_next']   = null;
            $room['block_one_owner']  = null;
            $room['block_one_target'] = null;

            $this->broadcastRoom($roomId, [
                'type'    => 'new_round',
                'round'   => $room['round'],
                'p1_id'   => $ids[0],
                'p2_id'   => $ids[1],
                'p1_wins' => $p1['wins'],
                'p2_wins' => $p2['wins'],
                'p1_hp'   => HP_MAX,
                'p2_hp'   => HP_MAX,
            ]);

            // Simpan pending_next, tunggu kedua player klik Lanjutkan
            $room['pending_next']   = ['type' => 'new_round'];
            $room['continue_ready'] = [];
            $this->startResultPhaseTimer($roomId);
        }
    }

    // ════════════════════════════════════════════
    //  Scheduling
    // ════════════════════════════════════════════

    private function broadcastRoundStart(string $roomId): void {
        if (!isset($this->rooms[$roomId])) return;
        $room = &$this->rooms[$roomId];
        $ids  = $room['player_ids'];
        $room['phase']              = 'choosing';
        $room['cards_picked']       = [];  // reset for new round
        $room['continue_ready']     = [];  // reset continue ready
        $room['round_end_resolved'] = false; // clear guard agar hp_sync ronde baru bisa diproses
        // KRUSIAL: reset choice semua player agar handleChoice tidak di-block
        foreach ($ids as $pid) {
            $room['players'][$pid]['choice'] = null;
        }

        // Aktifkan block_one dari ronde sebelumnya (block_one_next → block_one_active)
        $room['block_one_active'] = $room['block_one_next'];
        $room['block_one_next']   = null;  // sudah dikonsumsi
        $room['block_one_owner']  = null;
        $room['block_one_target'] = null;

        $this->broadcastRoom($roomId, [
            'type'             => 'round_start',
            'round'            => $room['round'],
            'p1_id'            => $ids[0],
            'p2_id'            => $ids[1],
            'p1_hp'            => $room['players'][$ids[0]]['hp'],
            'p2_hp'            => $room['players'][$ids[1]]['hp'],
            'countdown'        => ROUND_TIME,
            'block_one_target' => $room['block_one_active'],  // player_id yang kena efek (atau null)
        ]);
    }

    private function doNewRound(string $roomId): void {
        if (!isset($this->rooms[$roomId])) return;
        $this->broadcastRoundStart($roomId);
    }

    private function scheduleNextTurn(string $roomId): void {
        $room = &$this->rooms[$roomId];
        $ids  = $room['player_ids'];
        $room['phase']                        = 'choosing';
        $room['cards_picked']                 = [];  // reset for new turn
        $room['round_end_resolved']           = false; // clear guard agar hp_sync turn baru bisa diproses
        $room['players'][$ids[0]]['choice']   = null;
        $room['players'][$ids[1]]['choice']   = null;

        // Aktifkan block_one dari turn sebelumnya (block_one_next → block_one_active)
        $room['block_one_active'] = $room['block_one_next'];
        $room['block_one_next']   = null;  // sudah dikonsumsi
        $room['block_one_owner']  = null;
        $room['block_one_target'] = null;

        $this->broadcastRoom($roomId, [
            'type'             => 'next_turn',
            'round'            => $room['round'],
            'p1_id'            => $ids[0],
            'p2_id'            => $ids[1],
            'p1_hp'            => $room['players'][$ids[0]]['hp'],
            'p2_hp'            => $room['players'][$ids[1]]['hp'],
            'countdown'        => ROUND_TIME,
            'block_one_target' => $room['block_one_active'],  // player_id yang kena efek (atau null)
        ]);
    }

    // ════════════════════════════════════════════
    //  Disconnect & Leave
    // ════════════════════════════════════════════

    private function handleDisconnect(ConnectionInterface $conn, string $roomId): void {
        if (!isset($this->rooms[$roomId])) return;
        $room = &$this->rooms[$roomId];
        if ($room['phase'] === 'match_over') return;

        $meta = null;
        try { $meta = $this->clients[$conn] ?? null; } catch (\Throwable $e) {}
        if (!$meta) return;

        // Cancel timer Lanjutkan jika ada
        if (isset($room['continue_timer'])) {
            \React\EventLoop\Loop::cancelTimer($room['continue_timer']);
            unset($room['continue_timer']);
        }

        $dcPlayerId = $meta['player_id'];
        $ids        = $room['player_ids'];
        $winnerId   = ($ids[0] === $dcPlayerId) ? $ids[1] : $ids[0];

        $room['phase'] = 'match_over';

        $this->broadcastRoom($roomId, [
            'type'      => 'match_over',
            'winner_id' => $winnerId,
            'reason'    => 'disconnect',
            'dc_player' => $dcPlayerId,
        ]);

        $this->saveResult($roomId, $winnerId);
        unset($this->rooms[$roomId]);
    }

    private function handleLeaveRoom(ConnectionInterface $conn): void {
        $connId = $conn->resourceId;
        if (!isset($this->connRoom[$connId])) return;
        $roomId = $this->connRoom[$connId];
        $this->handleDisconnect($conn, $roomId);
        unset($this->connRoom[$connId]);
    }

    // ════════════════════════════════════════════
    //  Continue Ready (tunggu kedua player klik Lanjutkan)
    // ════════════════════════════════════════════

    private function handleContinueReady(ConnectionInterface $conn): void {
        $connId = $conn->resourceId;
        if (!isset($this->connRoom[$connId])) return;

        $roomId   = $this->connRoom[$connId];
        $meta     = $this->clients[$conn] ?? null;
        if (!$meta || !isset($this->rooms[$roomId])) return;

        $room     = &$this->rooms[$roomId];
        $playerId = $meta['player_id'];

        // Hanya proses jika fase sedang 'result'
        if ($room['phase'] !== 'result') return;

        // Tandai player ini sudah siap lanjut
        $room['continue_ready'][$playerId] = true;

        $ids      = $room['player_ids'];
        $readyIds = array_keys($room['continue_ready']);
        $waiting  = array_diff($ids, $readyIds);

        echo "[WS] continue_ready: {$meta['player_name']} in {$roomId}. Ready: " . count($readyIds) . "/2\n";

        // Beritahu lawan bahwa player ini sudah siap
        $this->broadcastRoomExcept($roomId, [
            'type'      => 'opponent_continue_ready',
            'player_id' => $playerId,
        ], $playerId);

        // Jika kedua player sudah ready → lanjut ke giliran berikutnya
        if (count($readyIds) >= 2) {
            // Batalkan timer AFK Lanjutkan jika ada
            if (isset($room['continue_timer'])) {
                \React\EventLoop\Loop::cancelTimer($room['continue_timer']);
                unset($room['continue_timer']);
            }

            $room['continue_ready'] = [];   // reset untuk ronde berikutnya

            // Tentukan apakah lanjut turn dalam ronde atau ronde baru sudah dikirim
            // Cek apakah ada pending_next yang sudah disiapkan
            if (!empty($room['pending_next'])) {
                $nextType = $room['pending_next']['type'];
                $room['pending_next'] = [];

                if ($nextType === 'next_turn') {
                    $this->scheduleNextTurn($roomId);
                } elseif ($nextType === 'new_round') {
                    $this->doNewRound($roomId);
                } elseif ($nextType === 'game_repeat') {
                    // Reset choice dan kirim next_turn dengan HP penuh
                    $this->scheduleNextTurnAfterRepeat($roomId);
                }
            }
        }
    }

    private function startResultPhaseTimer(string $roomId): void {
        if (!isset($this->rooms[$roomId])) return;
        $room = &$this->rooms[$roomId];

        // Hapus timer continue lama jika ada
        if (isset($room['continue_timer'])) {
            \React\EventLoop\Loop::cancelTimer($room['continue_timer']);
            unset($room['continue_timer']);
        }

        // Jadwalkan mulai hitung mundur 10 detik setelah 5s (agar pas saat animasi fight selesai di client)
        $roomIdCopy = $roomId;
        $room['continue_timer'] = \React\EventLoop\Loop::addTimer(5.0, function() use ($roomIdCopy) {
            $this->startContinueCountdown($roomIdCopy);
        });
    }

    private function startContinueCountdown(string $roomId): void {
        if (!isset($this->rooms[$roomId])) return;
        $room = &$this->rooms[$roomId];
        if ($room['phase'] !== 'result') return;

        $ids = $room['player_ids'];
        $readyIds = array_keys($room['continue_ready']);
        $waiting = array_diff($ids, $readyIds);

        // Jika semua sudah ready, tidak perlu countdown
        if (count($readyIds) >= 2) return;

        // Broadcast ke room bahwa hitung mundur 10 detik dimulai untuk siapa saja yang belum klik Lanjutkan
        $this->broadcastRoom($roomId, [
            'type'        => 'continue_countdown',
            'duration'    => 10,
            'waiting_for' => (count($waiting) === 1) ? reset($waiting) : 'both',
        ]);

        // Hapus timer lama jika ada
        if (isset($room['continue_timer'])) {
            \React\EventLoop\Loop::cancelTimer($room['continue_timer']);
        }

        $roomIdCopy = $roomId;
        $room['continue_timer'] = \React\EventLoop\Loop::addTimer(10.0, function() use ($roomIdCopy) {
            $this->handleContinueTimeout($roomIdCopy);
        });
    }

    private function handleContinueTimeout(string $roomId): void {
        if (!isset($this->rooms[$roomId])) return;
        $room = &$this->rooms[$roomId];

        // Pastikan fase masih result
        if ($room['phase'] !== 'result') return;

        $ids = $room['player_ids'];
        $readyIds = array_keys($room['continue_ready']);
        $waiting = array_diff($ids, $readyIds);

        // Cari siapa yang AFK
        if (count($readyIds) === 1) {
            $afkPlayerId = reset($waiting);
            $winnerId = ($ids[0] === $afkPlayerId) ? $ids[1] : $ids[0];
        } else {
            // Kedua player AFK (atau tidak ada yang klik Lanjutkan)
            $afkPlayerId = $ids[0];
            $winnerId = $ids[1];
        }

        $room['phase'] = 'match_over';

        // Hapus timer
        if (isset($room['continue_timer'])) {
            \React\EventLoop\Loop::cancelTimer($room['continue_timer']);
            unset($room['continue_timer']);
        }

        // Broadcast kekalahan AFK ke room
        $this->broadcastRoom($roomId, [
            'type'      => 'match_over',
            'winner_id' => $winnerId,
            'reason'    => 'afk',
            'afk_player'=> $afkPlayerId,
            'p1_id'     => $ids[0],
            'p2_id'     => $ids[1],
            'p1_wins'   => $room['players'][$ids[0]]['wins'],
            'p2_wins'   => $room['players'][$ids[1]]['wins'],
        ]);

        // Simpan hasil ke database dan tutup room
        $this->saveResult($roomId, $winnerId);
        unset($this->rooms[$roomId]);

        echo "[WS] Room {$roomId} ended due to AFK timeout. Winner: {$winnerId}\n";
    }

    // ════════════════════════════════════════════
    //  Card Events — relay to opponent
    // ════════════════════════════════════════════

    private function handleCardPicked(ConnectionInterface $conn, array $data = []): void {
        $connId = $conn->resourceId;
        if (!isset($this->connRoom[$connId])) return;
        $roomId   = $this->connRoom[$connId];
        $meta     = $this->clients[$conn] ?? null;
        if (!$meta || !isset($this->rooms[$roomId])) return;

        $room     = &$this->rooms[$roomId];
        $playerId = $meta['player_id'];

        // Track this player's card selection
        $room['cards_picked'][$playerId] = true;

        // ── Simpan kartu yang dipilih ke cards_used & langsung ke DB ──
        $cardIds = $data['card_ids'] ?? [];
        if (!empty($cardIds) && is_array($cardIds)) {
            // Sanitize
            $cardIds = array_filter(array_map(function($id) {
                return preg_replace('/[^a-z0-9_]/', '', strtolower((string)$id));
            }, $cardIds));

            // Tambah ke room state (untuk disimpan saat match selesai)
            if (!isset($room['cards_used'][$playerId])) $room['cards_used'][$playerId] = [];
            foreach ($cardIds as $cid) {
                if ($cid !== '') $room['cards_used'][$playerId][] = $cid;
            }

            // Simpan langsung ke DB (tidak tunggu match selesai)
            try {
                $db = getDB();
                $db->exec("
                    CREATE TABLE IF NOT EXISTS player_card_usage (
                        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                        player_id VARCHAR(20) NOT NULL,
                        card_id VARCHAR(40) NOT NULL,
                        use_count INT UNSIGNED NOT NULL DEFAULT 1,
                        last_used DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        UNIQUE KEY uniq_player_card (player_id, card_id),
                        INDEX idx_pcu_player (player_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
                $stmtCard = $db->prepare("
                    INSERT INTO player_card_usage (player_id, card_id, use_count, last_used)
                    VALUES (?, ?, 1, NOW())
                    ON DUPLICATE KEY UPDATE
                        use_count = use_count + 1,
                        last_used = NOW()
                ");
                foreach ($cardIds as $cid) {
                    if ($cid !== '') $stmtCard->execute([$playerId, $cid]);
                }
                echo "[WS] card_picked: saved " . count($cardIds) . " cards for {$meta['player_name']}\n";
            } catch (\Throwable $e) {
                echo "[WS] card_picked DB error: {$e->getMessage()}\n";
            }
        }

        // Relay to opponent so they can update their UI
        $this->broadcastRoomExcept($roomId, [
            'type'      => 'opponent_card_picked',
            'player_id' => $playerId,
        ], $playerId);

        echo "[WS] card_picked: {$meta['player_name']} in {$roomId}\n";

        // When BOTH players have picked → tell everyone so timers start together
        if (count($room['cards_picked']) >= count($room['player_ids'])) {
            $room['cards_picked'] = [];
            $this->broadcastRoom($roomId, ['type' => 'cards_ready']);
            echo "[WS] cards_ready: both players done in {$roomId}\n";
        }
    }

    private function handleRepeatActivate(ConnectionInterface $conn): void {
        $connId = $conn->resourceId;
        if (!isset($this->connRoom[$connId])) return;
        $roomId   = $this->connRoom[$connId];
        $meta     = $this->clients[$conn] ?? null;
        if (!$meta || !isset($this->rooms[$roomId])) return;

        $room     = &$this->rooms[$roomId];
        $playerId = $meta['player_id'];

        // Tandai repeat aktif untuk player ini di game yang sedang berjalan
        $room['repeat_active'] = true;
        $room['repeat_owner']  = $playerId;

        // Relay ke lawan agar chip Repeat muncul di sisi lawan
        $this->broadcastRoomExcept($roomId, [
            'type'      => 'opponent_repeat_active',
            'player_id' => $playerId,
        ], $playerId);

        echo "[WS] repeat_activate: {$meta['player_name']} in {$roomId}\n";
    }

    private function handleCardBlockOne(ConnectionInterface $conn): void {
        $connId = $conn->resourceId;
        if (!isset($this->connRoom[$connId])) return;
        $roomId   = $this->connRoom[$connId];
        $meta     = $this->clients[$conn] ?? null;
        if (!$meta || !isset($this->rooms[$roomId])) return;

        $room     = &$this->rooms[$roomId];
        $playerId = $meta['player_id'];
        $ids      = $room['player_ids'];

        // Tentukan siapa yang kena efek (lawan dari pengaktif)
        $targetId = ($ids[0] === $playerId) ? $ids[1] : $ids[0];

        // Simpan target untuk referensi (tidak perlu berlaku ronde berikutnya,
        // efek langsung via block_one_strike)
        $room['block_one_owner'] = $playerId;   // siapa yang mengaktifkan di ronde ini
        $room['block_one_target'] = $targetId;  // siapa yang kena

        // Relay ke LAWAN — beri tahu bahwa Block One telah diaktifkan
        $this->broadcastRoomExcept($roomId, [
            'type'      => 'opponent_block_one',
            'owner_id'  => $playerId,
        ], $playerId);

        // Konfirmasi ke pengaktif
        $conn->send(json_encode([
            'type'      => 'block_one_confirmed',
            'target_id' => $targetId,
        ]));

        // Track penggunaan kartu block_one
        if (!isset($room['cards_used'][$playerId])) $room['cards_used'][$playerId] = [];
        $room['cards_used'][$playerId][] = 'block_one';

        echo "[WS] card_block_one: {$meta['player_name']} → target={$targetId} in {$roomId}\n";
    }

    private function handleBlockOneStrike(ConnectionInterface $conn): void {
        $connId = $conn->resourceId;
        if (!isset($this->connRoom[$connId])) return;
        $roomId = $this->connRoom[$connId];
        $meta   = $this->clients[$conn] ?? null;
        if (!$meta || !isset($this->rooms[$roomId])) return;

        $room     = &$this->rooms[$roomId];
        $playerId = $meta['player_id'];

        // Pastikan yang mengirim adalah pengaktif Block One di ronde ini
        if (($room['block_one_owner'] ?? null) !== $playerId) {
            echo "[WS] block_one_strike ignored: {$playerId} is not owner\n";
            return;
        }

        $targetId = $room['block_one_target'] ?? null;
        if (!$targetId) return;

        // Relay pesan block_one_strike ke TARGET (lawan)
        $targetConn = $this->playerConn[$targetId] ?? null;
        if ($targetConn) {
            $targetConn->send(json_encode([
                'type'     => 'block_one_strike',
                'owner_id' => $playerId,
            ]));
            echo "[WS] block_one_strike: {$playerId} → target={$targetId}\n";
        }

        // Clear setelah dipakai
        $room['block_one_owner']  = null;
        $room['block_one_target'] = null;
    }

    private function scheduleNextTurnAfterRepeat(string $roomId): void {
        if (!isset($this->rooms[$roomId])) return;
        $room = &$this->rooms[$roomId];
        $ids  = $room['player_ids'];
        $room['phase']                        = 'choosing';
        $room['cards_picked']                 = [];
        $room['players'][$ids[0]]['choice']   = null;
        $room['players'][$ids[1]]['choice']   = null;
        // HP sudah di-reset saat game_repeated dikirim, kirim ulang via next_turn
        $this->broadcastRoom($roomId, [
            'type'      => 'next_turn',
            'round'     => $room['round'],
            'p1_id'     => $ids[0],
            'p2_id'     => $ids[1],
            'p1_hp'     => $room['players'][$ids[0]]['hp'],
            'p2_hp'     => $room['players'][$ids[1]]['hp'],
            'countdown' => ROUND_TIME,
            'from_repeat' => true,  // flag agar client tahu ini dari pengulangan Repeat
        ]);
    }

    private function handleCardEffectNotify(ConnectionInterface $conn, array $data): void {
        $connId = $conn->resourceId;
        if (!isset($this->connRoom[$connId])) return;
        $roomId   = $this->connRoom[$connId];
        $meta     = $this->clients[$conn] ?? null;
        if (!$meta) return;
        // Relay effect info to opponent so they can display it
        $payload = [
            'type'       => 'opponent_effect_active',
            'player_id'  => $meta['player_id'],
            'effect_id'  => $data['effect_id']  ?? '',
            'label'      => $data['label']       ?? '',
            'rarity'     => $data['rarity']      ?? 'common',
            'games_left' => $data['games_left']  ?? 1,
        ];
        // Relay shield_hp dan shield_max agar bar lawan tampil full
        if (isset($data['shield_hp']))  $payload['shield_hp']  = (int)$data['shield_hp'];
        if (isset($data['shield_max'])) $payload['shield_max'] = (int)$data['shield_max'];
        $this->broadcastRoomExcept($roomId, $payload, $meta['player_id']);
        echo "[WS] card_effect_notify: {$meta['player_name']} effect={$data['effect_id']} in {$roomId}\n";
    }

    private function handleHpSync(ConnectionInterface $conn, array $data): void {
        $connId = $conn->resourceId;
        if (!isset($this->connRoom[$connId])) return;
        $roomId   = $this->connRoom[$connId];
        $meta     = $this->clients[$conn] ?? null;
        if (!$meta || !isset($this->rooms[$roomId])) return;

        $playerId = $meta['player_id'];
        $room     = &$this->rooms[$roomId];
        $ids      = $room['player_ids'];

        // Hitung opponentId lebih awal (dibutuhkan untuk update HP dan relay)
        $opponentId = null;
        foreach ($ids as $pid) {
            if ($pid !== $playerId) { $opponentId = $pid; break; }
        }

        $myNewHp    = isset($data['my_hp'])    ? max(0, min(200, (int)$data['my_hp']))    : null;
        $theirNewHp = isset($data['their_hp']) ? max(0, min(200, (int)$data['their_hp'])) : null;
        $tieBreakerTriggered = isset($data['tie_breaker_triggered']) ? (bool)$data['tie_breaker_triggered'] : null;
        $reverseResultTriggered = isset($data['reverse_result_triggered']) ? (bool)$data['reverse_result_triggered'] : null;

        // ── Simpan apakah ronde sudah diselesaikan SEBELUM guard berjalan ──
        // Kunci: relay hp_sync PERTAMA (yang memicu HP=0) tetap dikirim ke lawan agar
        // tampilan HP lawan sinkron (misal: HP 0 saat Full Damage). hp_sync TERLAMBAT
        // (dari Phase-4 fight overlay yang datang setelah new_round) diblokir seluruhnya.
        $wasAlreadyResolved = !empty($room['round_end_resolved']);

        if (!$wasAlreadyResolved) {
            // Update HP sender di server
            if ($myNewHp !== null && isset($room['players'][$playerId])) {
                $room['players'][$playerId]['hp'] = $myNewHp;
            }
            // Update HP lawan di server jika sender juga melaporkannya (steal_hp, reverse_result, Full Damage, dll)
            if ($theirNewHp !== null && $opponentId && isset($room['players'][$opponentId])) {
                $room['players'][$opponentId]['hp'] = $theirNewHp;
            }

            // Jika tie_breaker atau reverse_result membatalkan seri (draw), reset draw_streak di server ke 0
            if ($tieBreakerTriggered || $reverseResultTriggered) {
                $room['draw_streak'] = 0;
            }

            // ── Cek HP 0 setelah hp_sync: berlaku untuk SEMUA kartu (bukan hanya reverse_result) ──
            // Menangani Full Damage (100 dmg), God Attack (2x/3x), Gambling ekstra, dsb.
            // resolveRound hanya kurangi HP base (20); efek kartu diselesaikan client-side lalu
            // dilaporkan via hp_sync. Server perlu mendeteksi HP=0 di sini agar pending_next
            // diubah dari 'next_turn' ke 'new_round' dan HP di-reset ke 100 sebelum Lanjutkan.
            if ($room['phase'] === 'result') {
                $p1Hp = $room['players'][$ids[0]]['hp'];
                $p2Hp = $room['players'][$ids[1]]['hp'];

                if ($p1Hp <= 0 || $p2Hp <= 0) {
                    $roundWinner = null;
                    if ($p1Hp <= 0 && $p2Hp <= 0) {
                        $roundWinner = 'draw';
                    } elseif ($p1Hp <= 0) {
                        $roundWinner = 'p2';
                        $room['players'][$ids[1]]['wins']++;
                    } elseif ($p2Hp <= 0) {
                        $roundWinner = 'p1';
                        $room['players'][$ids[0]]['wins']++;
                    }

                    if ($roundWinner !== null && $roundWinner !== 'draw') {
                        // Simpan continue_ready agar player yang sudah klik Lanjutkan tidak perlu klik ulang
                        $savedReady = $room['continue_ready'];

                        // Tandai ronde selesai — hp_sync terlambat dari Phase-4 (~5 detik) akan diblokir
                        $room['round_end_resolved'] = true;

                        // Relay hp_sync ini SEBELUM resolveRoundEnd, agar lawan melihat HP=0 yang benar
                        // (resolveRoundEnd akan broadcast new_round, tapi relay ini perlu terjadi dulu)
                        // ── relay dilakukan di bawah (setelah blok ini) karena $wasAlreadyResolved=false ──

                        $this->resolveRoundEnd($roomId, $roundWinner);

                        // Kembalikan continue_ready jika ada yang sudah klik Lanjutkan sebelum hp_sync tiba
                        if (!empty($savedReady) && isset($this->rooms[$roomId])) {
                            $this->rooms[$roomId]['continue_ready'] = $savedReady;
                            if (count($savedReady) >= 2) {
                                $this->rooms[$roomId]['continue_ready'] = [];
                                $nextType = $this->rooms[$roomId]['pending_next']['type'] ?? '';
                                $this->rooms[$roomId]['pending_next'] = [];
                                if ($nextType === 'new_round') {
                                    $this->doNewRound($roomId);
                                } elseif ($nextType === 'next_turn') {
                                    $this->scheduleNextTurn($roomId);
                                }
                            }
                        }
                        // Jangan return — lanjutkan ke relay di bawah
                    }
                }
            }
        } else {
            // hp_sync terlambat (room sudah resolved) — abaikan sepenuhnya termasuk relay-nya
            // agar nilai HP stale (mis: HP=0 dari fight overlay yang terlambat) tidak menimpa
            // HP=100 yang sudah di-set oleh new_round di sisi klien.
            return;
        }

        // Relay to opponent:
        //   their_hp = sender's new HP   (opponent sees this as the enemy's HP)
        //   my_hp    = opponent's own new HP (opponent sees this as their own HP, if changed)
        $drainLifeGamesLeft  = isset($data['drain_life_games_left'])  ? (int)$data['drain_life_games_left']  : -1;
        $gamblingGamesLeft   = isset($data['gambling_games_left'])    ? (int)$data['gambling_games_left']    : -1;
        $gamblingExtraDmg    = isset($data['gambling_extra_dmg'])     ? (int)$data['gambling_extra_dmg']     : 0;
        $safePlayGamesLeft   = isset($data['safe_play_games_left'])   ? (int)$data['safe_play_games_left']   : -1;
        $safePlay2GamesLeft  = isset($data['safe_play2_games_left'])  ? (int)$data['safe_play2_games_left']  : -1;
        $godAttackGamesLeft  = isset($data['god_attack_games_left'])  ? (int)$data['god_attack_games_left']  : -1;
        $godAttackMultiplier = isset($data['god_attack_multiplier'])  ? (int)$data['god_attack_multiplier']  : 1;
        $godAttackActualDmg  = isset($data['god_attack_actual_dmg'])  ? (int)$data['god_attack_actual_dmg']  : 0;
        $godAtk2GamesLeft    = isset($data['god_atk2_games_left'])    ? (int)$data['god_atk2_games_left']    : -1;
        $godAtk2Multiplier   = isset($data['god_atk2_multiplier'])    ? (int)$data['god_atk2_multiplier']    : 1;
        $godAtk2ActualDmg    = isset($data['god_atk2_actual_dmg'])    ? (int)$data['god_atk2_actual_dmg']    : 0;
        // FIX: god_atk3 dan full_damage sebelumnya tidak di-relay — menyebabkan chip tidak hilang
        $godAtk3GamesLeft    = isset($data['god_atk3_games_left'])    ? (int)$data['god_atk3_games_left']    : -1;
        $godAtk3Multiplier   = isset($data['god_atk3_multiplier'])    ? (int)$data['god_atk3_multiplier']    : 1;
        $godAtk3ActualDmg    = isset($data['god_atk3_actual_dmg'])    ? (int)$data['god_atk3_actual_dmg']    : 0;
        $fullDamageGamesLeft = isset($data['full_damage_games_left']) ? (int)$data['full_damage_games_left'] : -1;
        // forward barrier status so opponent's chip disappears when barrier breaks
        $barrierBroke  = isset($data['barrier_broke'])  ? (bool)$data['barrier_broke']  : null;
        $barrierActive = isset($data['barrier_active']) ? (bool)$data['barrier_active'] : null;
        $shieldHp      = isset($data['shield_hp'])      ? (int)$data['shield_hp']        : null;
        $shieldBroke   = isset($data['shield_broke'])   ? (bool)$data['shield_broke']    : null;
        // Critical Attack sync
        $criticalAttackDmg   = isset($data['critical_attack_dmg'])   ? (int)$data['critical_attack_dmg']   : null;
        $criticalGamesLeft   = isset($data['critical_games_left'])   ? (int)$data['critical_games_left']   : -1;
        // Reverse Result sync
        $reverseResultGamesLeft = isset($data['reverse_result_games_left']) ? (int)$data['reverse_result_games_left'] : null;

        $relayPayload = [
            'type'                   => 'opponent_hp_sync',
            'their_hp'               => $myNewHp ?? ($room['players'][$playerId]['hp'] ?? HP_MAX),
            'my_hp'                  => $theirNewHp,
            'heal_amount'            => (int)($data['heal_amount'] ?? 0),
            'dmg_amount'             => (int)($data['dmg_amount']  ?? 0),
            'drain_life_games_left'  => $drainLifeGamesLeft,
            'gambling_games_left'    => $gamblingGamesLeft,
            'gambling_extra_dmg'     => $gamblingExtraDmg,
            'safe_play_games_left'   => $safePlayGamesLeft,
            'safe_play2_games_left'  => $safePlay2GamesLeft,
            'god_attack_games_left'  => $godAttackGamesLeft,
            'god_attack_multiplier'  => $godAttackMultiplier,
            'god_attack_actual_dmg'  => $godAttackActualDmg,
            'god_atk2_games_left'    => $godAtk2GamesLeft,
            'god_atk2_multiplier'    => $godAtk2Multiplier,
            'god_atk2_actual_dmg'    => $godAtk2ActualDmg,
            'god_atk3_games_left'    => $godAtk3GamesLeft,
            'god_atk3_multiplier'    => $godAtk3Multiplier,
            'god_atk3_actual_dmg'    => $godAtk3ActualDmg,
            'full_damage_games_left' => $fullDamageGamesLeft,
        ];
        if ($barrierBroke  !== null) $relayPayload['barrier_broke']  = $barrierBroke;
        if ($barrierActive !== null) $relayPayload['barrier_active'] = $barrierActive;
        if ($tieBreakerTriggered !== null) $relayPayload['tie_breaker_triggered'] = $tieBreakerTriggered;
        if ($shieldHp    !== null) $relayPayload['shield_hp']    = $shieldHp;
        if ($shieldBroke !== null) $relayPayload['shield_broke'] = $shieldBroke;
        $shieldMax = isset($data['shield_max']) ? (int)$data['shield_max'] : null;
        if ($shieldMax !== null) $relayPayload['shield_max'] = $shieldMax;
        if ($criticalAttackDmg !== null) $relayPayload['critical_attack_dmg'] = $criticalAttackDmg;
        $relayPayload['critical_games_left'] = $criticalGamesLeft; // selalu kirim (-1 = tidak aktif)
        // Forward reverse_result agar chip lawan tersinkron
        if ($reverseResultTriggered !== null) $relayPayload['reverse_result_triggered']  = $reverseResultTriggered;
        if ($reverseResultGamesLeft !== null) $relayPayload['reverse_result_games_left'] = $reverseResultGamesLeft;
        // FIX S6: Forward steal_shield agar chip steal_hp muncul di sisi lawan
        $stealShield   = isset($data['steal_shield'])    ? (bool)$data['steal_shield']    : null;
        $stealShieldHp = isset($data['steal_shield_hp']) ? (int)$data['steal_shield_hp']  : null;
        if ($stealShield   !== null) $relayPayload['steal_shield']    = $stealShield;
        if ($stealShieldHp !== null) $relayPayload['steal_shield_hp'] = $stealShieldHp;
        // Forward steal_hp2 agar chip dan HP sync muncul di sisi lawan
        $stealHp2       = isset($data['steal_hp2'])        ? (bool)$data['steal_hp2']        : null;
        $stealHp2Amount = isset($data['steal_hp2_amount']) ? (int)$data['steal_hp2_amount']   : null;
        if ($stealHp2       !== null) $relayPayload['steal_hp2']        = $stealHp2;
        if ($stealHp2Amount !== null) $relayPayload['steal_hp2_amount'] = $stealHp2Amount;

        $this->broadcastRoomExcept($roomId, $relayPayload, $playerId);

        echo "[WS] hp_sync: {$meta['player_name']} my_hp=" . ($myNewHp ?? '?') . " their_hp=" . ($theirNewHp ?? '?') . " drain_gl={$drainLifeGamesLeft} crit_gl=" . ($criticalGamesLeft ?? '?') . " in {$roomId}\n";
    }

    // ════════════════════════════════════════════
    //  Generic Card Relay (card_trap, card_absolute_reset, card_invert, card_used)
    // ════════════════════════════════════════════

    private function handleCardRelay(ConnectionInterface $conn, string $relayType, array $data = []): void {
        $connId = $conn->resourceId;
        if (!isset($this->connRoom[$connId])) return;
        $roomId   = $this->connRoom[$connId];
        $meta     = $this->clients[$conn] ?? null;
        if (!$meta || !isset($this->rooms[$roomId])) return;

        $playerId = $meta['player_id'];

        $payload = ['type' => $relayType, 'player_id' => $playerId];

        // Sertakan field tambahan dari card_used jika ada
        if (!empty($data['card_id']))    $payload['card_id']    = $data['card_id'];
        if (!empty($data['effect_id']))  $payload['effect_id']  = $data['effect_id'];

        $this->broadcastRoomExcept($roomId, $payload, $playerId);

        // Track kartu yang dipakai untuk statistik
        if (!empty($data['card_id'])) {
            $room =& $this->rooms[$roomId];
            if (!isset($room['cards_used'][$playerId])) $room['cards_used'][$playerId] = [];
            $room['cards_used'][$playerId][] = $data['card_id'];
        }

        echo "[WS] {$relayType}: {$meta['player_name']} in {$roomId}\n";
    }

    // ════════════════════════════════════════════
    //  Absolute Reset — Reset match ke ronde 1 game 1
    // ════════════════════════════════════════════

    private function handleCardAbsoluteReset(ConnectionInterface $conn): void {
        $connId = $conn->resourceId;
        if (!isset($this->connRoom[$connId])) return;
        $roomId = $this->connRoom[$connId];
        $meta   = $this->clients[$conn] ?? null;
        if (!$meta || !isset($this->rooms[$roomId])) return;

        $room = &$this->rooms[$roomId];
        $ids  = $room['player_ids'];

        // Reset semua state room ke kondisi awal
        foreach ($ids as $pid) {
            $room['players'][$pid]['hp']     = HP_MAX;
            $room['players'][$pid]['wins']   = 0;
            $room['players'][$pid]['choice'] = null;
        }
        $room['round']             = 1;
        $room['phase']             = 'choosing';
        $room['rounds_log']        = [];
        $room['cards_picked']      = [];
        $room['continue_ready']    = [];
        $room['repeat_active']     = false;
        $room['repeat_owner']      = null;
        $room['block_one_next']    = null;
        $room['block_one_owner']   = null;
        $room['block_one_target']  = null;
        $room['block_one_active']  = null;
        $room['draw_streak']       = 0;

        // Track penggunaan kartu absolute_reset
        $pid_ar = $meta['player_id'];
        if (!isset($room['cards_used'][$pid_ar])) $room['cards_used'][$pid_ar] = [];
        $room['cards_used'][$pid_ar][] = 'absolute_reset';

        echo "[WS] card_absolute_reset: {$meta['player_name']} in {$roomId} — resetting to round 1\n";

        // Beritahu KEDUA player bahwa Absolute Reset diaktifkan
        // lalu langsung broadcast round_start ke ronde 1
        $this->broadcastRoom($roomId, [
            'type'      => 'absolute_reset_triggered',
            'player_id' => $meta['player_id'],
        ]);

        // Broadcast round_start baru ke ronde 1 dengan HP 100
        $this->broadcastRoom($roomId, [
            'type'             => 'round_start',
            'round'            => 1,
            'p1_id'            => $ids[0],
            'p2_id'            => $ids[1],
            'p1_hp'            => HP_MAX,
            'p2_hp'            => HP_MAX,
            'countdown'        => ROUND_TIME,
            'block_one_target' => null,
            'absolute_reset'   => true,  // flag agar client tahu ini dari absolute reset
        ]);
    }

    // ════════════════════════════════════════════
    //  Rematch
    // ════════════════════════════════════════════

    private function handleRematch(ConnectionInterface $conn): void {
        $connId = $conn->resourceId;
        if (!isset($this->connRoom[$connId])) return;
        $roomId = $this->connRoom[$connId];
        $meta   = $this->clients[$conn];
        $room   = &$this->rooms[$roomId];

        $room['rematch_votes'][$meta['player_id']] = true;

        $this->broadcastRoom($roomId, [
            'type'      => 'rematch_vote',
            'player_id' => $meta['player_id'],
        ]);

        if (count($room['rematch_votes']) >= 2) {
            $ids = $room['player_ids'];
            foreach ($ids as $pid) {
                $room['players'][$pid]['hp']     = HP_MAX;
                $room['players'][$pid]['wins']   = 0;
                $room['players'][$pid]['choice'] = null;
            }
            $room['round']         = 1;
            $room['phase']         = 'choosing';
            $room['rounds_log']    = [];
            $room['rematch_votes'] = [];
            $room['start_time']    = time();
            $room['repeat_active']    = false;
            $room['repeat_owner']     = null;
            $room['block_one_next']   = null;
            $room['block_one_owner']  = null;
            $room['block_one_target'] = null;
            $room['block_one_active'] = null;
            $room['draw_streak']      = 0;

            $this->broadcastRoom($roomId, ['type' => 'rematch_start', 'round' => 1]);
            $this->broadcastRoundStart($roomId);
        }
    }

    // ════════════════════════════════════════════
    //  Lobby Chat Handlers
    // ════════════════════════════════════════════

    private function handleChatAuth(ConnectionInterface $conn, array $data): void {
        $playerId   = trim($data['player_id']   ?? '');
        $playerName = trim($data['player_name'] ?? 'Unknown');
        $rating     = (int)($data['rating']     ?? 1000);
        if ($playerId === '') return;

        // Get avatar from DB
        $avatar = '⚔️';
        try {
            $row = getDB()->prepare("SELECT avatar FROM players WHERE id = ? LIMIT 1");
            $row->execute([$playerId]);
            $r = $row->fetch();
            if ($r && !empty($r['avatar'])) $avatar = $r['avatar'];
        } catch (\Throwable $e) {}

        // If player was already in chat (reconnect / tab re-open), clean old conn map
        if (isset($this->chatPlayers[$playerId])) {
            $oldConn = $this->chatPlayers[$playerId]['conn'];
            if (isset($this->chatConnMap[$oldConn->resourceId])) {
                unset($this->chatConnMap[$oldConn->resourceId]);
            }
        }

        // Register
        $this->chatPlayers[$playerId] = [
            'conn'   => $conn,
            'name'   => $playerName,
            'avatar' => $avatar,
            'rating' => $rating,
        ];
        $this->chatConnMap[$conn->resourceId] = $playerId;

        // Confirm auth to this player
        $conn->send(json_encode(['type' => 'chat_auth_ok']));

        // Kirim riwayat chat ke player yang baru/kembali join
        if (!empty($this->chatHistory)) {
            $conn->send(json_encode([
                'type'     => 'chat_history',
                'messages' => $this->chatHistory,
            ]));
        }

        // Send current online list to the NEW player immediately
        $playersList = [];
        foreach ($this->chatPlayers as $pid => $p) {
            $playersList[$pid] = [
                'name'   => $p['name'],
                'avatar' => $p['avatar'],
                'rating' => $p['rating'],
            ];
        }
        $conn->send(json_encode(['type' => 'chat_online_update', 'players' => $playersList]));

        // Broadcast updated online list to ALL (including new player)
        $this->broadcastChatOnlineUpdate();

        // Broadcast join message to everyone EXCEPT the joiner themselves
        $this->broadcastChat([
            'type' => 'chat_system',
            'msg'  => "➡️ {$playerName} masuk ke lobby",
        ], $playerId);

        echo "[Chat] {$playerName} joined lobby chat (total: " . count($this->chatPlayers) . ")\n";
    }

    private function handleChatSend(ConnectionInterface $conn, array $data): void {
        $playerId   = trim($data['player_id']   ?? '');
        $playerName = trim($data['player_name'] ?? '???');
        $text       = trim($data['text']        ?? '');

        if ($playerId === '' || $text === '') return;
        if (mb_strlen($text) > 200) $text = mb_substr($text, 0, 200);

        // Use server-stored avatar (authoritative)
        $avatar = $this->chatPlayers[$playerId]['avatar'] ?? '⚔️';
        // Also use server-stored name if available (prevents spoofing)
        $playerName = $this->chatPlayers[$playerId]['name'] ?? $playerName;

        $payload = [
            'type'        => 'chat_message',
            'player_id'   => $playerId,
            'player_name' => $playerName,
            'avatar'      => $avatar,
            'text'        => $text,
            'ts'          => time(), // epoch timestamp for consistent display
        ];

        // Broadcast to ALL chat players including sender (server is single source of truth)
        $this->broadcastChat($payload);

        // Simpan ke rolling history (max 50 pesan)
        $this->chatHistory[] = $payload;
        if (count($this->chatHistory) > 50) {
            array_shift($this->chatHistory);
        }

        // Simpan ke database
        if ($this->dbChatReady) {
            try {
                getDB()->prepare("
                    INSERT INTO lobby_chat_log (player_id, player_name, avatar, message, sent_at)
                    VALUES (?, ?, ?, ?, NOW())
                ")->execute([$playerId, $playerName, $avatar, $text]);
            } catch (\Throwable $e) {
                echo "[Chat] DB save error: {$e->getMessage()}\n";
            }
        }

        echo "[Chat] {$playerName}: {$text}\n";
    }

    private function handleChatLeave(ConnectionInterface $conn, array $data): void {
        $playerId = trim($data['player_id'] ?? '');
        if ($playerId === '' || !isset($this->chatPlayers[$playerId])) return;

        $name = $this->chatPlayers[$playerId]['name'];
        // Clean both maps
        unset($this->chatConnMap[$conn->resourceId]);
        unset($this->chatPlayers[$playerId]);
        // Broadcast updated list first, then system message
        $this->broadcastChatOnlineUpdate();
        $this->broadcastChat([
            'type' => 'chat_system',
            'msg'  => "⬅️ {$name} keluar dari lobby",
        ]);
        echo "[Chat] {$name} left lobby chat (total: " . count($this->chatPlayers) . ")\n";
    }

    /**
     * Broadcast payload to all chat players.
     * @param string $excludePlayerId  If set, skip this player (used for join-notify only)
     */
    private function broadcastChat(array $payload, string $excludePlayerId = ''): void {
        $json = json_encode($payload);
        foreach ($this->chatPlayers as $pid => $p) {
            if ($excludePlayerId !== '' && $pid === $excludePlayerId) continue;
            try { $p['conn']->send($json); } catch (\Throwable $e) {
                echo "[Chat] Send error to {$pid}: {$e->getMessage()}\n";
            }
        }
    }

    private function broadcastChatOnlineUpdate(): void {
        $playersList = [];
        foreach ($this->chatPlayers as $pid => $p) {
            $playersList[$pid] = [
                'name'   => $p['name'],
                'avatar' => $p['avatar'],
                'rating' => $p['rating'],
            ];
        }
        $payload = json_encode(['type' => 'chat_online_update', 'players' => $playersList]);
        foreach ($this->chatPlayers as $p) {
            try { $p['conn']->send($payload); } catch (\Throwable $e) {}
        }
    }

    // ════════════════════════════════════════════
    //  Utilities
    // ════════════════════════════════════════════

    private function getResult(string $c1, string $c2): string {
        if ($c1 === $c2) return 'draw';
        $beats = ['rock' => 'scissors', 'scissors' => 'paper', 'paper' => 'rock'];
        return ($beats[$c1] === $c2) ? 'p1' : 'p2';
    }

    private function broadcastRoom(string $roomId, array $payload): void {
        if (!isset($this->rooms[$roomId])) return;
        $json = json_encode($payload);
        foreach ($this->rooms[$roomId]['players'] as $pdata) {
            try {
                $pdata['conn']->send($json);
            } catch (\Throwable $e) {
                echo "[WS] Send error: {$e->getMessage()}\n";
            }
        }
    }

    private function broadcastRoomExcept(string $roomId, array $payload, string $exceptPlayerId): void {
        if (!isset($this->rooms[$roomId])) return;
        $json = json_encode($payload);
        foreach ($this->rooms[$roomId]['players'] as $pid => $pdata) {
            if ($pid === $exceptPlayerId) continue;
            try {
                $pdata['conn']->send($json);
            } catch (\Throwable $e) {
                echo "[WS] Send error: {$e->getMessage()}\n";
            }
        }
    }

    private function saveResult(string $roomId, ?string $winnerId): void {
        $room = $this->rooms[$roomId] ?? null;
        if (!$room) return;
        $ids = $room['player_ids'];

        // ── Rating delta: Win +10, Loss -10, Draw 0 ──
        $ratingWin  = 10;
        $ratingLoss = 10;

        try {
            $p1Rat = $room['players'][$ids[0]]['rating'] ?? 1000;
            $p2Rat = $room['players'][$ids[1]]['rating'] ?? 1000;

            // Hitung rating baru
            if ($winnerId === $ids[0]) {
                $p1NewRat = $p1Rat + $ratingWin;
                $p2NewRat = max(0, $p2Rat - $ratingLoss);
            } elseif ($winnerId === $ids[1]) {
                $p1NewRat = max(0, $p1Rat - $ratingLoss);
                $p2NewRat = $p2Rat + $ratingWin;
            } else {
                // Draw — rating tidak berubah
                $p1NewRat = $p1Rat;
                $p2NewRat = $p2Rat;
            }

            saveMatchHistory([
                'player1_id'           => $ids[0],
                'player2_id'           => $ids[1],
                'winner_id'            => $winnerId,
                'player1_round_wins'   => $room['players'][$ids[0]]['wins'],
                'player2_round_wins'   => $room['players'][$ids[1]]['wins'],
                'rounds'               => $room['rounds_log'],
                'duration_sec'         => time() - ($room['start_time'] ?? time()),
                'player1_rating_before'=> $p1Rat,
                'player2_rating_before'=> $p2Rat,
                'player1_rating_after' => $p1NewRat,
                'player2_rating_after' => $p2NewRat,
            ]);

            // ── Update wins/losses/draws (TANPA update rating — kita handle rating sendiri di bawah) ──
            if ($winnerId) {
                $loserId = ($ids[0] === $winnerId) ? $ids[1] : $ids[0];
                updatePlayerStats($winnerId, 'win');
                updatePlayerStats($loserId,  'loss');
            } else {
                updatePlayerStats($ids[0], 'draw');
                updatePlayerStats($ids[1], 'draw');
            }

            // ── Auto-unlock avatar berdasarkan misi (cek setelah stats di-update) ──
            try {
                $db = getDB();

                // Pastikan tabel avatar_unlocks ada
                $db->exec("CREATE TABLE IF NOT EXISTS avatar_unlocks (
                    id            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
                    player_id     VARCHAR(20)      NOT NULL,
                    avatar_index  TINYINT UNSIGNED NOT NULL COMMENT 'Index avatar 0-11',
                    unlocked_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE  KEY uq_av_player_index (player_id, avatar_index),
                    INDEX        idx_av_player     (player_id),
                    CONSTRAINT   fk_av_player
                        FOREIGN KEY (player_id) REFERENCES players (id)
                        ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

                // Cek dan unlock avatar untuk setiap player di room
                foreach ($ids as $pid) {
                    $pRow = $db->prepare("SELECT * FROM players WHERE id = ? LIMIT 1");
                    $pRow->execute([$pid]);
                    $pData = $pRow->fetch(\PDO::FETCH_ASSOC);
                    if (!$pData) continue;

                    $pw        = (int)($pData['wins']              ?? 0);
                    $pl        = (int)($pData['losses']            ?? 0);
                    $pd        = (int)($pData['draws']             ?? 0);
                    $paiw      = (int)($pData['ai_wins']           ?? 0);
                    $pstreak   = (int)($pData['max_win_streak']    ?? 0);
                    $prating   = (int)($pData['rating']            ?? 1000);
                    $pbio      = !empty($pData['bio']);
                    $ptotal    = $pw + $paiw;        // total menang PvP + AI
                    $ptotal_pvp= $pw + $pl + $pd;    // total match PvP

                    // Definisi kondisi per avatar_index (sama persis dgn profile.php)
                    $missionCheck = [
                        0  => true,
                        1  => $ptotal    >= 5,
                        2  => $ptotal    >= 10,
                        3  => $ptotal_pvp >= 1,
                        4  => $paiw      >= 5,
                        5  => $pstreak   >= 3,
                        6  => $paiw      >= 10,
                        7  => $prating   >= 1100,
                        8  => $ptotal    >= 20,
                        9  => $pbio,
                        10 => $pstreak   >= 5,
                        11 => $ptotal    >= 30,
                    ];

                    // Ambil yang sudah di-unlock
                    $existStmt = $db->prepare("SELECT avatar_index FROM avatar_unlocks WHERE player_id = ?");
                    $existStmt->execute([$pid]);
                    $alreadyUnlocked = array_column($existStmt->fetchAll(\PDO::FETCH_ASSOC), 'avatar_index');

                    $insertStmt = $db->prepare(
                        "INSERT IGNORE INTO avatar_unlocks (player_id, avatar_index) VALUES (?, ?)"
                    );

                    $newlyUnlocked = [];
                    foreach ($missionCheck as $avIdx => $condMet) {
                        if ($condMet && !in_array($avIdx, $alreadyUnlocked)) {
                            $insertStmt->execute([$pid, $avIdx]);
                            $newlyUnlocked[] = $avIdx;
                        }
                    }

                    if (!empty($newlyUnlocked)) {
                        // Kirim notifikasi avatar unlock ke player yang bersangkutan
                        if (isset($this->rooms[$roomId]['players'][$pid]['conn'])) {
                            try {
                                $this->rooms[$roomId]['players'][$pid]['conn']->send(json_encode([
                                    'type'             => 'avatar_unlocked',
                                    'avatar_indices'   => $newlyUnlocked,
                                ]));
                            } catch (\Throwable $e) {}
                        }
                        echo "[WS] Avatar unlocked for {$pid}: [" . implode(',', $newlyUnlocked) . "]\n";
                    }
                }
            } catch (\Throwable $avErr) {
                echo "[WS] Avatar unlock error: {$avErr->getMessage()}\n";
            }

            // Update total pilihan (rock/paper/scissors) dari rounds_log
            try {
                $choiceMap = [
                    $ids[0] => ['rock' => 0, 'paper' => 0, 'scissors' => 0],
                    $ids[1] => ['rock' => 0, 'paper' => 0, 'scissors' => 0],
                ];
                foreach ($room['rounds_log'] as $rnd) {
                    $c1 = $rnd['p1_choice'] ?? null;
                    $c2 = $rnd['p2_choice'] ?? null;
                    if ($c1 && isset($choiceMap[$ids[0]][$c1])) $choiceMap[$ids[0]][$c1]++;
                    if ($c2 && isset($choiceMap[$ids[1]][$c2])) $choiceMap[$ids[1]][$c2]++;
                }
                $db = getDB();
                $stmtChoice = $db->prepare(
                    "UPDATE players
                    SET total_rock     = total_rock     + ?,
                        total_paper    = total_paper    + ?,
                        total_scissors = total_scissors + ?,
                        updated_at     = NOW()
                    WHERE id = ?"
                );
                foreach ([$ids[0], $ids[1]] as $pid) {
                    $c = $choiceMap[$pid];
                    if ($c['rock'] + $c['paper'] + $c['scissors'] > 0) {
                        $stmtChoice->execute([$c['rock'], $c['paper'], $c['scissors'], $pid]);
                    }
                }
                echo "[WS] Choice stats updated for room {$roomId}\n";
            } catch (\Throwable $choiceErr) {
                echo "[WS] Choice stats DB Error: {$choiceErr->getMessage()}\n";
            }

            // Update statistik kartu favorit ke DB
            try {
                $db = getDB();
                $db->exec("
                    CREATE TABLE IF NOT EXISTS player_card_usage (
                        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                        player_id VARCHAR(20) NOT NULL,
                        card_id VARCHAR(40) NOT NULL,
                        use_count INT UNSIGNED NOT NULL DEFAULT 1,
                        last_used DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        UNIQUE KEY uniq_player_card (player_id, card_id),
                        INDEX idx_pcu_player (player_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
                $stmtCard = $db->prepare("
                    INSERT INTO player_card_usage (player_id, card_id, use_count, last_used)
                    VALUES (?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE
                        use_count = use_count + VALUES(use_count),
                        last_used = NOW()
                ");
                foreach ($room['cards_used'] as $pid => $cardList) {
                    $counted = array_count_values($cardList);
                    foreach ($counted as $cardId => $cnt) {
                        $stmtCard->execute([$pid, $cardId, $cnt]);
                    }
                }
                echo "[WS] Card usage stats updated for room {$roomId}\n";
            } catch (\Throwable $cardErr) {
                echo "[WS] Card usage DB Error: {$cardErr->getMessage()}\n";
            }

            // ── Update rating & peak_rating langsung ke DB (override apapun yang dilakukan updatePlayerStats) ──
            try {
                $db = getDB();
                $db->prepare(
                    "UPDATE players SET
                        rating = ?,
                        peak_rating = GREATEST(COALESCE(peak_rating, 0), ?)
                     WHERE id = ?"
                )->execute([$p1NewRat, $p1NewRat, $ids[0]]);

                $db->prepare(
                    "UPDATE players SET
                        rating = ?,
                        peak_rating = GREATEST(COALESCE(peak_rating, 0), ?)
                     WHERE id = ?"
                )->execute([$p2NewRat, $p2NewRat, $ids[1]]);

                echo "[WS] Rating updated: {$ids[0]} {$p1Rat}→{$p1NewRat} | {$ids[1]} {$p2Rat}→{$p2NewRat}\n";
            } catch (\Throwable $dbErr) {
                echo "[WS] Rating DB Error: {$dbErr->getMessage()}\n";
            }

            // ── Kirim rating_change ke masing-masing player ──
            $p1Delta   = $p1NewRat - $p1Rat;
            $p2Delta   = $p2NewRat - $p2Rat;
            $p1RankNew = $this->getRankTierName($p1NewRat);
            $p1RankOld = $this->getRankTierName($p1Rat);
            $p2RankNew = $this->getRankTierName($p2NewRat);
            $p2RankOld = $this->getRankTierName($p2Rat);

            if (isset($room['players'][$ids[0]]['conn'])) {
                try {
                    $room['players'][$ids[0]]['conn']->send(json_encode([
                        'type'          => 'rating_change',
                        'old_rating'    => $p1Rat,
                        'new_rating'    => $p1NewRat,
                        'delta'         => $p1Delta,
                        'rank_name'     => $p1RankNew,
                        'rank_up'       => ($p1RankNew !== $p1RankOld && $p1Delta > 0),
                        'rank_old_name' => $p1RankOld,
                    ]));
                } catch (\Throwable $e) {}
            }
            if (isset($room['players'][$ids[1]]['conn'])) {
                try {
                    $room['players'][$ids[1]]['conn']->send(json_encode([
                        'type'          => 'rating_change',
                        'old_rating'    => $p2Rat,
                        'new_rating'    => $p2NewRat,
                        'delta'         => $p2Delta,
                        'rank_name'     => $p2RankNew,
                        'rank_up'       => ($p2RankNew !== $p2RankOld && $p2Delta > 0),
                        'rank_old_name' => $p2RankOld,
                    ]));
                } catch (\Throwable $e) {}
            }

            echo "[WS] Match saved: room={$roomId} winner=" . ($winnerId ?? 'draw') . "\n";
        } catch (\Throwable $e) {
            echo "[WS] DB Error: {$e->getMessage()}\n";
        }
    }

    // ── Tentukan nama tier dari nilai rating ──
    private function getRankTierName(int $rating): string {
        if ($rating >= 2000) return 'GRANDMASTER';
        if ($rating >= 1700) return 'MASTER';
        if ($rating >= 1500) return 'DIAMOND';
        if ($rating >= 1300) return 'PLATINUM';
        if ($rating >= 1100) return 'GOLD';
        if ($rating >= 950)  return 'SILVER';
        return 'BRONZE';
    }
}

// ════════════════════════════════════════════
//  Boot Server
// ════════════════════════════════════════════
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new RpsGameServer()
        )
    ),
    WS_PORT,
    '0.0.0.0'
);

// ── Deteksi IP lokal otomatis ──────────────────
function getLocalIP(): string {
    // Cara 1: stream_socket (tidak butuh ext-sockets)
    $sock = @stream_socket_client('udp://8.8.8.8:53', $errno, $errstr, 1);
    if ($sock) {
        $name = stream_socket_get_name($sock, false);
        fclose($sock);
        $ip = parse_url('udp://' . $name, PHP_URL_HOST);
        if ($ip && $ip !== '0.0.0.0') return $ip;
    }
    // Cara 2: hostname lookup
    $host = gethostname();
    $ip   = gethostbyname($host);
    if ($ip && $ip !== $host) return $ip;
    // Cara 3: ipconfig (Windows) / hostname -I (Linux/Mac)
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $out = shell_exec('ipconfig');
        if (preg_match('/IPv4[^\d]+([\d]+\.[\d]+\.[\d]+\.[\d]+)/i', $out ?? '', $m)) {
            return $m[1];
        }
    } else {
        $out = shell_exec('hostname -I 2>/dev/null');
        $parts = explode(' ', trim($out ?? ''));
        if (!empty($parts[0])) return $parts[0];
    }
    return '127.0.0.1';
}

$localIP = getLocalIP();
$pad = fn(string $s, int $n) => $s . str_repeat(' ', max(0, $n - strlen($s)));

echo "\n";
echo "╔══════════════════════════════════════════════════╗\n";
echo "║     Lucky Battle — WebSocket Server RUNNING      ║\n";
echo "╠══════════════════════════════════════════════════╣\n";
echo "║  " . $pad("Port    : " . WS_PORT, 48) . "║\n";
echo "║  " . $pad("Local   : ws://localhost:" . WS_PORT, 48) . "║\n";
echo "║  " . $pad("Network : ws://" . $localIP . ":" . WS_PORT, 48) . "║\n";
echo "╠══════════════════════════════════════════════════╣\n";
echo "║  Laptop lain buka browser ke:                    ║\n";
echo "║  " . $pad("http://" . $localIP . "/Game-baru/lucky-battle/", 48) . "║\n";
echo "╚══════════════════════════════════════════════════╝\n";
echo "\n";

$server->run();