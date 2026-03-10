<?php
return [
    'timezone' => 'Asia/Ho_Chi_Minh',
    'session_name' => 'premium_admin_session',
    'webhook_secret' => 'CHANGE_ME_SECRET',
    'telegram_bot_token' => 'BOT_TOKEN_HERE',
    'telegram_chat_id' => 'CHAT_ID_HERE',
    'admin_password_hash' => '$2y$12$Ev59g0.gcAVbBLZtRUvV7uHhSJ/Kg91vaQXMhhW8vGMMiTwfaXwoe', // admin123456
    'public' => [
        'site_name' => 'Premium Service',
        'site_tagline' => 'Dich vu so xu ly qua Gmail, webhook va Telegram admin',
        'price' => 29000,
        'plan_name' => '29.000đ / 1 tháng',
        'bank_name' => 'MB Bank',
        'bank_account_name' => 'LE KIM YEN',
        'bank_account_number' => '610793',
        'support_link' => 'https://t.me/your_support',
        'support_label' => 'Telegram hỗ trợ',
        'qr_image_path' => 'img/mbbank-qr.png',
        'hero_badge' => 'Webhook xác nhận tự động',
        'cta_subtitle' => 'Nhập Gmail, thanh toán QR, admin nhận Telegram để xử lý.',
    ],
    'limits' => [
        'create_order_per_minute' => 3,
        'create_order_per_hour' => 10,
        'admin_login_per_10_minutes' => 8,
        'minimum_form_fill_seconds' => 2,
        'duplicate_order_cooldown_seconds' => 120,
        'status_poll_interval_ms' => 5000,
    ],
];
