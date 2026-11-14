<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261114135826 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'E-mail audit log (email_log): one row per attempt to send a mail.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE email_log (id UUID NOT NULL, recipient VARCHAR(500) NOT NULL, subject TEXT NOT NULL, template VARCHAR(120) DEFAULT NULL, status VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, body TEXT DEFAULT NULL, error TEXT DEFAULT NULL, message_id VARCHAR(255) DEFAULT NULL, user_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_6FB4883A76ED395 ON email_log (user_id)');
        $this->addSql('CREATE INDEX IDX_email_log_created ON email_log (created_at)');
        $this->addSql('CREATE INDEX IDX_email_log_recipient ON email_log (recipient)');
        $this->addSql('ALTER TABLE email_log ADD CONSTRAINT FK_6FB4883A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE email_log DROP CONSTRAINT FK_6FB4883A76ED395');
        $this->addSql('DROP TABLE email_log');
    }
}
