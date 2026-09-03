<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\MatchSide;
use App\Value\OvertimeOutcome;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class SetFinalScoreFormData
{
    public const string STATE_LIVE = 'live';
    public const string STATE_FINISHED = 'finished';

    #[Assert\Choice(choices: [self::STATE_LIVE, self::STATE_FINISHED])]
    public string $state = self::STATE_FINISHED;

    #[Assert\NotNull(message: 'Zadejte prosím skóre domácích.')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Skóre nemůže být záporné.')]
    public ?int $homeScore = null;

    #[Assert\NotNull(message: 'Zadejte prosím skóre hostů.')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Skóre nemůže být záporné.')]
    public ?int $awayScore = null;

    /** @var list<PeriodScoreFormData> */
    #[Assert\Valid]
    public array $periods = [];

    public const string OVERTIME_DRAW_STANDS = 'draw';

    /**
     * The organizer's answer to ONE question, asked only on a regular-time
     * draw: did the draw stand ('draw' — leagues, groups, first legs), or who
     * won after extra time / penalties ('home' / 'away'). The stored pair is
     * derived (the draw plus one goal for the winner, Value\OvertimeOutcome);
     * nobody types a second score, so a plain draw can never run into an
     * overtime rule. A pick submitted with a NON-draw score is meaningless and
     * simply ignored (never a violation: the field is hidden then, so an error
     * would point at a control the organizer cannot see).
     */
    #[Assert\Choice(choices: [self::OVERTIME_DRAW_STANDS, 'home', 'away'], message: 'Zvolený výsledek po prodloužení není platný.')]
    public string $overtimeWinner = self::OVERTIME_DRAW_STANDS;

    /**
     * The overtime pair to store — null unless a winner was chosen AND the
     * main score is a draw.
     *
     * @var array{int, int}|null
     */
    public ?array $overtimeScorePair {
        get {
            $winner = MatchSide::tryFrom($this->overtimeWinner);

            return null === $winner || null === $this->homeScore || null === $this->awayScore || $this->homeScore !== $this->awayScore
                ? null
                : (new OvertimeOutcome($winner))->scoreAfter($this->homeScore, $this->awayScore);
        }
    }

    /** @var list<MatchEventFormData> */
    #[Assert\Valid]
    public array $events = [];

    public bool $isLastMatch = false;

    public bool $isFinishing {
        get => self::STATE_FINISHED === $this->state;
    }

    /**
     * Contiguously filled period pairs from the start, or null when no period
     * was entered at all. Assumes the Callback validation passed.
     *
     * @return list<array{int, int}>|null
     */
    public function filledPeriodPairs(): ?array
    {
        $pairs = [];

        foreach ($this->periods as $period) {
            if (!$period->isComplete) {
                break;
            }

            \assert(null !== $period->homeScore && null !== $period->awayScore);
            $pairs[] = [$period->homeScore, $period->awayScore];
        }

        return [] === $pairs ? null : $pairs;
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        $this->validatePeriods($context);
    }

    private function validatePeriods(ExecutionContextInterface $context): void
    {
        $firstEmptyIndex = null;

        foreach ($this->periods as $index => $period) {
            if (!$period->isEmpty && !$period->isComplete) {
                $context->buildViolation('Vyplňte prosím obě hodnoty, nebo nechte celou část prázdnou.')
                    ->atPath(sprintf('periods[%d].%s', $index, null === $period->homeScore ? 'homeScore' : 'awayScore'))
                    ->addViolation();

                return;
            }

            if ($period->isEmpty) {
                $firstEmptyIndex ??= $index;

                continue;
            }

            if (null !== $firstEmptyIndex) {
                $context->buildViolation('Části zápasu vyplňujte postupně od první.')
                    ->atPath(sprintf('periods[%d].homeScore', $firstEmptyIndex))
                    ->addViolation();

                return;
            }
        }

        $filled = $this->filledPeriodPairs();

        if (null === $filled || !$this->isFinishing) {
            return;
        }

        if (count($filled) !== count($this->periods)) {
            \assert(null !== $firstEmptyIndex);
            $context->buildViolation('U ukončeného zápasu vyplňte skóre všech částí, nebo je nechte celé prázdné.')
                ->atPath(sprintf('periods[%d].homeScore', $firstEmptyIndex))
                ->addViolation();

            return;
        }

        if (null === $this->homeScore || null === $this->awayScore) {
            return;
        }

        $sumHome = array_sum(array_column($filled, 0));
        $sumAway = array_sum(array_column($filled, 1));

        if ($sumHome !== $this->homeScore || $sumAway !== $this->awayScore) {
            $context->buildViolation('Součet gólů za jednotlivé části zápasu musí odpovídat konečnému skóre.')
                ->atPath('periods[0].homeScore')
                ->addViolation();
        }
    }
}
