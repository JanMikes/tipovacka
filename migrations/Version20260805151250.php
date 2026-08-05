<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Retires the single-source scope columns now that every reader goes through
 * `competition_sources`, and renames the surviving FK to what it actually means:
 * the HEADLINE zdroj (layer 0's), used for display, sport derivation and
 * authorization — never for scope.
 *
 * `selection_mode` / `include_playoff` were copied onto layer 0 by
 * Version20260805132603 and have been mirrored on every write since, so
 * dropping them loses nothing. That is asserted rather than assumed: the guard
 * below aborts the migration if any competition lacks a layer 0 or disagrees
 * with it, so a database this migration has not seen cannot silently lose the
 * columns. `down()` restores the values from the layers rather than from
 * defaults, so the round trip is lossless too.
 */
final class Version20260805151250 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop the mirrored scope columns; rename competitions.match_source_id to headline_source_id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DO $$
            DECLARE offenders int;
            BEGIN
                SELECT count(*) INTO offenders
                FROM competitions c
                LEFT JOIN competition_sources cs
                       ON cs.competition_id = c.id AND cs.position = 0
                WHERE cs.id IS NULL
                   OR cs.match_source_id <> c.match_source_id
                   OR cs.selection_mode <> c.selection_mode
                   OR cs.include_playoff <> c.include_playoff;

                IF offenders > 0 THEN
                    RAISE EXCEPTION
                        'Nelze zahodit sloupce rozsahu: % soutěží nemá vrstvu 0 nebo se s ní neshoduje.', offenders;
                END IF;
            END $$;
            SQL);

        $this->addSql('ALTER TABLE competitions DROP CONSTRAINT fk_a7dd463d8c8d50ca');
        $this->addSql('DROP INDEX idx_competitions_match_source');
        $this->addSql('DROP INDEX idx_a7dd463d8c8d50ca');
        $this->addSql('ALTER TABLE competitions DROP selection_mode');
        $this->addSql('ALTER TABLE competitions DROP include_playoff');
        $this->addSql('ALTER TABLE competitions RENAME COLUMN match_source_id TO headline_source_id');
        $this->addSql('ALTER TABLE competitions ADD CONSTRAINT FK_A7DD463DBB25B0D1 FOREIGN KEY (headline_source_id) REFERENCES match_sources (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_A7DD463DBB25B0D1 ON competitions (headline_source_id)');
        $this->addSql('CREATE INDEX IDX_competitions_headline_source ON competitions (headline_source_id, deleted_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE competitions DROP CONSTRAINT FK_A7DD463DBB25B0D1');
        $this->addSql('DROP INDEX IDX_A7DD463DBB25B0D1');
        $this->addSql('DROP INDEX IDX_competitions_headline_source');
        $this->addSql('ALTER TABLE competitions ADD selection_mode VARCHAR(255) DEFAULT \'all\' NOT NULL');
        $this->addSql('ALTER TABLE competitions ADD include_playoff BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('ALTER TABLE competitions RENAME COLUMN headline_source_id TO match_source_id');

        // Restore the real values from layer 0 — the defaults above would hand
        // the old code back a subset competition claiming to include everything.
        $this->addSql(<<<'SQL'
            UPDATE competitions c
            SET selection_mode = cs.selection_mode,
                include_playoff = cs.include_playoff
            FROM competition_sources cs
            WHERE cs.competition_id = c.id AND cs.position = 0
            SQL);

        $this->addSql('ALTER TABLE competitions ADD CONSTRAINT fk_a7dd463d8c8d50ca FOREIGN KEY (match_source_id) REFERENCES match_sources (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_competitions_match_source ON competitions (match_source_id, deleted_at)');
        $this->addSql('CREATE INDEX idx_a7dd463d8c8d50ca ON competitions (match_source_id)');
    }
}
