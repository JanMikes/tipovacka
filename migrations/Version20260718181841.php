<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260718181841 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'S02: MatchSource visibility→kind (public→curated), drop creation_pin, SportMatch.is_playoff, Competition selection mode + include_playoff, competition_match_selections table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE competition_match_selections (id UUID NOT NULL, added_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, competition_id UUID NOT NULL, sport_match_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_943F285C7B39D312 ON competition_match_selections (competition_id)');
        $this->addSql('CREATE INDEX IDX_943F285C1C1C536C ON competition_match_selections (sport_match_id)');
        $this->addSql('CREATE UNIQUE INDEX UIDX_competition_match_selections_competition_match ON competition_match_selections (competition_id, sport_match_id)');
        $this->addSql('ALTER TABLE competition_match_selections ADD CONSTRAINT FK_943F285C7B39D312 FOREIGN KEY (competition_id) REFERENCES competitions (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE competition_match_selections ADD CONSTRAINT FK_943F285C1C1C536C FOREIGN KEY (sport_match_id) REFERENCES sport_matches (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE competitions ADD selection_mode VARCHAR(255) DEFAULT \'all\' NOT NULL');
        $this->addSql('ALTER TABLE competitions ADD include_playoff BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('DROP INDEX idx_match_sources_owner_visibility');
        $this->addSql('DROP INDEX idx_match_sources_public_active');
        $this->addSql('ALTER TABLE match_sources DROP creation_pin');
        $this->addSql('ALTER TABLE match_sources RENAME COLUMN visibility TO kind');
        // Data migration: former "public" sources become "curated" ("private" stays).
        $this->addSql("UPDATE match_sources SET kind = 'curated' WHERE kind = 'public'");
        $this->addSql('CREATE INDEX IDX_match_sources_kind_active ON match_sources (kind, finished_at, deleted_at)');
        $this->addSql('CREATE INDEX IDX_match_sources_owner_kind ON match_sources (owner_id, kind, deleted_at)');
        $this->addSql('ALTER TABLE sport_matches ADD is_playoff BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE competition_match_selections DROP CONSTRAINT FK_943F285C7B39D312');
        $this->addSql('ALTER TABLE competition_match_selections DROP CONSTRAINT FK_943F285C1C1C536C');
        $this->addSql('DROP TABLE competition_match_selections');
        $this->addSql('ALTER TABLE competitions DROP selection_mode');
        $this->addSql('ALTER TABLE competitions DROP include_playoff');
        $this->addSql('DROP INDEX IDX_match_sources_kind_active');
        $this->addSql('DROP INDEX IDX_match_sources_owner_kind');
        $this->addSql('ALTER TABLE match_sources ADD creation_pin VARCHAR(8) DEFAULT NULL');
        $this->addSql('ALTER TABLE match_sources RENAME COLUMN kind TO visibility');
        $this->addSql("UPDATE match_sources SET visibility = 'public' WHERE visibility = 'curated'");
        $this->addSql('CREATE INDEX idx_match_sources_owner_visibility ON match_sources (owner_id, visibility, deleted_at)');
        $this->addSql('CREATE INDEX idx_match_sources_public_active ON match_sources (visibility, finished_at, deleted_at)');
        $this->addSql('ALTER TABLE sport_matches DROP is_playoff');
    }
}
