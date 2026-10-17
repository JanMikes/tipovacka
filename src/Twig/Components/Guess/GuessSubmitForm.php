<?php

declare(strict_types=1);

namespace App\Twig\Components\Guess;

use App\Command\DeleteGuess\DeleteGuessCommand;
use App\Command\SubmitGuess\SubmitGuessCommand;
use App\Command\UpdateGuess\UpdateGuessCommand;
use App\Entity\Guess;
use App\Entity\Player;
use App\Entity\Sport;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\MatchSide;
use App\Exception\InvalidGuessScore;
use App\Repository\CompetitionRepository;
use App\Repository\GuessRepository;
use App\Repository\PlayerRepository;
use App\Service\Competition\CompetitionGuessFeatures;
use App\Service\Competition\GuessFeatures;
use App\Service\EffectiveTipDeadlineResolver;
use App\Value\GuessScorerInput;
use App\Value\OvertimeOutcome;
use App\Value\PeriodScores;
use App\Value\TipWindow;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\ValidatableComponentTrait;
use Symfony\UX\TwigComponent\Attribute\PostMount;

/**
 * Live score-input block for one match guess. The visible tip parts follow the
 * competition's rule enablement ({@see CompetitionGuessFeatures}): period
 * inputs (labels per sport), an overtime tip revealed REACTIVELY when the main
 * tip is a draw, and a scorer multi-picker (tom-select in a data-live-ignore
 * island — selections travel through the writable `scorersJson` model).
 *
 * Period props are fixed scalars (period1Home … period3Away) instead of a
 * writable array — LiveProp model binding on scalar names is rock-solid across
 * re-renders, and v1 sports have at most 3 periods (hockey). A future sport
 * with more periods needs additional props here.
 */
#[AsLiveComponent(name: 'Guess:GuessSubmitForm')]
final class GuessSubmitForm
{
    use DefaultActionTrait;
    use ValidatableComponentTrait;

    private const int MAX_RENDERED_PERIODS = 3;

    public const string SCORE_RANGE_MESSAGE = 'Zadejte celé číslo od 0 do 99.';

    /** The part of the form an {@see $errorMessage} is about — the template marks it and prints the message next to it. */
    public const string ERROR_FIELD_HOME = 'homeScore';
    public const string ERROR_FIELD_AWAY = 'awayScore';
    public const string ERROR_FIELD_SCORE = 'score';
    public const string ERROR_FIELD_PERIODS = 'periods';
    public const string ERROR_FIELD_SCORERS = 'scorers';

    #[LiveProp]
    public SportMatch $sportMatch;

    #[LiveProp]
    public string $competitionId = '';

    /**
     * B19 — render without the component's own card chrome (border/background/
     * padding/rounding). Set it on call sites that already wrap the form in a
     * card of their own; the alternative was every such call site repeating a
     * `!rounded-none !bg-transparent !p-0 …` utility incantation, which silently
     * left the border behind. A LiveProp (not a plain prop) so the bare shape
     * survives live re-renders.
     */
    #[LiveProp]
    public bool $bare = false;

    /**
     * Item 22 — the surrounding card already names both teams, so the per-input
     * labels must not repeat them; they become `sr-only` (kept, never dropped: they
     * are the inputs' accessible names). Set it wherever the team name sits directly
     * above the spinner it belongs to. A LiveProp, like `bare`, so the shape
     * survives a re-render.
     */
    #[LiveProp]
    public bool $showTeamLabels = true;

    #[LiveProp(writable: true)]
    #[Assert\Range(notInRangeMessage: self::SCORE_RANGE_MESSAGE, min: 0, max: 99)]
    public ?int $homeScore = null;

    #[LiveProp(writable: true)]
    #[Assert\Range(notInRangeMessage: self::SCORE_RANGE_MESSAGE, min: 0, max: 99)]
    public ?int $awayScore = null;

    #[LiveProp(writable: true)]
    #[Assert\Range(notInRangeMessage: self::SCORE_RANGE_MESSAGE, min: 0, max: 99)]
    public ?int $period1Home = null;

    #[LiveProp(writable: true)]
    #[Assert\Range(notInRangeMessage: self::SCORE_RANGE_MESSAGE, min: 0, max: 99)]
    public ?int $period1Away = null;

    #[LiveProp(writable: true)]
    #[Assert\Range(notInRangeMessage: self::SCORE_RANGE_MESSAGE, min: 0, max: 99)]
    public ?int $period2Home = null;

