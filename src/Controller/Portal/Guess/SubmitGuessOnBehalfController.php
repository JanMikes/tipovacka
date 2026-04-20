<?php

declare(strict_types=1);

namespace App\Controller\Portal\Guess;

use App\Command\SubmitGuessOnBehalf\SubmitGuessOnBehalfCommand;
use App\Command\UpdateGuessOnBehalf\UpdateGuessOnBehalfCommand;
use App\Entity\User;
use App\Exception\GuessAlreadyExists;
use App\Exception\GuessDeadlinePassed;
use App\Exception\InvalidGuessScore;
use App\Exception\NotAMember;
use App\Repository\GroupRepository;
use App\Repository\GuessRepository;
use App\Repository\SportMatchRepository;
use App\Repository\UserRepository;
use App\Voter\GroupVoter;
use App\Voter\GuessOnBehalfContext;
use App\Voter\GuessVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/skupiny/{groupId}/zapasy/{sportMatchId}/clenove/{memberId}/tip',
    name: 'portal_group_guess_on_behalf',
    requirements: [
        'groupId' => Requirement::UUID,
        'sportMatchId' => Requirement::UUID,
        'memberId' => Requirement::UUID,
    ],
    methods: ['POST'],
)]
final class SubmitGuessOnBehalfController extends AbstractController
{
    public function __construct(
        private readonly GroupRepository $groupRepository,
        private readonly SportMatchRepository $sportMatchRepository,
        private readonly UserRepository $userRepository,
        private readonly GuessRepository $guessRepository,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $groupId, string $sportMatchId, string $memberId): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $group = $this->groupRepository->get(Uuid::fromString($groupId));
        $this->denyAccessUnlessGranted(GroupVoter::MANAGE_MEMBERS, $group);

        $csrfToken = $request->request->get('_token');
        $expectedToken = sprintf('guess_on_behalf_%s_%s_%s', $groupId, $sportMatchId, $memberId);

        if (!\is_string($csrfToken) || !$this->isCsrfTokenValid($expectedToken, $csrfToken)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $sportMatch = $this->sportMatchRepository->get(Uuid::fromString($sportMatchId));
        $member = $this->userRepository->get(Uuid::fromString($memberId));

        $homeScore = (int) $request->request->get('homeScore', '-1');
        $awayScore = (int) $request->request->get('awayScore', '-1');

        $redirect = $request->request->getString(
            'redirect_to',
            $this->generateUrl('portal_group_sport_match_guesses', [
                'groupId' => $group->id->toRfc4122(),
                'sportMatchId' => $sportMatch->id->toRfc4122(),
            ]),
        );

        $existing = $this->guessRepository->findActiveByUserMatchGroup(
            $member->id,
            $sportMatch->id,
            $group->id,
        );

        try {
            if (null === $existing) {
                $context = new GuessOnBehalfContext($group, $sportMatch, $member);
                $this->denyAccessUnlessGranted(GuessVoter::SUBMIT_ON_BEHALF, $context);

                $this->commandBus->dispatch(new SubmitGuessOnBehalfCommand(
                    actingUserId: $user->id,
                    targetUserId: $member->id,
                    groupId: $group->id,
                    sportMatchId: $sportMatch->id,
                    homeScore: $homeScore,
                    awayScore: $awayScore,
                ));
            } else {
                $this->denyAccessUnlessGranted(GuessVoter::UPDATE_ON_BEHALF, $existing);

                $this->commandBus->dispatch(new UpdateGuessOnBehalfCommand(
                    actingUserId: $user->id,
                    guessId: $existing->id,
                    homeScore: $homeScore,
                    awayScore: $awayScore,
                ));
            }

            $this->addFlash('success', sprintf('Tip pro %s uložen.', $member->nickname));
        } catch (HandlerFailedException $e) {
            $inner = $e->getPrevious();

            if ($inner instanceof InvalidGuessScore
                || $inner instanceof GuessDeadlinePassed
                || $inner instanceof GuessAlreadyExists
                || $inner instanceof NotAMember
            ) {
                $this->addFlash('error', $inner->getMessage());
            } else {
                throw $e;
            }
        }

        return $this->redirect($redirect);
    }
}
