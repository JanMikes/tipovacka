<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Competition;

use App\DataFixtures\AppFixtures;
use App\Entity\CompetitionInvitation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class SendInvitationFlowTest extends WebTestCase
{
    public function testMemberCanSendInvitation(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        $owner = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($owner);
        $client->loginUser($owner);

        $client->request('GET', '/portal/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID);
        self::assertResponseIsSuccessful();

        $client->submitForm('Odeslat pozvánku', [
            'send_invitation_form[email]' => 'newguest@tipovacka.test',
        ]);

        self::assertResponseRedirects('/portal/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID);

        $em->clear();
        $result = $em->createQueryBuilder()
            ->select('i')
            ->from(CompetitionInvitation::class, 'i')
            ->where('i.competition = :competitionId')
            ->andWhere('i.email = :email')
            ->setParameter('competitionId', Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID))
            ->setParameter('email', 'newguest@tipovacka.test')
            ->getQuery()
            ->getResult();

        self::assertCount(1, $result);
    }

    public function testNonMemberCannotSendInvitation(): void
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
            email: 'stranger-inv@tipovacka.test',
            password: null,
            nickname: 'stranger_inv_'.bin2hex(random_bytes(3)),
            createdAt: $now,
        );
        $stranger->changePassword($hasher->hashPassword($stranger, 'password'), $now);
        $stranger->markAsVerified($now);
        $stranger->popEvents();
        $em->persist($stranger);
        $em->flush();

        $client->loginUser($stranger);

        $client->request('POST', '/portal/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID.'/pozvanky/odeslat', [
            'send_invitation_form' => ['email' => 'guest@tipovacka.test'],
        ]);

        self::assertResponseStatusCodeSame(403);
    }
}
