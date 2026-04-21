<?php

declare(strict_types=1);

namespace App\Tests\Integration\Invitation;

use App\DataFixtures\AppFixtures;
use App\Entity\GroupInvitation;
use App\Entity\Membership;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class EmailInvitationLandingTest extends WebTestCase
{
    private const string EMAIL_TOKEN_URL = '/pozvanka/'.AppFixtures::PENDING_INVITATION_TOKEN;

    public function testAnonymousSeesLandingWithLockedEmailStep(): void
    {
        $client = static::createClient();
        $client->request('GET', self::EMAIL_TOKEN_URL);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', AppFixtures::PUBLIC_GROUP_NAME);
        // Locked email input carries the invitation's target address.
        self::assertInputValueSame('invitation_email_form[email]', AppFixtures::PENDING_INVITATION_EMAIL);
        self::assertSelectorExists('input[name="invitation_email_form[email]"][disabled]');
    }

    public function testInvalidTokenReturns404WithLandingTemplate(): void
    {
        $client = static::createClient();
        $client->request('GET', '/pozvanka/'.str_repeat('0', 64));

        self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextContains('body', 'Pozvánka nenalezena');
    }

    public function testExpiredInvitationShowsTerminalState(): void
    {
        $client = static::createClient();
        $em = $this->em($client);

        $em->getConnection()->executeStatement(
            'UPDATE group_invitations SET expires_at = :past WHERE id = :id',
            ['past' => '2024-01-01 00:00:00', 'id' => AppFixtures::PENDING_INVITATION_ID],
        );

        $client->request('GET', self::EMAIL_TOKEN_URL);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Pozvánka vypršela');
    }

    public function testRevokedInvitationShowsTerminalState(): void
    {
        $client = static::createClient();
        $em = $this->em($client);

        $em->getConnection()->executeStatement(
            'UPDATE group_invitations SET revoked_at = :now WHERE id = :id',
            ['now' => '2025-06-15 11:00:00', 'id' => AppFixtures::PENDING_INVITATION_ID],
        );

        $client->request('GET', self::EMAIL_TOKEN_URL);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Pozvánka byla zrušena');
    }

    public function testAcceptedInvitationShowsTerminalState(): void
    {
        $client = static::createClient();
        $em = $this->em($client);

        $em->getConnection()->executeStatement(
            'UPDATE group_invitations SET accepted_at = :now WHERE id = :id',
            ['now' => '2025-06-15 11:00:00', 'id' => AppFixtures::PENDING_INVITATION_ID],
        );

        $client->request('GET', self::EMAIL_TOKEN_URL);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'už byla přijata');
    }

    public function testCheckEmailForPresetAddressShowsRegisterStepForNewUser(): void
    {
        $client = static::createClient();

        $this->submitEmailForm($client, self::EMAIL_TOKEN_URL, AppFixtures::PENDING_INVITATION_EMAIL);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Vytvoř si účet');
        self::assertSelectorExists('input[name="invitation_register_form[nickname]"]');
    }

    public function testRegisterThroughEmailInviteAutoVerifiesAndJoinsGroup(): void
    {
        $client = static::createClient();

        $this->submitEmailForm($client, self::EMAIL_TOKEN_URL, AppFixtures::PENDING_INVITATION_EMAIL);
        $client->submitForm('Vytvořit účet a připojit se', [
            '_action' => 'register',
            'email' => AppFixtures::PENDING_INVITATION_EMAIL,
            'invitation_register_form[nickname]' => 'outsider_new',
            'invitation_register_form[password][first]' => 'Str0ngP4ssword!',
            'invitation_register_form[password][second]' => 'Str0ngP4ssword!',
        ]);

        self::assertResponseRedirects('/portal/skupiny/'.AppFixtures::PUBLIC_GROUP_ID);

        $em = $this->em($client);
        $em->clear();

        $user = $em->createQueryBuilder()
            ->select('u')->from(User::class, 'u')
            ->where('u.email = :e')
            ->setParameter('e', AppFixtures::PENDING_INVITATION_EMAIL)
            ->getQuery()->getOneOrNullResult();

        self::assertInstanceOf(User::class, $user);
        self::assertTrue($user->isVerified, 'Email-invite registrations must auto-verify.');

        $invitation = $em->find(GroupInvitation::class, Uuid::fromString(AppFixtures::PENDING_INVITATION_ID));
        self::assertNotNull($invitation);
        self::assertTrue($invitation->isAccepted);

        $memberships = $em->createQueryBuilder()
            ->select('m')->from(Membership::class, 'm')
            ->where('m.user = :u')
            ->andWhere('m.group = :g')
            ->setParameter('u', $user->id)
            ->setParameter('g', Uuid::fromString(AppFixtures::PUBLIC_GROUP_ID))
            ->getQuery()->getResult();

        self::assertCount(1, $memberships);
    }

    public function testRegisterStepWithEmptyPasswordReRendersWithError(): void
    {
        $client = static::createClient();

        $this->submitEmailForm($client, self::EMAIL_TOKEN_URL, AppFixtures::PENDING_INVITATION_EMAIL);

        $client->submitForm('Vytvořit účet a připojit se', [
            '_action' => 'register',
            'email' => AppFixtures::PENDING_INVITATION_EMAIL,
            'invitation_register_form[nickname]' => 'newcomer',
            'invitation_register_form[password][first]' => '',
            'invitation_register_form[password][second]' => '',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Zadejte prosím heslo.');
    }

    public function testRegisterStepWithMismatchedPasswordsReRendersWithError(): void
    {
        $client = static::createClient();

        $this->submitEmailForm($client, self::EMAIL_TOKEN_URL, AppFixtures::PENDING_INVITATION_EMAIL);

        $client->submitForm('Vytvořit účet a připojit se', [
            '_action' => 'register',
            'email' => AppFixtures::PENDING_INVITATION_EMAIL,
            'invitation_register_form[nickname]' => 'newcomer',
            'invitation_register_form[password][first]' => 'Str0ngP4ssword!',
            'invitation_register_form[password][second]' => 'Different1!',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Hesla se musí shodovat.');
    }

    public function testCompleteRegistrationWithEmptyPasswordReRendersWithError(): void
    {
        $client = static::createClient();
        $em = $this->em($client);

        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
        $stub = new User(
            id: Uuid::v7(),
            email: AppFixtures::PENDING_INVITATION_EMAIL,
            password: null,
            nickname: 'stub_'.bin2hex(random_bytes(3)),
            createdAt: $now,
        );
        $stub->popEvents();
        $em->persist($stub);
        $em->flush();

        $this->submitEmailForm($client, self::EMAIL_TOKEN_URL, AppFixtures::PENDING_INVITATION_EMAIL);

        $client->submitForm('Dokončit registraci a připojit se', [
            '_action' => 'complete_registration',
            'email' => AppFixtures::PENDING_INVITATION_EMAIL,
            'complete_invitation_registration_form[password][first]' => '',
            'complete_invitation_registration_form[password][second]' => '',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Zadejte prosím heslo.');
    }

    public function testCheckEmailForExistingVerifiedStubShowsLoginStep(): void
    {
        $client = static::createClient();
        $em = $this->em($client);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);

        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
        $user = new User(
            id: Uuid::v7(),
            email: AppFixtures::PENDING_INVITATION_EMAIL,
            password: null,
            nickname: 'pwdset_'.bin2hex(random_bytes(3)),
            createdAt: $now,
        );
        $user->changePassword($hasher->hashPassword($user, 'password'), $now);
        $user->markAsVerified($now);
        $user->popEvents();
        $em->persist($user);
        $em->flush();

        $this->submitEmailForm($client, self::EMAIL_TOKEN_URL, AppFixtures::PENDING_INVITATION_EMAIL);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="invitation_login_form[password]"]');
    }

    public function testAuthenticatedVerifiedUserMatchingPresetEmailIsAddedImmediately(): void
    {
        $client = static::createClient();
        $em = $this->em($client);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);

        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
        $user = new User(
            id: Uuid::v7(),
            email: AppFixtures::PENDING_INVITATION_EMAIL,
            password: null,
            nickname: 'preset_'.bin2hex(random_bytes(3)),
            createdAt: $now,
        );
        $user->changePassword($hasher->hashPassword($user, 'password'), $now);
        $user->markAsVerified($now);
        $user->popEvents();
        $em->persist($user);
        $em->flush();

        $client->loginUser($user);

        $client->request('GET', self::EMAIL_TOKEN_URL);

        self::assertResponseRedirects('/portal/skupiny/'.AppFixtures::PUBLIC_GROUP_ID);
    }

    public function testAuthenticatedDifferentEmailShowsMismatchPrompt(): void
    {
        $client = static::createClient();
        $em = $this->em($client);

        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);

        $client->loginUser($admin);

        $client->request('GET', self::EMAIL_TOKEN_URL);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Pozvánka je pro jiný e-mail');
    }

    private function em(KernelBrowser $client): EntityManagerInterface
    {
        /* @var EntityManagerInterface */
        return $client->getContainer()->get('doctrine.orm.entity_manager');
    }

    private function submitEmailForm(KernelBrowser $client, string $url, string $email): void
    {
        $client->request('GET', $url);
        $client->submitForm('Pokračovat', [
            '_action' => 'check_email',
            'invitation_email_form[email]' => $email,
        ]);
    }
}
