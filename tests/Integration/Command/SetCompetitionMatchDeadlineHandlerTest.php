<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\SetCompetitionMatchDeadline\SetCompetitionMatchDeadlineCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\CompetitionMatchSetting;
use App\Exception\CompetitionMatchDeadlineAfterKickoff;
use App\Exception\CompetitionMatchOpeningAfterDeadline;
use App\Exception\CompetitionMatchOpeningNoteWithoutTime;
use App\Exception\MatchNotInCompetition;
use App\Repository\CompetitionMatchSettingRepository;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;

final class SetCompetitionMatchDeadlineHandlerTest extends IntegrationTestCase
{
    private function settingRepository(): CompetitionMatchSettingRepository
    {
        /* @var CompetitionMatchSettingRepository */
        return self::getContainer()->get(CompetitionMatchSettingRepository::class);
    }

    public function testCreatesOverrideWhenNoneExists(): void
    {
        $competitionId = Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID);
        $matchId = Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID);
        $deadline = new \DateTimeImmutable('2025-06-20 17:00:00');

        $this->commandBus()->dispatch(new SetCompetitionMatchDeadlineCommand(
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: $competitionId,
            sportMatchId: $matchId,
            deadline: $deadline,
        ));

        $this->entityManager()->clear();

        $repo = $this->settingRepository();
        $setting = $repo->findByCompetitionAndMatch($competitionId, $matchId);
        self::assertInstanceOf(CompetitionMatchSetting::class, $setting);
        self::assertEquals($deadline, $setting->deadline);
    }

    public function testUpdatesExistingOverride(): void
    {
        $competitionId = Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID);
        $matchId = Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID);

        $this->commandBus()->dispatch(new SetCompetitionMatchDeadlineCommand(
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: $competitionId,
            sportMatchId: $matchId,
            deadline: new \DateTimeImmutable('2025-06-20 17:00:00'),
        ));

        $newer = new \DateTimeImmutable('2025-06-20 17:45:00');
        $this->commandBus()->dispatch(new SetCompetitionMatchDeadlineCommand(
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: $competitionId,
            sportMatchId: $matchId,
            deadline: $newer,
        ));

        $this->entityManager()->clear();

        $repo = $this->settingRepository();
        $setting = $repo->findByCompetitionAndMatch($competitionId, $matchId);
        self::assertInstanceOf(CompetitionMatchSetting::class, $setting);
        self::assertEquals($newer, $setting->deadline);
    }

    public function testRemovesOverrideWhenDeadlineIsNull(): void
    {
        $competitionId = Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID);
        $matchId = Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID);

        $this->commandBus()->dispatch(new SetCompetitionMatchDeadlineCommand(
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: $competitionId,
            sportMatchId: $matchId,
            deadline: new \DateTimeImmutable('2025-06-20 17:00:00'),
        ));

        $this->commandBus()->dispatch(new SetCompetitionMatchDeadlineCommand(
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: $competitionId,
            sportMatchId: $matchId,
            deadline: null,
        ));

        $this->entityManager()->clear();

        $repo = $this->settingRepository();
        self::assertNull($repo->findByCompetitionAndMatch($competitionId, $matchId));
    }

    public function testNullDeadlineWithoutExistingOverrideIsNoOp(): void
    {
        $competitionId = Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID);
        $matchId = Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID);

        $this->commandBus()->dispatch(new SetCompetitionMatchDeadlineCommand(
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: $competitionId,
            sportMatchId: $matchId,
            deadline: null,
        ));

        $repo = $this->settingRepository();
        self::assertNull($repo->findByCompetitionAndMatch($competitionId, $matchId));
    }

    public function testRejectsMatchNotInCompetition(): void
    {
        // MATCH_PLAYOFF is not among the subset competition's selected matches.
        $competitionId = Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID);
        $matchId = Uuid::fromString(AppFixtures::MATCH_PLAYOFF_ID);

        try {
            $this->commandBus()->dispatch(new SetCompetitionMatchDeadlineCommand(
                editorId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
                competitionId: $competitionId,
                sportMatchId: $matchId,
                deadline: new \DateTimeImmutable('2025-06-22 17:00:00'),
            ));
            self::fail('Expected MatchNotInCompetition to be thrown.');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(MatchNotInCompetition::class, $e->getPrevious());
        }

        $this->entityManager()->clear();

        self::assertNull($this->settingRepository()->findByCompetitionAndMatch($competitionId, $matchId));
    }

    // ── „Tipování otevřeno od" — the admin-only end of the window ────────────

    public function testAdminStoresOpeningAndNote(): void
    {
        $competitionId = Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID);
        $matchId = Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID);
        $opensAt = new \DateTimeImmutable('2025-06-18 09:00:00');

        $this->commandBus()->dispatch(new SetCompetitionMatchDeadlineCommand(
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            competitionId: $competitionId,
            sportMatchId: $matchId,
            deadline: null,
            changeOpening: true,
            opensAt: $opensAt,
            openingNote: 'Otevřeme po losu skupin.',
        ));

        $this->entityManager()->clear();

        $setting = $this->settingRepository()->findByCompetitionAndMatch($competitionId, $matchId);
        self::assertInstanceOf(CompetitionMatchSetting::class, $setting);
        self::assertNull($setting->deadline);
        self::assertEquals($opensAt, $setting->opensAt);
        self::assertSame('Otevřeme po losu skupin.', $setting->openingNote);
    }

    public function testNonAdminCannotSetTheOpening(): void
    {
        $competitionId = Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID);
        $matchId = Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID);

        try {
            $this->commandBus()->dispatch(new SetCompetitionMatchDeadlineCommand(
                // VERIFIED_USER owns this competition but is not an admin.
                editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
                competitionId: $competitionId,
                sportMatchId: $matchId,
                deadline: null,
                changeOpening: true,
                opensAt: new \DateTimeImmutable('2025-06-18 09:00:00'),
            ));
            self::fail('Expected AccessDeniedException to be thrown.');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(AccessDeniedException::class, $e->getPrevious());
        }

        $this->entityManager()->clear();
        self::assertNull($this->settingRepository()->findByCompetitionAndMatch($competitionId, $matchId));
    }

    /**
     * A manager saving the deadline must not wipe the admin's opening — their
     * form does not carry those fields, so the command arrives with
     * `changeOpening: false` and the stored opening survives untouched.
     */
    public function testManagerDeadlineSaveKeepsTheAdminsOpening(): void
    {
        $competitionId = Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID);
        $matchId = Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID);
        $opensAt = new \DateTimeImmutable('2025-06-18 09:00:00');

        $this->commandBus()->dispatch(new SetCompetitionMatchDeadlineCommand(
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            competitionId: $competitionId,
            sportMatchId: $matchId,
            deadline: null,
            changeOpening: true,
            opensAt: $opensAt,
            openingNote: 'Po losu.',
        ));

        $deadline = new \DateTimeImmutable('2025-06-20 17:00:00');
        $this->commandBus()->dispatch(new SetCompetitionMatchDeadlineCommand(
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            competitionId: $competitionId,
            sportMatchId: $matchId,
            deadline: $deadline,
        ));

        $this->entityManager()->clear();

        $setting = $this->settingRepository()->findByCompetitionAndMatch($competitionId, $matchId);
        self::assertInstanceOf(CompetitionMatchSetting::class, $setting);
        self::assertEquals($deadline, $setting->deadline);
        self::assertEquals($opensAt, $setting->opensAt);
        self::assertSame('Po losu.', $setting->openingNote);
    }

    public function testClearingBothEndsRemovesTheRow(): void
    {
        $competitionId = Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID);
        $matchId = Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID);

        $this->commandBus()->dispatch(new SetCompetitionMatchDeadlineCommand(
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            competitionId: $competitionId,
            sportMatchId: $matchId,
            deadline: new \DateTimeImmutable('2025-06-20 17:00:00'),
            changeOpening: true,
            opensAt: new \DateTimeImmutable('2025-06-18 09:00:00'),
        ));

        $this->commandBus()->dispatch(new SetCompetitionMatchDeadlineCommand(
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            competitionId: $competitionId,
            sportMatchId: $matchId,
            deadline: null,
            changeOpening: true,
            opensAt: null,
        ));

        $this->entityManager()->clear();
        self::assertNull($this->settingRepository()->findByCompetitionAndMatch($competitionId, $matchId));
    }

    public function testRejectsOpeningAtOrAfterTheDeadline(): void
    {
        $competitionId = Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID);
        $matchId = Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID);
        $deadline = new \DateTimeImmutable('2025-06-20 17:00:00');

        try {
            $this->commandBus()->dispatch(new SetCompetitionMatchDeadlineCommand(
                editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
                competitionId: $competitionId,
                sportMatchId: $matchId,
                deadline: $deadline,
                changeOpening: true,
                opensAt: $deadline,
            ));
            self::fail('Expected CompetitionMatchOpeningAfterDeadline to be thrown.');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(CompetitionMatchOpeningAfterDeadline::class, $e->getPrevious());
        }

        $this->entityManager()->clear();
        self::assertNull($this->settingRepository()->findByCompetitionAndMatch($competitionId, $matchId));
    }

    /**
     * Without an explicit deadline the opening is still validated — against the
     * deadline the competition's own rule produces (here: the match's kickoff).
     */
    public function testRejectsOpeningAfterTheRuleDerivedDeadline(): void
    {
        $competitionId = Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID);
        $matchId = Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID);

        try {
            $this->commandBus()->dispatch(new SetCompetitionMatchDeadlineCommand(
                editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
                competitionId: $competitionId,
                sportMatchId: $matchId,
                deadline: null,
                changeOpening: true,
                // MATCH_SCHEDULED kicks off 2025-06-20 18:00 and is late-added
                // in PUBLIC_COMPETITION ⇒ that kickoff IS the deadline.
                opensAt: new \DateTimeImmutable('2025-06-20 18:30:00'),
            ));
            self::fail('Expected CompetitionMatchOpeningAfterDeadline to be thrown.');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(CompetitionMatchOpeningAfterDeadline::class, $e->getPrevious());
        }

        $this->entityManager()->clear();
        self::assertNull($this->settingRepository()->findByCompetitionAndMatch($competitionId, $matchId));
    }

    public function testRejectsANoteWithoutAnOpening(): void
    {
        $competitionId = Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID);
        $matchId = Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID);

        try {
            $this->commandBus()->dispatch(new SetCompetitionMatchDeadlineCommand(
                editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
                competitionId: $competitionId,
                sportMatchId: $matchId,
                deadline: null,
                changeOpening: true,
                opensAt: null,
                openingNote: 'Text, který by nikdo nikdy neuviděl.',
            ));
            self::fail('Expected CompetitionMatchOpeningNoteWithoutTime to be thrown.');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(CompetitionMatchOpeningNoteWithoutTime::class, $e->getPrevious());
        }

        $this->entityManager()->clear();
        self::assertNull($this->settingRepository()->findByCompetitionAndMatch($competitionId, $matchId));
    }

    public function testRejectsDeadlineAfterKickoff(): void
    {
        $competitionId = Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID);
        $matchId = Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID);
        // MATCH_PRIVATE_SCHEDULED_ID kickoff is 2025-06-20 19:00 UTC.
        $tooLate = new \DateTimeImmutable('2025-06-20 19:00:01');

        try {
            $this->commandBus()->dispatch(new SetCompetitionMatchDeadlineCommand(
                editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
                competitionId: $competitionId,
                sportMatchId: $matchId,
                deadline: $tooLate,
            ));
            self::fail('Expected CompetitionMatchDeadlineAfterKickoff to be thrown.');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(CompetitionMatchDeadlineAfterKickoff::class, $e->getPrevious());
        }

        $this->entityManager()->clear();

        $repo = $this->settingRepository();
        self::assertNull($repo->findByCompetitionAndMatch($competitionId, $matchId));
    }
}
