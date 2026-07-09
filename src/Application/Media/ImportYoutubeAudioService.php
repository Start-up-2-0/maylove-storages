<?php

declare(strict_types=1);

namespace App\Application\Media;

use App\Domain\File\Exception\StorageException;
use App\Domain\File\StorageDriverInterface;
use App\Infrastructure\Persistence\Entity\StorageFile;
use App\Infrastructure\Persistence\Repository\StorageFileRepository;
use App\Infrastructure\Storage\StoragePathResolver;
use Psr\Log\LoggerInterface;

final class ImportYoutubeAudioService
{
    private const MAX_DURATION_SECONDS = 600;

    public function __construct(
        private readonly StorageFileRepository $fileRepository,
        private readonly StorageDriverInterface $storageDriver,
        private readonly StoragePathResolver $pathResolver,
        private readonly AudioProbeService $audioProbeService,
        private readonly LoggerInterface $logger,
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

        $this->assertYoutubeToolingAvailable();

        $file = new StorageFile($context, $contextId, 'youtube-audio.mp3');
        $pendingPath = $this->pathResolver->buildPendingPath($file, 'audio');
        $file->setRelativePath($pendingPath);
        $this->fileRepository->save($file);

        $absolutePending = $this->storageDriver->absolutePath($pendingPath);
        $this->ensureDirectory(\dirname($absolutePending));

        $tempBase = rtrim($this->storageRoot, '/\\').'/tmp/youtube-'.(string) $file->getId();
        $this->ensureDirectory(\dirname($tempBase));

        try {
            $downloaded = $this->downloadYoutubeAudio($url, $tempBase);

            $duration = $this->audioProbeService->probeDuration($downloaded);
            if ($duration !== null && $duration > self::MAX_DURATION_SECONDS) {
                throw new StorageException('Áudio excede a duração máxima permitida.', 'AUDIO_TOO_LONG', 422);
            }

            $finalPath = $this->pathResolver->buildFinalPath($file, 'audio');
            $absoluteFinal = $this->storageDriver->absolutePath($finalPath);
            $this->ensureDirectory(\dirname($absoluteFinal));

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
        } catch (\Throwable $exception) {
            $this->cleanupTempFiles($tempBase);
            throw $exception;
        }
    }

    private function assertYoutubeToolingAvailable(): void
    {
        exec('yt-dlp --version 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new StorageException(
                'Importação do YouTube indisponível no servidor (yt-dlp).',
                'YOUTUBE_UNAVAILABLE',
                503,
            );
        }

        exec('ffmpeg -version 2>&1', $ffmpegOutput, $ffmpegExitCode);
        if ($ffmpegExitCode !== 0) {
            throw new StorageException(
                'Conversão de áudio indisponível no servidor (ffmpeg).',
                'FFMPEG_UNAVAILABLE',
                503,
            );
        }
    }

    private function downloadYoutubeAudio(string $url, string $tempBase): string
    {
        $template = $tempBase.'.%(ext)s';
        $command = sprintf(
            'yt-dlp --no-playlist --no-warnings --socket-timeout 30 --retries 3 '
            .'-f "ba/bestaudio/best" --extract-audio --audio-format mp3 --audio-quality 2 '
            .'--extractor-args %s -o %s %s 2>&1',
            escapeshellarg('youtube:player_client=android,web'),
            escapeshellarg($template),
            escapeshellarg($url),
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            $details = trim(implode("\n", array_slice($output, -8)));
            $this->logger->error('yt-dlp falhou ao importar áudio.', [
                'url' => $url,
                'exit_code' => $exitCode,
                'output' => $details,
            ]);

            throw new StorageException(
                $this->buildDownloadErrorMessage($details),
                'YOUTUBE_IMPORT_FAILED',
                422,
            );
        }

        return $this->locateDownloadedMp3($tempBase);
    }

    private function locateDownloadedMp3(string $tempBase): string
    {
        $mp3 = $tempBase.'.mp3';
        if (is_file($mp3) && filesize($mp3) > 0) {
            return $mp3;
        }

        $candidates = glob($tempBase.'.*') ?: [];
        foreach ($candidates as $path) {
            if (!is_file($path) || filesize($path) === 0) {
                continue;
            }

            if (str_ends_with(strtolower($path), '.mp3')) {
                return $path;
            }
        }

        foreach ($candidates as $path) {
            if (!is_file($path) || filesize($path) === 0) {
                continue;
            }

            $converted = $tempBase.'.mp3';
            $this->convertToMp3($path, $converted);
            @unlink($path);

            return $converted;
        }

        throw new StorageException('Arquivo de áudio não encontrado após download.', 'YOUTUBE_IMPORT_FAILED', 422);
    }

    private function convertToMp3(string $source, string $destination): void
    {
        $command = sprintf(
            'ffmpeg -y -i %s -vn -acodec libmp3lame -q:a 2 %s 2>&1',
            escapeshellarg($source),
            escapeshellarg($destination),
        );
        exec($command, $output, $exitCode);
        if ($exitCode !== 0 || !is_file($destination) || filesize($destination) === 0) {
            throw new StorageException('Falha ao converter áudio para MP3.', 'AUDIO_CONVERT_FAILED', 500);
        }
    }

    private function buildDownloadErrorMessage(string $details): string
    {
        if ($details === '') {
            return 'Não foi possível baixar o áudio do YouTube.';
        }

        if (str_contains($details, 'Private video') || str_contains($details, 'privado')) {
            return 'Este vídeo é privado ou indisponível.';
        }

        if (str_contains($details, 'Sign in to confirm') || str_contains($details, 'bot')) {
            return 'O YouTube bloqueou o download deste vídeo. Tente outro link ou envie um MP3/MP4.';
        }

        $snippet = mb_substr(preg_replace('/\s+/', ' ', $details) ?? $details, 0, 180);

        return 'Não foi possível baixar o áudio do YouTube. '.$snippet;
    }

    private function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (!mkdir($path, 0775, true) && !is_dir($path)) {
            throw new StorageException('Não foi possível criar diretório temporário.', 'STORAGE_ERROR', 500);
        }
    }

    private function cleanupTempFiles(string $tempBase): void
    {
        foreach (glob($tempBase.'.*') ?: [] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}
