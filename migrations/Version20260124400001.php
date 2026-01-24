<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Add icon and color columns to saved_filters table
 */
final class Version20260124400001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add icon and color columns to saved_filters table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE saved_filters ADD COLUMN icon VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE saved_filters ADD COLUMN color VARCHAR(7) DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN saved_filters.id IS \'(DC2Type:guid)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE saved_filters DROP COLUMN icon');
        $this->addSql('ALTER TABLE saved_filters DROP COLUMN color');
        $this->addSql('COMMENT ON COLUMN saved_filters.id IS NULL');
    }
}
