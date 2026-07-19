<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\BoostType;
use App\Event\BoostRefunded;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * One boost a player bought in a `boosts`-monetized competition. The wallet
 * debit ({@see CreditWallet::spend}) and this row are written
 * together in one transaction. Refunded (credited back) only when the manager
 * re-enables premium. See .docs/DOMAIN.md §Monetization.
 *
 * At most one ACTIVE row per (user, competition, type): the partial unique index
 * ignores refunded rows, so a boost that was refunded can be bought again.
 */
#[ORM\Entity]
#[ORM\Table(name: 'boost_purchases')]
#[ORM\UniqueConstraint(
    name: 'UIDX_boost_purchases_active',
    columns: ['user_id', 'competition_id', 'type'],
    options: ['where' => '(refunded_at IS NULL)'],
)]
class BoostPurchase implements EntityWithEvents
{
    use HasEvents;

    #[ORM\Column(nullable: true)]
    public private(set) ?\DateTimeImmutable $refundedAt = null;

    public bool $isActive {
        get => null === $this->refundedAt;
    }

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private(set) Uuid $id,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
        private(set) User $user,
        #[ORM\ManyToOne(targetEntity: Competition::class)]
        #[ORM\JoinColumn(name: 'competition_id', referencedColumnName: 'id', nullable: false)]
        private(set) Competition $competition,
        #[ORM\Column(enumType: BoostType::class)]
        private(set) BoostType $type,
        #[ORM\Column]
        private(set) int $pricePaid,
        #[ORM\Column]
        private(set) \DateTimeImmutable $purchasedAt,
    ) {
    }

    /**
     * Credit the buyer back (manager re-enabled premium). Idempotent — refunding
     * an already refunded purchase does nothing. Records {@see BoostRefunded} so
     * S11 can notify.
     */
    public function refund(\DateTimeImmutable $now): void
    {
        if (null !== $this->refundedAt) {
            return;
        }

        $this->refundedAt = $now;

        $this->recordThat(new BoostRefunded(
            competitionId: $this->competition->id,
            userId: $this->user->id,
            boostType: $this->type->value,
            amount: $this->pricePaid,
            occurredOn: $now,
        ));
    }
}
