<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PremiumChargeStatus;
use App\Event\PremiumBalanceLow;
use App\Event\PremiumChargeUncovered;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * One premium per-player charge: the manager owes {@see $amount} credits for a
 * given member joining a premium competition. Charged at join; settled on
 * top-up; refunded on downgrade or a switch to boosts. See
 * .docs/DOMAIN.md §Monetization.
 *
 * Exactly one row per (competition, member) — a rejoin after leaving reuses and
 * reactivates the same row rather than piling up history.
 */
#[ORM\Entity]
#[ORM\Table(name: 'competition_premium_charges')]
#[ORM\UniqueConstraint(name: 'UIDX_premium_charges_competition_member', columns: ['competition_id', 'member_id'])]
class CompetitionPremiumCharge implements EntityWithEvents
{
    use HasEvents;

    #[ORM\Column(enumType: PremiumChargeStatus::class)]
    public private(set) PremiumChargeStatus $status;

    #[ORM\Column]
    public private(set) int $amount;

    #[ORM\Column(nullable: true)]
    public private(set) ?\DateTimeImmutable $chargedAt = null;

    #[ORM\Column(nullable: true)]
    public private(set) ?\DateTimeImmutable $refundedAt = null;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private(set) Uuid $id,
        #[ORM\ManyToOne(targetEntity: Competition::class)]
        #[ORM\JoinColumn(name: 'competition_id', referencedColumnName: 'id', nullable: false)]
        private(set) Competition $competition,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'member_id', referencedColumnName: 'id', nullable: false)]
        private(set) User $member,
        int $amount,
        #[ORM\Column]
        private(set) \DateTimeImmutable $createdAt,
    ) {
        $this->amount = $amount;
        $this->status = PremiumChargeStatus::Uncovered;
    }

    public bool $isCharged {
        get => PremiumChargeStatus::Charged === $this->status;
    }

    public bool $isUncovered {
        get => PremiumChargeStatus::Uncovered === $this->status;
    }

    /** Wallet debit succeeded — the manager has paid for this member. */
    public function markCharged(\DateTimeImmutable $now): void
    {
        $this->status = PremiumChargeStatus::Charged;
        $this->chargedAt = $now;
        $this->refundedAt = null;
    }

    /**
     * The manager's balance could not cover this charge. Records
     * {@see PremiumChargeUncovered} so S11 can notify.
     */
    public function markUncovered(\DateTimeImmutable $now): void
    {
        $this->status = PremiumChargeStatus::Uncovered;
        $this->chargedAt = null;

        $this->recordThat(new PremiumChargeUncovered(
            competitionId: $this->competition->id,
            ownerId: $this->competition->owner->id,
            memberId: $this->member->id,
            amount: $this->amount,
            occurredOn: $now,
        ));
    }

    /** Credited back to the manager (downgrade or switch-to-boosts). Idempotent. */
    public function markRefunded(\DateTimeImmutable $now): void
    {
        if (PremiumChargeStatus::Refunded === $this->status) {
            return;
        }

        $this->status = PremiumChargeStatus::Refunded;
        $this->refundedAt = $now;
    }

    /**
     * Rejoin after leaving: reuse this row for a fresh charge attempt at the
     * current price. The caller immediately follows with markCharged/markUncovered.
     */
    public function reactivate(int $amount, \DateTimeImmutable $now): void
    {
        $this->amount = $amount;
        $this->status = PremiumChargeStatus::Uncovered;
        $this->chargedAt = null;
        $this->refundedAt = null;
    }

    /**
     * After a join charge attempt the manager's wallet is low — records
     * {@see PremiumBalanceLow}. Carried on this (always-written) row so the event
     * is collected on both the charged and the uncovered path.
     */
    public function flagOwnerBalanceLow(int $balance, \DateTimeImmutable $now): void
    {
        $this->recordThat(new PremiumBalanceLow(
            competitionId: $this->competition->id,
            ownerId: $this->competition->owner->id,
            balance: $balance,
            occurredOn: $now,
        ));
    }
}
