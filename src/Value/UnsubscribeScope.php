<?php

declare(strict_types=1);

namespace App\Value;

use App\Enum\NotificationType;
use Symfony\Component\Uid\Uuid;

/**
 * WHAT an unsubscribe link switches off — one notification type, one soutěž,
 * or every notification e-mail. Travels in the URL as a short slug
 * ({@see toSlug} / {@see fromSlug}); the signed part of the link identifies the
 * player, never the scope, so a tampered scope can at worst silence something
 * else of the same player's.
 */
final class UnsubscribeScope
{
    private const string ALL = 'vse';
    private const string TYPE_PREFIX = 'typ-';
    private const string COMPETITION_PREFIX = 'soutez-';

    private function __construct(
        public readonly ?NotificationType $type,
        public readonly ?Uuid $competitionId,
    ) {
    }

    public static function all(): self
    {
        return new self(null, null);
    }

    public static function type(NotificationType $type): self
    {
        return new self($type, null);
    }

    public static function competition(Uuid $competitionId): self
    {
        return new self(null, $competitionId);
    }

    public static function fromSlug(string $slug): ?self
    {
        if (self::ALL === $slug) {
            return self::all();
        }

        if (str_starts_with($slug, self::TYPE_PREFIX)) {
            $type = NotificationType::tryFrom(substr($slug, strlen(self::TYPE_PREFIX)));

            return null !== $type ? self::type($type) : null;
        }

        if (str_starts_with($slug, self::COMPETITION_PREFIX)) {
            $id = substr($slug, strlen(self::COMPETITION_PREFIX));

            return Uuid::isValid($id) ? self::competition(Uuid::fromString($id)) : null;
        }

        return null;
    }

    public bool $isAll {
        get => null === $this->type && null === $this->competitionId;
    }

    public function toSlug(): string
    {
        if (null !== $this->type) {
            return self::TYPE_PREFIX.$this->type->value;
        }

        if (null !== $this->competitionId) {
            return self::COMPETITION_PREFIX.$this->competitionId->toRfc4122();
        }

        return self::ALL;
    }
}
