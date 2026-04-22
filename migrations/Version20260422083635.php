<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260422083635 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE group_match_settings (deadline TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, group_id UUID NOT NULL, sport_match_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_A0B2B321FE54D947 ON group_match_settings (group_id)');
        $this->addSql('CREATE INDEX IDX_A0B2B3211C1C536C ON group_match_settings (sport_match_id)');
        $this->addSql('CREATE UNIQUE INDEX UIDX_group_match_settings_group_match ON group_match_settings (group_id, sport_match_id)');
        $this->addSql('ALTER TABLE group_match_settings ADD CONSTRAINT FK_A0B2B321FE54D947 FOREIGN KEY (group_id) REFERENCES user_groups (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE group_match_settings ADD CONSTRAINT FK_A0B2B3211C1C536C FOREIGN KEY (sport_match_id) REFERENCES sport_matches (id) NOT DEFERRABLE');
        // Add the new boolean with a temporary DEFAULT so the NOT NULL constraint
        // succeeds against existing rows, then drop the default to match the
        // entity (which has no DB-side default).
        $this->addSql('ALTER TABLE user_groups ADD hide_others_tips_before_deadline BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE user_groups ALTER hide_others_tips_before_deadline DROP DEFAULT');
        $this->addSql('ALTER TABLE user_groups ADD tips_deadline TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE group_match_settings DROP CONSTRAINT FK_A0B2B321FE54D947');
        $this->addSql('ALTER TABLE group_match_settings DROP CONSTRAINT FK_A0B2B3211C1C536C');
        $this->addSql('DROP TABLE group_match_settings');
        $this->addSql('ALTER TABLE user_groups DROP hide_others_tips_before_deadline');
        $this->addSql('ALTER TABLE user_groups DROP tips_deadline');
    }
}
