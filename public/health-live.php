<?php

declare(strict_types=1);

header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'service' => 'maylove-storages',
], JSON_THROW_ON_ERROR);
