<?php

declare(strict_types=1);

namespace App\Infrastructure\Validation;

final class MimeMagicValidator
{
    /**
     * @var array<string, list<string>>
     */
    private const SIGNATURES = [
        'image/jpeg' => ['ffd8ff'],
        'image/png' => ['89504e47'],
        'image/webp' => ['52494646'],
        'video/mp4' => ['66747970'],
        'audio/mpeg' => ['494433', 'fff'],
    ];

    public function validate(string $mimeType, string $filePath): bool
    {
        if (!is_readable($filePath)) {
            return false;
        }

        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            return false;
        }

        $bytes = fread($handle, 12);
        fclose($handle);

        if ($bytes === false || $bytes === '') {
            return false;
        }

        $hex = strtolower(bin2hex($bytes));

        return match ($mimeType) {
            'image/jpeg' => str_starts_with($hex, 'ffd8ff'),
            'image/png' => str_starts_with($hex, '89504e47'),
            'image/webp' => str_starts_with($hex, '52494646') && str_contains($hex, '57454250'),
            'video/mp4' => $this->containsAtOffset($hex, '66747970', 4),
            'audio/mpeg' => str_starts_with($hex, '494433') || str_starts_with($hex, 'fff'),
            default => false,
        };
    }

    private function containsAtOffset(string $hex, string $needle, int $byteOffset): bool
    {
        $offset = $byteOffset * 2;

        return substr($hex, $offset, strlen($needle)) === $needle
            || str_contains($hex, $needle);
    }
}
