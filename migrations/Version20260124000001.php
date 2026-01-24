<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Add API token expiration fields to users table
 */
final class Version20260124000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add api_token_issued_at and api_token_expires_at columns to users table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD api_token_issued_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD api_token_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_users_api_token_expires_at ON users (api_token_expires_at)');
        $this->addSql('COMMENT ON COLUMN users.api_token_issued_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN users.api_token_expires_at IS \'(DC2Type:datetime_immutable)\'');

        // Set expiration for existing tokens (48 hours from now)
        $this->addSql('UPDATE users SET api_token_issued_at = NOW(), api_token_expires_at = NOW() + INTERVAL \'48 hours\' WHERE api_token IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_users_api_token_expires_at');
        $this->addSql('ALTER TABLE users DROP COLUMN IF EXISTS api_token_issued_at');
        $this->addSql('ALTER TABLE users DROP COLUMN IF EXISTS api_token_expires_at');
    }
}
