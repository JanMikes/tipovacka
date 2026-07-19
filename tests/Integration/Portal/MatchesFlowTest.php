<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\Guess;
use App\Entity\Membership;
use App\Entity\SportMatch;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class MatchesFlowTest extends WebTestCase
{
    public function testAnonymousRedirectedToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/zapasy');

        self::assertResponseRedirects('/prihlaseni');
    }

    public function testPageListsMatchesFromMultipleSouteze(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        // Verified owns VERIFIED_COMPETITION (private match source → match "Tygři vs Lvi").
        // Add them to PUBLIC_COMPETITION (public match source → "Bohemians 1905" etc.) so the
        // page must aggregate matches across two soutěže / match sources.
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

        $client->loginUser($verified);

        $client->request('GET', '/zapasy');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Vaše zápasy');

        $body = $client->getResponse()->getContent();
        self::assertIsString($body);
        // Private-match source match…
        self::assertStringContainsString('Tygři', $body);
        // …and public-match source match.
        self::assertStringContainsString('Bohemians 1905', $body);
    }

    public function testFinishedFilterShowsOnlyFinishedMatches(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        // Admin (PUBLIC_COMPETITION) has a finished match (Bohemians) and a scheduled one (Sparta).
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/zapasy?filtr=ukoncene');
        self::assertResponseIsSuccessful();

        $body = $client->getResponse()->getContent();
        self::assertIsString($body);
        self::assertStringContainsString('Bohemians 1905', $body);
        self::assertStringNotContainsString('Sparta Praha', $body);
    }

    public function testTippableFilterShowsOnlyOpenFutureMatches(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/zapasy?filtr=tipovatelne');
        self::assertResponseIsSuccessful();

        $body = $client->getResponse()->getContent();
        self::assertIsString($body);
        // Scheduled future match is tippable…
        self::assertStringContainsString('Sparta Praha', $body);
        // …the finished one is not.
        self::assertStringNotContainsString('Bohemians 1905', $body);
    }

    public function testMissingTipFractionCountsOnlyOpenCompetitions(): void
    {
        // Admin is in PUBLIC_COMPETITION (all-mode) and — added here — SUBSET_COMPETITION,
        // both on PUBLIC_SOURCE and both including MATCH_SCHEDULED (2025-06-20, open).
        // Admin tipped it in PUBLIC only ⇒ „Chybí tip (1/2)" (2 open, 1 guessed).
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $subset = $em->find(Competition::class, Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID));
        self::assertNotNull($subset);
        $publicCompetition = $em->find(Competition::class, Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID));
        self::assertNotNull($publicCompetition);
        $scheduledMatch = $em->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID));
        self::assertNotNull($scheduledMatch);

        $membership = new Membership(id: Uuid::v7(), competition: $subset, user: $admin, joinedAt: $now);
        $membership->popEvents();
        $em->persist($membership);

        // Admin also owns the two S09 global competitions on PUBLIC_SOURCE (they
        // include MATCH_SCHEDULED too). Lock them so this scenario stays at the two
        // intended OPEN competitions (PUBLIC + SUBSET).
        foreach ([AppFixtures::GLOBAL_COMPETITION_ID, AppFixtures::FREE_GLOBAL_COMPETITION_ID] as $globalId) {
            $global = $em->find(Competition::class, Uuid::fromString($globalId));
            self::assertNotNull($global);
            $global->lockTips($now);
            $global->popEvents();
        }

        $guess = new Guess(
            id: Uuid::v7(),
            user: $admin,
            sportMatch: $scheduledMatch,
            competition: $publicCompetition,
            homeScore: 1,
            awayScore: 0,
            submittedAt: $now,
        );
        $guess->popEvents();
        $em->persist($guess);
        $em->flush();

        $client->loginUser($admin);

        $client->request('GET', '/zapasy');
        self::assertResponseIsSuccessful();
        $body = (string) $client->getResponse()->getContent();
        // Denominator counts only OPEN competitions (both open here).
        self::assertStringContainsString('Chybí tip (1/2)', $body);

        // Locking the un-tipped competition drops it from the open count: the match
        // now reads as fully tipped among still-open competitions.
        $subset->lockTips($now);
        $subset->popEvents();
        $em->flush();

        $client->request('GET', '/zapasy');
        $body = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString('Chybí tip (1/2)', $body);
        self::assertStringContainsString('Tip odeslán', $body);
    }

    public function testTippableFilterRespectsPerCompetitionLocking(): void
    {
        // „Tipovatelné" is per-competition: after the user's only competition
        // including the match locks its tips, the match stops being tippable
        // even though its kickoff is still ahead.
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        $verified = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($verified);
        $client->loginUser($verified);

        $client->request('GET', '/zapasy?filtr=tipovatelne');
        self::assertResponseIsSuccessful();
        $body = $client->getResponse()->getContent();
        self::assertIsString($body);
        self::assertStringContainsString('Tygři', $body);

        $competition = $em->find(Competition::class, Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID));
        self::assertNotNull($competition);
        $competition->lockTips(new \DateTimeImmutable('2025-06-15 12:00:00 UTC'));
        $competition->popEvents();
        $em->flush();

        $client->request('GET', '/zapasy?filtr=tipovatelne');
        self::assertResponseIsSuccessful();
        $body = $client->getResponse()->getContent();
        self::assertIsString($body);
        self::assertStringNotContainsString('Tygři', $body);

        // On the unfiltered list the row renders as locked, not as missing a tip.
        $client->request('GET', '/zapasy');
        $body = $client->getResponse()->getContent();
        self::assertIsString($body);
        self::assertStringContainsString('Tygři', $body);
        self::assertStringContainsString('Uzamčeno', $body);
        self::assertStringNotContainsString('Chybí tip', $body);
    }
}
