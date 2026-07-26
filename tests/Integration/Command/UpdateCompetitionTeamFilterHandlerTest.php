<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\CreateCompetition\CreateCompetitionCommand;
use App\Command\UpdateCompetitionTeamFilter\UpdateCompetitionTeamFilterCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\CompetitionTeamFilter;
use App\Enum\CompetitionMatchSelectionMode;
use App\Exception\TeamNotInSource;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Uid\Uuid;

final class UpdateCompetitionTeamFilterHandlerTest extends IntegrationTestCase
{
    public function testFullReplaceOfTheFilterTeams(): void
    {
        $competition = $this->createTeamsCompetition('Filtr úprava', [AppFixtures::TEAM_SPARTA_ID]);

        $this->commandBus()->dispatch(new UpdateCompetitionTeamFilterCommand(
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: $competition->id,
            teamIds: [
                Uuid::fromString(AppFixtures::TEAM_REAL_MADRID_ID),
                Uuid::fromString(AppFixtures::TEAM_SLAVIA_ID),
            ],
        ));

        self::assertEqualsCanonicalizing(
            [AppFixtures::TEAM_REAL_MADRID_ID, AppFixtures::TEAM_SLAVIA_ID],
            $this->filterTeamIds($competition->id),
        );
    }

    public function testEmptyReplacementIsRejected(): void
    {
        $competition = $this->createTeamsCompetition('Prázdný filtr', [AppFixtures::TEAM_SPARTA_ID]);

        try {
            $this->commandBus()->dispatch(new UpdateCompetitionTeamFilterCommand(
                editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
                competitionId: $competition->id,
                teamIds: [],
            ));
            self::fail('Expected an empty filter to be rejected.');
        } catch (HandlerFailedException $exception) {
            self::assertInstanceOf(\DomainException::class, $this->firstWrappedException($exception));
        }

        // Original filter is untouched (transaction rolled back).
        self::assertSame([AppFixtures::TEAM_SPARTA_ID], $this->filterTeamIds($competition->id));
    }

    public function testForeignTeamIsRejected(): void
    {
        $competition = $this->createTeamsCompetition('Cizí ve filtru', [AppFixtures::TEAM_SPARTA_ID]);

        try {
            $this->commandBus()->dispatch(new UpdateCompetitionTeamFilterCommand(
                editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
                competitionId: $competition->id,
                teamIds: [Uuid::fromString(AppFixtures::TEAM_TYGRI_ID)], // local to the PRIVATE source
            ));
            self::fail('Expected TeamNotInSource.');
        } catch (HandlerFailedException $exception) {
            self::assertInstanceOf(TeamNotInSource::class, $this->firstWrappedException($exception));
        }
    }

    public function testRejectedForNonTeamsCompetition(): void
    {
        // PUBLIC_COMPETITION is a plain All-mode competition.
        try {
            $this->commandBus()->dispatch(new UpdateCompetitionTeamFilterCommand(
                editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
                competitionId: Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
                teamIds: [Uuid::fromString(AppFixtures::TEAM_SPARTA_ID)],
            ));
            self::fail('Expected a non-Teams competition to be rejected.');
        } catch (HandlerFailedException $exception) {
            self::assertInstanceOf(\DomainException::class, $this->firstWrappedException($exception));
        }
    }

    /**
     * @param list<string> $teamIds
     */
    private function createTeamsCompetition(string $name, array $teamIds): Competition
    {
        $this->commandBus()->dispatch(new CreateCompetitionCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            name: $name,
            matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
            sportId: null,
            fromScratch: false,
            withPin: false,
            selectionMode: CompetitionMatchSelectionMode::Teams,
            filterTeamIds: array_map(static fn (string $id): Uuid => Uuid::fromString($id), $teamIds),
        ));

        $this->entityManager()->clear();

        $competition = $this->entityManager()->createQueryBuilder()
            ->select('c')
            ->from(Competition::class, 'c')
            ->where('c.name = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(Competition::class, $competition);

        return $competition;
    }

    /**
     * @return list<string>
     */
    private function filterTeamIds(Uuid $competitionId): array
    {
        /** @var list<CompetitionTeamFilter> $rows */
        $rows = $this->entityManager()->createQueryBuilder()
            ->select('f')
            ->from(CompetitionTeamFilter::class, 'f')
            ->where('f.competition = :competitionId')
            ->setParameter('competitionId', $competitionId)
            ->getQuery()
            ->getResult();

        return array_map(static fn (CompetitionTeamFilter $row): string => $row->team->id->toRfc4122(), $rows);
    }
}
