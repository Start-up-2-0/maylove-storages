<?php

declare(strict_types=1);

namespace App\Application\Media;

use App\Domain\File\Exception\StorageException;
use App\Domain\File\FileStatus;
use App\Domain\File\StorageDriverInterface;
use App\Infrastructure\Persistence\Repository\StorageFileRepository;
use Symfony\Component\Uid\Uuid;

final class ExtractAudioFromVideoService
{
    public function __construct(
        private readonly StorageFileRepository $fileRepository,
        private readonly StorageDriverInterface $storageDriver,
        private readonly AudioProbeService $audioProbeService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function extract(string $fileId): array
    {
        $file = $this->fileRepository->find(Uuid::fromString($fileId));
        if ($file === null || $file->getStatus() === FileStatus::Deleted) {
            throw new StorageException('Arquivo não encontrado.', 'FILE_NOT_FOUND', 404);
        }

        $sourcePath = $file->getRelativePath();
        $absoluteSource = $this->storageDriver->absolutePath($sourcePath);
        if (!is_file($absoluteSource)) {
            throw new StorageException('Upload não encontrado.', 'UPLOAD_NOT_FOUND', 404);
        }

        $outputPath = preg_replace('/\.[^.]+$/', '', $sourcePath).'.mp3';
        if ($outputPath === null || $outputPath === $sourcePath) {
            $outputPath = $sourcePath.'.mp3';
        }
        $absoluteOutput = $this->storageDriver->absolutePath($outputPath);

        $outputDir = \dirname($absoluteOutput);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0775, true);
        }

        $command = sprintf(
            'ffmpeg -y -i %s -vn -acodec libmp3lame -q:a 2 %s 2>&1',
            escapeshellarg($absoluteSource),
            escapeshellarg($absoluteOutput),
        );
        exec($command, $output, $exitCode);
        if ($exitCode !== 0 || !is_file($absoluteOutput)) {
            throw new StorageException('Falha ao extrair áudio do vídeo.', 'AUDIO_EXTRACT_FAILED', 500);
        }

        if ($sourcePath !== $outputPath && $this->storageDriver->exists($sourcePath)) {
            $this->storageDriver->delete($sourcePath);
        }

        $file->setRelativePath($outputPath);
        $file->setMimeType('audio/mpeg');
        $file->setSizeBytes((int) filesize($absoluteOutput));
        $file->setSha256(hash_file('sha256', $absoluteOutput) ?: '');
        $file->markActive();
        $this->fileRepository->save($file);

        return [
            'file_id' => (string) $file->getId(),
            'mime_type' => 'audio/mpeg',
            'size_bytes' => $file->getSizeBytes(),
            'duration_seconds' => $this->audioProbeService->probeDuration($absoluteOutput),
            'path' => $outputPath,
        ];
    }
}
