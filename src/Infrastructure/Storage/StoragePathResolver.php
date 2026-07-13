<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Infrastructure\Persistence\Entity\StorageFile;

final class StoragePathResolver
{
    public function buildPendingPath(StorageFile $file, string $mediaType): string
    {
        $extension = $this->resolveExtension($file->getOriginalFilename(), $mediaType);

        return sprintf(
            'tmp/%s/%s%s',
            $file->getContext(),
            (string) $file->getId(),
            $extension,
        );
    }

    public function buildFinalPath(StorageFile $file, string $mediaType, ?string $extensionOverride = null): string
    {
        $extension = $extensionOverride ?? $this->resolveExtension($file->getOriginalFilename(), $mediaType);

        return match ($file->getContext()) {
            'tribute' => sprintf(
                'tributes/%s/%s/%s%s',
                $file->getContextId(),
                $this->mediaFolder($mediaType),
                (string) $file->getId(),
                $extension,
            ),
            'album' => sprintf(
                'albums/%s/%s/%s%s',
                $file->getContextId(),
                $this->mediaFolder($mediaType),
                (string) $file->getId(),
                $extension,
            ),
            'platform' => sprintf(
                'platform/%s/%s%s',
                $file->getContextId(),
                (string) $file->getId(),
                $extension,
            ),
            'og' => sprintf('og/%s%s', $file->getContextId(), $extension),
            default => sprintf(
                '%s/%s/%s%s',
                $file->getContext(),
                $file->getContextId(),
                (string) $file->getId(),
                $extension,
            ),
        };
    }

    private function mediaFolder(string $mediaType): string
    {
        return match ($mediaType) {
            'photo' => 'photos',
            'video' => 'videos',
            'audio' => 'audio',
            default => 'files',
        };
    }

    private function resolveExtension(string $originalFilename, string $mediaType): string
    {
        $basename = basename(str_replace('\\', '/', $originalFilename));
        $extension = strtolower(pathinfo($basename, PATHINFO_EXTENSION));

        if ($extension !== '' && preg_match('/^[a-z0-9]{1,8}$/', $extension) === 1) {
            return '.'.$extension;
        }

        return match ($mediaType) {
            'photo' => '.jpg',
            'video' => '.mp4',
            'audio' => '.mp3',
            default => '.bin',
        };
    }
}
