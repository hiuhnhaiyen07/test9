<?php

declare(strict_types=1);

require __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    requireAdminAuth();
    $cfg = config();
    $public = $cfg['public'] ?? [];
    jsonResponse([
        'ok' => true,
        'settings' => [
            'site_name' => (string) ($public['site_name'] ?? ''),
            'site_tagline' => (string) ($public['site_tagline'] ?? ''),
            'price' => (int) ($public['price'] ?? 29000),
            'plan_name' => (string) ($public['plan_name'] ?? ''),
            'bank_name' => (string) ($public['bank_name'] ?? ''),
            'bank_account_name' => (string) ($public['bank_account_name'] ?? ''),
            'bank_account_number' => (string) ($public['bank_account_number'] ?? ''),
            'support_link' => (string) ($public['support_link'] ?? ''),
            'support_label' => (string) ($public['support_label'] ?? ''),
            'hero_badge' => (string) ($public['hero_badge'] ?? ''),
            'cta_subtitle' => (string) ($public['cta_subtitle'] ?? ''),
            'telegram_bot_token' => (string) ($cfg['telegram_bot_token'] ?? ''),
            'telegram_chat_id' => (string) ($cfg['telegram_chat_id'] ?? ''),
            'webhook_secret' => (string) ($cfg['webhook_secret'] ?? ''),
            'qr_image_path' => publicConfig()['qr_image_path'],
        ],
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'message' => 'Method không hợp lệ.'], 405);
}

requireAdminAuth(true);
$existingOverrides = loadSettingsOverrides();
$publicOverrides = $existingOverrides['public'] ?? [];

$publicOverrides['site_name'] = cleanText((string) ($_POST['site_name'] ?? 'Premium Service'), 100);
$publicOverrides['site_tagline'] = cleanText((string) ($_POST['site_tagline'] ?? ''), 180);
$publicOverrides['price'] = max(0, (int) ($_POST['price'] ?? 29000));
$publicOverrides['plan_name'] = cleanText((string) ($_POST['plan_name'] ?? '29.000đ / 1 tháng'), 120);
$publicOverrides['bank_name'] = cleanText((string) ($_POST['bank_name'] ?? 'MB Bank'), 80);
$publicOverrides['bank_account_name'] = cleanText((string) ($_POST['bank_account_name'] ?? ''), 120);
$publicOverrides['bank_account_number'] = preg_replace('/\s+/', '', (string) ($_POST['bank_account_number'] ?? ''));
$publicOverrides['support_link'] = cleanText((string) ($_POST['support_link'] ?? '#'), 255);
$publicOverrides['support_label'] = cleanText((string) ($_POST['support_label'] ?? 'Telegram hỗ trợ'), 80);
$publicOverrides['hero_badge'] = cleanText((string) ($_POST['hero_badge'] ?? 'Webhook xác nhận tự động'), 100);
$publicOverrides['cta_subtitle'] = cleanText((string) ($_POST['cta_subtitle'] ?? ''), 180);

if (!empty($_FILES['qr_image']) && ($_FILES['qr_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    if ((int) $_FILES['qr_image']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['ok' => false, 'message' => 'Upload QR thất bại.'], 422);
    }

    $tmp = (string) $_FILES['qr_image']['tmp_name'];
    $info = @getimagesize($tmp);
    if ($info === false) {
        jsonResponse(['ok' => false, 'message' => 'File QR phải là ảnh hợp lệ.'], 422);
    }

    $mime = (string) ($info['mime'] ?? '');
    $extensionMap = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    if (!isset($extensionMap[$mime])) {
        jsonResponse(['ok' => false, 'message' => 'Chỉ hỗ trợ PNG, JPG hoặc WEBP cho QR.'], 422);
    }

    $targetRelative = 'uploads/payment-qr.' . $extensionMap[$mime];
    $targetAbsolute = rootPath($targetRelative);
    foreach (glob(rootPath('uploads/payment-qr.*')) ?: [] as $oldFile) {
        if (is_file($oldFile)) {
            @unlink($oldFile);
        }
    }
    if (!move_uploaded_file($tmp, $targetAbsolute)) {
        jsonResponse(['ok' => false, 'message' => 'Không lưu được file QR đã tải lên.'], 500);
    }
    $publicOverrides['qr_image_path'] = $targetRelative;
}

$existingOverrides['public'] = $publicOverrides;
$existingOverrides['telegram_bot_token'] = cleanText((string) ($_POST['telegram_bot_token'] ?? ''), 160);
$existingOverrides['telegram_chat_id'] = cleanText((string) ($_POST['telegram_chat_id'] ?? ''), 80);
$existingOverrides['webhook_secret'] = cleanText((string) ($_POST['webhook_secret'] ?? ''), 120);

$newPassword = (string) ($_POST['new_admin_password'] ?? '');
if ($newPassword !== '') {
    if (textLength($newPassword) < 8) {
        jsonResponse(['ok' => false, 'message' => 'Mật khẩu admin mới cần tối thiểu 8 ký tự.'], 422);
    }
    $existingOverrides['admin_password_hash'] = createPasswordHash($newPassword);
}

saveSettingsOverrides($existingOverrides);
config(true);
logEvent('admin', ['action' => 'update_settings']);

jsonResponse([
    'ok' => true,
    'message' => 'Đã cập nhật cấu hình.',
    'settings' => publicConfig(),
]);
