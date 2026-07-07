<?php

declare(strict_types=1);

/**
 * Railway/Docker: config vem das variáveis do platform, não de .env na imagem.
 * Symfony Runtime lê APP_ENV em $_SERVER/$_ENV; Dotenv exige o arquivo .env se não for desabilitado.
 */
$projectDir = \dirname(__DIR__);

$environ = @\file_get_contents('/proc/self/environ');
if (\is_string($environ) && $environ !== '') {
    foreach (\explode("\0", $environ) as $pair) {
        if ($pair === '' || !\str_contains($pair, '=')) {
            continue;
        }
        [$key, $value] = \explode('=', $pair, 2);
        $_SERVER[$key] ??= $value;
        $_ENV[$key] ??= $value;
    }
} elseif (\function_exists('getenv')) {
    $vars = \getenv();
    if (\is_array($vars)) {
        foreach ($vars as $key => $value) {
            if ($value === false) {
                continue;
            }
            $_SERVER[$key] ??= $value;
            $_ENV[$key] ??= $value;
        }
    }
}

if (!\is_file($projectDir.'/.env')) {
    $options = $_SERVER['APP_RUNTIME_OPTIONS'] ?? $_ENV['APP_RUNTIME_OPTIONS'] ?? [];
    if (\is_string($options)) {
        $decoded = \json_decode($options, true);
        $options = \is_array($decoded) ? $decoded : [];
    }
    if (!\is_array($options)) {
        $options = [];
    }

    // Symfony 7.2: APP_RUNTIME_OPTIONS deve ser array (sem json_decode no autoload_runtime.php).
    $_SERVER['APP_RUNTIME_OPTIONS'] = $options + ['disable_dotenv' => true];
}
