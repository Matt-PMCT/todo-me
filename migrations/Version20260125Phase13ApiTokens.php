<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260125Phase13ApiTokens extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add api_tokens table for Phase 13 API token management';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE api_tokens (
            id UUID NOT NULL,
            owner_id UUID NOT NULL,
            name VARCHAR(100) NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            token_prefix VARCHAR(8) NOT NULL,
            scopes JSONB NOT NULL DEFAULT \'["*"]\',
            expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            last_used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE INDEX idx_tokens_owner ON api_tokens (owner_id)');
        $this->addSql('CREATE INDEX idx_tokens_hash ON api_tokens (token_hash)');

        $this->addSql('ALTER TABLE api_tokens ADD CONSTRAINT FK_API_TOKENS_OWNER
            FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('COMMENT ON COLUMN api_tokens.expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN api_tokens.last_used_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN api_tokens.created_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE api_tokens');
    }
}
