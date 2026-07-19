<?php

declare(strict_types=1);

namespace App\Command\EnablePremium;

use Symfony\Component\Uid\Uuid;

/**
 * Manager/admin turns premium ON (anytime). Charges the whole current group at
 * once — all-or-nothing. See .docs/DOMAIN.md §Monetization.
 */
final readonly class EnablePremiumCommand
{
    public function __construct(
        public Uuid $editorId,
        public Uuid $competitionId,
    ) {
    }
}
