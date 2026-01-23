<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Create tags table with unique constraint
 */
final class Version20240101000004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tags table with unique constraint on (owner_id, name)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE tags (
            id UUID NOT NULL,
            owner_id UUID NOT NULL,
            name VARCHAR(100) NOT NULL,
            color VARCHAR(7) NOT NULL DEFAULT \'#6B7280\',
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE INDEX idx_tags_owner ON tags (owner_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_tags_owner_name ON tags (owner_id, name)');

        $this->addSql('ALTER TABLE tags ADD CONSTRAINT fk_tags_owner
            FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('COMMENT ON COLUMN tags.created_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tags DROP CONSTRAINT fk_tags_owner');
        $this->addSql('DROP TABLE tags');
    }
}
