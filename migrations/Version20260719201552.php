<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260719201552 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE notification_preferences (in_app BOOLEAN NOT NULL, email BOOLEAN NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, type VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_3CAA95B4A76ED395 ON notification_preferences (user_id)');
        $this->addSql('CREATE UNIQUE INDEX UIDX_notification_preferences_user_type ON notification_preferences (user_id, type)');
        $this->addSql('CREATE TABLE notifications (title VARCHAR(160) NOT NULL, body TEXT NOT NULL, url VARCHAR(512) DEFAULT NULL, payload JSON DEFAULT NULL, dedup_key VARCHAR(191) DEFAULT NULL, in_app_visible BOOLEAN DEFAULT true NOT NULL, read_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, type VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, competition_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_6000B0D3A76ED395 ON notifications (user_id)');
        $this->addSql('CREATE INDEX IDX_6000B0D37B39D312 ON notifications (competition_id)');
        $this->addSql('CREATE INDEX IDX_notifications_user_feed ON notifications (user_id, read_at, created_at)');
        $this->addSql('CREATE INDEX IDX_notifications_dedup ON notifications (user_id, type, dedup_key)');
        $this->addSql('ALTER TABLE notification_preferences ADD CONSTRAINT FK_3CAA95B4A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT FK_6000B0D3A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT FK_6000B0D37B39D312 FOREIGN KEY (competition_id) REFERENCES competitions (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE competitions ADD ended_notified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE notification_preferences DROP CONSTRAINT FK_3CAA95B4A76ED395');
        $this->addSql('ALTER TABLE notifications DROP CONSTRAINT FK_6000B0D3A76ED395');
        $this->addSql('ALTER TABLE notifications DROP CONSTRAINT FK_6000B0D37B39D312');
        $this->addSql('DROP TABLE notification_preferences');
        $this->addSql('DROP TABLE notifications');
        $this->addSql('ALTER TABLE competitions DROP ended_notified_at');
    }
}
