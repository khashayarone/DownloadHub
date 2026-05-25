<?php
/**
 * ═══════════════════════════════════════════════════
 * Bale DownloadHub Bot — PHP 8.2 (v7 — Public Release)
 * ═══════════════════════════════════════════════════
 * 
 * یه ربات همه‌کاره برای دانلود از یوتیوب، اینستاگرام، تیک‌تاک،
 * ساندکلاد، فیسبوک، تلگرام، اینترنت آرشیو و ۱۸۰۰+ سایت دیگه
 * 
 * ویژگی‌ها:
 *  - سیستم کش دو لایه (کپی فایل بین کانال‌ها — زیر ۱ ثانیه)
 *  - آپلود مستقیم توی کانال شخصی کاربر
 *  - پشتیبانی از اشتراک پریمیوم (بدون محدودیت حجم)
 *  - split خودکار فایل‌های بزرگ با WinRAR برای کاربران ویژه
 *  - پنل مدیریت کامل با آمار و لاگ
 *  - سیستم پشتیبانی مستقیم
 * 
 * Repository: github.com/khashayardev/DownloadHub
 * ═══════════════════════════════════════════════════
 */

// ═══════════════════════════════════════════════════
// CONFIGURATION — این بخش رو با اطلاعات خودت پر کن
// ═══════════════════════════════════════════════════

define('BALE_BOT_TOKEN', 'اینجا_توکن_رباتت_رو_بذار');
define('GITHUB_TOKEN', 'اینجا_Personal_Access_Token_گیت‌هاب_رو_بذار');
define('GITHUB_REPO_OWNER', 'یوزرنیم_گیت‌هاب_تو');
define('GITHUB_REPO_NAME', 'اسم_ریپوی_فورک‌شده');
define('ADMIN_USER_ID', 0); // آیدی عددی خودت توی بله — با @userinfobot میتونی بگیری
define('SPONSOR_CHANNEL', '@آیدی_کانال_اسپانسر');

// ── Premium Settings ──────────────────────────────
define('PREMIUM_ENABLED', false); // true = فعال کردن خرید اشتراک
define('PREMIUM_PROVIDER_TOKEN', 'WALLET-TEST-1111111111111111'); // از @botfather بگیر
define('MAX_FREE_SIZE_MB', 45);
define('MAX_FREE_SIZE_BYTES', 47185920);
define('PREMIUM_PRICE_RIAL', 500000); // ۵۰ هزار تومن

define('DATA_DIR', __DIR__ . '/bot_data');
define('DB_FILE', DATA_DIR . '/downloadhub.db');
define('BALE_API', 'https://tapi.bale.ai/bot' . BALE_BOT_TOKEN);
define('GITHUB_API', 'https://api.github.com/repos/' . GITHUB_REPO_OWNER . '/' . GITHUB_REPO_NAME);

define('WORKFLOW_MAP', json_encode([
    'youtube'    => 'yt-dl.yml',
    'soundcloud' => 'soundcloud-dl.yml',
    'instagram'  => 'instagram-dl.yml',
    'tiktok'     => 'tiktok-dl.yml',
    'facebook'   => 'facebook-dl.yml',
    'telegram'   => 'telegram-dl.yml',
    'archive'    => 'archive-dl.yml',
    'generic'    => 'generic-dl.yml',
]));

define('URL_PATTERNS', json_encode([
    'youtube'    => '#^(https?://)?(www\.)?(youtube\.com/(watch\?v=|shorts/|playlist\?list=|channel/|@|c/)|youtu\.be/|music\.youtube\.com/watch)#i',
    'soundcloud' => '#^(https?://)?(www\.)?(soundcloud\.com|on\.soundcloud\.com)/#i',
    'instagram'  => '#^(https?://)?(www\.)?instagram\.com/(p/|reel/|tv/|stories/)#i',
    'tiktok'     => '#^(https?://)?(www\.)?(tiktok\.com|vm\.tiktok\.com|vt\.tiktok\.com)/#i',
    'facebook'   => '#^(https?://)?(www\.)?(facebook\.com|fb\.com|fb\.watch)/#i',
    'telegram'   => '#^(https?://)?(www\.)?t\.me/([a-zA-Z0-9_]+)(/(\d+))?#i',
    'archive'    => '#^https?://archive\.org/details/([a-zA-Z0-9_\-\.]+)#i',
    'generic'    => '#^https?://#i',
]));

// ═══════════════════════════════════════════════════
// DATABASE LAYER — SQLite
// ═══════════════════════════════════════════════════

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
        $pdo = new PDO('sqlite:' . DB_FILE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("PRAGMA journal_mode=WAL");
        $pdo->exec("PRAGMA busy_timeout=5000");
        init_tables($pdo);
    }
    return $pdo;
}

function init_tables(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            user_id INTEGER PRIMARY KEY,
            first_name TEXT DEFAULT '',
            username TEXT DEFAULT '',
            state TEXT DEFAULT 'CHECK_SPONSOR',
            archive_channel_id TEXT DEFAULT '',
            archive_channel_username TEXT DEFAULT '',
            is_premium INTEGER DEFAULT 0,
            premium_expires_at INTEGER DEFAULT 0,
            is_blocked INTEGER DEFAULT 0,
            joined_sponsor INTEGER DEFAULT 0,
            last_message_id INTEGER DEFAULT 0,
            last_chat_id INTEGER DEFAULT 0,
            current_platform TEXT DEFAULT '',
            pending_url TEXT DEFAULT '',
            pending_quality TEXT DEFAULT '',
            created_at INTEGER DEFAULT (strftime('%s','now')),
            updated_at INTEGER DEFAULT (strftime('%s','now'))
        );

        CREATE TABLE IF NOT EXISTS file_cache (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            cache_key TEXT NOT NULL UNIQUE,
            original_url TEXT NOT NULL,
            platform TEXT NOT NULL,
            quality TEXT NOT NULL,
            file_id TEXT NOT NULL,
            message_id INTEGER NOT NULL,
            source_channel_id TEXT NOT NULL,
            source_channel_username TEXT DEFAULT '',
            file_size INTEGER DEFAULT 0,
            file_name TEXT DEFAULT '',
            is_multipart INTEGER DEFAULT 0,
            total_parts INTEGER DEFAULT 1,
            hit_count INTEGER DEFAULT 1,
            last_hit_at INTEGER DEFAULT (strftime('%s','now')),
            created_at INTEGER DEFAULT (strftime('%s','now'))
        );

        CREATE TABLE IF NOT EXISTS queue (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            platform TEXT NOT NULL,
            url TEXT NOT NULL,
            quality TEXT DEFAULT 'best',
            status TEXT DEFAULT 'pending',
            github_run_id TEXT DEFAULT '',
            cache_hit INTEGER DEFAULT 0,
            error_message TEXT DEFAULT '',
            file_size INTEGER DEFAULT 0,
            file_name TEXT DEFAULT '',
            created_at INTEGER DEFAULT (strftime('%s','now')),
            updated_at INTEGER DEFAULT (strftime('%s','now')),
            FOREIGN KEY (user_id) REFERENCES users(user_id)
        );

        CREATE TABLE IF NOT EXISTS support_messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            message_text TEXT NOT NULL,
            reply_text TEXT DEFAULT '',
            is_replied INTEGER DEFAULT 0,
            created_at INTEGER DEFAULT (strftime('%s','now')),
            replied_at INTEGER DEFAULT NULL
        );

        CREATE TABLE IF NOT EXISTS admin_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            event_type TEXT NOT NULL,
            user_id INTEGER DEFAULT 0,
            message TEXT DEFAULT '',
            created_at INTEGER DEFAULT (strftime('%s','now'))
        );

        CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT DEFAULT ''
        );

        CREATE TABLE IF NOT EXISTS connection_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            status TEXT DEFAULT 'unknown',
            response_time_ms INTEGER DEFAULT 0,
            error_message TEXT DEFAULT '',
            created_at INTEGER DEFAULT (strftime('%s','now'))
        );
    ");
    
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_cache_key ON file_cache(cache_key)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_cache_platform ON file_cache(platform)");

    $stmt = $pdo->prepare("INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)");
    $stmt->execute(['paused', '0']);
    $stmt->execute(['last_connection_check', '0']);
}

