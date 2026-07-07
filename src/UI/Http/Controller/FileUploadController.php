<?php

declare(strict_types=1);

namespace App\UI\Http\Controller;

use App\Application\Upload\HandleFileUploadService;
use App\Domain\File\Exception\StorageException;
use App\UI\Http\ApiResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/files', name: 'files_')]
final class FileUploadController extends AbstractController
{
    public function __construct(
        private readonly HandleFileUploadService $handleFileUploadService,
    ) {
    }

    #[Route('/upload', name: 'upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $ticket = $request->headers->get('X-Upload-Ticket') ?? $request->headers->get('x-upload-ticket');
        if ($ticket === null || $ticket === '') {
            return ApiResponse::error('Upload ticket ausente.', 'INVALID_UPLOAD_TICKET', Response::HTTP_UNAUTHORIZED);
        }

        /** @var UploadedFile|null $uploadedFile */
        $uploadedFile = $request->files->get('file');
        if (!$uploadedFile instanceof UploadedFile) {
            return ApiResponse::error('Arquivo não enviado.', 'FILE_REQUIRED', Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->handleFileUploadService->upload($ticket, $uploadedFile);
        } catch (StorageException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->getErrorCode(), $exception->getStatusCode());
        }

        return ApiResponse::success($result, Response::HTTP_CREATED);
    }
}
