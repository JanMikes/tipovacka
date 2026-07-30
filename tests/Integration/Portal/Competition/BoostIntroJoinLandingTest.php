<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Competition;

use App\DataFixtures\AppFixtures;
use App\Entity\Membership;
use App\Entity\User;
use App\Enum\InvitationKind;
use App\Tests\Support\WebFlowHelpers;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

/**
 * Item 28 — the boost-price modal does not greet a player in the same breath as the join.
 *
 * Every redirect that is the direct result of a join carries `?pripojeno=1`, and the
 * competition page treats that as „not this time". The property that makes this safe, and
 * the thing these tests exist to protect, is that **a suppressed render consumes nothing**:
 * `Membership.boostIntroSeenAt` is stamped only by `DismissBoostIntroController`, so the
 * modal simply waits for the next ordinary visit. Assert the stamp, not only the markup —
 * a regression that stamped on render would still look right on the join landing and would
 * silently cost every new member the intro forever.
 *
 * „Příspěvková firemní liga" (`BOOSTS_COMPETITION`) is the fixture used throughout: it is
 * `monetization = boosts` (Premium XOR boosts — nothing else puts the modal in play), it
 * has a shareable link token, and `VERIFIED_USER` is deliberately not a member of it.
 */
final class BoostIntroJoinLandingTest extends WebTestCase
{
    use InteractsWithLiveComponents;
    use WebFlowHelpers;

    private const string DETAIL = '/souteze/'.AppFixtures::BOOSTS_COMPETITION_ID;
    private const string JUST_JOINED = self::DETAIL.'?pripojeno=1';
    private const string LINK_URL = '/souteze/pozvanka/'.AppFixtures::BOOSTS_COMPETITION_LINK_TOKEN;
    private const string DIALOG = 'dialog[data-boost-intro-target="dialog"]';

    /**
     * The reported flow, end to end: invitation link → register → verify → land on the
     * soutěž. The join is completed by `LoginSubscriber` on the login the verification
     * performs, which is the redirect that has to carry the parameter.
     */
    public function testTheRegisterThenVerifyFunnelLandsOnTheCompetitionWithoutTheIntro(): void
    {
        $client = static::createClient();
        $client->request('GET', self::LINK_URL);
        self::assertResponseIsSuccessful();

        $user = $this->registerThroughLanding($client, 'boost-intro-funnel@example.test', 'boost_intro_funnel');
        $this->clickVerificationLink($client, $user);

        self::assertResponseRedirects(self::JUST_JOINED);

        $landing = $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertCount(0, $landing->filter(self::DIALOG), 'No upsell in the same breath as the welcome.');

        // …and the visit that was skipped cost the player nothing.
        self::assertNull($this->membershipOf($user->id)->boostIntroSeenAt);

        $second = $client->request('GET', self::DETAIL);
        self::assertResponseIsSuccessful();
        self::assertCount(1, $second->filter(self::DIALOG), 'The modal is owed on the next ordinary visit.');
    }

    /**
     * Signing IN through an invitation joins inline (`InvitationAcceptanceService`), a
     * different redirect from the one above — it needs the parameter just as much.
     */
    public function testJoiningWhileAlreadySignedInAlsoLandsWithoutTheIntro(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        $client->request('GET', self::LINK_URL);
        self::assertResponseRedirects(self::JUST_JOINED);

        $landing = $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertCount(0, $landing->filter(self::DIALOG));
    }

    /**
     * **The point of the whole item.** The suppressed visit must not consume the first
     * visit — walked the way a player does it: join, look around, go to the Nástěnka,
     * come back.
     */
    public function testTheSuppressedVisitDoesNotConsumeTheFirstVisit(): void
    {
        $client = static::createClient();
        $user = $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        $client->request('GET', self::LINK_URL);
        $landing = $client->followRedirect();
        self::assertCount(0, $landing->filter(self::DIALOG));
        self::assertNull(
            $this->membershipOf($user->id)->boostIntroSeenAt,
            'Rendering — or not rendering — the intro never writes to the membership.',
        );

        $client->request('GET', '/nastenka');
        self::assertResponseIsSuccessful();

        $back = $client->request('GET', self::DETAIL);
        self::assertResponseIsSuccessful();
        self::assertCount(1, $back->filter(self::DIALOG));
        // Item 26 put a second <dialog> („Pravidla") on this page, so every
        // assertion here names the boost-intro one explicitly.
        self::assertSelectorTextContains(self::DIALOG, 'Co si můžete v téhle soutěži odemknout');
        self::assertNull($this->membershipOf($user->id)->boostIntroSeenAt);
    }

