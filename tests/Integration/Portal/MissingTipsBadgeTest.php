<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal;

use App\Command\SubmitGuess\SubmitGuessCommand;
use App\DataFixtures\AppFixtures;
use App\Tests\Support\WebFlowHelpers;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Uid\Uuid;

/**
 * Item 30 — the red „Chybí N tipů" badge, on BOTH card surfaces: the Nástěnka's
 * „Moje soutěže" grid and /souteze's „Soutěže, kde tipuješ". Item 36 shortened
 * the copy from „Chybí natipovat N zápasů" to „Chybí N tipů" — same rule, same
 * count, only the label got shorter.
 *
 * The point of testing them together is criterion 7: one soutěž must show one
 * number. Two implementations would drift, and this test is what would catch it.
 */
final class MissingTipsBadgeTest extends WebTestCase
{
    use WebFlowHelpers;

    /** Criterion 1 — the badge is red (`pill-danger`) and declines „tip" correctly. */
    public function testTheBadgeRendersWithTheCzechPlural(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        $client->request('GET', '/nastenka');

        self::assertResponseIsSuccessful();
        $body = (string) $client->getResponse()->getContent();

        // „Kámoši u piva" has exactly one scheduled, still tippable match.
        self::assertStringContainsString('pill pill-danger', $body);
        self::assertStringContainsString('Chybí 1 tip', $body);
        self::assertStringNotContainsString('Chybí 1 tipy', $body);
    }

    /** Criterion 2 — nothing outstanding renders no badge, not a „0 tipů" one. */
    public function testNoBadgeWhenNothingIsOutstanding(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        $this->testCommandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
            homeScore: 2,
            awayScore: 1,
        ));

        foreach (['/nastenka', '/souteze'] as $path) {
            self::assertSame([], $this->badges($client, $path), $path);
            self::assertStringNotContainsString('0 tipů', (string) $client->getResponse()->getContent(), $path);
        }
    }

    /**
     * Criterion 3 — the live and the finished curated match are never tipped here,
     * yet once the two still-open ones are, both pages drop the badge. A locked
     * match is „Netipováno" (B5), never a red nag.
     */
    public function testNoBadgeOnceEveryStillTippableMatchIsTipped(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        self::assertSame(
            ['Chybí 2 tipy', 'Chybí 2 tipy', 'Chybí 1 tip'],
            $this->badges($client, '/nastenka'),
        );

        foreach ([AppFixtures::MATCH_SCHEDULED_ID, AppFixtures::MATCH_PLAYOFF_ID] as $matchId) {
            foreach ([AppFixtures::PREMIUM_COMPETITION_ID, AppFixtures::BOOSTS_COMPETITION_ID] as $competitionId) {
                $this->testCommandBus()->dispatch(new SubmitGuessCommand(
                    userId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
                    competitionId: Uuid::fromString($competitionId),
                    sportMatchId: Uuid::fromString($matchId),
                    homeScore: 1,
                    awayScore: 0,
                ));
            }
        }

        // „Vybrané zápasy party" (subset) still has its one open match untipped.
        self::assertSame(['Chybí 1 tip'], $this->badges($client, '/nastenka'));
        self::assertSame(['Chybí 1 tip'], $this->badges($client, '/souteze'));
    }

    /** Criterion 7 — the same soutěž shows the same number on both pages. */
    public function testBothSurfacesAgreeOnTheNumber(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        self::assertSame(
            $this->badges($client, '/nastenka'),
            $this->badges($client, '/souteze'),
        );
    }

    /**
     * Criterion 6 — /souteze's card is one clickable target: „Tipuj N →" is gone
     * and the only link inside the card is the stretched one.
     */
    public function testThePlayingCardIsOneClickableTargetWithNoTipujLink(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $crawler = $client->request('GET', '/souteze');

        self::assertResponseIsSuccessful();

        $card = $crawler->filter('#hraju-'.AppFixtures::PREMIUM_COMPETITION_ID);
        self::assertCount(1, $card);

        // ONE link, and it is the stretched overlay — no „Tipuj N →", no linked title.
        self::assertCount(1, $card->filter('a'));
        self::assertSame('card-stretch', $card->filter('a')->attr('class'));
        self::assertSame(
            '/souteze/'.AppFixtures::PREMIUM_COMPETITION_ID,
            $card->filter('a')->attr('href'),
        );
        self::assertStringNotContainsString('/moje-tipy', $card->html());

        // The footer keeps only „Tipuj do" + the date — never „· N zápasů" beside it.
        self::assertStringContainsString('Tipuj do', $card->html());
        self::assertDoesNotMatchRegularExpression('/·\s*\d+\s*zápas/u', $card->html());
    }

    /**
     * The soutěž cards' badges, in DOM order. Deliberately narrowed to the item 30
     * label (a digit right after „Chybí "): `.pill-danger` is also item 25's
     * „Chybí tip" (no number in between) on the match rows, which is a different
     * surface answering a different question.
     *
     * @return list<string>
     */
    private function badges(KernelBrowser $client, string $path): array
    {
        $crawler = $client->request('GET', $path);

        self::assertResponseIsSuccessful();

        return array_values(array_filter(
            $crawler->filter('.pill-danger')->each(
                static fn (Crawler $node): string => trim($node->text()),
            ),
            static fn (string $label): bool => 1 === preg_match('/^Chybí \d/u', $label),
        ));
    }
}
