<?php

declare(strict_types=1);

namespace App\Tests\Integration\Invitation;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use App\Enum\InvitationKind;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

/**
 * A rejected sign-up must say what is wrong and how to fix it — in a box above the
 * submit button, not only under a field a screen higher (2026-10-17: a visitor tapped
 * „Vytvořit účet" four times, got four silent 422s and gave up).
 */
final class SignUpFeedbackTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    public function testFreshFormShowsNoSummary(): void
    {
        $client = static::createClient();

        $rendered = (string) $this->invitationForm($client)->render();

        self::assertStringNotContainsString('Účet zatím nevznikl', $rendered);
        self::assertStringContainsString('bez mezer a diakritiky', $rendered);
    }

    public function testLinkRegistrationWithFullNameAsNicknameExplainsAndSuggestsFix(): void
    {
        $client = static::createClient();
        $component = $this->invitationForm($client);
        $client->catchExceptions(true);

        $component->submitForm($this->invitationRegistration(['nickname' => 'Petr Svoboda']), 'submit');

        self::assertSame(422, $component->response()->getStatusCode());
        $body = (string) $component->response()->getContent();
        self::assertStringContainsString('Účet zatím nevznikl', $body);
        self::assertStringContainsString('nesmí obsahovat mezery ani diakritiku', $body);
        self::assertStringContainsString('data-form-feedback-value-param="petr.svoboda"', $body);
    }

    public function testEveryProblemIsListedInTheSummary(): void
    {
        $client = static::createClient();
        $component = $this->invitationForm($client);
        $client->catchExceptions(true);

        $component->submitForm($this->invitationRegistration([
            'password' => 'short',
            'passwordConfirm' => 'other',
            'gdprConsent' => null,
        ]), 'submit');

        self::assertSame(422, $component->response()->getStatusCode());
        $summary = $this->summaryOf((string) $component->response()->getContent());
        self::assertStringContainsString('Heslo musí mít alespoň 8 znaků.', $summary);
        self::assertStringContainsString('Hesla se neshodují. Zadejte do obou polí stejné heslo.', $summary);
        self::assertStringContainsString('souhlasit se zpracováním osobních údajů', $summary);
    }

    public function testPasswordlessAccountReachedByLinkIsToldAtTheFieldNotByAnInvisibleFlash(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        $stub = new User(
            id: Uuid::v7(),
            email: 'stub-by-link@example.test',
            password: null,
            nickname: null,
            createdAt: new \DateTimeImmutable('2025-06-15 12:00:00 UTC'),
        );
        $stub->popEvents();
        $em->persist($stub);
        $em->flush();

        $component = $this->invitationForm($client);
        $component->submitForm([
            'invitation_form' => [
                'email' => 'stub-by-link@example.test',
                'password' => 'Str0ngP4ssword!',
                'passwordConfirm' => 'Str0ngP4ssword!',
                'gdprConsent' => '1',
            ],
        ], 'submit');

        self::assertSame(200, $component->response()->getStatusCode());
        $summary = $this->summaryOf((string) $component->render());
        self::assertStringContainsString('ještě nemá heslo', $summary);
    }

    public function testRegistrationPageListsProblemsAndSuggestsFreeNickname(): void
    {
        $client = static::createClient();
        $component = $this->createLiveComponent('Auth:RegistrationForm', [], $client);
        $client->catchExceptions(true);

        $component->submitForm([
            'registration_form' => [
                'email' => 'jiri-novak@example.test',
                'nickname' => 'Jiří Novák',
                'firstName' => 'Jiří',
                'lastName' => 'Novák',
                'password' => ['first' => 'abc', 'second' => 'abc'],
                'gdprConsent' => '1',
            ],
        ], 'register');

        self::assertSame(422, $component->response()->getStatusCode());
        $body = (string) $component->response()->getContent();
        $summary = $this->summaryOf($body);
        self::assertStringContainsString('nesmí obsahovat mezery ani diakritiku', $summary);
        self::assertStringContainsString('Heslo musí mít alespoň 8 znaků.', $summary);
        self::assertStringContainsString('data-form-feedback-value-param="jiri.novak"', $body);
    }

    public function testMistypedMailboxDomainOffersTheCorrection(): void
    {
        $client = static::createClient();

        $rendered = (string) $this->invitationForm($client)
            ->set('invitation_form', ['email' => 'tipujici@seznam.cy'])
            ->render();

        self::assertStringContainsString('Nemysleli jste', $rendered);
        self::assertStringContainsString('data-form-feedback-value-param="tipujici@seznam.cz"', $rendered);
    }

    public function testUntickedConsentIsMarkedInvalid(): void
    {
        $client = static::createClient();
        $component = $this->invitationForm($client);
        $client->catchExceptions(true);

        $component->submitForm($this->invitationRegistration(['gdprConsent' => null]), 'submit');

        self::assertMatchesRegularExpression(
            '/<input[^>]*name="invitation_form\[gdprConsent\]"[^>]*aria-invalid="true"|<input[^>]*aria-invalid="true"[^>]*name="invitation_form\[gdprConsent\]"/',
            (string) $component->response()->getContent(),
        );
    }

    private function invitationForm(KernelBrowser $client): TestLiveComponent
    {
        return $this->createLiveComponent('Auth:InvitationForm', [
            'kind' => InvitationKind::ShareableLink->value,
            'token' => AppFixtures::VERIFIED_COMPETITION_LINK_TOKEN,
        ], $client);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, array<string, mixed>>
     */
    private function invitationRegistration(array $overrides = []): array
    {
        return [
            'invitation_form' => array_filter(array_replace([
                'email' => 'petr-svoboda@example.test',
                'password' => 'Str0ngP4ssword!',
                'passwordConfirm' => 'Str0ngP4ssword!',
                'nickname' => 'petr_svoboda',
                'firstName' => 'Petr',
                'lastName' => 'Svoboda',
                'gdprConsent' => '1',
            ], $overrides), static fn (mixed $value): bool => null !== $value),
        ];
    }

    private function summaryOf(string $html): string
    {
        $start = strpos($html, 'data-form-feedback-target="summary"');
        self::assertNotFalse($start, 'The error summary must be rendered.');
        $end = strpos($html, '</div>', $start);
        self::assertNotFalse($end);

        return html_entity_decode(substr($html, $start, $end - $start));
    }
}