    #[LiveProp(writable: true)]
    #[Assert\Range(notInRangeMessage: self::SCORE_RANGE_MESSAGE, min: 0, max: 99)]
    public ?int $period2Away = null;

    #[LiveProp(writable: true)]
    #[Assert\Range(notInRangeMessage: self::SCORE_RANGE_MESSAGE, min: 0, max: 99)]
    public ?int $period3Home = null;

    #[LiveProp(writable: true)]
    #[Assert\Range(notInRangeMessage: self::SCORE_RANGE_MESSAGE, min: 0, max: 99)]
    public ?int $period3Away = null;

    /**
     * Who wins after extra time / penalties when the main tip is a draw:
     * '' (no pick), 'home' or 'away'. The stored pair is derived (the draw
     * plus one goal for the winner) — see Value\OvertimeOutcome.
     */
    #[LiveProp(writable: true)]
    #[Assert\Choice(callback: 'overtimeWinnerChoices', message: 'Vyberte prosím jednu z nabídnutých možností, kdo vyhraje po prodloužení.')]
    public string $overtimeWinner = '';

    /**
     * @return list<string>
     */
    public static function overtimeWinnerChoices(): array
    {
        return ['', ...array_column(MatchSide::cases(), 'value')];
    }

    /** JSON list of {side: 'home'|'away', name: string} written by the scorer-picker Stimulus controller. */
    #[LiveProp(writable: true)]
    public string $scorersJson = '[]';

    #[LiveProp]
    public ?string $errorMessage = null;

    /**
     * Where {@see $errorMessage} belongs (one of the ERROR_FIELD_* constants), or null
     * for a message about the whole tip (deadline passed, not a member …). The template
     * prints the message next to that part and marks its inputs aria-invalid, so the
     * form-feedback controller scrolls to and focuses it after the submit re-render.
     */
    #[LiveProp]
    public ?string $errorField = null;

    #[LiveProp]
    public ?string $successMessage = null;

