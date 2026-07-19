<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\SoftDeletable;
use App\Entity\Concerns\SoftDeletes;
use App\Enum\CompetitionMatchSelectionMode;
use App\Enum\CompetitionMonetization;
use App\Event\CompetitionCreated;
use App\Event\CompetitionDeleted;
use App\Event\CompetitionMatchSelectionChanged;
use App\Event\CompetitionPinRegenerated;
use App\Event\CompetitionPinRevoked;
use App\Event\CompetitionRulesChanged;
use App\Event\CompetitionShareableLinkRegenerated;
use App\Event\CompetitionShareableLinkRevoked;
use App\Event\CompetitionTipsLocked;
use App\Event\CompetitionTipsUnlocked;
use App\Event\CompetitionUpdated;
use App\Exception\CompetitionTipsCannotBeUnlocked;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'competitions')]
#[ORM\Index(columns: ['match_source_id', 'deleted_at'], name: 'IDX_competitions_match_source')]
#[ORM\Index(columns: ['owner_id', 'deleted_at'], name: 'IDX_competitions_owner')]
#[ORM\UniqueConstraint(name: 'UIDX_competitions_pin', columns: ['pin'], options: ['where' => '(pin IS NOT NULL)'])]
#[ORM\UniqueConstraint(name: 'UIDX_competitions_shareable_link_token', columns: ['shareable_link_token'], options: ['where' => '(shareable_link_token IS NOT NULL)'])]
class Competition implements EntityWithEvents, SoftDeletable
{
    use HasEvents;
    use SoftDeletes;

    #[ORM\Column(length: 160)]
    public private(set) string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public private(set) ?string $description;

    #[ORM\Column(length: 8, nullable: true)]
    public private(set) ?string $pin;

    #[ORM\Column(length: 48, nullable: true)]
    public private(set) ?string $shareableLinkToken;

    #[ORM\Column]
    public private(set) bool $hideOthersTipsBeforeDeadline = false;

    /**
     * Manual lock moment („Uzamknout tipy"). When null, the competition's tips
     * lock automatically at the earliest kickoff among its included matches
     * (computed live by {@see \App\Service\EffectiveTipDeadlineResolver}).
     */
    #[ORM\Column(nullable: true)]
    public private(set) ?\DateTimeImmutable $tipsLockedAt = null;

    /**
     * „Měnit tip" entitlement offset: entitled members may change tips until
     * this many minutes before the day's first competition match. Editable by
     * managers on premium competitions only (S10); until then the default holds.
     */
    #[ORM\Column(options: ['default' => 60])]
    public private(set) int $tipChangeOffsetMinutes = 60;

    /** All ⇒ every source match belongs here; Subset ⇒ only `CompetitionMatchSelection` rows. */
    #[ORM\Column(enumType: CompetitionMatchSelectionMode::class, options: ['default' => CompetitionMatchSelectionMode::All->value])]
    public private(set) CompetitionMatchSelectionMode $selectionMode = CompetitionMatchSelectionMode::All;

    /** Only meaningful in All mode — Subset selections always win over this flag. */
    #[ORM\Column(options: ['default' => true])]
    public private(set) bool $includePlayoff = true;

    /**
     * Premium XOR boosts (XOR by column). Set by the create-competition wizard
     * (premium|boosts); admin/global competitions default None. S08 stores intent
     * only — charging goes live in S10. See .docs/DOMAIN.md §Monetization.
     */
    #[ORM\Column(enumType: CompetitionMonetization::class, options: ['default' => CompetitionMonetization::None->value])]
    public private(set) CompetitionMonetization $monetization = CompetitionMonetization::None;

    #[ORM\Column]
    public private(set) \DateTimeImmutable $updatedAt;

