<?php

declare(strict_types=1);

namespace App\Infrastructure\Driver;

use App\Domain\File\StorageDriverInterface;
use Symfony\Component\Filesystem\Filesystem;

final class LocalFilesystemDriver implements StorageDriverInterface
{
    private readonly Filesystem $filesystem;

    public function __construct(
        private readonly string $root,
    ) {
        $this->filesystem = new Filesystem();
    }

    public function putStream(string $path, $stream, array $meta = []): string
    {
        $absolutePath = $this->resolvePath($path);
        $directory = \dirname($absolutePath);

        if (!$this->filesystem->exists($directory)) {
            $this->filesystem->mkdir($directory);
        }

        $target = fopen($absolutePath, 'wb');
        if ($target === false) {
            throw new \RuntimeException('Não foi possível gravar o arquivo.');
        }

        stream_copy_to_stream($stream, $target);
        fclose($target);

        return $path;
    }

    public function delete(string $path): void
    {
        $absolutePath = $this->resolvePath($path);

        if ($this->filesystem->exists($absolutePath)) {
            $this->filesystem->remove($absolutePath);
        }
    }

    public function exists(string $path): bool
    {
        return $this->filesystem->exists($this->resolvePath($path));
    }

    public function readStream(string $path)
    {
        $absolutePath = $this->resolvePath($path);
        $stream = fopen($absolutePath, 'rb');
        if ($stream === false) {
            throw new \RuntimeException('Arquivo não encontrado.');
        }

        return $stream;
    }

    public function absolutePath(string $path): string
    {
        return $this->resolvePath($path);
    }

    public function isWritable(): bool
    {
        if (!$this->filesystem->exists($this->root)) {
            try {
                $this->filesystem->mkdir($this->root);
            } catch (\Throwable) {
                return false;
            }
        }

        return is_writable($this->root);
    }

    private function resolvePath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        $normalized = ltrim($normalized, '/');

        if (str_contains($normalized, '..')) {
            throw new \InvalidArgumentException('Path inválido.');
        }

        return rtrim($this->root, '/\\').'/'.$normalized;
    }
}
