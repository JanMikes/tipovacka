<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260420140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Group domain: user_groups + memberships tables with partial unique indices';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE user_groups (
                id UUID NOT NULL,
                tournament_id UUID NOT NULL,
                owner_id UUID NOT NULL,
                name VARCHAR(160) NOT NULL,
                description TEXT DEFAULT NULL,
                pin VARCHAR(8) DEFAULT NULL,
                shareable_link_token VARCHAR(48) DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_user_groups_tournament ON user_groups (tournament_id, deleted_at)');
        $this->addSql('CREATE INDEX IDX_user_groups_owner ON user_groups (owner_id, deleted_at)');
        $this->addSql('CREATE UNIQUE INDEX UIDX_user_groups_pin ON user_groups (pin) WHERE pin IS NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UIDX_user_groups_shareable_link_token ON user_groups (shareable_link_token) WHERE shareable_link_token IS NOT NULL');
        $this->addSql("COMMENT ON COLUMN user_groups.id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN user_groups.tournament_id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN user_groups.owner_id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN user_groups.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN user_groups.updated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN user_groups.deleted_at IS '(DC2Type:datetime_immutable)'");

        $this->addSql('ALTER TABLE user_groups ADD CONSTRAINT FK_user_groups_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_groups ADD CONSTRAINT FK_user_groups_owner FOREIGN KEY (owner_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql(<<<'SQL'
            CREATE TABLE memberships (
                id UUID NOT NULL,
                group_id UUID NOT NULL,
                user_id UUID NOT NULL,
                joined_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                left_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_memberships_group_active ON memberships (group_id, left_at)');
        $this->addSql('CREATE INDEX IDX_memberships_user_active ON memberships (user_id, left_at)');
        $this->addSql('CREATE UNIQUE INDEX UIDX_memberships_active ON memberships (group_id, user_id) WHERE left_at IS NULL');
        $this->addSql("COMMENT ON COLUMN memberships.id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN memberships.group_id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN memberships.user_id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN memberships.joined_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN memberships.left_at IS '(DC2Type:datetime_immutable)'");

        $this->addSql('ALTER TABLE memberships ADD CONSTRAINT FK_memberships_group FOREIGN KEY (group_id) REFERENCES user_groups (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE memberships ADD CONSTRAINT FK_memberships_user FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE memberships DROP CONSTRAINT FK_memberships_group');
        $this->addSql('ALTER TABLE memberships DROP CONSTRAINT FK_memberships_user');
        $this->addSql('ALTER TABLE user_groups DROP CONSTRAINT FK_user_groups_tournament');
        $this->addSql('ALTER TABLE user_groups DROP CONSTRAINT FK_user_groups_owner');
        $this->addSql('DROP TABLE memberships');
        $this->addSql('DROP TABLE user_groups');
    }
}
