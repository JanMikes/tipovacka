<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * S07 tip locking: replaces the old group-wide default deadline
 * (competitions.tips_deadline) with the competition-start locking model.
 *
 * - tip_change_offset_minutes: „Měnit tip" entitlement offset (default 60,
 *   manager-editable on premium competitions from S10).
 * - tips_deadline → tips_locked_at: the generated RENAME carries the old value
 *   across, then a conditional UPDATE keeps it as a manual lock ONLY when it is
 *   a faithful lock moment — i.e. at/before the competition's start (its
 *   earliest still-present kickoff). A stored deadline AFTER the first kickoff
 *   never actually locked anything under the old model, so resurrecting it as a
 *   manual `tips_locked_at` would be wrong (it would appear to have locked tips
 *   the old system left open); such rows are cleared to NULL and fall back to
 *   the automatic lock at first kickoff.
 *
 *   Notes on the predicate: `tips_locked_at >= NULL` evaluates to NULL, so a
 *   competition with no matches is NOT matched and keeps its value — correct.
 *   The simple source-level `MIN(kickoff_at)` is a deliberate approximation:
 *   for subset competitions it also considers unselected source matches, which
 *   is acceptable on the test-only data this migration ever runs against.
 */
final class Version20260719093102 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'S07: competitions.tips_deadline → tips_locked_at (manual lock) + tip_change_offset_minutes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE competitions ADD tip_change_offset_minutes INT DEFAULT 60 NOT NULL');
        $this->addSql('ALTER TABLE competitions RENAME COLUMN tips_deadline TO tips_locked_at');
        $this->addSql(<<<'SQL'
            UPDATE competitions c
            SET tips_locked_at = NULL
            WHERE tips_locked_at IS NOT NULL
              AND tips_locked_at >= (
                  SELECT MIN(m.kickoff_at)
                  FROM sport_matches m
                  WHERE m.match_source_id = c.match_source_id
                    AND m.deleted_at IS NULL
              )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE competitions DROP tip_change_offset_minutes');
        $this->addSql('ALTER TABLE competitions RENAME COLUMN tips_locked_at TO tips_deadline');
    }
}
