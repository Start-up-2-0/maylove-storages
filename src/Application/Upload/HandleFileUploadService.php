<?php

declare(strict_types=1);

namespace App\Application\Upload;

use App\Domain\File\Exception\StorageException;
use App\Domain\File\FileStatus;
use App\Domain\File\StorageDriverInterface;
use App\Infrastructure\Persistence\Repository\StorageFileRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

final class HandleFileUploadService
{
    public function __construct(
        private readonly StorageFileRepository $fileRepository,
        private readonly UploadTicketService $uploadTicketService,
        private readonly StorageDriverInterface $storageDriver,
    ) {
    }

    /**
     * @return array{file_id: string, status: string, size_bytes: int}
     */
    public function upload(string $ticketValue, UploadedFile $uploadedFile): array
    {
        $ticket = $this->uploadTicketService->validate($ticketValue);
        if ($ticket === null) {
            throw new StorageException('Upload ticket inválido ou expirado.', 'INVALID_UPLOAD_TICKET', 401);
        }

        $file = $this->fileRepository->find(Uuid::fromString($ticket['fid']));
        if ($file === null || $file->getStatus() !== FileStatus::Pending) {
            throw new StorageException('Arquivo não encontrado.', 'FILE_NOT_FOUND', 404);
        }

        if ($file->getContext() !== $ticket['ctx'] || $file->getContextId() !== $ticket['ctxid']) {
            throw new StorageException('Upload ticket inválido.', 'INVALID_UPLOAD_TICKET', 401);
        }

        $sizeBytes = $uploadedFile->getSize() ?: 0;
        if ($sizeBytes <= 0) {
            throw new StorageException('Arquivo vazio.', 'EMPTY_FILE', 400);
        }

        if ($sizeBytes > $ticket['max']) {
            throw new StorageException('Arquivo excede o tamanho máximo permitido.', 'FILE_TOO_LARGE', 413);
        }

        $pendingPath = $file->getRelativePath();
        $stream = fopen($uploadedFile->getPathname(), 'rb');
        if ($stream === false) {
            throw new StorageException('Falha ao ler upload.', 'UPLOAD_FAILED', 500);
        }

        $this->storageDriver->putStream($pendingPath, $stream);
        fclose($stream);

        $file->setSizeBytes($sizeBytes);
        $this->fileRepository->save($file);

        return [
            'file_id' => (string) $file->getId(),
            'status' => 'pending_confirm',
            'size_bytes' => $sizeBytes,
        ];
    }
}
