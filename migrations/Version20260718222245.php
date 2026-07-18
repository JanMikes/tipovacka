<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260718222245 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'S05: sport period config (+hockey seed), match periods/overtime, match_events + players, match_sources finished→completed';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE match_events (id UUID NOT NULL, type VARCHAR(255) NOT NULL, side VARCHAR(255) NOT NULL, minute INT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, sport_match_id UUID NOT NULL, player_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_F2E8AE4B99E6F5DF ON match_events (player_id)');
        $this->addSql('CREATE INDEX IDX_match_events_sport_match ON match_events (sport_match_id)');
        $this->addSql('CREATE TABLE players (id UUID NOT NULL, team_name VARCHAR(120) NOT NULL, name VARCHAR(120) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, match_source_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_264E43A68C8D50CA ON players (match_source_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_players_source_team_name ON players (match_source_id, team_name, name)');
        $this->addSql('ALTER TABLE match_events ADD CONSTRAINT FK_F2E8AE4B1C1C536C FOREIGN KEY (sport_match_id) REFERENCES sport_matches (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE match_events ADD CONSTRAINT FK_F2E8AE4B99E6F5DF FOREIGN KEY (player_id) REFERENCES players (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE players ADD CONSTRAINT FK_264E43A68C8D50CA FOREIGN KEY (match_source_id) REFERENCES match_sources (id) NOT DEFERRABLE');
        $this->addSql('DROP INDEX idx_match_sources_kind_active');
        $this->addSql('ALTER TABLE match_sources RENAME COLUMN finished_at TO completed_at');
        $this->addSql('CREATE INDEX IDX_match_sources_kind_active ON match_sources (kind, completed_at, deleted_at)');
        $this->addSql('ALTER TABLE sport_matches ADD period_scores JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE sport_matches ADD overtime_home_score INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sport_matches ADD overtime_away_score INT DEFAULT NULL');
        // Hand-adjusted: the generated ADD ... NOT NULL would fail on the non-empty
        // sports table (football row seeded by Version20260420143443). Add the
        // columns nullable, backfill/seed, then enforce NOT NULL.
        $this->addSql('ALTER TABLE sports ADD period_count INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sports ADD period_label_singular VARCHAR(30) DEFAULT NULL');
        $this->addSql('ALTER TABLE sports ADD period_label_plural VARCHAR(30) DEFAULT NULL');

        // Backfill football config (2 poločasy).
        $this->addSql(<<<'SQL'
            UPDATE sports
            SET period_count = 2, period_label_singular = 'poločas', period_label_plural = 'poločasy'
            WHERE code = 'football'
            SQL);

        // Seed hockey (3 třetiny) — fixed UUID mirrors Sport::HOCKEY_ID. Self-healing:
        // if a hockey row already exists (e.g. re-running up after a partial down),
        // its period config is updated instead of being silently left stale.
        $this->addSql(<<<'SQL'
            INSERT INTO sports (id, code, name, period_count, period_label_singular, period_label_plural)
            VALUES ('01960000-0000-7000-8000-000000000002', 'hockey', 'Hokej', 3, 'třetina', 'třetiny')
            ON CONFLICT (code) DO UPDATE SET
                period_count = EXCLUDED.period_count,
                period_label_singular = EXCLUDED.period_label_singular,
                period_label_plural = EXCLUDED.period_label_plural
            SQL);

        $this->addSql('ALTER TABLE sports ALTER COLUMN period_count SET NOT NULL');
        $this->addSql('ALTER TABLE sports ALTER COLUMN period_label_singular SET NOT NULL');
        $this->addSql('ALTER TABLE sports ALTER COLUMN period_label_plural SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Remove the hockey seed (Sport::HOCKEY_ID) so down + up round-trips cleanly.
        $this->addSql("DELETE FROM sports WHERE id = '01960000-0000-7000-8000-000000000002'");
        $this->addSql('ALTER TABLE match_events DROP CONSTRAINT FK_F2E8AE4B1C1C536C');
        $this->addSql('ALTER TABLE match_events DROP CONSTRAINT FK_F2E8AE4B99E6F5DF');
        $this->addSql('ALTER TABLE players DROP CONSTRAINT FK_264E43A68C8D50CA');
        $this->addSql('DROP TABLE match_events');
        $this->addSql('DROP TABLE players');
        $this->addSql('DROP INDEX IDX_match_sources_kind_active');
        $this->addSql('ALTER TABLE match_sources RENAME COLUMN completed_at TO finished_at');
        $this->addSql('CREATE INDEX idx_match_sources_kind_active ON match_sources (kind, finished_at, deleted_at)');
        $this->addSql('ALTER TABLE sport_matches DROP period_scores');
        $this->addSql('ALTER TABLE sport_matches DROP overtime_home_score');
        $this->addSql('ALTER TABLE sport_matches DROP overtime_away_score');
        $this->addSql('ALTER TABLE sports DROP period_count');
        $this->addSql('ALTER TABLE sports DROP period_label_singular');
        $this->addSql('ALTER TABLE sports DROP period_label_plural');
    }
}
