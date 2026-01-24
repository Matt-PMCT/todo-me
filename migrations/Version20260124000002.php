<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Add soft delete support to projects table
 */
final class Version20260124000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add deleted_at column to projects table for soft delete support';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE projects ADD deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_projects_deleted_at ON projects (deleted_at)');
        $this->addSql('COMMENT ON COLUMN projects.deleted_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_projects_deleted_at');
        $this->addSql('ALTER TABLE projects DROP COLUMN IF EXISTS deleted_at');
    }
}
