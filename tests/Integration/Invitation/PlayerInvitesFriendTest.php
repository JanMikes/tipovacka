<?php

declare(strict_types=1);

namespace App\Tests\Integration\Invitation;

use App\Command\AdjustUserCredits\AdjustUserCreditsCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\CompetitionInvitation;
use App\Entity\Membership;
use App\Tests\Support\WebFlowHelpers;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;
use Symfony\Component\Uid\Uuid;

/**
 * „Jako hráč chci pozvat kamaráda" — inviting stopped being an organizer privilege.
 *
 * The two halves that must not drift apart:
 *
 *   • a PRIVATE soutěž — a member's invitation is the organizer's invitation: a seat is
 *     held for the invitee so somebody can tip on their behalf, and it costs nobody
 *     anything. Bounded by the organizer's switch: with the PIN and the link both revoked
 *     the doors are shut and a member's e-mail must not reopen them;
 *   • a GLOBAL soutěž — the seat costs money, so no seat is held. The invitation carries
 *     the public invitation page and the invitee pays the entry fee on arrival, exactly as
 *     if they had found the competition on the public list.
 *
 * The assertion most worth keeping honest is therefore the negative one: inviting into a
 * paid competition must create NO membership and NO invitation row.
 */
final class PlayerInvitesFriendTest extends WebTestCase
{
    use WebFlowHelpers;

    private const string BOOSTS_ID = AppFixtures::BOOSTS_COMPETITION_ID;
    private const string GLOBAL_ID = AppFixtures::GLOBAL_COMPETITION_ID;

    // ── A private soutěž: a member invites, and a seat is held ──────────────

    public function testPlainMemberSendsAnInvitationAndLandsBackOnTheCompetition(): void
    {
        $client = static::createClient();
        // SECOND_VERIFIED_USER is a plain, non-owner member of the boosts competition.
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $crawler = $client->request('GET', '/souteze/'.self::BOOSTS_ID);
        self::assertResponseIsSuccessful();

        $client->submit($crawler->selectButton('Odeslat')->form([
            'send_invitation_form[email]' => 'kamarad@example.com',
        ]));

        // Back to the competition, NOT to „Nastavení" — a plain member cannot go there.
        self::assertResponseRedirects('/souteze/'.self::BOOSTS_ID);
        // Asserted before following the redirect: the mailer collector only ever holds the
        // LAST request's messages, and the redirected GET sends none.
        self::assertNotEmpty(self::getMailerMessages());

        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Pozvánka byla odeslána');

        $invitation = $this->invitationFor(self::BOOSTS_ID, 'kamarad@example.com');
        self::assertInstanceOf(CompetitionInvitation::class, $invitation);
        self::assertSame(AppFixtures::SECOND_VERIFIED_USER_ID, $invitation->inviter->id->toRfc4122());

        // The seat is held up front, which is the whole point of the private path.
        self::assertSame(1, $this->membershipCount(self::BOOSTS_ID, $invitation->email));
    }

    /**
     * The organizer's switch still wins. Revoking the PIN and the shareable link is how a
     * competition is closed to newcomers; a member's e-mail is not a way around it.
     */
    public function testMemberCannotInviteOnceEveryWayInIsRevoked(): void
    {
        $client = static::createClient();
        $this->closeTheDoors(self::BOOSTS_ID);
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $crawler = $client->request('GET', '/souteze/'.self::BOOSTS_ID);
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('form[action="/souteze/'.self::BOOSTS_ID.'/pozvanky/odeslat"]'));

        $client->request('POST', '/souteze/'.self::BOOSTS_ID.'/pozvanky/odeslat', [
            'send_invitation_form' => ['email' => 'kamarad@example.com'],
        ]);
        self::assertResponseStatusCodeSame(403);

