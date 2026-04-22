<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Group;

use App\DataFixtures\AppFixtures;
use App\Entity\Group;
use App\Entity\Guess;
use App\Entity\Membership;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class ManageMemberTipsFlowTest extends WebTestCase
{
    public function testOwnerCanFillGuessForUnverifiedMember(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        $owner = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        $group = $em->find(Group::class, Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID));
        $unverified = $em->find(User::class, Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID));
        self::assertNotNull($owner);
        self::assertNotNull($group);
        self::assertNotNull($unverified);

        $membership = new Membership(
            id: Uuid::v7(),
            group: $group,
            user: $unverified,
            joinedAt: new \DateTimeImmutable('2025-06-15 12:00:00 UTC'),
        );
        $membership->popEvents();
        $em->persist($membership);
        $em->flush();

        $client->loginUser($owner);

        // Land on page without a selected member: shows selectbox, no score form yet.
        $crawler = $client->request('GET', '/portal/skupiny/'.AppFixtures::VERIFIED_GROUP_ID.'/spravovat-tipy');
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('select[name="member"]')->count(), 'Member selectbox not found.');

        // Pick the unverified member via query param (as the <select>'s auto-submit would).
        $crawler = $client->request(
            'GET',
            '/portal/skupiny/'.AppFixtures::VERIFIED_GROUP_ID.'/spravovat-tipy?member='.AppFixtures::UNVERIFIED_USER_ID,
        );
        self::assertResponseIsSuccessful();

        $batchAction = sprintf(
            '/portal/skupiny/%s/spravovat-tipy/%s',
            AppFixtures::VERIFIED_GROUP_ID,
            AppFixtures::UNVERIFIED_USER_ID,
        );
        $formNode = $crawler->filter(sprintf('form[action="%s"]', $batchAction));
        self::assertGreaterThan(0, $formNode->count(), 'Batch save form not found.');

        $form = $formNode->form();
        $form['guesses['.AppFixtures::MATCH_PRIVATE_SCHEDULED_ID.'][homeScore]'] = '2';
        $form['guesses['.AppFixtures::MATCH_PRIVATE_SCHEDULED_ID.'][awayScore]'] = '1';
        $client->submit($form);

        self::assertResponseRedirects();

        $em->clear();
        /** @var Guess|null $guess */
        $guess = $em->createQueryBuilder()
            ->select('g')->from(Guess::class, 'g')
            ->where('g.user = :u')
            ->andWhere('g.sportMatch = :m')
            ->andWhere('g.group = :gr')
            ->setParameter('u', Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID))
            ->setParameter('m', Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID))
            ->setParameter('gr', Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID))
            ->getQuery()->getOneOrNullResult();
        self::assertInstanceOf(Guess::class, $guess);
        self::assertSame(2, $guess->homeScore);
        self::assertSame(1, $guess->awayScore);
    }

    public function testMemberOptionsCarryDataDataJson(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        $owner = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($owner);
        $owner->updateProfile(firstName: 'Jan', lastName: 'Tipař', phone: null, now: $now);
        $em->flush();

        $client->loginUser($owner);

        $crawler = $client->request('GET', '/portal/skupiny/'.AppFixtures::VERIFIED_GROUP_ID.'/spravovat-tipy');
        self::assertResponseIsSuccessful();

        $ownerOption = $crawler->filter('select[name="member"] option[value="'.AppFixtures::VERIFIED_USER_ID.'"]');
        self::assertGreaterThan(0, $ownerOption->count());
        $ownerData = json_decode((string) $ownerOption->attr('data-data'), true);
        self::assertIsArray($ownerData);
        self::assertSame(AppFixtures::VERIFIED_USER_NICKNAME, $ownerData['nickname']);
        self::assertSame('Jan Tipař', $ownerData['fullName']);
        self::assertFalse($ownerData['unverified']);

        // Anonymous member: no nickname, has fullName.
        $anonymousOption = $crawler->filter('select[name="member"] option[value="'.AppFixtures::ANONYMOUS_USER_ID.'"]');
        self::assertGreaterThan(0, $anonymousOption->count());
        $anonymousData = json_decode((string) $anonymousOption->attr('data-data'), true);
        self::assertIsArray($anonymousData);
        self::assertSame('', $anonymousData['nickname']);
        self::assertSame(
            AppFixtures::ANONYMOUS_USER_FIRST_NAME.' '.AppFixtures::ANONYMOUS_USER_LAST_NAME,
            $anonymousData['fullName'],
        );

        // Option text remains searchable: includes both nickname and fullName for the owner.
        $ownerText = trim($ownerOption->text());
        self::assertStringContainsString(AppFixtures::VERIFIED_USER_NICKNAME, $ownerText);
        self::assertStringContainsString('Jan Tipař', $ownerText);
    }

    public function testNonOwnerGets403ForManagePage(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        $secondUser = $em->find(User::class, Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID));
        $group = $em->find(Group::class, Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID));
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

        $client->request('GET', '/portal/skupiny/'.AppFixtures::VERIFIED_GROUP_ID.'/spravovat-tipy');
        self::assertResponseStatusCodeSame(403);
    }
}
