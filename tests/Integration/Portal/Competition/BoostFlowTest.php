<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Competition;

use App\Command\AdjustUserCredits\AdjustUserCreditsCommand;
use App\Command\JoinCompetitionByLink\JoinCompetitionByLinkCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\BoostPurchase;
use App\Enum\BoostType;
use App\Tests\Support\WebFlowHelpers;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Portal boost commerce: the „Tvoje vylepšení" sidebar, buying from the paywall,
 * the premium pill on premium competitions, and the insufficient-credits top-up
 * link. See .docs/DOMAIN.md §Monetization.
 */
final class BoostFlowTest extends WebTestCase
{
    use WebFlowHelpers;

    private const string BOOSTS_DETAIL = '/portal/souteze/'.AppFixtures::BOOSTS_COMPETITION_ID;
    private const string BOOSTS_PURCHASE = self::BOOSTS_DETAIL.'/vylepseni/koupit';
    private const string PREMIUM_MATCH = '/portal/souteze/'.AppFixtures::PREMIUM_COMPETITION_ID.'/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID;

    private function grant(string $userId, int $amount): void
    {
        $this->testCommandBus()->dispatch(new AdjustUserCreditsCommand(
            userId: Uuid::fromString($userId),
            amount: $amount,
            note: 'Test dotace',
            adjustedById: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));
    }

    private function activeTipChange(string $userId): ?BoostPurchase
    {
        $em = $this->testEntityManager();
        $em->clear();

        return $em->createQueryBuilder()
            ->select('b')
            ->from(BoostPurchase::class, 'b')
            ->where('b.user = :user')
            ->andWhere('b.competition = :competition')
            ->andWhere('b.type = :type')
            ->andWhere('b.refundedAt IS NULL')
            ->setParameter('user', Uuid::fromString($userId))
            ->setParameter('competition', Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID))
            ->setParameter('type', BoostType::TipChange)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function testMemberSeesBoostSidebarWithOwnedAndBuyStates(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);
        $this->grant(AppFixtures::SECOND_VERIFIED_USER_ID, 100);

        $crawler = $client->request('GET', self::BOOSTS_DETAIL);
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('body', 'Tvoje vylepšení');
        // SECOND_VERIFIED_USER owns OthersTips (fixture) → shown as active, not buyable.
        self::assertSelectorTextContains('body', 'Konkrétní tipy kolegů');

        // The tip_change boost is buyable (affordable) → a purchase form is present.
        $forms = $crawler->filter('form[action="'.self::BOOSTS_PURCHASE.'"]');
        self::assertGreaterThanOrEqual(1, $forms->count());
        self::assertCount(1, $crawler->filter('form[action="'.self::BOOSTS_PURCHASE.'"] input[value="tip_change"]'));
    }

    public function testBuyBoostFromSidebarWritesRowAndRedirects(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);
        $this->grant(AppFixtures::SECOND_VERIFIED_USER_ID, 100);

        $crawler = $client->request('GET', self::BOOSTS_DETAIL);
        $form = $crawler->filter('form[action="'.self::BOOSTS_PURCHASE.'"] input[value="tip_change"]')->ancestors()->filter('form')->form();
        $client->submit($form);

        self::assertResponseRedirects(self::BOOSTS_DETAIL);
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'je aktivní');

        self::assertInstanceOf(BoostPurchase::class, $this->activeTipChange(AppFixtures::SECOND_VERIFIED_USER_ID));
    }

    public function testPremiumCompetitionGuessPageShowsPremiumPillNotBuyForm(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $crawler = $client->request('GET', self::PREMIUM_MATCH);
        self::assertResponseIsSuccessful();

        // Premium (toggles off) → the paywall becomes a „Prémium" note, never a boost buy form.
        self::assertSelectorTextContains('body', 'Prémium');
        self::assertCount(0, $crawler->filter('form[action*="/vylepseni/koupit"]'));
    }

    public function testNonEntitledMemberSeesInlinePaywallOnGuessPage(): void
    {
        // VERIFIED_USER joins the boosts competition (no boost) and views a
        // pre-deadline match — the distribution + others paywalls render with buy
        // CTAs (the inline Boost:Panel branch).
        $client = static::createClient();
        $this->testCommandBus()->dispatch(new JoinCompetitionByLinkCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            token: AppFixtures::BOOSTS_COMPETITION_LINK_TOKEN,
        ));
        $this->grant(AppFixtures::VERIFIED_USER_ID, 100);
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        $guessPage = self::BOOSTS_DETAIL.'/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID;
        $crawler = $client->request('GET', $guessPage);
        self::assertResponseIsSuccessful();

        // Locked distribution + others paywalls each offer a boost purchase form.
        self::assertGreaterThanOrEqual(1, $crawler->filter('form[action="'.self::BOOSTS_PURCHASE.'"] input[value="tip_distribution"]')->count());
        self::assertGreaterThanOrEqual(1, $crawler->filter('form[action="'.self::BOOSTS_PURCHASE.'"] input[value="others_tips"]')->count());
        self::assertSelectorTextContains('body', 'Odemknout');
    }

    public function testBrokeMemberSeesTopUpLinkInsteadOfBuyForm(): void
    {
        $client = static::createClient();
        // SECOND_VERIFIED_USER is a BOOSTS member with 0 balance and no boosts.
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $crawler = $client->request('GET', self::BOOSTS_DETAIL);
        self::assertResponseIsSuccessful();

        // Cannot afford anything ⇒ no purchase form, but a top-up link is offered.
        self::assertCount(0, $crawler->filter('form[action="'.self::BOOSTS_PURCHASE.'"]'));
        self::assertGreaterThanOrEqual(1, $crawler->filter('a[href="/portal/kredity"]')->count());
    }
}
