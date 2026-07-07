<?php

declare(strict_types=1);

namespace App\UI\Http\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Liveness probe para Railway — sem dependências de storage/DB.
 */
#[Route('/health/live', name: 'health_live', methods: ['GET'])]
final class HealthLiveController extends AbstractController
{
    public function __invoke(): JsonResponse
    {
        return $this->json([
            'status' => 'ok',
            'service' => 'maylove-storages',
        ]);
    }
}
