<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Leaderboard;

use App\DataFixtures\AppFixtures;
use App\Entity\BoostPurchase;
use App\Entity\Competition;
use App\Entity\CompetitionMatchSelection;
use App\Entity\Guess;
use App\Entity\Membership;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\BoostType;
use App\Enum\CompetitionMatchSelectionMode;
use App\Enum\CompetitionMonetization;
use App\Service\EffectiveTipDeadlineResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Item 20 — „Tabulka tipů" is reachable by every member and reveals itself per
 * match: a FINISHED match's tips are readable by anybody who plays, a Scheduled
 * or Live one only with the entitlement.
 *
 * The rule is the PLATFORM-WIDE one (`TipVisibilityGate`, 2026-07-30): reveal iff
 * the viewer is entitled OR the match has a final result. The deadline plays no
 * part any more, so the last four tests here pin the same answer on match detail,
 * on the competition-scoped match page and on the „Rozložení tipů" aggregate —
 * a single rule that quietly drifts back to a deadline check on one surface is
 * exactly the leak these tests exist to catch.
 *
 * The tips asserted on are deliberately score strings that cannot appear anywhere
 * else in the markup: every fixture kickoff is on the hour (so „7:3" can never be
 * a rendered time) and the finished match's real score is 2:1.
 *
 * A leak here gives away a paid feature, so „is it hidden?" is asserted on the
 * WHOLE response body — a hidden tip must be absent from the markup, not merely
 * blurred or display:none. That is a property of the read model:
 * `MatrixCell::hidden()` carries no scores at all.
 */
final class GuessMatrixVisibilityTest extends WebTestCase
{
    /** The entitled member's tip on the LIVE match — hidden from everybody else. */
    private const string OTHERS_LIVE_TIP = '7:3';

    /** The entitled member's tip on the FINISHED match — readable by every member. */
    private const string OTHERS_FINISHED_TIP = '5:4';

    /** The unentitled viewer's OWN tip on the LIVE match — always readable. */
    private const string OWN_LIVE_TIP = '6:2';

    /**
     * Headline of `Boost:Panel`'s INLINE shape — the tip matrix's whole paywall.
     * Item 23: it IS the booster's one canonical name, so it is read from the enum
     * rather than transcribed (a transcription is how a surface drifts from the shop).
     */
    private static function ctaHeadline(): string
    {
        return BoostType::OthersTips->label();
    }

    private const string MATRIX_PATH = '/zebricek/matice?soutez='.AppFixtures::BOOSTS_COMPETITION_ID;

    /**
     * State 1 — a member with NO entitlement. The page renders, the finished
     * match's tips are readable, the live one's are not, and the unlock CTA is
     * offered instead.
     */
    public function testUnentitledMemberReadsFinishedMatchesOnlyAndGetsTheCta(): void
    {
        $client = static::createClient();
        $em = self::entityManager($client);
        $viewer = $this->seedBoostsWorld($em);

        $client->loginUser($viewer);
        $client->request('GET', self::MATRIX_PATH);

        self::assertResponseIsSuccessful();
        $body = self::body($client);

        self::assertStringContainsString(
            self::OTHERS_FINISHED_TIP,
            $body,
            'A finished match is „odehráno" — every member reads its tips without paying.',
        );
        self::assertStringContainsString(
            self::OWN_LIVE_TIP,
            $body,
            'The viewer always reads their OWN tip, whatever the match state.',
        );
        self::assertStringNotContainsString(
            self::OTHERS_LIVE_TIP,
            $body,
            'The LIVE match is not „odehráno": another member\'s tip must be absent from the MARKUP.',
        );
        self::assertStringContainsString(self::ctaHeadline(), $body);
    }

