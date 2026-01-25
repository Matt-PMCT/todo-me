<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260125Phase13Sessions extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user_sessions table for Phase 13 session management';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_sessions (
            id UUID NOT NULL,
            owner_id UUID NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            user_agent VARCHAR(500) DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            device VARCHAR(100) DEFAULT NULL,
            browser VARCHAR(100) DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            last_active_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE INDEX idx_sessions_owner ON user_sessions (owner_id)');
        $this->addSql('CREATE INDEX idx_sessions_token_hash ON user_sessions (token_hash)');

        $this->addSql('ALTER TABLE user_sessions ADD CONSTRAINT FK_USER_SESSIONS_OWNER
            FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('COMMENT ON COLUMN user_sessions.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN user_sessions.last_active_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_sessions');
    }
}
