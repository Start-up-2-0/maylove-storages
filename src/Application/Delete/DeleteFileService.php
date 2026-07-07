<?php

declare(strict_types=1);

namespace App\Application\Delete;

use App\Domain\File\Exception\StorageException;
use App\Domain\File\FileStatus;
use App\Domain\File\StorageDriverInterface;
use App\Infrastructure\Persistence\Repository\StorageFileRepository;
use Symfony\Component\Uid\Uuid;

final class DeleteFileService
{
    public function __construct(
        private readonly StorageFileRepository $fileRepository,
        private readonly StorageDriverInterface $storageDriver,
    ) {
    }

    public function delete(string $fileId): void
    {
        $file = $this->fileRepository->find(Uuid::fromString($fileId));
        if ($file === null || $file->getStatus() === FileStatus::Deleted) {
            throw new StorageException('Arquivo não encontrado.', 'FILE_NOT_FOUND', 404);
        }

        if ($file->getRelativePath() !== '' && $this->storageDriver->exists($file->getRelativePath())) {
            $this->storageDriver->delete($file->getRelativePath());
        }

        $file->markDeleted();
        $this->fileRepository->save($file);
    }
}
