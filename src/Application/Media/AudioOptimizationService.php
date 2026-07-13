<?php

declare(strict_types=1);

namespace App\Application\Media;

use App\Domain\File\Exception\StorageException;

final class AudioOptimizationService
{
    public function __construct(
        private readonly int $maxBitrateKbps,
        private readonly AudioProbeService $audioProbeService,
    ) {
    }

    /**
     * Transcodifica áudio para MP3 com bitrate adequado quando necessário.
     *
     * @return array{
     *     absolute_path: string,
     *     mime_type: string,
     *     extension: string,
     *     size_bytes: int,
     *     duration_seconds: ?float
     * }
     */
    public function optimize(string $absolutePath, string $inputMime): array
    {
        if (!is_file($absolutePath)) {
            throw new StorageException('Arquivo de áudio não encontrado.', 'UPLOAD_NOT_FOUND', 404);
        }

        if ($inputMime === 'audio/mpeg' && !$this->shouldTranscode($absolutePath)) {
            return [
                'absolute_path' => $absolutePath,
                'mime_type' => 'audio/mpeg',
                'extension' => '.mp3',
                'size_bytes' => (int) filesize($absolutePath),
                'duration_seconds' => $this->audioProbeService->probeDuration($absolutePath),
            ];
        }

        exec('ffmpeg -version 2>&1', $ffmpegOutput, $ffmpegExitCode);
        if ($ffmpegExitCode !== 0) {
            if ($inputMime === 'audio/mpeg') {
                return [
                    'absolute_path' => $absolutePath,
                    'mime_type' => 'audio/mpeg',
                    'extension' => '.mp3',
                    'size_bytes' => (int) filesize($absolutePath),
                    'duration_seconds' => $this->audioProbeService->probeDuration($absolutePath),
                ];
            }

            throw new StorageException(
                'Conversão de áudio indisponível no servidor (ffmpeg).',
                'AUDIO_PROCESS_UNAVAILABLE',
                500,
            );
        }

        $targetPath = preg_replace('/\.[^.]+$/', '', $absolutePath).'.mp3';
        if ($targetPath === null || $targetPath === $absolutePath) {
            $targetPath = $absolutePath.'.mp3';
        }

        $command = sprintf(
            'ffmpeg -y -i %s -vn -acodec libmp3lame -b:a %dk %s 2>&1',
            escapeshellarg($absolutePath),
            $this->maxBitrateKbps,
            escapeshellarg($targetPath),
        );
        exec($command, $output, $exitCode);

        if ($exitCode !== 0 || !is_file($targetPath)) {
            throw new StorageException('Falha ao otimizar áudio.', 'AUDIO_PROCESS_FAILED', 500);
        }

        if ($targetPath !== $absolutePath && is_file($absolutePath)) {
            unlink($absolutePath);
        }

        return [
            'absolute_path' => $targetPath,
            'mime_type' => 'audio/mpeg',
            'extension' => '.mp3',
            'size_bytes' => (int) filesize($targetPath),
            'duration_seconds' => $this->audioProbeService->probeDuration($targetPath),
        ];
    }

    private function shouldTranscode(string $absolutePath): bool
    {
        $command = sprintf(
            'ffprobe -v error -select_streams a:0 -show_entries stream=bit_rate -of csv=p=0 %s 2>&1',
            escapeshellarg($absolutePath),
        );
        exec($command, $output, $exitCode);
        if ($exitCode !== 0 || !isset($output[0])) {
            return true;
        }

        $bitrate = (int) trim((string) $output[0]);
        if ($bitrate <= 0) {
            return true;
        }

        return $bitrate > ($this->maxBitrateKbps * 1000);
    }
}
