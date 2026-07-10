<?php

declare(strict_types=1);

namespace App\Form;

use App\Command\InitiateCreditPurchase\InitiateCreditPurchaseCommand;
use Symfony\Component\Validator\Constraints as Assert;

final class BuyCreditsFormData
{
    #[Assert\NotNull(message: 'Zadejte prosím počet kreditů.')]
    #[Assert\GreaterThanOrEqual(
        value: InitiateCreditPurchaseCommand::MINIMUM_CREDITS,
        message: 'Minimální nákup je {{ compared_value }} kreditů.',
    )]
    #[Assert\LessThanOrEqual(
        value: InitiateCreditPurchaseCommand::MAXIMUM_CREDITS,
        message: 'Maximální nákup je {{ compared_value }} kreditů.',
    )]
    public ?int $credits = InitiateCreditPurchaseCommand::MINIMUM_CREDITS;
}
