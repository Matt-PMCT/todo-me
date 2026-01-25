<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260125Phase10 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email verification, password reset, and account lockout fields to users table';
    }

    public function up(Schema $schema): void
    {
        // Email verification fields
        $this->addSql('ALTER TABLE users ADD COLUMN email_verified BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE users ADD COLUMN email_verification_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD COLUMN email_verification_sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');

        // Password reset fields
        $this->addSql('ALTER TABLE users ADD COLUMN password_reset_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD COLUMN password_reset_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');

        // Account lockout fields
        $this->addSql('ALTER TABLE users ADD COLUMN failed_login_attempts INTEGER NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE users ADD COLUMN locked_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD COLUMN last_failed_login_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');

        // Indexes for token lookups
        $this->addSql('CREATE INDEX idx_users_password_reset_token ON users (password_reset_token)');
        $this->addSql('CREATE INDEX idx_users_email_verification_token ON users (email_verification_token)');

        // Mark all existing users as verified
        $this->addSql('UPDATE users SET email_verified = TRUE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_users_password_reset_token');
        $this->addSql('DROP INDEX idx_users_email_verification_token');

        $this->addSql('ALTER TABLE users DROP COLUMN email_verified');
        $this->addSql('ALTER TABLE users DROP COLUMN email_verification_token');
        $this->addSql('ALTER TABLE users DROP COLUMN email_verification_sent_at');
        $this->addSql('ALTER TABLE users DROP COLUMN password_reset_token');
        $this->addSql('ALTER TABLE users DROP COLUMN password_reset_expires_at');
        $this->addSql('ALTER TABLE users DROP COLUMN failed_login_attempts');
        $this->addSql('ALTER TABLE users DROP COLUMN locked_until');
        $this->addSql('ALTER TABLE users DROP COLUMN last_failed_login_at');
    }
}
