<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\JoinCompetitionByPin\JoinCompetitionByPinCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\MatchSource;
use App\Entity\Membership;
use App\Entity\User;
use App\Exception\AlreadyMember;
use App\Exception\CompetitionIsGlobal;
use App\Exception\InvalidPin;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class JoinCompetitionByPinHandlerTest extends IntegrationTestCase
{
    public function testJoinsCompetitionWithValidPin(): void
    {
        $user = $this->createVerifiedUser();

        $this->commandBus()->dispatch(new JoinCompetitionByPinCommand(
            userId: $user->id,
            pin: AppFixtures::VERIFIED_COMPETITION_PIN,
        ));

        $em = $this->entityManager();
        $em->clear();

        $memberships = $em->createQueryBuilder()
            ->select('m')
            ->from(Membership::class, 'm')
            ->where('m.user = :userId')
            ->andWhere('m.competition = :competitionId')
            ->setParameter('userId', $user->id)
            ->setParameter('competitionId', Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID))
            ->getQuery()
            ->getResult();

        self::assertCount(1, $memberships);
    }

    public function testInvalidPinThrows(): void
    {
        $this->expectException(HandlerFailedException::class);

        try {
            $this->commandBus()->dispatch(new JoinCompetitionByPinCommand(
                userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
                pin: '99999999',
            ));
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(InvalidPin::class, $e->getPrevious());

            throw $e;
        }
    }

    public function testAlreadyMemberThrows(): void
    {
        $this->expectException(HandlerFailedException::class);

        try {
            $this->commandBus()->dispatch(new JoinCompetitionByPinCommand(
                userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
                pin: AppFixtures::VERIFIED_COMPETITION_PIN,
            ));
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(AlreadyMember::class, $e->getPrevious());

            throw $e;
        }
    }

    /**
     * Defense-in-depth: a global competition is joinable via the entry-fee flow
     * ONLY. Even if one somehow carries a PIN, that PIN must never buy a fee-free
     * membership.
     */
    public function testGlobalCompetitionCannotBeJoinedByPin(): void
    {
        $user = $this->createVerifiedUser();
        $pin = 'GLOB1234';
        $competitionId = $this->createGlobalCompetitionWithPin($pin);

        try {
            $this->commandBus()->dispatch(new JoinCompetitionByPinCommand(
                userId: $user->id,
                pin: $pin,
            ));
            self::fail('Expected CompetitionIsGlobal.');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(CompetitionIsGlobal::class, $this->firstWrappedException($e));
        }

        $em = $this->entityManager();
        $em->clear();

        $memberships = $em->createQueryBuilder()
            ->select('m')
            ->from(Membership::class, 'm')
            ->where('m.user = :userId')
            ->andWhere('m.competition = :competitionId')
            ->setParameter('userId', $user->id)
            ->setParameter('competitionId', $competitionId)
            ->getQuery()
            ->getResult();

        self::assertCount(0, $memberships);
    }

    private function createGlobalCompetitionWithPin(string $pin): Uuid
    {
        $em = $this->entityManager();
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        /** @var MatchSource $source */
        $source = $em->find(MatchSource::class, Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID));
        /** @var User $admin */
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));

        $competition = new Competition(
            id: $this->identityProvider()->next(),
            matchSource: $source,
            owner: $admin,
            name: 'Globální s PINem',
            description: null,
            pin: $pin,
            shareableLinkToken: null,
            createdAt: $now,
            isGlobal: true,
            entryFeeCredits: 50,
        );
        $competition->popEvents();
        $em->persist($competition);
        $em->flush();

        return $competition->id;
    }

    private function createVerifiedUser(): User
    {
        $em = $this->entityManager();
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
        $user = new User(
            id: $this->identityProvider()->next(),
            email: 'joiner@tipovacka.test',
            password: null,
            nickname: 'joiner',
            createdAt: $now,
        );
        $user->changePassword($hasher->hashPassword($user, 'password'), $now);
        $user->markAsVerified($now);
        $user->popEvents();
        $em->persist($user);
        $em->flush();

        return $user;
    }
}
