<?php

declare(strict_types=1);

function rootPath(string $suffix = ''): string
{
    return dirname(__DIR__) . ($suffix ? '/' . ltrim($suffix, '/') : '');
}

function dataPath(string $suffix = ''): string
{
    return rootPath('data' . ($suffix ? '/' . ltrim($suffix, '/') : ''));
}

function settingsFile(): string
{
    return dataPath('settings.json');
}

function config(bool $forceReload = false): array
{
    static $config = null;

    if ($forceReload) {
        $config = null;
    }

    if ($config === null) {
        $base = require __DIR__ . '/config.php';
        $overrides = loadSettingsOverrides();
        $config = array_replace_recursive($base, $overrides);
        date_default_timezone_set($config['timezone'] ?? 'Asia/Ho_Chi_Minh');
    }

    return $config;
}

function publicConfig(): array
{
    $cfg = config();
    $public = $cfg['public'] ?? [];
    $qr = resolveQrImagePath((string) ($public['qr_image_path'] ?? 'img/mbbank-qr.png'));

    return [
        'site_name' => (string) ($public['site_name'] ?? 'Premium Service'),
        'site_tagline' => (string) ($public['site_tagline'] ?? ''),
        'price' => (int) ($public['price'] ?? 29000),
        'plan_name' => (string) ($public['plan_name'] ?? '29.000đ / 1 tháng'),
        'bank_name' => (string) ($public['bank_name'] ?? 'MB Bank'),
        'bank_account_name' => (string) ($public['bank_account_name'] ?? ''),
        'bank_account_number' => (string) ($public['bank_account_number'] ?? ''),
        'support_link' => (string) ($public['support_link'] ?? '#'),
        'support_label' => (string) ($public['support_label'] ?? 'Hỗ trợ'),
        'qr_image_path' => $qr,
        'hero_badge' => (string) ($public['hero_badge'] ?? 'Webhook xác nhận tự động'),
        'cta_subtitle' => (string) ($public['cta_subtitle'] ?? ''),
        'status_poll_interval_ms' => (int) (($cfg['limits']['status_poll_interval_ms'] ?? 5000)),
    ];
}

function resolveQrImagePath(string $path): string
{
    $path = ltrim(trim($path), '/');
    if ($path === '') {
        $path = 'img/mbbank-qr.png';
    }

    $absolute = rootPath($path);
    if (!is_file($absolute)) {
        $path = 'img/mbbank-qr.png';
        $absolute = rootPath($path);
    }

    $version = is_file($absolute) ? (string) filemtime($absolute) : (string) time();
    return $path . '?v=' . rawurlencode($version);
}

function loadSettingsOverrides(): array
{
    $file = settingsFile();
    if (!is_file($file)) {
        return [];
    }

    $content = file_get_contents($file);
    if ($content === false || trim($content) === '') {
        return [];
    }

    $decoded = json_decode($content, true);
    return is_array($decoded) ? $decoded : [];
}

