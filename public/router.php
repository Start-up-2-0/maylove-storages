<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($path === '/health-live.php') {
    require __DIR__.'/health-live.php';

    return true;
}

require __DIR__.'/index.php';
