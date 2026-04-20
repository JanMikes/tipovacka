<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260420161114 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE guesses (home_score INT NOT NULL, away_score INT NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, submitted_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, user_id UUID NOT NULL, sport_match_id UUID NOT NULL, group_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_27FBAF45A76ED395 ON guesses (user_id)');
        $this->addSql('CREATE INDEX IDX_27FBAF451C1C536C ON guesses (sport_match_id)');
        $this->addSql('CREATE INDEX IDX_27FBAF45FE54D947 ON guesses (group_id)');
        $this->addSql('CREATE INDEX IDX_guesses_match_group ON guesses (sport_match_id, group_id, deleted_at)');
        $this->addSql('CREATE INDEX IDX_guesses_user_group ON guesses (user_id, group_id, deleted_at)');
        $this->addSql('CREATE UNIQUE INDEX UIDX_guesses_active ON guesses (user_id, sport_match_id, group_id) WHERE (deleted_at IS NULL)');
        $this->addSql('ALTER TABLE guesses ADD CONSTRAINT FK_27FBAF45A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE guesses ADD CONSTRAINT FK_27FBAF451C1C536C FOREIGN KEY (sport_match_id) REFERENCES sport_matches (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE guesses ADD CONSTRAINT FK_27FBAF45FE54D947 FOREIGN KEY (group_id) REFERENCES user_groups (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE guesses DROP CONSTRAINT FK_27FBAF45A76ED395');
        $this->addSql('ALTER TABLE guesses DROP CONSTRAINT FK_27FBAF451C1C536C');
        $this->addSql('ALTER TABLE guesses DROP CONSTRAINT FK_27FBAF45FE54D947');
        $this->addSql('DROP TABLE guesses');
    }
}
