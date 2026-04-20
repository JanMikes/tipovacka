<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260420170534 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE leaderboard_tie_resolutions (rank INT NOT NULL, id UUID NOT NULL, resolved_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, group_id UUID NOT NULL, user_id UUID NOT NULL, resolved_by_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_BFC3191FE54D947 ON leaderboard_tie_resolutions (group_id)');
        $this->addSql('CREATE INDEX IDX_BFC3191A76ED395 ON leaderboard_tie_resolutions (user_id)');
        $this->addSql('CREATE INDEX IDX_BFC31916713A32B ON leaderboard_tie_resolutions (resolved_by_id)');
        $this->addSql('CREATE UNIQUE INDEX UIDX_tie_resolution ON leaderboard_tie_resolutions (group_id, user_id)');
        $this->addSql('ALTER TABLE leaderboard_tie_resolutions ADD CONSTRAINT FK_BFC3191FE54D947 FOREIGN KEY (group_id) REFERENCES user_groups (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE leaderboard_tie_resolutions ADD CONSTRAINT FK_BFC3191A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE leaderboard_tie_resolutions ADD CONSTRAINT FK_BFC31916713A32B FOREIGN KEY (resolved_by_id) REFERENCES users (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE leaderboard_tie_resolutions DROP CONSTRAINT FK_BFC3191FE54D947');
        $this->addSql('ALTER TABLE leaderboard_tie_resolutions DROP CONSTRAINT FK_BFC3191A76ED395');
        $this->addSql('ALTER TABLE leaderboard_tie_resolutions DROP CONSTRAINT FK_BFC31916713A32B');
        $this->addSql('DROP TABLE leaderboard_tie_resolutions');
    }
}
