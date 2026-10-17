<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\MatchSource;
use App\Entity\Sport;
use App\Enum\CompetitionMonetization;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class MatchSourceFormData
{
    #[Assert\NotNull(message: 'Vyberte prosím sport.')]
    public ?Sport $sport = null;

    #[Assert\NotBlank(message: 'Zadejte prosím název zdroje zápasů.')]
    #[Assert\Length(
        max: 160,
        maxMessage: 'Název zdroje zápasů nesmí být delší než {{ limit }} znaků.',
    )]
    public string $name = '';

    /** The column is TEXT (unbounded); the cap only keeps a pasted novel out of the zdroj header. */
    #[Assert\Length(max: 2000, maxMessage: 'Popis může mít nejvýš {{ limit }} znaků — zkraťte ho prosím.')]
    public ?string $description = null;

    public ?\DateTimeImmutable $startAt = null;

    public ?\DateTimeImmutable $endAt = null;

    /** Cups / play-offs: a drawn match may go on to extra time or penalties. */
    public bool $hasOvertime = false;

    /** Optional „Rovnou vytvořit globální soutěž" step (admin curated create only). */
    public bool $createGlobalCompetition = false;

    #[Assert\Length(max: 160, maxMessage: 'Název soutěže nesmí být delší než {{ limit }} znaků.')]
    public ?string $globalCompetitionName = null;

    #[Assert\GreaterThanOrEqual(value: 0, message: 'Vstupné nesmí být záporné.')]
    public int $globalCompetitionEntryFee = 0;

    public CompetitionMonetization $globalCompetitionMonetization = CompetitionMonetization::None;

    public static function fromMatchSource(MatchSource $matchSource): self
    {
        $formData = new self();
        $formData->sport = $matchSource->sport;
        $formData->name = $matchSource->name;
        $formData->description = $matchSource->description;
        $formData->startAt = $matchSource->startAt;
        $formData->endAt = $matchSource->endAt;
        $formData->hasOvertime = $matchSource->hasOvertime;

        return $formData;
    }

    #[Assert\Callback]
    public function validatePeriod(ExecutionContextInterface $context): void
    {
        if (null !== $this->startAt && null !== $this->endAt && $this->endAt <= $this->startAt) {
            $context->buildViolation('Konec musí být po začátku — upravte jedno z dat.')
                ->atPath('endAt')
                ->addViolation();
        }
    }
}
