<?php

declare(strict_types=1);

namespace App\Controller\Admin\Competition;

use App\Command\SponsorCompetitionPremium\SponsorCompetitionPremiumCommand;
use App\Entity\User;
use App\Repository\CompetitionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

/**
 * „Prémium na nás" — an admin gives a partička premium at our expense, or takes
 * that back. Reachable only under the ^/admin firewall; the handler re-checks
 * ROLE_ADMIN because the same command also runs from the console.
 */
#[Route(
    '/admin/souteze/{id}/sponzorovat-premium',
    name: 'admin_competition_sponsor_premium',
    requirements: ['id' => Requirement::UUID],
    methods: ['POST'],
)]
final class SponsorCompetitionPremiumController extends AbstractController
{
    public function __construct(
        private readonly CompetitionRepository $competitionRepository,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        $competition = $this->competitionRepository->get(Uuid::fromString($id));

        if (!$this->isCsrfTokenValid('admin_competition_sponsor_premium_'.$competition->id->toRfc4122(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Neplatný bezpečnostní token. Zkuste to znovu.');

            return $this->redirectToRoute('admin_competition_list');
        }

        /** @var User $admin */
        $admin = $this->getUser();

        $sponsored = !$competition->isPremiumSponsored;

        $this->commandBus->dispatch(new SponsorCompetitionPremiumCommand(
            competitionId: $competition->id,
            grantedById: $admin->id,
            sponsored: $sponsored,
        ));

        $this->addFlash('success', $sponsored
            ? sprintf('Soutěž „%s" má prémium na nás — nikomu se nic nestrhne.', $competition->name)
            : sprintf('Sponzoring soutěže „%s" ukončen. Prémium zůstává, další hráče už platí organizátor.', $competition->name));

        return $this->redirectToRoute('admin_competition_list');
    }
}
