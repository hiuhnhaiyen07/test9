<?php

declare(strict_types=1);

require __DIR__ . '/helpers.php';

$orderId = trim((string) ($_GET['order_id'] ?? ''));
if ($orderId === '') {
    jsonResponse(['ok' => false, 'message' => 'Thiếu order_id.'], 422);
}

$order = loadOrder($orderId);
if ($order === null) {
    jsonResponse(['ok' => false, 'message' => 'Không tìm thấy đơn hàng.'], 404);
}

jsonResponse([
    'ok' => true,
    'order' => $order,
]);
