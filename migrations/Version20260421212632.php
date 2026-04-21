<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260421212632 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow nullable email and nickname on users (anonymous members) with partial unique indexes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_1483a5e9e7927c74');
        $this->addSql('DROP INDEX uniq_1483a5e9a188fe64');
        $this->addSql('ALTER TABLE users ALTER email DROP NOT NULL');
        $this->addSql('ALTER TABLE users ALTER nickname DROP NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX users_email_unique ON users (email) WHERE (email IS NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX users_nickname_unique ON users (nickname) WHERE (nickname IS NOT NULL)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX users_email_unique');
        $this->addSql('DROP INDEX users_nickname_unique');
        $this->addSql('ALTER TABLE users ALTER email SET NOT NULL');
        $this->addSql('ALTER TABLE users ALTER nickname SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_1483a5e9e7927c74 ON users (email)');
        $this->addSql('CREATE UNIQUE INDEX uniq_1483a5e9a188fe64 ON users (nickname)');
    }
}
