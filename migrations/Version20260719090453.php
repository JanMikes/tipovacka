<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260719090453 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE guess_scorers (id UUID NOT NULL, side VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, guess_id UUID NOT NULL, player_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_AA657B0B79D9EE1 ON guess_scorers (guess_id)');
        $this->addSql('CREATE INDEX IDX_AA657B0B99E6F5DF ON guess_scorers (player_id)');
        $this->addSql('CREATE UNIQUE INDEX UIDX_guess_scorers_guess_player ON guess_scorers (guess_id, player_id)');
        $this->addSql('ALTER TABLE guess_scorers ADD CONSTRAINT FK_AA657B0B79D9EE1 FOREIGN KEY (guess_id) REFERENCES guesses (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE guess_scorers ADD CONSTRAINT FK_AA657B0B99E6F5DF FOREIGN KEY (player_id) REFERENCES players (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE guesses ADD period_scores JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE guesses ADD overtime_home_score INT DEFAULT NULL');
        $this->addSql('ALTER TABLE guesses ADD overtime_away_score INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE guess_scorers DROP CONSTRAINT FK_AA657B0B79D9EE1');
        $this->addSql('ALTER TABLE guess_scorers DROP CONSTRAINT FK_AA657B0B99E6F5DF');
        $this->addSql('DROP TABLE guess_scorers');
        $this->addSql('ALTER TABLE guesses DROP period_scores');
        $this->addSql('ALTER TABLE guesses DROP overtime_home_score');
        $this->addSql('ALTER TABLE guesses DROP overtime_away_score');
    }
}