// ═══════════════════════════════════════════════════
// USER OPERATIONS
// ═══════════════════════════════════════════════════

function get_user(int $user_id): ?array {
    $stmt = db()->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch() ?: null;
}

function upsert_user(int $user_id, array $data): void {
    $existing = get_user($user_id);
    if ($existing) {
        $fields = []; $values = [];
        foreach ($data as $k => $v) { $fields[] = "$k = ?"; $values[] = $v; }
        $fields[] = "updated_at = strftime('%s','now')"; $values[] = $user_id;
        db()->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE user_id = ?")->execute($values);
    } else {
        $data['user_id'] = $user_id;
        $defaults = ['state'=>'CHECK_SPONSOR','archive_channel_id'=>'','archive_channel_username'=>'','last_message_id'=>0,'last_chat_id'=>0,'current_platform'=>'','pending_url'=>'','pending_quality'=>'','joined_sponsor'=>0,'is_premium'=>0,'first_name'=>'','username'=>''];
        foreach ($defaults as $k => $v) if (!isset($data[$k])) $data[$k] = $v;
        $fields = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        db()->prepare("INSERT INTO users ($fields) VALUES ($placeholders)")->execute(array_values($data));
    }
}

function get_all_users(): array { return db()->query("SELECT * FROM users")->fetchAll(); }
function get_user_count(): int { return (int)db()->query("SELECT COUNT(*) FROM users")->fetchColumn(); }

// ═══════════════════════════════════════════════════
// FILE CACHE OPERATIONS (Two-Layer System)
// ═══════════════════════════════════════════════════

function make_cache_key(string $url, string $quality): string {
    return md5(trim($url) . '_' . $quality);
}

function cache_file(string $cache_key, string $url, string $platform, string $quality, string $file_id, int $message_id, string $channel_id, string $channel_username = '', int $file_size = 0, string $file_name = '', bool $is_multipart = false, int $total_parts = 1): void {
    db()->prepare("INSERT OR REPLACE INTO file_cache (cache_key, original_url, platform, quality, file_id, message_id, source_channel_id, source_channel_username, file_size, file_name, is_multipart, total_parts) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
      ->execute([$cache_key, $url, $platform, $quality, $file_id, $message_id, $channel_id, $channel_username, $file_size, $file_name, $is_multipart ? 1 : 0, $total_parts]);
}

function get_cached_file(string $cache_key): ?array {
    $stmt = db()->prepare("SELECT * FROM file_cache WHERE cache_key = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$cache_key]);
    $cached = $stmt->fetch();
    if ($cached) {
        db()->prepare("UPDATE file_cache SET hit_count = hit_count + 1, last_hit_at = strftime('%s','now') WHERE id = ?")->execute([$cached['id']]);
    }
    return $cached ?: null;
}

function remove_cached_file(int $id): void {
    db()->prepare("DELETE FROM file_cache WHERE id = ?")->execute([$id]);
}

function get_cache_stats(): array {
    $total = (int)db()->query("SELECT COUNT(*) FROM file_cache")->fetchColumn();
    $hits = (int)db()->query("SELECT COALESCE(SUM(hit_count), 0) FROM file_cache")->fetchColumn();
    $size = (int)db()->query("SELECT COALESCE(SUM(file_size), 0) FROM file_cache")->fetchColumn();
    $platforms = db()->query("SELECT platform, COUNT(*) as cnt FROM file_cache GROUP BY platform ORDER BY cnt DESC")->fetchAll();
    return ['total_files' => $total, 'total_hits' => $hits, 'total_size_mb' => round($size/1048576, 1), 'platforms' => $platforms];
}

// ═══════════════════════════════════════════════════
// QUEUE OPERATIONS
// ═══════════════════════════════════════════════════

function add_to_queue(int $user_id, string $platform, string $url, string $quality, string $run_id = '', bool $cache_hit = false): int {
    $status = $cache_hit ? 'completed' : 'dispatched';
    db()->prepare("INSERT INTO queue (user_id, platform, url, quality, status, github_run_id, cache_hit) VALUES (?, ?, ?, ?, ?, ?, ?)")
      ->execute([$user_id, $platform, $url, $quality, $status, $run_id, $cache_hit ? 1 : 0]);
    return (int)db()->lastInsertId();
}

function get_user_queue(int $user_id): array {
    $stmt = db()->prepare("SELECT * FROM queue WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

function get_active_queue_count(): int {
    return (int)db()->query("SELECT COUNT(*) FROM queue WHERE status IN ('pending', 'dispatched')")->fetchColumn();
}

function get_queue_stats(): array {
    db()->exec("UPDATE queue SET status = 'completed', updated_at = strftime('%s','now') WHERE status = 'dispatched' AND updated_at < strftime('%s','now') - 7200");
    $stats = [];
    foreach (['pending','dispatched','completed','failed','cancelled'] as $s) {
        $stmt = db()->prepare("SELECT COUNT(*) FROM queue WHERE status = ?");
        $stmt->execute([$s]);
        $stats[$s] = (int)$stmt->fetchColumn();
    }
    return $stats;
}

function cancel_job(int $job_id, int $user_id): bool {
    return db()->prepare("UPDATE queue SET status='cancelled', updated_at=strftime('%s','now') WHERE id=? AND user_id=? AND status IN ('pending','dispatched')")
      ->execute([$job_id, $user_id])->rowCount() > 0;
}

function cancel_all_user_jobs(int $user_id): int {
    return db()->prepare("UPDATE queue SET status='cancelled', updated_at=strftime('%s','now') WHERE user_id=? AND status IN ('pending','dispatched')")
      ->execute([$user_id])->rowCount();
}

function get_platform_stats(): array {
    db()->exec("UPDATE queue SET status='completed', updated_at=strftime('%s','now') WHERE status='dispatched' AND updated_at < strftime('%s','now') - 7200");
    $stats = [];
    foreach (db()->query("SELECT platform, status, COUNT(*) as cnt FROM queue GROUP BY platform, status")->fetchAll() as $row) {
        $stats[$row['platform']][$row['status']] = (int)$row['cnt'];
    }
    return $stats;
}

// ═══════════════════════════════════════════════════
// SETTINGS
// ═══════════════════════════════════════════════════

function is_paused(): bool {
    $stmt = db()->prepare("SELECT value FROM settings WHERE key='paused'");
    $stmt->execute();
    return $stmt->fetchColumn() === '1';
}
function set_paused(bool $v): void { db()->prepare("UPDATE settings SET value=? WHERE key='paused'")->execute([$v?'1':'0']); }

// ═══════════════════════════════════════════════════
// SUPPORT
// ═══════════════════════════════════════════════════

function add_support_message(int $uid, string $text): int {
    db()->prepare("INSERT INTO support_messages (user_id, message_text) VALUES (?,?)")->execute([$uid, $text]);
    return (int)db()->lastInsertId();
}
function get_support_message(int $id): ?array {
    $stmt = db()->prepare("SELECT * FROM support_messages WHERE id=?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}
function mark_support_replied(int $id, string $reply): void {
    db()->prepare("UPDATE support_messages SET reply_text=?, is_replied=1, replied_at=strftime('%s','now') WHERE id=?")->execute([$reply, $id]);
}

// ═══════════════════════════════════════════════════
// LOGS
// ═══════════════════════════════════════════════════

function add_admin_log(string $event, int $uid = 0, string $msg = ''): void {
    db()->prepare("INSERT INTO admin_logs (event_type, user_id, message) VALUES (?,?,?)")->execute([$event, $uid, $msg]);
}
function add_connection_log(string $status, int $rt, string $err = ''): void {
    db()->prepare("INSERT INTO connection_logs (status, response_time_ms, error_message) VALUES (?,?,?)")->execute([$status, $rt, $err]);
}

// ═══════════════════════════════════════════════════
// BALE API
// ═══════════════════════════════════════════════════

function bale_request(string $method, array $params = []): mixed {
    $ch = curl_init(BALE_API . '/' . $method);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($params),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
    ]);
    $res = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($res === false || $code !== 200) return null;
    $data = json_decode($res, true);
    return (is_array($data) && ($data['ok'] ?? false)) ? ($data['result'] ?? true) : null;
}

function bale_get_request(string $method, array $params = []): mixed {
    $q = http_build_query($params);
    $ch = curl_init(BALE_API . '/' . $method . ($q ? '?' . $q : ''));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
    $res = curl_exec($ch); curl_close($ch);
    if ($res === false) return null;
    $data = json_decode($res, true);
    return (is_array($data) && ($data['ok'] ?? false)) ? ($data['result'] ?? null) : null;
}

function send_message(int $chat_id, string $text, ?array $rm = null): ?int {
    $p = ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'Markdown'];
    if ($rm) $p['reply_markup'] = json_encode($rm);
    $r = bale_request('sendMessage', $p);
    return (is_array($r) && isset($r['message_id'])) ? (int)$r['message_id'] : null;
}

function edit_message_text(int $chat_id, int $msg_id, string $text, ?array $rm = null): bool {
    $p = ['chat_id' => $chat_id, 'message_id' => $msg_id, 'text' => $text, 'parse_mode' => 'Markdown'];
    if ($rm) $p['reply_markup'] = json_encode($rm);
    return bale_request('editMessageText', $p) !== null;
}

function answer_callback_query(string $cb_id, string $text = '', bool $alert = false): bool {
    $p = ['callback_query_id' => $cb_id];
    if ($text !== '') $p['text'] = $text;
    if ($alert) $p['show_alert'] = true;
    return bale_request('answerCallbackQuery', $p) !== null;
}

function copy_message(int $chat_id, string $from_chat_id, int $message_id): ?int {
    $r = bale_request('copyMessage', ['chat_id' => $chat_id, 'from_chat_id' => $from_chat_id, 'message_id' => $message_id]);
    return (is_array($r) && isset($r['message_id'])) ? (int)$r['message_id'] : null;
}

function get_chat(string $chat_id): ?array { $r = bale_get_request('getChat', ['chat_id' => $chat_id]); return is_array($r) ? $r : null; }
function get_chat_member(string $chat_id, int $user_id): ?array {
    $r = bale_get_request('getChatMember', ['chat_id' => $chat_id, 'user_id' => $user_id]);
    return is_array($r) ? $r : null;
}
function get_me(): ?array { $r = bale_get_request('getMe'); return is_array($r) ? $r : null; }

function check_bale_connection(): array {
    $start = microtime(true); $r = get_me(); $rt = round((microtime(true)-$start)*1000);
    $ok = $r && isset($r['id']);
    add_connection_log($ok ? 'success' : 'failed', $rt);
    return ['status' => $ok ? 'success' : 'failed', 'response_time' => $rt];
}

// ═══════════════════════════════════════════════════
// GITHUB API
// ═══════════════════════════════════════════════════

function dispatch_workflow(string $platform, array $inputs): ?string {
    $map = json_decode(WORKFLOW_MAP, true); $wf = $map[$platform] ?? $map['generic'];
    $ch = curl_init(GITHUB_API . '/actions/workflows/' . $wf . '/dispatches');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode(['ref' => 'main', 'inputs' => $inputs]),
        CURLOPT_HTTPHEADER => ['Authorization: Bearer '.GITHUB_TOKEN, 'Accept: application/vnd.github.v3+json', 'Content-Type: application/json', 'User-Agent: Bale-DownloadHub/1.0'],
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
    ]);
    curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code === 204) { sleep(2); return get_latest_run_id($wf); }
    return null;
}

