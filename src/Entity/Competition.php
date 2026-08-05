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
use App\Exception\CompetitionTipsLockTimeInvalid;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

    /**
     * „Popis soutěže" cap (item 19). A TEXT column, so the limit is a product
     * decision, not a storage one: it is a description shown under the heading
     * of the detail page, not an article. Every write surface validates against
     * this one constant (create wizard, competition_edit, admin global create).
     */
    public const int DESCRIPTION_MAX_LENGTH = 1000;

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
     *
     * The moment IS the state: a value in the past (or now) means the tips are
     * locked, a value in the FUTURE means the manager scheduled the lock for
     * that moment ({@see scheduleTipsLock}) — no job flips anything, the
     * deadline resolver simply reaches it (B2).
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

    /**
     * The competition's match scope: an ordered list of {@see CompetitionSource}
     * layers whose UNION is „which matches are in this soutěž". Never read this
     * collection to answer that question directly — ask
     * {@see \App\Service\Competition\CompetitionMatchProvider}, the single
     * authority that all three membership implementations agree on.
     *
     * @var Collection<int, CompetitionSource>
     */
    #[ORM\OneToMany(mappedBy: 'competition', targetEntity: CompetitionSource::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $sourceLinks;

    /**
     * Legacy mirror of the FIRST layer's mode, kept only until every reader is
     * moved onto {@see $sources}. Writers must keep it in sync with layer 0.
     *
     * @deprecated read the layer ({@see CompetitionSource::$selectionMode})
     */
    #[ORM\Column(enumType: CompetitionMatchSelectionMode::class, options: ['default' => CompetitionMatchSelectionMode::All->value])]
    public private(set) CompetitionMatchSelectionMode $selectionMode = CompetitionMatchSelectionMode::All;

    /**
     * Legacy mirror of the FIRST layer's playoff flag.
     *
     * @deprecated read the layer ({@see CompetitionSource::$includePlayoff})
     */
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

    /**
     * The competition's scope layers, in display order.
     *
     * @var list<CompetitionSource>
     */
    public array $sources {
        get => array_values($this->sourceLinks->toArray());
    }

    /** More than one zdroj zápasů feeds this competition. */
    public bool $isMultiSource {
        get => $this->sourceLinks->count() > 1;
    }

    /**
     * How the soutěž's zdroje read on a card, a switcher or an invitation —
     * the headline one, plus how many others. Every surface that used to print
     * the single zdroj's name prints this instead, so a multi-source soutěž
     * never advertises only its first zdroj.
     */
    public string $sourcesLabel {
        get => self::describeSources($this->matchSource->name, $this->sourceLinks->count());
    }

    /**
     * The single home of that copy, as a static so list queries can build it
     * from a batched layer count without touching the lazy collection per row.
     */
    public static function describeSources(string $headlineSourceName, int $sourceCount): string
    {
        $others = max(0, $sourceCount - 1);

        return match (true) {
            0 === $others => $headlineSourceName,
            $others < 5 => sprintf('%s a %d další', $headlineSourceName, $others),
            default => sprintf('%s a %d dalších', $headlineSourceName, $others),
        };
    }

    /**
     * „The schedule is known-complete" — every zdroj feeding this competition
     * has been marked completed („poslední zápas"). This, plus „no included
     * match is still unsettled", is what ends a competition; with several
     * layers a single un-finished source keeps the whole soutěž open, exactly
     * as the single-source rule did before.
     */
    public bool $scheduleIsComplete {
        get {
            if (0 === $this->sourceLinks->count()) {
                return false;
            }

            foreach ($this->sourceLinks as $link) {
                if (!$link->matchSource->isCompleted) {
                    return false;
                }
            }

            return true;
        }
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
        $this->sourceLinks = new ArrayCollection();
        $this->updatedAt = $this->createdAt;

        $this->recordThat(new CompetitionCreated(
            competitionId: $this->id,
            matchSourceId: $this->matchSource->id,
            ownerId: $this->owner->id,
            name: $this->name,
            occurredOn: $this->createdAt,
        ));
    }

    /**
     * Attaches a scope layer, keeping the in-memory collection authoritative for
     * the rest of the transaction (the provider reads it straight after a
     * competition is composed). The legacy {@see $selectionMode} /
     * {@see $includePlayoff} mirror follows the FIRST layer.
     */
    public function attachSource(CompetitionSource $source): void
    {
        if ($this->sourceLinks->contains($source)) {
            return;
        }

        $this->sourceLinks->add($source);
        $this->syncLegacyScopeMirror();
    }

    public function detachSource(CompetitionSource $source): void
    {
        $this->sourceLinks->removeElement($source);
        $this->syncLegacyScopeMirror();
    }

    /** The layer feeding this competition from the given zdroj, if any. */
    public function sourceFor(Uuid $matchSourceId): ?CompetitionSource
    {
        foreach ($this->sourceLinks as $link) {
            if ($link->matchSource->id->equals($matchSourceId)) {
                return $link;
            }
        }

        return null;
    }

    /**
     * Re-points the legacy scope mirror at the first layer. Called on every
     * layer mutation; drops out with the two deprecated columns.
     */
    public function syncLegacyScopeMirror(): void
    {
        $first = $this->sources[0] ?? null;

        if (null === $first) {
            return;
        }

        $this->selectionMode = $first->selectionMode;
        $this->includePlayoff = $first->includePlayoff;
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
     * Manual „Uzamknout tipy · Ihned": from this moment the competition counts
     * as started for tip locking. Idempotent — locking an already locked
     * competition keeps the original lock moment. For the „V určený čas"
     * variant see {@see scheduleTipsLock}; a pending schedule is overwritten
     * here (locking now always wins over a later moment).
     */
    public function lockTips(\DateTimeImmutable $now): void
    {
        // Already locked ⇒ keep the original moment. A lock moment still ahead
        // is only a SCHEDULE, not a lock, so „Ihned" overwrites it.
        if (null !== $this->tipsLockedAt && $this->tipsLockedAt <= $now) {
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
     * „Uzamknout tipy · V určený čas" (B2): park the lock moment in the future
     * instead of locking now. Nothing has to run at that moment — `tipsLockedAt`
     * is already THE lock moment for {@see \App\Service\EffectiveTipDeadlineResolver},
     * so every match deadline becomes `min(lockAt, kickoff)` the second it is
     * stored, and the lock „fires" simply by time passing.
     *
     * Rules: the moment must be in the future (locking now = {@see lockTips})
     * and strictly before the competition's start, i.e. the first included
     * kickoff — a later moment would push the lock BEYOND the automatic one and
     * reopen tips that the competition start already closed. Re-schedulable and
     * cancellable ({@see unlockTips}) while it has not fired.
     *
     * Records NO event: nothing has locked yet. `CompetitionTipsLocked` stays
     * reserved for the moment tips actually close, which for a scheduled lock is
     * an instant nobody dispatches (deliberately — see .docs/ui-nav/BUGS.md B2).
     */
    public function scheduleTipsLock(
        \DateTimeImmutable $lockAt,
        \DateTimeImmutable $now,
        ?\DateTimeImmutable $firstKickoffAt,
    ): void {
        if ($lockAt <= $now) {
            throw CompetitionTipsLockTimeInvalid::notInFuture();
        }

        if (null !== $this->tipsLockedAt && $this->tipsLockedAt <= $now) {
            throw CompetitionTipsLockTimeInvalid::alreadyLocked();
        }

        if (null !== $firstKickoffAt && $lockAt >= $firstKickoffAt) {
            throw CompetitionTipsLockTimeInvalid::afterCompetitionStart();
        }

        $this->tipsLockedAt = $lockAt;
        $this->updatedAt = $now;
    }

    /**
     * Reverts a manual lock — also the „zrušit naplánované uzamčení" path, since
     * a pending schedule is just a lock moment that has not been reached yet.
     * Allowed only while the competition has not really started yet — i.e.
     * before the first included match kicks off (the caller passes that moment
     * in; null = the competition has no scheduled matches).
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
