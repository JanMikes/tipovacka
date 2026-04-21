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
    '/portal/skupiny/{groupId}/spravovat-tipy/{memberId}',
    name: 'portal_group_guess_on_behalf_batch',
    requirements: [
        'groupId' => Requirement::UUID,
        'memberId' => Requirement::UUID,
    ],
    methods: ['POST'],
)]
final class SubmitMemberTipsBatchController extends AbstractController
{
    public function __construct(
        private readonly GroupRepository $groupRepository,
        private readonly SportMatchRepository $sportMatchRepository,
        private readonly UserRepository $userRepository,
        private readonly GuessRepository $guessRepository,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $groupId, string $memberId): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $group = $this->groupRepository->get(Uuid::fromString($groupId));
        $this->denyAccessUnlessGranted(GroupVoter::MANAGE_MEMBERS, $group);

        $csrfToken = $request->request->get('_token');
        $expectedToken = sprintf('guess_on_behalf_batch_%s_%s', $groupId, $memberId);

        if (!\is_string($csrfToken) || !$this->isCsrfTokenValid($expectedToken, $csrfToken)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $member = $this->userRepository->get(Uuid::fromString($memberId));

        $guesses = $request->request->all('guesses');

        $saved = 0;
        $errors = [];

        foreach ($guesses as $sportMatchIdString => $scores) {
            if (!\is_string($sportMatchIdString) || !Uuid::isValid($sportMatchIdString)) {
                continue;
            }

            if (!\is_array($scores)) {
                continue;
            }

            $homeRaw = $scores['homeScore'] ?? '';
            $awayRaw = $scores['awayScore'] ?? '';

            if (!\is_string($homeRaw) || !\is_string($awayRaw)) {
                continue;
            }

            $homeRaw = trim($homeRaw);
            $awayRaw = trim($awayRaw);

            if ('' === $homeRaw && '' === $awayRaw) {
                continue;
            }

            $sportMatch = $this->sportMatchRepository->get(Uuid::fromString($sportMatchIdString));
            $label = sprintf('%s vs %s', $sportMatch->homeTeam, $sportMatch->awayTeam);

            if ('' === $homeRaw || '' === $awayRaw || !ctype_digit($homeRaw) || !ctype_digit($awayRaw)) {
                $errors[] = sprintf('%s: vyplň prosím oba skóre.', $label);

                continue;
            }

            $homeScore = (int) $homeRaw;
            $awayScore = (int) $awayRaw;

            $existing = $this->guessRepository->findActiveByUserMatchGroup(
                $member->id,
                $sportMatch->id,
                $group->id,
            );

            if (null !== $existing
                && $existing->homeScore === $homeScore
                && $existing->awayScore === $awayScore
            ) {
                continue;
            }

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

                ++$saved;
            } catch (HandlerFailedException $e) {
                $inner = $e->getPrevious();

                if ($inner instanceof InvalidGuessScore
                    || $inner instanceof GuessDeadlinePassed
                    || $inner instanceof GuessAlreadyExists
                    || $inner instanceof NotAMember
                ) {
                    $errors[] = sprintf('%s: %s', $label, $inner->getMessage());
                } else {
                    throw $e;
                }
            }
        }

        if ($saved > 0) {
            $this->addFlash('success', sprintf('Uloženo tipů: %d (pro %s).', $saved, $member->displayName));
        }

        foreach ($errors as $error) {
            $this->addFlash('error', $error);
        }

        if (0 === $saved && 0 === count($errors)) {
            $this->addFlash('info', 'Nebyly provedeny žádné změny.');
        }

        return $this->redirectToRoute('portal_group_manage_member_tips', [
            'id' => $group->id->toRfc4122(),
            'member' => $member->id->toRfc4122(),
        ]);
    }
}