function get_latest_run_id(string $wf): ?string {
    $ch = curl_init(GITHUB_API . '/actions/workflows/' . $wf . '/runs?per_page=1');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Authorization: Bearer '.GITHUB_TOKEN, 'Accept: application/vnd.github.v3+json', 'User-Agent: Bale-DownloadHub/1.0'],
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
    ]);
    $res = curl_exec($ch); curl_close($ch);
    $data = json_decode($res, true);
    return !empty($data['workflow_runs']) ? (string)$data['workflow_runs'][0]['id'] : null;
}

// ═══════════════════════════════════════════════════
// URL VALIDATION
// ═══════════════════════════════════════════════════

function validate_url(string $url, string $platform): bool {
    $p = json_decode(URL_PATTERNS, true);
    return (bool)preg_match($p[$platform] ?? $p['generic'], trim($url));
}

function detect_platform(string $url): string {
    foreach (['youtube','soundcloud','instagram','tiktok','facebook','telegram','archive'] as $p) {
        if (validate_url($url, $p)) return $p;
    }
    return 'generic';
}

// ═══════════════════════════════════════════════════
// KEYBOARDS
// ═══════════════════════════════════════════════════

function kb_sponsor(): array {
    $ch = ltrim(SPONSOR_CHANNEL, '@');
    return ['inline_keyboard' => [
        [['text' => '🔗 عضویت در کانال اسپانسر', 'url' => "https://ble.ir/{$ch}"]],
        [['text' => '✅ عضو شدم — بررسی کن', 'callback_data' => 'check_sponsor']],
    ]];
}

function kb_archive_setup(): array {
    return ['inline_keyboard' => [[['text' => '📖 راهنمای ساخت کانال', 'callback_data' => 'guide_archive']]]];
}

function kb_main_menu(bool $is_admin, bool $is_premium = false): array {
    $kb = [
        [['text' => '📂 تنظیم مجدد کانال آرشیو', 'callback_data' => 'setup_archive']],
        [['text' => '🎬 YouTube', 'callback_data' => 'platform_youtube'], ['text' => '🎧 SoundCloud', 'callback_data' => 'platform_soundcloud']],
        [['text' => '📸 Instagram', 'callback_data' => 'platform_instagram'], ['text' => '🎵 TikTok', 'callback_data' => 'platform_tiktok']],
        [['text' => '📘 Facebook', 'callback_data' => 'platform_facebook'], ['text' => '📨 Telegram', 'callback_data' => 'platform_telegram']],
        [['text' => '📚 Internet Archive', 'callback_data' => 'platform_archive'], ['text' => '🌐 سایت دیگر', 'callback_data' => 'platform_generic']],
        [['text' => '📊 وضعیت درخواست‌ها', 'callback_data' => 'queue_status'], ['text' => '❌ لغو همه', 'callback_data' => 'cancel_all']],
        [['text' => '📞 پشتیبانی', 'callback_data' => 'support']],
    ];
    if (PREMIUM_ENABLED && !$is_premium) {
        $kb[] = [['text' => '⭐ خرید اشتراک پریمیوم (نامحدود)', 'callback_data' => 'buy_premium']];
    }
    if ($is_admin) $kb[] = [['text' => '🔐 پنل مدیریت', 'callback_data' => 'admin_panel']];
    return ['inline_keyboard' => $kb];
}

