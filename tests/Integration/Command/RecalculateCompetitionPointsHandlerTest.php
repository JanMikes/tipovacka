<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\RecalculateCompetitionPoints\RecalculateCompetitionPointsCommand;
use App\Command\SetSportMatchFinalScore\SetSportMatchFinalScoreCommand;
use App\Command\SubmitGuess\SubmitGuessCommand;
use App\Command\UpdateCompetitionMatchSelection\UpdateCompetitionMatchSelectionCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\GuessEvaluation;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Uid\Uuid;

final class RecalculateCompetitionPointsHandlerTest extends IntegrationTestCase
{
    public function testRecalcRebuildsEvaluations(): void
    {
        $competitionId = Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID);

        // Corrupt the state: hard-delete the fixture evaluation, so only a real
        // handler run can bring it back.
        $this->deleteEvaluations($competitionId);
        self::assertCount(0, $this->loadEvaluations($competitionId));

        $this->handleRecalc($competitionId);

        $evaluations = $this->loadEvaluations($competitionId);

        // Rebuilt from the fixture guess: 3:0 vs actual 2:1 = correct outcome only.
        self::assertCount(1, $evaluations);
        self::assertSame(3, $evaluations[0]->totalPoints);
    }

    public function testRecalcIsIdempotent(): void
    {
        $competitionId = Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID);

        $this->deleteEvaluations($competitionId);

        $this->handleRecalc($competitionId);
        $this->handleRecalc($competitionId);

        $evaluations = $this->loadEvaluations($competitionId);

        self::assertCount(1, $evaluations);
        self::assertSame(3, $evaluations[0]->totalPoints);
    }

    public function testRecalcPicksUpNewGuesses(): void
    {
        $competitionId = Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID);

        // Submit a guess on the scheduled match, then finish it with exact score —
        // the finish evaluates synchronously.
        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::ADMIN_ID),
            competitionId: $competitionId,
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            homeScore: 1,
            awayScore: 0,
        ));

        $this->commandBus()->dispatch(new SetSportMatchFinalScoreCommand(
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            homeScore: 1,
            awayScore: 0,
        ));

        self::assertCount(2, $this->loadEvaluations($competitionId));

        // Wipe everything the sync path produced; only the recalc handler can recreate it.
        $this->deleteEvaluations($competitionId);

        $this->handleRecalc($competitionId);

        $evaluations = $this->loadEvaluations($competitionId);

        // Two evaluations recreated: admin's fixture guess and the new guess (exact score 1:0).
        self::assertCount(2, $evaluations);

        $totals = array_map(fn (GuessEvaluation $e) => $e->totalPoints, $evaluations);
        sort($totals);
        // 3 (correct outcome only) + 10 (all four rules hit)
        self::assertSame([3, 10], $totals);
    }

    public function testRecalcOfOneCompetitionLeavesOthersUntouched(): void
    {
        $publicId = Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID);
        $subsetId = Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID);

        // Corrupt PUBLIC_COMPETITION's fixture evaluation so a recalc touching it is visible.
        $this->entityManager()->createQuery(
            'UPDATE App\Entity\GuessEvaluation e SET e.totalPoints = 999 WHERE e.id = :id',
        )->execute(['id' => Uuid::fromString(AppFixtures::FIXTURE_GUESS_EVALUATION_ID)]);

        // SUBSET_COMPETITION shares the source with PUBLIC_COMPETITION — recalculating
        // it evaluates ONLY its own (S06 fixture) guess and must not touch
        // PUBLIC_COMPETITION's evaluations. Subset total: exact 2:1 hit ⇒ base 10
        // + period_exact 2×5 + scorer_hit 1×2 = 22.
        $this->handleRecalc($subsetId);

        $subsetEvaluations = $this->loadEvaluations($subsetId);
        self::assertCount(1, $subsetEvaluations);
        self::assertSame(22, $subsetEvaluations[0]->totalPoints);

        $publicEvaluations = $this->loadEvaluations($publicId);
        self::assertCount(1, $publicEvaluations);
        self::assertSame(999, $publicEvaluations[0]->totalPoints);

        // Recalculating PUBLIC itself repairs the corruption.
        $this->handleRecalc($publicId);

        $publicEvaluations = $this->loadEvaluations($publicId);
        self::assertCount(1, $publicEvaluations);
        self::assertSame(3, $publicEvaluations[0]->totalPoints);
    }

    public function testRecalcPurgesAndRestoresEvaluationsOnSelectionChanges(): void
    {
        /** @var InMemoryTransport $async */
        $async = self::getContainer()->get('test.messenger.transport.async'); // @phpstan-ignore symfonyContainer.serviceNotFound
        $async->reset();

        $competitionId = Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID);

        // Owner tips on the selected scheduled match; the finish evaluates synchronously
        // (subset rules: 5 + 3 + 1 + 1 = 10 for the exact score).
        $this->commandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            competitionId: $competitionId,
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            homeScore: 1,
            awayScore: 0,
        ));

        $this->commandBus()->dispatch(new SetSportMatchFinalScoreCommand(
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            homeScore: 1,
            awayScore: 0,
        ));

        self::assertCount(1, $this->loadEvaluations($competitionId));

        // Deselect the finished match — the selection change enqueues a recalc.
        $async->reset();
        $this->commandBus()->dispatch(new UpdateCompetitionMatchSelectionCommand(
            editorId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            competitionId: $competitionId,
            selectedMatchIds: [Uuid::fromString(AppFixtures::MATCH_FINISHED_ID)],
        ));

        self::assertSame(1, $this->processEnqueuedRecalcs($async));

        // Only the S06 fixture guess on the still-selected MATCH_FINISHED remains.
        $remaining = $this->loadEvaluations($competitionId);
        self::assertCount(1, $remaining);
        self::assertSame(22, $remaining[0]->totalPoints);

        // Re-add the match — another recalc restores the evaluation.
        $async->reset();
        $this->commandBus()->dispatch(new UpdateCompetitionMatchSelectionCommand(
            editorId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            competitionId: $competitionId,
            selectedMatchIds: [
                Uuid::fromString(AppFixtures::MATCH_FINISHED_ID),
                Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            ],
        ));

        self::assertSame(1, $this->processEnqueuedRecalcs($async));

        $evaluations = $this->loadEvaluations($competitionId);
        self::assertCount(2, $evaluations);

        $totals = array_map(fn (GuessEvaluation $e) => $e->totalPoints, $evaluations);
        sort($totals);
        // 10 = exact 1:0 hit on the re-added match; 22 = the S06 fixture guess.
        self::assertSame([10, 22], $totals);

        $async->reset();
    }

    /**
     * Handles the command locally with full command-bus middleware (including the
     * doctrine_transaction flush): the ReceivedStamp makes SendMessageMiddleware
     * skip the async transport the command is normally routed to — exactly what a
     * worker consuming the queue would do.
     */
    private function handleRecalc(Uuid $competitionId): void
    {
        $this->commandBus()->dispatch(new Envelope(
            new RecalculateCompetitionPointsCommand(competitionId: $competitionId),
            [new ReceivedStamp('async')],
        ));
    }

    /**
     * Drains RecalculateCompetitionPointsCommand envelopes from the in-memory async
     * transport and handles each locally. Returns how many were processed.
     */
    private function processEnqueuedRecalcs(InMemoryTransport $async): int
    {
        $recalcCommands = [];

        foreach ($async->getSent() as $envelope) {
            $message = $envelope->getMessage();

            if ($message instanceof RecalculateCompetitionPointsCommand) {
                $recalcCommands[] = $message;
            }
        }

        foreach ($recalcCommands as $command) {
            $this->commandBus()->dispatch(new Envelope($command, [new ReceivedStamp('async')]));
        }

        return count($recalcCommands);
    }

    /**
     * Hard-deletes every evaluation of the competition (ORM remove cascades to the
     * rule-points rows) so subsequent assertions can only pass if the recalc
     * handler actually rebuilt them.
     */
    private function deleteEvaluations(Uuid $competitionId): void
    {
        $em = $this->entityManager();

        /** @var list<GuessEvaluation> $evaluations */
        $evaluations = $em->createQueryBuilder()
            ->select('e')
            ->from(GuessEvaluation::class, 'e')
            ->innerJoin('e.guess', 'g')
            ->where('g.competition = :competitionId')
            ->setParameter('competitionId', $competitionId)
            ->getQuery()
            ->getResult();

        foreach ($evaluations as $evaluation) {
            $em->remove($evaluation);
        }

        $em->flush();
    }

    /**
     * @return list<GuessEvaluation>
     */
    private function loadEvaluations(Uuid $competitionId): array
    {
        $em = $this->entityManager();
        $em->clear();

        /** @var list<GuessEvaluation> $evaluations */
        $evaluations = $em->createQueryBuilder()
            ->select('e')
            ->from(GuessEvaluation::class, 'e')
            ->innerJoin('e.guess', 'g')
            ->where('g.competition = :competitionId')
            ->setParameter('competitionId', $competitionId)
            ->getQuery()
            ->getResult();

        return $evaluations;
    }
}
