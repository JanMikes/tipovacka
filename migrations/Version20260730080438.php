<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * B15 — a pending competition join that survives the verification mail round trip.
 */
final class Version20260730080438 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the pending competition join (kind + token) to users.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE users ADD pending_join_kind VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD pending_join_token VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE users DROP pending_join_kind');
        $this->addSql('ALTER TABLE users DROP pending_join_token');
    }
}
