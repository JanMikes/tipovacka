<?php

declare(strict_types=1);

namespace App\Form;

use App\Command\InitiateCreditPurchase\InitiateCreditPurchaseCommand;
use Symfony\Component\Validator\Constraints as Assert;

final class BuyCreditsFormData
{
    #[Assert\NotNull(message: 'Zadejte prosím, kolik kreditů chcete dobít — nejméně '.InitiateCreditPurchaseCommand::MINIMUM_CREDITS.'.')]
    #[Assert\GreaterThanOrEqual(
        value: InitiateCreditPurchaseCommand::MINIMUM_CREDITS,
        message: 'Minimální nákup je {{ compared_value }} kreditů ({{ compared_value }} Kč) — zadejte prosím {{ compared_value }} nebo víc.',
    )]
    #[Assert\LessThanOrEqual(
        value: InitiateCreditPurchaseCommand::MAXIMUM_CREDITS,
        message: 'Najednou lze dobít nejvýše {{ compared_value }} kreditů — zadejte prosím menší počet, nebo nákup rozdělte do více plateb.',
    )]
    public ?int $credits = InitiateCreditPurchaseCommand::MINIMUM_CREDITS;
}