function kb_quality(string $platform, string $back = 'back_to_main'): array {
    $kb = ['inline_keyboard' => []];
    if (in_array($platform, ['youtube','facebook','telegram'])) {
        $kb['inline_keyboard'][] = [['text' => '📹 720p', 'callback_data' => 'quality_720'], ['text' => '📹 480p', 'callback_data' => 'quality_480']];
        $kb['inline_keyboard'][] = [['text' => '🎵 فقط صوت (MP3)', 'callback_data' => 'quality_audio']];
    } elseif ($platform === 'soundcloud') {
        $kb['inline_keyboard'][] = [['text' => '🎵 کیفیت بالا', 'callback_data' => 'quality_high'], ['text' => '🎵 کیفیت متوسط', 'callback_data' => 'quality_medium']];
    } elseif ($platform === 'archive') {
        $kb['inline_keyboard'][] = [['text' => '📕 PDF', 'callback_data' => 'quality_pdf'], ['text' => '📗 EPUB', 'callback_data' => 'quality_epub']];
        $kb['inline_keyboard'][] = [['text' => '📘 TXT', 'callback_data' => 'quality_txt'], ['text' => '📙 DJVU', 'callback_data' => 'quality_djvu']];
    } else {
        $kb['inline_keyboard'][] = [['text' => '📹 720p', 'callback_data' => 'quality_720'], ['text' => '📹 480p', 'callback_data' => 'quality_480']];
        $kb['inline_keyboard'][] = [['text' => '🎵 فقط صوت', 'callback_data' => 'quality_audio']];
    }
    $kb['inline_keyboard'][] = [['text' => '🔙 بازگشت', 'callback_data' => $back]];
    return $kb;
}

function kb_confirm(): array {
    return ['inline_keyboard' => [[['text' => '✅ تأیید و شروع', 'callback_data' => 'confirm_download'], ['text' => '🔙 کیفیت دیگر', 'callback_data' => 'back_to_quality']]]];
}

function kb_back(string $cb = 'back_to_main'): array {
    return ['inline_keyboard' => [[['text' => '🔙 بازگشت', 'callback_data' => $cb]]]];
}

function kb_admin(): array {
    $p = is_paused();
    return ['inline_keyboard' => [
        [['text' => $p ? '▶️ فعال‌سازی' : '⏸️ توقف موقت', 'callback_data' => 'toggle_pause']],
        [['text' => '📊 آمار پلتفرم‌ها', 'callback_data' => 'admin_stats'], ['text' => '💾 وضعیت کش', 'callback_data' => 'admin_cache']],
        [['text' => '📢 پیام همگانی', 'callback_data' => 'admin_broadcast'], ['text' => '📋 لاگ‌ها', 'callback_data' => 'admin_logs']],
        [['text' => '🌐 اتصال API', 'callback_data' => 'admin_connection'], ['text' => '👥 کاربران', 'callback_data' => 'admin_users']],
        [['text' => '🚫 پاک کردن صف', 'callback_data' => 'admin_clear_queue']],
        [['text' => '🔙 بازگشت به منوی اصلی', 'callback_data' => 'back_to_main']],
    ]];
}

// ═══════════════════════════════════════════════════
// MESSAGE TEMPLATES
// ═══════════════════════════════════════════════════

function msg_welcome(): string {
    return "🎉 *به DownloadHub خوش آمدید!*\n\n📥 *دانلود از:*\n🎬 YouTube | 🎧 SoundCloud\n📱 Instagram | 🎵 TikTok\n📘 Facebook | 📨 Telegram\n📚 Internet Archive | 🌍 ۱۸۰۰+ سایت\n\n━━━━━━━━━━━━━━━━━━\n⚠️ *ابتدا در کانال اسپانسر عضو شوید:*";
}
function msg_sponsor_ok(): string { return "✅ *عضویت تأیید شد!*\n\n📂 *مرحله بعد:* راه‌اندازی کانال آرشیو"; }
function msg_archive_success(string $dn): string { return "🎉 *تبریک!*\n\n✅ کانال: `{$dn}`\n\n🎯 *حالا دانلود کنید:*"; }
function msg_archive_fail_not_admin(): string { return "❌ *ربات مدیر کانال نیست!*\n\nربات را *مدیر* کانال کنید."; }
function msg_archive_fail_not_channel(): string { return "❌ *پیام Forward معتبر نیست!*\n\nاز *کانال عمومی* Forward کنید."; }
function msg_archive_guide(): string { return "📖 *راهنما:*\n۱. کانال عمومی بسازید\n۲. ربات را *مدیر* کانال کنید\n۳. پیام را Forward کنید\nیا شناسه بدون @ بفرستید"; }
function msg_main_menu(): string { return "🎯 *منوی اصلی*\n\nپلتفرم مورد نظر را انتخاب کنید:"; }