    public bool $isNotDeleted {
        get => null === $this->deletedAt;
    }

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private(set) Uuid $id,
        #[ORM\ManyToOne(targetEntity: MatchSource::class)]
        #[ORM\JoinColumn(name: 'match_source_id', referencedColumnName: 'id', nullable: false)]
        private(set) MatchSource $matchSource,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'owner_id', referencedColumnName: 'id', nullable: false)]
        private(set) User $owner,
        string $name,
        ?string $description,
        ?string $pin,
        ?string $shareableLinkToken,
        #[ORM\Column]
        private(set) \DateTimeImmutable $createdAt,
        CompetitionMatchSelectionMode $selectionMode = CompetitionMatchSelectionMode::All,
        bool $includePlayoff = true,
        bool $hideOthersTipsBeforeDeadline = false,
        CompetitionMonetization $monetization = CompetitionMonetization::None,
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->pin = $pin;
        $this->shareableLinkToken = $shareableLinkToken;
        $this->selectionMode = $selectionMode;
        $this->includePlayoff = $includePlayoff;
        $this->hideOthersTipsBeforeDeadline = $hideOthersTipsBeforeDeadline;
        $this->monetization = $monetization;
        $this->updatedAt = $this->createdAt;

        $this->recordThat(new CompetitionCreated(
            competitionId: $this->id,
            matchSourceId: $this->matchSource->id,
            ownerId: $this->owner->id,
            name: $this->name,
            occurredOn: $this->createdAt,
        ));
    }

    public function updateDetails(
        string $name,
        ?string $description,
        bool $hideOthersTipsBeforeDeadline,
        \DateTimeImmutable $now,
    ): void {
        $this->name = $name;
        $this->description = $description;
        $this->hideOthersTipsBeforeDeadline = $hideOthersTipsBeforeDeadline;
        $this->updatedAt = $now;

        $this->recordThat(new CompetitionUpdated(
            competitionId: $this->id,
            occurredOn: $now,
        ));
    }

    /**
     * Manual „Uzamknout tipy": from this moment the competition counts as
     * started for tip locking. Idempotent — locking an already locked
     * competition keeps the original lock moment.
     */
    public function lockTips(\DateTimeImmutable $now): void
    {
        if (null !== $this->tipsLockedAt) {
            return;
        }

        $this->tipsLockedAt = $now;
        $this->updatedAt = $now;

        $this->recordThat(new CompetitionTipsLocked(
            competitionId: $this->id,
            occurredOn: $now,
        ));
    }

    /**
     * Reverts a manual lock. Allowed only while the competition has not really
     * started yet — i.e. before the first included match kicks off (the caller
     * passes that moment in; null = the competition has no scheduled matches).
     * Unlocking an unlocked competition is a no-op.
     */
    public function unlockTips(\DateTimeImmutable $now, ?\DateTimeImmutable $firstKickoffAt): void
    {
        if (null === $this->tipsLockedAt) {
            return;
        }

        if (null !== $firstKickoffAt && $now >= $firstKickoffAt) {
            throw CompetitionTipsCannotBeUnlocked::afterCompetitionStart();
        }

        $this->tipsLockedAt = null;
        $this->updatedAt = $now;

        $this->recordThat(new CompetitionTipsUnlocked(
            competitionId: $this->id,
            occurredOn: $now,
        ));
    }

    /**
     * Pins the automatic lock moment onto `tipsLockedAt` when the match that
     * defined it (the competition's earliest included kickoff) is postponed
     * later or soft-deleted after the moment already passed. Without it the
     * live first-kickoff recomputation would jump forward and reopen already
     * closed tips (see {@see \App\Service\EffectiveTipDeadlineResolver::lockMomentToPinAfterDefiningMatchLeft}).
     * Idempotent; records NO event — a correctness pin, not a manager's
     * „Uzamknout tipy" action.
     */
    public function pinTipsLockMoment(\DateTimeImmutable $lockMoment, \DateTimeImmutable $now): void
    {
        if (null !== $this->tipsLockedAt) {
            return;
        }

        $this->tipsLockedAt = $lockMoment;
        $this->updatedAt = $now;
    }

    /**
     * S10 territory (premium settings) — kept here so the offset has a single
     * mutation path once the premium UI lands. No event: it is a tuning knob,
     * not a domain fact.
     */
    public function changeTipChangeOffset(int $minutes, \DateTimeImmutable $now): void
    {
        if ($minutes < 0) {
            throw new \InvalidArgumentException('Tip change offset must not be negative.');
        }

        $this->tipChangeOffsetMinutes = $minutes;
        $this->updatedAt = $now;
    }

    public function recordMatchSelectionChanged(User $editor, \DateTimeImmutable $now): void
    {
        $this->updatedAt = $now;

        $this->recordThat(new CompetitionMatchSelectionChanged(
            competitionId: $this->id,
            changedByUserId: $editor->id,
            occurredOn: $now,
        ));
    }

    public function recordRulesChanged(User $editor, \DateTimeImmutable $now): void
    {
        $this->updatedAt = $now;

        $this->recordThat(new CompetitionRulesChanged(
            competitionId: $this->id,
            changedByUserId: $editor->id,
            occurredOn: $now,
        ));
    }

    public function setPin(string $pin, \DateTimeImmutable $now): void
    {
        $this->pin = $pin;
        $this->updatedAt = $now;

        $this->recordThat(new CompetitionPinRegenerated(
            competitionId: $this->id,
            occurredOn: $now,
        ));
    }

    public function revokePin(\DateTimeImmutable $now): void
    {
        if (null === $this->pin) {
            return;
        }

        $this->pin = null;
        $this->updatedAt = $now;

        $this->recordThat(new CompetitionPinRevoked(
            competitionId: $this->id,
            occurredOn: $now,
        ));
    }

    public function setShareableLinkToken(string $token, \DateTimeImmutable $now): void
    {
        $this->shareableLinkToken = $token;
        $this->updatedAt = $now;

        $this->recordThat(new CompetitionShareableLinkRegenerated(
            competitionId: $this->id,
            occurredOn: $now,
        ));
    }

    public function revokeShareableLinkToken(\DateTimeImmutable $now): void
    {
        if (null === $this->shareableLinkToken) {
            return;
        }

        $this->shareableLinkToken = null;
        $this->updatedAt = $now;

        $this->recordThat(new CompetitionShareableLinkRevoked(
            competitionId: $this->id,
            occurredOn: $now,
        ));
    }

    public function softDelete(\DateTimeImmutable $now): void
    {
        if (null !== $this->deletedAt) {
            return;
        }

        $this->markDeleted($now);
        $this->updatedAt = $now;

        $this->recordThat(new CompetitionDeleted(
            competitionId: $this->id,
            ownerId: $this->owner->id,
            name: $this->name,
            occurredOn: $now,
        ));
    }
}
