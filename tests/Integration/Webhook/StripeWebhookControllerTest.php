<?php

declare(strict_types=1);

namespace App\Tests\Integration\Webhook;

use App\Command\InitiateCreditPurchase\InitiateCreditPurchaseCommand;
use App\Command\InitiateCreditPurchase\InitiatedCreditCheckout;
use App\DataFixtures\AppFixtures;
use App\Entity\CreditPurchase;
use App\Entity\CreditWallet;
use App\Enum\CreditPurchaseStatus;
use App\Tests\Support\WebFlowHelpers;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Uid\Uuid;

/**
 * Uses the REAL StripeWebhookParser — payloads are signed with
 * STRIPE_WEBHOOK_SECRET from .env.test using Stripe's signature scheme.
 * Only the payment gateway (API calls) is faked.
 */
final class StripeWebhookControllerTest extends WebTestCase
{
    use WebFlowHelpers;

    private const string WEBHOOK_SECRET = 'whsec_test_secret';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    private function initiatePurchase(int $credits): CreditPurchase
    {
        $envelope = $this->testCommandBus()->dispatch(new InitiateCreditPurchaseCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            credits: $credits,
            successUrl: 'https://wtips.test/navrat?session_id={CHECKOUT_SESSION_ID}',
            cancelUrl: 'https://wtips.test/navrat?cancelled=1',
        ));

        $result = $envelope->last(HandledStamp::class)?->getResult();
        self::assertInstanceOf(InitiatedCreditCheckout::class, $result);

        return $result->purchase;
    }

    /**
     * @param array<string, mixed> $dataObject
     */
    private function eventPayload(string $type, array $dataObject): string
    {
        return json_encode([
            'id' => 'evt_test_'.bin2hex(random_bytes(6)),
            'object' => 'event',
            'type' => $type,
            'data' => ['object' => $dataObject],
        ], JSON_THROW_ON_ERROR);
    }

    private function sign(string $payload, string $secret = self::WEBHOOK_SECRET): string
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return sprintf('t=%d,v1=%s', $timestamp, $signature);
    }

    private function postWebhook(string $payload, ?string $signatureHeader): void
    {
        $server = ['CONTENT_TYPE' => 'application/json'];

        if (null !== $signatureHeader) {
            $server['HTTP_STRIPE_SIGNATURE'] = $signatureHeader;
        }

        $this->client->request('POST', '/webhooks/stripe', server: $server, content: $payload);
    }

    private function walletBalance(): int
    {
        $wallet = $this->testEntityManager()->createQueryBuilder()
            ->select('w')
            ->from(CreditWallet::class, 'w')
            ->where('w.user = :userId')
            ->setParameter('userId', Uuid::fromString(AppFixtures::VERIFIED_USER_ID))
            ->getQuery()
            ->getOneOrNullResult();

        return $wallet->balance ?? 0;
    }

    private function reloadPurchase(CreditPurchase $purchase): CreditPurchase
    {
        $em = $this->testEntityManager();
        $em->clear();
        $reloaded = $em->find(CreditPurchase::class, $purchase->id);
        self::assertInstanceOf(CreditPurchase::class, $reloaded);

        return $reloaded;
    }

    public function testCompletedEventCreditsWallet(): void
    {
        $purchase = $this->initiatePurchase(250);
        $this->paymentGateway()->primePaidSession($purchase->stripeCheckoutSessionId, amountTotal: 25000, invoiceId: 'in_test_wh');

        $payload = $this->eventPayload('checkout.session.completed', [
            'id' => $purchase->stripeCheckoutSessionId,
            'object' => 'checkout.session',
        ]);

        $this->postWebhook($payload, $this->sign($payload));

        self::assertResponseIsSuccessful();
        self::assertSame(250, $this->walletBalance());

        $reloaded = $this->reloadPurchase($purchase);
        self::assertSame(CreditPurchaseStatus::Completed, $reloaded->status);
        self::assertSame('in_test_wh', $reloaded->stripeInvoiceId);
    }

    public function testInvalidSignatureIsRejected(): void
    {
        $purchase = $this->initiatePurchase(250);
        $this->paymentGateway()->primePaidSession($purchase->stripeCheckoutSessionId, amountTotal: 25000);

        $payload = $this->eventPayload('checkout.session.completed', [
            'id' => $purchase->stripeCheckoutSessionId,
            'object' => 'checkout.session',
        ]);

        $this->postWebhook($payload, $this->sign($payload, 'whsec_wrong_secret'));

        self::assertResponseStatusCodeSame(400);
        self::assertSame(0, $this->walletBalance());
    }

    public function testMissingSignatureIsRejected(): void
    {
        $payload = $this->eventPayload('checkout.session.completed', [
            'id' => 'cs_test_any',
            'object' => 'checkout.session',
        ]);

        $this->postWebhook($payload, null);

        self::assertResponseStatusCodeSame(400);
    }

    public function testTamperedPayloadIsRejected(): void
    {
        $payload = $this->eventPayload('checkout.session.completed', [
            'id' => 'cs_test_original',
            'object' => 'checkout.session',
        ]);
        $signature = $this->sign($payload);
        $tampered = str_replace('cs_test_original', 'cs_test_tampered', $payload);

        $this->postWebhook($tampered, $signature);

        self::assertResponseStatusCodeSame(400);
    }

    public function testExpiredEventMarksPurchaseExpired(): void
    {
        $purchase = $this->initiatePurchase(100);

        $payload = $this->eventPayload('checkout.session.expired', [
            'id' => $purchase->stripeCheckoutSessionId,
            'object' => 'checkout.session',
        ]);

        $this->postWebhook($payload, $this->sign($payload));

        self::assertResponseIsSuccessful();
        self::assertSame(CreditPurchaseStatus::Expired, $this->reloadPurchase($purchase)->status);
    }

    public function testAsyncPaymentFailedMarksPurchaseFailed(): void
    {
        $purchase = $this->initiatePurchase(100);

        $payload = $this->eventPayload('checkout.session.async_payment_failed', [
            'id' => $purchase->stripeCheckoutSessionId,
            'object' => 'checkout.session',
        ]);

        $this->postWebhook($payload, $this->sign($payload));

        self::assertResponseIsSuccessful();
        self::assertSame(CreditPurchaseStatus::Failed, $this->reloadPurchase($purchase)->status);
        self::assertSame(0, $this->walletBalance());
    }

    public function testUnhandledEventTypeIsAcknowledged(): void
    {
        $payload = $this->eventPayload('customer.created', [
            'id' => 'cus_test_1',
            'object' => 'customer',
        ]);

        $this->postWebhook($payload, $this->sign($payload));

        self::assertResponseIsSuccessful();
    }
}
