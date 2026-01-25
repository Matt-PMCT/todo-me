<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260125Phase11TwoFactorAuth extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add two-factor authentication fields to users table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD COLUMN two_factor_enabled BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE users ADD COLUMN totp_secret VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD COLUMN backup_codes JSONB DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD COLUMN two_factor_enabled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD COLUMN backup_codes_generated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP COLUMN two_factor_enabled');
        $this->addSql('ALTER TABLE users DROP COLUMN totp_secret');
        $this->addSql('ALTER TABLE users DROP COLUMN backup_codes');
        $this->addSql('ALTER TABLE users DROP COLUMN two_factor_enabled_at');
        $this->addSql('ALTER TABLE users DROP COLUMN backup_codes_generated_at');
    }
}
