<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Competition;

use App\Command\AdjustUserCredits\AdjustUserCreditsCommand;
use App\Command\PurchaseBoost\PurchaseBoostCommand;
use App\Command\SubmitGuess\SubmitGuessCommand;
use App\DataFixtures\AppFixtures;
use App\Enum\BoostType;
use App\Tests\Support\WebFlowHelpers;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * „Tipovat za členy" must not become a peephole: managing a member's tip tells the
 * organizer only WHETHER it is filled (and lets them overwrite it), never the
 * scores — unless they hold the same entitlement anyone else would need. See the
 * 2026-07-23 decision in .docs/DOMAIN.md.
 */
final class OnBehalfTipPrivacyTest extends WebTestCase
{
    use WebFlowHelpers;

    private const string BOOSTS_DETAIL = '/portal/souteze/'.AppFixtures::BOOSTS_COMPETITION_ID;
    private const string MANAGE_TIPS = self::BOOSTS_DETAIL.'/spravovat-tipy?member='.AppFixtures::SECOND_VERIFIED_USER_ID;
    private const string COMPETITION_MATCH = self::BOOSTS_DETAIL.'/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID;

    private function seedMemberTip(): void
    {
        // SECOND_VERIFIED_USER is the fixture member of the boosts competition.
        $this->testCommandBus()->dispatch(new SubmitGuessCommand(
            userId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            homeScore: 4,
            awayScore: 3,
        ));
    }

    public function testManageScreenShowsFilledStateWithoutTheScores(): void
    {
        $client = static::createClient();
        $this->seedMemberTip();
        $this->loginUserById($client, AppFixtures::ADMIN_ID);

        $crawler = $client->request('GET', self::MANAGE_TIPS);
        self::assertResponseIsSuccessful();

        $matchKey = AppFixtures::MATCH_SCHEDULED_ID;
        $home = $crawler->filter('input[name="guesses['.$matchKey.'][homeScore]"]');
        $away = $crawler->filter('input[name="guesses['.$matchKey.'][awayScore]"]');

        self::assertCount(1, $home, 'The member’s open match should be manageable.');
        self::assertSame('', $home->attr('value') ?? '', 'The member’s score must not be pre-filled.');
        self::assertSame('', $away->attr('value') ?? '');

        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Vyplněno', $body, 'The manager must still see that a tip exists.');
        self::assertStringNotContainsString('value="4"', $body, 'The member’s actual score leaked.');
    }

    public function testManageScreenRevealsScoresOnceTheManagerIsEntitled(): void
    {
        $client = static::createClient();
        $this->seedMemberTip();
        // The organizer buys the same boost a member would need.
        $this->testCommandBus()->dispatch(new AdjustUserCreditsCommand(
            userId: Uuid::fromString(AppFixtures::ADMIN_ID),
            amount: 100,
            note: 'Test dotace',
            adjustedById: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));
        $this->testCommandBus()->dispatch(new PurchaseBoostCommand(
            userId: Uuid::fromString(AppFixtures::ADMIN_ID),
            competitionId: Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID),
            type: BoostType::OthersTips,
        ));
        $this->loginUserById($client, AppFixtures::ADMIN_ID);

        $crawler = $client->request('GET', self::MANAGE_TIPS);
        self::assertResponseIsSuccessful();

        $matchKey = AppFixtures::MATCH_SCHEDULED_ID;
        self::assertSame('4', $crawler->filter('input[name="guesses['.$matchKey.'][homeScore]"]')->attr('value'));
        self::assertSame('3', $crawler->filter('input[name="guesses['.$matchKey.'][awayScore]"]')->attr('value'));
    }

    public function testMatchPageMemberRowsHideScoresButKeepTheFilledPill(): void
    {
        $client = static::createClient();
        $this->seedMemberTip();
        $this->loginUserById($client, AppFixtures::ADMIN_ID);

        $client->request('GET', self::COMPETITION_MATCH);
        self::assertResponseIsSuccessful();

        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Tipy členů', $body);
        self::assertStringContainsString('Vyplněno', $body);
        self::assertStringNotContainsString('value="4"', $body, 'The member’s actual score leaked.');
    }
}
