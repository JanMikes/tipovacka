<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Teams become first-class: players + sport_matches switch from free-text team
 * names to FK references into the new `teams` table.
 *
 * The auto-generated version of this migration renamed players.match_source_id
 * to team_id and added the FK — leaving match-source UUIDs in a column that must
 * reference teams. On any database with data the ADD CONSTRAINT failed
 * (FK violation) and the whole migration rolled back, so prod stayed on the old
 * schema while new code was already shipping. This version migrates the DATA:
 * it builds Team rows from the existing names first (hybrid scope: curated
 * source → global team per (sport, name), private source → local team per
 * (source, name)), then remaps players and matches by name. Final schema and
 * constraint/index names are identical to what doctrine:migrations:diff produced.
 */
final class Version20260725111530 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'First-class teams: build teams from existing names, re-point players and sport_matches to team FKs';
    }

    public function up(Schema $schema): void
    {
        // 1. Build the team directory from every team name currently in use
        //    (match home/away sides + player rosters). Curated sources share one
        //    GLOBAL team per (sport, name); private sources get LOCAL teams.
        $this->addSql(<<<'SQL'
            INSERT INTO teams (id, name, short_name, country, brand_color, logo, sport_id, match_source_id, created_at, updated_at)
            SELECT gen_random_uuid(), src.name, NULL, NULL, NULL, NULL, src.sport_id, src.local_source_id, timezone('utc', now()), timezone('utc', now())
            FROM (
                SELECT DISTINCT
                    ms.sport_id,
                    names.team_name AS name,
                    CASE WHEN ms.kind = 'private' THEN ms.id END AS local_source_id
                FROM (
                    SELECT match_source_id, home_team AS team_name FROM sport_matches
                    UNION
                    SELECT match_source_id, away_team FROM sport_matches
                    UNION
                    SELECT match_source_id, team_name FROM players
                ) names
                INNER JOIN match_sources ms ON ms.id = names.match_source_id
            ) src
            WHERE NOT EXISTS (
                SELECT 1 FROM teams t
                WHERE t.sport_id = src.sport_id
                  AND t.name = src.name
                  AND t.match_source_id IS NOT DISTINCT FROM src.local_source_id
            )
            SQL);

        // 2. players: (match_source_id, team_name) → team_id, resolved through
        //    the same hybrid-scope rule the teams were created with.
        $this->addSql('ALTER TABLE players DROP CONSTRAINT fk_264e43a68c8d50ca');
        $this->addSql('DROP INDEX uniq_players_source_team_name');
        $this->addSql('DROP INDEX idx_264e43a68c8d50ca');
        $this->addSql('ALTER TABLE players ADD team_id UUID');
        $this->addSql(<<<'SQL'
            UPDATE players p
            SET team_id = t.id
            FROM match_sources ms, teams t
            WHERE ms.id = p.match_source_id
              AND t.sport_id = ms.sport_id
              AND t.name = p.team_name
              AND t.match_source_id IS NOT DISTINCT FROM (CASE WHEN ms.kind = 'private' THEN ms.id END)
            SQL);

        // 3. Curated sources sharing a global team can now hold duplicate
        //    (team, player-name) rows that came from different sources. Merge
        //    them into the oldest player (UUIDv7 ⇒ min id = first created) so
        //    UNIQ_players_team_name can be built; re-point the two referencing
        //    tables first. A single guess can't end up referencing a merged
        //    player twice: its scorers all come from one source, where
        //    (team_name, name) was already unique.
        $this->addSql(<<<'SQL'
            UPDATE match_events me
            SET player_id = d.keep_id
            FROM (
                SELECT id, FIRST_VALUE(id) OVER (PARTITION BY team_id, name ORDER BY id) AS keep_id
                FROM players
            ) d
            WHERE me.player_id = d.id AND d.id <> d.keep_id
            SQL);
        $this->addSql(<<<'SQL'
            UPDATE guess_scorers gs
            SET player_id = d.keep_id
            FROM (
                SELECT id, FIRST_VALUE(id) OVER (PARTITION BY team_id, name ORDER BY id) AS keep_id
                FROM players
            ) d
            WHERE gs.player_id = d.id AND d.id <> d.keep_id
            SQL);
        $this->addSql(<<<'SQL'
            DELETE FROM players
            WHERE id IN (
                SELECT id FROM (
                    SELECT id, ROW_NUMBER() OVER (PARTITION BY team_id, name ORDER BY id) AS rn
                    FROM players
                ) ranked
                WHERE ranked.rn > 1
            )
            SQL);

        $this->addSql('ALTER TABLE players ALTER team_id SET NOT NULL');
        $this->addSql('ALTER TABLE players DROP team_name');
        $this->addSql('ALTER TABLE players DROP match_source_id');
        $this->addSql('ALTER TABLE players ADD CONSTRAINT FK_264E43A6296CD8AE FOREIGN KEY (team_id) REFERENCES teams (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_264E43A6296CD8AE ON players (team_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_players_team_name ON players (team_id, name)');

        // 4. sport_matches: home/away names → team FKs, same resolution rule.
        $this->addSql('ALTER TABLE sport_matches ADD home_team_id UUID');
        $this->addSql('ALTER TABLE sport_matches ADD away_team_id UUID');
        $this->addSql(<<<'SQL'
            UPDATE sport_matches sm
            SET home_team_id = t.id
            FROM match_sources ms, teams t
            WHERE ms.id = sm.match_source_id
              AND t.sport_id = ms.sport_id
              AND t.name = sm.home_team
              AND t.match_source_id IS NOT DISTINCT FROM (CASE WHEN ms.kind = 'private' THEN ms.id END)
            SQL);
        $this->addSql(<<<'SQL'
            UPDATE sport_matches sm
            SET away_team_id = t.id
            FROM match_sources ms, teams t
            WHERE ms.id = sm.match_source_id
              AND t.sport_id = ms.sport_id
              AND t.name = sm.away_team
              AND t.match_source_id IS NOT DISTINCT FROM (CASE WHEN ms.kind = 'private' THEN ms.id END)
            SQL);
        $this->addSql('ALTER TABLE sport_matches ALTER home_team_id SET NOT NULL');
        $this->addSql('ALTER TABLE sport_matches ALTER away_team_id SET NOT NULL');
        $this->addSql('ALTER TABLE sport_matches DROP home_team');
        $this->addSql('ALTER TABLE sport_matches DROP away_team');
        $this->addSql('ALTER TABLE sport_matches ADD CONSTRAINT FK_A79359109C4C13F6 FOREIGN KEY (home_team_id) REFERENCES teams (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE sport_matches ADD CONSTRAINT FK_A793591045185D02 FOREIGN KEY (away_team_id) REFERENCES teams (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_A79359109C4C13F6 ON sport_matches (home_team_id)');
        $this->addSql('CREATE INDEX IDX_A793591045185D02 ON sport_matches (away_team_id)');
    }

    public function down(Schema $schema): void
    {
        // Duplicate players are merged and per-source team ownership is folded
        // into shared global teams — that information cannot be reconstructed.
        $this->throwIrreversibleMigrationException('First-class teams migration merges duplicate players and cannot restore per-source team names.');
    }
}
