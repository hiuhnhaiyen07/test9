<?php

declare(strict_types=1);

require __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'message' => 'Method không hợp lệ.'], 405);
}

$cfg = config();
$payload = readJsonBody();
$clientIp = getClientIp();

$minuteLimit = consumeRateLimit('create_order_minute', $clientIp, (int) ($cfg['limits']['create_order_per_minute'] ?? 3), 60);
if (!$minuteLimit['allowed']) {
    jsonResponse([
        'ok' => false,
        'message' => 'Bạn thao tác quá nhanh. Vui lòng thử lại sau ít phút.',
        'retry_after' => $minuteLimit['retry_after'],
    ], 429);
}

$hourLimit = consumeRateLimit('create_order_hour', $clientIp, (int) ($cfg['limits']['create_order_per_hour'] ?? 10), 3600);
if (!$hourLimit['allowed']) {
    jsonResponse([
        'ok' => false,
        'message' => 'Thiết bị này đã tạo quá nhiều đơn trong thời gian ngắn.',
        'retry_after' => $hourLimit['retry_after'],
    ], 429);
}

$email = cleanEmail((string) ($payload['email'] ?? ''));
$note = cleanText((string) ($payload['note'] ?? ''), 300);
$website = trim((string) ($payload['website'] ?? ''));
$startedAt = (int) ($payload['started_at'] ?? 0);
$minimumSeconds = (int) ($cfg['limits']['minimum_form_fill_seconds'] ?? 2);

if ($website !== '') {
    jsonResponse(['ok' => false, 'message' => 'Yêu cầu không hợp lệ.'], 422);
}

if ($startedAt > 0) {
    $elapsed = time() - $startedAt;
    if ($elapsed < $minimumSeconds) {
        jsonResponse(['ok' => false, 'message' => 'Vui lòng thao tác chậm lại một chút rồi thử lại.'], 422);
    }
}

if (!validateGmail($email)) {
    jsonResponse(['ok' => false, 'message' => 'Vui lòng nhập đúng Gmail.'], 422);
}

$duplicate = findRecentDuplicateOrder($email, $clientIp, (int) ($cfg['limits']['duplicate_order_cooldown_seconds'] ?? 120));
if ($duplicate !== null) {
    jsonResponse([
        'ok' => true,
        'message' => 'Đã tìm thấy đơn gần nhất, chuyển bạn về đơn đang xử lý.',
        'order' => $duplicate,
        'duplicate' => true,
    ]);
}

$public = publicConfig();
$orderId = generateOrderId();
$order = [
    'order_id' => $orderId,
    'email' => $email,
    'note' => $note,
    'amount' => (int) $public['price'],
    'plan_name' => (string) $public['plan_name'],
    'status' => 'pending',
    'transfer_content' => transferContentForOrder($orderId),
    'created_at' => date('c'),
    'updated_at' => date('c'),
    'paid_at' => null,
    'transaction_id' => null,
    'client_ip' => $clientIp,
    'admin_note' => '',
    'source' => 'website',
    'payment_payload' => null,
];

saveOrder($order);
logEvent('orders', ['action' => 'create_order', 'order_id' => $orderId, 'email' => $email, 'ip' => $clientIp]);

jsonResponse([
    'ok' => true,
    'order' => $order,
]);
