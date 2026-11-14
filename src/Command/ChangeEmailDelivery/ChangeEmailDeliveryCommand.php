<?php

declare(strict_types=1);

namespace App\Command\ChangeEmailDelivery;

use App\Enum\EmailFrequency;
use Symfony\Component\Uid\Uuid;

/**
 * The player chose how often they want notification e-mails. `$digestHour`
 * null = keep whatever hour they have (the default when they never set one).
 */
final readonly class ChangeEmailDeliveryCommand
{
    public function __construct(
        public Uuid $userId,
        public EmailFrequency $frequency,
        public ?int $digestHour = null,
    ) {
    }
}
