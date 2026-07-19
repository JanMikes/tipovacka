<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\SoftDeletable;
use App\Entity\Concerns\SoftDeletes;
use App\Enum\SportMatchState;
use App\Event\GuessesEvaluatedForMatch;
use App\Event\SportMatchCancelled;
use App\Event\SportMatchCreated;
use App\Event\SportMatchDeleted;
use App\Event\SportMatchFinished;
use App\Event\SportMatchLive;
use App\Event\SportMatchLiveScoreChanged;
use App\Event\SportMatchPostponed;
use App\Event\SportMatchScoreUpdated;
use App\Event\SportMatchUpdated;
use App\Exception\InvalidScore;
use App\Exception\SportMatchCannotBeEdited;
use App\Exception\SportMatchInvalidTransition;
use App\Value\PeriodScores;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'sport_matches')]
#[ORM\Index(columns: ['match_source_id', 'kickoff_at', 'deleted_at'], name: 'IDX_sport_matches_match_source_kickoff')]
#[ORM\Index(columns: ['state', 'kickoff_at', 'deleted_at'], name: 'IDX_sport_matches_state_kickoff')]
class SportMatch implements EntityWithEvents, SoftDeletable
{
    use HasEvents;
    use SoftDeletes;

    #[ORM\Column(length: 120)]
    public private(set) string $homeTeam;

    #[ORM\Column(length: 120)]
    public private(set) string $awayTeam;

    #[ORM\Column]
    public private(set) \DateTimeImmutable $kickoffAt;

    #[ORM\Column(length: 160, nullable: true)]
    public private(set) ?string $venue;

    /** Round / stage label, e.g. „Skupina A", „Čtvrtfinále". Free text, optional. */
    #[ORM\Column(length: 120, nullable: true)]
    public private(set) ?string $round;

    /** Playoff matches can be excluded per competition (`Competition::$includePlayoff`). */
    #[ORM\Column(options: ['default' => false])]
    public private(set) bool $isPlayoff = false;

    #[ORM\Column(enumType: SportMatchState::class)]
    public private(set) SportMatchState $state;

    #[ORM\Column(nullable: true)]
    public private(set) ?int $homeScore = null;

    #[ORM\Column(nullable: true)]
    public private(set) ?int $awayScore = null;

    /**
     * Raw per-period [home, away] pairs; exposed through the $periodScores
     * virtual property as a PeriodScores value object.
     *
     * @var list<array{int, int}>|null
     */
    #[ORM\Column(name: 'period_scores', type: Types::JSON, nullable: true)]
    private ?array $periodScoresData = null;

    /**
     * Final score AFTER prolongation/shootout (home side). Settable only when the
     * regular score is a draw; the regular score remains the primary result.
     */
    #[ORM\Column(nullable: true)]
    public private(set) ?int $overtimeHomeScore = null;

    /** Final score AFTER prolongation/shootout (away side). See $overtimeHomeScore. */
    #[ORM\Column(nullable: true)]
    public private(set) ?int $overtimeAwayScore = null;

    #[ORM\Column]
    public private(set) \DateTimeImmutable $updatedAt;

    public ?PeriodScores $periodScores {
        get => PeriodScores::fromNullableArray($this->periodScoresData);
    }

    public bool $hasOvertimeScore {
        get => null !== $this->overtimeHomeScore && null !== $this->overtimeAwayScore;
    }

    public bool $isScheduled {
        get => SportMatchState::Scheduled === $this->state;
    }

    public bool $isLive {
        get => SportMatchState::Live === $this->state;
    }

    public bool $isFinished {
        get => SportMatchState::Finished === $this->state;
    }

    public bool $isPostponed {
        get => SportMatchState::Postponed === $this->state;
    }

    public bool $isCancelled {
        get => SportMatchState::Cancelled === $this->state;
    }

