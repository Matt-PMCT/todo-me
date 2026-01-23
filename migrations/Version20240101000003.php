<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Create tasks table with foreign keys and indexes
 */
final class Version20240101000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tasks table with foreign keys and indexes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE tasks (
            id UUID NOT NULL,
            owner_id UUID NOT NULL,
            project_id UUID DEFAULT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'pending\',
            priority SMALLINT NOT NULL DEFAULT 3,
            due_date DATE DEFAULT NULL,
            position INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )');

        // Individual indexes
        $this->addSql('CREATE INDEX idx_tasks_owner ON tasks (owner_id)');
        $this->addSql('CREATE INDEX idx_tasks_project ON tasks (project_id)');
        $this->addSql('CREATE INDEX idx_tasks_status ON tasks (status)');
        $this->addSql('CREATE INDEX idx_tasks_priority ON tasks (priority)');
        $this->addSql('CREATE INDEX idx_tasks_due_date ON tasks (due_date)');
        $this->addSql('CREATE INDEX idx_tasks_position ON tasks (position)');

        // Composite indexes for common queries
        $this->addSql('CREATE INDEX idx_tasks_status_priority ON tasks (status, priority)');
        $this->addSql('CREATE INDEX idx_tasks_owner_status ON tasks (owner_id, status)');

        // Foreign key constraints
        $this->addSql('ALTER TABLE tasks ADD CONSTRAINT fk_tasks_owner
            FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE tasks ADD CONSTRAINT fk_tasks_project
            FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('COMMENT ON COLUMN tasks.due_date IS \'(DC2Type:date_immutable)\'');
        $this->addSql('COMMENT ON COLUMN tasks.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN tasks.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN tasks.completed_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tasks DROP CONSTRAINT fk_tasks_owner');
        $this->addSql('ALTER TABLE tasks DROP CONSTRAINT fk_tasks_project');
        $this->addSql('DROP TABLE tasks');
    }
}
