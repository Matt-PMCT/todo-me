<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Add composite index for optimized subtask queries
 */
final class Version20260125000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add composite index on owner_id and parent_task_id for optimized subtask queries';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_tasks_owner_parent ON tasks (owner_id, parent_task_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_tasks_owner_parent');
    }
}
