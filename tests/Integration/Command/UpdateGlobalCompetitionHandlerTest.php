<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\JoinGlobalCompetition\JoinGlobalCompetitionCommand;
use App\Command\LeaveCompetition\LeaveCompetitionCommand;
use App\Command\UpdateGlobalCompetition\UpdateGlobalCompetitionCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Enum\CompetitionMonetization;
use App\Exception\CompetitionIsNotGlobal;
use App\Exception\GlobalCompetitionFeeLocked;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Uid\Uuid;

final class UpdateGlobalCompetitionHandlerTest extends IntegrationTestCase
{
    public function testUpdatesFeeWhileOwnerIsSoleMember(): void
    {
        $this->commandBus()->dispatch(new UpdateGlobalCompetitionCommand(
            competitionId: Uuid::fromString(AppFixtures::GLOBAL_COMPETITION_ID),
            entryFeeCredits: 75,
            monetization: CompetitionMonetization::Boosts,
        ));

        $this->entityManager()->clear();

        /** @var Competition $competition */
        $competition = $this->entityManager()->find(Competition::class, Uuid::fromString(AppFixtures::GLOBAL_COMPETITION_ID));
        self::assertSame(75, $competition->entryFeeCredits);
        self::assertSame(CompetitionMonetization::Boosts, $competition->monetization);
    }

    public function testFeeLockedOnceANonOwnerJoins(): void
    {
        // A non-owner joins the free global competition ⇒ its terms lock.
        $this->commandBus()->dispatch(new JoinGlobalCompetitionCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::FREE_GLOBAL_COMPETITION_ID),
        ));

        try {
            $this->commandBus()->dispatch(new UpdateGlobalCompetitionCommand(
                competitionId: Uuid::fromString(AppFixtures::FREE_GLOBAL_COMPETITION_ID),
                entryFeeCredits: 40,
                monetization: CompetitionMonetization::None,
            ));
            self::fail('Expected GlobalCompetitionFeeLocked.');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(GlobalCompetitionFeeLocked::class, $this->firstWrappedException($e));
        }

        $this->entityManager()->clear();
        /** @var Competition $competition */
        $competition = $this->entityManager()->find(Competition::class, Uuid::fromString(AppFixtures::FREE_GLOBAL_COMPETITION_ID));
        self::assertSame(0, $competition->entryFeeCredits);
    }

    public function testFeeStaysLockedAfterTheJoinerLeaves(): void
    {
        // A non-owner joins the free global competition ⇒ terms lock.
        $this->commandBus()->dispatch(new JoinGlobalCompetitionCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::FREE_GLOBAL_COMPETITION_ID),
        ));

        // …then leaves again. The lock is monotonic: a left member's row persists,
        // so the fee must STAY locked (active-member counting would wrongly unlock).
        $this->commandBus()->dispatch(new LeaveCompetitionCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::FREE_GLOBAL_COMPETITION_ID),
        ));

        try {
            $this->commandBus()->dispatch(new UpdateGlobalCompetitionCommand(
                competitionId: Uuid::fromString(AppFixtures::FREE_GLOBAL_COMPETITION_ID),
                entryFeeCredits: 40,
                monetization: CompetitionMonetization::None,
            ));
            self::fail('Expected GlobalCompetitionFeeLocked.');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(GlobalCompetitionFeeLocked::class, $this->firstWrappedException($e));
        }

        $this->entityManager()->clear();
        /** @var Competition $competition */
        $competition = $this->entityManager()->find(Competition::class, Uuid::fromString(AppFixtures::FREE_GLOBAL_COMPETITION_ID));
        self::assertSame(0, $competition->entryFeeCredits);
    }

    public function testNonGlobalCompetitionIsRejected(): void
    {
        try {
            $this->commandBus()->dispatch(new UpdateGlobalCompetitionCommand(
                competitionId: Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
                entryFeeCredits: 10,
                monetization: CompetitionMonetization::None,
            ));
            self::fail('Expected CompetitionIsNotGlobal.');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(CompetitionIsNotGlobal::class, $this->firstWrappedException($e));
        }
    }
}
