<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Leaderboard;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\Membership;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class SoutezSwitcherFlowTest extends WebTestCase
{
    public function testSwitcherListsAllUserCompetitionsAsGroupedOptions(): void
    {
        $client = static::createClient();
        $verified = $this->userInTwoCompetitions($client->getContainer());
        $client->loginUser($verified);

        $client->request('GET', '/zebricek?soutez='.AppFixtures::PUBLIC_COMPETITION_ID);
        self::assertResponseIsSuccessful();

        $body = $client->getResponse()->getContent();
        self::assertIsString($body);

        // Switcher lists both of the user's soutěže by name…
        self::assertStringContainsString(AppFixtures::PUBLIC_COMPETITION_NAME, $body);
        self::assertStringContainsString(AppFixtures::VERIFIED_COMPETITION_NAME, $body);

        // …as options of a plain GET form pointing at the resolver route, which is what
        // keeps the control working with JavaScript disabled.
        self::assertStringContainsString('<form method="get" action="/zebricek"', $body);
        self::assertStringContainsString('name="soutez"', $body);
        self::assertStringContainsString('value="'.AppFixtures::VERIFIED_COMPETITION_ID.'"', $body);
        self::assertStringContainsString('<optgroup label="Probíhající">', $body);
    }

    public function testChoosingASoutezSwitchesTheLeaderboard(): void
    {
        $client = static::createClient();
        $verified = $this->userInTwoCompetitions($client->getContainer());
        $client->loginUser($verified);

        $client->request('GET', '/zebricek?soutez='.AppFixtures::VERIFIED_COMPETITION_ID);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Žebříček');
        // The page scoped itself to the chosen soutěž — its detail link proves which one.
        self::assertSelectorExists('a[href="/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID.'"]');
    }

    public function testForeignSoutezIdFallsBackToTheViewersOwnSoutez(): void
    {
        $client = static::createClient();
        $verified = $this->userInTwoCompetitions($client->getContainer());
        $client->loginUser($verified);

        // A competition the viewer may not see must never resolve — the page silently
        // falls back to a soutěž of their own rather than leaking a foreign board.
        $client->request('GET', '/zebricek?soutez=de305d54-75b4-431b-adb2-eb6b9e546014');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/souteze/'.AppFixtures::PUBLIC_COMPETITION_ID.'"]');
    }

    /**
     * The single-competition (static chip) and zero-competition variants render side by side
     * on the styleguide — see App\Tests\Integration\DesignStyleguideFlowTest and
     * App\Tests\Unit\Twig\Components\SoutezSwitcherTest.
     *
     * The verified user already owns VERIFIED_COMPETITION. Add them to PUBLIC_COMPETITION too
     * (and most recently, so it becomes their primary), giving them the ≥2 soutěže the
     * switcher needs.
     */
    private function userInTwoCompetitions(\Psr\Container\ContainerInterface $container): User
    {
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        $verified = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($verified);
        $publicCompetition = $em->find(Competition::class, Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID));
        self::assertNotNull($publicCompetition);

        $membership = new Membership(
            id: Uuid::v7(),
            competition: $publicCompetition,
            user: $verified,
            joinedAt: $now,
        );
        $membership->popEvents();
        $em->persist($membership);
        $em->flush();

        return $verified;
    }
}
