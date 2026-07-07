<?php

declare(strict_types=1);

namespace App\Application\Upload;

use App\Domain\File\Exception\StorageException;
use App\Infrastructure\Persistence\Entity\StorageFile;
use App\Infrastructure\Persistence\Repository\StorageFileRepository;
use App\Infrastructure\Storage\StoragePathResolver;

final class CreateUploadTicketService
{
    public function __construct(
        private readonly StorageFileRepository $fileRepository,
        private readonly UploadTicketService $uploadTicketService,
        private readonly StoragePathResolver $pathResolver,
        private readonly string $publicUrl,
        private readonly int $defaultTtlSeconds,
    ) {
    }

    /**
     * @return array{file_id: string, upload_url: string, upload_ticket: string, expires_at: string}
     */
    public function create(
        string $context,
        string $contextId,
        string $mediaType,
        string $originalFilename,
        string $mimeType,
        int $maxSizeBytes,
        int $ttlSeconds,
        ?string $userId = null,
    ): array {
        if ($context === '' || $contextId === '' || $mediaType === '' || $originalFilename === '') {
            throw new StorageException('Payload inválido.', 'VALIDATION_ERROR', 400);
        }

        $this->assertFilename($originalFilename);

        $file = new StorageFile($context, $contextId, basename($originalFilename));
        $file->setRelativePath($this->pathResolver->buildPendingPath($file, $mediaType));
        $this->fileRepository->save($file);

        $ttl = $ttlSeconds > 0 ? $ttlSeconds : $this->defaultTtlSeconds;
        $ticket = $this->uploadTicketService->issue($file, $mediaType, $mimeType, $maxSizeBytes, $ttl, $userId);
        $expiresAt = (new \DateTimeImmutable())->setTimestamp(time() + $ttl);

        return [
            'file_id' => (string) $file->getId(),
            'upload_url' => rtrim($this->publicUrl, '/').'/api/v1/files/upload',
            'upload_ticket' => $ticket,
            'expires_at' => $expiresAt->format(\DateTimeInterface::ATOM),
        ];
    }

    private function assertFilename(string $filename): void
    {
        $basename = basename(str_replace('\\', '/', $filename));
        if ($basename === '' || str_contains($basename, '..') || str_contains($basename, '/')) {
            throw new StorageException('Nome de arquivo inválido.', 'INVALID_FILENAME', 400);
        }
    }
}
