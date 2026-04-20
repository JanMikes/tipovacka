<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260420145555 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE group_invitations (accepted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, email VARCHAR(180) NOT NULL, token VARCHAR(64) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, group_id UUID NOT NULL, inviter_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_69F0F6FB79F4F04 ON group_invitations (inviter_id)');
        $this->addSql('CREATE INDEX IDX_group_invitations_group ON group_invitations (group_id)');
        $this->addSql('CREATE UNIQUE INDEX UIDX_group_invitations_token ON group_invitations (token)');
        $this->addSql('CREATE TABLE group_join_requests (decided_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, decision VARCHAR(255) DEFAULT NULL, id UUID NOT NULL, requested_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, decided_by_id UUID DEFAULT NULL, group_id UUID NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_690CDC20E26B496B ON group_join_requests (decided_by_id)');
        $this->addSql('CREATE INDEX IDX_690CDC20FE54D947 ON group_join_requests (group_id)');
        $this->addSql('CREATE INDEX IDX_690CDC20A76ED395 ON group_join_requests (user_id)');
        $this->addSql('CREATE INDEX IDX_join_requests_group_decided ON group_join_requests (group_id, decided_at)');
        $this->addSql('CREATE INDEX IDX_join_requests_user_decided ON group_join_requests (user_id, decided_at)');
        $this->addSql('CREATE UNIQUE INDEX UIDX_join_requests_pending ON group_join_requests (group_id, user_id) WHERE (decided_at IS NULL)');
        $this->addSql('ALTER TABLE group_invitations ADD CONSTRAINT FK_69F0F6FFE54D947 FOREIGN KEY (group_id) REFERENCES user_groups (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE group_invitations ADD CONSTRAINT FK_69F0F6FB79F4F04 FOREIGN KEY (inviter_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE group_join_requests ADD CONSTRAINT FK_690CDC20E26B496B FOREIGN KEY (decided_by_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE group_join_requests ADD CONSTRAINT FK_690CDC20FE54D947 FOREIGN KEY (group_id) REFERENCES user_groups (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE group_join_requests ADD CONSTRAINT FK_690CDC20A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE group_invitations DROP CONSTRAINT FK_69F0F6FFE54D947');
        $this->addSql('ALTER TABLE group_invitations DROP CONSTRAINT FK_69F0F6FB79F4F04');
        $this->addSql('ALTER TABLE group_join_requests DROP CONSTRAINT FK_690CDC20E26B496B');
        $this->addSql('ALTER TABLE group_join_requests DROP CONSTRAINT FK_690CDC20FE54D947');
        $this->addSql('ALTER TABLE group_join_requests DROP CONSTRAINT FK_690CDC20A76ED395');
        $this->addSql('DROP TABLE group_invitations');
        $this->addSql('DROP TABLE group_join_requests');
    }
}
