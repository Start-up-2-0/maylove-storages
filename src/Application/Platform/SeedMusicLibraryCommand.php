<?php

declare(strict_types=1);

namespace App\Application\Platform;

use App\Domain\File\StorageDriverInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-music-library',
    description: 'Gera os 15 MP3 da biblioteca MayLove em platform/music/ (placeholder ou arquivos reais).',
)]
final class SeedMusicLibraryCommand extends Command
{
    public function __construct(
        private readonly StorageDriverInterface $storageDriver,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Sobrescreve arquivos já existentes')
            ->addOption(
                'source-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Pasta com MP3s nomeados pelo slug (ex: acorde-do-coracao.mp3)',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');
        $sourceDir = $input->getOption('source-dir');
        $sourceDir = is_string($sourceDir) && $sourceDir !== '' ? $sourceDir : null;

        if (!$this->storageDriver->isWritable()) {
            $io->error('Diretório de storage não é gravável. Verifique STORAGE_ROOT.');

            return Command::FAILURE;
        }

        $created = 0;
        $skipped = 0;
        $placeholder = $this->silentMp3Bytes();

        foreach (MusicLibraryCatalog::TRACK_SLUGS as $slug) {
            $relativePath = MusicLibraryCatalog::relativePath($slug);

            if (!$force && $this->storageDriver->exists($relativePath)) {
                ++$skipped;
                continue;
            }

            $bytes = $this->resolveTrackBytes($slug, $sourceDir, $placeholder);
            $this->writeFile($relativePath, $bytes);
            ++$created;
        }

        $io->success(sprintf(
            'Biblioteca musical: %d arquivo(s) criado(s), %d ignorado(s). Servidos em /platform/music/{slug}.mp3',
            $created,
            $skipped,
        ));

        if ($sourceDir === null && $created > 0) {
            $io->note(
                'Placeholders silenciosos gerados. Para faixas reais, coloque MP3s em uma pasta e rode: '
                .'app:seed-music-library --source-dir=/caminho/para/mp3s --force',
            );
        }

        return Command::SUCCESS;
    }

    private function resolveTrackBytes(string $slug, ?string $sourceDir, string $placeholder): string
    {
        if ($sourceDir === null) {
            return $placeholder;
        }

        $candidate = rtrim(str_replace('\\', '/', $sourceDir), '/').'/'.$slug.'.mp3';
        if (!is_file($candidate)) {
            throw new \RuntimeException(sprintf('Arquivo não encontrado para slug "%s": %s', $slug, $candidate));
        }

        $bytes = file_get_contents($candidate);
        if ($bytes === false || $bytes === '') {
            throw new \RuntimeException(sprintf('Não foi possível ler: %s', $candidate));
        }

        return $bytes;
    }

    private function writeFile(string $relativePath, string $bytes): void
    {
        $absolutePath = $this->storageDriver->absolutePath($relativePath);
        $directory = \dirname($absolutePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        if (file_put_contents($absolutePath, $bytes) === false) {
            throw new \RuntimeException('Falha ao gravar: '.$relativePath);
        }
    }

    private function silentMp3Bytes(): string
    {
        $embedded = $this->projectDir.'/assets/seed/silent.mp3';
        if (is_file($embedded)) {
            $bytes = file_get_contents($embedded);
            if (is_string($bytes) && $bytes !== '') {
                return $bytes;
            }
        }

        $decoded = base64_decode(
            'SUQzBAAAAAAAI1RTU0UAAAAPAAADTGF2ZjU4Ljc2LjEwMAAAAAAAAAAAAAAA//tQAAAAAAAAAAAAAAAAAAAAWGluZwAAAA8AAAACAAACcQCAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICA//////////////////////////////////////////////////////////////////8AAABhTEFNRTMuMTI5BLkAAAAAAAAAABUgJAUHQQAB9gAAAnGAH0xBTUUzLjEwMFVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVV//uQxAAACAAABpAAAAAFBTUUzLjEwMFVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVQ==',
        );

        if (!is_string($decoded) || $decoded === '') {
            throw new \RuntimeException('Não foi possível gerar MP3 placeholder.');
        }

        return $decoded;
    }
}
