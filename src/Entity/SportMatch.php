<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\SoftDeletable;
use App\Entity\Concerns\SoftDeletes;
use App\Enum\SportMatchState;
use App\Event\SportMatchCancelled;
use App\Event\SportMatchCreated;
use App\Event\SportMatchDeleted;
use App\Event\SportMatchFinished;
use App\Event\SportMatchLive;
use App\Event\SportMatchPostponed;
use App\Event\SportMatchScoreUpdated;
use App\Event\SportMatchUpdated;
use App\Exception\InvalidScore;
use App\Exception\SportMatchCannotBeEdited;
use App\Exception\SportMatchInvalidTransition;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'sport_matches')]
#[ORM\Index(columns: ['tournament_id', 'kickoff_at', 'deleted_at'], name: 'IDX_sport_matches_tournament_kickoff')]
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

    #[ORM\Column(enumType: SportMatchState::class)]
    public private(set) SportMatchState $state;

    #[ORM\Column(nullable: true)]
    public private(set) ?int $homeScore = null;

    #[ORM\Column(nullable: true)]
    public private(set) ?int $awayScore = null;

    #[ORM\Column]
    public private(set) \DateTimeImmutable $updatedAt;

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
        #[ORM\ManyToOne(targetEntity: Tournament::class)]
        #[ORM\JoinColumn(name: 'tournament_id', referencedColumnName: 'id', nullable: false)]
        private(set) Tournament $tournament,
        string $homeTeam,
        string $awayTeam,
        \DateTimeImmutable $kickoffAt,
        ?string $venue,
        #[ORM\Column]
        private(set) \DateTimeImmutable $createdAt,
    ) {
        $this->homeTeam = $homeTeam;
        $this->awayTeam = $awayTeam;
        $this->kickoffAt = $kickoffAt;
        $this->venue = $venue;
        $this->state = SportMatchState::Scheduled;
        $this->updatedAt = $this->createdAt;

        $this->recordThat(new SportMatchCreated(
            sportMatchId: $this->id,
            tournamentId: $this->tournament->id,
            homeTeam: $this->homeTeam,
            awayTeam: $this->awayTeam,
            kickoffAt: $this->kickoffAt,
            occurredOn: $this->createdAt,
        ));
    }

    public function updateDetails(
        ?string $homeTeam,
        ?string $awayTeam,
        ?\DateTimeImmutable $kickoffAt,
        ?string $venue,
        \DateTimeImmutable $now,
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

    public function setFinalScore(int $homeScore, int $awayScore, \DateTimeImmutable $now): void
    {
        if ($homeScore < 0 || $awayScore < 0) {
            throw InvalidScore::negative();
        }

        if ($this->isCancelled || null !== $this->deletedAt) {
            throw SportMatchCannotBeEdited::create();
        }

        $wasFinished = $this->isFinished;

        $this->homeScore = $homeScore;
        $this->awayScore = $awayScore;
        $this->state = SportMatchState::Finished;
        $this->updatedAt = $now;

        if ($wasFinished) {
            $this->recordThat(new SportMatchScoreUpdated(
                sportMatchId: $this->id,
                tournamentId: $this->tournament->id,
                homeScore: $homeScore,
                awayScore: $awayScore,
                occurredOn: $now,
            ));

            return;
        }

        $this->recordThat(new SportMatchFinished(
            sportMatchId: $this->id,
            tournamentId: $this->tournament->id,
            homeScore: $homeScore,
            awayScore: $awayScore,
            occurredOn: $now,
        ));
    }

    public function postponeTo(\DateTimeImmutable $newKickoffAt, \DateTimeImmutable $now): void
    {
        if (!$this->isScheduled && !$this->isPostponed) {
            throw SportMatchInvalidTransition::from($this->state, 'postpone');
        }

        $this->kickoffAt = $newKickoffAt;
        $this->state = SportMatchState::Postponed;
        $this->updatedAt = $now;

        $this->recordThat(new SportMatchPostponed(
            sportMatchId: $this->id,
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
            tournamentId: $this->tournament->id,
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
            tournamentId: $this->tournament->id,
            occurredOn: $now,
        ));
    }
}
