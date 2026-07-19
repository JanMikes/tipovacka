<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Competition;

use App\Command\AdjustUserCredits\AdjustUserCreditsCommand;
use App\Command\EnablePremium\EnablePremiumCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Enum\CompetitionMonetization;
use App\Tests\Support\WebFlowHelpers;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Portal premium management: enable/switch buttons on the competition detail,
 * the premium settings page, and the switch-to-boosts action (S10 Part A).
 */
final class PremiumFlowTest extends WebTestCase
{
    use WebFlowHelpers;

    private const string PREMIUM_DETAIL = '/portal/souteze/'.AppFixtures::PREMIUM_COMPETITION_ID;
    private const string PREMIUM_SETTINGS = self::PREMIUM_DETAIL.'/premium';
    private const string PREMIUM_SWITCH = self::PREMIUM_DETAIL.'/premium/prepnout-na-prispevky';

    private const string PLAIN_DETAIL = '/portal/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID;
    private const string PLAIN_ENABLE = self::PLAIN_DETAIL.'/premium/zapnout';

    private function reloadCompetition(string $id): Competition
    {
        $em = $this->testEntityManager();
        $em->clear();
        $competition = $em->find(Competition::class, Uuid::fromString($id));
        self::assertInstanceOf(Competition::class, $competition);

        return $competition;
    }

    public function testOwnerSeesPremiumAndSwitchButtonsOnPremiumCompetition(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::ADMIN_ID);

        $crawler = $client->request('GET', self::PREMIUM_DETAIL);
        self::assertResponseIsSuccessful();

        self::assertCount(1, $crawler->filter('a[href="'.self::PREMIUM_SETTINGS.'"]'));

        $switchForm = $crawler->filter('form[action="'.self::PREMIUM_SWITCH.'"]');
        self::assertCount(1, $switchForm);
        self::assertSame('confirm', $switchForm->attr('data-controller'));
        self::assertCount(1, $switchForm->filter('input[name="_token"]'));

        // A premium competition never offers "enable premium".
        self::assertCount(0, $crawler->filter('form[action="'.self::PREMIUM_DETAIL.'/premium/zapnout"]'));
    }

    public function testOwnerSeesEnableButtonOnNonPremiumCompetition(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        $crawler = $client->request('GET', self::PLAIN_DETAIL);
        self::assertResponseIsSuccessful();

        $enableForm = $crawler->filter('form[action="'.self::PLAIN_ENABLE.'"]');
        self::assertCount(1, $enableForm);
        self::assertSame('confirm', $enableForm->attr('data-controller'));

        self::assertCount(0, $crawler->filter('form[action="'.self::PLAIN_DETAIL.'/premium/prepnout-na-prispevky"]'));
    }

    public function testPremiumSettingsPageRendersAndSaves(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::ADMIN_ID);

        $client->request('GET', self::PREMIUM_SETTINGS);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Prémium');

        // Passing a value for a checkbox field ticks it.
        $client->submitForm('Uložit nastavení', [
            'premium_settings_form[showDistribution]' => '1',
            'premium_settings_form[showOthersTips]' => '1',
            'premium_settings_form[allowTipChanges]' => '1',
            'premium_settings_form[tipChangeOffsetMinutes]' => '120',
        ]);

        self::assertResponseRedirects(self::PREMIUM_SETTINGS);

        $competition = $this->reloadCompetition(AppFixtures::PREMIUM_COMPETITION_ID);
        self::assertTrue($competition->premiumShowDistribution);
        self::assertTrue($competition->premiumShowOthersTips);
        self::assertTrue($competition->premiumAllowTipChanges);
        self::assertSame(120, $competition->tipChangeOffsetMinutes);
    }

    public function testPremiumSettingsRedirectsForNonPremiumCompetition(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        $client->request('GET', self::PLAIN_DETAIL.'/premium');

        self::assertResponseRedirects(self::PLAIN_DETAIL);
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Prémiové nastavení je dostupné jen u prémiových soutěží.');
    }

    public function testSwitchToBoostsFlowRefundsAndFlips(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::ADMIN_ID);

        $crawler = $client->request('GET', self::PREMIUM_DETAIL);
        $client->submit($crawler->filter('form[action="'.self::PREMIUM_SWITCH.'"]')->form());

        self::assertResponseRedirects(self::PREMIUM_DETAIL);
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Přepnuto na příspěvky');

        self::assertSame(CompetitionMonetization::Boosts, $this->reloadCompetition(AppFixtures::PREMIUM_COMPETITION_ID)->monetization);
    }

    public function testSwitchToBoostsRejectsInvalidCsrf(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::ADMIN_ID);

        $client->request('POST', self::PREMIUM_SWITCH, ['_token' => 'invalid']);

        self::assertResponseRedirects(self::PREMIUM_DETAIL);
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Neplatný bezpečnostní token.');

        // The switch did not happen.
        self::assertSame(CompetitionMonetization::Premium, $this->reloadCompetition(AppFixtures::PREMIUM_COMPETITION_ID)->monetization);
    }

    public function testEnablePremiumFlowChargesGroupAndEnables(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        // Owner needs credits for the 1 non-owner member (ANONYMOUS) × 10.
        $this->testCommandBus()->dispatch(new AdjustUserCreditsCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            amount: 50,
            note: 'Test dotace',
            adjustedById: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));

        $crawler = $client->request('GET', self::PLAIN_DETAIL);
        $client->submit($crawler->filter('form[action="'.self::PLAIN_ENABLE.'"]')->form());

        self::assertResponseRedirects(self::PLAIN_DETAIL.'/premium');

        self::assertSame(CompetitionMonetization::Premium, $this->reloadCompetition(AppFixtures::VERIFIED_COMPETITION_ID)->monetization);
    }

    public function testEnablePremiumOnAlreadyPremiumShowsFriendlyFlash(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        // Owner opens the enable form on a still-`none` competition (valid token).
        $crawler = $client->request('GET', self::PLAIN_DETAIL);
        $form = $crawler->filter('form[action="'.self::PLAIN_ENABLE.'"]')->form();

        // Meanwhile the competition becomes premium out-of-band (a double-submit /
        // another tab already enabled it). ANONYMOUS is the sole non-owner member.
        $this->testCommandBus()->dispatch(new AdjustUserCreditsCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            amount: 50,
            note: 'Test dotace',
            adjustedById: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));
        $this->testCommandBus()->dispatch(new EnablePremiumCommand(
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
        ));

        // Submitting the now-stale form must not double-charge — friendly flash.
        $client->submit($form);

        self::assertResponseRedirects(self::PLAIN_DETAIL.'/premium');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Soutěž už je prémiová.');

        self::assertSame(CompetitionMonetization::Premium, $this->reloadCompetition(AppFixtures::VERIFIED_COMPETITION_ID)->monetization);
    }

    public function testEnablePremiumInsufficientCreditsRedirectsToTopUp(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        // No credits granted ⇒ the group charge cannot be covered.
        $crawler = $client->request('GET', self::PLAIN_DETAIL);
        $client->submit($crawler->filter('form[action="'.self::PLAIN_ENABLE.'"]')->form());

        self::assertResponseRedirects('/portal/kredity');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Nedostatek kreditů');

        self::assertSame(CompetitionMonetization::None, $this->reloadCompetition(AppFixtures::VERIFIED_COMPETITION_ID)->monetization);
    }
}
