<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Add composite indexes and circular reference prevention trigger for projects.
 *
 * Issue 2.6: Add composite indexes for common query patterns on projects table.
 * Issue 2.7: Add PostgreSQL trigger to prevent circular references in project hierarchy.
 */
final class Version20260124200001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add composite indexes and circular reference prevention trigger for projects';
    }

    public function up(Schema $schema): void
    {
        // Issue 2.6: Composite indexes for common queries
        $this->addSql('CREATE INDEX idx_projects_parent_position ON projects (parent_id, position)');
        $this->addSql('CREATE INDEX idx_projects_owner_parent ON projects (owner_id, parent_id)');
        $this->addSql('CREATE INDEX idx_projects_owner_archived_parent ON projects (owner_id, is_archived, parent_id)');

        // Issue 2.7: PostgreSQL trigger to prevent circular references
        $this->addSql('
            CREATE OR REPLACE FUNCTION check_project_circular_reference()
            RETURNS TRIGGER AS $$
            DECLARE
                current_id UUID;
                depth INTEGER := 0;
            BEGIN
                IF NEW.parent_id IS NULL THEN
                    RETURN NEW;
                END IF;

                current_id := NEW.parent_id;
                WHILE current_id IS NOT NULL AND depth < 100 LOOP
                    IF current_id = NEW.id THEN
                        RAISE EXCEPTION \'Circular reference detected in project hierarchy\';
                    END IF;
                    SELECT parent_id INTO current_id FROM projects WHERE id = current_id;
                    depth := depth + 1;
                END LOOP;

                IF depth >= 100 THEN
                    RAISE EXCEPTION \'Project hierarchy too deep\';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        ');

        $this->addSql('
            CREATE TRIGGER trg_check_project_circular_reference
            BEFORE INSERT OR UPDATE ON projects
            FOR EACH ROW
            EXECUTE FUNCTION check_project_circular_reference()
        ');
    }

    public function down(Schema $schema): void
    {
        // Drop trigger and function
        $this->addSql('DROP TRIGGER IF EXISTS trg_check_project_circular_reference ON projects');
        $this->addSql('DROP FUNCTION IF EXISTS check_project_circular_reference()');

        // Drop composite indexes
        $this->addSql('DROP INDEX IF EXISTS idx_projects_parent_position');
        $this->addSql('DROP INDEX IF EXISTS idx_projects_owner_parent');
        $this->addSql('DROP INDEX IF EXISTS idx_projects_owner_archived_parent');
    }
}
