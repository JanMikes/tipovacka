<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\SoftDeletable;
use App\Entity\Concerns\SoftDeletes;
use App\Event\GuessSubmitted;
use App\Event\GuessUpdated;
use App\Event\GuessVoided;
use App\Exception\InvalidGuessScore;
use App\Value\PeriodScores;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
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

    /**
     * Raw per-period [home, away] tip pairs; exposed through the $periodScores
     * virtual property as a PeriodScores value object (same pattern as SportMatch).
     *
     * @var list<array{int, int}>|null
     */
    #[ORM\Column(name: 'period_scores', type: Types::JSON, nullable: true)]
    private ?array $periodScoresData = null;

    /**
     * Tip on the final score AFTER prolongation/shootout (home side). Allowed
     * only when the main tip is a draw; mirrors SportMatch overtime semantics
     * (must not be a draw, each side ≥ the regular tip).
     */
    #[ORM\Column(nullable: true)]
    public private(set) ?int $overtimeHomeScore = null;

    /** Tip on the final score AFTER prolongation/shootout (away side). See $overtimeHomeScore. */
    #[ORM\Column(nullable: true)]
    public private(set) ?int $overtimeAwayScore = null;

    #[ORM\Column]
    public private(set) \DateTimeImmutable $updatedAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'submitted_by_user_id', referencedColumnName: 'id', nullable: true)]
    public private(set) ?User $submittedBy = null;

    /**
     * @var Collection<int, GuessScorer>
     */
    #[ORM\OneToMany(targetEntity: GuessScorer::class, mappedBy: 'guess', cascade: ['persist'], orphanRemoval: true)]
    public private(set) Collection $scorers;

    public ?PeriodScores $periodScores {
        get => PeriodScores::fromNullableArray($this->periodScoresData);
    }

    public bool $hasOvertimeTip {
        get => null !== $this->overtimeHomeScore && null !== $this->overtimeAwayScore;
    }

    /**
     * Whether the stored overtime tip is still valid for a NEW main tip.
     * Partial UIs (batch pages, on-behalf forms) use this to decide if the OT
     * pair can be passed through a full-replace update — the new tip must be a
     * draw and each OT side must stay ≥ the new regular tip; otherwise the OT
     * tip is dropped (consistent with the non-draw drop).
     */
    public function overtimeTipValidFor(int $homeScore, int $awayScore): bool
    {
        if (null === $this->overtimeHomeScore || null === $this->overtimeAwayScore) {
            return false;
        }

        return $homeScore === $awayScore
            && $this->overtimeHomeScore >= $homeScore
            && $this->overtimeAwayScore >= $awayScore;
    }

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
        // Appended (optional) so existing call sites keep compiling; the fields
        // themselves are declared up top next to the score columns.
        ?PeriodScores $periodScores = null,
        ?int $overtimeHomeScore = null,
        ?int $overtimeAwayScore = null,
    ) {
        $this->assertValidTip($homeScore, $awayScore, $periodScores, $overtimeHomeScore, $overtimeAwayScore);

        $this->homeScore = $homeScore;
        $this->awayScore = $awayScore;
        $this->periodScoresData = $periodScores?->toArray();
        $this->overtimeHomeScore = $overtimeHomeScore;
        $this->overtimeAwayScore = $overtimeAwayScore;
        $this->updatedAt = $this->submittedAt;
        $this->submittedBy = $submittedBy;
        $this->scorers = new ArrayCollection();

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

    /**
     * Full-replace semantics: every tip part (main score, periods, overtime) is
     * set to exactly what is passed — omitted parts are cleared, never kept.
     * Callers exposing only a partial UI must pass the untouched parts through.
     */
    public function updateScores(
        int $homeScore,
        int $awayScore,
        \DateTimeImmutable $now,
        ?PeriodScores $periodScores = null,
        ?int $overtimeHomeScore = null,
        ?int $overtimeAwayScore = null,
    ): void {
        $this->assertValidTip($homeScore, $awayScore, $periodScores, $overtimeHomeScore, $overtimeAwayScore);

        $this->homeScore = $homeScore;
        $this->awayScore = $awayScore;
        $this->periodScoresData = $periodScores?->toArray();
        $this->overtimeHomeScore = $overtimeHomeScore;
        $this->overtimeAwayScore = $overtimeAwayScore;
        $this->updatedAt = $now;

        $this->recordThat(new GuessUpdated(
            guessId: $this->id,
            homeScore: $this->homeScore,
            awayScore: $this->awayScore,
            occurredOn: $now,
        ));
    }

    public function addScorer(GuessScorer $scorer): void
    {
        $this->scorers->add($scorer);
        $this->updatedAt = $scorer->createdAt;
    }

    public function removeScorer(GuessScorer $scorer, \DateTimeImmutable $now): void
    {
        $this->scorers->removeElement($scorer);
        $this->updatedAt = $now;
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

    private function assertValidTip(
        int $homeScore,
        int $awayScore,
        ?PeriodScores $periodScores,
        ?int $overtimeHomeScore,
        ?int $overtimeAwayScore,
    ): void {
        if ($homeScore < 0 || $awayScore < 0) {
            throw InvalidGuessScore::create();
        }

        if (null !== $periodScores) {
            // All-or-nothing: a period tip covers every period of the sport
            // (guess → sportMatch → matchSource → sport is always reachable).
            $sport = $this->sportMatch->matchSource->sport;

            if (count($periodScores) !== $sport->periodCount) {
                throw InvalidGuessScore::periodCountMismatch($sport->periodCount, $sport->periodLabelPlural);
            }

            // Mirror of the SportMatch invariant: the per-period tips must add
            // up to the main (regular-time) tip.
            if ($periodScores->sumHome() !== $homeScore || $periodScores->sumAway() !== $awayScore) {
                throw InvalidGuessScore::periodSumMismatch();
            }
        }

        if ((null === $overtimeHomeScore) !== (null === $overtimeAwayScore)) {
            throw InvalidGuessScore::overtimeIncomplete();
        }

        if (null !== $overtimeHomeScore && null !== $overtimeAwayScore) {
            if ($overtimeHomeScore < 0 || $overtimeAwayScore < 0) {
                throw InvalidGuessScore::create();
            }

            if ($homeScore !== $awayScore) {
                throw InvalidGuessScore::overtimeWithoutDraw();
            }

            // Mirror of SportMatch semantics: the overtime tip is the FINAL
            // result incl. prolongation/shootout — it must decide the match and
            // can never undo regular-time goals.
            if ($overtimeHomeScore === $overtimeAwayScore) {
                throw InvalidGuessScore::overtimeDraw();
            }

            if ($overtimeHomeScore < $homeScore || $overtimeAwayScore < $awayScore) {
                throw InvalidGuessScore::overtimeBelowRegular();
            }
        }
    }
}
