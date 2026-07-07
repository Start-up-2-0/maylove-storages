<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Entity;

use App\Domain\File\FileStatus;
use App\Domain\File\FileVisibility;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'files')]
#[ORM\Index(name: 'idx_files_context', columns: ['context', 'context_id'])]
#[ORM\Index(name: 'idx_files_status', columns: ['status'])]
#[ORM\Index(name: 'idx_files_sha256', columns: ['sha256'])]
class StorageFile
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 50)]
    private string $context;

    #[ORM\Column(name: 'context_id', length: 64)]
    private string $contextId;

    #[ORM\Column(name: 'relative_path', length: 500)]
    private string $relativePath = '';

    #[ORM\Column(name: 'original_filename', length: 255)]
    private string $originalFilename;

    #[ORM\Column(name: 'mime_type', length: 100, nullable: true)]
    private ?string $mimeType = null;

    #[ORM\Column(name: 'size_bytes', type: Types::BIGINT, options: ['default' => 0])]
    private int $sizeBytes = 0;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $sha256 = null;

    #[ORM\Column(length: 20, enumType: FileVisibility::class)]
    private FileVisibility $visibility = FileVisibility::Private;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    private int $version = 1;

    #[ORM\Column(length: 20, enumType: FileStatus::class)]
    private FileStatus $status = FileStatus::Pending;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $context, string $contextId, string $originalFilename)
    {
        $this->id = Uuid::v7();
        $this->context = $context;
        $this->contextId = $contextId;
        $this->originalFilename = $originalFilename;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getContext(): string
    {
        return $this->context;
    }

    public function getContextId(): string
    {
        return $this->contextId;
    }

    public function getRelativePath(): string
    {
        return $this->relativePath;
    }

    public function setRelativePath(string $relativePath): void
    {
        $this->relativePath = $relativePath;
        $this->touch();
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): void
    {
        $this->mimeType = $mimeType;
        $this->touch();
    }

    public function getSizeBytes(): int
    {
        return $this->sizeBytes;
    }

    public function setSizeBytes(int $sizeBytes): void
    {
        $this->sizeBytes = $sizeBytes;
        $this->touch();
    }

    public function getSha256(): ?string
    {
        return $this->sha256;
    }

    public function setSha256(string $sha256): void
    {
        $this->sha256 = $sha256;
        $this->touch();
    }

    public function getVisibility(): FileVisibility
    {
        return $this->visibility;
    }

    public function getStatus(): FileStatus
    {
        return $this->status;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function markActive(): void
    {
        $this->status = FileStatus::Active;
        $this->touch();
    }

    public function markPublic(): void
    {
        $this->visibility = FileVisibility::Public;
        $this->touch();
    }

    public function markDeleted(): void
    {
        $this->status = FileStatus::Deleted;
        $this->touch();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->touch();
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
