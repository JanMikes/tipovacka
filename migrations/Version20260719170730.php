<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260719170730 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE competition_premium_charges (status VARCHAR(255) NOT NULL, amount INT NOT NULL, charged_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, refunded_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, competition_id UUID NOT NULL, member_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_1992AC347B39D312 ON competition_premium_charges (competition_id)');
        $this->addSql('CREATE INDEX IDX_1992AC347597D3FE ON competition_premium_charges (member_id)');
        $this->addSql('CREATE UNIQUE INDEX UIDX_premium_charges_competition_member ON competition_premium_charges (competition_id, member_id)');
        $this->addSql('ALTER TABLE competition_premium_charges ADD CONSTRAINT FK_1992AC347B39D312 FOREIGN KEY (competition_id) REFERENCES competitions (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE competition_premium_charges ADD CONSTRAINT FK_1992AC347597D3FE FOREIGN KEY (member_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE competitions ADD premium_reconciled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE competitions ADD premium_show_distribution BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE competitions ADD premium_show_others_tips BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE competitions ADD premium_allow_tip_changes BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE competition_premium_charges DROP CONSTRAINT FK_1992AC347B39D312');
        $this->addSql('ALTER TABLE competition_premium_charges DROP CONSTRAINT FK_1992AC347597D3FE');
        $this->addSql('DROP TABLE competition_premium_charges');
        $this->addSql('ALTER TABLE competitions DROP premium_reconciled_at');
        $this->addSql('ALTER TABLE competitions DROP premium_show_distribution');
        $this->addSql('ALTER TABLE competitions DROP premium_show_others_tips');
        $this->addSql('ALTER TABLE competitions DROP premium_allow_tip_changes');
    }
}