function msg_await_link(string $platform): string {
    $n = ['youtube'=>'YouTube','soundcloud'=>'SoundCloud','instagram'=>'Instagram','tiktok'=>'TikTok','facebook'=>'Facebook','telegram'=>'Telegram','archive'=>'Internet Archive','generic'=>'سایت مورد نظر'];
    return "🔗 *دانلود از " . ($n[$platform]??$platform) . "*\n\nلطفاً لینک را ارسال کنید.";
}
function msg_invalid_link(string $platform): string {
    $n = ['youtube'=>'YouTube','soundcloud'=>'SoundCloud','instagram'=>'Instagram','tiktok'=>'TikTok','facebook'=>'Facebook','telegram'=>'Telegram','archive'=>'Internet Archive','generic'=>'سایت'];
    return "❌ *لینک نامعتبر!*\n\nلینک معتبر از *" . ($n[$platform]??$platform) . "* ارسال کنید.";
}
function msg_select_quality(string $url, string $platform): string {
    $s = mb_strlen($url) > 50 ? mb_substr($url, 0, 47) . '...' : $url;
    $n = ['youtube'=>'YouTube','soundcloud'=>'SoundCloud','instagram'=>'Instagram','tiktok'=>'TikTok','facebook'=>'Facebook','telegram'=>'Telegram','archive'=>'Internet Archive','generic'=>'Generic'];
    if ($platform === 'archive') {
        return "📚 *انتخاب فرمت - Internet Archive*\n\n🔗 `{$s}`\n\nفرمت کتاب را انتخاب کنید:";
    }
    return "📊 *انتخاب کیفیت - " . ($n[$platform]??$platform) . "*\n\n🔗 `{$s}`\n\nکیفیت را انتخاب کنید:";
}
function msg_confirm(string $platform, string $url, string $quality): string {
    $n = ['youtube'=>'YouTube','soundcloud'=>'SoundCloud','instagram'=>'Instagram','tiktok'=>'TikTok','facebook'=>'Facebook','telegram'=>'Telegram','archive'=>'Internet Archive','generic'=>'Generic'];
    $qf = ['best'=>'🎬 بهترین','720'=>'📹 720p','480'=>'📹 480p','audio'=>'🎵 صوت','high'=>'🎵 بالا','medium'=>'🎵 متوسط','pdf'=>'📕 PDF','epub'=>'📗 EPUB','txt'=>'📘 TXT','djvu'=>'📙 DJVU'];
    return "📋 *تأیید نهایی*\n\n🎯 " . ($n[$platform]??$platform) . "\n🔗 `{$url}`\n📊 " . ($qf[$quality]??$quality) . "\n\nتأیید می‌کنید؟";
}
function msg_download_started(string $platform, int $job_id, string $url, bool $from_cache = false): string {
    $n = ['youtube'=>'YouTube','soundcloud'=>'SoundCloud','instagram'=>'Instagram','tiktok'=>'TikTok','facebook'=>'Facebook','telegram'=>'Telegram','archive'=>'Internet Archive','generic'=>'Generic'];
    $s = mb_strlen($url) > 40 ? mb_substr($url, 0, 37) . '...' : $url;
    if ($from_cache) return "⚡ *از حافظه کش ارسال شد!*\n\n🎯 " . ($n[$platform]??$platform) . "\n🔗 `{$s}`\n🆔 #{$job_id}\n⏱ < ۱ ثانیه";
    return "✅ *دانلود شروع شد!*\n\n🎯 " . ($n[$platform]??$platform) . "\n🔗 `{$s}`\n🆔 #{$job_id}\n\n⏳ در حال پردازش...";
}
function msg_queue_status(int $uid): string {
    $jobs = get_user_queue($uid);
    if (empty($jobs)) return "📊 *هیچ درخواستی ثبت نشده.*";
    $t = "📊 *وضعیت درخواست‌ها:*\n\n"; $ic = ['pending'=>'📥','dispatched'=>'🚀','completed'=>'✅','failed'=>'❌','cancelled'=>'🚫'];
    foreach ($jobs as $j) { $icon=$ic[$j['status']]??'❓'; $us=mb_strlen($j['url'])>40?mb_substr($j['url'],0,37).'...':$j['url']; $t.="{$icon} #{$j['id']} | {$j['platform']}".($j['cache_hit']?' ⚡':'')."\n`{$us}`\n"; if($j['error_message'])$t.="⚠️ {$j['error_message']}\n"; $t.="━━━━━━━━━━━━━━━━━━\n"; }
    return $t;
}
function msg_admin_panel(): string {
    $p=is_paused(); $u=get_user_count(); $s=get_queue_stats();
    return "🔐 *پنل مدیریت*\n\n👥 کاربران: {$u}\n📥 صف: {$s['pending']}\n🚀 در حال اجرا: {$s['dispatched']}\n✅ تکمیل: {$s['completed']}\n❌ ناموفق: {$s['failed']}\n🚫 لغو: {$s['cancelled']}\n⏸️ وضعیت: ".($p?'🔴 متوقف':'🟢 فعال');
}
function msg_platform_stats(): string {
    $stats=get_platform_stats(); $t="📊 *آمار تفکیکی:*\n\n";
    foreach (['youtube','soundcloud','instagram','tiktok','facebook','telegram','archive','generic'] as $p) {
        $c=$stats[$p]['completed']??0; $f=$stats[$p]['failed']??0; $d=$stats[$p]['dispatched']??0; $pn=$stats[$p]['pending']??0;
        $total=$c+$f+$d+$pn; if($total==0)continue;
        $t.="*".($p==='archive'?'Internet Archive':ucfirst($p))."*\n✅ {$c} | ❌ {$f} | 🚀 {$d} | 📥 {$pn}\n📊 کل: {$total}\n━━━━━━━━━━━━━━━━━━\n";
    }
    return $t ?: "📊 *هنوز آماری ثبت نشده.*";
}
function msg_cache_stats(): string {
    $cs=get_cache_stats(); $t="💾 *وضعیت کش*\n\n📁 فایل‌های کش شده: {$cs['total_files']}\n👁 مجموع دفعات استفاده: {$cs['total_hits']}\n💿 حجم تقریبی: {$cs['total_size_mb']} MB\n\n*تفکیک پلتفرم‌ها:*\n";
    foreach($cs['platforms'] as $p) $t.="• {$p['platform']}: {$p['cnt']} فایل\n";
    return $t;
}
function msg_connection_status(): string {
    $logs=db()->query("SELECT * FROM connection_logs ORDER BY created_at DESC LIMIT 10")->fetchAll();
    $t="🌐 *وضعیت اتصال API*\n\n"; foreach($logs as $l){ $time=date('H:i:s',$l['created_at']); $icon=$l['status']==='success'?'✅':'❌'; $t.="{$icon} `{$time}` | {$l['response_time_ms']}ms\n"; }
    return $t ?: "هنوز گزارشی ثبت نشده.";
}
function msg_premium_activated(): string {
    return "🎉 *تبریک!*\n\n✅ اشتراک پریمیوم شما با موفقیت فعال شد!\n\n⚡ دانلود نامحدود\n📦 بدون محدودیت حجم\n🚀 اولویت در صف\n\n💰 *مبلغ:* " . number_format(PREMIUM_PRICE_RIAL) . " ریال\n📅 *اعتبار:* ۳۰ روز";
}

// ═══════════════════════════════════════════════════
// MAIN HANDLER
// ═══════════════════════════════════════════════════