    /**
     * And the intro that finally showed up still behaves: one dismissal, stamped, gone.
     */
    public function testDismissingItOnTheSecondVisitStillStampsAndEndsIt(): void
    {
        $client = static::createClient();
        $user = $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        $client->request('GET', self::LINK_URL);
        $client->followRedirect();

        $crawler = $client->request('GET', self::DETAIL);
        $token = $crawler->filter(self::DIALOG.' form input[name="_token"]')->attr('value');
        self::assertIsString($token);

        $client->request('POST', self::DETAIL.'/vylepseni/uvod/skryt', ['_token' => $token]);
        self::assertNotNull($this->membershipOf($user->id)->boostIntroSeenAt);

        $after = $client->request('GET', self::DETAIL);
        self::assertCount(0, $after->filter(self::DIALOG));
    }

    /**
     * The parameter is advisory and unsigned on purpose: forging or sharing it skips one
     * render of a promotional modal, which then returns. Anything more (a token, a session
     * check) would be more machinery than the thing it protects.
     */
    public function testTheParameterCostsOneRenderEvenWhenNobodyJustJoined(): void
    {
        $client = static::createClient();
        $user = $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $forged = $client->request('GET', self::JUST_JOINED);
        self::assertResponseIsSuccessful();
        self::assertCount(0, $forged->filter(self::DIALOG));
        self::assertNull($this->membershipOf($user->id)->boostIntroSeenAt);

        $ordinary = $client->request('GET', self::DETAIL);
        self::assertCount(1, $ordinary->filter(self::DIALOG), 'One render skipped, not one intro spent.');
    }

    /**
     * Criterion 4: nothing changes for a member arriving by an ordinary route.
     */
    public function testAnOrdinaryArrivalStillSeesItOnTheFirstVisit(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $crawler = $client->request('GET', self::DETAIL);
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter(self::DIALOG));
    }

    private function registerThroughLanding(KernelBrowser $client, string $email, string $nickname): User
    {
        $component = $this->createLiveComponent('Auth:InvitationForm', [
            'kind' => InvitationKind::ShareableLink->value,
            'token' => AppFixtures::BOOSTS_COMPETITION_LINK_TOKEN,
        ], $client);

        $response = $component->submitForm([
            'invitation_form' => [
                'email' => $email,
                'password' => 'Str0ngP4ssword!',
                'passwordConfirm' => 'Str0ngP4ssword!',
                'nickname' => $nickname,
                'firstName' => 'Jan',
                'lastName' => 'Novák',
                'gdprConsent' => '1',
            ],
        ], 'submit')->response();

        self::assertSame('/overeni-ceka', $response->headers->get('Location'));

        $em = $this->testEntityManager();
        $em->clear();

        $user = $em->createQueryBuilder()
            ->select('u')->from(User::class, 'u')
            ->where('u.email = :e')->setParameter('e', $email)
            ->getQuery()->getOneOrNullResult();

        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    private function clickVerificationLink(KernelBrowser $client, User $user): void
    {
        /** @var VerifyEmailHelperInterface $helper */
        $helper = static::getContainer()->get(VerifyEmailHelperInterface::class);
        \assert(null !== $user->email);

        $client->request('GET', $helper->generateSignature(
            'app_verify_email',
            $user->id->toRfc4122(),
            $user->email,
            ['id' => $user->id->toRfc4122()],
        )->getSignedUrl());
    }

    private function membershipOf(Uuid $userId): Membership
    {
        $em = $this->testEntityManager();
        $em->clear();

        $membership = $em->createQueryBuilder()
            ->select('m')->from(Membership::class, 'm')
            ->where('m.user = :u')->andWhere('m.competition = :c')
            ->setParameter('u', $userId)
            ->setParameter('c', Uuid::fromString(AppFixtures::BOOSTS_COMPETITION_ID))
            ->getQuery()->getOneOrNullResult();

        self::assertInstanceOf(Membership::class, $membership);

        return $membership;
    }
}
