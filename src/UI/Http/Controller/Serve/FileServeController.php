<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Serve;

use App\Application\Url\FileUrlService;
use App\Domain\File\Exception\StorageException;
use App\Domain\File\StorageDriverInterface;
use App\UI\Http\ApiResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

final class FileServeController extends AbstractController
{
    public function __construct(
        private readonly FileUrlService $fileUrlService,
        private readonly StorageDriverInterface $storageDriver,
    ) {
    }

    #[Route('/serve/{fileId}', name: 'files_serve', methods: ['GET'])]
    public function serve(string $fileId, Request $request): Response
    {
        $token = (string) $request->query->get('token', '');
        if ($token === '') {
            return ApiResponse::error('Token ausente.', 'INVALID_URL_TOKEN', Response::HTTP_UNAUTHORIZED);
        }

        try {
            $resolved = $this->fileUrlService->resolveServeToken($fileId, $token);
        } catch (StorageException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->getErrorCode(), $exception->getStatusCode());
        }

        $mimeType = $resolved['file']->getMimeType() ?? 'application/octet-stream';

        return new StreamedResponse(function () use ($resolved): void {
            $stream = $this->storageDriver->readStream($resolved['path']);
            fpassthru($stream);
            fclose($stream);
        }, Response::HTTP_OK, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, max-age=60',
        ]);
    }
}
