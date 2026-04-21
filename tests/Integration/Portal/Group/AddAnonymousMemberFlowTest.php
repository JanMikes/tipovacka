<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Group;

use App\DataFixtures\AppFixtures;
use App\Entity\Membership;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class AddAnonymousMemberFlowTest extends WebTestCase
{
    public function testOwnerCanAddAnonymousMember(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        $owner = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($owner);
        $client->loginUser($owner);

        $client->request('GET', '/portal/skupiny/'.AppFixtures::VERIFIED_GROUP_ID.'/clenove/bez-emailu');
        self::assertResponseIsSuccessful();

        $client->submitForm('Přidat tipujícího', [
            'anonymous_member_form[firstName]' => 'Josef',
            'anonymous_member_form[lastName]' => 'Strejda',
        ]);

        self::assertResponseRedirects('/portal/skupiny/'.AppFixtures::VERIFIED_GROUP_ID);

        $em->clear();

        $user = $em->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('u.firstName = :fn AND u.lastName = :ln')
            ->setParameter('fn', 'Josef')
            ->setParameter('ln', 'Strejda')
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(User::class, $user);
        self::assertNull($user->email);
        self::assertNull($user->nickname);

        $membership = $em->createQueryBuilder()
            ->select('m')->from(Membership::class, 'm')
            ->where('m.user = :uid')->setParameter('uid', $user->id)
            ->andWhere('m.group = :gid')->setParameter('gid', Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID))
            ->andWhere('m.leftAt IS NULL')
            ->getQuery()->getOneOrNullResult();
        self::assertInstanceOf(Membership::class, $membership);

        // Anonymous badge + display name visible on the group detail page.
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Josef Strejda');
        self::assertSelectorTextContains('body', 'Anonymní');
    }

    public function testNonMemberCannotAccessForm(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
        $stranger = new User(
            id: Uuid::v7(),
            email: 'stranger-anon@tipovacka.test',
            password: null,
            nickname: 'stranger_anon_'.bin2hex(random_bytes(3)),
            createdAt: $now,
        );
        $stranger->changePassword($hasher->hashPassword($stranger, 'password'), $now);
        $stranger->markAsVerified($now);
        $stranger->popEvents();
        $em->persist($stranger);
        $em->flush();

        $client->loginUser($stranger);

        $client->request('GET', '/portal/skupiny/'.AppFixtures::VERIFIED_GROUP_ID.'/clenove/bez-emailu');
        self::assertResponseStatusCodeSame(403);
    }
}
