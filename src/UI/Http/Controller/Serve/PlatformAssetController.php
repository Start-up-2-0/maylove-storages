<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Serve;

use App\Domain\File\StorageDriverInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

final class PlatformAssetController extends AbstractController
{
    public function __construct(
        private readonly StorageDriverInterface $storageDriver,
    ) {
    }

    #[Route('/platform/{filePath}', name: 'platform_asset', requirements: ['filePath' => '.+'], methods: ['GET'])]
    public function serve(string $filePath): Response
    {
        $filePath = str_replace('\\', '/', $filePath);
        if ($filePath === '' || str_contains($filePath, '..')) {
            return new Response('Arquivo não encontrado.', Response::HTTP_NOT_FOUND);
        }

        $relativePath = 'platform/'.$filePath;
        if (!$this->storageDriver->exists($relativePath)) {
            return new Response('Arquivo não encontrado.', Response::HTTP_NOT_FOUND);
        }

        $absolutePath = $this->storageDriver->absolutePath($relativePath);

        $response = new BinaryFileResponse($absolutePath);
        $response->headers->set('Content-Type', $this->guessMimeType($filePath));
        $response->headers->set('Cache-Control', 'public, max-age=86400');
        $response->headers->set('Accept-Ranges', 'bytes');
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            basename($absolutePath),
        );

        return $response;
    }

    private function guessMimeType(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'application/octet-stream',
        };
    }
}
