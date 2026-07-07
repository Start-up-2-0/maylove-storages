<?php

declare(strict_types=1);

namespace App\Domain\File;

interface StorageDriverInterface
{
    /**
     * @param resource $stream
     * @param array<string, mixed> $meta
     */
    public function putStream(string $path, $stream, array $meta = []): string;

    public function delete(string $path): void;

    public function exists(string $path): bool;

    /**
     * @return resource
     */
    public function readStream(string $path);

    public function absolutePath(string $path): string;

    public function isWritable(): bool;
}
