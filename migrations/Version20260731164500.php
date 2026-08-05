<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Data-only: notification urls become host-relative paths.
 *
 * They used to be stored absolute, generated with the router's `default_uri`. In
 * production that fell back to the committed dev default (`http://localhost:8080`)
 * because the box only ever set `APP_URL` — so every notification written from cron
 * (the hourly guess-reminder sweep above all) linked users off wtips.cz to their own
 * localhost. The origin is now the caller's, not the row's; strip the one the rows
 * already carry so the whole existing feed clicks through correctly again.
 */
final class Version20260731164500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Strip the absolute origin from notifications.url (host-relative paths from now on)';
    }

    public function up(Schema $schema): void
    {
        // A url that was nothing but an origin ('https://host') has no path left — send it to the root.
        $this->addSql(<<<'SQL'
            UPDATE notifications
            SET url = COALESCE(NULLIF(regexp_replace(url, '^https?://[^/]+', ''), ''), '/')
            WHERE url ~ '^https?://'
            SQL);
    }

    public function down(Schema $schema): void
    {
        // Irreversible by design: the stripped origin is exactly the wrong data this
        // migration exists to drop, and paths are what the code now writes and reads.
    }
}
