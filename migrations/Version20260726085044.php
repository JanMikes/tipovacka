<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Competition team filter: the link table backing the „zápasy vybraných týmů"
 * selection mode — a competition dynamically includes every source match where
 * one of its filter teams plays (home or away).
 */
final class Version20260726085044 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add competition_team_filters (team-filtered competition selection mode)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE competition_team_filters (id UUID NOT NULL, added_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, competition_id UUID NOT NULL, team_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_309BEB2E7B39D312 ON competition_team_filters (competition_id)');
        $this->addSql('CREATE INDEX IDX_309BEB2E296CD8AE ON competition_team_filters (team_id)');
        $this->addSql('CREATE UNIQUE INDEX UIDX_competition_team_filters_competition_team ON competition_team_filters (competition_id, team_id)');
        $this->addSql('ALTER TABLE competition_team_filters ADD CONSTRAINT FK_309BEB2E7B39D312 FOREIGN KEY (competition_id) REFERENCES competitions (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE competition_team_filters ADD CONSTRAINT FK_309BEB2E296CD8AE FOREIGN KEY (team_id) REFERENCES teams (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE competition_team_filters DROP CONSTRAINT FK_309BEB2E7B39D312');
        $this->addSql('ALTER TABLE competition_team_filters DROP CONSTRAINT FK_309BEB2E296CD8AE');
        $this->addSql('DROP TABLE competition_team_filters');
    }
}
