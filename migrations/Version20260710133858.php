<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260710133858 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE credit_purchases (status VARCHAR(255) NOT NULL, stripe_payment_intent_id VARCHAR(255) DEFAULT NULL, stripe_invoice_id VARCHAR(255) DEFAULT NULL, stripe_invoice_url VARCHAR(255) DEFAULT NULL, stripe_invoice_pdf_url VARCHAR(255) DEFAULT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, credits INT NOT NULL, amount_total INT NOT NULL, currency VARCHAR(3) NOT NULL, stripe_checkout_session_id VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_6BAEC7ECA76ED395 ON credit_purchases (user_id)');
        $this->addSql('CREATE INDEX IDX_credit_purchases_user_created ON credit_purchases (user_id, created_at)');
        $this->addSql('CREATE UNIQUE INDEX UIDX_credit_purchases_session ON credit_purchases (stripe_checkout_session_id)');
        $this->addSql('CREATE TABLE credit_transactions (id UUID NOT NULL, amount INT NOT NULL, balance_after INT NOT NULL, type VARCHAR(255) NOT NULL, note TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, wallet_id UUID NOT NULL, performed_by_id UUID DEFAULT NULL, purchase_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_CC5D0006712520F3 ON credit_transactions (wallet_id)');
        $this->addSql('CREATE INDEX IDX_CC5D00062E65C292 ON credit_transactions (performed_by_id)');
        $this->addSql('CREATE INDEX IDX_CC5D0006558FBEB9 ON credit_transactions (purchase_id)');
        $this->addSql('CREATE INDEX IDX_credit_transactions_wallet_created ON credit_transactions (wallet_id, created_at)');
        $this->addSql('CREATE TABLE credit_wallets (balance INT NOT NULL, stripe_customer_id VARCHAR(255) DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_ACFECD43A76ED395 ON credit_wallets (user_id)');
        $this->addSql('ALTER TABLE credit_purchases ADD CONSTRAINT FK_6BAEC7ECA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE credit_transactions ADD CONSTRAINT FK_CC5D0006712520F3 FOREIGN KEY (wallet_id) REFERENCES credit_wallets (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE credit_transactions ADD CONSTRAINT FK_CC5D00062E65C292 FOREIGN KEY (performed_by_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE credit_transactions ADD CONSTRAINT FK_CC5D0006558FBEB9 FOREIGN KEY (purchase_id) REFERENCES credit_purchases (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE credit_wallets ADD CONSTRAINT FK_ACFECD43A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE credit_purchases DROP CONSTRAINT FK_6BAEC7ECA76ED395');
        $this->addSql('ALTER TABLE credit_transactions DROP CONSTRAINT FK_CC5D0006712520F3');
        $this->addSql('ALTER TABLE credit_transactions DROP CONSTRAINT FK_CC5D00062E65C292');
        $this->addSql('ALTER TABLE credit_transactions DROP CONSTRAINT FK_CC5D0006558FBEB9');
        $this->addSql('ALTER TABLE credit_wallets DROP CONSTRAINT FK_ACFECD43A76ED395');
        $this->addSql('DROP TABLE credit_purchases');
        $this->addSql('DROP TABLE credit_transactions');
        $this->addSql('DROP TABLE credit_wallets');
    }
}
