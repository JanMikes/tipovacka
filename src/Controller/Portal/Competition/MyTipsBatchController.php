<?php

declare(strict_types=1);

namespace App\Controller\Portal\Competition;

use App\Command\DeleteGuess\DeleteGuessCommand;
use App\Command\SubmitGuess\SubmitGuessCommand;
use App\Command\UpdateGuess\UpdateGuessCommand;
use App\Entity\Competition;
use App\Entity\Guess;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Exception\GuessAlreadyExists;
use App\Exception\GuessDeadlinePassed;
use App\Exception\GuessFeatureNotEnabled;
use App\Exception\GuessNotFound;
use App\Exception\GuessNotYetOpen;
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
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/souteze/{id}/moje-tipy',
    name: 'competition_my_tips_batch',
    requirements: ['id' => Requirement::UUID],
    methods: ['GET', 'POST'],
)]
#[IsGranted('ROLE_USER')]
final class MyTipsBatchController extends AbstractController
{
    /** Which inputs of a rejected row the template marks invalid. */
    public const string PART_SCORE = 'score';
    public const string PART_PERIODS = 'periods';

    /** Same ceiling as the tip card (Guess:GuessSubmitForm). */
    private const int MAX_SCORE = 99;

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
            return $this->save($request, $competition, $user);
        }

        return $this->renderPage($competition, $user);
    }

    /**
     * The table, fresh from the database — or, after a rejected save, with each
     * rejected row showing what the player typed, its problem next to it and the
     * whole list on top (422, so a reload does not resubmit).
     *
     * @param array<string, array{label: string, message: string, part: string}> $rowErrors  matchId → problem
     * @param array<mixed>                                                       $submitted  the posted guesses
     * @param list<string>                                                       $formErrors problems without a row to sit on
     */
    private function renderPage(
        Competition $competition,
        User $user,
        array $rowErrors = [],
        array $submitted = [],
        array $formErrors = [],
        bool $keepAllSubmitted = false,
    ): Response {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $rows = $this->buildRows($competition, $user, $now);
        $lockMoment = $this->deadlineResolver->lockMomentFor($competition);
        $sport = $competition->headlineSource->sport;

        foreach ($rows as $index => $row) {
            $matchId = $row['match']->id->toRfc4122();
            $error = $rowErrors[$matchId] ?? null;
            $posted = $submitted[$matchId] ?? null;

            $rows[$index]['values'] = (null !== $error || $keepAllSubmitted) && \is_array($posted)
                ? $this->postedValues($posted, $sport->periodCount)
                : $this->storedValues($row['guess'], $sport->periodCount);
            $rows[$index]['error'] = $error;
            unset($rowErrors[$matchId]);
        }

        // A row that failed but is no longer listed (its uzávěrka passed meanwhile)
        // still has to say why — in the box on top.
        foreach ($rowErrors as $error) {
            $formErrors[] = sprintf('%s: %s', $error['label'], $error['message']);
        }

        $hasErrors = [] !== $formErrors || \in_array(true, array_map(static fn (array $row): bool => null !== $row['error'], $rows), true);

        return $this->render('portal/competition/my_tips_batch.html.twig', [
            'competition' => $competition,
            'rows' => $rows,
            'lock_moment' => $lockMoment,
            // B5: „no rows" has two very different causes — nothing scheduled, or
            // the whole soutěž is locked. The empty state must say which.
            'tips_locked' => null !== $lockMoment && $lockMoment <= $now,
            'sport' => $sport,
            'features' => $this->guessFeatures->featuresFor($competition->id),
            'form_errors' => $formErrors,
        ], new Response(status: $hasErrors ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    private function save(Request $request, Competition $competition, User $user): Response
    {
        $competitionId = $competition->id;
        $guesses = $request->request->all('guesses');
        $csrfToken = $request->request->get('_token');
        $expectedToken = sprintf('my_tips_batch_%s_%s', $competitionId->toRfc4122(), $user->id->toRfc4122());

        if (!\is_string($csrfToken) || !$this->isCsrfTokenValid($expectedToken, $csrfToken)) {
            // Typically a form left open for hours: keep every typed tip on screen.
            return $this->renderPage($competition, $user, [], $guesses, [
                'Formulář mezitím vypršel, a tak se žádný tip neuložil. Vaše tipy jsme ponechali vyplněné — zkontrolujte je a klikněte znovu na „Uložit vše".',
            ], true);
        }

        $features = $this->guessFeatures->featuresFor($competitionId);
        $sport = $competition->headlineSource->sport;

        $saved = 0;
        $deleted = 0;
        /** @var array<string, array{label: string, message: string, part: string}> $errors */
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
            $label = sprintf('%s – %s', $sportMatch->homeTeam->name, $sportMatch->awayTeam->name);
            $reject = static function (string $message, string $part = self::PART_SCORE) use (&$errors, $sportMatchIdString, $label): void {
                $errors[$sportMatchIdString] = ['label' => $label, 'message' => $message, 'part' => $part];
            };

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
                    $reject('Vyplňte i celkové skóre zápasu — tip na části platí jen spolu s ním.');

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
                    $reject($this->domainMessage($e));
                }

                continue;
            }

            if ('' === $homeRaw || '' === $awayRaw) {
                $reject(null === $existing
                    ? 'Doplňte skóre obou týmů, nebo nechte obě políčka prázdná a zápas se přeskočí.'
                    : 'Doplňte skóre obou týmů, nebo nechte obě políčka prázdná a uložený tip se smaže.');

                continue;
            }

            if (!$this->isScore($homeRaw) || !$this->isScore($awayRaw)) {
                $reject('Skóre musí být celé číslo od 0 do 99.');

                continue;
            }

            $homeScore = (int) $homeRaw;
            $awayScore = (int) $awayRaw;

            $periodScores = null;

            if ($features->periodTips) {
                $periodScores = $this->parsePeriodScores($scores, $sport->periodCount, $sport->periodLabelPlural, $homeScore, $awayScore, $reject);

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
                    // pair travels only while re-derived for the NEW tip (the winner pick survives a corrected draw),
                    // else it is dropped (consistent with the non-draw drop).
                    $carriedOvertime = $features->overtimeTip ? $existing->overtimeTipFor($homeScore, $awayScore) : null;

                    $this->commandBus->dispatch(new UpdateGuessCommand(
                        userId: $user->id,
                        guessId: $existing->id,
                        homeScore: $homeScore,
                        awayScore: $awayScore,
                        periodScores: $periodScores,
                        overtimeHomeScore: $carriedOvertime[0] ?? null,
                        overtimeAwayScore: $carriedOvertime[1] ?? null,
                        scorers: $features->scorerTips ? $this->scorerWriter->inputsFor($existing) : [],
                    ));
                }

                ++$saved;
            } catch (HandlerFailedException $e) {
                $reject($this->domainMessage($e));
            }
        }

        if ($saved > 0) {
            $this->addFlash('success', sprintf('Uloženo tipů: %d.', $saved));
        }

        if ($deleted > 0) {
            $this->addFlash('success', sprintf('Smazáno tipů: %d.', $deleted));
        }

        if ([] !== $errors) {
            return $this->renderPage($competition, $user, $errors, $guesses);
        }

        if (0 === $saved && 0 === $deleted) {
            $this->addFlash('info', 'Nebyly provedeny žádné změny.');
        }

        return $this->redirectToRoute('competition_my_tips_batch', [
            'id' => $competitionId->toRfc4122(),
        ]);
    }

    private function isScore(string $raw): bool
    {
        return ctype_digit($raw) && (int) $raw <= self::MAX_SCORE;
    }

    /**
     * The player-facing reason a domain command refused the row; anything that is
     * not a domain refusal is a bug and must surface as one.
     */
    private function domainMessage(HandlerFailedException $e): string
    {
        $inner = $e->getPrevious();

        if ($inner instanceof InvalidGuessScore
            || $inner instanceof GuessFeatureNotEnabled
            || $inner instanceof GuessDeadlinePassed
            || $inner instanceof GuessNotYetOpen
            || $inner instanceof GuessAlreadyExists
            || $inner instanceof GuessNotFound
            || $inner instanceof NotAMember
            || $inner instanceof MatchNotInCompetition
        ) {
            return $inner->getMessage();
        }

        throw $e;
    }

    /**
     * @param array<mixed> $posted
     *
     * @return array{home: string, away: string, periods: array<int, array{home: string, away: string}>}
     */
    private function postedValues(array $posted, int $periodCount): array
    {
        $string = static fn (mixed $value): string => \is_string($value) ? trim($value) : '';
        $periodsRaw = \is_array($posted['periods'] ?? null) ? $posted['periods'] : [];
        $periods = [];

        for ($number = 1; $number <= $periodCount; ++$number) {
            $pair = \is_array($periodsRaw[$number] ?? null) ? $periodsRaw[$number] : [];
            $periods[$number] = ['home' => $string($pair['home'] ?? null), 'away' => $string($pair['away'] ?? null)];
        }

        return ['home' => $string($posted['homeScore'] ?? null), 'away' => $string($posted['awayScore'] ?? null), 'periods' => $periods];
    }

    /**
     * @return array{home: string, away: string, periods: array<int, array{home: string, away: string}>}
     */
    private function storedValues(?Guess $guess, int $periodCount): array
    {
        $stored = $guess?->periodScores?->toArray();
        $periods = [];

        for ($number = 1; $number <= $periodCount; ++$number) {
            $pair = $stored[$number - 1] ?? null;
            $periods[$number] = null === $pair
                ? ['home' => '', 'away' => '']
                : ['home' => (string) $pair[0], 'away' => (string) $pair[1]];
        }

        return [
            'home' => null === $guess ? '' : (string) $guess->homeScore,
            'away' => null === $guess ? '' : (string) $guess->awayScore,
            'periods' => $periods,
        ];
    }

    /**
     * All-or-nothing period tip from `guesses[<matchId>][periods][<n>][home|away]`,
     * whose sums must match the main tip (checked here, not only in the entity, so
     * the error lands on the period inputs and says what they add up to).
     *
     * @param array<mixed>                   $scores
     * @param callable(string, string): void $reject
     *
     * @return PeriodScores|false|null false = validation error (collected)
     */
    private function parsePeriodScores(array $scores, int $periodCount, string $periodLabelPlural, int $homeScore, int $awayScore, callable $reject): PeriodScores|false|null
    {
        $periodsRaw = $scores['periods'] ?? [];

        if (!\is_array($periodsRaw)) {
            return null;
        }

        $pairs = [];
        $anyFilled = false;
        $allFilled = true;
        $allValid = true;

        for ($number = 1; $number <= $periodCount; ++$number) {
            $pair = $periodsRaw[$number] ?? [];
            $homeRaw = \is_array($pair) && \is_string($pair['home'] ?? null) ? trim($pair['home']) : '';
            $awayRaw = \is_array($pair) && \is_string($pair['away'] ?? null) ? trim($pair['away']) : '';

            if ('' !== $homeRaw || '' !== $awayRaw) {
                $anyFilled = true;
            }

            if ('' === $homeRaw || '' === $awayRaw) {
                $allFilled = false;
                $pairs[] = [0, 0];

                continue;
            }

            if (!$this->isScore($homeRaw) || !$this->isScore($awayRaw)) {
                $allValid = false;
                $pairs[] = [0, 0];

                continue;
            }

            $pairs[] = [(int) $homeRaw, (int) $awayRaw];
        }

        if (!$anyFilled) {
            return null;
        }

        if (!$allValid) {
            $reject(sprintf('Skóre za %s musí být celá čísla od 0 do 99.', $periodLabelPlural), self::PART_PERIODS);

            return false;
        }

        if (!$allFilled) {
            $reject(sprintf('Vyplňte prosím všechny %s, nebo je nechte prázdné.', $periodLabelPlural), self::PART_PERIODS);

            return false;
        }

        $periodScores = PeriodScores::fromArray($pairs);

        if ($periodScores->sumHome() !== $homeScore || $periodScores->sumAway() !== $awayScore) {
            $reject(InvalidGuessScore::periodSumMismatch(
                $periodScores->sumHome(),
                $periodScores->sumAway(),
                $homeScore,
                $awayScore,
                $periodLabelPlural,
            )->getMessage(), self::PART_PERIODS);

            return false;
        }

        return $periodScores;
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
     * @return list<array{match: SportMatch, guess: Guess|null, deadline: \DateTimeImmutable, waiting: bool, opensAt: ?\DateTimeImmutable, openingNote: ?string}>
     */
    private function buildRows(Competition $competition, User $user, \DateTimeImmutable $now): array
    {
        $allMatches = $this->matchProvider->matchesFor($competition);
        $candidateMatches = array_values(array_filter(
            $allMatches,
            static fn ($m) => $m->isOpenForGuesses,
        ));

        // The effective deadline is never later than kickoff, so filtering on
        // the deadline alone covers the "already kicked off" case too.
        $windows = $this->deadlineResolver->windowsFor($competition, $candidateMatches, $user);

        $rows = [];

        foreach ($candidateMatches as $sportMatch) {
            $window = $windows[$sportMatch->id->toRfc4122()];

            if ($window->isClosed($now)) {
                continue;
            }

            // A match whose tipping has not opened yet KEEPS its row (dropping it
            // would read as „not in this soutěž") — it renders without inputs and
            // says when it opens. The handler refuses a tip for it regardless.
            $rows[] = [
                'match' => $sportMatch,
                'guess' => $this->guessRepository->findActiveByUserMatchCompetition(
                    $user->id,
                    $sportMatch->id,
                    $competition->id,
                ),
                'deadline' => $window->deadline,
                'waiting' => $window->isWaiting($now),
                'opensAt' => $window->opensAt,
                'openingNote' => $window->openingNote,
            ];
        }

        return $rows;
    }
}
