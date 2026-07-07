<?php

/**
 * Symfony Runtime resolve APP_ENV/APP_DEBUG apenas em $_SERVER e $_ENV.
 * Variáveis injetadas pelo Railway ficam no ambiente do processo (getenv),
 * mas php-cli-alpine e php -S nem sempre as copiam para os superglobals.
 */
if (!\function_exists('getenv')) {
    return;
}

$env = getenv();
if (!\is_array($env)) {
    return;
}

foreach ($env as $key => $value) {
    if ($value === false) {
        continue;
    }
    $_SERVER[$key] ??= $value;
    $_ENV[$key] ??= $value;
}
