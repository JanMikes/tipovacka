<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\UpdateCompetitionMatchSelection\UpdateCompetitionMatchSelectionCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\CompetitionMatchSelection;
use App\Entity\Guess;
use App\Entity\MatchSource;
use App\Entity\SportMatch;
use App\Exception\MatchNotInCompetition;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Uid\Uuid;

final class UpdateCompetitionMatchSelectionHandlerTest extends IntegrationTestCase
{
    public function testFullReplaceOfSelection(): void
    {
        // Fixture selection: MATCH_SCHEDULED + MATCH_FINISHED.
        // New selection: MATCH_SCHEDULED + MATCH_PLAYOFF ⇒ FINISHED removed, PLAYOFF added.
        $this->commandBus()->dispatch(new UpdateCompetitionMatchSelectionCommand(
            editorId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID),
            selectedMatchIds: [
                Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
                Uuid::fromString(AppFixtures::MATCH_PLAYOFF_ID),
            ],
        ));

        $em = $this->entityManager();
        $em->clear();

        $selectedIds = array_map(
            static fn (CompetitionMatchSelection $s): string => $s->sportMatch->id->toRfc4122(),
            $em->createQueryBuilder()
                ->select('s')
                ->from(CompetitionMatchSelection::class, 's')
                ->where('s.competition = :competitionId')
                ->setParameter('competitionId', Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID))
                ->getQuery()
                ->getResult(),
        );
        sort($selectedIds);

        $expected = [AppFixtures::MATCH_SCHEDULED_ID, AppFixtures::MATCH_PLAYOFF_ID];
        sort($expected);

        self::assertSame($expected, $selectedIds);
    }

    public function testRemovingSelectedMatchKeepsExistingGuesses(): void
    {
        $em = $this->entityManager();
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        $secondVerified = $em->find(\App\Entity\User::class, Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID));
        self::assertNotNull($secondVerified);
        $subsetCompetition = $em->find(\App\Entity\Competition::class, Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID));
        self::assertNotNull($subsetCompetition);
        $scheduledMatch = $em->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID));
        self::assertNotNull($scheduledMatch);

        $guess = new Guess(
            id: Uuid::v7(),
            user: $secondVerified,
            sportMatch: $scheduledMatch,
            competition: $subsetCompetition,
            homeScore: 1,
            awayScore: 1,
            submittedAt: $now,
        );
        $guess->popEvents();
        $em->persist($guess);
        $em->flush();
        $guessId = $guess->id;

        // Unselect MATCH_SCHEDULED (keep only MATCH_FINISHED).
        $this->commandBus()->dispatch(new UpdateCompetitionMatchSelectionCommand(
            editorId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID),
            selectedMatchIds: [Uuid::fromString(AppFixtures::MATCH_FINISHED_ID)],
        ));

        $em->clear();

        // The guess survives — it just stops counting (provider excludes the match).
        $survivingGuess = $em->find(Guess::class, $guessId);
        self::assertInstanceOf(Guess::class, $survivingGuess);
        self::assertNull($survivingGuess->deletedAt);
    }

    public function testRejectsAllModeCompetition(): void
    {
        $this->expectException(HandlerFailedException::class);

        try {
            $this->commandBus()->dispatch(new UpdateCompetitionMatchSelectionCommand(
                editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
                competitionId: Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
                selectedMatchIds: [Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID)],
            ));
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(\DomainException::class, $e->getPrevious());

            throw $e;
        }
    }

    public function testRejectsMatchesFromForeignSourcesWithoutMutating(): void
    {
        try {
            $this->commandBus()->dispatch(new UpdateCompetitionMatchSelectionCommand(
                editorId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
                competitionId: Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID),
                // Only PRIVATE source matches — every id is foreign to the competition's source.
                selectedMatchIds: [Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID)],
            ));
            self::fail('Expected MatchNotInCompetition to be thrown.');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(MatchNotInCompetition::class, $e->getPrevious());
        }

        // Validation happens before any mutation — fixture selection is untouched.
        self::assertSame(
            $this->fixtureSelection(),
            $this->currentSelection(),
        );
    }

    public function testRejectsCancelledMatch(): void
    {
        $em = $this->entityManager();
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        $publicSource = $em->find(MatchSource::class, Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID));
        self::assertNotNull($publicSource);

        $cancelledMatch = new SportMatch(
            id: Uuid::v7(),
            matchSource: $publicSource,
            homeTeam: 'Zrušení',
            awayTeam: 'Soupeři',
            kickoffAt: new \DateTimeImmutable('2025-06-25 18:00:00 UTC'),
            venue: null,
            createdAt: $now,
        );
        $cancelledMatch->cancel($now);
        $cancelledMatch->popEvents();
        $em->persist($cancelledMatch);
        $em->flush();

        try {
            $this->commandBus()->dispatch(new UpdateCompetitionMatchSelectionCommand(
                editorId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
                competitionId: Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID),
                selectedMatchIds: [
                    Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
                    $cancelledMatch->id,
                ],
            ));
            self::fail('Expected MatchNotInCompetition to be thrown.');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(MatchNotInCompetition::class, $e->getPrevious());
        }

        self::assertSame(
            $this->fixtureSelection(),
            $this->currentSelection(),
        );
    }

    public function testRejectsEmptySelection(): void
    {
        try {
            $this->commandBus()->dispatch(new UpdateCompetitionMatchSelectionCommand(
                editorId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
                competitionId: Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID),
                selectedMatchIds: [],
            ));
            self::fail('Expected DomainException to be thrown.');
        } catch (HandlerFailedException $e) {
            $previous = $e->getPrevious();
            self::assertInstanceOf(\DomainException::class, $previous);
            self::assertSame('Vyberte prosím alespoň jeden zápas.', $previous->getMessage());
        }

        self::assertSame(
            $this->fixtureSelection(),
            $this->currentSelection(),
        );
    }

    /**
     * @return list<string>
     */
    private function fixtureSelection(): array
    {
        $expected = [AppFixtures::MATCH_SCHEDULED_ID, AppFixtures::MATCH_FINISHED_ID];
        sort($expected);

        return $expected;
    }

    /**
     * @return list<string>
     */
    private function currentSelection(): array
    {
        $em = $this->entityManager();
        $em->clear();

        $selectedIds = array_map(
            static fn (CompetitionMatchSelection $s): string => $s->sportMatch->id->toRfc4122(),
            $em->createQueryBuilder()
                ->select('s')
                ->from(CompetitionMatchSelection::class, 's')
                ->where('s.competition = :competitionId')
                ->setParameter('competitionId', Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID))
                ->getQuery()
                ->getResult(),
        );
        sort($selectedIds);

        return $selectedIds;
    }
}
