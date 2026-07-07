<?php

declare(strict_types=1);

namespace App\Application\Url;

use App\Domain\File\Exception\StorageException;
use App\Domain\File\FileVisibility;
use App\Infrastructure\Persistence\Entity\StorageFile;
use App\Infrastructure\Persistence\Repository\StorageFileRepository;
use App\Infrastructure\Security\HmacTokenCodec;
use Symfony\Component\Uid\Uuid;

final class FileUrlService
{
    public function __construct(
        private readonly StorageFileRepository $fileRepository,
        private readonly HmacTokenCodec $codec,
        private readonly string $publicUrl,
        private readonly int $minTtlSeconds,
        private readonly int $maxTtlSeconds,
    ) {
    }

    /**
     * @return array{url: string, expires_at: string}
     */
    public function createUrl(string $fileId, string $visibility, int $ttlSeconds): array
    {
        $file = $this->fileRepository->findActiveById(Uuid::fromString($fileId));
        if ($file === null) {
            throw new StorageException('Arquivo não encontrado.', 'FILE_NOT_FOUND', 404);
        }

        if ($visibility === 'public' && $file->getVisibility() !== FileVisibility::Public) {
            throw new StorageException('Arquivo não é público.', 'FILE_NOT_PUBLIC', 403);
        }

        $ttl = max($this->minTtlSeconds, min($ttlSeconds, $this->maxTtlSeconds));
        $expiresAt = (new \DateTimeImmutable())->modify(sprintf('+%d seconds', $ttl));
        $token = $this->codec->encode([
            'fid' => (string) $file->getId(),
            'path' => $file->getRelativePath(),
            'vis' => $visibility,
            'exp' => $expiresAt->getTimestamp(),
        ]);

        return [
            'url' => rtrim($this->publicUrl, '/').'/serve/'.rawurlencode((string) $file->getId()).'?token='.rawurlencode($token),
            'expires_at' => $expiresAt->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array{file: StorageFile, path: string}
     */
    public function resolveServeToken(string $fileId, string $token): array
    {
        $payload = $this->codec->decode($token);
        if ($payload === null) {
            throw new StorageException('URL inválida.', 'INVALID_URL_TOKEN', 401);
        }

        if (($payload['fid'] ?? null) !== $fileId) {
            throw new StorageException('URL inválida.', 'INVALID_URL_TOKEN', 401);
        }

        $exp = $payload['exp'] ?? null;
        if (!is_int($exp) || $exp < time()) {
            throw new StorageException('URL expirada.', 'URL_EXPIRED', 401);
        }

        $file = $this->fileRepository->findActiveById(Uuid::fromString($fileId));
        if ($file === null) {
            throw new StorageException('Arquivo não encontrado.', 'FILE_NOT_FOUND', 404);
        }

        $path = $payload['path'] ?? '';
        if (!is_string($path) || $path === '' || $path !== $file->getRelativePath()) {
            throw new StorageException('URL inválida.', 'INVALID_URL_TOKEN', 401);
        }

        return ['file' => $file, 'path' => $path];
    }
}
