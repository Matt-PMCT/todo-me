<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Clamp task priority values to the new 1-5 range.
 *
 * Previously the range was 0-4. This migration updates any out-of-range
 * values to fit within the new 1-5 range.
 */
final class Version20260201ClampPriorityRange extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clamp task priority values to new 1-5 range';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE tasks SET priority = 1 WHERE priority = 0');
        $this->addSql('UPDATE tasks SET priority = 5 WHERE priority > 5');
    }

    public function down(Schema $schema): void
    {
        // Cannot reliably reverse: we don't know which priority=1 rows were originally 0
        $this->addSql('SELECT 1');
    }
}
