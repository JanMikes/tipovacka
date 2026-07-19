<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Command\CreateCompetition\CreateCompetitionCommand;
use App\Command\CreateSportMatch\CreateSportMatchCommand;
use App\Command\JoinCompetitionByPin\JoinCompetitionByPinCommand;
use App\Command\SetSportMatchFinalScore\SetSportMatchFinalScoreCommand;
use App\Command\SubmitGuess\SubmitGuessCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\Guess;
use App\Entity\GuessEvaluation;
use App\Entity\Sport;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\MatchSourceKind;
use App\Query\GetCompetitionLeaderboard\CompetitionLeaderboardResult;
use App\Query\GetCompetitionLeaderboard\GetCompetitionLeaderboard;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Uid\Uuid;

/**
 * End-to-end happy-path test covering the full Tipovačka flow:
 *   user A → private match source → competition with PIN → user B joins via PIN →
 *   user A adds a match → both submit guesses → user A sets final score →
 *   leaderboard reflects correct points.
 *
 * Uses the command / query buses directly rather than a real browser,
 * per project choice to skip Panther (see CLAUDE.md / feedback memory).
 * Verifies the whole stack end-to-end including event handlers
 * (scoring) and the leaderboard query.
 */
final class FullHappyPathTest extends IntegrationTestCase
{
    public function testRegisterMatchSourceCompetitionJoinGuessScoreLeaderboard(): void
    {
        $em = $this->entityManager();
        $bus = $this->commandBus();

        $userA = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($userA);
        $userB = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($userB);

        // 1+2) User A creates a from-scratch competition (with PIN) — this
        // auto-creates the hidden private source AND the owner membership.
        $competitionId = $this->extractId($bus->dispatch(new CreateCompetitionCommand(
            ownerId: $userA->id,
            name: 'E2E Soutěž',
            matchSourceId: null,
            sportId: Uuid::fromString(Sport::FOOTBALL_ID),
            fromScratch: true,
            withPin: true,
        )), Competition::class);

        $em->clear();
        $competition = $em->find(Competition::class, $competitionId);
        self::assertNotNull($competition);
        self::assertNotNull($competition->pin);
        $matchSourceId = $competition->matchSource->id;
        self::assertSame(MatchSourceKind::Private, $competition->matchSource->kind);

        // 3) User B joins via PIN.
        $bus->dispatch(new JoinCompetitionByPinCommand(
            userId: $userB->id,
            pin: $competition->pin,
        ));

        // 4) User A adds a scheduled match.
        $matchId = $this->extractId($bus->dispatch(new CreateSportMatchCommand(
            matchSourceId: $matchSourceId,
            editorId: $userA->id,
            homeTeam: 'E2E Home',
            awayTeam: 'E2E Away',
            kickoffAt: new \DateTimeImmutable('2025-07-01 20:00', new \DateTimeZone('UTC')),
            venue: null,
        )), SportMatch::class);

        // 5) Both users submit guesses (A: exact 2:1; B: correct outcome 3:0).
        $bus->dispatch(new SubmitGuessCommand(
            userId: $userA->id,
            competitionId: $competitionId,
            sportMatchId: $matchId,
            homeScore: 2,
            awayScore: 1,
        ));
        $bus->dispatch(new SubmitGuessCommand(
            userId: $userB->id,
            competitionId: $competitionId,
            sportMatchId: $matchId,
            homeScore: 3,
            awayScore: 0,
        ));

        // 6) User A sets final score 2:1 → SportMatchFinished fires → evaluations computed.
        $bus->dispatch(new SetSportMatchFinalScoreCommand(
            sportMatchId: $matchId,
            editorId: $userA->id,
            homeScore: 2,
            awayScore: 1,
        ));

        // 7) Verify evaluations.
        $em->clear();
        $guessA = $em->createQueryBuilder()
            ->select('g')->from(Guess::class, 'g')
            ->where('g.user = :u AND g.sportMatch = :m')
            ->setParameter('u', $userA->id)
            ->setParameter('m', $matchId)
            ->getQuery()
            ->getOneOrNullResult();
        self::assertNotNull($guessA);

        $evalA = $em->createQueryBuilder()
            ->select('e')->from(GuessEvaluation::class, 'e')
            ->where('e.guess = :g')
            ->setParameter('g', $guessA->id)
            ->getQuery()
            ->getOneOrNullResult();
        self::assertNotNull($evalA, 'User A should have a GuessEvaluation after score is set');

        // 2:1 vs 2:1 → hits: exact (5) + outcome (3) + home (1) + away (1) = 10.
        self::assertSame(10, $evalA->totalPoints);

        // B: 3:0 vs 2:1 → outcome only (3).
        $guessB = $em->createQueryBuilder()
            ->select('g')->from(Guess::class, 'g')
            ->where('g.user = :u AND g.sportMatch = :m')
            ->setParameter('u', $userB->id)
            ->setParameter('m', $matchId)
            ->getQuery()
            ->getOneOrNullResult();
        self::assertNotNull($guessB);

        $evalB = $em->createQueryBuilder()
            ->select('e')->from(GuessEvaluation::class, 'e')
            ->where('e.guess = :g')
            ->setParameter('g', $guessB->id)
            ->getQuery()
            ->getOneOrNullResult();
        self::assertNotNull($evalB);
        self::assertSame(3, $evalB->totalPoints);

        // 8) Leaderboard reflects correct ordering.
        /** @var CompetitionLeaderboardResult $leaderboard */
        $leaderboard = $this->queryBus()->handle(new GetCompetitionLeaderboard(competitionId: $competitionId));

        self::assertCount(2, $leaderboard->rows);
        self::assertSame(10, $leaderboard->rows[0]->totalPoints);
        self::assertTrue($leaderboard->rows[0]->userId->equals($userA->id));
        self::assertSame(1, $leaderboard->rows[0]->rank);
        self::assertSame(3, $leaderboard->rows[1]->totalPoints);
        self::assertTrue($leaderboard->rows[1]->userId->equals($userB->id));
        self::assertSame(2, $leaderboard->rows[1]->rank);
    }

    private function extractId(Envelope $envelope, string $expectedClass): Uuid
    {
        $entity = $envelope->last(HandledStamp::class)?->getResult();
        self::assertInstanceOf($expectedClass, $entity);

        return $entity->id;
    }

    protected function commandBus(): MessageBusInterface
    {
        /* @var MessageBusInterface */
        return self::getContainer()->get('test.command.bus');
    }
}