    public bool $isOpenForGuesses {
        get => SportMatchState::Scheduled === $this->state && null === $this->deletedAt;
    }

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private(set) Uuid $id,
        #[ORM\ManyToOne(targetEntity: MatchSource::class)]
        #[ORM\JoinColumn(name: 'match_source_id', referencedColumnName: 'id', nullable: false)]
        private(set) MatchSource $matchSource,
        string $homeTeam,
        string $awayTeam,
        \DateTimeImmutable $kickoffAt,
        ?string $venue,
        #[ORM\Column]
        private(set) \DateTimeImmutable $createdAt,
        // Appended (optional) so existing positional/named call sites keep compiling;
        // the fields themselves are declared up top next to $venue.
        ?string $round = null,
        bool $isPlayoff = false,
    ) {
        $this->homeTeam = $homeTeam;
        $this->awayTeam = $awayTeam;
        $this->kickoffAt = $kickoffAt;
        $this->venue = $venue;
        $this->round = $round;
        $this->isPlayoff = $isPlayoff;
        $this->state = SportMatchState::Scheduled;
        $this->updatedAt = $this->createdAt;

        $this->recordThat(new SportMatchCreated(
            sportMatchId: $this->id,
            matchSourceId: $this->matchSource->id,
            homeTeam: $this->homeTeam,
            awayTeam: $this->awayTeam,
            kickoffAt: $this->kickoffAt,
            isPlayoff: $this->isPlayoff,
            occurredOn: $this->createdAt,
        ));
    }

    public function updateDetails(
        ?string $homeTeam,
        ?string $awayTeam,
        ?\DateTimeImmutable $kickoffAt,
        ?string $venue,
        \DateTimeImmutable $now,
        ?string $round = null,
        bool $isPlayoff = false,
    ): void {
        if ($this->isCancelled || null !== $this->deletedAt) {
            throw SportMatchCannotBeEdited::create();
        }

        if (null !== $homeTeam) {
            $this->homeTeam = $homeTeam;
        }

        if (null !== $awayTeam) {
            $this->awayTeam = $awayTeam;
        }

        if (null !== $kickoffAt) {
            $this->kickoffAt = $kickoffAt;
        }

        $this->venue = $venue;
        $this->round = $round;
        $this->isPlayoff = $isPlayoff;
        $this->updatedAt = $now;

        $this->recordThat(new SportMatchUpdated(
            sportMatchId: $this->id,
            occurredOn: $now,
        ));
    }

    public function beginLive(\DateTimeImmutable $now): void
    {
        if (!$this->isScheduled) {
            throw SportMatchInvalidTransition::from($this->state, 'live');
        }

        $this->state = SportMatchState::Live;
        $this->updatedAt = $now;

        $this->recordThat(new SportMatchLive(
            sportMatchId: $this->id,
            occurredOn: $now,
        ));
    }

    /**
     * Live score update: allowed from Scheduled (transitions to Live implicitly)
     * and Live. Records SportMatchLiveScoreChanged only — no evaluation trigger.
     */
    public function updateLiveScore(
        int $homeScore,
        int $awayScore,
        ?PeriodScores $periodScores,
        \DateTimeImmutable $now,
    ): void {
        if ($homeScore < 0 || $awayScore < 0) {
            throw InvalidScore::negative();
        }

        if ($this->isCancelled || null !== $this->deletedAt) {
            throw SportMatchCannotBeEdited::create();
        }

        if (!$this->isScheduled && !$this->isLive) {
            throw SportMatchInvalidTransition::from($this->state, 'live_score');
        }

        // A live match may have only some periods played so far, never more than the sport allows.
        $sport = $this->matchSource->sport;
        if (null !== $periodScores && count($periodScores) > $sport->periodCount) {
            throw InvalidScore::tooManyPeriods($sport->periodCount, $sport->periodLabelPlural);
        }

        $this->state = SportMatchState::Live;
        $this->homeScore = $homeScore;
        $this->awayScore = $awayScore;
        $this->periodScoresData = $periodScores?->toArray();
        $this->updatedAt = $now;

        $this->recordThat(new SportMatchLiveScoreChanged(
            sportMatchId: $this->id,
            matchSourceId: $this->matchSource->id,
            homeScore: $homeScore,
            awayScore: $awayScore,
            occurredOn: $now,
        ));
    }

    public function setFinalScore(
        int $homeScore,
        int $awayScore,
        ?PeriodScores $periodScores,
        ?int $overtimeHomeScore,
        ?int $overtimeAwayScore,
        \DateTimeImmutable $now,
    ): void {
        if ($homeScore < 0 || $awayScore < 0) {
            throw InvalidScore::negative();
        }

        if ($this->isCancelled || null !== $this->deletedAt) {
            throw SportMatchCannotBeEdited::create();
        }

        if (null !== $periodScores) {
            $sport = $this->matchSource->sport;

            if (count($periodScores) !== $sport->periodCount) {
                throw InvalidScore::periodCountMismatch($sport->periodCount, $sport->periodLabelPlural);
            }

            if ($periodScores->sumHome() !== $homeScore || $periodScores->sumAway() !== $awayScore) {
                throw InvalidScore::periodSumMismatch();
            }
        }

        if ((null === $overtimeHomeScore) !== (null === $overtimeAwayScore)) {
            throw InvalidScore::overtimeIncomplete();
        }

        if (null !== $overtimeHomeScore && null !== $overtimeAwayScore) {
            if ($overtimeHomeScore < 0 || $overtimeAwayScore < 0) {
                throw InvalidScore::negative();
            }

            if ($homeScore !== $awayScore) {
                throw InvalidScore::overtimeWithoutDraw();
            }

            // The overtime score is the FINAL result incl. prolongation/shootout:
            // it must decide the match and can never undo regular-time goals.
            if ($overtimeHomeScore === $overtimeAwayScore) {
                throw InvalidScore::overtimeDraw();
            }

            if ($overtimeHomeScore < $homeScore || $overtimeAwayScore < $awayScore) {
                throw InvalidScore::overtimeBelowRegular();
            }
        }

        $wasFinished = $this->isFinished;

        $this->homeScore = $homeScore;
        $this->awayScore = $awayScore;
        $this->periodScoresData = $periodScores?->toArray();
        $this->overtimeHomeScore = $overtimeHomeScore;
        $this->overtimeAwayScore = $overtimeAwayScore;
        $this->state = SportMatchState::Finished;
        $this->updatedAt = $now;

        if ($wasFinished) {
            $this->recordThat(new SportMatchScoreUpdated(
                sportMatchId: $this->id,
                matchSourceId: $this->matchSource->id,
                homeScore: $homeScore,
                awayScore: $awayScore,
                occurredOn: $now,
            ));

            return;
        }

        $this->recordThat(new SportMatchFinished(
            sportMatchId: $this->id,
            matchSourceId: $this->matchSource->id,
            homeScore: $homeScore,
            awayScore: $awayScore,
            occurredOn: $now,
        ));
    }

    /**
     * Records that this match's guesses were evaluated (S11 `match_evaluated`
     * fan-out). Bumps `updatedAt` so the row is dirtied and the recorded event
     * is collected by the domain-event subscriber on flush, then dispatched
     * (isolated, post-commit) once the evaluations are committed. Call only
     * after the evaluation batch produced at least one evaluation.
     */
    public function recordGuessesEvaluated(\DateTimeImmutable $now): void
    {
        $this->updatedAt = $now;

        $this->recordThat(new GuessesEvaluatedForMatch(
            sportMatchId: $this->id,
            occurredOn: $now,
        ));
    }

    public function postponeTo(\DateTimeImmutable $newKickoffAt, \DateTimeImmutable $now): void
    {
        if (!$this->isScheduled && !$this->isPostponed) {
            throw SportMatchInvalidTransition::from($this->state, 'postpone');
        }

        $previousKickoffAt = $this->kickoffAt;
        $this->kickoffAt = $newKickoffAt;
        $this->state = SportMatchState::Postponed;
        $this->updatedAt = $now;

        $this->recordThat(new SportMatchPostponed(
            sportMatchId: $this->id,
            previousKickoffAt: $previousKickoffAt,
            newKickoffAt: $newKickoffAt,
            occurredOn: $now,
        ));
    }

    public function reschedule(\DateTimeImmutable $newKickoffAt, \DateTimeImmutable $now): void
    {
        if (!$this->isPostponed) {
            throw SportMatchInvalidTransition::from($this->state, 'reschedule');
        }

        $this->kickoffAt = $newKickoffAt;
        $this->state = SportMatchState::Scheduled;
        $this->updatedAt = $now;

        $this->recordThat(new SportMatchUpdated(
            sportMatchId: $this->id,
            occurredOn: $now,
        ));
    }

    public function cancel(\DateTimeImmutable $now): void
    {
        if ($this->isFinished) {
            throw SportMatchInvalidTransition::from($this->state, 'cancel');
        }

        if ($this->isCancelled) {
            return;
        }

        $this->state = SportMatchState::Cancelled;
        $this->updatedAt = $now;

        $this->recordThat(new SportMatchCancelled(
            sportMatchId: $this->id,
            matchSourceId: $this->matchSource->id,
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

        $this->recordThat(new SportMatchDeleted(
            sportMatchId: $this->id,
            matchSourceId: $this->matchSource->id,
            occurredOn: $now,
        ));
    }
}