        // The owner is unaffected — they hold the switch.
        $this->loginUserById($client, AppFixtures::ADMIN_ID);
        $client->request('GET', '/souteze/'.self::BOOSTS_ID);
        self::assertSelectorTextContains('body', 'Pozvat přítele');
    }

    // ── A global soutěž: an invitation buys nothing ─────────────────────────

    public function testInvitingIntoAPaidCompetitionHoldsNoSeat(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::ADMIN_ID);

        $client->request('POST', '/souteze/'.self::GLOBAL_ID.'/pozvanky/odeslat', [
            'send_invitation_form' => ['email' => 'platici@example.com'],
            'navrat' => 'detail',
        ]);

        self::assertResponseRedirects('/souteze/'.self::GLOBAL_ID);

        // Nothing was granted and nothing was recorded — only an e-mail went out, and it
        // carries the public invitation page rather than a token.
        self::assertNull($this->invitationFor(self::GLOBAL_ID, 'platici@example.com'));
        self::assertSame(0, $this->membershipCount(self::GLOBAL_ID, 'platici@example.com'));

        $mail = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $mail);
        $body = (string) $mail->getHtmlBody();
        self::assertStringContainsString('/souteze/'.self::GLOBAL_ID.'/pozvanka', $body);
        self::assertStringContainsString('50 kreditů', $body);
    }

    /**
     * The reported bug, in its exact shape: a GLOBAL competition with no entry fee, no PIN
     * and no shareable link left its own ORGANIZER with no way to invite anybody — not on
     * the competition page and not in „Nastavení", because all three invitation
     * permissions carried a blanket `!isGlobal`. Nothing about a global competition makes
     * it unshareable; it is the most public thing in the app.
     */
    public function testAFreeGlobalCompetitionOffersItsOrganizerAWayToInvite(): void
    {
        $client = static::createClient();
        $id = AppFixtures::FREE_GLOBAL_COMPETITION_ID;

        // What the invited friend gets — checked while still logged out, which is the
        // state they are actually in. Free means free: no price is quoted on the way in.
        $client->request('GET', '/souteze/'.$id.'/pozvanka');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', AppFixtures::FREE_GLOBAL_COMPETITION_NAME);
        self::assertSelectorTextNotContains('body', 'Vstupné je');

        // …and what the organizer gets: something to hand over at all.
        $this->loginUserById($client, AppFixtures::ADMIN_ID);
        $crawler = $client->request('GET', '/souteze/'.$id);
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('body', 'Pozvat přítele');
        self::assertCount(1, $crawler->filter('form[action="/souteze/'.$id.'/pozvanky/odeslat"]'));
        self::assertStringContainsString('/souteze/'.$id.'/pozvanka', (string) $client->getResponse()->getContent());
    }

    // ── The landing the invitation points at ────────────────────────────────

    public function testTheGlobalLandingNamesTheCompetitionAndItsPriceToAnonymousVisitors(): void
    {
        $client = static::createClient();
        $client->request('GET', '/souteze/'.self::GLOBAL_ID.'/pozvanka');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', AppFixtures::GLOBAL_COMPETITION_NAME);
        self::assertSelectorTextContains('body', '50 kreditů');
        self::assertSelectorExists('input[name="invitation_form[email]"]');
    }

    /**
     * A private competition's id must resolve to exactly the same „not found" as a
     * nonexistent one — otherwise this public page becomes a way to confirm that a given
     * UUID names a real partička.
     */
    public function testTheGlobalLandingRefusesPrivateAndUnknownCompetitionsAlike(): void
    {
        $client = static::createClient();

        $client->request('GET', '/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID.'/pozvanka');
        self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextContains('body', 'Pozvánka nenalezena');

        $client->request('GET', '/souteze/'.Uuid::v7()->toRfc4122().'/pozvanka');
        self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextContains('body', 'Pozvánka nenalezena');
    }

    public function testAFundedVisitorJoinsAndIsChargedTheEntryFee(): void
    {
        $client = static::createClient();
        $this->fund(AppFixtures::SECOND_VERIFIED_USER_ID, AppFixtures::GLOBAL_COMPETITION_ENTRY_FEE);
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $client->request('GET', '/souteze/'.self::GLOBAL_ID.'/pozvanka');

        self::assertResponseRedirects('/souteze/'.self::GLOBAL_ID.'?pripojeno=1');
        self::assertSame(1, $this->membershipCount(self::GLOBAL_ID, AppFixtures::SECOND_VERIFIED_USER_EMAIL));
    }

    /**
     * Being invited buys no discount: an empty wallet lands on the top-up page with the
     * shortfall named, and joins nothing.
     */
    public function testAnEmptyWalletIsSentToTopUpInsteadOfJoiningForFree(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::SECOND_VERIFIED_USER_ID);

        $client->request('GET', '/souteze/'.self::GLOBAL_ID.'/pozvanka');

        self::assertResponseRedirects('/kredity');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'potřebujete ještě 50 kreditů');
        self::assertSame(0, $this->membershipCount(self::GLOBAL_ID, AppFixtures::SECOND_VERIFIED_USER_EMAIL));
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function fund(string $userId, int $amount): void
    {
        $this->testCommandBus()->dispatch(new AdjustUserCreditsCommand(
            userId: Uuid::fromString($userId),
            amount: $amount,
            note: 'Vstupné pro test',
            adjustedById: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));
    }

    /** Revokes both join mechanics, i.e. closes the competition to newcomers. */
    private function closeTheDoors(string $competitionId): void
    {
        $em = $this->testEntityManager();
        $competition = $em->find(Competition::class, Uuid::fromString($competitionId));
        self::assertInstanceOf(Competition::class, $competition);

        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
        $competition->revokePin($now);
        $competition->revokeShareableLinkToken($now);
        $em->flush();
    }

    private function invitationFor(string $competitionId, string $email): ?CompetitionInvitation
    {
        $invitation = $this->testEntityManager()->createQueryBuilder()
            ->select('i')
            ->from(CompetitionInvitation::class, 'i')
            ->where('i.competition = :competition')
            ->andWhere('i.email = :email')
            ->setParameter('competition', Uuid::fromString($competitionId))
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();

        return $invitation instanceof CompetitionInvitation ? $invitation : null;
    }

    private function membershipCount(string $competitionId, string $email): int
    {
        return (int) $this->testEntityManager()->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(Membership::class, 'm')
            ->join('m.user', 'u')
            ->where('m.competition = :competition')
            ->andWhere('u.email = :email')
            ->andWhere('m.leftAt IS NULL')
            ->setParameter('competition', Uuid::fromString($competitionId))
            ->setParameter('email', $email)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
