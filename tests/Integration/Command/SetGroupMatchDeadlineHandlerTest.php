<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\SetGroupMatchDeadline\SetGroupMatchDeadlineCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\GroupMatchSetting;
use App\Exception\GroupMatchDeadlineAfterKickoff;
use App\Repository\GroupMatchSettingRepository;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Uid\Uuid;

final class SetGroupMatchDeadlineHandlerTest extends IntegrationTestCase
{
    private function settingRepository(): GroupMatchSettingRepository
    {
        /* @var GroupMatchSettingRepository */
        return self::getContainer()->get(GroupMatchSettingRepository::class);
    }

    public function testCreatesOverrideWhenNoneExists(): void
    {
        $groupId = Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID);
        $matchId = Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID);
        $deadline = new \DateTimeImmutable('2025-06-20 17:00:00');

        $this->commandBus()->dispatch(new SetGroupMatchDeadlineCommand(
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            groupId: $groupId,
            sportMatchId: $matchId,
            deadline: $deadline,
        ));

        $this->entityManager()->clear();

        $repo = $this->settingRepository();
        $setting = $repo->findByGroupAndMatch($groupId, $matchId);
        self::assertInstanceOf(GroupMatchSetting::class, $setting);
        self::assertEquals($deadline, $setting->deadline);
    }

    public function testUpdatesExistingOverride(): void
    {
        $groupId = Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID);
        $matchId = Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID);

        $this->commandBus()->dispatch(new SetGroupMatchDeadlineCommand(
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            groupId: $groupId,
            sportMatchId: $matchId,
            deadline: new \DateTimeImmutable('2025-06-20 17:00:00'),
        ));

        $newer = new \DateTimeImmutable('2025-06-20 17:45:00');
        $this->commandBus()->dispatch(new SetGroupMatchDeadlineCommand(
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            groupId: $groupId,
            sportMatchId: $matchId,
            deadline: $newer,
        ));

        $this->entityManager()->clear();

        $repo = $this->settingRepository();
        $setting = $repo->findByGroupAndMatch($groupId, $matchId);
        self::assertInstanceOf(GroupMatchSetting::class, $setting);
        self::assertEquals($newer, $setting->deadline);
    }

    public function testRemovesOverrideWhenDeadlineIsNull(): void
    {
        $groupId = Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID);
        $matchId = Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID);

        $this->commandBus()->dispatch(new SetGroupMatchDeadlineCommand(
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            groupId: $groupId,
            sportMatchId: $matchId,
            deadline: new \DateTimeImmutable('2025-06-20 17:00:00'),
        ));

        $this->commandBus()->dispatch(new SetGroupMatchDeadlineCommand(
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            groupId: $groupId,
            sportMatchId: $matchId,
            deadline: null,
        ));

        $this->entityManager()->clear();

        $repo = $this->settingRepository();
        self::assertNull($repo->findByGroupAndMatch($groupId, $matchId));
    }

    public function testNullDeadlineWithoutExistingOverrideIsNoOp(): void
    {
        $groupId = Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID);
        $matchId = Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID);

        $this->commandBus()->dispatch(new SetGroupMatchDeadlineCommand(
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            groupId: $groupId,
            sportMatchId: $matchId,
            deadline: null,
        ));

        $repo = $this->settingRepository();
        self::assertNull($repo->findByGroupAndMatch($groupId, $matchId));
    }

    public function testRejectsDeadlineAfterKickoff(): void
    {
        $groupId = Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID);
        $matchId = Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID);
        // MATCH_SCHEDULED_ID kickoff is 2025-06-20 18:00 UTC.
        $tooLate = new \DateTimeImmutable('2025-06-20 18:00:01');

        try {
            $this->commandBus()->dispatch(new SetGroupMatchDeadlineCommand(
                editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
                groupId: $groupId,
                sportMatchId: $matchId,
                deadline: $tooLate,
            ));
            self::fail('Expected GroupMatchDeadlineAfterKickoff to be thrown.');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(GroupMatchDeadlineAfterKickoff::class, $e->getPrevious());
        }

        $this->entityManager()->clear();

        $repo = $this->settingRepository();
        self::assertNull($repo->findByGroupAndMatch($groupId, $matchId));
    }
}
