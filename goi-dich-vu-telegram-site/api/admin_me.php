<?php

declare(strict_types=1);

require __DIR__ . '/helpers.php';

startAdminSession();

jsonResponse([
    'ok' => true,
    'authenticated' => adminIsAuthenticated(),
    'csrf_token' => adminIsAuthenticated() ? adminCsrfToken() : null,
    'public_config' => publicConfig(),
]);
