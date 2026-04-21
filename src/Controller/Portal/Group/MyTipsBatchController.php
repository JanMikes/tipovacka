<?php

declare(strict_types=1);

namespace App\Controller\Portal\Group;

use App\Command\DeleteGuess\DeleteGuessCommand;
use App\Command\SubmitGuess\SubmitGuessCommand;
use App\Command\UpdateGuess\UpdateGuessCommand;
use App\Entity\User;
use App\Enum\SportMatchState;
use App\Exception\GuessAlreadyExists;
use App\Exception\GuessDeadlinePassed;
use App\Exception\GuessNotFound;
use App\Exception\InvalidGuessScore;
use App\Exception\NotAMember;
use App\Repository\GroupRepository;
use App\Repository\GuessRepository;
use App\Repository\SportMatchRepository;
use App\Voter\GroupVoter;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/skupiny/{id}/moje-tipy',
    name: 'portal_group_my_tips_batch',
    requirements: ['id' => Requirement::UUID],
    methods: ['GET', 'POST'],
)]
final class MyTipsBatchController extends AbstractController
{
    public function __construct(
        private readonly GroupRepository $groupRepository,
        private readonly SportMatchRepository $sportMatchRepository,
        private readonly GuessRepository $guessRepository,
        private readonly MessageBusInterface $commandBus,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $group = $this->groupRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(GroupVoter::VIEW, $group);

        if ($request->isMethod('POST')) {
            return $this->save($request, $group->id, $user);
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $rows = $this->buildRows($group->id, $group->tournament->id, $user->id, $now);

        return $this->render('portal/group/my_tips_batch.html.twig', [
            'group' => $group,
            'rows' => $rows,
        ]);
    }

    private function save(Request $request, Uuid $groupId, User $user): Response
    {
        $csrfToken = $request->request->get('_token');
        $expectedToken = sprintf('my_tips_batch_%s_%s', $groupId->toRfc4122(), $user->id->toRfc4122());

        if (!\is_string($csrfToken) || !$this->isCsrfTokenValid($expectedToken, $csrfToken)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $guesses = $request->request->all('guesses');

        $saved = 0;
        $deleted = 0;
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

            $sportMatch = $this->sportMatchRepository->get(Uuid::fromString($sportMatchIdString));
            $label = sprintf('%s vs %s', $sportMatch->homeTeam, $sportMatch->awayTeam);

            $existing = $this->guessRepository->findActiveByUserMatchGroup(
                $user->id,
                $sportMatch->id,
                $groupId,
            );

            $bothEmpty = '' === $homeRaw && '' === $awayRaw;

            if ($bothEmpty) {
                if (null === $existing) {
                    continue;
                }

                try {
                    $this->commandBus->dispatch(new DeleteGuessCommand(
                        userId: $user->id,
                        guessId: $existing->id,
                    ));
                    ++$deleted;
                } catch (HandlerFailedException $e) {
                    $this->collectError($errors, $label, $e);
                }

                continue;
            }

            if ('' === $homeRaw || '' === $awayRaw || !ctype_digit($homeRaw) || !ctype_digit($awayRaw)) {
                $errors[] = sprintf('%s: vyplň prosím oba skóre (nebo nech obě pole prázdná).', $label);

                continue;
            }

            $homeScore = (int) $homeRaw;
            $awayScore = (int) $awayRaw;

            if (null !== $existing
                && $existing->homeScore === $homeScore
                && $existing->awayScore === $awayScore
            ) {
                continue;
            }

            try {
                if (null === $existing) {
                    $this->commandBus->dispatch(new SubmitGuessCommand(
                        userId: $user->id,
                        groupId: $groupId,
                        sportMatchId: $sportMatch->id,
                        homeScore: $homeScore,
                        awayScore: $awayScore,
                    ));
                } else {
                    $this->commandBus->dispatch(new UpdateGuessCommand(
                        userId: $user->id,
                        guessId: $existing->id,
                        homeScore: $homeScore,
                        awayScore: $awayScore,
                    ));
                }

                ++$saved;
            } catch (HandlerFailedException $e) {
                $this->collectError($errors, $label, $e);
            }
        }

        if ($saved > 0) {
            $this->addFlash('success', sprintf('Uloženo tipů: %d.', $saved));
        }

        if ($deleted > 0) {
            $this->addFlash('success', sprintf('Smazáno tipů: %d.', $deleted));
        }

        foreach ($errors as $error) {
            $this->addFlash('error', $error);
        }

        if (0 === $saved && 0 === $deleted && 0 === count($errors)) {
            $this->addFlash('info', 'Nebyly provedeny žádné změny.');
        }

        return $this->redirectToRoute('portal_group_my_tips_batch', [
            'id' => $groupId->toRfc4122(),
        ]);
    }

    /**
     * @param list<string> $errors
     */
    private function collectError(array &$errors, string $label, HandlerFailedException $e): void
    {
        $inner = $e->getPrevious();

        if ($inner instanceof InvalidGuessScore
            || $inner instanceof GuessDeadlinePassed
            || $inner instanceof GuessAlreadyExists
            || $inner instanceof GuessNotFound
            || $inner instanceof NotAMember
        ) {
            $errors[] = sprintf('%s: %s', $label, $inner->getMessage());

            return;
        }

        throw $e;
    }

    /**
     * @return list<array{match: \App\Entity\SportMatch, guess: \App\Entity\Guess|null}>
     */
    private function buildRows(Uuid $groupId, Uuid $tournamentId, Uuid $userId, \DateTimeImmutable $now): array
    {
        $allMatches = $this->sportMatchRepository->listByTournament(
            $tournamentId,
            SportMatchState::Scheduled,
            $now,
        );
        $openMatches = array_values(array_filter(
            $allMatches,
            static fn ($m) => $m->isOpenForGuesses && $m->kickoffAt > $now,
        ));

        $rows = [];

        foreach ($openMatches as $sportMatch) {
            $rows[] = [
                'match' => $sportMatch,
                'guess' => $this->guessRepository->findActiveByUserMatchGroup(
                    $userId,
                    $sportMatch->id,
                    $groupId,
                ),
            ];
        }

        return $rows;
    }
}
