<?php

declare(strict_types=1);

namespace App\Application\Confirm;

use App\Application\Media\AudioOptimizationService;
use App\Application\Media\AudioProbeService;
use App\Application\Media\ImageOptimizationService;
use App\Domain\File\Exception\StorageException;
use App\Domain\File\FileStatus;
use App\Domain\File\StorageDriverInterface;
use App\Infrastructure\Persistence\Entity\StorageFile;
use App\Infrastructure\Persistence\Repository\StorageFileRepository;
use App\Infrastructure\Storage\StoragePathResolver;
use App\Infrastructure\Validation\MimeMagicValidator;
use Symfony\Component\Uid\Uuid;

final class ConfirmFileService
{
    public function __construct(
        private readonly StorageFileRepository $fileRepository,
        private readonly StorageDriverInterface $storageDriver,
        private readonly StoragePathResolver $pathResolver,
        private readonly MimeMagicValidator $mimeValidator,
        private readonly AudioProbeService $audioProbeService,
        private readonly ImageOptimizationService $imageOptimizationService,
        private readonly AudioOptimizationService $audioOptimizationService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function confirm(string $fileId, string $mediaType, string $expectedMime): array
    {
        $file = $this->fileRepository->find(Uuid::fromString($fileId));
        if ($file === null || $file->getStatus() === FileStatus::Deleted) {
            throw new StorageException('Arquivo não encontrado.', 'FILE_NOT_FOUND', 404);
        }

        if ($file->getStatus() === FileStatus::Active) {
            return $this->serialize($file, $mediaType);
        }

        $pendingPath = $file->getRelativePath();
        if (!$this->storageDriver->exists($pendingPath)) {
            throw new StorageException('Upload pendente não encontrado.', 'UPLOAD_NOT_FOUND', 404);
        }

        $absolutePath = $this->storageDriver->absolutePath($pendingPath);
        if (!$this->mimeValidator->validate($expectedMime, $absolutePath)) {
            $this->storageDriver->delete($pendingPath);
            throw new StorageException('MIME inválido para o arquivo enviado.', 'INVALID_MIME', 422);
        }

        $outputExtension = null;
        $durationSeconds = null;

        if ($mediaType === 'photo') {
            $optimized = $this->imageOptimizationService->optimize($absolutePath, $expectedMime);
            $absolutePath = $optimized['absolute_path'];
            $expectedMime = $optimized['mime_type'];
            $outputExtension = $optimized['extension'];
            if ($absolutePath !== $this->storageDriver->absolutePath($pendingPath)) {
                $this->storageDriver->delete($pendingPath);
                $pendingPath = $this->replaceExtension($pendingPath, $outputExtension);
                $this->moveOptimizedToPending($absolutePath, $pendingPath);
            }
        }

        if ($mediaType === 'audio') {
            $optimized = $this->audioOptimizationService->optimize($absolutePath, $expectedMime);
            $absolutePath = $optimized['absolute_path'];
            $expectedMime = $optimized['mime_type'];
            $outputExtension = $optimized['extension'];
            $durationSeconds = $optimized['duration_seconds'];
            if ($absolutePath !== $this->storageDriver->absolutePath($pendingPath)) {
                $this->storageDriver->delete($pendingPath);
                $pendingPath = $this->replaceExtension($pendingPath, $outputExtension);
                $this->moveOptimizedToPending($absolutePath, $pendingPath);
            }
        }

        $finalPath = $this->pathResolver->buildFinalPath($file, $mediaType, $outputExtension);
        $this->moveFile($pendingPath, $finalPath);

        $sha256 = hash_file('sha256', $this->storageDriver->absolutePath($finalPath)) ?: '';
        $sizeBytes = (int) filesize($this->storageDriver->absolutePath($finalPath));
        $file->setRelativePath($finalPath);
        $file->setMimeType($expectedMime);
        $file->setSizeBytes($sizeBytes);
        $file->setSha256($sha256);
        $file->markActive();
        $this->fileRepository->save($file);

        return $this->serialize($file, $mediaType, $durationSeconds);
    }

    private function moveOptimizedToPending(string $absoluteSource, string $pendingRelativePath): void
    {
        $pendingAbsolute = $this->storageDriver->absolutePath($pendingRelativePath);
        $directory = \dirname($pendingAbsolute);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        if (!rename($absoluteSource, $pendingAbsolute)) {
            $stream = fopen($absoluteSource, 'rb');
            if ($stream === false) {
                throw new StorageException('Falha ao mover arquivo otimizado.', 'FILE_MOVE_FAILED', 500);
            }
            $this->storageDriver->putStream($pendingRelativePath, $stream);
            fclose($stream);
            unlink($absoluteSource);
        }
    }

    private function replaceExtension(string $relativePath, string $extension): string
    {
        $base = preg_replace('/\.[^.]+$/', '', $relativePath);

        return ($base ?? $relativePath).$extension;
    }

    private function moveFile(string $from, string $to): void
    {
        if ($from === $to) {
            return;
        }

        $fromAbsolute = $this->storageDriver->absolutePath($from);
        $toAbsolute = $this->storageDriver->absolutePath($to);
        $directory = \dirname($toAbsolute);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        if (!rename($fromAbsolute, $toAbsolute)) {
            $stream = $this->storageDriver->readStream($from);
            $this->storageDriver->putStream($to, $stream);
            fclose($stream);
            $this->storageDriver->delete($from);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(StorageFile $file, string $mediaType, ?float $durationSeconds = null): array
    {
        $dimensions = $this->resolveImageDimensions($file, $mediaType);

        $payload = [
            'file_id' => (string) $file->getId(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSizeBytes(),
            'sha256' => $file->getSha256(),
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'thumbnail_url' => null,
            'path' => $file->getRelativePath(),
        ];

        if ($mediaType === 'audio') {
            $payload['duration_seconds'] = $durationSeconds ?? $this->audioProbeService->probeDuration(
                $this->storageDriver->absolutePath($file->getRelativePath()),
            );
        }

        return $payload;
    }

    /**
     * @return array{width: ?int, height: ?int}
     */
    private function resolveImageDimensions(StorageFile $file, string $mediaType): array
    {
        if ($mediaType !== 'photo' || $file->getMimeType() === null) {
            return ['width' => null, 'height' => null];
        }

        $info = @getimagesize($this->storageDriver->absolutePath($file->getRelativePath()));
        if ($info === false) {
            return ['width' => null, 'height' => null];
        }

        return ['width' => $info[0], 'height' => $info[1]];
    }
}
