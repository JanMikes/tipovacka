<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * S04 — rules move from the match source to the competition.
 *
 * Creates `competition_rule_configurations`, copies every source's rule rows to
 * EVERY competition of that source (competitions.match_source_id join), then drops
 * `match_source_rule_configurations`. INSERT runs before the DROP inside the one
 * migration transaction, so a failure leaves the old table intact.
 *
 * Note on ids: the app generates UUIDv7 via ProvideIdentity, but a data migration
 * has no app-side id source — plain `gen_random_uuid()` (v4) is acceptable here;
 * these rows are never ordered or ranged by id.
 */
final class Version20260718193331 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rules per competition: create competition_rule_configurations, copy source rule rows to every competition, drop match_source_rule_configurations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE competition_rule_configurations (enabled BOOLEAN NOT NULL, points INT NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, rule_identifier VARCHAR(64) NOT NULL, competition_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_7D7B46D37B39D312 ON competition_rule_configurations (competition_id)');
        $this->addSql('CREATE UNIQUE INDEX UIDX_rule_config_competition_rule ON competition_rule_configurations (competition_id, rule_identifier)');
        $this->addSql('ALTER TABLE competition_rule_configurations ADD CONSTRAINT FK_7D7B46D37B39D312 FOREIGN KEY (competition_id) REFERENCES competitions (id) NOT DEFERRABLE');
        $this->addSql(<<<'SQL'
            INSERT INTO competition_rule_configurations (id, competition_id, rule_identifier, enabled, points, updated_at)
            SELECT gen_random_uuid(), c.id, msrc.rule_identifier, msrc.enabled, msrc.points, msrc.updated_at
            FROM match_source_rule_configurations msrc
            JOIN competitions c ON c.match_source_id = msrc.match_source_id
            SQL);
        $this->addSql('ALTER TABLE match_source_rule_configurations DROP CONSTRAINT fk_1a48a5448c8d50ca');
        $this->addSql('DROP TABLE match_source_rule_configurations');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE match_source_rule_configurations (enabled BOOLEAN NOT NULL, points INT NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, rule_identifier VARCHAR(64) NOT NULL, match_source_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_1a48a5448c8d50ca ON match_source_rule_configurations (match_source_id)');
        $this->addSql('CREATE UNIQUE INDEX uidx_rule_config_match_source_rule ON match_source_rule_configurations (match_source_id, rule_identifier)');
        $this->addSql('ALTER TABLE match_source_rule_configurations ADD CONSTRAINT fk_1a48a5448c8d50ca FOREIGN KEY (match_source_id) REFERENCES match_sources (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE competition_rule_configurations DROP CONSTRAINT FK_7D7B46D37B39D312');
        $this->addSql('DROP TABLE competition_rule_configurations');
    }
}
