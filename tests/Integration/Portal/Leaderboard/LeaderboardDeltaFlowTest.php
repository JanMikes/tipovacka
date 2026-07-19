<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Leaderboard;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\Guess;
use App\Entity\GuessEvaluation;
use App\Entity\GuessEvaluationRulePoints;
use App\Entity\Membership;
use App\Entity\SportMatch;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * S12 leaderboard-Δ UI surfaces. VERIFIED_COMPETITION has no finished matches, so
 * its live board is all-zeros; the seeded snapshots mirror that (0 points) — the
 * screen is self-consistent. VERIFIED_USER (owner) sits in the 2025-06-14 baseline
 * ⇒ „beze změny"; ANONYMOUS_USER joined today ⇒ absent from the baseline ⇒ „nový".
 */
final class LeaderboardDeltaFlowTest extends WebTestCase
{
    private const string DELTA_HEADER = 'th[title="Změna pořadí oproti předchozímu dennímu snímku"]';

    public function testLeaderboardRendersDeltaColumnCoherentlyWithSeededHistory(): void
    {
        $client = static::createClient();
        $this->loginVerified($client);

        $client->request('GET', '/portal/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID.'/zebricek');

        self::assertResponseIsSuccessful();
        // The Δ column renders under the all-time tab, with the corrected tooltip.
        self::assertSelectorExists(self::DELTA_HEADER);
        // ANONYMOUS_USER joined today ⇒ absent from the 2025-06-14 baseline ⇒ „nový".
        self::assertSelectorExists('.lb-delta-new');

        $body = (string) $client->getResponse()->getContent();
        // VERIFIED_USER was already rank 1 in the baseline ⇒ „Beze změny od minula".
        self::assertStringContainsString('Beze změny od minula', $body);
        // The factually-wrong „od včera" copy is retired everywhere on the page.
        self::assertStringNotContainsString('od včera', $body);
        self::assertStringNotContainsString('včerejšku', $body);
    }

    /**
     * Fix: under a windowed (non-Celkem) tab the „Tvoje pozice" strip must follow
     * the re-ranked table, never keep showing the all-time rank — otherwise one
     * screen shows two contradictory ranks for the same user.
     */
    public function testWindowedTabYouStripRankMatchesTableAndHidesDelta(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        // PUBLIC_COMPETITION: ADMIN holds the fixture evaluation (3 pts on
        // MATCH_FINISHED, kickoff 2025-06-10 — inside the last-7-days window). Add
        // VERIFIED_USER with points ONLY outside that window: 10 pts all-time
        // (rank 1) but 0 in the last 7 days (rank 2, behind ADMIN's 3). All-time
        // and windowed ranks therefore genuinely disagree.
        $competition = $em->find(Competition::class, Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID));
        self::assertNotNull($competition);
        $verified = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($verified);

        $membership = new Membership(id: Uuid::v7(), competition: $competition, user: $verified, joinedAt: $now);
        $membership->popEvents();
        $em->persist($membership);

        $oldMatch = new SportMatch(
            id: Uuid::v7(),
            matchSource: $competition->matchSource,
            homeTeam: 'Staré A',
            awayTeam: 'Staré B',
            kickoffAt: new \DateTimeImmutable('2025-06-01 18:00:00', new \DateTimeZone('UTC')),
            venue: null,
            createdAt: $now,
        );
        $oldMatch->popEvents();
        $em->persist($oldMatch);

        $guess = new Guess(
            id: Uuid::v7(),
            user: $verified,
            sportMatch: $oldMatch,
            competition: $competition,
            homeScore: 1,
            awayScore: 0,
            submittedAt: $now,
        );
        $guess->popEvents();
        $em->persist($guess);

        $evaluation = new GuessEvaluation(id: Uuid::v7(), guess: $guess, evaluatedAt: $now);
        $evaluation->addRulePoints(new GuessEvaluationRulePoints(
            id: Uuid::v7(),
            evaluation: $evaluation,
            ruleIdentifier: 'exact_score',
            points: 10,
        ));
        $em->persist($evaluation);
        $em->flush();

        $client->loginUser($verified);

        // All-time tab: VERIFIED leads (10 pts) ⇒ strip AND table both show rank 1.
        $crawler = $client->request('GET', '/portal/souteze/'.AppFixtures::PUBLIC_COMPETITION_ID.'/zebricek');
        self::assertResponseIsSuccessful();
        self::assertStringStartsWith('1.', trim($crawler->filter('.you-strip .pos')->text()));
        self::assertSame('1.', trim($crawler->filter('tr.lb-tr.me .lb-pos')->text()));

        // 7-day tab: VERIFIED has no in-window points ⇒ the table re-ranks them 2nd
        // behind ADMIN. The strip must show the SAME rank, not the all-time 1st.
        $crawler = $client->request('GET', '/portal/souteze/'.AppFixtures::PUBLIC_COMPETITION_ID.'/zebricek?obdobi=7dni');
        self::assertResponseIsSuccessful();

        $tableRank = trim($crawler->filter('tr.lb-tr.me .lb-pos')->text());
        self::assertSame('2.', $tableRank, 'Windowed table re-ranks VERIFIED to 2nd.');
        self::assertStringStartsWith(
            $tableRank,
            trim($crawler->filter('.you-strip .pos')->text()),
            'The you-strip rank must match the windowed table, never the all-time rank.',
        );
        // Δ is all-time only ⇒ the column (and any „od minula" movement) is hidden.
        self::assertSelectorNotExists(self::DELTA_HEADER);
        self::assertStringNotContainsString('od minula', (string) $client->getResponse()->getContent());
    }

    public function testTimeFilterTabsRender(): void
    {
        $client = static::createClient();
        $this->loginVerified($client);

        $client->request('GET', '/portal/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID.'/zebricek');
        $body = (string) $client->getResponse()->getContent();

        // Only the two implemented windows are offered as tabs.
        self::assertStringContainsString('Celkem', $body);
        self::assertStringContainsString('Posledních 7 dní', $body);
    }

    public function testDashboardMiniLeaderboardRendersDeltaChip(): void
    {
        $client = static::createClient();
        $this->loginVerified($client);

        $client->request('GET', '/nastenka');

        self::assertResponseIsSuccessful();
        // The mini-board carries the Δ chip; ANONYMOUS_USER is „nový" (absent from
        // the previous snapshot day) ⇒ the „nový" chip variant renders.
        self::assertSelectorExists('.lb-delta-chip.new');
    }

    public function testMemberBreakdownRendersProgressList(): void
    {
        $client = static::createClient();
        $this->loginVerified($client);

        $client->request(
            'GET',
            '/portal/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID.'/zebricek/clen/'.AppFixtures::VERIFIED_USER_ID,
        );

        self::assertResponseIsSuccessful();
        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Vývoj', $body);
        // Both seeded snapshot days appear (2025-06-14 baseline and 2025-06-15).
        self::assertStringContainsString('14. 6. 2025', $body);
        self::assertStringContainsString('15. 6. 2025', $body);
    }

    private function loginVerified(KernelBrowser $client): void
    {
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $verified = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($verified);
        $client->loginUser($verified);
    }
}
