<?php

declare(strict_types=1);

namespace App\Controller;

use App\Query\ListMyCompetitions\CompetitionListItem;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Dev/admin-only reference styleguide for DEFERRED (🔮) design-system elements.
 *
 * These elements appear in the Wtips design system but their feature is not built
 * (premium/contribution tiers, scorers editor + „Trefený střelec", notifications
 * bell+feed, Δ rank-change column). They are rendered here as VISUAL-ONLY, INERT
 * references labeled „Připravujeme / reference" — never wired into a production flow.
 *
 * `/_design` is not under an existing `access_control` prefix, so the in-controller
 * `denyAccessUnlessGranted('ROLE_ADMIN')` is the gate: admin → 200, logged-in
 * non-admin → 403, anonymous → redirect to login via the firewall entry point.
 */
#[Route('/_design', name: 'app_design_styleguide', methods: ['GET'])]
final class DesignStyleguideController extends AbstractController
{
    public function __invoke(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('design/styleguide.html.twig', [
            'switcher_competitions' => $this->sampleCompetitions(),
        ]);
    }

    /**
     * Hand-made sample rows for the <twig:SoutezSwitcher> section — the styleguide has no
     * backend, so the picker is fed literals instead of ListMyCompetitions. Two live and one
     * finished soutěž, which is exactly what it takes to see both optgroups.
     *
     * @return list<CompetitionListItem>
     */
    private function sampleCompetitions(): array
    {
        $joinedAt = new \DateTimeImmutable('2026-01-15 09:00:00', new \DateTimeZone('UTC'));

        return [
            new CompetitionListItem(
                competitionId: Uuid::fromString('01930000-0000-7000-8000-000000000001'),
                competitionName: 'Firemní MS 2026',
                matchSourceId: Uuid::fromString('01930000-0000-7000-8000-0000000000a1'),
                matchSourceName: 'MS ve fotbale 2026',
                matchSourceIsCompleted: false,
                ownerNickname: 'admin',
                isOwner: true,
                joinedAt: $joinedAt,
                matchSourceStartAt: new \DateTimeImmutable('2026-06-02 16:00:00', new \DateTimeZone('UTC')),
                matchSourceEndAt: new \DateTimeImmutable('2026-06-11 20:00:00', new \DateTimeZone('UTC')),
            ),
            new CompetitionListItem(
                competitionId: Uuid::fromString('01930000-0000-7000-8000-000000000002'),
                competitionName: 'Kámoši u piva',
                matchSourceId: Uuid::fromString('01930000-0000-7000-8000-0000000000a2'),
                matchSourceName: 'Chodská liga — jaro',
                matchSourceIsCompleted: false,
                ownerNickname: 'tipovac',
                isOwner: false,
                joinedAt: $joinedAt,
                matchSourceStartAt: new \DateTimeImmutable('2026-03-01 12:00:00', new \DateTimeZone('UTC')),
                matchSourceEndAt: null,
            ),
            new CompetitionListItem(
                competitionId: Uuid::fromString('01930000-0000-7000-8000-000000000003'),
                competitionName: 'VŠCHT tipovačka',
                matchSourceId: Uuid::fromString('01930000-0000-7000-8000-0000000000a3'),
                matchSourceName: 'EURO 2024',
                matchSourceIsCompleted: true,
                ownerNickname: 'katka',
                isOwner: false,
                joinedAt: $joinedAt,
                matchSourceStartAt: new \DateTimeImmutable('2024-06-14 19:00:00', new \DateTimeZone('UTC')),
                matchSourceEndAt: new \DateTimeImmutable('2024-07-14 19:00:00', new \DateTimeZone('UTC')),
            ),
        ];
    }
}
