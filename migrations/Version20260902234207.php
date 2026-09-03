<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260902234207 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'MatchSource.hasOvertime: whether a drawn match in the zdroj can go on to extra time / penalties (default off: a draw is final).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE match_sources ADD has_overtime BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE match_sources DROP has_overtime');
    }
}
