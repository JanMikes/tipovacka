<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Competition;

use App\DataFixtures\AppFixtures;
use App\Entity\CompetitionJoinRequest;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class RequestJoinFlowTest extends WebTestCase
{
    public function testPublicCompetitionRequestRedirectsToDashboard(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
        $user = new User(
            id: Uuid::v7(),
            email: 'reqflow@tipovacka.test',
            password: null,
            nickname: 'reqflow_'.bin2hex(random_bytes(3)),
            createdAt: $now,
        );
        $user->changePassword($hasher->hashPassword($user, 'password'), $now);
        $user->markAsVerified($now);
        $user->popEvents();
        $em->persist($user);
        $em->flush();

        $client->loginUser($user);

        // POST with empty CSRF token — controller redirects with an error flash; no request created.
        $client->request('POST', '/portal/souteze/'.AppFixtures::PUBLIC_COMPETITION_ID.'/pozadat-o-pripojeni');

        self::assertResponseRedirects('/nastenka');

        $requests = $em->createQueryBuilder()
            ->select('r')
            ->from(CompetitionJoinRequest::class, 'r')
            ->where('r.user = :userId')
            ->andWhere('r.competition = :competitionId')
            ->setParameter('userId', $user->id)
            ->setParameter('competitionId', Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID))
            ->getQuery()
            ->getResult();

        self::assertCount(0, $requests);
    }

    public function testPrivateCompetitionRequestForbidden(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
        $user = new User(
            id: Uuid::v7(),
            email: 'reqpriv@tipovacka.test',
            password: null,
            nickname: 'reqpriv_'.bin2hex(random_bytes(3)),
            createdAt: $now,
        );
        $user->changePassword($hasher->hashPassword($user, 'password'), $now);
        $user->markAsVerified($now);
        $user->popEvents();
        $em->persist($user);
        $em->flush();

        $client->loginUser($user);

        // Voter denies REQUEST_JOIN for private match sources — we should get 403 before CSRF is even checked.
        $client->request('POST', '/portal/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID.'/pozadat-o-pripojeni');

        self::assertResponseStatusCodeSame(403);
    }
}
