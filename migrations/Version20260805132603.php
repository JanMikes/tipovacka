<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Multi-source competitions: a competition's match scope becomes an ordered set
 * of `competition_sources` layers (zdroj + per-source selection mode) whose
 * union is „which matches are in this soutěž".
 *
 * Data-aware: both new FK columns are added NULLable, backfilled from the
 * existing 1:1 linkage, and only then promoted to NOT NULL — the generated DDL
 * would have added them NOT NULL against populated tables and failed on any
 * seeded database.
 */
final class Version20260805132603 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Competition match scope becomes a set of CompetitionSource layers (multi-source competitions)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE competition_sources (selection_mode VARCHAR(255) NOT NULL, include_playoff BOOLEAN NOT NULL, position INT NOT NULL, id UUID NOT NULL, added_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, competition_id UUID NOT NULL, match_source_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_CDE8A55C7B39D312 ON competition_sources (competition_id)');
        $this->addSql('CREATE INDEX IDX_competition_sources_match_source ON competition_sources (match_source_id)');
        $this->addSql('CREATE UNIQUE INDEX UIDX_competition_sources_competition_source ON competition_sources (competition_id, match_source_id)');
        $this->addSql('ALTER TABLE competition_sources ADD CONSTRAINT FK_CDE8A55C7B39D312 FOREIGN KEY (competition_id) REFERENCES competitions (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE competition_sources ADD CONSTRAINT FK_CDE8A55C8C8D50CA FOREIGN KEY (match_source_id) REFERENCES match_sources (id) NOT DEFERRABLE');

        // Every existing competition becomes a single-layer one, carrying its
        // own mode/playoff flag over. `added_at` = the competition's creation
        // moment, so a backfilled layer is never mistaken for a late addition
        // by EffectiveTipDeadlineResolver. Soft-deleted competitions are
        // included — their selection/filter rows still need a parent layer.
        // gen_random_uuid() (v4) rather than the app's v7: Postgres 17 has no
        // uuidv7(), and these ids are never sorted on.
        $this->addSql(<<<'SQL'
            INSERT INTO competition_sources (id, competition_id, match_source_id, selection_mode, include_playoff, position, added_at)
            SELECT gen_random_uuid(), c.id, c.match_source_id, c.selection_mode,
                   CASE WHEN c.selection_mode = 'all' THEN c.include_playoff ELSE true END,
                   0, c.created_at
            FROM competitions c
            SQL);

        $this->addSql('ALTER TABLE competition_match_selections ADD competition_source_id UUID DEFAULT NULL');
        $this->addSql(<<<'SQL'
            UPDATE competition_match_selections s
            SET competition_source_id = cs.id
            FROM competition_sources cs
            WHERE cs.competition_id = s.competition_id
            SQL);
        $this->addSql('ALTER TABLE competition_match_selections ALTER COLUMN competition_source_id SET NOT NULL');
        $this->addSql('ALTER TABLE competition_match_selections ADD CONSTRAINT FK_943F285C3EDC5003 FOREIGN KEY (competition_source_id) REFERENCES competition_sources (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_competition_match_selections_source ON competition_match_selections (competition_source_id)');

        $this->addSql('ALTER TABLE competition_team_filters ADD competition_source_id UUID DEFAULT NULL');
        $this->addSql(<<<'SQL'
            UPDATE competition_team_filters f
            SET competition_source_id = cs.id
            FROM competition_sources cs
            WHERE cs.competition_id = f.competition_id
            SQL);
        $this->addSql('ALTER TABLE competition_team_filters ALTER COLUMN competition_source_id SET NOT NULL');
        $this->addSql('ALTER TABLE competition_team_filters ADD CONSTRAINT FK_309BEB2E3EDC5003 FOREIGN KEY (competition_source_id) REFERENCES competition_sources (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_309BEB2E3EDC5003 ON competition_team_filters (competition_source_id)');

        // Uniqueness moves from (competition, team) to (layer, team): one global
        // directory team may legitimately filter two different zdroje of the
        // same soutěž (Sparta in Chance Lize AND in Lize mistrů).
        $this->addSql('DROP INDEX uidx_competition_team_filters_competition_team');
        $this->addSql('CREATE UNIQUE INDEX UIDX_competition_team_filters_source_team ON competition_team_filters (competition_source_id, team_id)');
        $this->addSql('ALTER INDEX idx_309beb2e7b39d312 RENAME TO IDX_competition_team_filters_competition');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE competition_match_selections DROP CONSTRAINT FK_943F285C3EDC5003');
        $this->addSql('DROP INDEX IDX_competition_match_selections_source');
        $this->addSql('ALTER TABLE competition_match_selections DROP competition_source_id');
        $this->addSql('ALTER TABLE competition_team_filters DROP CONSTRAINT FK_309BEB2E3EDC5003');
        $this->addSql('DROP INDEX IDX_309BEB2E3EDC5003');
        $this->addSql('DROP INDEX UIDX_competition_team_filters_source_team');
        $this->addSql('ALTER TABLE competition_team_filters DROP competition_source_id');
        $this->addSql('CREATE UNIQUE INDEX uidx_competition_team_filters_competition_team ON competition_team_filters (competition_id, team_id)');
        $this->addSql('ALTER INDEX idx_competition_team_filters_competition RENAME TO idx_309beb2e7b39d312');
        $this->addSql('ALTER TABLE competition_sources DROP CONSTRAINT FK_CDE8A55C7B39D312');
        $this->addSql('ALTER TABLE competition_sources DROP CONSTRAINT FK_CDE8A55C8C8D50CA');
        $this->addSql('DROP TABLE competition_sources');
    }
}
