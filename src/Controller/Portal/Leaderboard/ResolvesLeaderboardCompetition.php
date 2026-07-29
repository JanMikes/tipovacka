<?php

declare(strict_types=1);

namespace App\Controller\Portal\Leaderboard;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * The žebříček sub-pages hang off `/zebricek/…` and scope themselves with
 * `?soutez={uuid}` — the same parameter the page itself uses, because the
 * `<twig:SoutezSwitcher>` is a plain GET form and can only append a query string.
 *
 * A missing or malformed id is a 404, never a silent fallback: unlike the page,
 * these are always reached from a link that already knows which soutěž it means.
 */
trait ResolvesLeaderboardCompetition
{
    private static function competitionIdFromRequest(Request $request): Uuid
    {
        $id = $request->query->getString('soutez');

        if ('' === $id || !Uuid::isValid($id)) {
            throw new NotFoundHttpException('Chybí platný parametr „soutez".');
        }

        return Uuid::fromString($id);
    }
}
