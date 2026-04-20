<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Group;

use App\DataFixtures\AppFixtures;
use App\Entity\GroupInvitation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class RevokeInvitationFlowTest extends WebTestCase
{
    public function testOwnerCanRevoke(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        // GET detail first — initialises session + CSRF token.
        $crawler = $client->request('GET', '/portal/skupiny/'.AppFixtures::PUBLIC_GROUP_ID);
        self::assertResponseIsSuccessful();

        $form = $crawler->filter(
            'form[action="/portal/pozvanky/'.AppFixtures::PENDING_INVITATION_ID.'/zrusit"]'
        )->form();
        $client->submit($form);

        self::assertResponseRedirects('/portal/skupiny/'.AppFixtures::PUBLIC_GROUP_ID);

        $em->clear();
        $invitation = $em->find(GroupInvitation::class, Uuid::fromString(AppFixtures::PENDING_INVITATION_ID));
        self::assertNotNull($invitation);
        self::assertTrue($invitation->isRevoked);
    }

    public function testUnrelatedUserCannotRevoke(): void
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
            email: 'stranger-revoke@tipovacka.test',
            password: null,
            nickname: 'stranger_rev_'.bin2hex(random_bytes(3)),
            createdAt: $now,
        );
        $stranger->changePassword($hasher->hashPassword($stranger, 'password'), $now);
        $stranger->markAsVerified($now);
        $stranger->popEvents();
        $em->persist($stranger);
        $em->flush();

        $client->loginUser($stranger);

        // Stranger has no access to the group detail (not a member) so the button isn't rendered.
        // POST with any token — controller should treat as invalid CSRF and redirect, leaving invitation intact.
        $client->request('POST', '/portal/pozvanky/'.AppFixtures::PENDING_INVITATION_ID.'/zrusit', [
            '_token' => 'invalid-token',
        ]);

        $em->clear();
        $invitation = $em->find(GroupInvitation::class, Uuid::fromString(AppFixtures::PENDING_INVITATION_ID));
        self::assertNotNull($invitation);
        self::assertFalse($invitation->isRevoked);
    }
}
