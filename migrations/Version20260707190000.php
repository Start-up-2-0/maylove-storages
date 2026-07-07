<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260707190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cria tabela files para metadados do MayLove Storages.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE files (
                id CHAR(36) NOT NULL,
                context VARCHAR(50) NOT NULL,
                context_id VARCHAR(64) NOT NULL,
                relative_path VARCHAR(500) NOT NULL,
                original_filename VARCHAR(255) NOT NULL,
                mime_type VARCHAR(100) DEFAULT NULL,
                size_bytes BIGINT NOT NULL DEFAULT 0,
                sha256 CHAR(64) DEFAULT NULL,
                visibility VARCHAR(20) NOT NULL DEFAULT 'private',
                version INTEGER NOT NULL DEFAULT 1,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                created_at DATETIME(6) NOT NULL,
                updated_at DATETIME(6) NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql('CREATE INDEX idx_files_context ON files (context, context_id)');
        $this->addSql('CREATE INDEX idx_files_status ON files (status)');
        $this->addSql('CREATE INDEX idx_files_sha256 ON files (sha256)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE files');
    }
}
