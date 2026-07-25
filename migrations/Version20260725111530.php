<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260725111530 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE players DROP CONSTRAINT fk_264e43a68c8d50ca');
        $this->addSql('DROP INDEX uniq_players_source_team_name');
        $this->addSql('DROP INDEX idx_264e43a68c8d50ca');
        $this->addSql('ALTER TABLE players DROP team_name');
        $this->addSql('ALTER TABLE players RENAME COLUMN match_source_id TO team_id');
        $this->addSql('ALTER TABLE players ADD CONSTRAINT FK_264E43A6296CD8AE FOREIGN KEY (team_id) REFERENCES teams (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_264E43A6296CD8AE ON players (team_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_players_team_name ON players (team_id, name)');
        $this->addSql('ALTER TABLE sport_matches ADD home_team_id UUID NOT NULL');
        $this->addSql('ALTER TABLE sport_matches ADD away_team_id UUID NOT NULL');
        $this->addSql('ALTER TABLE sport_matches DROP home_team');
        $this->addSql('ALTER TABLE sport_matches DROP away_team');
        $this->addSql('ALTER TABLE sport_matches ADD CONSTRAINT FK_A79359109C4C13F6 FOREIGN KEY (home_team_id) REFERENCES teams (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE sport_matches ADD CONSTRAINT FK_A793591045185D02 FOREIGN KEY (away_team_id) REFERENCES teams (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_A79359109C4C13F6 ON sport_matches (home_team_id)');
        $this->addSql('CREATE INDEX IDX_A793591045185D02 ON sport_matches (away_team_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE players DROP CONSTRAINT FK_264E43A6296CD8AE');
        $this->addSql('DROP INDEX IDX_264E43A6296CD8AE');
        $this->addSql('DROP INDEX UNIQ_players_team_name');
        $this->addSql('ALTER TABLE players ADD team_name VARCHAR(120) NOT NULL');
        $this->addSql('ALTER TABLE players RENAME COLUMN team_id TO match_source_id');
        $this->addSql('ALTER TABLE players ADD CONSTRAINT fk_264e43a68c8d50ca FOREIGN KEY (match_source_id) REFERENCES match_sources (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX uniq_players_source_team_name ON players (match_source_id, team_name, name)');
        $this->addSql('CREATE INDEX idx_264e43a68c8d50ca ON players (match_source_id)');
        $this->addSql('ALTER TABLE sport_matches DROP CONSTRAINT FK_A79359109C4C13F6');
        $this->addSql('ALTER TABLE sport_matches DROP CONSTRAINT FK_A793591045185D02');
        $this->addSql('DROP INDEX IDX_A79359109C4C13F6');
        $this->addSql('DROP INDEX IDX_A793591045185D02');
        $this->addSql('ALTER TABLE sport_matches ADD home_team VARCHAR(120) NOT NULL');
        $this->addSql('ALTER TABLE sport_matches ADD away_team VARCHAR(120) NOT NULL');
        $this->addSql('ALTER TABLE sport_matches DROP home_team_id');
        $this->addSql('ALTER TABLE sport_matches DROP away_team_id');
    }
}
