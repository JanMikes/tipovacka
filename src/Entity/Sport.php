<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'sports')]
class Sport
{
    public const string FOOTBALL_ID = '01960000-0000-7000-8000-000000000001';
    public const string HOCKEY_ID = '01960000-0000-7000-8000-000000000002';

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private(set) Uuid $id,
        #[ORM\Column(length: 32, unique: true)]
        private(set) string $code,
        #[ORM\Column(length: 100)]
        private(set) string $name,
        /** Number of regular playing periods (football 2 poločasy, hockey 3 třetiny). */
        #[ORM\Column]
        private(set) int $periodCount,
        /** Czech singular period label, e.g. „poločas" / „třetina". */
        #[ORM\Column(length: 30)]
        private(set) string $periodLabelSingular,
        /** Czech plural period label, e.g. „poločasy" / „třetiny". */
        #[ORM\Column(length: 30)]
        private(set) string $periodLabelPlural,
    ) {
    }
}
