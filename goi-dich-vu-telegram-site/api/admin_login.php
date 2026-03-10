<?php

declare(strict_types=1);

require __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'message' => 'Method không hợp lệ.'], 405);
}

$cfg = config();
$clientIp = getClientIp();
$limit = consumeRateLimit('admin_login', $clientIp, (int) ($cfg['limits']['admin_login_per_10_minutes'] ?? 8), 600);
if (!$limit['allowed']) {
    jsonResponse([
        'ok' => false,
        'message' => 'Bạn nhập sai quá nhiều lần. Vui lòng thử lại sau.',
        'retry_after' => $limit['retry_after'],
    ], 429);
}

$payload = readJsonBody();
$password = (string) ($payload['password'] ?? '');
if ($password === '') {
    jsonResponse(['ok' => false, 'message' => 'Vui lòng nhập mật khẩu admin.'], 422);
}

if (!adminLogin($password)) {
    logEvent('admin', ['action' => 'login_failed', 'ip' => $clientIp]);
    jsonResponse(['ok' => false, 'message' => 'Mật khẩu không đúng.'], 401);
}

logEvent('admin', ['action' => 'login_success', 'ip' => $clientIp]);
jsonResponse([
    'ok' => true,
    'message' => 'Đăng nhập thành công.',
    'csrf_token' => adminCsrfToken(),
]);
