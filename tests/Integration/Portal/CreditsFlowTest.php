<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal;

use App\Command\AdjustUserCredits\AdjustUserCreditsCommand;
use App\Command\InitiateCreditPurchase\InitiateCreditPurchaseCommand;
use App\Command\InitiateCreditPurchase\InitiatedCreditCheckout;
use App\DataFixtures\AppFixtures;
use App\Entity\CreditPurchase;
use App\Enum\BoostType;
use App\Tests\Support\WebFlowHelpers;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Uid\Uuid;

final class CreditsFlowTest extends WebTestCase
{
    use WebFlowHelpers;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testCreditsPageRequiresLogin(): void
    {
        $this->client->request('GET', '/kredity');

        self::assertResponseRedirects();
        self::assertStringContainsString('/prihlaseni', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testCreditsPageShowsBalanceAndHistory(): void
    {
        $this->testCommandBus()->dispatch(new AdjustUserCreditsCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            amount: 350,
            note: 'Vítejte ve Wtips',
            adjustedById: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));

        $this->loginUserById($this->client, AppFixtures::VERIFIED_USER_ID);
        $this->client->request('GET', '/kredity');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Kredity');
        self::assertAnySelectorTextContains('.stat-val', '350');
        self::assertAnySelectorTextContains('td', 'Úprava administrátorem');
        self::assertAnySelectorTextContains('td', 'Vítejte ve Wtips');
    }

    public function testBuyRedirectsToStripeCheckout(): void
    {
        $this->loginUserById($this->client, AppFixtures::VERIFIED_USER_ID);

        $this->client->request('POST', '/kredity/koupit', [
            'buy_credits_form' => ['credits' => '250'],
        ]);

        self::assertResponseStatusCodeSame(303);
        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertStringStartsWith('https://checkout.stripe.test/pay/cs_test_', $location);

        $purchase = $this->testEntityManager()->createQueryBuilder()
            ->select('p')
            ->from(CreditPurchase::class, 'p')
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(CreditPurchase::class, $purchase);
        self::assertSame(250, $purchase->credits);
        self::assertTrue($purchase->isPending);
    }

    public function testBuyBelowMinimumIsRejected(): void
    {
        $this->loginUserById($this->client, AppFixtures::VERIFIED_USER_ID);

        $this->client->request('POST', '/kredity/koupit', [
            'buy_credits_form' => ['credits' => '50'],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'Minimální nákup je 100 kreditů (100 Kč) — zadejte prosím 100 nebo víc.');
        // The rejected field is marked for the form-feedback controller to reveal.
        self::assertSelectorExists('input[name="buy_credits_form[credits]"][aria-invalid="true"]');

        $purchaseCount = (int) $this->testEntityManager()->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(CreditPurchase::class, 'p')
            ->getQuery()
            ->getSingleScalarResult();

        self::assertSame(0, $purchaseCount);
    }

    public function testBuyWithANonNumberSaysWhatToType(): void
    {
        $this->loginUserById($this->client, AppFixtures::VERIFIED_USER_ID);

        $this->client->request('POST', '/kredity/koupit', [
            'buy_credits_form' => ['credits' => 'sto'],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'Zadejte prosím celé číslo bez mezer a desetinné čárky, např. 250.');
    }

    /**
     * Stripe down (or misconfigured) used to answer the buyer with a 500 page — not
     * knowing whether money moved. Now the same page comes back, 422, with the reason in
     * the form's summary box; nothing is written.
     */
    public function testCheckoutThatCannotBeOpenedStaysOnThePageAndSaysNothingWasCharged(): void
    {
        $this->loginUserById($this->client, AppFixtures::VERIFIED_USER_ID);
        $this->paymentGateway()->nextCheckoutFailure = new \RuntimeException('Stripe API unreachable');

        $this->client->request('POST', '/kredity/koupit', [
            'buy_credits_form' => ['credits' => '250'],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#dobit [role="alert"]', 'Platbu se nepodařilo spustit');
        self::assertSelectorTextContains('#dobit [role="alert"]', 'Nic jsme vám nestrhli');

        $purchaseCount = (int) $this->testEntityManager()->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(CreditPurchase::class, 'p')
            ->getQuery()
            ->getSingleScalarResult();

        self::assertSame(0, $purchaseCount);
    }

    public function testBuyFormStatesTheLimitsUpFront(): void
    {
        $this->loginUserById($this->client, AppFixtures::VERIFIED_USER_ID);
        $crawler = $this->client->request('GET', '/kredity');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#dobit', 'Celé číslo od 100 do 100 000');
        $input = $crawler->filter('input[name="buy_credits_form[credits]"]');
        self::assertSame('numeric', $input->attr('inputmode'));
        // A fresh form shouts at nobody.
        self::assertSelectorNotExists('#dobit [role="alert"]');
    }

    /**
     * A booster the wallet cannot cover routes its „Odemknout za N kr." here; the dobití
     * card says which booster, how much is missing, and what to do.
     */
    public function testArrivingFromAnUnaffordableBoosterNamesTheShortfall(): void
    {
        $this->loginUserById($this->client, AppFixtures::VERIFIED_USER_ID);
        $this->client->request('GET', '/kredity?vylepseni='.BoostType::OthersTips->value);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#dobit', BoostType::OthersTips->label());
        self::assertSelectorTextContains('#dobit', sprintf('vám chybí %d kreditů', BoostType::OthersTips->price()));
    }

    public function testNoShortfallNoticeWhenTheWalletCoversTheBooster(): void
    {
        $this->testCommandBus()->dispatch(new AdjustUserCreditsCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            amount: 500,
            note: 'Testovací dobití',
            adjustedById: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));

        $this->loginUserById($this->client, AppFixtures::VERIFIED_USER_ID);
        $this->client->request('GET', '/kredity?vylepseni='.BoostType::OthersTips->value);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('#dobit', 'vám chybí');
    }

    public function testCancelledReturnShowsInfo(): void
    {
        $this->loginUserById($this->client, AppFixtures::VERIFIED_USER_ID);

        $this->client->request('GET', '/kredity/navrat?cancelled=1');

        self::assertResponseRedirects('/kredity');
        $this->client->followRedirect();
        self::assertAnySelectorTextContains('body', 'Platba byla zrušena');
    }

    public function testSuccessfulReturnFulfillsPurchase(): void
    {
        $envelope = $this->testCommandBus()->dispatch(new InitiateCreditPurchaseCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            credits: 500,
            successUrl: 'https://wtips.test/navrat?session_id={CHECKOUT_SESSION_ID}',
            cancelUrl: 'https://wtips.test/navrat?cancelled=1',
        ));
        $checkout = $envelope->last(HandledStamp::class)?->getResult();
        self::assertInstanceOf(InitiatedCreditCheckout::class, $checkout);

        $this->paymentGateway()->primePaidSession(
            $checkout->purchase->stripeCheckoutSessionId,
            amountTotal: 50000,
            invoiceId: 'in_test_return',
        );

        $this->loginUserById($this->client, AppFixtures::VERIFIED_USER_ID);
        $this->client->request('GET', '/kredity/navrat?session_id='.$checkout->purchase->stripeCheckoutSessionId);

        self::assertResponseRedirects('/kredity');
        $this->client->followRedirect();
        self::assertAnySelectorTextContains('body', '500 kreditů bylo připsáno');
        self::assertAnySelectorTextContains('.stat-val', '500');
    }
}
