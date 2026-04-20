<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260420203831 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE guesses ADD submitted_by_user_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE guesses ADD CONSTRAINT FK_27FBAF45DDBFAD6E FOREIGN KEY (submitted_by_user_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_27FBAF45DDBFAD6E ON guesses (submitted_by_user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE guesses DROP CONSTRAINT FK_27FBAF45DDBFAD6E');
        $this->addSql('DROP INDEX IDX_27FBAF45DDBFAD6E');
        $this->addSql('ALTER TABLE guesses DROP submitted_by_user_id');
    }
}
