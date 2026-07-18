<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Competition;

use App\DataFixtures\AppFixtures;
use App\Entity\CompetitionInvitation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class PromoteAnonymousMemberFlowTest extends WebTestCase
{
    public function testOwnerPromotesAnonymousMember(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        $owner = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($owner);
        $client->loginUser($owner);

        $path = sprintf(
            '/portal/souteze/%s/clenove/%s/pridat-email',
            AppFixtures::VERIFIED_COMPETITION_ID,
            AppFixtures::ANONYMOUS_USER_ID,
        );

        $client->request('GET', $path);
        self::assertResponseIsSuccessful();

        $client->submitForm('Odeslat pozvánku', [
            'promote_anonymous_member_form[email]' => 'franta-promoted@example.com',
        ]);

        self::assertResponseRedirects('/portal/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID);

        $em->clear();

        $target = $em->find(User::class, Uuid::fromString(AppFixtures::ANONYMOUS_USER_ID));
        self::assertInstanceOf(User::class, $target);
        self::assertSame('franta-promoted@example.com', $target->email);
        self::assertFalse($target->isAnonymous);
        // Still no password — they'll set one by accepting the invitation email.
        self::assertFalse($target->hasPassword);

        $invitation = $em->createQueryBuilder()
            ->select('i')
            ->from(CompetitionInvitation::class, 'i')
            ->where('i.email = :e')
            ->setParameter('e', 'franta-promoted@example.com')
            ->getQuery()
            ->getOneOrNullResult();
        self::assertInstanceOf(CompetitionInvitation::class, $invitation);
        self::assertFalse($invitation->isAccepted);
    }
}
