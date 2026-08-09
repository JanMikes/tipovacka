<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * „Premium on us": when an admin granted a private group premium at our expense,
 * so nobody is ever charged for it. NULL = ordinary premium, billed per player
 * to the organizer — which is every existing row, hence a plain nullable add
 * with no backfill.
 */
final class Version20260809124319 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Competition.premiumSponsoredAt — admin-granted premium billed to nobody';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE competitions ADD premium_sponsored_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE competitions DROP premium_sponsored_at');
    }
}
