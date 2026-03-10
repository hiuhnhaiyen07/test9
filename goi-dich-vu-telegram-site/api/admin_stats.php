<?php

declare(strict_types=1);

require __DIR__ . '/helpers.php';

requireAdminAuth();
$orders = allOrders();
$stats = summarizeOrders($orders);
$recent = array_slice($orders, 0, 8);

jsonResponse([
    'ok' => true,
    'stats' => $stats,
    'recent_orders' => $recent,
]);
