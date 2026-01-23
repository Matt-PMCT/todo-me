<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Create projects table with foreign key to users
 */
final class Version20240101000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create projects table with foreign key to users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE projects (
            id UUID NOT NULL,
            owner_id UUID NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            is_archived BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE INDEX idx_projects_owner ON projects (owner_id)');
        $this->addSql('CREATE INDEX idx_projects_archived ON projects (is_archived)');

        $this->addSql('ALTER TABLE projects ADD CONSTRAINT fk_projects_owner
            FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('COMMENT ON COLUMN projects.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN projects.updated_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE projects DROP CONSTRAINT fk_projects_owner');
        $this->addSql('DROP TABLE projects');
    }
}
