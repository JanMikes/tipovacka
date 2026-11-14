<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261114134420 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Notification e-mail frequency (notification_settings), per-soutěž mute, and the e-mail queue on notifications.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE notification_settings (email_frequency VARCHAR(255) NOT NULL, digest_hour INT NOT NULL, last_digest_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UIDX_notification_settings_user ON notification_settings (user_id)');
        $this->addSql('ALTER TABLE notification_settings ADD CONSTRAINT FK_B0559860A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE memberships ADD notifications_muted BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE notifications ADD email_queued_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE notifications ADD email_sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_notifications_email_pending ON notifications (email_queued_at) WHERE ((email_queued_at IS NOT NULL) AND (email_sent_at IS NULL))');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE notification_settings DROP CONSTRAINT FK_B0559860A76ED395');
        $this->addSql('DROP TABLE notification_settings');
        $this->addSql('ALTER TABLE memberships DROP notifications_muted');
        $this->addSql('DROP INDEX IDX_notifications_email_pending');
        $this->addSql('ALTER TABLE notifications DROP email_queued_at');
        $this->addSql('ALTER TABLE notifications DROP email_sent_at');
    }
}
