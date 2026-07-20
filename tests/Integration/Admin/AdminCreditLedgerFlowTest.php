<?php

declare(strict_types=1);

namespace App\Tests\Integration\Admin;

use App\Command\AdjustUserCredits\AdjustUserCreditsCommand;
use App\Command\JoinGlobalCompetition\JoinGlobalCompetitionCommand;
use App\DataFixtures\AppFixtures;
use App\Tests\Support\WebFlowHelpers;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Admin-wide credit ledger view (Kredity → Transakce): the S03/S10 transaction
 * types across every wallet, filterable by type and competition.
 */
final class AdminCreditLedgerFlowTest extends WebTestCase
{
    use WebFlowHelpers;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /** Grants VERIFIED_USER credits (admin_adjustment) then makes them pay the 50-credit global entry fee. */
    private function seedAdjustmentAndEntryFee(): void
    {
        $this->testCommandBus()->dispatch(new AdjustUserCreditsCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            amount: 100,
            note: 'Dotace pro test',
            adjustedById: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));
        $this->testCommandBus()->dispatch(new JoinGlobalCompetitionCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::GLOBAL_COMPETITION_ID),
        ));
    }

    public function testLedgerShowsAllTransactionTypesAcrossWallets(): void
    {
        $this->seedAdjustmentAndEntryFee();

        $this->loginUserById($this->client, AppFixtures::ADMIN_ID);
        $this->client->request('GET', '/admin/kredity/transakce');

        self::assertResponseIsSuccessful();
        // Both the top-up and the burned entry fee show, named by type + wallet owner.
        self::assertAnySelectorTextContains('td', 'Úprava administrátorem');
        self::assertAnySelectorTextContains('td', 'Vstupné do soutěže');
        self::assertAnySelectorTextContains('td', AppFixtures::GLOBAL_COMPETITION_NAME);
        // The tab bar links both credit views.
        self::assertSelectorExists('a[href="/admin/kredity"]');
        self::assertSelectorExists('a[href="/admin/kredity/transakce"]');
    }

    public function testLedgerFiltersByType(): void
    {
        $this->seedAdjustmentAndEntryFee();

        $this->loginUserById($this->client, AppFixtures::ADMIN_ID);
        $this->client->request('GET', '/admin/kredity/transakce?type=entry_fee');

        self::assertResponseIsSuccessful();
        self::assertAnySelectorTextContains('td', 'Vstupné do soutěže');
        // The adjustment label appears only as a filter-dropdown option, never as a table row.
        self::assertSelectorTextNotContains('table', 'Úprava administrátorem');
    }

    public function testLedgerFiltersByCompetition(): void
    {
        $this->seedAdjustmentAndEntryFee();

        $this->loginUserById($this->client, AppFixtures::ADMIN_ID);
        // The admin_adjustment has no competition ⇒ scoping to the global competition hides it.
        $this->client->request('GET', '/admin/kredity/transakce?competition='.AppFixtures::GLOBAL_COMPETITION_ID);

        self::assertResponseIsSuccessful();
        self::assertAnySelectorTextContains('td', 'Vstupné do soutěže');
        self::assertSelectorTextNotContains('table', 'Úprava administrátorem');
    }

    public function testNonAdminIsForbidden(): void
    {
        $this->loginUserById($this->client, AppFixtures::VERIFIED_USER_ID);
        $this->client->request('GET', '/admin/kredity/transakce');

        self::assertResponseStatusCodeSame(403);
    }
}
