<?php

declare(strict_types=1);

namespace App\Application\Media;

use App\Domain\File\Exception\StorageException;

final class ImageOptimizationService
{
    public function __construct(
        private readonly int $maxLongEdge,
        private readonly int $webpQuality,
        private readonly int $jpegQuality,
        private readonly bool $preferWebp,
    ) {
    }

    /**
     * Otimiza imagem no disco: redimensiona, remove metadados (re-encode) e converte para WebP quando possível.
     *
     * @return array{
     *     absolute_path: string,
     *     mime_type: string,
     *     extension: string,
     *     width: int,
     *     height: int,
     *     size_bytes: int
     * }
     */
    public function optimize(string $absolutePath, string $inputMime): array
    {
        if (!is_file($absolutePath)) {
            throw new StorageException('Arquivo de imagem não encontrado.', 'UPLOAD_NOT_FOUND', 404);
        }

        $image = $this->loadImage($absolutePath, $inputMime);
        $width = imagesx($image);
        $height = imagesy($image);

        if ($width === false || $height === false || $width < 1 || $height < 1) {
            imagedestroy($image);
            throw new StorageException('Não foi possível ler as dimensões da imagem.', 'IMAGE_INVALID', 422);
        }

        $longEdge = max($width, $height);
        if ($longEdge > $this->maxLongEdge) {
            $scale = $this->maxLongEdge / $longEdge;
            $targetWidth = max(1, (int) round($width * $scale));
            $targetHeight = max(1, (int) round($height * $scale));
            $resized = imagecreatetruecolor($targetWidth, $targetHeight);
            if ($resized === false) {
                imagedestroy($image);
                throw new StorageException('Falha ao redimensionar imagem.', 'IMAGE_PROCESS_FAILED', 500);
            }

            $this->preserveAlpha($resized, $inputMime);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
            $width = $targetWidth;
            $height = $targetHeight;
        }

        $useWebp = $this->preferWebp && function_exists('imagewebp');
        $targetMime = $useWebp ? 'image/webp' : 'image/jpeg';
        $extension = $useWebp ? '.webp' : '.jpg';
        $targetPath = preg_replace('/\.[^.]+$/', '', $absolutePath).$extension;
        if ($targetPath === null || $targetPath === $absolutePath) {
            $targetPath = $absolutePath.$extension;
        }

        if (!$useWebp) {
            $flattened = imagecreatetruecolor($width, $height);
            if ($flattened === false) {
                imagedestroy($image);
                throw new StorageException('Falha ao preparar imagem JPEG.', 'IMAGE_PROCESS_FAILED', 500);
            }
            $white = imagecolorallocate($flattened, 255, 255, 255);
            if ($white !== false) {
                imagefill($flattened, 0, 0, $white);
            }
            imagecopy($flattened, $image, 0, 0, 0, 0, $width, $height);
            imagedestroy($image);
            $image = $flattened;
        }

        $saved = $useWebp
            ? imagewebp($image, $targetPath, $this->webpQuality)
            : imagejpeg($image, $targetPath, $this->jpegQuality);
        imagedestroy($image);

        if (!$saved || !is_file($targetPath)) {
            throw new StorageException('Falha ao salvar imagem otimizada.', 'IMAGE_PROCESS_FAILED', 500);
        }

        if ($targetPath !== $absolutePath && is_file($absolutePath)) {
            unlink($absolutePath);
        }

        return [
            'absolute_path' => $targetPath,
            'mime_type' => $targetMime,
            'extension' => $extension,
            'width' => $width,
            'height' => $height,
            'size_bytes' => (int) filesize($targetPath),
        ];
    }

    /**
     * @return \GdImage
     */
    private function loadImage(string $absolutePath, string $mimeType): \GdImage
    {
        $image = match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($absolutePath),
            'image/png' => @imagecreatefrompng($absolutePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absolutePath) : false,
            default => false,
        };

        if ($image === false) {
            throw new StorageException('Formato de imagem não suportado.', 'INVALID_MIME', 422);
        }

        return $image;
    }

    private function preserveAlpha(\GdImage $canvas, string $mimeType): void
    {
        if ($mimeType !== 'image/png' && $mimeType !== 'image/webp') {
            return;
        }

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        if ($transparent !== false) {
            imagefill($canvas, 0, 0, $transparent);
        }
    }
}
