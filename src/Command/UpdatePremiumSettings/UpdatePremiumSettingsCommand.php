<?php

declare(strict_types=1);

namespace App\Command\UpdatePremiumSettings;

use Symfony\Component\Uid\Uuid;

/**
 * Manager saves the premium feature toggles + the „Měnit tip" offset. See
 * .docs/DOMAIN.md §Monetization / §Tips visibility.
 */
final readonly class UpdatePremiumSettingsCommand
{
    public function __construct(
        public Uuid $editorId,
        public Uuid $competitionId,
        public bool $showDistribution,
        public bool $showOthersTips,
        public bool $allowTipChanges,
        public int $tipChangeOffsetMinutes,
    ) {
    }
}
