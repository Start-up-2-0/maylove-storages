<?php

declare(strict_types=1);

namespace App\UI\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class ApiResponse
{
    public static function success(mixed $data = null, int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse(['data' => $data], $status);
    }

    public static function error(string $message, string $code, int $status): JsonResponse
    {
        return new JsonResponse([
            'message' => $message,
            'code' => $code,
        ], $status);
    }
}
