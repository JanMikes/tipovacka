<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260719175630 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE boost_purchases (refunded_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, type VARCHAR(255) NOT NULL, price_paid INT NOT NULL, purchased_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, competition_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_D7FC49C1A76ED395 ON boost_purchases (user_id)');
        $this->addSql('CREATE INDEX IDX_D7FC49C17B39D312 ON boost_purchases (competition_id)');
        $this->addSql('CREATE UNIQUE INDEX UIDX_boost_purchases_active ON boost_purchases (user_id, competition_id, type) WHERE (refunded_at IS NULL)');
        $this->addSql('ALTER TABLE boost_purchases ADD CONSTRAINT FK_D7FC49C1A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE boost_purchases ADD CONSTRAINT FK_D7FC49C17B39D312 FOREIGN KEY (competition_id) REFERENCES competitions (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE boost_purchases DROP CONSTRAINT FK_D7FC49C1A76ED395');
        $this->addSql('ALTER TABLE boost_purchases DROP CONSTRAINT FK_D7FC49C17B39D312');
        $this->addSql('DROP TABLE boost_purchases');
    }
}
