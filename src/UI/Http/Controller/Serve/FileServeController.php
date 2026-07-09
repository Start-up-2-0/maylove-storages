<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Serve;

use App\Application\Url\FileUrlService;
use App\Domain\File\Exception\StorageException;
use App\Domain\File\StorageDriverInterface;
use App\UI\Http\ApiResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
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
        $absolutePath = $this->storageDriver->absolutePath($resolved['path']);

        if (!is_file($absolutePath)) {
            return ApiResponse::error('Arquivo não encontrado.', 'FILE_NOT_FOUND', Response::HTTP_NOT_FOUND);
        }

        $response = new BinaryFileResponse($absolutePath);
        $response->headers->set('Content-Type', $mimeType);
        $response->headers->set('Cache-Control', 'private, max-age=60');
        $response->headers->set('Accept-Ranges', 'bytes');
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            basename($absolutePath),
        );

        return $response;
    }
}
