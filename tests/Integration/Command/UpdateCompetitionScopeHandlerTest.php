<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\UpdateCompetitionScope\UpdateCompetitionScopeCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\CompetitionMatchSelection;
use App\Entity\CompetitionSource;
use App\Entity\CompetitionTeamFilter;
use App\Entity\MatchSource;
use App\Enum\CompetitionMatchSelectionMode;
use App\Enum\MatchSourceKind;
use App\Exception\CompetitionIsGlobal;
use App\Exception\CompetitionSourcesSportMismatch;
use App\Service\Competition\CompetitionMatchProvider;
use App\Tests\Support\IntegrationTestCase;
use App\Value\CompetitionSourceSpec;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Uid\Uuid;

/**
 * „Zápasy soutěže" after the fact: the organizer's basket reconciled against the
 * layers a soutěž already has. The guarantee under test throughout is that a save
 * touches THIS competition's rows and nothing else — a zdroj's own rozpis is
 * never rewritten, and neither is another soutěž drawing from it.
 */
final class UpdateCompetitionScopeHandlerTest extends IntegrationTestCase
{
    public function testAddingASecondZdrojWidensTheScope(): void
    {
        // VERIFIED_COMPETITION starts on the private zdroj alone; add the curated one.
        $this->save([
            new CompetitionSourceSpec(matchSourceId: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID)),
            new CompetitionSourceSpec(matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID)),
        ]);

        $competition = $this->competition(AppFixtures::VERIFIED_COMPETITION_ID);

        self::assertCount(2, $competition->sources);
        self::assertSame(
            [AppFixtures::PRIVATE_SOURCE_ID, AppFixtures::PUBLIC_SOURCE_ID],
            array_map(static fn (CompetitionSource $l): string => $l->matchSource->id->toRfc4122(), $competition->sources),
        );

        $matchIds = $this->matchIdsIn($competition);
        self::assertContains(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID, $matchIds);
        self::assertContains(AppFixtures::MATCH_SCHEDULED_ID, $matchIds, 'The curated zdroj joined the scope.');
    }

    public function testALateAddedZdrojIsAnchoredAtTheMomentItJoins(): void
    {
        $this->save([
            new CompetitionSourceSpec(matchSourceId: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID)),
            new CompetitionSourceSpec(matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID)),
        ]);

        $competition = $this->competition(AppFixtures::VERIFIED_COMPETITION_ID);
        $added = $competition->sourceFor(Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID));

        self::assertNotNull($added);
        self::assertEquals(
            new \DateTimeImmutable('2025-06-15 12:00:00 UTC'),
            $added->addedAt,
            'A zdroj joining an existing soutěž carries the late-add anchor, so its matches get their own deadlines.',
        );
    }

    public function testDroppingAZdrojRemovesItsLayerAndRowsButKeepsTheZdroj(): void
    {
        // SUBSET_COMPETITION is a single subset layer over the curated zdroj —
        // replace it with the whole zdroj taken in „all" mode.
        $this->save(
            [new CompetitionSourceSpec(matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID))],
            AppFixtures::SUBSET_COMPETITION_ID,
            AppFixtures::SECOND_VERIFIED_USER_ID,
        );

        $competition = $this->competition(AppFixtures::SUBSET_COMPETITION_ID);

        self::assertCount(1, $competition->sources);
        self::assertSame(CompetitionMatchSelectionMode::All, $competition->sources[0]->selectionMode);
        self::assertSame(
            0,
            $this->countRows(CompetitionMatchSelection::class, AppFixtures::SUBSET_COMPETITION_ID),
            'Switching to „all" throws away the hand-picked rows the mode no longer uses.',
        );

        // The zdroj — shared with every other soutěž — is untouched.
        $curated = $this->entityManager()->find(MatchSource::class, Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID));
        self::assertNotNull($curated);
        self::assertNull($curated->deletedAt);
    }

    public function testSwitchingToSubsetKeepsAlreadyIncludedMatchesOnTheirOriginalDeadline(): void
    {
        $competition = $this->competition(AppFixtures::VERIFIED_COMPETITION_ID);

        $this->save([
            new CompetitionSourceSpec(
                matchSourceId: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
                selectionMode: CompetitionMatchSelectionMode::Subset,
                selectedMatchIds: [Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID)],
            ),
        ]);

        $selection = $this->entityManager()->createQueryBuilder()
            ->select('s')
            ->from(CompetitionMatchSelection::class, 's')
            ->where('s.competition = :competitionId')
            ->setParameter('competitionId', Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID))
            ->getQuery()
            ->getSingleResult();

        self::assertInstanceOf(CompetitionMatchSelection::class, $selection);
        self::assertEquals(
            $competition->createdAt,
            $selection->addedAt,
            'A match that was in the soutěž all along must not be re-anchored as „pozdě přidaný" — that would reopen closed tips.',
        );
    }

    public function testOwnMatchesCreatesAPrivateZdrojOnDemand(): void
    {
        // SUBSET_COMPETITION draws from the curated zdroj only; add „vlastní zápasy".
        $this->save(
            [
                new CompetitionSourceSpec(
                    matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
                    selectionMode: CompetitionMatchSelectionMode::Subset,
                    selectedMatchIds: [Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID)],
                ),
                new CompetitionSourceSpec(matchSourceId: null),
            ],
            AppFixtures::SUBSET_COMPETITION_ID,
            AppFixtures::SECOND_VERIFIED_USER_ID,
        );

        $competition = $this->competition(AppFixtures::SUBSET_COMPETITION_ID);

        self::assertCount(2, $competition->sources);

        $own = $competition->sources[1]->matchSource;
        self::assertSame(MatchSourceKind::Private, $own->kind);
        self::assertSame(AppFixtures::SECOND_VERIFIED_USER_ID, $own->owner->id->toRfc4122(), 'The organizer owns their soutěž\'s own zdroj.');
        self::assertSame($competition->name, $own->name);
    }

    public function testAskingForOwnMatchesTwiceCollapsesIntoOneZdroj(): void
    {
        $this->save(
            [
                new CompetitionSourceSpec(
                    matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
                    selectionMode: CompetitionMatchSelectionMode::Subset,
                    selectedMatchIds: [Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID)],
                ),
                new CompetitionSourceSpec(matchSourceId: null),
                new CompetitionSourceSpec(matchSourceId: null),
            ],
            AppFixtures::SUBSET_COMPETITION_ID,
            AppFixtures::SECOND_VERIFIED_USER_ID,
        );

        self::assertCount(2, $this->competition(AppFixtures::SUBSET_COMPETITION_ID)->sources);
    }

    public function testReSavingKeepsTheSameOwnZdrojInsteadOfCreatingAnother(): void
    {
        // VERIFIED_COMPETITION's private zdroj IS its own zdroj (no other soutěž
        // draws from it), so an „own matches" spec must resolve back to it.
        $this->save([new CompetitionSourceSpec(matchSourceId: null)]);

        $competition = $this->competition(AppFixtures::VERIFIED_COMPETITION_ID);

        self::assertCount(1, $competition->sources);
        self::assertSame(AppFixtures::PRIVATE_SOURCE_ID, $competition->sources[0]->matchSource->id->toRfc4122());
    }

    public function testTeamFilterRowsAreWrittenForATeamsLayer(): void
    {
        $this->save([
            new CompetitionSourceSpec(
                matchSourceId: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
                selectionMode: CompetitionMatchSelectionMode::Teams,
                filterTeamIds: [Uuid::fromString(AppFixtures::TEAM_TYGRI_ID)],
            ),
        ]);

        self::assertSame(1, $this->countRows(CompetitionTeamFilter::class, AppFixtures::VERIFIED_COMPETITION_ID));
        self::assertContains(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID, $this->matchIdsIn($this->competition(AppFixtures::VERIFIED_COMPETITION_ID)));
    }

    public function testEmptyBasketIsRefused(): void
    {
        $this->expectDomainFailure('Soutěž musí mít aspoň jeden zdroj zápasů.');

        $this->save([]);
    }

    public function testGlobalCompetitionIsRefused(): void
    {
        try {
            $this->save(
                [new CompetitionSourceSpec(matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID))],
                AppFixtures::GLOBAL_COMPETITION_ID,
                AppFixtures::ADMIN_ID,
            );
            self::fail('A global competition\'s scope must not be editable from the organizer screen.');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(CompetitionIsGlobal::class, $this->firstWrappedException($e));
        }
    }

    public function testMixingSportsIsRefused(): void
    {
        $hockeySource = $this->createHockeySource();

        try {
            $this->save([
                new CompetitionSourceSpec(matchSourceId: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID)),
                new CompetitionSourceSpec(matchSourceId: $hockeySource),
            ]);
            self::fail('A soutěž may only combine zdroje of one sport.');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(CompetitionSourcesSportMismatch::class, $this->firstWrappedException($e));
        }
    }

    public function testAnotherCompetitionDrawingFromTheSameZdrojIsUnaffected(): void
    {
        // SUBSET_COMPETITION and PUBLIC_COMPETITION both draw from the curated zdroj.
        $before = $this->matchIdsIn($this->competition(AppFixtures::PUBLIC_COMPETITION_ID));

        $this->save(
            [new CompetitionSourceSpec(matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID))],
            AppFixtures::SUBSET_COMPETITION_ID,
            AppFixtures::SECOND_VERIFIED_USER_ID,
        );

        self::assertSame($before, $this->matchIdsIn($this->competition(AppFixtures::PUBLIC_COMPETITION_ID)));
    }

    // ---- helpers ---------------------------------------------------------

    /**
     * @param list<CompetitionSourceSpec> $layers
     */
    private function save(
        array $layers,
        string $competitionId = AppFixtures::VERIFIED_COMPETITION_ID,
        string $editorId = AppFixtures::VERIFIED_USER_ID,
    ): void {
        $this->commandBus()->dispatch(new UpdateCompetitionScopeCommand(
            editorId: Uuid::fromString($editorId),
            competitionId: Uuid::fromString($competitionId),
            layers: $layers,
        ));

        $this->entityManager()->clear();
    }

    private function competition(string $id): Competition
    {
        $competition = $this->entityManager()->find(Competition::class, Uuid::fromString($id));
        self::assertInstanceOf(Competition::class, $competition);

        return $competition;
    }

    /**
     * @return list<string>
     */
    private function matchIdsIn(Competition $competition): array
    {
        /** @var CompetitionMatchProvider $provider */
        $provider = self::getContainer()->get(CompetitionMatchProvider::class);
        $provider->reset();

        $ids = array_map(
            static fn (\App\Entity\SportMatch $m): string => $m->id->toRfc4122(),
            $provider->matchesFor($competition),
        );
        sort($ids);

        return $ids;
    }

    /**
     * @param class-string $entityClass
     */
    private function countRows(string $entityClass, string $competitionId): int
    {
        return (int) $this->entityManager()->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from($entityClass, 'r')
            ->where('r.competition = :competitionId')
            ->setParameter('competitionId', Uuid::fromString($competitionId))
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function createHockeySource(): Uuid
    {
        $em = $this->entityManager();
        $id = Uuid::v7();

        $sport = $em->find(\App\Entity\Sport::class, Uuid::fromString(\App\Entity\Sport::HOCKEY_ID));
        $owner = $em->find(\App\Entity\User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($sport);
        self::assertNotNull($owner);

        $em->persist(new MatchSource(
            id: $id,
            sport: $sport,
            owner: $owner,
            kind: MatchSourceKind::Private,
            name: 'Hokejová parta',
            description: null,
            startAt: null,
            endAt: null,
            createdAt: new \DateTimeImmutable('2025-06-01 10:00:00 UTC'),
        ));
        $em->flush();

        return $id;
    }

    private function expectDomainFailure(string $message): void
    {
        $this->expectException(HandlerFailedException::class);
        $this->expectExceptionMessageMatches('/'.preg_quote($message, '/').'/');
    }
}
