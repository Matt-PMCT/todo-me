<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260125Phase12Notifications extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add notifications table for Phase 12 notification system';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE notifications (
            id UUID NOT NULL,
            owner_id UUID NOT NULL,
            type VARCHAR(50) NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT DEFAULT NULL,
            data JSONB NOT NULL DEFAULT \'{}\',
            read_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE INDEX idx_notifications_owner_read ON notifications (owner_id, read_at)');
        $this->addSql('CREATE INDEX idx_notifications_owner_created ON notifications (owner_id, created_at DESC)');
        $this->addSql('CREATE INDEX idx_notifications_type ON notifications (type)');

        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT FK_6000B0D37E3C61F9
            FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('COMMENT ON COLUMN notifications.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN notifications.read_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE notifications');
    }
}
