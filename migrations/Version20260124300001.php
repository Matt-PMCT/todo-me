<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Create saved_filters table for Phase 4 Views & Filtering feature
 */
final class Version20260124300001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create saved_filters table for Phase 4 Views & Filtering feature';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE saved_filters (
            id UUID NOT NULL,
            owner_id UUID NOT NULL,
            name VARCHAR(100) NOT NULL,
            criteria JSON NOT NULL,
            is_default BOOLEAN DEFAULT false NOT NULL,
            position INTEGER DEFAULT 0 NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE INDEX idx_saved_filters_owner ON saved_filters (owner_id)');
        $this->addSql('CREATE INDEX idx_saved_filters_default ON saved_filters (owner_id, is_default)');

        $this->addSql('ALTER TABLE saved_filters ADD CONSTRAINT FK_saved_filters_owner
            FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('COMMENT ON COLUMN saved_filters.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN saved_filters.updated_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE saved_filters DROP CONSTRAINT FK_saved_filters_owner');
        $this->addSql('DROP TABLE saved_filters');
    }
}
