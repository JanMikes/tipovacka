<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\SoftDeletable;
use App\Entity\Concerns\SoftDeletes;
use App\Enum\CompetitionMatchSelectionMode;
use App\Enum\CompetitionMonetization;
use App\Event\CompetitionCreated;
use App\Event\CompetitionDeleted;
use App\Event\CompetitionEnded;
use App\Event\CompetitionMatchSelectionChanged;
use App\Event\CompetitionPinRegenerated;
use App\Event\CompetitionPinRevoked;
use App\Event\CompetitionRulesChanged;
use App\Event\CompetitionShareableLinkRegenerated;
use App\Event\CompetitionShareableLinkRevoked;
use App\Event\CompetitionTipsLocked;
use App\Event\CompetitionTipsUnlocked;
use App\Event\CompetitionUpdated;
use App\Event\PremiumConfirmed;
use App\Event\PremiumDowngraded;
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

    /**
     * Global (admin-run, publicly discoverable) competition. Global competitions
     * are joinable by any verified user by paying {@see $entryFeeCredits} (no
     * PIN/link/invite), and have on-behalf tipping + anonymous members disabled
     * (voter-level). See .docs/DOMAIN.md §Global competitions.
     */
    #[ORM\Column(options: ['default' => false])]
    public private(set) bool $isGlobal = false;

    /**
     * Credit entry fee (0 = free), charged once at join and BURNED
     * (non-refundable). Meaningful only when {@see $isGlobal}. Always ≥ 0.
     */
    #[ORM\Column(options: ['default' => 0])]
    public private(set) int $entryFeeCredits = 0;

    /**
     * When the premium per-player charges were reconciled at competition start
     * (all covered ⇒ confirmed; any uncovered ⇒ refunded + downgraded). Null
     * until reconciliation runs. Guards {@see \App\Command\ReconcilePremiumCompetitions}
     * against re-processing and stops a late uncovered join from re-downgrading.
     */
    #[ORM\Column(nullable: true)]
    public private(set) ?\DateTimeImmutable $premiumReconciledAt = null;

    /** Premium toggle: show the anonymous tip-distribution bar to everyone. */
    #[ORM\Column(options: ['default' => false])]
    public private(set) bool $premiumShowDistribution = false;

    /** Premium toggle: show concrete member tips to everyone (superset of distribution). */
    #[ORM\Column(options: ['default' => false])]
    public private(set) bool $premiumShowOthersTips = false;

    /** Premium toggle: let everyone change tips (until the tip-change offset). */
    #[ORM\Column(options: ['default' => false])]
    public private(set) bool $premiumAllowTipChanges = false;

    /**
     * When the „competition ended" notifications were sent to members (S11).
     * A one-shot guard so re-firing {@see \App\Event\MatchSourceCompleted}
     * (e.g. a source reopened + re-completed) never re-notifies the group.
     */
    #[ORM\Column(nullable: true)]
    public private(set) ?\DateTimeImmutable $endedNotifiedAt = null;

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
        bool $isGlobal = false,
        int $entryFeeCredits = 0,
    ) {
        if ($entryFeeCredits < 0) {
            throw new \InvalidArgumentException('Vstupné nesmí být záporné.');
        }

        $this->name = $name;
        $this->description = $description;
        $this->pin = $pin;
        $this->shareableLinkToken = $shareableLinkToken;
        $this->selectionMode = $selectionMode;
        $this->includePlayoff = $includePlayoff;
        $this->hideOthersTipsBeforeDeadline = $hideOthersTipsBeforeDeadline;
        $this->monetization = $monetization;
        $this->isGlobal = $isGlobal;
        $this->entryFeeCredits = $entryFeeCredits;
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
     * Admin edit of a global competition's entry fee + monetization. The
     * caller ({@see \App\Command\UpdateGlobalCompetition\UpdateGlobalCompetitionHandler})
     * refuses this once the first non-owner member has joined — from that
     * moment the fee is locked (players joined under the advertised terms).
     */
    public function updateGlobalSettings(
        int $entryFeeCredits,
        CompetitionMonetization $monetization,
        \DateTimeImmutable $now,
    ): void {
        if ($entryFeeCredits < 0) {
            throw new \InvalidArgumentException('Vstupné nesmí být záporné.');
        }

        $this->entryFeeCredits = $entryFeeCredits;
        $this->monetization = $monetization;
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

    /**
     * Premium settings (manager, only meaningful when monetization=premium):
     * the three visibility/change toggles that feed
     * {@see \App\Service\Competition\CompetitionEntitlements}, plus the
     * „Měnit tip" offset. Tuning knobs — no domain event.
     */
    public function setPremiumFeatures(
        bool $showDistribution,
        bool $showOthersTips,
        bool $allowTipChanges,
        int $tipChangeOffsetMinutes,
        \DateTimeImmutable $now,
    ): void {
        if ($tipChangeOffsetMinutes < 0) {
            throw new \InvalidArgumentException('Tip change offset must not be negative.');
        }

        $this->premiumShowDistribution = $showDistribution;
        $this->premiumShowOthersTips = $showOthersTips;
        $this->premiumAllowTipChanges = $allowTipChanges;
        $this->tipChangeOffsetMinutes = $tipChangeOffsetMinutes;
        $this->updatedAt = $now;
    }

    /**
     * Turn premium ON (re-enable anytime). Resets the reconciliation stamp so
     * the competition is reconciled again at its (next) start. The per-member
     * charges + any boost refunds are handled by
     * {@see \App\Command\EnablePremium\EnablePremiumHandler}.
     */
    public function enablePremium(\DateTimeImmutable $now): void
    {
        $this->monetization = CompetitionMonetization::Premium;
        $this->premiumReconciledAt = null;
        $this->updatedAt = $now;
    }

    /**
     * Manager switches the competition to boosts (refunds handled by the
     * caller). Not a reconciliation — leaves {@see $premiumReconciledAt} alone.
     */
    public function switchToBoosts(\DateTimeImmutable $now): void
    {
        $this->monetization = CompetitionMonetization::Boosts;
        $this->updatedAt = $now;
    }

    /**
     * Reconciliation, all charges covered: the competition stays premium and is
     * stamped reconciled. Idempotent — a second run is a no-op.
     */
    public function markPremiumReconciled(\DateTimeImmutable $now): void
    {
        if (null !== $this->premiumReconciledAt) {
            return;
        }

        $this->premiumReconciledAt = $now;
        $this->updatedAt = $now;

        $this->recordThat(new PremiumConfirmed(
            competitionId: $this->id,
            ownerId: $this->owner->id,
            occurredOn: $now,
        ));
    }

    /**
     * Reconciliation, at least one uncovered charge: the competition is
     * downgraded to boosts and stamped reconciled (the caller refunds every
     * charged row). Idempotent — a second run is a no-op.
     */
    public function downgradeToBoosts(\DateTimeImmutable $now): void
    {
        if (null !== $this->premiumReconciledAt) {
            return;
        }

        $this->monetization = CompetitionMonetization::Boosts;
        $this->premiumReconciledAt = $now;
        $this->updatedAt = $now;

        $this->recordThat(new PremiumDowngraded(
            competitionId: $this->id,
            ownerId: $this->owner->id,
            occurredOn: $now,
        ));
    }

    /**
     * S11 one-shot guard: stamps that „competition ended" notifications were
     * delivered. Idempotent. Records {@see CompetitionEnded} the first time the
     * competition is detected as over — the single moment „the competition is
     * finished" becomes a domain fact — so S12 can freeze a final leaderboard
     * snapshot independently of the S11 notification side effect.
     */
    public function markEndedNotified(\DateTimeImmutable $now): void
    {
        if (null !== $this->endedNotifiedAt) {
            return;
        }

        $this->endedNotifiedAt = $now;
        $this->updatedAt = $now;

        // One-shot: fires exactly when the competition is first detected as over,
        // so a final leaderboard snapshot can be captured (S12) independently of
        // the notification side effect (S11).
        $this->recordThat(new CompetitionEnded(
            competitionId: $this->id,
            occurredOn: $now,
        ));
    }

    /**
     * Clears the „competition ended" guard so a corrected final standing can be
     * re-sent — used when the match source is reopened (more matches to play).
     * Idempotent; records NO event (a delivery marker, not a domain fact).
     */
    public function clearEndedNotified(\DateTimeImmutable $now): void
    {
        if (null === $this->endedNotifiedAt) {
            return;
        }

        $this->endedNotifiedAt = null;
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