    /**
     * The behaviour CHANGE of item 20, asserted on its own: the live match's tip
     * deadline has long passed (kickoff 2025-06-15 11:00, MockClock 12:00), which
     * used to reveal the whole column. It no longer does.
     */
    public function testLiveMatchTipsStayHiddenEvenThoughItsDeadlineHasPassed(): void
    {
        $client = static::createClient();
        $em = self::entityManager($client);
        $viewer = $this->seedBoostsWorld($em);

        $competition = self::competition($em, AppFixtures::BOOSTS_COMPETITION_ID);
        $live = self::match($em, AppFixtures::MATCH_LIVE_ID);

        /** @var EffectiveTipDeadlineResolver $deadlines */
        $deadlines = $client->getContainer()->get(EffectiveTipDeadlineResolver::class);
        self::assertLessThan(
            new \DateTimeImmutable('2025-06-15 12:00:00 UTC'),
            $deadlines->deadlineFor($competition, $live),
            'Pre-condition: the live match is past its (userless) tip deadline.',
        );

        $client->loginUser($viewer);
        $client->request('GET', self::MATRIX_PATH);

        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString(self::OTHERS_LIVE_TIP, self::body($client));
    }

    /** State 2 — an entitled member (owns the OthersTips boost) reads everything. */
    public function testEntitledMemberReadsEveryColumnAndIsNotSoldAnything(): void
    {
        $client = static::createClient();
        $em = self::entityManager($client);
        $this->seedBoostsWorld($em);

        $entitled = self::user($em, AppFixtures::SECOND_VERIFIED_USER_ID);
        $client->loginUser($entitled);
        $client->request('GET', self::MATRIX_PATH);

        self::assertResponseIsSuccessful();
        $body = self::body($client);

        self::assertStringContainsString(self::OWN_LIVE_TIP, $body, 'The boost reveals the live column too.');
        self::assertStringContainsString(self::OTHERS_FINISHED_TIP, $body);
        self::assertStringNotContainsString(self::ctaHeadline(), $body, 'Nothing is hidden ⇒ no paywall.');
    }

    /**
     * State 3 — the competition's OWNER, who is also a system admin, with no
     * purchase. Managers and admins get no free pass (2026-07-23 decision).
     */
    public function testManagerAndAdminGetNoFreePass(): void
    {
        $client = static::createClient();
        $em = self::entityManager($client);
        $this->seedBoostsWorld($em);

        $owner = self::user($em, AppFixtures::ADMIN_ID);
        self::assertTrue(
            self::competition($em, AppFixtures::BOOSTS_COMPETITION_ID)->owner->id->equals($owner->id),
            'Pre-condition: this viewer owns the competition (and holds ROLE_ADMIN).',
        );

        $client->loginUser($owner);
        $client->request('GET', self::MATRIX_PATH);

        self::assertResponseIsSuccessful();
        $body = self::body($client);

        self::assertStringNotContainsString(self::OTHERS_LIVE_TIP, $body);
        self::assertStringContainsString(self::ctaHeadline(), $body, 'The organizer buys like anybody else.');
    }

    /** State 4 — a signed-in NON-member is refused outright. Unchanged. */
    public function testNonMemberIsRefused(): void
    {
        $client = static::createClient();
        $em = self::entityManager($client);
        $this->seedBoostsWorld($em);

        $client->loginUser($this->createStranger($client));
        $client->request('GET', self::MATRIX_PATH);

        self::assertResponseStatusCodeSame(403);
    }

    /** State 5 — an anonymous visitor is refused. „Každý" means every MEMBER. */
    public function testAnonymousVisitorIsSentToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', self::MATRIX_PATH);

