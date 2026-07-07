<?php

declare(strict_types=1);

namespace App\Application\List;

use App\Infrastructure\Persistence\Repository\StorageFileRepository;

final class ListFilesService
{
    public function __construct(
        private readonly StorageFileRepository $fileRepository,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listByContext(string $context, string $contextId): array
    {
        $files = $this->fileRepository->findByContext($context, $contextId);

        return array_map(static fn ($file) => [
            'file_id' => (string) $file->getId(),
            'context' => $file->getContext(),
            'context_id' => $file->getContextId(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSizeBytes(),
            'status' => $file->getStatus()->value,
            'path' => $file->getRelativePath(),
            'created_at' => $file->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], $files);
    }
}
