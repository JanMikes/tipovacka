<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Player;
use App\Enum\MatchEventType;
use App\Enum\MatchSide;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One event row (gól / karta) of the score-entry form.
 */
final class MatchEventFormData
{
    #[Assert\NotNull(message: 'Vyberte prosím typ události.')]
    public ?MatchEventType $type = MatchEventType::Goal;

    #[Assert\NotNull(message: 'Vyberte prosím tým.')]
    public ?MatchSide $side = null;

    #[Assert\Range(notInRangeMessage: 'Minuta musí být mezi {{ min }} a {{ max }}.', min: 0, max: 150)]
    public ?int $minute = null;

    #[Assert\NotBlank(message: 'Zadejte prosím jméno hráče.')]
    #[Assert\Length(max: Player::NAME_MAX_LENGTH, maxMessage: 'Jméno hráče nesmí být delší než {{ limit }} znaků.')]
    public string $playerName = '';
}
