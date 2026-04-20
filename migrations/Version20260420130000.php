<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260420130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tournament domain: tournaments table with FKs to sports and users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE tournaments (
                id UUID NOT NULL,
                sport_id UUID NOT NULL,
                owner_id UUID NOT NULL,
                visibility VARCHAR(255) NOT NULL,
                name VARCHAR(160) NOT NULL,
                description TEXT DEFAULT NULL,
                start_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                end_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                finished_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_tournaments_sport ON tournaments (sport_id)');
        $this->addSql('CREATE INDEX IDX_tournaments_owner ON tournaments (owner_id)');
        $this->addSql('CREATE INDEX IDX_tournaments_public_active ON tournaments (visibility, finished_at, deleted_at)');
        $this->addSql('CREATE INDEX IDX_tournaments_owner_visibility ON tournaments (owner_id, visibility, deleted_at)');
        $this->addSql("COMMENT ON COLUMN tournaments.id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN tournaments.sport_id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN tournaments.owner_id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN tournaments.start_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN tournaments.end_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN tournaments.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN tournaments.updated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN tournaments.finished_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN tournaments.deleted_at IS '(DC2Type:datetime_immutable)'");

        $this->addSql('ALTER TABLE tournaments ADD CONSTRAINT FK_tournaments_sport FOREIGN KEY (sport_id) REFERENCES sports (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE tournaments ADD CONSTRAINT FK_tournaments_owner FOREIGN KEY (owner_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tournaments DROP CONSTRAINT FK_tournaments_sport');
        $this->addSql('ALTER TABLE tournaments DROP CONSTRAINT FK_tournaments_owner');
        $this->addSql('DROP TABLE tournaments');
    }
}