        self::assertResponseRedirects();
        self::assertStringContainsString('/prihlaseni', (string) $client->getResponse()->headers->get('Location'));
    }

    /**
     * B6 — a competition whose every match is settled has nothing left to unlock.
     * In the matrix this falls out of the per-match rule: only finished columns
     * remain (cancelled ones are not rendered at all), so nothing is hidden and no
     * purchase is offered. The page still renders, with the tips readable.
     */
    public function testFullyOverCompetitionRendersWithoutOfferingAPurchase(): void
    {
        $client = static::createClient();
        $em = self::entityManager($client);
        $now = self::now();

        $viewer = self::user($em, AppFixtures::VERIFIED_USER_ID);
        $tipper = self::user($em, AppFixtures::SECOND_VERIFIED_USER_ID);
        $finished = self::match($em, AppFixtures::MATCH_FINISHED_ID);

        $over = new Competition(
            id: Uuid::v7(),
            matchSource: $finished->matchSource,
            owner: $tipper,
            name: 'Dohraná parta',
            description: null,
            pin: null,
            shareableLinkToken: null,
            createdAt: $now,
            selectionMode: CompetitionMatchSelectionMode::Subset,
            monetization: CompetitionMonetization::Boosts,
        );
        $over->popEvents();
        $em->persist($over);

        $em->persist(new CompetitionMatchSelection(
            id: Uuid::v7(),
            competition: $over,
            sportMatch: $finished,
            addedAt: $now,
        ));

        foreach ([$viewer, $tipper] as $member) {
            $membership = new Membership(id: Uuid::v7(), competition: $over, user: $member, joinedAt: $now);
            $membership->popEvents();
            $em->persist($membership);
        }

        $this->persistGuess($em, $tipper, $finished, $over, 5, 4);
        $em->flush();

        $client->loginUser($viewer);
        $client->request('GET', '/zebricek/matice?soutez='.$over->id->toRfc4122());

        self::assertResponseIsSuccessful();
        $body = self::body($client);

        self::assertStringContainsString(self::OTHERS_FINISHED_TIP, $body);
        self::assertStringNotContainsString(self::ctaHeadline(), $body);
        self::assertStringNotContainsString('Odemknout za', $body);
    }

    /**
     * SURFACE 2 — match detail („Pořadí za zápas" / „Jak tipovali ostatní"). The
     * rule is the SHARED one, so a live match's tips are gated here too. This
     * REVERSES item 10, whose reference screenshots showed that table unlocked on a
     * live match because its deadline had passed.
     */
    public function testMatchDetailHidesALiveMatchsTipsFromAnUnentitledMember(): void
    {
        $client = static::createClient();
        $em = self::entityManager($client);
        $viewer = $this->seedBoostsWorld($em);

        $client->loginUser($viewer);
        $crawler = $client->request(
            'GET',
            '/souteze/'.AppFixtures::BOOSTS_COMPETITION_ID.'/zapasy/'.AppFixtures::MATCH_LIVE_ID,
        );

        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString(
            self::OTHERS_LIVE_TIP,
            self::body($client),
            'A live match has no result: its tips must be absent from the markup here too.',
        );
        self::assertCount(0, $crawler->filter('table.lb-table'), 'The ranking table itself must not render.');
        // Match detail composes the same paywall differently since B27: the card,
        // the blurred skeleton and the lock coin are the page's, and `Boost:Panel`
        // renders `shape="bare"` — the gold „Odemknout za N kr." control only. The
        // overlay used to invent its own pitch („Uvidíš konkrétní tipy kolegů");
        // it now names the booster with the SAME canonical label as the matrix.
        self::assertStringContainsString(
            self::ctaHeadline(),
            self::body($client),
            'The locked twin must name the booster it sells.',
        );
        // …and the bare CTA control (this viewer has no credits, so the control links
        // to dobití — but it is the SAME gold `.dist-unlock` „Odemknout za N kr.").
        self::assertGreaterThanOrEqual(
            1,
            $crawler->filter('.dist-card.is-locked .dist-unlock')->count(),
            'The locked twin must carry the bare CTA (Boost:Panel, feature="others" shape="bare").',
        );
    }

    /** The entitlement is the other door, and it still opens on match detail. */
    public function testMatchDetailRevealsALiveMatchsTipsToAnEntitledMember(): void
    {
        $client = static::createClient();
        $em = self::entityManager($client);
        $this->seedBoostsWorld($em);

        $client->loginUser(self::user($em, AppFixtures::SECOND_VERIFIED_USER_ID));
        $client->request(
            'GET',
            '/souteze/'.AppFixtures::BOOSTS_COMPETITION_ID.'/zapasy/'.AppFixtures::MATCH_LIVE_ID,
        );

        self::assertResponseIsSuccessful();
        self::assertStringContainsString(self::OWN_LIVE_TIP, self::body($client));
    }

    /**
     * SURFACE 3 — the competition-scoped match page's „Tipy členů", where the
     * organizer may tip on a member's behalf. Same gate, same answer: with no
     * entitlement they see WHETHER a tip is filled and empty score inputs; once they
     * buy the boost the same page fills them in. Asserted on the inputs' `value`,
     * because that is where a leak would actually sit.
     */
    public function testOnBehalfTipsOfAnUnplayedMatchStayBlindForTheOrganizer(): void
    {
        $client = static::createClient();
        $em = self::entityManager($client);
        $this->seedBoostsWorld($em);
        $path = '/souteze/'.AppFixtures::BOOSTS_COMPETITION_ID.'/zapasy/'.AppFixtures::MATCH_LIVE_ID;
        $owner = self::user($em, AppFixtures::ADMIN_ID);

        $client->loginUser($owner);
        $crawler = $client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertGreaterThanOrEqual(1, $crawler->filter('input[name="homeScore"]')->count());
        self::assertSame(
            [],
            array_values(array_filter(
                $crawler->filter('input[name="homeScore"]')->extract(['value']),
                static fn (mixed $value): bool => '' !== $value,
            )),
            'The organizer (and system admin) reads an unplayed match no earlier than anybody else.',
        );
        self::assertStringNotContainsString(self::OTHERS_LIVE_TIP, self::body($client));

        // Same organizer, now holding the boost they were offered.
        $purchase = new BoostPurchase(
            id: Uuid::v7(),
            user: $owner,
            competition: self::competition($em, AppFixtures::BOOSTS_COMPETITION_ID),
            type: BoostType::OthersTips,
            pricePaid: BoostType::OthersTips->price(),
            purchasedAt: self::now(),
        );
        $purchase->popEvents();
        $em->persist($purchase);
        $em->flush();

        $crawler = $client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertContains(
            '7',
            $crawler->filter('input[name="homeScore"]')->extract(['value']),
            'The entitlement is the other door and it still opens here.',
        );
    }

    /**
     * SURFACE 4 — „Rozložení tipů" (the aggregate). It follows the same rule
     * (`TipVisibilityGate::$distributionRevealsOnResult`, config/services.php), so a
     * live match shows the DECORATIVE skeleton, never the real bars. Item 11's
     * distinction is the assertion: `.dist-fill` is real, `.dist-ghost-fill` is
     * paywall decoration.
     */
    public function testDistributionOfALiveMatchIsAGhostForAnUnentitledMemberAndRealForAnEntitledOne(): void
    {
        $client = static::createClient();
        $em = self::entityManager($client);
        $viewer = $this->seedBoostsWorld($em);
        $path = '/souteze/'.AppFixtures::BOOSTS_COMPETITION_ID.'/zapasy/'.AppFixtures::MATCH_LIVE_ID;

        $client->loginUser($viewer);
        $crawler = $client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('.dist-fill'), 'No real bar for a match without a result.');
        self::assertGreaterThanOrEqual(1, $crawler->filter('.dist-ghost-fill')->count());

        $client->loginUser(self::user($em, AppFixtures::SECOND_VERIFIED_USER_ID));
        $crawler = $client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertGreaterThanOrEqual(1, $crawler->filter('.dist-fill')->count(), 'The boost still unlocks it.');
    }

    /**
     * The correctness argument for dropping the deadline (2026-07-30): a SCHEDULED
     * match whose kickoff has passed — an organizer late to postpone — used to be
     * „past its deadline" and therefore fully readable, for a match that had not
     * been played. It is not readable any more.
     */
    public function testAnUnplayedMatchWhoseKickoffPassedDoesNotRevealTips(): void
    {
        $client = static::createClient();
        $em = self::entityManager($client);
        $viewer = $this->seedBoostsWorld($em);

        // A match that kicked off but was never played and never postponed: still
        // Scheduled, kickoff in the past, no result. (Its own kickoff is its deadline
        // — it enters this all-mode competition after the lock moment.)
        $competition = self::competition($em, AppFixtures::BOOSTS_COMPETITION_ID);
        $finished = self::match($em, AppFixtures::MATCH_FINISHED_ID);
        $stale = new SportMatch(
            id: Uuid::v7(),
            matchSource: $finished->matchSource,
            homeTeam: $finished->homeTeam,
            awayTeam: $finished->awayTeam,
            kickoffAt: new \DateTimeImmutable('2025-06-14 18:00:00 UTC'),
            venue: null,
            createdAt: self::now(),
        );
        $stale->popEvents();
        $em->persist($stale);
        $this->persistGuess($em, self::user($em, AppFixtures::SECOND_VERIFIED_USER_ID), $stale, $competition, 9, 8);
        $em->flush();

        /** @var EffectiveTipDeadlineResolver $deadlines */
        $deadlines = $client->getContainer()->get(EffectiveTipDeadlineResolver::class);
        $deadlines->forgetCompetition($competition->id);
        self::assertLessThan(
            self::now(),
            $deadlines->deadlineFor($competition, $stale),
            'Pre-condition: the stale match is past its tip deadline.',
        );
        self::assertFalse($stale->isFinished, 'Pre-condition: and it has no result.');

        $client->loginUser($viewer);
        $client->request('GET', self::MATRIX_PATH);

        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('9:8', self::body($client));
    }

    /**
     * BOOSTS_COMPETITION (all-mode, over the curated source) + one extra plain
     * member, which the method returns:
     *
     * - ADMIN                 — owner/manager, no purchase;
     * - SECOND_VERIFIED_USER  — member holding the OthersTips boost (entitled),
     *                           tipped both the LIVE and the FINISHED match;
     * - VERIFIED_USER         — plain member, no entitlement, tipped the LIVE match.
     */
    private function seedBoostsWorld(EntityManagerInterface $em): User
    {
        $now = self::now();
        $competition = self::competition($em, AppFixtures::BOOSTS_COMPETITION_ID);
        $live = self::match($em, AppFixtures::MATCH_LIVE_ID);
        $finished = self::match($em, AppFixtures::MATCH_FINISHED_ID);
        $entitled = self::user($em, AppFixtures::SECOND_VERIFIED_USER_ID);
        $plain = self::user($em, AppFixtures::VERIFIED_USER_ID);

        $membership = new Membership(id: Uuid::v7(), competition: $competition, user: $plain, joinedAt: $now);
        $membership->popEvents();
        $em->persist($membership);

        $this->persistGuess($em, $entitled, $live, $competition, 7, 3);
        $this->persistGuess($em, $entitled, $finished, $competition, 5, 4);
        $this->persistGuess($em, $plain, $live, $competition, 6, 2);

        $em->flush();

        return $plain;
    }

    private function persistGuess(
        EntityManagerInterface $em,
        User $user,
        SportMatch $sportMatch,
        Competition $competition,
        int $homeScore,
        int $awayScore,
    ): void {
        $guess = new Guess(
            id: Uuid::v7(),
            user: $user,
            sportMatch: $sportMatch,
            competition: $competition,
            homeScore: $homeScore,
            awayScore: $awayScore,
            submittedAt: self::now(),
        );
        $guess->popEvents();
        $em->persist($guess);
    }

    private function createStranger(KernelBrowser $client): User
    {
        $container = $client->getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $now = self::now();
        $stranger = new User(
            id: Uuid::v7(),
            email: 'matrix-stranger-'.bin2hex(random_bytes(4)).'@tipovacka.test',
            password: null,
            nickname: 'matrix_stranger_'.bin2hex(random_bytes(3)),
            createdAt: $now,
        );
        $stranger->changePassword($hasher->hashPassword($stranger, 'password'), $now);
        $stranger->markAsVerified($now);
        $stranger->popEvents();
        $em->persist($stranger);
        $em->flush();

        return $stranger;
    }

    private static function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
    }

    private static function entityManager(KernelBrowser $client): EntityManagerInterface
    {
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        return $em;
    }

    private static function competition(EntityManagerInterface $em, string $id): Competition
    {
        $competition = $em->find(Competition::class, Uuid::fromString($id));
        self::assertNotNull($competition);

        return $competition;
    }

    private static function match(EntityManagerInterface $em, string $id): SportMatch
    {
        $sportMatch = $em->find(SportMatch::class, Uuid::fromString($id));
        self::assertNotNull($sportMatch);

        return $sportMatch;
    }

    private static function user(EntityManagerInterface $em, string $id): User
    {
        $user = $em->find(User::class, Uuid::fromString($id));
        self::assertNotNull($user);

        return $user;
    }

    private static function body(KernelBrowser $client): string
    {
        $body = $client->getResponse()->getContent();
        self::assertIsString($body);

        return $body;
    }
}
