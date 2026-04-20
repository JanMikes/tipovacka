<?php

declare(strict_types=1);

namespace App\Tests\Integration\Invitation;

use App\DataFixtures\AppFixtures;
use App\Entity\Membership;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class ShareableLinkLandingTest extends WebTestCase
{
    private const string LINK_URL = '/skupiny/pozvanka/'.AppFixtures::VERIFIED_GROUP_LINK_TOKEN;

    public function testAnonymousSeesLandingWithEditableEmailStep(): void
    {
        $client = static::createClient();
        $client->request('GET', self::LINK_URL);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', AppFixtures::VERIFIED_GROUP_NAME);
        self::assertSelectorExists('input[name="invitation_email_form[email]"]');
        self::assertSelectorNotExists('input[name="invitation_email_form[email]"][disabled]');
    }

    public function testInvalidTokenReturns404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/skupiny/pozvanka/'.str_repeat('0', 48));

        self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextContains('body', 'Pozvánka nenalezena');
    }

    public function testCheckEmailForExistingUserShowsLoginStep(): void
    {
        $client = static::createClient();

        $this->submitEmailForm($client, self::LINK_URL, AppFixtures::VERIFIED_USER_EMAIL);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="invitation_login_form[password]"]');
    }

    public function testCheckEmailForNewUserShowsRegisterStep(): void
    {
        $client = static::createClient();

        $this->submitEmailForm($client, self::LINK_URL, 'fresh-user@example.test');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Vytvoř si účet');
        self::assertSelectorExists('input[name="invitation_register_form[nickname]"]');
    }

    public function testLoginFlowJoinsShareableGroup(): void
    {
        $client = static::createClient();
        $em = $this->em($client);

        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);

        // Admin is not yet a member of VERIFIED_GROUP (private group owned by the verified user).
        $this->submitEmailForm($client, self::LINK_URL, AppFixtures::ADMIN_EMAIL);

        $client->submitForm('Přihlásit se a připojit', [
            '_action' => 'login',
            'email' => AppFixtures::ADMIN_EMAIL,
            'invitation_login_form[password]' => AppFixtures::DEFAULT_PASSWORD,
        ]);

        self::assertResponseRedirects('/portal/skupiny/'.AppFixtures::VERIFIED_GROUP_ID);

        $em->clear();
        $memberships = $em->createQueryBuilder()
            ->select('m')->from(Membership::class, 'm')
            ->where('m.user = :u')
            ->andWhere('m.group = :g')
            ->setParameter('u', $admin->id)
            ->setParameter('g', Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID))
            ->getQuery()->getResult();

        self::assertCount(1, $memberships);
    }

    public function testLoginWithWrongPasswordReRendersLoginStepWithError(): void
    {
        $client = static::createClient();

        $this->submitEmailForm($client, self::LINK_URL, AppFixtures::ADMIN_EMAIL);

        $client->submitForm('Přihlásit se a připojit', [
            '_action' => 'login',
            'email' => AppFixtures::ADMIN_EMAIL,
            'invitation_login_form[password]' => 'wrongpassword',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Nesprávný e-mail nebo heslo');
        // Token stays in URL and email is preserved across re-render.
        self::assertSelectorExists('input[name="invitation_login_form[password]"]');
    }

    public function testRegisterThroughShareableLinkDoesNotAutoVerify(): void
    {
        $client = static::createClient();

        $this->submitEmailForm($client, self::LINK_URL, 'new-link-user@example.test');

        $client->submitForm('Vytvořit účet a připojit se', [
            '_action' => 'register',
            'email' => 'new-link-user@example.test',
            'invitation_register_form[nickname]' => 'new_link_user',
            'invitation_register_form[password][first]' => 'Str0ngP4ssword!',
            'invitation_register_form[password][second]' => 'Str0ngP4ssword!',
        ]);

        // Unverified users cannot join immediately — they bounce through email verification.
        self::assertResponseRedirects('/overeni-ceka');

        $em = $this->em($client);
        $em->clear();
        $user = $em->createQueryBuilder()
            ->select('u')->from(User::class, 'u')
            ->where('u.email = :e')
            ->setParameter('e', 'new-link-user@example.test')
            ->getQuery()->getOneOrNullResult();

        self::assertInstanceOf(User::class, $user);
        self::assertFalse(
            $user->isVerified,
            'Shareable-link registrations must require email verification.',
        );

        // No membership yet — it will be created only after the email-verification round-trip.
        $memberships = $em->createQueryBuilder()
            ->select('m')->from(Membership::class, 'm')
            ->where('m.user = :u')
            ->andWhere('m.group = :g')
            ->setParameter('u', $user->id)
            ->setParameter('g', Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID))
            ->getQuery()->getResult();

        self::assertCount(0, $memberships);
    }

    public function testAuthenticatedVerifiedUserJoinsImmediately(): void
    {
        $client = static::createClient();
        $em = $this->em($client);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);

        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
        $user = new User(
            id: Uuid::v7(),
            email: 'authjoin@example.test',
            password: null,
            nickname: 'authjoin_'.bin2hex(random_bytes(3)),
            createdAt: $now,
        );
        $user->changePassword($hasher->hashPassword($user, 'password'), $now);
        $user->markAsVerified($now);
        $user->popEvents();
        $em->persist($user);
        $em->flush();

        $client->loginUser($user);
        $client->request('GET', self::LINK_URL);

        self::assertResponseRedirects('/portal/skupiny/'.AppFixtures::VERIFIED_GROUP_ID);
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
