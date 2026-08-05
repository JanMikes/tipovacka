<?php

declare(strict_types=1);

namespace App\Service\Competition;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

/**
 * „Přišel jsem sem ze soutěže" — the `?soutez={uuid}` hop that sends the match
 * forms (new / edit / delete) back to „Zápasy soutěže" instead of the zdroj page
 * they normally belong to. An organizer filling in their own rozpis stays on the
 * screen they started from.
 *
 * It carries a competition UUID, never a URL: the target route is fixed here, so
 * a crafted parameter can redirect nowhere else, and the scope page runs its own
 * voter anyway.
 */
final readonly class ScopeReturn
{
    public const string PARAM = 'soutez';

    /**
     * The competition to return to (RFC 4122), or null when the visitor came the
     * usual way. Read from both bags: the match forms carry it in the action URL,
     * the delete button as a hidden field.
     */
    public function competitionId(Request $request): ?string
    {
        $raw = $request->request->get(self::PARAM) ?? $request->query->get(self::PARAM);

        return \is_string($raw) && Uuid::isValid($raw) ? $raw : null;
    }
}
