<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Competition;

use App\Command\AdjustUserCredits\AdjustUserCreditsCommand;
use App\Command\FulfillCreditPurchase\FulfillCreditPurchaseCommand;
use App\Command\InitiateCreditPurchase\InitiateCreditPurchaseCommand;
use App\Command\InitiateCreditPurchase\InitiatedCreditCheckout;
use App\DataFixtures\AppFixtures;
use App\Entity\Membership;
use App\Tests\Support\WebFlowHelpers;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Uid\Uuid;

final class JoinGlobalCompetitionFlowTest extends WebTestCase
{
    use WebFlowHelpers;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testFreeJoinRedirectsToDetailAndCreatesMembership(): void
    {
        $this->loginUserById($this->client, AppFixtures::SECOND_VERIFIED_USER_ID);
        $this->postJoin(AppFixtures::FREE_GLOBAL_COMPETITION_ID);

        self::assertResponseRedirects('/portal/souteze/'.AppFixtures::FREE_GLOBAL_COMPETITION_ID);
        self::assertTrue($this->isMember(AppFixtures::SECOND_VERIFIED_USER_ID, AppFixtures::FREE_GLOBAL_COMPETITION_ID));
    }

    public function testPaidJoinWithSufficientCreditsSucceeds(): void
    {
        $this->testCommandBus()->dispatch(new AdjustUserCreditsCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            amount: 100,
            note: 'test',
            adjustedById: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));

        $this->loginUserById($this->client, AppFixtures::VERIFIED_USER_ID);
        $this->postJoin(AppFixtures::GLOBAL_COMPETITION_ID);

        self::assertResponseRedirects('/portal/souteze/'.AppFixtures::GLOBAL_COMPETITION_ID);
        self::assertTrue($this->isMember(AppFixtures::VERIFIED_USER_ID, AppFixtures::GLOBAL_COMPETITION_ID));
    }

    public function testInsufficientCreditsRedirectsToCreditsWithFlash(): void
    {
        $this->loginUserById($this->client, AppFixtures::SECOND_VERIFIED_USER_ID);
        $this->postJoin(AppFixtures::GLOBAL_COMPETITION_ID);

        self::assertResponseRedirects('/portal/kredity');
        $this->client->followRedirect();
        self::assertAnySelectorTextContains('body', 'Na vstupné potřebujete ještě 50 kreditů.');

        // No membership created for the failed paid join.
        self::assertFalse($this->isMember(AppFixtures::SECOND_VERIFIED_USER_ID, AppFixtures::GLOBAL_COMPETITION_ID));
    }

    public function testTopUpReturnRedirectsBackToCompetitionWithoutAutoJoin(): void
    {
        $this->loginUserById($this->client, AppFixtures::SECOND_VERIFIED_USER_ID);

        // Insufficient join stores the return-to-competition intent in the session.
        $this->postJoin(AppFixtures::GLOBAL_COMPETITION_ID);
        self::assertResponseRedirects('/portal/kredity');

        // Complete a credit purchase out-of-band (prime + fulfill back-to-back on
        // the command bus, so the fake gateway's in-memory session is not reset
        // between requests). The return request then only needs the already
        // completed purchase + the session intent.
        $envelope = $this->testCommandBus()->dispatch(new InitiateCreditPurchaseCommand(
            userId: Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID),
            credits: 100,
            successUrl: 'https://wtips.test/navrat?session_id={CHECKOUT_SESSION_ID}',
            cancelUrl: 'https://wtips.test/navrat?cancelled=1',
        ));
        $checkout = $envelope->last(HandledStamp::class)?->getResult();
        self::assertInstanceOf(InitiatedCreditCheckout::class, $checkout);

        $this->paymentGateway()->primePaidSession(
            $checkout->purchase->stripeCheckoutSessionId,
            amountTotal: 10000,
            invoiceId: 'in_test_global_join',
        );
        $this->testCommandBus()->dispatch(new FulfillCreditPurchaseCommand(
            $checkout->purchase->stripeCheckoutSessionId,
        ));

        // Return from Stripe: completed purchase + stored intent send the user
        // BACK to the public discovery list, anchored on the competition (NOT the
        // detail page — its VIEW voter would 403 a not-yet-member).
        $this->client->request('GET', '/portal/kredity/navrat?session_id='.$checkout->purchase->stripeCheckoutSessionId);

        self::assertResponseRedirects('/souteze#soutez-'.AppFixtures::GLOBAL_COMPETITION_ID);

        // The landing must actually render (no 403 dead-end) and show the
        // competition so the user can click „Připojit se" again.
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertAnySelectorTextContains('body', AppFixtures::GLOBAL_COMPETITION_NAME);

        // Intent only redirects back — it does NOT auto-join.
        self::assertFalse($this->isMember(AppFixtures::SECOND_VERIFIED_USER_ID, AppFixtures::GLOBAL_COMPETITION_ID));
    }

    private function postJoin(string $competitionId): void
    {
        // We prime the session with a known join CSRF token rather than scraping
        // the form off /souteze: the public list intentionally hides the join form
        // for a viewer who cannot afford the fee (they see the „dokoupit" state),
        // so the two insufficient-credits scenarios have no form to scrape. This
        // is exactly what the rendered form's token would resolve to server-side.
        $this->client->request('GET', '/souteze');
        $session = $this->client->getRequest()->getSession();
        $session->set('_csrf/competition_join_global_'.$competitionId, 'test-csrf');
        $session->save();

        $this->client->request('POST', '/portal/souteze/'.$competitionId.'/pripojit-se', [
            '_token' => 'test-csrf',
        ]);
    }

    private function isMember(string $userId, string $competitionId): bool
    {
        $this->testEntityManager()->clear();

        return null !== $this->testEntityManager()->createQueryBuilder()
            ->select('m')
            ->from(Membership::class, 'm')
            ->where('m.user = :userId')
            ->andWhere('m.competition = :competitionId')
            ->andWhere('m.leftAt IS NULL')
            ->setParameter('userId', Uuid::fromString($userId))
            ->setParameter('competitionId', Uuid::fromString($competitionId))
            ->getQuery()
            ->getOneOrNullResult();
    }
}
