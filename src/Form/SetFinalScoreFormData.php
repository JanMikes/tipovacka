<?php

declare(strict_types=1);

namespace App\Form;

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

    #[Assert\GreaterThanOrEqual(value: 0, message: 'Skóre nemůže být záporné.')]
    public ?int $overtimeHomeScore = null;

    #[Assert\GreaterThanOrEqual(value: 0, message: 'Skóre nemůže být záporné.')]
    public ?int $overtimeAwayScore = null;

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
        $this->validateOvertime($context);
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

    private function validateOvertime(ExecutionContextInterface $context): void
    {
        if (!$this->isFinishing) {
            return;
        }

        if ((null === $this->overtimeHomeScore) !== (null === $this->overtimeAwayScore)) {
            $context->buildViolation('Zadejte prosím obě hodnoty skóre po prodloužení.')
                ->atPath(null === $this->overtimeHomeScore ? 'overtimeHomeScore' : 'overtimeAwayScore')
                ->addViolation();

            return;
        }

        if (null === $this->overtimeHomeScore || null === $this->overtimeAwayScore) {
            return;
        }

        if (null !== $this->homeScore && null !== $this->awayScore && $this->homeScore !== $this->awayScore) {
            $context->buildViolation('Skóre po prodloužení lze zadat jen při remíze v základní hrací době.')
                ->atPath('overtimeHomeScore')
                ->addViolation();

            return;
        }

        if ($this->overtimeHomeScore === $this->overtimeAwayScore) {
            $context->buildViolation('Skóre po prodloužení nemůže být remíza.')
                ->atPath('overtimeHomeScore')
                ->addViolation();

            return;
        }

        if (null !== $this->homeScore && null !== $this->awayScore
            && ($this->overtimeHomeScore < $this->homeScore || $this->overtimeAwayScore < $this->awayScore)
        ) {
            $context->buildViolation('Skóre po prodloužení nemůže být nižší než skóre v základní hrací době.')
                ->atPath('overtimeHomeScore')
                ->addViolation();
        }
    }
}
