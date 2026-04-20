<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260420143443 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Consolidated schema: users, reset_password_request, sports, tournaments, user_groups, memberships + football seed';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE memberships (left_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, joined_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, group_id UUID NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_865A4776FE54D947 ON memberships (group_id)');
        $this->addSql('CREATE INDEX IDX_865A4776A76ED395 ON memberships (user_id)');
        $this->addSql('CREATE INDEX IDX_memberships_group_active ON memberships (group_id, left_at)');
        $this->addSql('CREATE INDEX IDX_memberships_user_active ON memberships (user_id, left_at)');
        $this->addSql('CREATE UNIQUE INDEX UIDX_memberships_active ON memberships (group_id, user_id) WHERE (left_at IS NULL)');
        $this->addSql('CREATE TABLE reset_password_request (id UUID NOT NULL, selector VARCHAR(20) NOT NULL, hashed_token VARCHAR(100) NOT NULL, requested_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_7CE748AA76ED395 ON reset_password_request (user_id)');
        $this->addSql('CREATE TABLE sports (id UUID NOT NULL, code VARCHAR(32) NOT NULL, name VARCHAR(100) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_73C9F91C77153098 ON sports (code)');
        $this->addSql('CREATE TABLE tournaments (name VARCHAR(160) NOT NULL, description TEXT DEFAULT NULL, start_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, end_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, finished_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, visibility VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, sport_id UUID NOT NULL, owner_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_E4BCFAC3AC78BCF8 ON tournaments (sport_id)');
        $this->addSql('CREATE INDEX IDX_E4BCFAC37E3C61F9 ON tournaments (owner_id)');
        $this->addSql('CREATE INDEX IDX_tournaments_public_active ON tournaments (visibility, finished_at, deleted_at)');
        $this->addSql('CREATE INDEX IDX_tournaments_owner_visibility ON tournaments (owner_id, visibility, deleted_at)');
        $this->addSql('CREATE TABLE user_groups (name VARCHAR(160) NOT NULL, description TEXT DEFAULT NULL, pin VARCHAR(8) DEFAULT NULL, shareable_link_token VARCHAR(48) DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, tournament_id UUID NOT NULL, owner_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_953F224D33D1A3E7 ON user_groups (tournament_id)');
        $this->addSql('CREATE INDEX IDX_953F224D7E3C61F9 ON user_groups (owner_id)');
        $this->addSql('CREATE INDEX IDX_user_groups_tournament ON user_groups (tournament_id, deleted_at)');
        $this->addSql('CREATE INDEX IDX_user_groups_owner ON user_groups (owner_id, deleted_at)');
        $this->addSql('CREATE UNIQUE INDEX UIDX_user_groups_pin ON user_groups (pin) WHERE (pin IS NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UIDX_user_groups_shareable_link_token ON user_groups (shareable_link_token) WHERE (shareable_link_token IS NOT NULL)');
        $this->addSql('CREATE TABLE users (roles JSON NOT NULL, is_verified BOOLEAN NOT NULL, is_active BOOLEAN NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, first_name VARCHAR(100) DEFAULT NULL, last_name VARCHAR(100) DEFAULT NULL, phone VARCHAR(20) DEFAULT NULL, id UUID NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) DEFAULT NULL, nickname VARCHAR(30) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9A188FE64 ON users (nickname)');
        $this->addSql('ALTER TABLE memberships ADD CONSTRAINT FK_865A4776FE54D947 FOREIGN KEY (group_id) REFERENCES user_groups (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE memberships ADD CONSTRAINT FK_865A4776A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE reset_password_request ADD CONSTRAINT FK_7CE748AA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE tournaments ADD CONSTRAINT FK_E4BCFAC3AC78BCF8 FOREIGN KEY (sport_id) REFERENCES sports (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE tournaments ADD CONSTRAINT FK_E4BCFAC37E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE user_groups ADD CONSTRAINT FK_953F224D33D1A3E7 FOREIGN KEY (tournament_id) REFERENCES tournaments (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE user_groups ADD CONSTRAINT FK_953F224D7E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) NOT DEFERRABLE');

        // Seed football Sport row (mirrors the seed from the previous Stage 0 migration).
        $this->addSql(<<<'SQL'
            INSERT INTO sports (id, code, name)
            VALUES ('01960000-0000-7000-8000-000000000001', 'football', 'Fotbal')
            ON CONFLICT (id) DO NOTHING
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE memberships DROP CONSTRAINT FK_865A4776FE54D947');
        $this->addSql('ALTER TABLE memberships DROP CONSTRAINT FK_865A4776A76ED395');
        $this->addSql('ALTER TABLE reset_password_request DROP CONSTRAINT FK_7CE748AA76ED395');
        $this->addSql('ALTER TABLE tournaments DROP CONSTRAINT FK_E4BCFAC3AC78BCF8');
        $this->addSql('ALTER TABLE tournaments DROP CONSTRAINT FK_E4BCFAC37E3C61F9');
        $this->addSql('ALTER TABLE user_groups DROP CONSTRAINT FK_953F224D33D1A3E7');
        $this->addSql('ALTER TABLE user_groups DROP CONSTRAINT FK_953F224D7E3C61F9');
        $this->addSql('DROP TABLE memberships');
        $this->addSql('DROP TABLE reset_password_request');
        $this->addSql('DROP TABLE sports');
        $this->addSql('DROP TABLE tournaments');
        $this->addSql('DROP TABLE user_groups');
        $this->addSql('DROP TABLE users');
    }
}
