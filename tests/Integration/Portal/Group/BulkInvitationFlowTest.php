<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Group;

use App\DataFixtures\AppFixtures;
use App\Entity\GroupInvitation;
use App\Entity\Membership;
use App\Entity\User;
use App\Enum\InvitationKind;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

final class BulkInvitationFlowTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    public function testOwnerCanBulkInviteAndInviteeCompletesRegistration(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        $owner = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($owner);
        $client->loginUser($owner);

        $client->request('POST', '/portal/skupiny/'.AppFixtures::VERIFIED_GROUP_ID.'/pozvanky/hromadne', [
            'bulk_invitation_form' => [
                'emails' => "alice@example.com\nbob@example.com, alice@example.com",
            ],
        ]);

        self::assertResponseRedirects('/portal/skupiny/'.AppFixtures::VERIFIED_GROUP_ID);

        $em->clear();
        $invitations = $em->createQueryBuilder()
            ->select('i')->from(GroupInvitation::class, 'i')
            ->where('i.group = :groupId')
            ->andWhere('i.email IN (:emails)')
            ->setParameter('groupId', Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID))
            ->setParameter('emails', ['alice@example.com', 'bob@example.com'])
            ->getQuery()->getResult();
        self::assertCount(2, $invitations);

        // Fetch alice's invitation token.
        /** @var GroupInvitation $alice */
        $alice = $em->createQueryBuilder()
            ->select('i')->from(GroupInvitation::class, 'i')
            ->where('i.email = :e')->setParameter('e', 'alice@example.com')
            ->getQuery()->getOneOrNullResult();

        // Log out to simulate the invitee clicking the link fresh.
        $client->request('GET', '/odhlaseni');

        // Mount the unified live invitation form for alice's token (email-kind, locked email).
        // Stub account exists for alice → the form should adapt into the "set password" branch.
        $component = $this->createLiveComponent('Auth:InvitationForm', [
            'kind' => InvitationKind::Email->value,
            'token' => $alice->token,
        ], $client);

        $response = $component->submitForm([
            'invitation_form' => [
                'email' => 'alice@example.com',
                'password' => 'Str0ngP4ssword!',
                'passwordConfirm' => 'Str0ngP4ssword!',
                'gdprConsent' => '1',
            ],
        ], 'submit')->response();

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/portal/skupiny/'.AppFixtures::VERIFIED_GROUP_ID, $response->headers->get('Location'));

        $em->clear();

        /** @var User $alice */
        $alice = $em->createQueryBuilder()
            ->select('u')->from(User::class, 'u')
            ->where('u.email = :e')->setParameter('e', 'alice@example.com')
            ->getQuery()->getOneOrNullResult();
        self::assertTrue($alice->hasPassword);
        self::assertTrue($alice->isVerified);

        $membership = $em->createQueryBuilder()
            ->select('m')->from(Membership::class, 'm')
            ->where('m.user = :u')
            ->andWhere('m.group = :g')
            ->andWhere('m.leftAt IS NULL')
            ->setParameter('u', $alice->id)
            ->setParameter('g', Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID))
            ->getQuery()->getOneOrNullResult();
        self::assertInstanceOf(Membership::class, $membership);
    }

    public function testNonOwnerMemberGets403ForBulk(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        // Admin is a verified user, but is not owner of the private group.
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        // Admin has ROLE_ADMIN, which passes GroupVoter::MANAGE_MEMBERS. Swap to a plain non-owner member.

        // Make SECOND_VERIFIED_USER a member of the VERIFIED_GROUP (not the owner).
        $secondUser = $em->find(User::class, Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID));
        $group = $em->find(\App\Entity\Group::class, Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID));
        self::assertNotNull($secondUser);
        self::assertNotNull($group);

        $membership = new Membership(
            id: Uuid::v7(),
            group: $group,
            user: $secondUser,
            joinedAt: new \DateTimeImmutable('2025-06-15 12:00:00 UTC'),
        );
        $membership->popEvents();
        $em->persist($membership);
        $em->flush();

        $client->loginUser($secondUser);

        $client->request('POST', '/portal/skupiny/'.AppFixtures::VERIFIED_GROUP_ID.'/pozvanky/hromadne', [
            'bulk_invitation_form' => ['emails' => 'someone@example.com'],
        ]);

        self::assertResponseStatusCodeSame(403);
    }
}
