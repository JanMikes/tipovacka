<?php

declare(strict_types=1);

namespace App\Entity\Concerns;

use Doctrine\ORM\Mapping as ORM;

trait SoftDeletes
{
    #[ORM\Column(nullable: true)]
    public private(set) ?\DateTimeImmutable $deletedAt = null;

    public function markDeleted(\DateTimeImmutable $now): void
    {
        $this->deletedAt = $now;
    }

    public function isDeleted(): bool
    {
        return null !== $this->deletedAt;
    }
}
