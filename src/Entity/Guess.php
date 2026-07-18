<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\SoftDeletable;
use App\Entity\Concerns\SoftDeletes;
use App\Event\GuessSubmitted;
use App\Event\GuessUpdated;
use App\Event\GuessVoided;
use App\Exception\InvalidGuessScore;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'guesses')]
#[ORM\Index(columns: ['sport_match_id', 'competition_id', 'deleted_at'], name: 'IDX_guesses_match_competition')]
#[ORM\Index(columns: ['user_id', 'competition_id', 'deleted_at'], name: 'IDX_guesses_user_competition')]
#[ORM\UniqueConstraint(name: 'UIDX_guesses_active', columns: ['user_id', 'sport_match_id', 'competition_id'], options: ['where' => '(deleted_at IS NULL)'])]
class Guess implements EntityWithEvents, SoftDeletable
{
    use HasEvents;
    use SoftDeletes;

    #[ORM\Column]
    public private(set) int $homeScore;

    #[ORM\Column]
    public private(set) int $awayScore;

    #[ORM\Column]
    public private(set) \DateTimeImmutable $updatedAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'submitted_by_user_id', referencedColumnName: 'id', nullable: true)]
    public private(set) ?User $submittedBy = null;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private(set) Uuid $id,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
        private(set) User $user,
        #[ORM\ManyToOne(targetEntity: SportMatch::class)]
        #[ORM\JoinColumn(name: 'sport_match_id', referencedColumnName: 'id', nullable: false)]
        private(set) SportMatch $sportMatch,
        #[ORM\ManyToOne(targetEntity: Competition::class)]
        #[ORM\JoinColumn(name: 'competition_id', referencedColumnName: 'id', nullable: false)]
        private(set) Competition $competition,
        int $homeScore,
        int $awayScore,
        #[ORM\Column]
        private(set) \DateTimeImmutable $submittedAt,
        ?User $submittedBy = null,
    ) {
        if ($homeScore < 0 || $awayScore < 0) {
            throw InvalidGuessScore::create();
        }

        $this->homeScore = $homeScore;
        $this->awayScore = $awayScore;
        $this->updatedAt = $this->submittedAt;
        $this->submittedBy = $submittedBy;

        $this->recordThat(new GuessSubmitted(
            guessId: $this->id,
            userId: $this->user->id,
            sportMatchId: $this->sportMatch->id,
            competitionId: $this->competition->id,
            homeScore: $this->homeScore,
            awayScore: $this->awayScore,
            occurredOn: $this->submittedAt,
        ));
    }

    public function updateScores(int $homeScore, int $awayScore, \DateTimeImmutable $now): void
    {
        if ($homeScore < 0 || $awayScore < 0) {
            throw InvalidGuessScore::create();
        }

        $this->homeScore = $homeScore;
        $this->awayScore = $awayScore;
        $this->updatedAt = $now;

        $this->recordThat(new GuessUpdated(
            guessId: $this->id,
            homeScore: $this->homeScore,
            awayScore: $this->awayScore,
            occurredOn: $now,
        ));
    }

    public function voidGuess(\DateTimeImmutable $now): void
    {
        if (null !== $this->deletedAt) {
            return;
        }

        $this->markDeleted($now);
        $this->updatedAt = $now;

        $this->recordThat(new GuessVoided(
            guessId: $this->id,
            occurredOn: $now,
        ));
    }
}
