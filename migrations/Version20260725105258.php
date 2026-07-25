<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260725105258 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE teams (name VARCHAR(120) NOT NULL, short_name VARCHAR(40) DEFAULT NULL, country VARCHAR(2) DEFAULT NULL, brand_color VARCHAR(7) DEFAULT NULL, logo VARCHAR(255) DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, sport_id UUID NOT NULL, match_source_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_96C22258AC78BCF8 ON teams (sport_id)');
        $this->addSql('CREATE INDEX IDX_96C222588C8D50CA ON teams (match_source_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_teams_global_sport_name ON teams (sport_id, name) WHERE (match_source_id IS NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_teams_local_source_name ON teams (match_source_id, name) WHERE (match_source_id IS NOT NULL)');
        $this->addSql('ALTER TABLE teams ADD CONSTRAINT FK_96C22258AC78BCF8 FOREIGN KEY (sport_id) REFERENCES sports (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE teams ADD CONSTRAINT FK_96C222588C8D50CA FOREIGN KEY (match_source_id) REFERENCES match_sources (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE teams DROP CONSTRAINT FK_96C22258AC78BCF8');
        $this->addSql('ALTER TABLE teams DROP CONSTRAINT FK_96C222588C8D50CA');
        $this->addSql('DROP TABLE teams');
    }
}
