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
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

final class ShareableLinkLandingTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    private const string LINK_URL = '/skupiny/pozvanka/'.AppFixtures::VERIFIED_GROUP_LINK_TOKEN;

    public function testAnonymousSeesLandingWithEditableEmailField(): void
    {
        $client = static::createClient();
        $client->request('GET', self::LINK_URL);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', AppFixtures::VERIFIED_GROUP_NAME);
        self::assertSelectorExists('input[name="invitation_form[email]"]');
        self::assertSelectorNotExists('input[name="invitation_form[email]"][disabled]');
    }

    public function testInvalidTokenReturns404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/skupiny/pozvanka/'.str_repeat('0', 48));

        self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextContains('body', 'Pozvánka nenalezena');
    }

    public function testEnteringExistingUsersEmailHidesNicknameAndNameInputs(): void
    {
        $client = static::createClient();

        $component = $this->createInvitationFormComponent($client);
        $rendered = (string) $component
            ->set('invitation_form', ['email' => AppFixtures::VERIFIED_USER_EMAIL])
            ->render();

        self::assertStringNotContainsString('invitation_form[nickname]', $rendered);
        self::assertStringNotContainsString('invitation_form[firstName]', $rendered);
        self::assertStringNotContainsString('invitation_form[lastName]', $rendered);
        self::assertStringNotContainsString('invitation_form[passwordConfirm]', $rendered);
        self::assertStringContainsString('invitation_form[password]', $rendered);
    }

    public function testEnteringNewEmailRevealsNicknameAndNameInputs(): void
    {
        $client = static::createClient();

        $component = $this->createInvitationFormComponent($client);
        $rendered = (string) $component
            ->set('invitation_form', ['email' => 'fresh-user@example.test'])
            ->render();

        self::assertStringContainsString('invitation_form[nickname]', $rendered);
        self::assertStringContainsString('invitation_form[firstName]', $rendered);
        self::assertStringContainsString('invitation_form[lastName]', $rendered);
        self::assertStringContainsString('invitation_form[passwordConfirm]', $rendered);
    }

    public function testLoginFlowJoinsShareableGroup(): void
    {
        $client = static::createClient();
        $em = $this->em($client);

        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);

        $component = $this->createInvitationFormComponent($client);
        $response = $component
            ->submitForm([
                'invitation_form' => [
                    'email' => AppFixtures::ADMIN_EMAIL,
                    'password' => AppFixtures::DEFAULT_PASSWORD,
                ],
            ], 'submit')
            ->response();

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/portal/skupiny/'.AppFixtures::VERIFIED_GROUP_ID, $response->headers->get('Location'));

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

    public function testLoginWithWrongPasswordRendersInlineError(): void
    {
        $client = static::createClient();

        $component = $this->createInvitationFormComponent($client);
        $component->submitForm([
            'invitation_form' => [
                'email' => AppFixtures::ADMIN_EMAIL,
                'password' => 'wrongpassword',
            ],
        ], 'submit');

        self::assertSame(200, $component->response()->getStatusCode());
        self::assertStringContainsString('Nesprávný e-mail nebo heslo', (string) $component->render());
    }

    public function testRegisterThroughShareableLinkDoesNotAutoVerify(): void
    {
        $client = static::createClient();

        $component = $this->createInvitationFormComponent($client);
        $response = $component
            ->submitForm($this->validRegistration([
                'email' => 'new-link-user@example.test',
                'nickname' => 'new_link_user',
            ]), 'submit')
            ->response();

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/overeni-ceka', $response->headers->get('Location'));

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

        $memberships = $em->createQueryBuilder()
            ->select('m')->from(Membership::class, 'm')
            ->where('m.user = :u')
            ->andWhere('m.group = :g')
            ->setParameter('u', $user->id)
            ->setParameter('g', Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID))
            ->getQuery()->getResult();

        self::assertCount(0, $memberships);
    }

    public function testRegisterWithEmptyPasswordRejected(): void
    {
        $client = static::createClient();
        $component = $this->createInvitationFormComponent($client);

        $this->expectException(UnprocessableEntityHttpException::class);

        $component->submitForm($this->validRegistration([
            'email' => 'fresh-empty@example.test',
            'password' => '',
            'passwordConfirm' => '',
        ]), 'submit');
    }

    public function testRegisterWithMismatchedPasswordsRejected(): void
    {
        $client = static::createClient();
        $component = $this->createInvitationFormComponent($client);

        $this->expectException(UnprocessableEntityHttpException::class);

        $component->submitForm($this->validRegistration([
            'email' => 'fresh-mismatch@example.test',
            'password' => 'Str0ngP4ssword!',
            'passwordConfirm' => 'Different1!',
        ]), 'submit');
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

    /**
     * @return \Symfony\UX\LiveComponent\Test\TestLiveComponent
     */
    private function createInvitationFormComponent(KernelBrowser $client)
    {
        return $this->createLiveComponent('Auth:InvitationForm', [
            'kind' => InvitationKind::ShareableLink->value,
            'token' => AppFixtures::VERIFIED_GROUP_LINK_TOKEN,
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
