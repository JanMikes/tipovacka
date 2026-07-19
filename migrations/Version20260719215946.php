<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260719215946 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE leaderboard_snapshots (id UUID NOT NULL, day DATE NOT NULL, points INT NOT NULL, rank INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, competition_id UUID NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_B64711567B39D312 ON leaderboard_snapshots (competition_id)');
        $this->addSql('CREATE INDEX IDX_B6471156A76ED395 ON leaderboard_snapshots (user_id)');
        $this->addSql('CREATE INDEX IDX_leaderboard_snapshots_competition_day ON leaderboard_snapshots (competition_id, day)');
        $this->addSql('CREATE UNIQUE INDEX UIDX_leaderboard_snapshots_competition_user_day ON leaderboard_snapshots (competition_id, user_id, day)');
        $this->addSql('ALTER TABLE leaderboard_snapshots ADD CONSTRAINT FK_B64711567B39D312 FOREIGN KEY (competition_id) REFERENCES competitions (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE leaderboard_snapshots ADD CONSTRAINT FK_B6471156A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE leaderboard_snapshots DROP CONSTRAINT FK_B64711567B39D312');
        $this->addSql('ALTER TABLE leaderboard_snapshots DROP CONSTRAINT FK_B6471156A76ED395');
        $this->addSql('DROP TABLE leaderboard_snapshots');
    }
}
