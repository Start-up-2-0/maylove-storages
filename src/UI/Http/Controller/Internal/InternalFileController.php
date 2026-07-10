<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Internal;

use App\Application\Confirm\ConfirmFileService;
use App\Application\Delete\DeleteFileService;
use App\Application\List\ListFilesService;
use App\Application\Media\ExtractAudioFromVideoService;
use App\Application\Media\ImportYoutubeAudioService;
use App\Application\Og\GenerateOgImageService;
use App\Domain\File\Exception\StorageException;
use App\Application\Upload\CreateUploadTicketService;
use App\Application\Url\FileUrlService;
use App\Infrastructure\Security\ServiceTokenValidator;
use App\UI\Http\ApiResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/internal', name: 'internal_')]
final class InternalFileController extends AbstractController
{
    public function __construct(
        private readonly ServiceTokenValidator $serviceTokenValidator,
        private readonly CreateUploadTicketService $createUploadTicketService,
        private readonly ConfirmFileService $confirmFileService,
        private readonly DeleteFileService $deleteFileService,
        private readonly ListFilesService $listFilesService,
        private readonly FileUrlService $fileUrlService,
        private readonly GenerateOgImageService $generateOgImageService,
        private readonly ExtractAudioFromVideoService $extractAudioFromVideoService,
        private readonly ImportYoutubeAudioService $importYoutubeAudioService,
    ) {
    }

    #[Route('/upload-ticket', name: 'upload_ticket', methods: ['POST'])]
    public function createUploadTicket(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return ApiResponse::error('Não autorizado.', 'UNAUTHORIZED', Response::HTTP_UNAUTHORIZED);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];

        $result = $this->createUploadTicketService->create(
            context: (string) ($payload['context'] ?? ''),
            contextId: (string) ($payload['context_id'] ?? ''),
            mediaType: (string) ($payload['media_type'] ?? ''),
            originalFilename: (string) ($payload['original_filename'] ?? ''),
            mimeType: (string) ($payload['mime_type'] ?? ''),
            maxSizeBytes: (int) ($payload['max_size_bytes'] ?? 0),
            ttlSeconds: (int) ($payload['ttl_seconds'] ?? 900),
            userId: isset($payload['user_id']) ? (string) $payload['user_id'] : null,
        );

        return ApiResponse::success($result, Response::HTTP_CREATED);
    }

    #[Route('/files/{fileId}/confirm', name: 'files_confirm', methods: ['POST'])]
    public function confirm(string $fileId, Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return ApiResponse::error('Não autorizado.', 'UNAUTHORIZED', Response::HTTP_UNAUTHORIZED);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];

        $result = $this->confirmFileService->confirm(
            $fileId,
            (string) ($payload['media_type'] ?? 'photo'),
            (string) ($payload['mime_type'] ?? 'application/octet-stream'),
        );

        return ApiResponse::success($result);
    }

    #[Route('/files/{fileId}/url', name: 'files_url', methods: ['GET'])]
    public function url(string $fileId, Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return ApiResponse::error('Não autorizado.', 'UNAUTHORIZED', Response::HTTP_UNAUTHORIZED);
        }

        $result = $this->fileUrlService->createUrl(
            $fileId,
            (string) $request->query->get('visibility', 'protected'),
            (int) $request->query->get('ttl', 3600),
        );

        return ApiResponse::success($result);
    }

    #[Route('/files/{fileId}', name: 'files_delete', methods: ['DELETE'])]
    public function delete(string $fileId, Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return ApiResponse::error('Não autorizado.', 'UNAUTHORIZED', Response::HTTP_UNAUTHORIZED);
        }

        $this->deleteFileService->delete($fileId);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/files', name: 'files_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return ApiResponse::error('Não autorizado.', 'UNAUTHORIZED', Response::HTTP_UNAUTHORIZED);
        }

        $context = (string) $request->query->get('context', '');
        $contextId = (string) $request->query->get('context_id', '');

        return ApiResponse::success($this->listFilesService->listByContext($context, $contextId));
    }

    #[Route('/og-image', name: 'og_image', methods: ['POST'])]
    public function generateOgImage(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return ApiResponse::error('Não autorizado.', 'UNAUTHORIZED', Response::HTTP_UNAUTHORIZED);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];

        $result = $this->generateOgImageService->generate(
            tributeId: (string) ($payload['tribute_id'] ?? ''),
            sourceFileId: isset($payload['source_file_id']) ? (string) $payload['source_file_id'] : null,
            title: (string) ($payload['title'] ?? 'MayLov'),
            colorPrimary: (string) ($payload['color_primary'] ?? '#e11d7a'),
        );

        return ApiResponse::success($result, Response::HTTP_CREATED);
    }

    #[Route('/files/{fileId}/extract-audio', name: 'files_extract_audio', methods: ['POST'])]
    public function extractAudio(string $fileId, Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return ApiResponse::error('Não autorizado.', 'UNAUTHORIZED', Response::HTTP_UNAUTHORIZED);
        }

        try {
            $result = $this->extractAudioFromVideoService->extract($fileId);
        } catch (StorageException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->getErrorCode(), $exception->getStatusCode());
        }

        return ApiResponse::success($result);
    }

    #[Route('/youtube-audio', name: 'youtube_audio', methods: ['POST'])]
    public function importYoutubeAudio(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return ApiResponse::error('Não autorizado.', 'UNAUTHORIZED', Response::HTTP_UNAUTHORIZED);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];

        try {
            $result = $this->importYoutubeAudioService->import(
                context: (string) ($payload['context'] ?? ''),
                contextId: (string) ($payload['context_id'] ?? ''),
                url: (string) ($payload['url'] ?? ''),
                userId: isset($payload['user_id']) ? (string) $payload['user_id'] : null,
            );
        } catch (StorageException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->getErrorCode(), $exception->getStatusCode());
        }

        return ApiResponse::success($result, Response::HTTP_CREATED);
    }

    private function isAuthorized(Request $request): bool
    {
        $token = $request->headers->get('x-maylove-service-token');
        if ($token === null || $token === '') {
            return false;
        }

        return $this->serviceTokenValidator->validate($token);
    }
}
