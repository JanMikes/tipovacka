<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\JoinCompetitionByLink\JoinCompetitionByLinkCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\MatchSource;
use App\Entity\Membership;
use App\Entity\User;
use App\Exception\CompetitionIsGlobal;
use App\Exception\InvalidShareableLink;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class JoinCompetitionByLinkHandlerTest extends IntegrationTestCase
{
    public function testJoinsCompetitionWithValidToken(): void
    {
        $user = $this->createVerifiedUser();

        $this->commandBus()->dispatch(new JoinCompetitionByLinkCommand(
            userId: $user->id,
            token: AppFixtures::VERIFIED_COMPETITION_LINK_TOKEN,
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

    public function testInvalidTokenThrows(): void
    {
        $this->expectException(HandlerFailedException::class);

        try {
            $this->commandBus()->dispatch(new JoinCompetitionByLinkCommand(
                userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
                token: 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            ));
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(InvalidShareableLink::class, $e->getPrevious());

            throw $e;
        }
    }

    /**
     * Defense-in-depth: a global competition is joinable via the entry-fee flow
     * ONLY. Even if one somehow carries a shareable-link token, that link must
     * never buy a fee-free membership.
     */
    public function testGlobalCompetitionCannotBeJoinedByLink(): void
    {
        $user = $this->createVerifiedUser();
        $token = str_repeat('f', 48);
        $competitionId = $this->createGlobalCompetitionWithToken($token);

        try {
            $this->commandBus()->dispatch(new JoinCompetitionByLinkCommand(
                userId: $user->id,
                token: $token,
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

    private function createGlobalCompetitionWithToken(string $token): Uuid
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
            name: 'Globální s odkazem',
            description: null,
            pin: null,
            shareableLinkToken: $token,
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
            email: 'linkjoiner@tipovacka.test',
            password: null,
            nickname: 'linkjoiner',
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
