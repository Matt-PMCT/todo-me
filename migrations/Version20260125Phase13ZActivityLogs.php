<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260125Phase13ZActivityLogs extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add activity_logs table for Phase 13 activity logging';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE activity_logs (
            id UUID NOT NULL,
            owner_id UUID NOT NULL,
            action VARCHAR(50) NOT NULL,
            entity_type VARCHAR(50) NOT NULL,
            entity_id UUID DEFAULT NULL,
            entity_title VARCHAR(255) NOT NULL,
            changes JSONB NOT NULL DEFAULT \'{}\',
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE INDEX idx_activity_owner_created ON activity_logs (owner_id, created_at DESC)');
        $this->addSql('CREATE INDEX idx_activity_entity ON activity_logs (entity_type, entity_id)');

        $this->addSql('ALTER TABLE activity_logs ADD CONSTRAINT FK_activity_owner
            FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('COMMENT ON COLUMN activity_logs.created_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE activity_logs');
    }
}
