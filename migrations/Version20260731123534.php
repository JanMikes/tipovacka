<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260731123534 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE team_aliases (alias VARCHAR(120) NOT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, team_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_A06BCA0E296CD8AE ON team_aliases (team_id)');
        $this->addSql('CREATE INDEX IDX_team_aliases_alias ON team_aliases (alias)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_team_aliases_team_alias ON team_aliases (team_id, alias)');
        $this->addSql('ALTER TABLE team_aliases ADD CONSTRAINT FK_A06BCA0E296CD8AE FOREIGN KEY (team_id) REFERENCES teams (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE match_sources ADD feed_provider VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE match_sources ADD feed_ref VARCHAR(160) DEFAULT NULL');
        $this->addSql('ALTER TABLE sport_matches ADD external_id VARCHAR(120) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_sport_matches_source_external ON sport_matches (match_source_id, external_id) WHERE (external_id IS NOT NULL AND deleted_at IS NULL)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE team_aliases DROP CONSTRAINT FK_A06BCA0E296CD8AE');
        $this->addSql('DROP TABLE team_aliases');
        $this->addSql('ALTER TABLE match_sources DROP feed_provider');
        $this->addSql('ALTER TABLE match_sources DROP feed_ref');
        $this->addSql('DROP INDEX UNIQ_sport_matches_source_external');
        $this->addSql('ALTER TABLE sport_matches DROP external_id');
    }
}
