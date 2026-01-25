<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260125Phase12PushSubscriptions extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add push_subscriptions table for Phase 12 web push notifications';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE push_subscriptions (
            id UUID NOT NULL,
            owner_id UUID NOT NULL,
            endpoint TEXT NOT NULL,
            endpoint_hash VARCHAR(64) NOT NULL,
            public_key VARCHAR(255) NOT NULL,
            auth_token VARCHAR(255) NOT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            last_used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE INDEX idx_push_subscriptions_owner ON push_subscriptions (owner_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_push_subscription_endpoint ON push_subscriptions (endpoint_hash)');

        $this->addSql('ALTER TABLE push_subscriptions ADD CONSTRAINT FK_PUSH_SUB_OWNER
            FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('COMMENT ON COLUMN push_subscriptions.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN push_subscriptions.last_used_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE push_subscriptions');
    }
}
