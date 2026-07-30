<?php

declare(strict_types=1);

namespace App\Tests\Integration\Invitation;

use App\DataFixtures\AppFixtures;
use App\Entity\Membership;
use App\Entity\User;
use App\Enum\InvitationKind;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

/**
 * B15 — the whole invite → sign-up → verify funnel, end to end.
 *
 * The unit-level pieces of this were all green while the funnel was broken, because the
 * defect lived in the gap between two requests: the join intent was kept in the PHP
 * session, and the verification click arrives from a mailbox — a different browser, a
 * phone, or simply after the browser was closed and the session cookie dropped. Hence
 * these tests walk requests, and one of them deliberately throws the cookie jar away
 * before clicking the verification link.
 */
final class InviteFunnelJourneyTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    private const string LINK_URL = '/souteze/pozvanka/'.AppFixtures::VERIFIED_COMPETITION_LINK_TOKEN;

    public function testLinkSignUpLandsInTheCompetitionAfterVerification(): void
    {
        $client = static::createClient();

        $client->request('GET', self::LINK_URL);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Chystáš se připojit do soutěže');
        self::assertSelectorTextContains('body', AppFixtures::VERIFIED_COMPETITION_NAME);

        $user = $this->registerThroughLanding($client, InvitationKind::ShareableLink, AppFixtures::VERIFIED_COMPETITION_LINK_TOKEN, 'funnel-link@example.test', 'funnel_link');

        $this->clickVerificationLink($client, $user);

        self::assertResponseRedirects('/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID);
        self::assertTrue($this->isMember($client, $user->id, AppFixtures::VERIFIED_COMPETITION_ID));
    }

    /**
     * The regression that would have caught B15: the mail is opened somewhere else, so
     * the session that recorded the intent is simply not there any more.
     */
    public function testLinkSignUpStillJoinsWhenTheMailIsOpenedInAnotherBrowser(): void
    {
        $client = static::createClient();
        $client->request('GET', self::LINK_URL);

        $user = $this->registerThroughLanding($client, InvitationKind::ShareableLink, AppFixtures::VERIFIED_COMPETITION_LINK_TOKEN, 'funnel-crossbrowser@example.test', 'funnel_cross');

        // Another device / a closed browser: no session cookie travels with the click.
        $client->getCookieJar()->clear();

        $this->clickVerificationLink($client, $user);

        self::assertResponseRedirects('/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID);
        self::assertTrue(
            $this->isMember($client, $user->id, AppFixtures::VERIFIED_COMPETITION_ID),
            'The join must not depend on the sign-up session surviving the mail round trip.',
        );
    }

    public function testAnonymousPinEntryNamesTheCompetitionAndJoinsAfterSignUp(): void
    {
        $client = static::createClient();

        $client->request('GET', '/pripojit');
        self::assertResponseIsSuccessful();

        $client->submitForm('Pokračovat', ['join_by_pin_form[pin]' => AppFixtures::VERIFIED_COMPETITION_PIN]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Chystáš se připojit do soutěže');
        self::assertSelectorTextContains('body', AppFixtures::VERIFIED_COMPETITION_NAME);

        $user = $this->registerThroughLanding($client, InvitationKind::Pin, AppFixtures::VERIFIED_COMPETITION_PIN, 'funnel-pin@example.test', 'funnel_pin');

        $this->clickVerificationLink($client, $user);

        self::assertResponseRedirects('/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID);
        self::assertTrue($this->isMember($client, $user->id, AppFixtures::VERIFIED_COMPETITION_ID));
    }

    /**
     * The 8-box PIN bar lives on public pages, so an anonymous visitor may use it — and
     * lands on the „you are about to join X" page rather than on a login wall.
     */
    public function testAnonymousQuickPinBarRemembersTheCompetition(): void
    {
        $client = static::createClient();
        $client->request('GET', '/souteze');

        $client->submitForm('Připojit se', ['pin' => AppFixtures::VERIFIED_COMPETITION_PIN]);
        self::assertResponseRedirects('/pripojit');

        $client->followRedirect();
        self::assertSelectorTextContains('body', AppFixtures::VERIFIED_COMPETITION_NAME);
    }

    /**
     * Signing IN (rather than up) from an invitation landing must end in the competition
     * too — that journey never needed verification, so it must not be made to wait.
     */
    public function testLinkSignInLandsInTheCompetition(): void
    {
        $client = static::createClient();
        $client->request('GET', self::LINK_URL);

        $component = $this->createLiveComponent('Auth:InvitationForm', [
            'kind' => InvitationKind::ShareableLink->value,
            'token' => AppFixtures::VERIFIED_COMPETITION_LINK_TOKEN,
        ], $client);

        $response = $component->submitForm([
            'invitation_form' => [
                'email' => AppFixtures::ADMIN_EMAIL,
                'password' => AppFixtures::DEFAULT_PASSWORD,
            ],
        ], 'submit')->response();

        self::assertSame('/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID, $response->headers->get('Location'));
        self::assertTrue($this->isMember($client, Uuid::fromString(AppFixtures::ADMIN_ID), AppFixtures::VERIFIED_COMPETITION_ID));
    }

    /**
     * A shareable link or a PIN proves nothing, so an unverified account may READ the
     * landing but never join through it — it is sent to the airlock with the intent kept.
     */
    public function testUnverifiedAccountCannotJoinByPinAndIsSentToTheAirlock(): void
    {
        $client = static::createClient();
        $em = $this->em($client);
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID));
        self::assertInstanceOf(User::class, $user);
        $client->loginUser($user);

        $client->request('GET', '/pripojit');
        self::assertResponseIsSuccessful('An unverified account may still type a PIN.');

        $client->submitForm('Pokračovat', ['join_by_pin_form[pin]' => AppFixtures::VERIFIED_COMPETITION_PIN]);
        self::assertResponseRedirects('/overeni-ceka');

        $em->clear();
        self::assertFalse(
            $this->isMember($client, $user->id, AppFixtures::VERIFIED_COMPETITION_ID),
            'An unverified account must not become a member.',
        );

        $reloaded = $em->find(User::class, Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID));
        self::assertInstanceOf(User::class, $reloaded);
        self::assertSame(InvitationKind::Pin, $reloaded->pendingJoinKind);
        self::assertSame(AppFixtures::VERIFIED_COMPETITION_PIN, $reloaded->pendingJoinToken);
    }

    private function registerThroughLanding(
        KernelBrowser $client,
        InvitationKind $kind,
        string $token,
        string $email,
        string $nickname,
    ): User {
        $component = $this->createLiveComponent('Auth:InvitationForm', [
            'kind' => $kind->value,
            'token' => $token,
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

        $em = $this->em($client);
        $em->clear();
        $user = $em->createQueryBuilder()
            ->select('u')->from(User::class, 'u')
            ->where('u.email = :e')->setParameter('e', $email)
            ->getQuery()->getOneOrNullResult();

        self::assertInstanceOf(User::class, $user);
        self::assertFalse($user->isVerified, 'A link/PIN sign-up must still verify its e-mail.');
        self::assertSame($kind, $user->pendingJoinKind, 'The join must be recorded on the account, not only in the session.');
        self::assertSame($token, $user->pendingJoinToken);

        return $user;
    }

    private function clickVerificationLink(KernelBrowser $client, User $user): void
    {
        /** @var VerifyEmailHelperInterface $helper */
        $helper = $client->getContainer()->get(VerifyEmailHelperInterface::class);
        \assert(null !== $user->email);

        $signed = $helper->generateSignature(
            'app_verify_email',
            $user->id->toRfc4122(),
            $user->email,
            ['id' => $user->id->toRfc4122()],
        )->getSignedUrl();

        $client->request('GET', $signed);
    }

    private function isMember(KernelBrowser $client, Uuid $userId, string $competitionId): bool
    {
        $em = $this->em($client);
        $em->clear();

        return [] !== $em->createQueryBuilder()
            ->select('m')->from(Membership::class, 'm')
            ->where('m.user = :u')->andWhere('m.competition = :c')
            ->setParameter('u', $userId)
            ->setParameter('c', Uuid::fromString($competitionId))
            ->getQuery()->getResult();
    }

    private function em(KernelBrowser $client): EntityManagerInterface
    {
        /* @var EntityManagerInterface */
        return $client->getContainer()->get('doctrine.orm.entity_manager');
    }
}
