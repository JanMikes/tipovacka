<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Competition;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\MatchSource;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Global competitions disable on-behalf tipping, anonymous members and
 * invite/PIN/link management (voter-level); the detail page hides the
 * corresponding UI. Admin keeps member removal for moderation.
 */
final class GlobalCompetitionGuardsFlowTest extends WebTestCase
{
    public function testOnBehalfTippingIsForbiddenForGlobalCompetition(): void
    {
        $client = static::createClient();
        $this->loginAdmin($client);

        $client->request('GET', '/portal/souteze/'.AppFixtures::GLOBAL_COMPETITION_ID.'/spravovat-tipy');

        self::assertResponseStatusCodeSame(403);
    }

    public function testGlobalDetailHidesJoinMechanicsAndOnBehalfUi(): void
    {
        $client = static::createClient();
        $this->loginAdmin($client);

        $client->request('GET', '/portal/souteze/'.AppFixtures::GLOBAL_COMPETITION_ID);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('body', 'Rychlé pozvánky');
        self::assertSelectorTextNotContains('body', 'Pozvánky e-mailem');
        self::assertSelectorTextNotContains('body', 'Tipovat za členy');
    }

    public function testNonGlobalDetailStillShowsJoinMechanics(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        // VERIFIED_USER owns the (non-global) VERIFIED_COMPETITION.
        $owner = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($owner);
        $client->loginUser($owner);

        $client->request('GET', '/portal/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Rychlé pozvánky');
        self::assertSelectorTextContains('body', 'Tipovat za členy');
    }

    /**
     * Defense-in-depth: even if a global competition somehow carried a shareable
     * link token, the landing page must NOT resolve it to a joinable context —
     * it renders the „invalid link" (404) state instead. Globals are entry-fee only.
     */
    public function testGlobalShareableLinkLandingIsNotJoinable(): void
    {
        $client = static::createClient();
        $token = str_repeat('e', 48);
        $this->persistGlobalCompetitionWithToken($client, $token);

        $client->request('GET', '/souteze/pozvanka/'.$token);

        self::assertResponseStatusCodeSame(404);
    }

    private function persistGlobalCompetitionWithToken(KernelBrowser $client, string $token): void
    {
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        /** @var MatchSource $source */
        $source = $em->find(MatchSource::class, Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID));
        /** @var User $admin */
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));

        $competition = new Competition(
            id: Uuid::v7(),
            matchSource: $source,
            owner: $admin,
            name: 'Globální s odkazem',
            description: null,
            pin: null,
            shareableLinkToken: $token,
            createdAt: new \DateTimeImmutable('2025-06-15 12:00:00 UTC'),
            isGlobal: true,
            entryFeeCredits: 50,
        );
        $competition->popEvents();
        $em->persist($competition);
        $em->flush();
    }

    private function loginAdmin(KernelBrowser $client): void
    {
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);
    }
}
