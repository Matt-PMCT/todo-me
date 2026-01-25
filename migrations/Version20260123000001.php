<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Phase 1 Review Fixes - Entity schema changes
 *
 * Changes:
 * - Users: Add username and settings columns
 * - Projects: Add parent_id, color, icon, position, archived_at, show_children_tasks
 * - Tasks: Change title to VARCHAR(500), priority default to 2, add due_time,
 *          parent_task_id, original_task_id, recurrence fields
 */
final class Version20260123000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 1 Review Fixes - Entity schema changes for users, projects, and tasks';
    }

    public function up(Schema $schema): void
    {
        // Users table changes
        $this->addSql('ALTER TABLE users ADD username VARCHAR(100) NOT NULL DEFAULT \'\'');
        $this->addSql('ALTER TABLE users ADD settings JSON NOT NULL DEFAULT \'{}\'');

        // Generate unique usernames from email for existing users BEFORE creating unique index
        $this->addSql('UPDATE users SET username = SPLIT_PART(email, \'@\', 1) || \'_\' || SUBSTRING(id::text, 1, 8) WHERE username = \'\'');

        // Now create unique index after data is populated
        $this->addSql('CREATE UNIQUE INDEX uniq_users_username ON users (username)');
        $this->addSql('CREATE INDEX idx_users_username ON users (username)');

        // Remove the default after migration
        $this->addSql('ALTER TABLE users ALTER COLUMN username DROP DEFAULT');

        // Projects table changes
        $this->addSql('ALTER TABLE projects ADD parent_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE projects ADD color VARCHAR(7) NOT NULL DEFAULT \'#808080\'');
        $this->addSql('ALTER TABLE projects ADD icon VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE projects ADD position INTEGER NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE projects ADD archived_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE projects ADD show_children_tasks BOOLEAN NOT NULL DEFAULT true');
        $this->addSql('ALTER TABLE projects ADD CONSTRAINT fk_projects_parent FOREIGN KEY (parent_id) REFERENCES projects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_projects_parent ON projects (parent_id)');
        $this->addSql('CREATE INDEX idx_projects_position ON projects (position)');
        $this->addSql('COMMENT ON COLUMN projects.archived_at IS \'(DC2Type:datetime_immutable)\'');

        // Tasks table changes
        // Drop triggers that depend on the title column before altering it
        $this->addSql('DROP TRIGGER IF EXISTS tasks_search_vector_update ON tasks');
        $this->addSql('DROP TRIGGER IF EXISTS tasks_search_vector_insert ON tasks');

        $this->addSql('ALTER TABLE tasks ALTER COLUMN title TYPE VARCHAR(500)');

        // Recreate the triggers after altering the column
        $this->addSql('
            CREATE TRIGGER tasks_search_vector_insert
            BEFORE INSERT ON tasks
            FOR EACH ROW
            EXECUTE FUNCTION tasks_search_vector_update()
        ');
        $this->addSql('
            CREATE TRIGGER tasks_search_vector_update
            BEFORE UPDATE ON tasks
            FOR EACH ROW
            WHEN (OLD.title IS DISTINCT FROM NEW.title OR OLD.description IS DISTINCT FROM NEW.description)
            EXECUTE FUNCTION tasks_search_vector_update()
        ');
        $this->addSql('ALTER TABLE tasks ALTER COLUMN priority SET DEFAULT 2');
        $this->addSql('ALTER TABLE tasks ADD due_time TIME(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE tasks ADD parent_task_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE tasks ADD original_task_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE tasks ADD is_recurring BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('ALTER TABLE tasks ADD recurrence_rule VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE tasks ADD recurrence_type VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE tasks ADD recurrence_end_date DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE tasks ADD CONSTRAINT fk_tasks_parent FOREIGN KEY (parent_task_id) REFERENCES tasks (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE tasks ADD CONSTRAINT fk_tasks_original FOREIGN KEY (original_task_id) REFERENCES tasks (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_tasks_parent ON tasks (parent_task_id)');
        $this->addSql('CREATE INDEX idx_tasks_original ON tasks (original_task_id)');
        $this->addSql('CREATE INDEX idx_tasks_recurring ON tasks (is_recurring)');
        $this->addSql('COMMENT ON COLUMN tasks.due_time IS \'(DC2Type:time_immutable)\'');
        $this->addSql('COMMENT ON COLUMN tasks.recurrence_end_date IS \'(DC2Type:date_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // Tasks table rollback
        $this->addSql('ALTER TABLE tasks DROP CONSTRAINT IF EXISTS fk_tasks_parent');
        $this->addSql('ALTER TABLE tasks DROP CONSTRAINT IF EXISTS fk_tasks_original');
        $this->addSql('DROP INDEX IF EXISTS idx_tasks_parent');
        $this->addSql('DROP INDEX IF EXISTS idx_tasks_original');
        $this->addSql('DROP INDEX IF EXISTS idx_tasks_recurring');
        $this->addSql('ALTER TABLE tasks DROP COLUMN IF EXISTS due_time');
        $this->addSql('ALTER TABLE tasks DROP COLUMN IF EXISTS parent_task_id');
        $this->addSql('ALTER TABLE tasks DROP COLUMN IF EXISTS original_task_id');
        $this->addSql('ALTER TABLE tasks DROP COLUMN IF EXISTS is_recurring');
        $this->addSql('ALTER TABLE tasks DROP COLUMN IF EXISTS recurrence_rule');
        $this->addSql('ALTER TABLE tasks DROP COLUMN IF EXISTS recurrence_type');
        $this->addSql('ALTER TABLE tasks DROP COLUMN IF EXISTS recurrence_end_date');
        // Drop triggers that depend on the title column before altering it
        $this->addSql('DROP TRIGGER IF EXISTS tasks_search_vector_update ON tasks');
        $this->addSql('DROP TRIGGER IF EXISTS tasks_search_vector_insert ON tasks');

        $this->addSql('ALTER TABLE tasks ALTER COLUMN title TYPE VARCHAR(255)');

        // Recreate the triggers after altering the column
        $this->addSql('
            CREATE TRIGGER tasks_search_vector_insert
            BEFORE INSERT ON tasks
            FOR EACH ROW
            EXECUTE FUNCTION tasks_search_vector_update()
        ');
        $this->addSql('
            CREATE TRIGGER tasks_search_vector_update
            BEFORE UPDATE ON tasks
            FOR EACH ROW
            WHEN (OLD.title IS DISTINCT FROM NEW.title OR OLD.description IS DISTINCT FROM NEW.description)
            EXECUTE FUNCTION tasks_search_vector_update()
        ');
        $this->addSql('ALTER TABLE tasks ALTER COLUMN priority SET DEFAULT 3');

        // Projects table rollback
        $this->addSql('ALTER TABLE projects DROP CONSTRAINT IF EXISTS fk_projects_parent');
        $this->addSql('DROP INDEX IF EXISTS idx_projects_parent');
        $this->addSql('DROP INDEX IF EXISTS idx_projects_position');
        $this->addSql('ALTER TABLE projects DROP COLUMN IF EXISTS parent_id');
        $this->addSql('ALTER TABLE projects DROP COLUMN IF EXISTS color');
        $this->addSql('ALTER TABLE projects DROP COLUMN IF EXISTS icon');
        $this->addSql('ALTER TABLE projects DROP COLUMN IF EXISTS position');
        $this->addSql('ALTER TABLE projects DROP COLUMN IF EXISTS archived_at');
        $this->addSql('ALTER TABLE projects DROP COLUMN IF EXISTS show_children_tasks');

        // Users table rollback
        $this->addSql('DROP INDEX IF EXISTS uniq_users_username');
        $this->addSql('DROP INDEX IF EXISTS idx_users_username');
        $this->addSql('ALTER TABLE users DROP COLUMN IF EXISTS username');
        $this->addSql('ALTER TABLE users DROP COLUMN IF EXISTS settings');
    }
}
