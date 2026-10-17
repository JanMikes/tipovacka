<?php

declare(strict_types=1);

namespace App\Tests\Integration\Admin\MatchSource;

use App\Command\MarkMatchSourceCompleted\MarkMatchSourceCompletedCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\MatchSource;
use App\Entity\User;
use App\Tests\Support\WebFlowHelpers;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class AdminUpdateMatchSourceFlowTest extends WebTestCase
{
    use WebFlowHelpers;

    public function testAdminCanUpdatePublicMatchSource(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/admin/turnaje/'.AppFixtures::PUBLIC_SOURCE_ID.'/upravit');
        self::assertResponseIsSuccessful();

        $client->submitForm('Uložit změny', [
            'match_source_form[name]' => 'Upravený turnaj',
        ]);

        self::assertResponseRedirects('/admin/turnaje');

        $em->clear();
        /** @var MatchSource $matchSource */
        $matchSource = $em->find(MatchSource::class, Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID));
        self::assertSame('Upravený turnaj', $matchSource->name);
    }

    /** The handler refuses a completed source — say so instead of a bare 409 page. */
    public function testCompletedSourceSendsTheAdminToReopenItInsteadOfFailing(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $this->testCommandBus()->dispatch(new MarkMatchSourceCompletedCommand(matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID)));

        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/admin/turnaje/'.AppFixtures::PUBLIC_SOURCE_ID.'/upravit');

        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'je ukončený, proto ho nejde upravit');
    }

    public function testNonAdminForbidden(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', '/admin/turnaje/'.AppFixtures::PUBLIC_SOURCE_ID.'/upravit');
        self::assertResponseStatusCodeSame(403);
    }
}
