<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260420120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Foundation: users, reset_password_request, sports tables + football seed';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE users (
                id UUID NOT NULL,
                email VARCHAR(180) NOT NULL,
                password VARCHAR(255) DEFAULT NULL,
                nickname VARCHAR(30) NOT NULL,
                roles JSON NOT NULL,
                is_verified BOOLEAN NOT NULL DEFAULT FALSE,
                is_active BOOLEAN NOT NULL DEFAULT TRUE,
                first_name VARCHAR(100) DEFAULT NULL,
                last_name VARCHAR(100) DEFAULT NULL,
                phone VARCHAR(20) DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_users_email ON users (email)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_users_nickname ON users (nickname)');
        $this->addSql("COMMENT ON COLUMN users.id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN users.created_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN users.updated_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN users.deleted_at IS '(DC2Type:datetime_immutable)'");

        $this->addSql(<<<'SQL'
            CREATE TABLE reset_password_request (
                id UUID NOT NULL,
                user_id UUID NOT NULL,
                selector VARCHAR(20) NOT NULL,
                hashed_token VARCHAR(100) NOT NULL,
                requested_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_reset_password_user ON reset_password_request (user_id)');
        $this->addSql("COMMENT ON COLUMN reset_password_request.id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN reset_password_request.user_id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN reset_password_request.requested_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN reset_password_request.expires_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('ALTER TABLE reset_password_request ADD CONSTRAINT FK_reset_password_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql(<<<'SQL'
            CREATE TABLE sports (
                id UUID NOT NULL,
                code VARCHAR(32) NOT NULL,
                name VARCHAR(100) NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_sports_code ON sports (code)');
        $this->addSql("COMMENT ON COLUMN sports.id IS '(DC2Type:uuid)'");

        $this->addSql(<<<'SQL'
            INSERT INTO sports (id, code, name)
            VALUES ('01960000-0000-7000-8000-000000000001', 'football', 'Fotbal')
            ON CONFLICT (id) DO NOTHING
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reset_password_request DROP CONSTRAINT FK_reset_password_user');
        $this->addSql('DROP TABLE reset_password_request');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE sports');
    }
}
