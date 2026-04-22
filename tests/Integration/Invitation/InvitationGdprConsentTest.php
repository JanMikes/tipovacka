<?php

declare(strict_types=1);

namespace App\Tests\Integration\Invitation;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use App\Enum\InvitationKind;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

final class InvitationGdprConsentTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    public function testShareableLinkLandingRendersConsentCheckboxForNewEmail(): void
    {
        $client = static::createClient();
        $client->request('GET', '/skupiny/pozvanka/'.AppFixtures::VERIFIED_GROUP_LINK_TOKEN);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="invitation_form[gdprConsent]"]');
        self::assertSelectorExists('a[href="/ochrana-soukromi"]');
    }

    public function testShareableLinkNewEmailRegistrationRequiresConsent(): void
    {
        $client = static::createClient();
        $component = $this->createLiveComponent('Auth:InvitationForm', [
            'kind' => InvitationKind::ShareableLink->value,
            'token' => AppFixtures::VERIFIED_GROUP_LINK_TOKEN,
        ], $client);

        $this->expectException(UnprocessableEntityHttpException::class);

        $component->submitForm([
            'invitation_form' => [
                'email' => 'fresh-consent@example.test',
                'password' => 'Str0ngP4ssword!',
                'passwordConfirm' => 'Str0ngP4ssword!',
                'nickname' => 'fresh_consent',
                'firstName' => 'Jan',
                'lastName' => 'Novák',
                'gdprConsent' => '0',
            ],
        ], 'submit');
    }

    public function testEmailInvitationLandingRendersConsentCheckbox(): void
    {
        $client = static::createClient();
        $client->request('GET', '/pozvanka/'.AppFixtures::PENDING_INVITATION_TOKEN);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="invitation_form[gdprConsent]"]');
        self::assertSelectorExists('a[href="/ochrana-soukromi"]');
    }

    public function testEmailInvitationNewUserRegistrationRequiresConsent(): void
    {
        $client = static::createClient();
        $component = $this->createLiveComponent('Auth:InvitationForm', [
            'kind' => InvitationKind::Email->value,
            'token' => AppFixtures::PENDING_INVITATION_TOKEN,
        ], $client);

        $this->expectException(UnprocessableEntityHttpException::class);

        $component->submitForm([
            'invitation_form' => [
                'email' => AppFixtures::PENDING_INVITATION_EMAIL,
                'password' => 'Str0ngP4ssword!',
                'passwordConfirm' => 'Str0ngP4ssword!',
                'nickname' => 'invite_newbie',
                'firstName' => 'Jan',
                'lastName' => 'Novák',
                'gdprConsent' => '0',
            ],
        ], 'submit');
    }

    public function testStubCompletionRequiresConsent(): void
    {
        $client = static::createClient();
        $em = $this->em($client);

        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
        $stub = new User(
            id: Uuid::v7(),
            email: AppFixtures::PENDING_INVITATION_EMAIL,
            password: null,
            nickname: 'stub_consent_'.bin2hex(random_bytes(3)),
            createdAt: $now,
        );
        $stub->popEvents();
        $em->persist($stub);
        $em->flush();

        $component = $this->createLiveComponent('Auth:InvitationForm', [
            'kind' => InvitationKind::Email->value,
            'token' => AppFixtures::PENDING_INVITATION_TOKEN,
        ], $client);

        $this->expectException(UnprocessableEntityHttpException::class);

        $component->submitForm([
            'invitation_form' => [
                'email' => AppFixtures::PENDING_INVITATION_EMAIL,
                'password' => 'Str0ngP4ssword!',
                'passwordConfirm' => 'Str0ngP4ssword!',
                'gdprConsent' => '0',
            ],
        ], 'submit');
    }

    public function testExistingUserLoginFlowDoesNotRenderCheckbox(): void
    {
        $client = static::createClient();

        $component = $this->createLiveComponent('Auth:InvitationForm', [
            'kind' => InvitationKind::ShareableLink->value,
            'token' => AppFixtures::VERIFIED_GROUP_LINK_TOKEN,
        ], $client);

        $rendered = (string) $component
            ->set('invitation_form', ['email' => AppFixtures::VERIFIED_USER_EMAIL])
            ->render();

        self::assertStringNotContainsString('invitation_form[gdprConsent]', $rendered);
    }

    private function em(KernelBrowser $client): EntityManagerInterface
    {
        /* @var EntityManagerInterface */
        return $client->getContainer()->get('doctrine.orm.entity_manager');
    }
}
