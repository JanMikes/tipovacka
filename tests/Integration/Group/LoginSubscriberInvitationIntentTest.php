<?php

declare(strict_types=1);

namespace App\Tests\Integration\Group;

use App\DataFixtures\AppFixtures;
use App\Entity\GroupInvitation;
use App\Entity\Membership;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class LoginSubscriberInvitationIntentTest extends WebTestCase
{
    public function testAnonymousVisitToInvitationStoresIntentAndLoginAcceptsIt(): void
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
            email: 'intenttest@tipovacka.test',
            password: null,
            nickname: 'intenttest_'.bin2hex(random_bytes(3)),
            createdAt: $now,
        );
        $user->changePassword($hasher->hashPassword($user, 'password'), $now);
        $user->markAsVerified($now);
        $user->popEvents();
        $em->persist($user);
        $em->flush();

        // 1) Anonymous visit: should redirect to login, stashing intent in session.
        $client->request('GET', '/pozvanka/'.AppFixtures::PENDING_INVITATION_TOKEN);
        self::assertResponseRedirects('/prihlaseni');

        // 2) Submit login form in the same session — LoginSubscriber should consume the intent.
        $client->request('GET', '/prihlaseni');
        $client->submitForm('Přihlásit se', [
            '_username' => $user->email,
            '_password' => 'password',
        ]);

        // After login, should redirect to the group detail via invitation intent.
        self::assertResponseRedirects('/portal/skupiny/'.AppFixtures::PUBLIC_GROUP_ID);

        $em->clear();
        $invitation = $em->find(GroupInvitation::class, Uuid::fromString(AppFixtures::PENDING_INVITATION_ID));
        self::assertNotNull($invitation);
        self::assertTrue($invitation->isAccepted);

        $memberships = $em->createQueryBuilder()
            ->select('m')
            ->from(Membership::class, 'm')
            ->where('m.user = :userId')
            ->andWhere('m.group = :groupId')
            ->setParameter('userId', $user->id)
            ->setParameter('groupId', Uuid::fromString(AppFixtures::PUBLIC_GROUP_ID))
            ->getQuery()
            ->getResult();

        self::assertCount(1, $memberships);
    }
}
