<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

final class HmacTokenCodec
{
    public function __construct(
        private readonly string $secret,
    ) {
        if (strlen($this->secret) < 32) {
            throw new \InvalidArgumentException('Token secret deve possuir ao menos 32 caracteres.');
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function encode(array $payload): string
    {
        $payloadJson = json_encode($payload, JSON_THROW_ON_ERROR);
        $payloadEncoded = $this->base64UrlEncode($payloadJson);
        $signature = $this->base64UrlEncode(hash_hmac('sha256', $payloadEncoded, $this->secret, true));

        return $payloadEncoded.'.'.$signature;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function decode(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }

        [$payloadEncoded, $signature] = $parts;
        $expectedSignature = $this->base64UrlEncode(hash_hmac('sha256', $payloadEncoded, $this->secret, true));

        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        try {
            $payloadJson = $this->base64UrlDecode($payloadEncoded);
            /** @var array<string, mixed> $payload */
            $payload = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);

            return $payload;
        } catch (\JsonException) {
            return null;
        }
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $padded = strtr($value, '-_', '+/');
        $mod = strlen($padded) % 4;
        if ($mod !== 0) {
            $padded .= str_repeat('=', 4 - $mod);
        }

        $decoded = base64_decode($padded, true);
        if ($decoded === false) {
            throw new \InvalidArgumentException('Token inválido.');
        }

        return $decoded;
    }
}
