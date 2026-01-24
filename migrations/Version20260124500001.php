<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Add recurring task support columns to tasks table
 */
final class Version20260124500001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add recurring task support: is_recurring, recurrence_rule, recurrence_type, recurrence_end_date columns';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tasks ADD COLUMN is_recurring BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE tasks ADD COLUMN recurrence_rule TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE tasks ADD COLUMN recurrence_type VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE tasks ADD COLUMN recurrence_end_date DATE DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_tasks_owner_recurring ON tasks (owner_id, is_recurring)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_tasks_owner_recurring');
        $this->addSql('ALTER TABLE tasks DROP COLUMN is_recurring');
        $this->addSql('ALTER TABLE tasks DROP COLUMN recurrence_rule');
        $this->addSql('ALTER TABLE tasks DROP COLUMN recurrence_type');
        $this->addSql('ALTER TABLE tasks DROP COLUMN recurrence_end_date');
    }
}
