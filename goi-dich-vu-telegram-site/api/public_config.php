<?php

declare(strict_types=1);

require __DIR__ . '/helpers.php';

jsonResponse([
    'ok' => true,
    'config' => publicConfig(),
]);