function saveSettingsOverrides(array $overrides): void
{
    ensureDataDirs();
    file_put_contents(
        settingsFile(),
        json_encode($overrides, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}

function ensureDataDirs(): void
{
    foreach ([
        dataPath('orders'),
        dataPath('logs'),
        dataPath('rate_limits'),
        rootPath('uploads'),
    ] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }
}

function jsonResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function readJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function cleanEmail(string $email): string
{
    return trim(strtolower($email));
}

function textSlice(string $value, int $start, ?int $length = null): string
{
    if (function_exists('mb_substr')) {
        return $length === null ? mb_substr($value, $start) : mb_substr($value, $start, $length);
    }
    return $length === null ? substr($value, $start) : substr($value, $start, $length);
}

function textLower(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
}

function textLength(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function validateGmail(string $email): bool
{
    return (bool) preg_match('/^[^\s@]+@gmail\.com$/i', cleanEmail($email));
}

function cleanText(string $value, int $maxLength = 255): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    return textSlice($value, 0, $maxLength);
}

function getClientIp(): string
{
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        if (!is_string($candidate) || trim($candidate) === '') {
            continue;
        }

        $first = trim(explode(',', $candidate)[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) {
            return $first;
        }
    }

    return '0.0.0.0';
}

function generateOrderId(): string
{
    return 'OD' . date('YmdHis') . strtoupper(bin2hex(random_bytes(2)));
}

function transferContentForOrder(string $orderId): string
{
    return 'NAP ' . $orderId;
}

function orderFile(string $orderId): string
{
    return dataPath('orders/' . $orderId . '.json');
}

function saveOrder(array $order): void
{
    ensureDataDirs();
    file_put_contents(
        orderFile((string) $order['order_id']),
        json_encode($order, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}

function loadOrder(string $orderId): ?array
{
    $file = orderFile($orderId);
    if (!is_file($file)) {
        return null;
    }

    $content = file_get_contents($file);
    $decoded = json_decode((string) $content, true);
    return is_array($decoded) ? $decoded : null;
}

function allOrders(): array
{
    ensureDataDirs();
    $files = glob(dataPath('orders/*.json')) ?: [];
    $orders = [];

    foreach ($files as $file) {
        $content = file_get_contents($file);
        $decoded = json_decode((string) $content, true);
        if (is_array($decoded)) {
            $orders[] = $decoded;
        }
    }

    usort($orders, static function (array $a, array $b): int {
        return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
    });

    return $orders;
}

function summarizeOrders(array $orders): array
{
    $stats = [
        'total' => 0,
        'pending' => 0,
        'paid' => 0,
        'processing' => 0,
        'completed' => 0,
        'cancelled' => 0,
        'revenue_paid' => 0,
        'revenue_completed' => 0,
        'today_orders' => 0,
    ];

    $today = date('Y-m-d');

    foreach ($orders as $order) {
        $stats['total']++;
        $status = (string) ($order['status'] ?? 'pending');
        if (isset($stats[$status])) {
            $stats[$status]++;
        }

        $amount = (int) ($order['amount'] ?? 0);
        if (in_array($status, ['paid', 'processing', 'completed'], true)) {
            $stats['revenue_paid'] += $amount;
        }
        if ($status === 'completed') {
            $stats['revenue_completed'] += $amount;
        }
        if (str_starts_with((string) ($order['created_at'] ?? ''), $today)) {
            $stats['today_orders']++;
        }
    }

    return $stats;
}

function findRecentDuplicateOrder(string $email, string $ip, int $withinSeconds): ?array
{
    $threshold = time() - $withinSeconds;
    foreach (allOrders() as $order) {
        $createdAt = strtotime((string) ($order['created_at'] ?? '')) ?: 0;
        if ($createdAt < $threshold) {
            break;
        }
        if (cleanEmail((string) ($order['email'] ?? '')) === cleanEmail($email)
            && (string) ($order['client_ip'] ?? '') === $ip
            && in_array((string) ($order['status'] ?? ''), ['pending', 'paid', 'processing'], true)) {
            return $order;
        }
    }
    return null;
}

function logEvent(string $type, array $payload): void
{
    ensureDataDirs();
    $file = dataPath('logs/' . $type . '-' . date('Y-m-d') . '.log');
    $line = '[' . date('Y-m-d H:i:s') . '] ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

function getHeaderValue(string $headerName): ?string
{
    $normalized = 'HTTP_' . strtoupper(str_replace('-', '_', $headerName));
    if (isset($_SERVER[$normalized])) {
        return trim((string) $_SERVER[$normalized]);
    }
    return null;
}

function extractOrderId(array $payload): ?string
{
    $candidates = [
        $payload['order_id'] ?? null,
        $payload['orderId'] ?? null,
        $payload['reference'] ?? null,
        $payload['meta']['order_id'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        if (is_string($candidate) && preg_match('/^OD\d{14}[A-Z0-9]{4}$/', $candidate)) {
            return $candidate;
        }
    }

    $content = '';
    foreach (['content', 'description', 'transfer_content', 'message'] as $key) {
        if (!empty($payload[$key]) && is_string($payload[$key])) {
            $content = $payload[$key];
            break;
        }
    }

    if ($content && preg_match('/(OD\d{14}[A-Z0-9]{4})/', $content, $matches)) {
        return $matches[1];
    }

    return null;
}

function normalizeAmount(array $payload): int
{
    $amount = $payload['amount'] ?? $payload['value'] ?? $payload['total_amount'] ?? 0;
    if (is_string($amount)) {
        $amount = preg_replace('/\D+/', '', $amount);
    }
    return (int) $amount;
}

function rateLimitFile(string $namespace, string $key): string
{
    $safeNamespace = preg_replace('/[^A-Za-z0-9_-]/', '_', $namespace) ?? 'limit';
    return dataPath('rate_limits/' . $safeNamespace . '-' . md5($key) . '.json');
}

function consumeRateLimit(string $namespace, string $key, int $limit, int $windowSeconds): array
{
    ensureDataDirs();
    $file = rateLimitFile($namespace, $key);
    $now = time();
    $data = ['hits' => []];

    if (is_file($file)) {
        $decoded = json_decode((string) file_get_contents($file), true);
        if (is_array($decoded) && isset($decoded['hits']) && is_array($decoded['hits'])) {
            $data = $decoded;
        }
    }

    $hits = array_values(array_filter($data['hits'], static function ($ts) use ($now, $windowSeconds) {
        return is_int($ts) && $ts > ($now - $windowSeconds);
    }));

    $allowed = count($hits) < $limit;
    if ($allowed) {
        $hits[] = $now;
    }

    file_put_contents($file, json_encode(['hits' => $hits]), LOCK_EX);

    $retryAfter = 0;
    if (!$allowed && $hits !== []) {
        sort($hits);
        $retryAfter = max(1, $windowSeconds - ($now - (int) $hits[0]));
    }

    return [
        'allowed' => $allowed,
        'remaining' => max(0, $limit - count($hits)),
        'retry_after' => $retryAfter,
    ];
}

function sendTelegramMessage(string $message): array
{
    $cfg = config();
    $token = trim((string) ($cfg['telegram_bot_token'] ?? ''));
    $chatId = trim((string) ($cfg['telegram_chat_id'] ?? ''));

    if ($token === '' || $chatId === '' || $token === 'BOT_TOKEN_HERE' || $chatId === 'CHAT_ID_HERE') {
        return ['ok' => false, 'message' => 'Thiếu Telegram bot token hoặc chat id trong cấu hình.'];
    }

    $payload = http_build_query([
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => 'true',
    ]);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n" .
                        'Content-Length: ' . strlen($payload) . "\r\n",
            'content' => $payload,
            'timeout' => 15,
        ],
    ]);

    $url = 'https://api.telegram.org/bot' . rawurlencode($token) . '/sendMessage';
    $result = @file_get_contents($url, false, $context);

    if ($result === false) {
        return ['ok' => false, 'message' => 'Không kết nối được Telegram API.'];
    }

    $decoded = json_decode($result, true);
    return is_array($decoded) ? $decoded : ['ok' => false, 'message' => 'Phản hồi Telegram không hợp lệ.'];
}

function buildTelegramText(array $order): string
{
    $amount = number_format((int) ($order['amount'] ?? 0), 0, ',', '.');
    $lines = [
        '<b>🔔 Đơn hàng đã thanh toán</b>',
        'Mã đơn: <code>' . htmlspecialchars((string) ($order['order_id'] ?? ''), ENT_QUOTES, 'UTF-8') . '</code>',
        'Gmail: <code>' . htmlspecialchars((string) ($order['email'] ?? ''), ENT_QUOTES, 'UTF-8') . '</code>',
        'Gói: ' . htmlspecialchars((string) ($order['plan_name'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'Số tiền: <b>' . $amount . 'đ</b>',
        'Nội dung CK: <code>' . htmlspecialchars((string) ($order['transfer_content'] ?? ''), ENT_QUOTES, 'UTF-8') . '</code>',
    ];

    if (!empty($order['transaction_id'])) {
        $lines[] = 'Mã GD: <code>' . htmlspecialchars((string) $order['transaction_id'], ENT_QUOTES, 'UTF-8') . '</code>';
    }
    if (!empty($order['admin_note'])) {
        $lines[] = 'Ghi chú admin: ' . htmlspecialchars((string) $order['admin_note'], ENT_QUOTES, 'UTF-8');
    }
    if (!empty($order['note'])) {
        $lines[] = 'Ghi chú khách: ' . htmlspecialchars((string) $order['note'], ENT_QUOTES, 'UTF-8');
    }
    if (!empty($order['paid_at'])) {
        $lines[] = 'Thanh toán lúc: ' . htmlspecialchars((string) $order['paid_at'], ENT_QUOTES, 'UTF-8');
    }

    return implode("\n", $lines);
}

function startAdminSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $cfg = config();
    session_name((string) ($cfg['session_name'] ?? 'premium_admin_session'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function adminIsAuthenticated(): bool
{
    startAdminSession();
    return !empty($_SESSION['admin_authenticated']) && !empty($_SESSION['admin_logged_at']);
}

function adminLogin(string $password): bool
{
    $cfg = config();
    $hash = (string) ($cfg['admin_password_hash'] ?? '');
    if ($hash === '') {
        return false;
    }

    if (!password_verify($password, $hash)) {
        return false;
    }

    startAdminSession();
    session_regenerate_id(true);
    $_SESSION['admin_authenticated'] = true;
    $_SESSION['admin_logged_at'] = time();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
    }

    return true;
}

function adminLogout(): void
{
    startAdminSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
}

function adminCsrfToken(): string
{
    startAdminSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
    }
    return (string) $_SESSION['csrf_token'];
}

function verifyAdminCsrf(?string $token): bool
{
    startAdminSession();
    return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals((string) $_SESSION['csrf_token'], $token);
}

function requireAdminAuth(bool $requireCsrf = false): void
{
    if (!adminIsAuthenticated()) {
        jsonResponse(['ok' => false, 'message' => 'Bạn chưa đăng nhập admin.'], 401);
    }

    if ($requireCsrf) {
        $token = getHeaderValue('X-CSRF-Token') ?? ($_POST['csrf_token'] ?? null);
        if (!verifyAdminCsrf(is_string($token) ? $token : null)) {
            jsonResponse(['ok' => false, 'message' => 'CSRF token không hợp lệ.'], 419);
        }
    }
}

function updateOrderFields(array $order, array $changes): array
{
    foreach ($changes as $key => $value) {
        $order[$key] = $value;
    }
    saveOrder($order);
    return $order;
}

function allowedStatuses(): array
{
    return ['pending', 'paid', 'processing', 'completed', 'cancelled'];
}

function filterOrders(array $orders, string $query = '', string $status = ''): array
{
    $query = trim(textLower($query));
    $status = trim(textLower($status));

    return array_values(array_filter($orders, static function (array $order) use ($query, $status): bool {
        if ($status !== '' && $status !== 'all' && textLower((string) ($order['status'] ?? '')) !== $status) {
            return false;
        }

        if ($query === '') {
            return true;
        }

        $haystack = textLower(implode(' ', [
            (string) ($order['order_id'] ?? ''),
            (string) ($order['email'] ?? ''),
            (string) ($order['note'] ?? ''),
            (string) ($order['admin_note'] ?? ''),
            (string) ($order['transaction_id'] ?? ''),
        ]));

        return str_contains($haystack, $query);
    }));
}

function paginate(array $items, int $page, int $pageSize): array
{
    $page = max(1, $page);
    $pageSize = max(1, min(100, $pageSize));
    $total = count($items);
    $pages = max(1, (int) ceil($total / $pageSize));
    $page = min($page, $pages);
    $offset = ($page - 1) * $pageSize;

    return [
        'items' => array_slice($items, $offset, $pageSize),
        'pagination' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total_items' => $total,
            'total_pages' => $pages,
        ],
    ];
}

function createPasswordHash(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}
