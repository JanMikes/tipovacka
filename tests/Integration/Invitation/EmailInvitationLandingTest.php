<?php

declare(strict_types=1);

namespace App\Tests\Integration\Invitation;

use App\DataFixtures\AppFixtures;
use App\Entity\GroupInvitation;
use App\Entity\Membership;
use App\Entity\User;
use App\Enum\InvitationKind;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

final class EmailInvitationLandingTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    private const string EMAIL_TOKEN_URL = '/pozvanka/'.AppFixtures::PENDING_INVITATION_TOKEN;

    public function testAnonymousSeesLandingWithLiveFormMounted(): void
    {
        $client = static::createClient();
        $client->request('GET', self::EMAIL_TOKEN_URL);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', AppFixtures::PUBLIC_GROUP_NAME);
        // Live component mounted with locked email pre-filled.
        self::assertSelectorExists('input[name="invitation_form[email]"][disabled]');
        self::assertInputValueSame('invitation_form[email]', AppFixtures::PENDING_INVITATION_EMAIL);
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

    public function testRegisterThroughEmailInviteAutoVerifiesAndJoinsGroup(): void
    {
        $client = static::createClient();
        $component = $this->createInvitationFormComponent($client);

        $response = $component
            ->submitForm($this->validRegistration([
                'email' => AppFixtures::PENDING_INVITATION_EMAIL,
                'nickname' => 'outsider_new',
            ]), 'submit')
            ->response();

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/portal/skupiny/'.AppFixtures::PUBLIC_GROUP_ID, $response->headers->get('Location'));

        $em = $this->em($client);
        $em->clear();

        $user = $em->createQueryBuilder()
            ->select('u')->from(User::class, 'u')
            ->where('u.email = :e')
            ->setParameter('e', AppFixtures::PENDING_INVITATION_EMAIL)
            ->getQuery()->getOneOrNullResult();

        self::assertInstanceOf(User::class, $user);
        self::assertTrue($user->isVerified, 'Email-invite registrations must auto-verify.');
        self::assertSame('Jan', $user->firstName);
        self::assertSame('Novák', $user->lastName);

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

    public function testRegisterStepWithEmptyPasswordRejected(): void
    {
        $client = static::createClient();
        $component = $this->createInvitationFormComponent($client);

        $this->expectException(UnprocessableEntityHttpException::class);

        $component->submitForm($this->validRegistration([
            'email' => AppFixtures::PENDING_INVITATION_EMAIL,
            'password' => '',
            'passwordConfirm' => '',
        ]), 'submit');
    }

    public function testRegisterStepWithMismatchedPasswordsRejected(): void
    {
        $client = static::createClient();
        $component = $this->createInvitationFormComponent($client);

        $this->expectException(UnprocessableEntityHttpException::class);

        $component->submitForm($this->validRegistration([
            'email' => AppFixtures::PENDING_INVITATION_EMAIL,
            'password' => 'Str0ngP4ssword!',
            'passwordConfirm' => 'Different1!',
        ]), 'submit');
    }

    public function testCheckEmailForExistingVerifiedUserShowsLoginUi(): void
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

        $component = $this->createInvitationFormComponent($client);
        $rendered = (string) $component->render();

        // Login UI: nickname/firstName/lastName/passwordConfirm fields gone, password remains.
        self::assertStringContainsString('Přihlášení', $rendered);
        self::assertStringNotContainsString('invitation_form[nickname]', $rendered);
        self::assertStringNotContainsString('invitation_form[firstName]', $rendered);
        self::assertStringNotContainsString('invitation_form[passwordConfirm]', $rendered);
        self::assertStringContainsString('invitation_form[password]', $rendered);
    }

    public function testCompleteRegistrationFromStubAccount(): void
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

        $component = $this->createInvitationFormComponent($client);
        $response = $component
            ->submitForm([
                'invitation_form' => [
                    'email' => AppFixtures::PENDING_INVITATION_EMAIL,
                    'password' => 'Str0ngP4ssword!',
                    'passwordConfirm' => 'Str0ngP4ssword!',
                ],
            ], 'submit')
            ->response();

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/portal/skupiny/'.AppFixtures::PUBLIC_GROUP_ID, $response->headers->get('Location'));
    }

    public function testCompleteRegistrationWithEmptyPasswordRejected(): void
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

        $component = $this->createInvitationFormComponent($client);

        $this->expectException(UnprocessableEntityHttpException::class);

        $component->submitForm([
            'invitation_form' => [
                'email' => AppFixtures::PENDING_INVITATION_EMAIL,
                'password' => '',
                'passwordConfirm' => '',
            ],
        ], 'submit');
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

    /**
     * @return \Symfony\UX\LiveComponent\Test\TestLiveComponent
     */
    private function createInvitationFormComponent(KernelBrowser $client)
    {
        return $this->createLiveComponent('Auth:InvitationForm', [
            'kind' => InvitationKind::Email->value,
            'token' => AppFixtures::PENDING_INVITATION_TOKEN,
        ], $client);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, array<string, mixed>>
     */
    private function validRegistration(array $overrides = []): array
    {
        return [
            'invitation_form' => array_replace([
                'email' => 'newuser@example.com',
                'password' => 'Str0ngP4ssword!',
                'passwordConfirm' => 'Str0ngP4ssword!',
                'nickname' => 'newuser123',
                'firstName' => 'Jan',
                'lastName' => 'Novák',
            ], $overrides),
        ];
    }

    private function em(KernelBrowser $client): EntityManagerInterface
    {
        /* @var EntityManagerInterface */
        return $client->getContainer()->get('doctrine.orm.entity_manager');
    }
}
