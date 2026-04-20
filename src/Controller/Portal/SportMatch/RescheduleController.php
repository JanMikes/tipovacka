<?php

declare(strict_types=1);

namespace App\Controller\Portal\SportMatch;

use App\Command\RescheduleSportMatch\RescheduleSportMatchCommand;
use App\Entity\User;
use App\Repository\SportMatchRepository;
use App\Voter\SportMatchVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/zapasy/{id}/presunout',
    name: 'portal_sport_match_reschedule',
    requirements: ['id' => Requirement::UUID],
    methods: ['POST'],
)]
final class RescheduleController extends AbstractController
{
    public function __construct(
        private readonly SportMatchRepository $sportMatchRepository,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $sportMatch = $this->sportMatchRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(SportMatchVoter::EDIT, $sportMatch);

        if (!$this->isCsrfTokenValid('sport_match_reschedule_'.$sportMatch->id->toRfc4122(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Neplatný bezpečnostní token. Zkuste to znovu.');

            return $this->redirectToRoute('portal_sport_match_detail', ['id' => $sportMatch->id->toRfc4122()]);
        }

        $raw = (string) $request->request->get('new_kickoff_at', '');
        $newKickoffAt = $this->parseKickoff($raw);

        if (null === $newKickoffAt) {
            $this->addFlash('error', 'Neplatný formát nového termínu.');

            return $this->redirectToRoute('portal_sport_match_detail', ['id' => $sportMatch->id->toRfc4122()]);
        }

        $this->commandBus->dispatch(new RescheduleSportMatchCommand(
            sportMatchId: $sportMatch->id,
            editorId: $user->id,
            newKickoffAt: $newKickoffAt,
        ));

        $this->addFlash('success', 'Zápas byl přesunut zpět do plánu.');

        return $this->redirectToRoute('portal_sport_match_detail', ['id' => $sportMatch->id->toRfc4122()]);
    }

    private function parseKickoff(string $raw): ?\DateTimeImmutable
    {
        if ('' === $raw) {
            return null;
        }

        foreach (['Y-m-d\TH:i', 'Y-m-d H:i', 'Y-m-d\TH:i:s'] as $format) {
            $parsed = \DateTimeImmutable::createFromFormat($format, $raw);
            if ($parsed instanceof \DateTimeImmutable) {
                return $parsed;
            }
        }

        return null;
    }
}
