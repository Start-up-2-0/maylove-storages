<?php

declare(strict_types=1);

namespace App\Application\Media;

final class AudioProbeService
{
    public function probeDuration(string $absolutePath): ?float
    {
        if (!is_file($absolutePath)) {
            return null;
        }

        $command = sprintf(
            'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s',
            escapeshellarg($absolutePath),
        );
        $output = [];
        exec($command, $output, $exitCode);
        if ($exitCode !== 0 || $output === []) {
            return null;
        }

        $duration = (float) trim($output[0]);

        return $duration > 0 ? $duration : null;
    }
}
