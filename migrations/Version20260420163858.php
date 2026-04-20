<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260420163858 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE guess_evaluation_rule_points (id UUID NOT NULL, rule_identifier VARCHAR(64) NOT NULL, points INT NOT NULL, evaluation_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_BA922877456C5646 ON guess_evaluation_rule_points (evaluation_id)');
        $this->addSql('CREATE UNIQUE INDEX UIDX_eval_rule_points ON guess_evaluation_rule_points (evaluation_id, rule_identifier)');
        $this->addSql('CREATE TABLE guess_evaluations (total_points INT NOT NULL, id UUID NOT NULL, evaluated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, guess_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C134D4C779D9EE1 ON guess_evaluations (guess_id)');
        $this->addSql('CREATE TABLE tournament_rule_configurations (enabled BOOLEAN NOT NULL, points INT NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, rule_identifier VARCHAR(64) NOT NULL, tournament_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_395B8CC533D1A3E7 ON tournament_rule_configurations (tournament_id)');
        $this->addSql('CREATE UNIQUE INDEX UIDX_rule_config_tournament_rule ON tournament_rule_configurations (tournament_id, rule_identifier)');
        $this->addSql('ALTER TABLE guess_evaluation_rule_points ADD CONSTRAINT FK_BA922877456C5646 FOREIGN KEY (evaluation_id) REFERENCES guess_evaluations (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE guess_evaluations ADD CONSTRAINT FK_C134D4C779D9EE1 FOREIGN KEY (guess_id) REFERENCES guesses (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE tournament_rule_configurations ADD CONSTRAINT FK_395B8CC533D1A3E7 FOREIGN KEY (tournament_id) REFERENCES tournaments (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE guess_evaluation_rule_points DROP CONSTRAINT FK_BA922877456C5646');
        $this->addSql('ALTER TABLE guess_evaluations DROP CONSTRAINT FK_C134D4C779D9EE1');
        $this->addSql('ALTER TABLE tournament_rule_configurations DROP CONSTRAINT FK_395B8CC533D1A3E7');
        $this->addSql('DROP TABLE guess_evaluation_rule_points');
        $this->addSql('DROP TABLE guess_evaluations');
        $this->addSql('DROP TABLE tournament_rule_configurations');
    }
}
