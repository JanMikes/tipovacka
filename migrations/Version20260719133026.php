<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260719133026 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'S09: global competitions (competitions.is_global + entry_fee_credits); retire the join-request flow (drop competition_join_requests).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE competition_join_requests DROP CONSTRAINT fk_c130d0c2e26b496b');
        $this->addSql('ALTER TABLE competition_join_requests DROP CONSTRAINT fk_c130d0c27b39d312');
        $this->addSql('ALTER TABLE competition_join_requests DROP CONSTRAINT fk_c130d0c2a76ed395');
        $this->addSql('DROP TABLE competition_join_requests');
        $this->addSql('ALTER TABLE competitions ADD is_global BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE competitions ADD entry_fee_credits INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE competition_join_requests (decided_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, decision VARCHAR(255) DEFAULT NULL, id UUID NOT NULL, requested_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, decided_by_id UUID DEFAULT NULL, competition_id UUID NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_join_requests_user_decided ON competition_join_requests (user_id, decided_at)');
        $this->addSql('CREATE UNIQUE INDEX uidx_join_requests_pending ON competition_join_requests (competition_id, user_id) WHERE (decided_at IS NULL)');
        $this->addSql('CREATE INDEX idx_c130d0c27b39d312 ON competition_join_requests (competition_id)');
        $this->addSql('CREATE INDEX idx_c130d0c2e26b496b ON competition_join_requests (decided_by_id)');
        $this->addSql('CREATE INDEX idx_c130d0c2a76ed395 ON competition_join_requests (user_id)');
        $this->addSql('CREATE INDEX idx_join_requests_competition_decided ON competition_join_requests (competition_id, decided_at)');
        $this->addSql('ALTER TABLE competition_join_requests ADD CONSTRAINT fk_c130d0c2e26b496b FOREIGN KEY (decided_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE competition_join_requests ADD CONSTRAINT fk_c130d0c27b39d312 FOREIGN KEY (competition_id) REFERENCES competitions (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE competition_join_requests ADD CONSTRAINT fk_c130d0c2a76ed395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE competitions DROP is_global');
        $this->addSql('ALTER TABLE competitions DROP entry_fee_credits');
    }
}
