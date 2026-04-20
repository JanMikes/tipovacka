<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260420154307 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE sport_matches (home_team VARCHAR(120) NOT NULL, away_team VARCHAR(120) NOT NULL, kickoff_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, venue VARCHAR(160) DEFAULT NULL, state VARCHAR(255) NOT NULL, home_score INT DEFAULT NULL, away_score INT DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, tournament_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_A793591033D1A3E7 ON sport_matches (tournament_id)');
        $this->addSql('CREATE INDEX IDX_sport_matches_tournament_kickoff ON sport_matches (tournament_id, kickoff_at, deleted_at)');
        $this->addSql('CREATE INDEX IDX_sport_matches_state_kickoff ON sport_matches (state, kickoff_at, deleted_at)');
        $this->addSql('ALTER TABLE sport_matches ADD CONSTRAINT FK_A793591033D1A3E7 FOREIGN KEY (tournament_id) REFERENCES tournaments (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE sport_matches DROP CONSTRAINT FK_A793591033D1A3E7');
        $this->addSql('DROP TABLE sport_matches');
    }
}
