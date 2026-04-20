<?php

declare(strict_types=1);

namespace App\Entity\Concerns;

interface SoftDeletable
{
    public ?\DateTimeImmutable $deletedAt { get; }

    public function markDeleted(\DateTimeImmutable $now): void;

    public function isDeleted(): bool;
}
