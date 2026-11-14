<?php

declare(strict_types=1);

namespace App\Value;

use Symfony\Component\Uid\Uuid;

/**
 * One soutěž inside a digest mail: what is missing there and what happened
 * there. A section without a soutěž (`$competitionId` null) collects the news
 * that belong to no soutěž.
 */
final readonly class EmailDigestSection
{
    /**
     * @param list<EmailItem> $items what happened, oldest first
     */
    public function __construct(
        public ?Uuid $competitionId,
        public ?string $competitionName,
        /** Host-relative path of the soutěž; null for the section without one. */
        public ?string $competitionUrl,
        /** The „chybí natipovat" line of this soutěž, when it has one. */
        public ?EmailItem $missingTips,
        public array $items,
        /** How many more news there were than {@see $items} shows. */
        public int $hiddenItemCount = 0,
    ) {
    }
}
