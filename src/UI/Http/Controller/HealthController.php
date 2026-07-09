<?php

declare(strict_types=1);

namespace App\UI\Http\Controller;

use App\Domain\File\StorageDriverInterface;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/health', name: 'health_', methods: ['GET'])]
final class HealthController extends AbstractController
{
    public function __construct(
        private readonly StorageDriverInterface $storageDriver,
        private readonly Connection $connection,
    ) {
    }

    #[Route('', name: 'check', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $storageRoot = $this->storageDriver->isWritable() ? 'ok' : 'error';
        $database = $this->checkDatabase();
        $storagePersistent = $this->isStoragePersistent() ? 'ok' : 'error';
        $checks = [
            'storage_root' => $storageRoot,
            'storage_persistent' => $storagePersistent,
            'database' => $database,
        ];

        $hasFailure = in_array('error', $checks, true);
        $status = $hasFailure ? 'degraded' : 'ok';

        return $this->json([
            'status' => $status,
            'service' => 'maylove-storages',
            'checks' => $checks,
        ], $hasFailure ? 503 : 200);
    }

    private function checkDatabase(): string
    {
        try {
            $this->connection->executeQuery('SELECT 1');
            $this->connection->executeQuery('SELECT 1 FROM files LIMIT 1');

            return 'ok';
        } catch (\Throwable) {
            return 'error';
        }
    }

    private function isStoragePersistent(): bool
    {
        $mountPath = getenv('RAILWAY_VOLUME_MOUNT_PATH');
        if (!is_string($mountPath) || $mountPath === '') {
            return getenv('RAILWAY_ENVIRONMENT') === false;
        }

        $storageRoot = getenv('STORAGE_ROOT');
        if (is_string($storageRoot) && $storageRoot !== '' && $storageRoot !== $mountPath) {
            return false;
        }

        return is_dir($mountPath) && is_writable($mountPath);
    }
}
