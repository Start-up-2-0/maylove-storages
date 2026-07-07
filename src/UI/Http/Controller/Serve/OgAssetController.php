<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Serve;

use App\Domain\File\StorageDriverInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

final class OgAssetController extends AbstractController
{
    public function __construct(
        private readonly StorageDriverInterface $storageDriver,
    ) {
    }

    #[Route('/og/{tributeId}.jpg', name: 'og_asset', methods: ['GET'])]
    public function serve(string $tributeId): Response
    {
        if ($tributeId === '' || !preg_match('/^[0-9a-f-]{36}$/i', $tributeId)) {
            return new Response('Arquivo não encontrado.', Response::HTTP_NOT_FOUND);
        }

        $relativePath = 'og/'.$tributeId.'.jpg';
        if (!$this->storageDriver->exists($relativePath)) {
            return new Response('Arquivo não encontrado.', Response::HTTP_NOT_FOUND);
        }

        return new StreamedResponse(function () use ($relativePath): void {
            $stream = $this->storageDriver->readStream($relativePath);
            fpassthru($stream);
            fclose($stream);
        }, Response::HTTP_OK, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
