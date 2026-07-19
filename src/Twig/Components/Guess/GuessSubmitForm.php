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
use App\Repository\CompetitionRepository;
use App\Repository\GuessRepository;
use App\Repository\PlayerRepository;
use App\Service\Competition\CompetitionGuessFeatures;
use App\Service\Competition\GuessFeatures;
use App\Service\EffectiveTipDeadlineResolver;
use App\Value\GuessScorerInput;
use App\Value\PeriodScores;
use Psr\Clock\ClockInterface;
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

    #[LiveProp]
    public SportMatch $sportMatch;

    #[LiveProp]
    public string $competitionId = '';

    #[LiveProp(writable: true)]
    #[Assert\GreaterThanOrEqual(0)]
    #[Assert\LessThanOrEqual(99)]
    public ?int $homeScore = null;

    #[LiveProp(writable: true)]
    #[Assert\GreaterThanOrEqual(0)]
    #[Assert\LessThanOrEqual(99)]
    public ?int $awayScore = null;

    #[LiveProp(writable: true)]
    #[Assert\GreaterThanOrEqual(0)]
    #[Assert\LessThanOrEqual(99)]
    public ?int $period1Home = null;

    #[LiveProp(writable: true)]
    #[Assert\GreaterThanOrEqual(0)]
    #[Assert\LessThanOrEqual(99)]
    public ?int $period1Away = null;

    #[LiveProp(writable: true)]
    #[Assert\GreaterThanOrEqual(0)]
    #[Assert\LessThanOrEqual(99)]
    public ?int $period2Home = null;

    #[LiveProp(writable: true)]
    #[Assert\GreaterThanOrEqual(0)]
    #[Assert\LessThanOrEqual(99)]
    public ?int $period2Away = null;

    #[LiveProp(writable: true)]
    #[Assert\GreaterThanOrEqual(0)]
    #[Assert\LessThanOrEqual(99)]
    public ?int $period3Home = null;

    #[LiveProp(writable: true)]
    #[Assert\GreaterThanOrEqual(0)]
    #[Assert\LessThanOrEqual(99)]
    public ?int $period3Away = null;

    #[LiveProp(writable: true)]
    #[Assert\GreaterThanOrEqual(0)]
    #[Assert\LessThanOrEqual(99)]
    public ?int $overtimeHomeScore = null;

    #[LiveProp(writable: true)]
    #[Assert\GreaterThanOrEqual(0)]
    #[Assert\LessThanOrEqual(99)]
    public ?int $overtimeAwayScore = null;

    /** JSON list of {side: 'home'|'away', name: string} written by the scorer-picker Stimulus controller. */
    #[LiveProp(writable: true)]
    public string $scorersJson = '[]';

    #[LiveProp]
    public ?string $errorMessage = null;

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
        $this->overtimeHomeScore = $existing->overtimeHomeScore;
        $this->overtimeAwayScore = $existing->overtimeAwayScore;

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
            if (!$this->sportMatch->isOpenForGuesses) {
                return true;
            }

            $now = \DateTimeImmutable::createFromInterface($this->clock->now());
            $competition = $this->competitionRepository->get(Uuid::fromString($this->competitionId));
            $deadline = $this->deadlineResolver->resolve($competition, $this->sportMatch);

            return $now >= $deadline;
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

    public bool $showOvertimeInputs {
        get => $this->features->overtimeTip
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
            $sourceId = $this->sportMatch->matchSource->id;

            return [
                [
                    'side' => MatchSide::Home->value,
                    'team' => $this->sportMatch->homeTeam,
                    'players' => array_map(
                        static fn (Player $player): string => $player->name,
                        $this->playerRepository->listBySourceAndTeam($sourceId, $this->sportMatch->homeTeam),
                    ),
                ],
                [
                    'side' => MatchSide::Away->value,
                    'team' => $this->sportMatch->awayTeam,
                    'players' => array_map(
                        static fn (Player $player): string => $player->name,
                        $this->playerRepository->listBySourceAndTeam($sourceId, $this->sportMatch->awayTeam),
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

    #[LiveAction]
    public function submit(): void
    {
        $this->errorMessage = null;
        $this->successMessage = null;

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            $this->errorMessage = 'Musíte být přihlášeni.';

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
                $this->successMessage = 'Tip smazán.';

                return;
            }

            if (null === $homeScore || null === $awayScore) {
                $this->errorMessage = 'Vyplňte prosím oba tipy.';

                return;
            }

            $this->validate();

            $periodScores = $this->buildPeriodScores();

            if (false === $periodScores) {
                return; // errorMessage set.
            }

            $scorers = $this->buildScorers();

            if (null === $scorers) {
                return; // errorMessage set.
            }

            // The overtime tip travels only while the inputs are visible (rule
            // enabled + draw tipped) — stale values from a previously tipped
            // draw must never reach the handler.
            $overtimeHome = $this->showOvertimeInputs ? $this->overtimeHomeScore : null;
            $overtimeAway = $this->showOvertimeInputs ? $this->overtimeAwayScore : null;

            if (null === $existing) {
                $this->dispatchCommand(new SubmitGuessCommand(
                    userId: $user->id,
                    competitionId: Uuid::fromString($this->competitionId),
                    sportMatchId: $this->sportMatch->id,
                    homeScore: $homeScore,
                    awayScore: $awayScore,
                    periodScores: $periodScores,
                    overtimeHomeScore: $overtimeHome,
                    overtimeAwayScore: $overtimeAway,
                    scorers: $scorers,
                ));
                $this->successMessage = 'Tip uložen.';
            } else {
                $this->dispatchCommand(new UpdateGuessCommand(
                    userId: $user->id,
                    guessId: $existing->id,
                    homeScore: $homeScore,
                    awayScore: $awayScore,
                    periodScores: $periodScores,
                    overtimeHomeScore: $overtimeHome,
                    overtimeAwayScore: $overtimeAway,
                    scorers: $scorers,
                ));
                $this->successMessage = 'Tip upraven.';
            }
        } catch (HandlerFailedException $e) {
            $previous = $e->getPrevious();
            $this->errorMessage = null !== $previous ? $previous->getMessage() : $e->getMessage();

            return;
        } catch (\DomainException|\RuntimeException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }
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
    private function buildPeriodScores(): PeriodScores|false|null
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
            $this->errorMessage = sprintf('Vyplňte prosím všechny %s, nebo je nechte prázdné.', $this->sport->periodLabelPlural);

            return false;
        }

        return PeriodScores::fromArray($pairs);
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
            $this->errorMessage = 'Tip na střelce se nepodařilo zpracovat, zkuste to prosím znovu.';

            return null;
        }

        $inputs = [];

        foreach ($decoded as $item) {
            if (mb_strlen($item['name']) > Player::NAME_MAX_LENGTH) {
                $this->errorMessage = sprintf('Jméno hráče nesmí být delší než %d znaků.', Player::NAME_MAX_LENGTH);

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
