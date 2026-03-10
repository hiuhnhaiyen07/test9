<?php

declare(strict_types=1);

require __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'message' => 'Method không hợp lệ.'], 405);
}

requireAdminAuth(true);
adminLogout();
jsonResponse(['ok' => true, 'message' => 'Đã đăng xuất.']);
