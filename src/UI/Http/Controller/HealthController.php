<?php

declare(strict_types=1);

namespace App\UI\Http\Controller;

use App\Domain\File\StorageDriverInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/health', name: 'health_', methods: ['GET'])]
final class HealthController extends AbstractController
{
    public function __construct(
        private readonly StorageDriverInterface $storageDriver,
    ) {
    }

    #[Route('', name: 'check', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $storageRoot = $this->storageDriver->isWritable() ? 'ok' : 'error';
        $status = $storageRoot === 'ok' ? 'ok' : 'degraded';

        return $this->json([
            'status' => $status,
            'service' => 'maylove-storages',
            'checks' => [
                'storage_root' => $storageRoot,
            ],
        ], $status === 'ok' ? 200 : 503);
    }
}