    public function __construct(
        private readonly Security $security,
        private readonly GuessRepository $guessRepository,
        private readonly CompetitionRepository $competitionRepository,
        private readonly PlayerRepository $playerRepository,
        private readonly CompetitionGuessFeatures $guessFeatures,
        private readonly EffectiveTipDeadlineResolver $deadlineResolver,
        private readonly ClockInterface $clock,
        #[Autowire(service: 'command.bus')]
        private readonly MessageBusInterface $commandBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[PostMount]
    public function prefillFromExistingGuess(): void
    {
        $existing = $this->findExistingGuess();

        if (null === $existing) {
            return;
        }

        $this->homeScore = $existing->homeScore;
        $this->awayScore = $existing->awayScore;
        $this->overtimeWinner = $existing->overtimeWinner->value ?? '';

        $periods = $existing->periodScores;

        if (null !== $periods) {
            foreach ($periods->toArray() as $index => [$home, $away]) {
                $this->setPeriodTip($index + 1, $home, $away);
            }
        }

        $selected = [];

        foreach ($existing->scorers as $scorer) {
            $selected[] = [
                'side' => $scorer->side->value,
                'name' => $scorer->player->name,
            ];
        }

        $this->scorersJson = json_encode($selected, \JSON_THROW_ON_ERROR);
    }

    public bool $hasExistingGuess {
        get => null !== $this->findExistingGuess();
    }

    public bool $isLocked {
        get {
            $now = \DateTimeImmutable::createFromInterface($this->clock->now());
            $competition = $this->competitionRepository->get(Uuid::fromString($this->competitionId));
            $user = $this->security->getUser();

            return $this->deadlineResolver->isLocked(
                $competition,
                $this->sportMatch,
                $user instanceof User ? $user : null,
                $now,
            );
        }
    }

    /**
     * Tipping has not STARTED yet — locked, but for the opposite reason to a
     * passed uzávěrka, so the card says „otevřeme v…" instead of „proběhla".
     * The template must therefore branch on this BEFORE `isLocked`.
     */
    public bool $isWaiting {
        get => $this->sportMatch->isOpenForGuesses
            && $this->window->isWaiting(\DateTimeImmutable::createFromInterface($this->clock->now()));
    }

    public ?\DateTimeImmutable $opensAt {
        get => $this->window->opensAt;
    }

    /** The admin's optional explanation, shown only while the match waits. */
    public ?string $openingNote {
        get => $this->window->openingNote;
    }

    /**
     * This viewer's effective tip deadline for the match (entitlements included)
     * — drives the „Uzávěrka" line and the cause-aware locked copy on the card.
     */
    public \DateTimeImmutable $effectiveDeadline {
        get => $this->window->deadline;
    }

    /**
     * This viewer's whole tip window, from the ONE authority. Resolved per read;
     * the component is short-lived and the resolver caches per request.
     */
    private TipWindow $window {
        get {
            $competition = $this->competitionRepository->get(Uuid::fromString($this->competitionId));
            $user = $this->security->getUser();

            return $this->deadlineResolver->windowFor(
                $competition,
                $this->sportMatch,
                $user instanceof User ? $user : null,
            );
        }
    }

    public ?Guess $existingGuess {
        get => $this->findExistingGuess();
    }

    public GuessFeatures $features {
        get => $this->guessFeatures->featuresFor(Uuid::fromString($this->competitionId));
    }

    public Sport $sport {
        get => $this->sportMatch->matchSource->sport;
    }

    /**
     * @var list<int>
     */
    public array $periodNumbers {
        get => range(1, min($this->sport->periodCount, self::MAX_RENDERED_PERIODS));
    }

    /**
     * The „kdo vyhraje po prodloužení" radio: only in a soutěž that scores it,
     * only for a zdroj that plays extra time at all, and only on a drawn tip.
     */
    public bool $showOvertimeWinnerChoice {
        get => $this->features->overtimeTip
            && $this->sportMatch->matchSource->hasOvertime
            && null !== $this->homeScore
            && $this->homeScore === $this->awayScore;
    }

    /**
     * Roster options of both teams for the scorer picker, grouped by team.
     * Rendered server-side from the source's Player pool (S05) — local
     * tom-select filtering gives the autocomplete UX without a remote endpoint.
     *
     * @var list<array{side: string, team: string, players: list<string>}>
     */
    public array $scorerOptionGroups {
        get {
            return [
                [
                    'side' => MatchSide::Home->value,
                    'team' => $this->sportMatch->homeTeam->name,
                    'players' => array_map(
                        static fn (Player $player): string => $player->name,
                        $this->playerRepository->listByTeam($this->sportMatch->homeTeam->id),
                    ),
                ],
                [
                    'side' => MatchSide::Away->value,
                    'team' => $this->sportMatch->awayTeam->name,
                    'players' => array_map(
                        static fn (Player $player): string => $player->name,
                        $this->playerRepository->listByTeam($this->sportMatch->awayTeam->id),
                    ),
                ],
            ];
        }
    }

    /**
     * Currently selected scorers, decoded for the initial select rendering.
     *
     * @var list<array{side: string, name: string}>
     */
    public array $selectedScorers {
        get => $this->decodeScorers() ?? [];
    }

    /**
     * @return array{?int, ?int}
     */
    public function periodTip(int $number): array
    {
        return match ($number) {
            1 => [$this->period1Home, $this->period1Away],
            2 => [$this->period2Home, $this->period2Away],
            3 => [$this->period3Home, $this->period3Away],
            default => [null, null],
        };
    }

    /**
     * Whether the template should mark the given part (an ERROR_FIELD_* constant, or a
     * period model name such as period2Home) as invalid: the component's own
     * {@see $errorField}, or a constraint violation on that model.
     */
    public function hasError(string $field): bool
    {
        if (null !== $this->errorField) {
            if ($field === $this->errorField) {
                return true;
            }

            if (self::ERROR_FIELD_SCORE === $this->errorField && \in_array($field, [self::ERROR_FIELD_HOME, self::ERROR_FIELD_AWAY], true)) {
                return true;
            }

            if (self::ERROR_FIELD_PERIODS === $this->errorField && str_starts_with($field, 'period')) {
                return true;
            }
        }

        return null !== $this->getError($field);
    }

    #[LiveAction]
    public function submit(): void
    {
        $this->errorMessage = null;
        $this->errorField = null;
        $this->successMessage = null;

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            $this->errorMessage = 'Pro uložení tipu se prosím přihlaste.';

            return;
        }

        $existing = $this->findExistingGuess();
        $homeScore = $this->homeScore;
        $awayScore = $this->awayScore;
        $bothCleared = null === $homeScore && null === $awayScore;

        try {
            if ($bothCleared && null !== $existing) {
                $this->dispatchCommand(new DeleteGuessCommand(
                    userId: $user->id,
                    guessId: $existing->id,
                ));
                $this->successMessage = sprintf(
                    'Tip smazán. Nový můžete zadat do %s.',
                    $this->formatPrague($this->effectiveDeadline),
                );

                return;
            }

            if (null === $homeScore || null === $awayScore) {
                $this->errorField = null === $homeScore && null === $awayScore
                    ? self::ERROR_FIELD_SCORE
                    : (null === $homeScore ? self::ERROR_FIELD_HOME : self::ERROR_FIELD_AWAY);
                $this->errorMessage = null === $homeScore && null === $awayScore
                    ? 'Zadejte skóre obou týmů, např. 2 : 1.'
                    : sprintf(
                        'Doplňte ještě skóre týmu %s (celé číslo od 0 do 99).',
                        null === $homeScore ? $this->sportMatch->homeTeam->name : $this->sportMatch->awayTeam->name,
                    );

                return;
            }

            // Field errors render next to their inputs (and keep re-validating
            // live as the player fixes them); the message above names the fix.
            $this->validate(false);

            if (!$this->isValid()) {
                $this->errorMessage = 'Tip se neuložil — opravte prosím zvýrazněná políčka.';

                return;
            }

            $periodScores = $this->buildPeriodScores($homeScore, $awayScore);

            if (false === $periodScores) {
                return; // errorMessage set.
            }

            $scorers = $this->buildScorers();

            if (null === $scorers) {
                return; // errorMessage set.
            }

            // The winner pick travels only while the choice is visible (rule
            // enabled + draw tipped) — a stale pick from a previously tipped
            // draw must never reach the handler.
            $overtimePair = $this->showOvertimeWinnerChoice && '' !== $this->overtimeWinner
                ? (new OvertimeOutcome(MatchSide::from($this->overtimeWinner)))->scoreAfter($homeScore, $awayScore)
                : null;

            if (null === $existing) {
                $this->dispatchCommand(new SubmitGuessCommand(
                    userId: $user->id,
                    competitionId: Uuid::fromString($this->competitionId),
                    sportMatchId: $this->sportMatch->id,
                    homeScore: $homeScore,
                    awayScore: $awayScore,
                    periodScores: $periodScores,
                    overtimeHomeScore: $overtimePair[0] ?? null,
                    overtimeAwayScore: $overtimePair[1] ?? null,
                    scorers: $scorers,
                ));
                $this->successMessage = sprintf('Tip uložen. Změnit ho můžete do %s.', $this->formatPrague($this->effectiveDeadline));
            } else {
                $this->dispatchCommand(new UpdateGuessCommand(
                    userId: $user->id,
                    guessId: $existing->id,
                    homeScore: $homeScore,
                    awayScore: $awayScore,
                    periodScores: $periodScores,
                    overtimeHomeScore: $overtimePair[0] ?? null,
                    overtimeAwayScore: $overtimePair[1] ?? null,
                    scorers: $scorers,
                ));
                $this->successMessage = sprintf('Tip upraven. Změnit ho můžete do %s.', $this->formatPrague($this->effectiveDeadline));
            }

            // A pick that did not travel (tip is no draw any more) is gone from
            // the stored guess; drop it from the form too, or a later draw would
            // show a pre-selected winner the guess does not hold.
            if (null === $overtimePair) {
                $this->overtimeWinner = '';
            }
        } catch (HandlerFailedException $e) {
            $this->rejectWith($e->getPrevious() ?? $e);
        } catch (\DomainException $e) {
            $this->rejectWith($e);
        }
    }

