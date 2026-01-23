<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Create task_tag junction table for ManyToMany relationship
 */
final class Version20240101000005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create task_tag junction table for ManyToMany relationship between tasks and tags';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE task_tag (
            task_id UUID NOT NULL,
            tag_id UUID NOT NULL,
            PRIMARY KEY(task_id, tag_id)
        )');

        $this->addSql('CREATE INDEX idx_task_tag_task ON task_tag (task_id)');
        $this->addSql('CREATE INDEX idx_task_tag_tag ON task_tag (tag_id)');

        $this->addSql('ALTER TABLE task_tag ADD CONSTRAINT fk_task_tag_task
            FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE task_tag ADD CONSTRAINT fk_task_tag_tag
            FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task_tag DROP CONSTRAINT fk_task_tag_task');
        $this->addSql('ALTER TABLE task_tag DROP CONSTRAINT fk_task_tag_tag');
        $this->addSql('DROP TABLE task_tag');
    }
}
