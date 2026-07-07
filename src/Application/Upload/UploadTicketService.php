<?php

declare(strict_types=1);

namespace App\Application\Upload;

use App\Infrastructure\Persistence\Entity\StorageFile;
use App\Infrastructure\Security\HmacTokenCodec;
use Symfony\Component\Uid\Uuid;

final class UploadTicketService
{
    public function __construct(
        private readonly HmacTokenCodec $codec,
    ) {
    }

    public function issue(
        StorageFile $file,
        string $mediaType,
        string $mimeType,
        int $maxSizeBytes,
        int $ttlSeconds,
        ?string $userId = null,
    ): string {
        $now = time();

        return $this->codec->encode([
            'fid' => (string) $file->getId(),
            'ctx' => $file->getContext(),
            'ctxid' => $file->getContextId(),
            'uid' => $userId,
            'media' => $mediaType,
            'mime' => $mimeType,
            'max' => $maxSizeBytes,
            'iat' => $now,
            'exp' => $now + $ttlSeconds,
        ]);
    }

    /**
     * @return array{fid: string, ctx: string, ctxid: string, uid: ?string, media: string, mime: string, max: int}|null
     */
    public function validate(string $ticket): ?array
    {
        $payload = $this->codec->decode($ticket);
        if ($payload === null) {
            return null;
        }

        $required = ['fid', 'ctx', 'ctxid', 'media', 'mime', 'max', 'iat', 'exp'];
        foreach ($required as $field) {
            if (!array_key_exists($field, $payload)) {
                return null;
            }
        }

        if (!is_string($payload['fid']) || !Uuid::isValid($payload['fid'])) {
            return null;
        }

        if (!is_string($payload['ctx']) || !is_string($payload['ctxid']) || !is_string($payload['media']) || !is_string($payload['mime'])) {
            return null;
        }

        if (!is_int($payload['max']) || !is_int($payload['iat']) || !is_int($payload['exp'])) {
            return null;
        }

        if ($payload['exp'] < time()) {
            return null;
        }

        return [
            'fid' => $payload['fid'],
            'ctx' => $payload['ctx'],
            'ctxid' => $payload['ctxid'],
            'uid' => is_string($payload['uid'] ?? null) ? $payload['uid'] : null,
            'media' => $payload['media'],
            'mime' => $payload['mime'],
            'max' => $payload['max'],
        ];
    }
}
