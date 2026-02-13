<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fix existing subtasks whose project doesn't match their parent's project.
 */
final class Version20260209FixSubtaskProjects extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align subtask projects with their parent task projects';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            UPDATE tasks AS child
            SET project_id = parent.project_id
            FROM tasks AS parent
            WHERE child.parent_task_id = parent.id
              AND child.project_id IS DISTINCT FROM parent.project_id
        ');
    }

    public function down(Schema $schema): void
    {
        // Cannot reliably reverse: we don't know the original project assignments
        $this->addSql('SELECT 1');
    }
}
