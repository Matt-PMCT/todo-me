<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Remove unused recurrence feature from tasks table.
 *
 * The recurrence properties (is_recurring, recurrence_rule, recurrence_type,
 * recurrence_end_date) were placeholder schema elements that were never
 * implemented in the service layer. Removing them to reduce code confusion.
 */
final class Version20260124100001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove unused recurrence columns and index from tasks table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_tasks_recurring');
        $this->addSql('ALTER TABLE tasks DROP COLUMN IF EXISTS is_recurring');
        $this->addSql('ALTER TABLE tasks DROP COLUMN IF EXISTS recurrence_rule');
        $this->addSql('ALTER TABLE tasks DROP COLUMN IF EXISTS recurrence_type');
        $this->addSql('ALTER TABLE tasks DROP COLUMN IF EXISTS recurrence_end_date');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tasks ADD is_recurring BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('ALTER TABLE tasks ADD recurrence_rule VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE tasks ADD recurrence_type VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE tasks ADD recurrence_end_date DATE DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_tasks_recurring ON tasks (is_recurring)');
        $this->addSql('COMMENT ON COLUMN tasks.recurrence_end_date IS \'(DC2Type:date_immutable)\'');
    }
}
