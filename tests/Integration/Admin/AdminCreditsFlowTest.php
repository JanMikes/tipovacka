<?php

declare(strict_types=1);

namespace App\Tests\Integration\Admin;

use App\Command\FulfillCreditPurchase\FulfillCreditPurchaseCommand;
use App\Command\InitiateCreditPurchase\InitiateCreditPurchaseCommand;
use App\Command\InitiateCreditPurchase\InitiatedCreditCheckout;
use App\DataFixtures\AppFixtures;
use App\Entity\CreditTransaction;
use App\Tests\Support\WebFlowHelpers;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Uid\Uuid;

final class AdminCreditsFlowTest extends WebTestCase
{
    use WebFlowHelpers;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testAdminCanAdjustCreditsWithNote(): void
    {
        $this->loginUserById($this->client, AppFixtures::ADMIN_ID);
        $url = '/admin/uzivatele/'.AppFixtures::VERIFIED_USER_ID.'/kredity';

        $this->client->request('POST', $url, [
            'adjust_credits_form' => ['amount' => '300', 'note' => 'Bonus za aktivitu'],
        ]);

        self::assertResponseRedirects($url);
        $this->client->followRedirect();

        self::assertAnySelectorTextContains('.stat-val', '300');
        self::assertAnySelectorTextContains('td', 'Bonus za aktivitu');

        $transaction = $this->testEntityManager()->createQueryBuilder()
            ->select('t')
            ->from(CreditTransaction::class, 't')
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(CreditTransaction::class, $transaction);
        self::assertSame(300, $transaction->amount);
        self::assertSame('Bonus za aktivitu', $transaction->note);
        self::assertSame(AppFixtures::ADMIN_ID, $transaction->performedBy?->id->toRfc4122());
    }

    public function testAdjustmentBelowZeroShowsFormError(): void
    {
        $this->loginUserById($this->client, AppFixtures::ADMIN_ID);

        $this->client->request('POST', '/admin/uzivatele/'.AppFixtures::VERIFIED_USER_ID.'/kredity', [
            'adjust_credits_form' => ['amount' => '-50', 'note' => 'Korekce'],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertAnySelectorTextContains('form', 'Nedostatek kreditů');
        // …and how far the admin CAN go.
        self::assertAnySelectorTextContains('form', 'Odeberte nejvýše');
        self::assertSelectorExists('#adjust_credits_form_amount[aria-invalid="true"]');
    }

    public function testZeroAdjustmentSaysHowToFixIt(): void
    {
        $this->loginUserById($this->client, AppFixtures::ADMIN_ID);

        $this->client->request('POST', '/admin/uzivatele/'.AppFixtures::VERIFIED_USER_ID.'/kredity', [
            'adjust_credits_form' => ['amount' => '0', 'note' => 'Korekce'],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertAnySelectorTextContains('form', 'Úprava o 0 kreditů nic nezmění');
        self::assertSelectorExists('#adjust_credits_form_amount[aria-invalid="true"]');
    }

    public function testFractionalAmountAsksForAWholeNumber(): void
    {
        $this->loginUserById($this->client, AppFixtures::ADMIN_ID);

        $this->client->request('POST', '/admin/uzivatele/'.AppFixtures::VERIFIED_USER_ID.'/kredity', [
            'adjust_credits_form' => ['amount' => '12.5', 'note' => 'Korekce'],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertAnySelectorTextContains('form', 'Zadejte celé číslo bez desetinných míst');
    }

    public function testAdjustmentAsksForConfirmationAndStatesItsLimits(): void
    {
        $this->loginUserById($this->client, AppFixtures::ADMIN_ID);

        $this->client->request('GET', '/admin/uzivatele/'.AppFixtures::VERIFIED_USER_ID.'/kredity');

        self::assertResponseIsSuccessful();
        // Money-moving: the confirm modal sits in front of the submit, next to form-feedback.
        self::assertSelectorExists('form[data-controller~="confirm"][data-controller~="form-feedback"]');
        self::assertSelectorExists('#adjust_credits_form_amount[min="-100000"][max="100000"][step="1"]');
        self::assertAnySelectorTextContains('form', 'zůstatek nesmí klesnout pod nulu');
    }

    public function testNoteIsRequired(): void
    {
        $this->loginUserById($this->client, AppFixtures::ADMIN_ID);

        $this->client->request('POST', '/admin/uzivatele/'.AppFixtures::VERIFIED_USER_ID.'/kredity', [
            'adjust_credits_form' => ['amount' => '100', 'note' => ''],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertAnySelectorTextContains('form', 'Napište důvod úpravy');
    }

    public function testNonAdminCannotAdjustCredits(): void
    {
        $this->loginUserById($this->client, AppFixtures::VERIFIED_USER_ID);

        $this->client->request('GET', '/admin/uzivatele/'.AppFixtures::SECOND_VERIFIED_USER_ID.'/kredity');

        self::assertResponseStatusCodeSame(403);
    }

    public function testPurchasesListShowsCompletedPurchase(): void
    {
        $envelope = $this->testCommandBus()->dispatch(new InitiateCreditPurchaseCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            credits: 250,
            successUrl: 'https://wtips.test/navrat?session_id={CHECKOUT_SESSION_ID}',
            cancelUrl: 'https://wtips.test/navrat?cancelled=1',
        ));
        $checkout = $envelope->last(HandledStamp::class)?->getResult();
        self::assertInstanceOf(InitiatedCreditCheckout::class, $checkout);

        $this->paymentGateway()->primePaidSession(
            $checkout->purchase->stripeCheckoutSessionId,
            amountTotal: 25000,
            paymentIntentId: 'pi_test_admin_list',
            invoiceId: 'in_test_admin_list',
        );
        $this->testCommandBus()->dispatch(new FulfillCreditPurchaseCommand($checkout->purchase->stripeCheckoutSessionId));

        $this->loginUserById($this->client, AppFixtures::ADMIN_ID);
        $this->client->request('GET', '/admin/kredity');

        self::assertResponseIsSuccessful();
        self::assertAnySelectorTextContains('td', AppFixtures::VERIFIED_USER_EMAIL);
        self::assertAnySelectorTextContains('td', '250');
        self::assertAnySelectorTextContains('td', 'Zaplaceno');
        self::assertSelectorExists('a[href*="pi_test_admin_list"]');
    }
}
