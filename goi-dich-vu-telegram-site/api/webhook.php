<?php

declare(strict_types=1);

require __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'message' => 'Method không hợp lệ.'], 405);
}

$cfg = config();
$payload = readJsonBody();
logEvent('webhook', $payload);

$secret = getHeaderValue('X-Webhook-Secret') ?? (string) ($payload['secret'] ?? '');
if ($secret !== (string) ($cfg['webhook_secret'] ?? '')) {
    jsonResponse(['ok' => false, 'message' => 'Secret webhook không hợp lệ.'], 401);
}

$orderId = extractOrderId($payload);
if (!$orderId) {
    jsonResponse(['ok' => false, 'message' => 'Không tìm thấy order_id trong payload.'], 422);
}

$order = loadOrder($orderId);
if (!$order) {
    jsonResponse(['ok' => false, 'message' => 'Đơn hàng không tồn tại.'], 404);
}

$amount = normalizeAmount($payload);
if ($amount < (int) ($order['amount'] ?? 0)) {
    jsonResponse(['ok' => false, 'message' => 'Số tiền thanh toán không khớp.'], 422);
}

if (in_array((string) ($order['status'] ?? ''), ['paid', 'processing', 'completed'], true)) {
    jsonResponse(['ok' => true, 'message' => 'Đơn hàng đã được xác nhận trước đó.', 'order' => $order]);
}

$order['status'] = 'paid';
$order['paid_at'] = date('c');
$order['updated_at'] = date('c');
$order['transaction_id'] = cleanText((string) ($payload['transaction_id'] ?? $payload['reference'] ?? ('TX' . date('YmdHis'))), 80);
$order['payment_payload'] = $payload;
saveOrder($order);

$telegramResult = sendTelegramMessage(buildTelegramText($order));
logEvent('orders', [
    'action' => 'webhook_paid',
    'order_id' => $order['order_id'],
    'telegram_ok' => $telegramResult['ok'] ?? false,
]);

jsonResponse([
    'ok' => true,
    'message' => 'Đã xác nhận thanh toán và gửi Telegram.',
    'telegram' => $telegramResult,
    'order' => $order,
]);
