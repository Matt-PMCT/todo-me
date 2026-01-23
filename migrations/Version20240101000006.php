<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Add search_vector column to tasks for PostgreSQL full-text search
 */
final class Version20240101000006 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add search_vector column (tsvector) to tasks table for PostgreSQL full-text search';
    }

    public function up(Schema $schema): void
    {
        // Add tsvector column for full-text search
        $this->addSql('ALTER TABLE tasks ADD COLUMN search_vector TSVECTOR');

        // Create GIN index for fast full-text search
        $this->addSql('CREATE INDEX idx_tasks_search_vector ON tasks USING GIN (search_vector)');

        // Initialize search_vector for existing rows
        $this->addSql("UPDATE tasks SET search_vector =
            setweight(to_tsvector('english', COALESCE(title, '')), 'A') ||
            setweight(to_tsvector('english', COALESCE(description, '')), 'B')
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_tasks_search_vector');
        $this->addSql('ALTER TABLE tasks DROP COLUMN search_vector');
    }
}
