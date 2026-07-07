<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

final class ServiceTokenValidator
{
    private const ISSUER = 'maylove-api';
    private const AUDIENCE = 'maylove-storages';

    public function __construct(
        private readonly HmacTokenCodec $codec,
        private readonly int $ttlSeconds = 300,
    ) {
    }

    public function validate(string $token): bool
    {
        $payload = $this->codec->decode($token);
        if ($payload === null) {
            return false;
        }

        if (($payload['iss'] ?? null) !== self::ISSUER) {
            return false;
        }

        if (($payload['aud'] ?? null) !== self::AUDIENCE) {
            return false;
        }

        if (!is_string($payload['scope'] ?? null) || $payload['scope'] === '') {
            return false;
        }

        $now = time();
        $iat = $payload['iat'] ?? null;
        $exp = $payload['exp'] ?? null;

        if (!is_int($iat) || !is_int($exp)) {
            return false;
        }

        if ($iat > $now + 60) {
            return false;
        }

        if ($exp < $now) {
            return false;
        }

        if ($exp - $iat > $this->ttlSeconds + 60) {
            return false;
        }

        return true;
    }
}
