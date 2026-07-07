<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\File\FileStatus;
use App\Infrastructure\Persistence\Entity\StorageFile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<StorageFile>
 */
final class StorageFileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StorageFile::class);
    }

    public function findActiveById(Uuid $id): ?StorageFile
    {
        return $this->findOneBy([
            'id' => $id,
            'status' => FileStatus::Active,
        ]);
    }

    public function findById(Uuid $id): ?StorageFile
    {
        $file = $this->find($id);

        if ($file !== null && $file->getStatus() === FileStatus::Deleted) {
            return null;
        }

        return $file;
    }

    /**
     * @return list<StorageFile>
     */
    public function findByContext(string $context, string $contextId): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.context = :context')
            ->andWhere('f.contextId = :contextId')
            ->andWhere('f.status != :deleted')
            ->setParameter('context', $context)
            ->setParameter('contextId', $contextId)
            ->setParameter('deleted', FileStatus::Deleted)
            ->orderBy('f.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(StorageFile $file): void
    {
        $this->getEntityManager()->persist($file);
        $this->getEntityManager()->flush();
    }
}
