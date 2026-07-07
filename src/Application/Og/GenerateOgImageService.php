<?php

declare(strict_types=1);

namespace App\Application\Og;

use App\Domain\File\Exception\StorageException;
use App\Domain\File\FileStatus;
use App\Domain\File\StorageDriverInterface;
use App\Infrastructure\Persistence\Entity\StorageFile;
use App\Infrastructure\Persistence\Repository\StorageFileRepository;
use Symfony\Component\Uid\Uuid;

final class GenerateOgImageService
{
    private const WIDTH = 1200;
    private const HEIGHT = 630;

    public function __construct(
        private readonly StorageFileRepository $fileRepository,
        private readonly StorageDriverInterface $storageDriver,
        private readonly string $publicUrl,
    ) {
    }

    /**
     * @return array{public_url: string, path: string, file_id: string}
     */
    public function generate(string $tributeId, ?string $sourceFileId, string $title, string $colorPrimary): array
    {
        if ($tributeId === '') {
            throw new StorageException('tribute_id é obrigatório.', 'VALIDATION_ERROR', 400);
        }

        $relativePath = sprintf('og/%s.jpg', $tributeId);
        $absolutePath = $this->storageDriver->absolutePath($relativePath);
        $directory = \dirname($absolutePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $image = $this->createCanvas($sourceFileId, $title, $colorPrimary);
        if (!imagejpeg($image, $absolutePath, 85)) {
            imagedestroy($image);
            throw new StorageException('Não foi possível gravar a OG image.', 'OG_WRITE_FAILED', 500);
        }
        imagedestroy($image);

        $sha256 = hash_file('sha256', $absolutePath) ?: '';
        $sizeBytes = filesize($absolutePath) ?: 0;
        $file = $this->resolveOgFile($tributeId);
        $file->setRelativePath($relativePath);
        $file->setMimeType('image/jpeg');
        $file->setSha256($sha256);
        $file->setSizeBytes((int) $sizeBytes);
        $file->markActive();
        $file->markPublic();
        $this->fileRepository->save($file);

        return [
            'public_url' => rtrim($this->publicUrl, '/').'/og/'.$tributeId.'.jpg',
            'path' => $relativePath,
            'file_id' => (string) $file->getId(),
        ];
    }

    /**
     * @return \GdImage
     */
    private function createCanvas(?string $sourceFileId, string $title, string $colorPrimary): \GdImage
    {
        $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        if ($canvas === false) {
            throw new StorageException('GD indisponível.', 'OG_GENERATION_FAILED', 500);
        }

        $background = $this->loadSourceImage($sourceFileId);
        if ($background !== null) {
            $this->drawCover($canvas, $background);
            imagedestroy($background);
        } else {
            $this->fillSolid($canvas, $colorPrimary);
        }

        $this->drawTitleOverlay($canvas, $title, $colorPrimary);

        return $canvas;
    }

    private function loadSourceImage(?string $sourceFileId): ?\GdImage
    {
        if ($sourceFileId === null || $sourceFileId === '' || !Uuid::isValid($sourceFileId)) {
            return null;
        }

        $source = $this->fileRepository->findActiveById(Uuid::fromString($sourceFileId));
        if ($source === null || $source->getRelativePath() === '') {
            return null;
        }

        if (!$this->storageDriver->exists($source->getRelativePath())) {
            return null;
        }

        $absolutePath = $this->storageDriver->absolutePath($source->getRelativePath());
        $mime = $source->getMimeType() ?? mime_content_type($absolutePath) ?: '';

        $image = match ($mime) {
            'image/png' => @imagecreatefrompng($absolutePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absolutePath) : false,
            default => @imagecreatefromjpeg($absolutePath),
        };

        if ($image === false) {
            return null;
        }

        return $image;
    }

    private function drawCover(\GdImage $canvas, \GdImage $source): void
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            return;
        }

        $scale = max(self::WIDTH / $sourceWidth, self::HEIGHT / $sourceHeight);
        $targetWidth = (int) round($sourceWidth * $scale);
        $targetHeight = (int) round($sourceHeight * $scale);
        $offsetX = (int) round((self::WIDTH - $targetWidth) / 2);
        $offsetY = (int) round((self::HEIGHT - $targetHeight) / 2);

        imagecopyresampled(
            $canvas,
            $source,
            $offsetX,
            $offsetY,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight,
        );
    }

    private function fillSolid(\GdImage $canvas, string $colorPrimary): void
    {
        [$r, $g, $b] = $this->parseHexColor($colorPrimary);
        $color = imagecolorallocate($canvas, $r, $g, $b);
        imagefilledrectangle($canvas, 0, 0, self::WIDTH, self::HEIGHT, $color);
    }

    private function drawTitleOverlay(\GdImage $canvas, string $title, string $colorPrimary): void
    {
        $overlay = imagecolorallocatealpha($canvas, 0, 0, 0, 60);
        imagefilledrectangle($canvas, 0, self::HEIGHT - 160, self::WIDTH, self::HEIGHT, $overlay);

        [$r, $g, $b] = $this->parseHexColor($colorPrimary);
        $accent = imagecolorallocate($canvas, $r, $g, $b);
        imagefilledrectangle($canvas, 0, self::HEIGHT - 160, 12, self::HEIGHT, $accent);

        $textColor = imagecolorallocate($canvas, 255, 255, 255);
        $safeTitle = trim(mb_substr($title, 0, 80));
        if ($safeTitle === '') {
            $safeTitle = 'MayLove';
        }

        imagestring($canvas, 5, 32, self::HEIGHT - 110, $safeTitle, $textColor);
        imagestring($canvas, 3, 32, self::HEIGHT - 70, 'maylove.com.br', $textColor);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function parseHexColor(string $color): array
    {
        if (preg_match('/^#?([0-9A-Fa-f]{6})$/', $color, $matches) !== 1) {
            return [217, 79, 122];
        }

        $hex = $matches[1];

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private function resolveOgFile(string $tributeId): StorageFile
    {
        $existing = $this->fileRepository->findByContext('og', $tributeId);
        foreach ($existing as $file) {
            if ($file->getStatus() !== FileStatus::Deleted) {
                return $file;
            }
        }

        return new StorageFile('og', $tributeId, $tributeId.'.jpg');
    }
}
