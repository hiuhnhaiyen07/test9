<?php

declare(strict_types=1);

require __DIR__ . '/helpers.php';

requireAdminAuth();

$query = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? 'all'));
$page = max(1, (int) ($_GET['page'] ?? 1));
$pageSize = max(1, min(50, (int) ($_GET['page_size'] ?? 10)));

$orders = filterOrders(allOrders(), $query, $status);
$result = paginate($orders, $page, $pageSize);

jsonResponse([
    'ok' => true,
    'items' => $result['items'],
    'pagination' => $result['pagination'],
    'filters' => [
        'q' => $query,
        'status' => $status,
    ],
]);
