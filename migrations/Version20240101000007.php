<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Create trigger function to auto-update search_vector on task insert/update
 */
final class Version20240101000007 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create trigger function to automatically update search_vector on task insert/update';
    }

    public function up(Schema $schema): void
    {
        // Create the trigger function
        $this->addSql("
            CREATE OR REPLACE FUNCTION tasks_search_vector_update() RETURNS trigger AS $$
            BEGIN
                NEW.search_vector :=
                    setweight(to_tsvector('english', COALESCE(NEW.title, '')), 'A') ||
                    setweight(to_tsvector('english', COALESCE(NEW.description, '')), 'B');
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        ");

        // Create trigger for INSERT
        $this->addSql("
            CREATE TRIGGER tasks_search_vector_insert
            BEFORE INSERT ON tasks
            FOR EACH ROW
            EXECUTE FUNCTION tasks_search_vector_update()
        ");

        // Create trigger for UPDATE (only when title or description changes)
        $this->addSql("
            CREATE TRIGGER tasks_search_vector_update
            BEFORE UPDATE ON tasks
            FOR EACH ROW
            WHEN (OLD.title IS DISTINCT FROM NEW.title OR OLD.description IS DISTINCT FROM NEW.description)
            EXECUTE FUNCTION tasks_search_vector_update()
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER IF EXISTS tasks_search_vector_update ON tasks');
        $this->addSql('DROP TRIGGER IF EXISTS tasks_search_vector_insert ON tasks');
        $this->addSql('DROP FUNCTION IF EXISTS tasks_search_vector_update()');
    }
}
