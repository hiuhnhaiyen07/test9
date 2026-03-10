<?php

declare(strict_types=1);

require __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'message' => 'Method không hợp lệ.'], 405);
}

requireAdminAuth(true);
$message = cleanText((string) ((readJsonBody()['message'] ?? '') ?: ''), 800);
if ($message === '') {
    $message = '✅ Test kết nối Telegram từ admin dashboard thành công.';
}

$result = sendTelegramMessage($message);
logEvent('admin', ['action' => 'send_test_telegram', 'ok' => $result['ok'] ?? false]);
jsonResponse([
    'ok' => true,
    'telegram' => $result,
]);
