<?php

declare(strict_types=1);

namespace App\Controller\Portal\Competition;

use App\Command\DeleteGuess\DeleteGuessCommand;
use App\Command\SubmitGuess\SubmitGuessCommand;
use App\Command\UpdateGuess\UpdateGuessCommand;
use App\Entity\User;
use App\Exception\GuessAlreadyExists;
use App\Exception\GuessDeadlinePassed;
use App\Exception\GuessFeatureNotEnabled;
use App\Exception\GuessNotFound;
use App\Exception\InvalidGuessScore;
use App\Exception\MatchNotInCompetition;
use App\Exception\NotAMember;
use App\Repository\CompetitionRepository;
use App\Repository\GuessRepository;
use App\Repository\SportMatchRepository;
use App\Service\Competition\CompetitionGuessFeatures;
use App\Service\Competition\CompetitionMatchProvider;
use App\Service\EffectiveTipDeadlineResolver;
use App\Service\Guess\GuessScorerWriter;
use App\Value\PeriodScores;
use App\Voter\CompetitionVoter;
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
    '/portal/souteze/{id}/moje-tipy',
    name: 'portal_competition_my_tips_batch',
    requirements: ['id' => Requirement::UUID],
    methods: ['GET', 'POST'],
)]
final class MyTipsBatchController extends AbstractController
{
    public function __construct(
        private readonly CompetitionRepository $competitionRepository,
        private readonly SportMatchRepository $sportMatchRepository,
        private readonly GuessRepository $guessRepository,
        private readonly CompetitionMatchProvider $matchProvider,
        private readonly CompetitionGuessFeatures $guessFeatures,
        private readonly GuessScorerWriter $scorerWriter,
        private readonly EffectiveTipDeadlineResolver $deadlineResolver,
        private readonly MessageBusInterface $commandBus,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $competition = $this->competitionRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(CompetitionVoter::VIEW, $competition);

        if ($request->isMethod('POST')) {
            return $this->save($request, $competition->id, $user);
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $rows = $this->buildRows($competition, $user->id, $now);

        return $this->render('portal/competition/my_tips_batch.html.twig', [
            'competition' => $competition,
            'rows' => $rows,
            'sport' => $competition->matchSource->sport,
            'features' => $this->guessFeatures->featuresFor($competition->id),
        ]);
    }

    private function save(Request $request, Uuid $competitionId, User $user): Response
    {
        $csrfToken = $request->request->get('_token');
        $expectedToken = sprintf('my_tips_batch_%s_%s', $competitionId->toRfc4122(), $user->id->toRfc4122());

        if (!\is_string($csrfToken) || !$this->isCsrfTokenValid($expectedToken, $csrfToken)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $guesses = $request->request->all('guesses');
        $competition = $this->competitionRepository->get($competitionId);
        $features = $this->guessFeatures->featuresFor($competitionId);
        $sport = $competition->matchSource->sport;

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

            $existing = $this->guessRepository->findActiveByUserMatchCompetition(
                $user->id,
                $sportMatch->id,
                $competitionId,
            );

            $bothEmpty = '' === $homeRaw && '' === $awayRaw;

            if ($bothEmpty) {
                // Periods typed without a main tip: neither a delete nor a
                // silent discard — the row gets an explicit error.
                if ($features->periodTips && $this->hasAnyPeriodInput($scores, $sport->periodCount)) {
                    $errors[] = sprintf('%s: Vyplňte i celkové skóre zápasu.', $label);

                    continue;
                }

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
                $errors[] = sprintf('%s: vyplňte prosím obě skóre (nebo nechte obě pole prázdná).', $label);

                continue;
            }

            $homeScore = (int) $homeRaw;
            $awayScore = (int) $awayRaw;

            $periodScores = null;

            if ($features->periodTips) {
                $periodScores = $this->parsePeriodScores($scores, $sport->periodCount, $sport->periodLabelPlural, $label, $errors);

                if (false === $periodScores) {
                    continue; // Error collected.
                }
            }

            // Unchanged-skip: parts of DISABLED features are ignored in the
            // comparison, so a no-op „Uložit vše" never normalizes (drops)
            // legacy tip parts of a since-disabled feature.
            if (null !== $existing
                && $existing->homeScore === $homeScore
                && $existing->awayScore === $awayScore
                && (!$features->periodTips || $existing->periodScores?->toArray() === $periodScores?->toArray())
            ) {
                continue;
            }

            try {
                if (null === $existing) {
                    $this->commandBus->dispatch(new SubmitGuessCommand(
                        userId: $user->id,
                        competitionId: $competitionId,
                        sportMatchId: $sportMatch->id,
                        homeScore: $homeScore,
                        awayScore: $awayScore,
                        periodScores: $periodScores,
                    ));
                } else {
                    // The batch page edits only the main score + periods; the
                    // overtime and scorer parts of a full-replace update are
                    // passed through untouched from the existing guess. The OT
                    // pair travels only while still valid against the NEW tip,
                    // else it is dropped (consistent with the non-draw drop).
                    $carryOvertime = $features->overtimeTip && $existing->overtimeTipValidFor($homeScore, $awayScore);

                    $this->commandBus->dispatch(new UpdateGuessCommand(
                        userId: $user->id,
                        guessId: $existing->id,
                        homeScore: $homeScore,
                        awayScore: $awayScore,
                        periodScores: $periodScores,
                        overtimeHomeScore: $carryOvertime ? $existing->overtimeHomeScore : null,
                        overtimeAwayScore: $carryOvertime ? $existing->overtimeAwayScore : null,
                        scorers: $features->scorerTips ? $this->scorerWriter->inputsFor($existing) : [],
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

        return $this->redirectToRoute('portal_competition_my_tips_batch', [
            'id' => $competitionId->toRfc4122(),
        ]);
    }

    /**
     * @param list<string> $errors
     */
    private function collectError(array &$errors, string $label, HandlerFailedException $e): void
    {
        $inner = $e->getPrevious();

        if ($inner instanceof InvalidGuessScore
            || $inner instanceof GuessFeatureNotEnabled
            || $inner instanceof GuessDeadlinePassed
            || $inner instanceof GuessAlreadyExists
            || $inner instanceof GuessNotFound
            || $inner instanceof NotAMember
            || $inner instanceof MatchNotInCompetition
        ) {
            $errors[] = sprintf('%s: %s', $label, $inner->getMessage());

            return;
        }

        throw $e;
    }

    /**
     * All-or-nothing period tip from `guesses[<matchId>][periods][<n>][home|away]`.
     *
     * @param array<mixed> $scores
     * @param list<string> $errors
     *
     * @return PeriodScores|false|null false = validation error (collected)
     */
    private function parsePeriodScores(array $scores, int $periodCount, string $periodLabelPlural, string $label, array &$errors): PeriodScores|false|null
    {
        $periodsRaw = $scores['periods'] ?? [];

        if (!\is_array($periodsRaw)) {
            return null;
        }

        $pairs = [];
        $anyFilled = false;
        $allFilled = true;

        for ($number = 1; $number <= $periodCount; ++$number) {
            $pair = $periodsRaw[$number] ?? [];
            $homeRaw = \is_array($pair) && \is_string($pair['home'] ?? null) ? trim($pair['home']) : '';
            $awayRaw = \is_array($pair) && \is_string($pair['away'] ?? null) ? trim($pair['away']) : '';

            if ('' !== $homeRaw || '' !== $awayRaw) {
                $anyFilled = true;
            }

            if ('' === $homeRaw || '' === $awayRaw || !ctype_digit($homeRaw) || !ctype_digit($awayRaw)) {
                $allFilled = false;
                $pairs[] = [0, 0];

                continue;
            }

            $pairs[] = [(int) $homeRaw, (int) $awayRaw];
        }

        if (!$anyFilled) {
            return null;
        }

        if (!$allFilled) {
            $errors[] = sprintf('%s: vyplňte prosím všechny %s, nebo je nechte prázdné.', $label, $periodLabelPlural);

            return false;
        }

        return PeriodScores::fromArray($pairs);
    }

    /**
     * Whether any period input of the row carries a value (used to distinguish
     * an intentional empty row from a periods-without-main-score mistake).
     *
     * @param array<mixed> $scores
     */
    private function hasAnyPeriodInput(array $scores, int $periodCount): bool
    {
        $periodsRaw = $scores['periods'] ?? [];

        if (!\is_array($periodsRaw)) {
            return false;
        }

        for ($number = 1; $number <= $periodCount; ++$number) {
            $pair = $periodsRaw[$number] ?? [];

            if (!\is_array($pair)) {
                continue;
            }

            foreach (['home', 'away'] as $key) {
                $value = $pair[$key] ?? null;

                if (\is_string($value) && '' !== trim($value)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return list<array{match: \App\Entity\SportMatch, guess: \App\Entity\Guess|null}>
     */
    private function buildRows(\App\Entity\Competition $competition, Uuid $userId, \DateTimeImmutable $now): array
    {
        $allMatches = $this->matchProvider->matchesFor($competition);
        $candidateMatches = array_values(array_filter(
            $allMatches,
            static fn ($m) => $m->isOpenForGuesses && $m->kickoffAt > $now,
        ));

        $deadlines = $this->deadlineResolver->resolveMany($competition, $candidateMatches);

        $rows = [];

        foreach ($candidateMatches as $sportMatch) {
            $deadline = $deadlines[$sportMatch->id->toRfc4122()];

            if ($deadline <= $now) {
                continue;
            }

            $rows[] = [
                'match' => $sportMatch,
                'guess' => $this->guessRepository->findActiveByUserMatchCompetition(
                    $userId,
                    $sportMatch->id,
                    $competition->id,
                ),
            ];
        }

        return $rows;
    }
}
