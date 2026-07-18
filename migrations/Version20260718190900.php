<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260718190900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE credit_transactions ADD boost_type VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE credit_transactions ADD competition_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE credit_transactions ADD related_user_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE credit_transactions ADD CONSTRAINT FK_CC5D00067B39D312 FOREIGN KEY (competition_id) REFERENCES competitions (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE credit_transactions ADD CONSTRAINT FK_CC5D000698771930 FOREIGN KEY (related_user_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_CC5D00067B39D312 ON credit_transactions (competition_id)');
        $this->addSql('CREATE INDEX IDX_CC5D000698771930 ON credit_transactions (related_user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE credit_transactions DROP CONSTRAINT FK_CC5D00067B39D312');
        $this->addSql('ALTER TABLE credit_transactions DROP CONSTRAINT FK_CC5D000698771930');
        $this->addSql('DROP INDEX IDX_CC5D00067B39D312');
        $this->addSql('DROP INDEX IDX_CC5D000698771930');
        $this->addSql('ALTER TABLE credit_transactions DROP boost_type');
        $this->addSql('ALTER TABLE credit_transactions DROP competition_id');
        $this->addSql('ALTER TABLE credit_transactions DROP related_user_id');
    }
}
