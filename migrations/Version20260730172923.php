<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260730172923 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'competition_match_settings: opens_at + opening_note („tipování otevřeno od"), deadline becomes nullable (an opening-only row carries no deadline)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE competition_match_settings ADD opens_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE competition_match_settings ADD opening_note TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE competition_match_settings ALTER deadline DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE competition_match_settings DROP opens_at');
        $this->addSql('ALTER TABLE competition_match_settings DROP opening_note');
        $this->addSql('ALTER TABLE competition_match_settings ALTER deadline SET NOT NULL');
    }
}