    /**
     * A refusal from the domain (deadline passed, not yet open, not a member …) is
     * written in Czech for the player and shown in place — in the locked panel too,
     * because a deadline that passed while the form was open re-renders as locked.
     * Anything else is a bug: a calm generic message here, the details in the log.
     */
    private function rejectWith(\Throwable $e): void
    {
        if ($e instanceof \DomainException || str_starts_with($e::class, 'App\\Exception\\')) {
            $this->errorMessage = 'Tip se neuložil: '.$e->getMessage();

            return;
        }

        $this->logger->error('Guess submit failed unexpectedly.', ['exception' => $e]);
        $this->errorMessage = 'Tip se nepodařilo uložit kvůli chybě na naší straně. Zkuste to prosím za chvíli znovu.';
    }

    private function formatPrague(\DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new \DateTimeZone('Europe/Prague'))->format('j. n. Y H:i');
    }

    private function findExistingGuess(): ?Guess
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return null;
        }

        return $this->guessRepository->findActiveByUserMatchCompetition(
            $user->id,
            $this->sportMatch->id,
            Uuid::fromString($this->competitionId),
        );
    }

    private function setPeriodTip(int $number, int $home, int $away): void
    {
        switch ($number) {
            case 1:
                $this->period1Home = $home;
                $this->period1Away = $away;

                break;
            case 2:
                $this->period2Home = $home;
                $this->period2Away = $away;

                break;
            case 3:
                $this->period3Home = $home;
                $this->period3Away = $away;

                break;
        }
    }

    /**
     * All-or-nothing period tip from the scalar props.
     *
     * @return PeriodScores|false|null false = validation failed (errorMessage set)
     */
    private function buildPeriodScores(int $homeScore, int $awayScore): PeriodScores|false|null
    {
        if (!$this->features->periodTips) {
            return null;
        }

        $pairs = [];
        $anyFilled = false;
        $allFilled = true;

        foreach ($this->periodNumbers as $number) {
            [$home, $away] = $this->periodTip($number);

            if (null !== $home || null !== $away) {
                $anyFilled = true;
            }

            if (null === $home || null === $away) {
                $allFilled = false;
            }

            $pairs[] = [$home, $away];
        }

        if (!$anyFilled) {
            return null;
        }

        if (!$allFilled) {
            $this->errorField = self::ERROR_FIELD_PERIODS;
            $this->errorMessage = sprintf('Vyplňte prosím všechny %s, nebo je nechte prázdné — tip na části platí jen celý.', $this->sport->periodLabelPlural);

            return false;
        }

        $periodScores = PeriodScores::fromArray($pairs);

        // Same rule as the Guess entity, checked here so the message can point at
        // the period inputs and say what the numbers add up to.
        if ($periodScores->sumHome() !== $homeScore || $periodScores->sumAway() !== $awayScore) {
            $this->errorField = self::ERROR_FIELD_PERIODS;
            $this->errorMessage = InvalidGuessScore::periodSumMismatch(
                $periodScores->sumHome(),
                $periodScores->sumAway(),
                $homeScore,
                $awayScore,
                $this->sport->periodLabelPlural,
            )->getMessage();

            return false;
        }

        return $periodScores;
    }

    /**
     * @return list<GuessScorerInput>|null null = validation failed (errorMessage set)
     */
    private function buildScorers(): ?array
    {
        if (!$this->features->scorerTips) {
            return [];
        }

        $decoded = $this->decodeScorers();

        if (null === $decoded) {
            $this->errorField = self::ERROR_FIELD_SCORERS;
            $this->errorMessage = 'Tip na střelce se nepodařilo zpracovat. Vyberte střelce prosím znovu, nebo pole nechte prázdné.';

            return null;
        }

        $inputs = [];

        foreach ($decoded as $item) {
            if (mb_strlen($item['name']) > Player::NAME_MAX_LENGTH) {
                $this->errorField = self::ERROR_FIELD_SCORERS;
                $this->errorMessage = sprintf('Jméno hráče nesmí být delší než %d znaků — zkraťte ho prosím.', Player::NAME_MAX_LENGTH);

                return null;
            }

            $inputs[] = new GuessScorerInput(
                side: MatchSide::from($item['side']),
                playerName: $item['name'],
            );
        }

        return $inputs;
    }

    /**
     * @return list<array{side: string, name: string}>|null
     */
    private function decodeScorers(): ?array
    {
        try {
            $decoded = json_decode($this->scorersJson, true, 8, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($decoded)) {
            return null;
        }

        $items = [];

        foreach ($decoded as $item) {
            if (!is_array($item) || !isset($item['side'], $item['name'])) {
                return null;
            }

            $side = $item['side'];
            $name = $item['name'];

            if (!is_string($side) || !is_string($name) || null === MatchSide::tryFrom($side) || '' === trim($name)) {
                return null;
            }

            $items[] = ['side' => $side, 'name' => trim($name)];
        }

        return $items;
    }

    private function dispatchCommand(object $message): Envelope
    {
        $envelope = $this->commandBus->dispatch($message);
        $handled = $envelope->last(HandledStamp::class);

        if (null === $handled) {
            throw new \LogicException(sprintf('Command "%s" was not handled.', $message::class));
        }

        return $envelope;
    }
}
