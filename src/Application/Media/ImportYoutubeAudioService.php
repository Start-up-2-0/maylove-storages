<?php

declare(strict_types=1);

namespace App\Application\Media;

use App\Domain\File\Exception\StorageException;
use App\Domain\File\StorageDriverInterface;
use App\Infrastructure\Persistence\Entity\StorageFile;
use App\Infrastructure\Persistence\Repository\StorageFileRepository;
use App\Infrastructure\Storage\StoragePathResolver;

final class ImportYoutubeAudioService
{
    private const MAX_DURATION_SECONDS = 600;

    public function __construct(
        private readonly StorageFileRepository $fileRepository,
        private readonly StorageDriverInterface $storageDriver,
        private readonly StoragePathResolver $pathResolver,
        private readonly AudioProbeService $audioProbeService,
        private readonly string $storageRoot,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function import(string $context, string $contextId, string $url, ?string $userId = null): array
    {
        if ($context === '' || $contextId === '' || trim($url) === '') {
            throw new StorageException('Payload inválido.', 'VALIDATION_ERROR', 400);
        }

        if (!preg_match('#(youtube\.com|youtu\.be)#i', $url)) {
            throw new StorageException('URL do YouTube inválida.', 'VALIDATION_ERROR', 400);
        }

        $file = new StorageFile($context, $contextId, 'youtube-audio.mp3');
        $pendingPath = $this->pathResolver->buildPendingPath($file, 'audio');
        $file->setRelativePath($pendingPath);
        $this->fileRepository->save($file);

        $absolutePending = $this->storageDriver->absolutePath($pendingPath);
        $directory = \dirname($absolutePending);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $tempBase = rtrim($this->storageRoot, '/\\').'/tmp/youtube-'.(string) $file->getId();
        $audioTemplate = $tempBase.'.%(ext)s';

        $command = sprintf(
            'yt-dlp -f bestaudio --extract-audio --audio-format mp3 --audio-quality 2 -o %s %s 2>&1',
            escapeshellarg($audioTemplate),
            escapeshellarg($url),
        );
        exec($command, $output, $exitCode);

        $downloaded = $tempBase.'.mp3';
        if ($exitCode !== 0 || !is_file($downloaded)) {
            throw new StorageException('Não foi possível baixar o áudio do YouTube.', 'YOUTUBE_IMPORT_FAILED', 422);
        }

        $duration = $this->audioProbeService->probeDuration($downloaded);
        if ($duration !== null && $duration > self::MAX_DURATION_SECONDS) {
            @unlink($downloaded);
            throw new StorageException('Áudio excede a duração máxima permitida.', 'AUDIO_TOO_LONG', 422);
        }

        $finalPath = $this->pathResolver->buildFinalPath($file, 'audio');
        $absoluteFinal = $this->storageDriver->absolutePath($finalPath);
        $finalDir = \dirname($absoluteFinal);
        if (!is_dir($finalDir)) {
            mkdir($finalDir, 0775, true);
        }

        if (!rename($downloaded, $absoluteFinal)) {
            $stream = fopen($downloaded, 'rb');
            if ($stream === false) {
                throw new StorageException('Falha ao mover áudio importado.', 'UPLOAD_FAILED', 500);
            }
            $this->storageDriver->putStream($finalPath, $stream);
            fclose($stream);
            @unlink($downloaded);
        }

        $file->setRelativePath($finalPath);
        $file->setMimeType('audio/mpeg');
        $file->setSizeBytes((int) filesize($absoluteFinal));
        $file->setSha256(hash_file('sha256', $absoluteFinal) ?: '');
        $file->markActive();
        $this->fileRepository->save($file);

        return [
            'file_id' => (string) $file->getId(),
            'original_filename' => 'youtube-audio.mp3',
            'mime_type' => 'audio/mpeg',
            'size_bytes' => $file->getSizeBytes(),
            'duration_seconds' => $duration,
        ];
    }
}
