<?php

declare(strict_types=1);

require __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'message' => 'Method không hợp lệ.'], 405);
}

requireAdminAuth(true);
$payload = readJsonBody();
$orderId = trim((string) ($payload['order_id'] ?? ''));
$action = trim((string) ($payload['action'] ?? 'update'));
$adminNote = cleanText((string) ($payload['admin_note'] ?? ''), 400);

$order = loadOrder($orderId);
if ($order === null) {
    jsonResponse(['ok' => false, 'message' => 'Không tìm thấy đơn hàng.'], 404);
}

if ($action === 'resend_telegram') {
    $result = sendTelegramMessage(buildTelegramText($order));
    logEvent('admin', ['action' => 'resend_telegram', 'order_id' => $orderId, 'ok' => $result['ok'] ?? false]);
    jsonResponse([
        'ok' => true,
        'message' => 'Đã gửi lại thông báo Telegram.',
        'telegram' => $result,
        'order' => $order,
    ]);
}

$status = trim((string) ($payload['status'] ?? ($order['status'] ?? 'pending')));
if (!in_array($status, allowedStatuses(), true)) {
    jsonResponse(['ok' => false, 'message' => 'Trạng thái không hợp lệ.'], 422);
}

$order['status'] = $status;
$order['admin_note'] = $adminNote;
$order['updated_at'] = date('c');
if ($status === 'completed' && empty($order['completed_at'])) {
    $order['completed_at'] = date('c');
}
if ($status === 'processing' && empty($order['processing_at'])) {
    $order['processing_at'] = date('c');
}
saveOrder($order);

logEvent('admin', ['action' => 'update_order', 'order_id' => $orderId, 'status' => $status]);
jsonResponse([
    'ok' => true,
    'message' => 'Đã cập nhật đơn hàng.',
    'order' => $order,
]);
