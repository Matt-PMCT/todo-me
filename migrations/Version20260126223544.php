<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rename api_token column to api_token_hash for security clarity.
 *
 * The column already stores SHA256 hashes (never plaintext tokens).
 * This rename makes the storage format explicit in the column name.
 */
final class Version20260126223544 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename api_token to api_token_hash in users table';
    }

    public function up(Schema $schema): void
    {
        // Rename the column
        $this->addSql('ALTER TABLE users RENAME COLUMN api_token TO api_token_hash');

        // Update indexes
        $this->addSql('DROP INDEX IF EXISTS idx_users_api_token');
        $this->addSql('DROP INDEX IF EXISTS uniq_users_api_token');
        $this->addSql('CREATE INDEX idx_users_api_token_hash ON users (api_token_hash)');
        $this->addSql('CREATE UNIQUE INDEX uniq_users_api_token_hash ON users (api_token_hash)');
    }

    public function down(Schema $schema): void
    {
        // Restore the original column name
        $this->addSql('DROP INDEX IF EXISTS idx_users_api_token_hash');
        $this->addSql('DROP INDEX IF EXISTS uniq_users_api_token_hash');
        $this->addSql('ALTER TABLE users RENAME COLUMN api_token_hash TO api_token');
        $this->addSql('CREATE INDEX idx_users_api_token ON users (api_token)');
        $this->addSql('CREATE UNIQUE INDEX uniq_users_api_token ON users (api_token)');
    }
}
