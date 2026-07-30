<?php

declare(strict_types=1);

namespace App\Tests\Integration\Console;

use App\Command\SetCompetitionMatchDeadline\SetCompetitionMatchDeadlineCommand;
use App\DataFixtures\AppFixtures;
use App\Repository\CompetitionMatchSettingRepository;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

/**
 * The bulk „tipování otevřeno od" ops tool. Its two safety properties are what
 * this pins: a dry run writes NOTHING, and an existing per-match uzávěrka
 * survives the bulk write instead of being cleared by it.
 */
final class BulkSetTipOpeningCommandTest extends IntegrationTestCase
{
    /**
     * Never boots a SECOND kernel: booting resets PredictableIdentityProvider's
     * counter, so a test that already dispatched a command would hand the bulk
     * run the same UUIDs again and hit the primary key.
     */
    private function tester(): CommandTester
    {
        self::getContainer();
        $kernel = self::$kernel;
        self::assertNotNull($kernel);

        $application = new Application($kernel);

        return new CommandTester($application->find('app:tip-opening:bulk-set'));
    }

    private function settingRepository(): CompetitionMatchSettingRepository
    {
        /* @var CompetitionMatchSettingRepository */
        return self::getContainer()->get(CompetitionMatchSettingRepository::class);
    }

    public function testDryRunWritesNothing(): void
    {
        $tester = $this->tester();

        $tester->execute([
            '--opens-at' => '2025-06-16 12:00',
            '--editor' => AppFixtures::ADMIN_ID,
            '--note' => 'Další zápasy půjdou tipovat po odehrání prvního kola',
        ]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('Zapsalo by se', $tester->getDisplay());

        $this->entityManager()->clear();
        self::assertNull($this->settingRepository()->findByCompetitionAndMatch(
            Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
            Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
        ));
    }

    public function testApplySetsTheOpeningAndHonoursTheExceptionList(): void
    {
        $tester = $this->tester();

        $tester->execute([
            '--opens-at' => '2025-06-16 12:00',
            '--editor' => AppFixtures::ADMIN_ID,
            '--note' => 'Další zápasy půjdou tipovat po odehrání prvního kola',
            '--except' => [AppFixtures::MATCH_PLAYOFF_ID],
            '--apply' => true,
        ]);

        $tester->assertCommandIsSuccessful();
        $this->entityManager()->clear();

        $set = $this->settingRepository()->findByCompetitionAndMatch(
            Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
            Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
        );
        self::assertNotNull($set);
        // 12:00 Prague in June = 10:00 UTC.
        self::assertEquals(new \DateTimeImmutable('2025-06-16 10:00:00'), $set->opensAt);
        self::assertSame('Další zápasy půjdou tipovat po odehrání prvního kola', $set->openingNote);

        // The exempted match stays untouched — it is what „except" means.
        self::assertNull($this->settingRepository()->findByCompetitionAndMatch(
            Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
            Uuid::fromString(AppFixtures::MATCH_PLAYOFF_ID),
        ));
    }

    public function testAnExistingDeadlineSurvivesTheBulkWrite(): void
    {
        $competitionId = Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID);
        $matchId = Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID);
        $deadline = new \DateTimeImmutable('2025-06-20 17:00:00');

        $this->commandBus()->dispatch(new SetCompetitionMatchDeadlineCommand(
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            competitionId: $competitionId,
            sportMatchId: $matchId,
            deadline: $deadline,
        ));

        $this->tester()->execute([
            '--opens-at' => '2025-06-16 12:00',
            '--editor' => AppFixtures::ADMIN_ID,
            '--apply' => true,
        ]);

        $this->entityManager()->clear();

        $set = $this->settingRepository()->findByCompetitionAndMatch($competitionId, $matchId);
        self::assertNotNull($set);
        self::assertEquals($deadline, $set->deadline, 'The bulk opening must not clear a stored uzávěrka.');
        self::assertEquals(new \DateTimeImmutable('2025-06-16 10:00:00'), $set->opensAt);
    }

    /**
     * The escape hatch from „tipy se zamykají startem soutěže": pinning every
     * deadline to the match's own kickoff is what lets a season run round by
     * round instead of freezing every tip at the first kickoff.
     */
    public function testDeadlineOwnKickoffPinsEachMatchToItsOwnKickoff(): void
    {
        $competitionId = Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID);
        $matchId = Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID);

        $this->tester()->execute([
            '--opens-at' => '2025-06-16 12:00',
            '--editor' => AppFixtures::ADMIN_ID,
            '--deadline-own-kickoff' => true,
            '--apply' => true,
        ]);

        $this->entityManager()->clear();

        $set = $this->settingRepository()->findByCompetitionAndMatch($competitionId, $matchId);
        self::assertNotNull($set);
        // MATCH_PRIVATE_SCHEDULED kicks off 2025-06-20 19:00 UTC.
        self::assertEquals(new \DateTimeImmutable('2025-06-20 19:00:00'), $set->deadline);
        self::assertEquals(new \DateTimeImmutable('2025-06-16 10:00:00'), $set->opensAt);
    }

    public function testNonAdminEditorIsRefused(): void
    {
        $tester = $this->tester();

        $this->expectExceptionMessage('Only an admin can set when tipping opens');

        $tester->execute([
            '--opens-at' => '2025-06-16 12:00',
            // VERIFIED_USER owns a competition but is not an admin.
            '--editor' => AppFixtures::VERIFIED_USER_ID,
            '--apply' => true,
        ]);
    }
}