function handle_update(array $update): void {
    // ── Pre-Checkout Query (Payment) ───────────────
    if (isset($update['pre_checkout_query'])) {
        $pcq = $update['pre_checkout_query'];
        bale_request('answerPreCheckoutQuery', [
            'pre_checkout_query_id' => $pcq['id'],
            'ok' => true,
        ]);
        return;
    }

    // ── Successful Payment ─────────────────────────
    if (isset($update['message']['successful_payment'])) {
        $sp = $update['message']['successful_payment'];
        $uid = (int)$update['message']['from']['id'];
        $payload = $sp['invoice_payload'] ?? '';

        if (str_starts_with($payload, 'premium_monthly_')) {
            $expires_at = time() + (30 * 24 * 3600);
            upsert_user($uid, ['is_premium' => 1, 'premium_expires_at' => $expires_at]);
            $user = get_user($uid);
            $is_premium = true;
            $is_admin = ($uid === ADMIN_USER_ID);
            send_message($uid, msg_premium_activated(), kb_main_menu($is_admin, $is_premium));
            add_admin_log('premium_activated', $uid, "Payment: {$sp['telegram_payment_charge_id']}");
        }
        return;
    }

    // ── Callback Query ──────────────────────────────
    if (isset($update['callback_query'])) {
        $cq = $update['callback_query'];
        if (!isset($cq['id'], $cq['from']['id'])) return;
        $cb_id = $cq['id']; $uid = (int)$cq['from']['id']; $data = $cq['data'] ?? '';
        $chat_id = isset($cq['message']['chat']['id']) ? (int)$cq['message']['chat']['id'] : 0;
        $msg_id = isset($cq['message']['message_id']) ? (int)$cq['message']['message_id'] : 0;
        if ($chat_id === 0 || $msg_id === 0) return;
        answer_callback_query($cb_id);

        // Get or create user
        $user = get_user($uid);
        if (!$user) {
            upsert_user($uid, ['first_name' => $cq['from']['first_name'] ?? '', 'username' => $cq['from']['username'] ?? '']);
            $user = get_user($uid);
        }

        $is_admin = ($uid === ADMIN_USER_ID);
        $is_premium = ($user['is_premium'] ?? 0) === 1;
        $has_archive = !empty($user['archive_channel_id']);
        $joined = ($user['joined_sponsor'] ?? 0) === 1;

        // ── Sponsor Check ──────────────────────────
        if ($data === 'check_sponsor') {
            $m = get_chat_member(SPONSOR_CHANNEL, $uid);
            if ($m && in_array($m['status'] ?? '', ['member','administrator','creator'])) {
                upsert_user($uid, ['joined_sponsor'=>1, 'state'=>'SETUP_ARCHIVE']);
                edit_message_text($chat_id, $msg_id, msg_sponsor_ok(), kb_archive_setup());
                add_admin_log('sponsor_verified', $uid);
            } else answer_callback_query($cb_id, '❌ هنوز عضو نشده‌اید!', true);
            return;
        }
        if (!$joined && $data !== 'check_sponsor') { answer_callback_query($cb_id, '⛔️ ابتدا عضو اسپانسر شوید.', true); return; }

        // ── Archive Setup ──────────────────────────
        if ($data === 'setup_archive') { upsert_user($uid, ['state'=>'SETUP_ARCHIVE']); edit_message_text($chat_id, $msg_id, "📂 *تنظیم کانال*\n\nForward کنید یا شناسه بدون @ بفرستید", kb_archive_setup()); return; }
        if ($data === 'guide_archive') { edit_message_text($chat_id, $msg_id, msg_archive_guide(), kb_back('setup_archive')); return; }
        if (!$has_archive && !in_array($data, ['setup_archive','guide_archive','back_to_main'])) { answer_callback_query($cb_id, '⛔️ ابتدا کانال آرشیو را تنظیم کنید.', true); return; }

        // ── Back to Main Menu ──────────────────────
        if ($data === 'back_to_main') { upsert_user($uid, ['state'=>'MAIN_MENU','current_platform'=>'','pending_url'=>'','pending_quality'=>'']); edit_message_text($chat_id, $msg_id, msg_main_menu(), kb_main_menu($is_admin, $is_premium)); return; }

        // ── Platform Selection ─────────────────────
        if (str_starts_with($data, 'platform_')) { $pl=str_replace('platform_','',$data); upsert_user($uid, ['state'=>'AWAITING_LINK','current_platform'=>$pl,'pending_url'=>'','pending_quality'=>'']); edit_message_text($chat_id, $msg_id, msg_await_link($pl), kb_back()); return; }

        // ── Quality/Format Selection ────────────────
        if (str_starts_with($data, 'quality_')) { $q=str_replace('quality_','',$data); $pu=$user['pending_url']??''; $pl=$user['current_platform']??'generic'; if(empty($pu)){answer_callback_query($cb_id,'⚠️ ابتدا لینک را ارسال کنید.',true);return;} upsert_user($uid,['pending_quality'=>$q,'state'=>'CONFIRMING']); edit_message_text($chat_id, $msg_id, msg_confirm($pl,$pu,$q), kb_confirm()); return; }
        if ($data === 'back_to_quality') { $pu=$user['pending_url']??''; $pl=$user['current_platform']??'generic'; edit_message_text($chat_id, $msg_id, msg_select_quality($pu,$pl), kb_quality($pl)); return; }

        // ── Buy Premium ────────────────────────────
        if ($data === 'buy_premium' && PREMIUM_ENABLED) {
            $prices = [['label' => 'اشتراک ماهانه پریمیوم', 'amount' => PREMIUM_PRICE_RIAL]];
            $result = bale_request('sendInvoice', [
                'chat_id' => $uid,
                'title' => 'اشتراک پریمیوم DownloadHub',
                'description' => 'دانلود نامحدود بدون محدودیت حجم | بدون split فایل‌ها | اولویت در صف',
                'payload' => 'premium_monthly_' . $uid . '_' . time(),
                'provider_token' => PREMIUM_PROVIDER_TOKEN,
                'prices' => json_encode($prices),
            ]);
            if (!$result) {
                answer_callback_query($cb_id, '❌ خطا در ایجاد درخواست پرداخت. لطفاً دوباره تلاش کنید.', true);
            }
            return;
        }

        // ── CONFIRM DOWNLOAD (Two-Layer Cache + Premium) ──
        if ($data === 'confirm_download') {
            $pl = $user['current_platform'] ?? '';
            $url = $user['pending_url'] ?? '';
            $quality = $user['pending_quality'] ?? 'best';
            if (($user['state']??'') === 'MAIN_MENU') { answer_callback_query($cb_id, '⏳ قبلاً ثبت شده.', true); return; }
            if (empty($pl) || empty($url)) { answer_callback_query($cb_id, '⚠️ لینک ثبت نشده.', true); return; }

            $archive_id = $user['archive_channel_id'] ?? '';
            $archive_un = $user['archive_channel_username'] ?? '';
            $cache_key = make_cache_key($url, $quality);

            // Clear state immediately to prevent double-click
            upsert_user($uid, ['state'=>'MAIN_MENU','current_platform'=>'','pending_url'=>'','pending_quality'=>'']);

            // ═══ LAYER 1: Check file_cache ═══
            $cached = get_cached_file($cache_key);
            if ($cached) {
                $is_multipart = ($cached['is_multipart'] ?? 0) === 1;
                $total_parts = (int)($cached['total_parts'] ?? 1);

                // Free users cannot use cache for multipart files (>45MB)
                if ($is_multipart && !$is_premium) {
                    goto dispatch_to_github;
                }

                // Copy main file
                $copy_result = copy_message((int)$archive_id, $cached['source_channel_id'], (int)$cached['message_id']);
                if ($copy_result) {
                    // For multipart files, copy all additional parts
                    if ($is_multipart && $total_parts > 1) {
                        $parts_copied = 1;
                        for ($i = 1; $i < $total_parts; $i++) {
                            $part_result = copy_message((int)$archive_id, $cached['source_channel_id'], (int)$cached['message_id'] + $i);
                            if ($part_result) $parts_copied++;
                            usleep(200000);
                        }
                        add_admin_log('cache_hit_multipart', $uid, "Platform: {$pl}, Parts: {$parts_copied}/{$total_parts}");
                    }

                    $job_id = add_to_queue($uid, $pl, $url, $quality, '', true);
                    add_admin_log('cache_hit', $uid, "Platform: {$pl}, Job: #{$job_id}, Source: {$cached['source_channel_username']}");
                    edit_message_text($chat_id, $msg_id, msg_download_started($pl, $job_id, $url, true), kb_back());
                    answer_callback_query($cb_id, '⚡ از حافظه کش ارسال شد!');
                    return;
                }

                // Copy failed — remove stale cache and fall through
                remove_cached_file($cached['id']);
                add_admin_log('cache_stale', $uid, "Removed #{$cached['id']}");
            }

            // ═══ LAYER 2: Dispatch to GitHub ═══
            dispatch_to_github:
            $inputs = [
                'chat_id' => (string)$chat_id,
                'channel_id' => $archive_id,
                'channel_username' => $archive_un,
                'quality' => $quality,
                'is_premium' => $is_premium ? 'true' : 'false',
            ];
            $ikm = ['youtube'=>'youtube_urls','soundcloud'=>'soundcloud_urls','instagram'=>'instagram_urls','tiktok'=>'tiktok_urls','facebook'=>'facebook_urls','telegram'=>'telegram_urls','archive'=>'archive_id','generic'=>'url'];
            $inputs[$ikm[$pl]??'url'] = $url;
            $pt = "⏳ *در حال ارسال به گیت‌هاب...*\n\n🎯 {$pl}\n🔗 `".(mb_strlen($url)>40?mb_substr($url,0,37).'...':$url)."`\n\nمنتظر بمانید...";
            edit_message_text($chat_id, $msg_id, $pt);

            $run_id = dispatch_workflow($pl, $inputs);
            if ($run_id) {
                $job_id = add_to_queue($uid, $pl, $url, $quality, $run_id);
                add_admin_log('download_start', $uid, "Platform: {$pl}, Job: #{$job_id}, Premium: " . ($is_premium ? 'yes' : 'no'));
                edit_message_text($chat_id, $msg_id, msg_download_started($pl, $job_id, $url), kb_back());
            } else {
                upsert_user($uid, ['state'=>'CONFIRMING','current_platform'=>$pl,'pending_url'=>$url,'pending_quality'=>$quality]);
                edit_message_text($chat_id, $msg_id, msg_confirm($pl, $url, $quality), kb_confirm());
                answer_callback_query($cb_id, '❌ خطا در ارتباط با گیت‌هاب!', true);
                add_admin_log('github_error', $uid, "Failed: {$pl}");
            }
            return;
        }

        // ── Queue, Cancel, Support ─────────────────
        if ($data === 'queue_status') { $t=msg_queue_status($uid); $jobs=get_user_queue($uid); $aj=array_filter($jobs,fn($j)=>in_array($j['status'],['pending','dispatched'])); $kb=['inline_keyboard'=>[]]; foreach($aj as $j) $kb['inline_keyboard'][]=[['text'=>"❌ لغو #{$j['id']} | {$j['platform']}", 'callback_data'=>"cancel_{$j['id']}"]]; $kb['inline_keyboard'][]=[['text'=>'🔄 بروزرسانی','callback_data'=>'queue_status']]; if(!empty($aj))$kb['inline_keyboard'][]=[['text'=>'❌ لغو همه','callback_data'=>'cancel_all']]; $kb['inline_keyboard'][]=[['text'=>'🔙 بازگشت','callback_data'=>'back_to_main']]; edit_message_text($chat_id,$msg_id,$t,$kb); return; }
        if (str_starts_with($data,'cancel_')) { $jid=(int)str_replace('cancel_','',$data); cancel_job($jid,$uid)?answer_callback_query($cb_id,'✅ لغو شد.'):answer_callback_query($cb_id,'❌ قابل لغو نیست.',true); $t=msg_queue_status($uid); $jobs=get_user_queue($uid); $aj=array_filter($jobs,fn($j)=>in_array($j['status'],['pending','dispatched'])); $kb=['inline_keyboard'=>[]]; foreach($aj as $j) $kb['inline_keyboard'][]=[['text'=>"❌ لغو #{$j['id']} | {$j['platform']}", 'callback_data'=>"cancel_{$j['id']}"]]; $kb['inline_keyboard'][]=[['text'=>'🔄 بروزرسانی','callback_data'=>'queue_status']]; if(!empty($aj))$kb['inline_keyboard'][]=[['text'=>'❌ لغو همه','callback_data'=>'cancel_all']]; $kb['inline_keyboard'][]=[['text'=>'🔙 بازگشت','callback_data'=>'back_to_main']]; edit_message_text($chat_id,$msg_id,$t,$kb); return; }
        if ($data === 'cancel_all') { $c=cancel_all_user_jobs($uid); add_admin_log('all_jobs_cancelled',$uid,"{$c} jobs"); edit_message_text($chat_id,$msg_id,msg_main_menu(),kb_main_menu($is_admin, $is_premium)); return; }
        if ($data === 'support') { upsert_user($uid,['state'=>'AWAITING_SUPPORT']); edit_message_text($chat_id,$msg_id,"📞 *پشتیبانی*\n\nپیام خود را بنویسید:",kb_back()); return; }

        // ── Admin Panel ────────────────────────────
        if (!$is_admin) return;
        if ($data === 'admin_panel') { edit_message_text($chat_id,$msg_id,msg_admin_panel(),kb_admin()); return; }
        if ($data === 'toggle_pause') { $p=is_paused(); set_paused(!$p); add_admin_log(!$p?'paused':'resumed',$uid); edit_message_text($chat_id,$msg_id,msg_admin_panel(),kb_admin()); return; }
        if ($data === 'admin_stats') { edit_message_text($chat_id,$msg_id,msg_platform_stats(),kb_back('admin_panel')); return; }
        if ($data === 'admin_cache') { edit_message_text($chat_id,$msg_id,msg_cache_stats(),kb_back('admin_panel')); return; }
        if ($data === 'admin_connection') { $conn=check_bale_connection(); $t=msg_connection_status(); $t.="\n\n*بررسی جدید:*\n⏱️ {$conn['response_time']}ms | ".($conn['status']==='success'?'✅':'❌'); edit_message_text($chat_id,$msg_id,$t,kb_back('admin_panel')); return; }
        if ($data === 'admin_broadcast') { upsert_user($uid,['state'=>'AWAITING_BROADCAST']); edit_message_text($chat_id,$msg_id,"📢 *پیام همگانی*\n\nمتن را بنویسید:",kb_back('admin_panel')); return; }
        if ($data === 'admin_clear_queue') { db()->exec("UPDATE queue SET status='cancelled', updated_at=strftime('%s','now') WHERE status IN ('pending','dispatched')"); add_admin_log('clear_queue',$uid); edit_message_text($chat_id,$msg_id,msg_admin_panel(),kb_admin()); return; }
        if ($data === 'admin_users') { $users=get_all_users(); $total=count($users); $t="👥 *کاربران ({$total}):*\n\n"; foreach(array_slice($users,0,50) as $u){ $a=$u['archive_channel_username']?'✅':'❌'; $s=$u['joined_sponsor']?'✅':'❌'; $pr=$u['is_premium']?'⭐':'⚪'; $t.="`{$u['user_id']}` | {$u['first_name']} | س:{$s} آ:{$a} {$pr}\n"; } if($total>50)$t.="\n... و ".($total-50)." کاربر دیگر"; edit_message_text($chat_id,$msg_id,$t,kb_back('admin_panel')); return; }
        if ($data === 'admin_logs') { $logs=db()->query("SELECT * FROM admin_logs ORDER BY created_at DESC LIMIT 30")->fetchAll(); $t="📋 *لاگ‌ها:*\n\n"; if(empty($logs))$t.="خالی"; else foreach($logs as $l){ $time=date('m/d H:i',$l['created_at']); $t.="`{$time}` *{$l['event_type']}*"; if($l['message'])$t.=" - {$l['message']}"; $t.="\n"; } edit_message_text($chat_id,$msg_id,$t,kb_back('admin_panel')); return; }
        return;
    }

    // ── Message Handler ─────────────────────────────
    if (isset($update['message'])) {
        $msg = $update['message'];
        if (!isset($msg['from']['id'], $msg['chat']['id'])) return;
        $uid = (int)$msg['from']['id'];
        $chat_id = (int)$msg['chat']['id'];
        $text = trim($msg['text'] ?? $msg['caption'] ?? '');
        $msg_id = (int)$msg['message_id'];
        $is_admin = ($uid === ADMIN_USER_ID);

        // Ignore channel/bot messages
        if (isset($msg['sender_chat']) && ($msg['sender_chat']['type']??'') === 'channel') return;
        if (($msg['from']['is_bot'] ?? false) === true) return;

        $fwd_chat = $msg['forward_from_chat'] ?? null;
        $is_fwd_channel = ($fwd_chat && ($fwd_chat['type']??'') === 'channel');

        // ═══ /cache command (from Actions workflow) ═══
        if (str_starts_with($text, '/cache ') && $is_admin) {
            $parts = explode(' ', $text);
            if (count($parts) >= 8) {
                $pl=$parts[1]; $url=$parts[2]; $q=$parts[3]; $fid=$parts[4]; $sch=$parts[5]; $sm=(int)$parts[6]; $fs=(int)$parts[7]; $fn=$parts[8]??'';
                $mp = (int)($parts[9] ?? 0);
                $tp = (int)($parts[10] ?? 1);
                $ck = make_cache_key($url, $q);
                cache_file($ck, $url, $pl, $q, $fid, $sm, $sch, '', $fs, $fn, $mp === 1, $tp);
                add_admin_log('cache_registered', $uid, "Key: {$ck}, Platform: {$pl}, Multipart: {$mp}, Parts: {$tp}");
                send_message($chat_id, "✅ کش ثبت شد: `{$ck}`");
            }
            return;
        }

        // ── /start ──────────────────────────────────
        if ($text === '/start') {
            upsert_user($uid, ['state'=>'CHECK_SPONSOR','first_name'=>$msg['from']['first_name']??'','username'=>$msg['from']['username']??'','joined_sponsor'=>0,'archive_channel_id'=>'','archive_channel_username'=>'','current_platform'=>'','pending_url'=>'','pending_quality'=>'']);
            send_message($chat_id, msg_welcome(), kb_sponsor());
            add_admin_log('new_start', $uid);
            return;
        }

        // Get user
        $user = get_user($uid);
        if (!$user) { send_message($chat_id, "⚠️ لطفاً /start را بزنید."); return; }

        $state = $user['state'] ?? 'CHECK_SPONSOR';
        $joined = ($user['joined_sponsor']??0)===1;
        $is_premium = ($user['is_premium'] ?? 0) === 1;
        $has_archive = !empty($user['archive_channel_id']);
        if ($msg_id) upsert_user($uid, ['last_message_id'=>$msg_id, 'last_chat_id'=>$chat_id]);

        // ── CHECK_SPONSOR ───────────────────────────
        if ($state === 'CHECK_SPONSOR') { send_message($chat_id, "⚠️ ابتدا عضو کانال اسپانسر شوید.", kb_sponsor()); return; }

        // ── SETUP_ARCHIVE ───────────────────────────
        if ($state === 'SETUP_ARCHIVE') {
            if ($is_fwd_channel) {
                $ch_id=(string)$fwd_chat['id']; $ch_un=$fwd_chat['username']??'';
                $me=get_me(); if(!$me||!isset($me['id'])){send_message($chat_id,"❌ خطای سیستمی!",kb_archive_setup());return;}
                $ci=$ch_un?"@{$ch_un}":$ch_id; $member=get_chat_member($ci,(int)$me['id']);
                if($member&&in_array($member['status']??'',['administrator','creator'])){
                    upsert_user($uid,['state'=>'MAIN_MENU','archive_channel_id'=>$ch_id,'archive_channel_username'=>$ch_un]);
                    $dn=$ch_un?"@{$ch_un}":$ch_id; send_message($chat_id,msg_archive_success($dn),kb_main_menu($is_admin, $is_premium));
                    add_admin_log('archive_setup',$uid,"Channel: {$dn}"); return;
                }
                send_message($chat_id,msg_archive_fail_not_admin(),kb_archive_setup()); return;
            }
            if($msg['forward_from']??null){send_message($chat_id,msg_archive_fail_not_channel(),kb_archive_setup());return;}
            if(!empty($text)){
                $un=trim(str_replace(['@','https://ble.ir/','https://t.me/'],'',$text)); $un=explode('/',$un)[0];
                if(preg_match('/^[a-zA-Z][a-zA-Z0-9_]{4,31}$/',$un)){
                    $ci=get_chat("@{$un}"); if(!$ci||($ci['type']??'')!=='channel'){send_message($chat_id,"❌ کانال یافت نشد!",kb_archive_setup());return;}
                    $me=get_me(); if(!$me||!isset($me['id'])){send_message($chat_id,"❌ خطای سیستمی!",kb_archive_setup());return;}
                    $m=get_chat_member("@{$un}",(int)$me['id']);
                    if($m&&in_array($m['status']??'',['administrator','creator'])){
                        upsert_user($uid,['state'=>'MAIN_MENU','archive_channel_id'=>(string)$ci['id'],'archive_channel_username'=>$un]);
                        send_message($chat_id,msg_archive_success("@{$un}"),kb_main_menu($is_admin, $is_premium));
                        add_admin_log('archive_setup',$uid,"Channel: @{$un}"); return;
                    }
                    send_message($chat_id,msg_archive_fail_not_admin(),kb_archive_setup()); return;
                }
            }
            send_message($chat_id,"📂 *تنظیم کانال*\n\nForward کنید یا شناسه بدون @ بفرستید",kb_archive_setup()); return;
        }

        // ── Guard: Both checks passed? ──────────────
        if(!$joined||!$has_archive){ if(!$joined)send_message($chat_id,"⛔️ ابتدا عضو اسپانسر شوید."); else send_message($chat_id,"⛔️ ابتدا کانال آرشیو را تنظیم کنید.",kb_archive_setup()); return; }

        // ── AWAITING_LINK ───────────────────────────
        if($state==='AWAITING_LINK'){
            $pl=$user['current_platform']??'generic'; if(empty($text)){send_message($chat_id,"⚠️ لینک را ارسال کنید.",kb_back());return;}
            if($pl==='generic'){$pl=detect_platform($text); upsert_user($uid,['current_platform'=>$pl]);}
            if(!validate_url($text,$pl)){send_message($chat_id,msg_invalid_link($pl),kb_back());return;}
            upsert_user($uid,['pending_url'=>$text,'state'=>'AWAITING_QUALITY']); send_message($chat_id,msg_select_quality($text,$pl),kb_quality($pl)); return;
        }

        // ── AWAITING_SUPPORT ────────────────────────
        if($state==='AWAITING_SUPPORT'){
            if(empty($text)){send_message($chat_id,"⚠️ پیام را بنویسید.",kb_back());return;}
            $sid=add_support_message($uid,$text); send_message(ADMIN_USER_ID,"📞 *پشتیبانی #{$sid}*\n👤 `{$uid}` | {$user['first_name']}\n\n{$text}");
            send_message($chat_id,"✅ پیام ارسال شد. 🆔 #{$sid}",kb_back()); upsert_user($uid,['state'=>'MAIN_MENU']); add_admin_log('support',$uid,"Ticket #{$sid}"); return;
        }

        // ── AWAITING_BROADCAST (Admin) ─────────────
        if($state==='AWAITING_BROADCAST'&&$is_admin){
            if(empty($text)){send_message($chat_id,"⚠️ متن را بنویسید.",kb_back('admin_panel'));return;}
            $all=get_all_users(); $sent=0; foreach($all as $u){if(send_message((int)$u['user_id'],"📢 *پیام مدیریت:*\n\n{$text}"))$sent++; usleep(50000);}
            send_message($chat_id,"📢 ارسال به {$sent}/".count($all)." کاربر",kb_admin()); upsert_user($uid,['state'=>'MAIN_MENU']); add_admin_log('broadcast',$uid,"Sent: {$sent}"); return;
        }

        // ── Admin reply to support ──────────────────
        if($is_admin&&preg_match('/^\/reply_(\d+)\s+(.+)$/s',$text,$m)){
            $sid=(int)$m[1]; $reply=trim($m[2]); $sm=get_support_message($sid);
            if(!$sm){send_message($chat_id,"❌ پیام #{$sid} یافت نشد.");return;}
            if($sm['is_replied']){send_message($chat_id,"⚠️ قبلاً پاسخ داده شده.");return;}
            send_message((int)$sm['user_id'],"📬 *پاسخ پشتیبانی:*\n\n{$reply}"); mark_support_replied($sid,$reply);
            send_message($chat_id,"✅ پاسخ ارسال شد."); add_admin_log('support_reply',$uid,"Ticket #{$sid}"); return;
        }
    }
}

// ═══════════════════════════════════════════════════
// ENTRY POINT
// ═══════════════════════════════════════════════════

header('Content-Type: application/json; charset=utf-8');
$input = json_decode(file_get_contents('php://input'), true);
if ($input) {
    try { handle_update($input); }
    catch (Exception $e) { error_log('Bot Error: ' . $e->getMessage()); }
}
http_response_code(200);
echo json_encode(['ok' => true]);
