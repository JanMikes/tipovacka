<?php

declare(strict_types=1);

namespace App\Tests\Integration\Auth;

use App\DataFixtures\AppFixtures;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

final class RegistrationFlowTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    public function testRegistrationPageRenders(): void
    {
        $client = static::createClient();
        $client->request('GET', '/registrace');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="registration_form[email]"]');
        self::assertSelectorExists('input[name="registration_form[firstName]"]');
        self::assertSelectorExists('input[name="registration_form[lastName]"]');
        self::assertSelectorExists('input[name="registration_form[nickname]"]');
    }

    public function testSuccessfulRegistrationRedirectsToPending(): void
    {
        static::createClient();

        $component = $this->createLiveComponent('Auth:RegistrationForm');
        $response = $component->submitForm($this->validRegistrationFormValues(), 'register')->response();

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/overeni-ceka', $response->headers->get('Location'));
    }

    public function testDuplicateEmailRendersInlineError(): void
    {
        static::createClient();

        $component = $this->createLiveComponent('Auth:RegistrationForm');
        $component->submitForm(
            $this->validRegistrationFormValues(['email' => AppFixtures::VERIFIED_USER_EMAIL]),
            'register',
        );

        self::assertSame(200, $component->response()->getStatusCode());
        self::assertStringContainsString('e-mail je již zaregistrován', (string) $component->render());
    }

    public function testDuplicateNicknameRendersInlineError(): void
    {
        static::createClient();

        $component = $this->createLiveComponent('Auth:RegistrationForm');
        $component->submitForm(
            $this->validRegistrationFormValues(['nickname' => AppFixtures::VERIFIED_USER_NICKNAME]),
            'register',
        );

        self::assertSame(200, $component->response()->getStatusCode());
        self::assertStringContainsString('přezdívka je již obsazena', (string) $component->render());
    }

    public function testInvalidNicknameRegexRejected(): void
    {
        static::createClient();

        $component = $this->createLiveComponent('Auth:RegistrationForm');

        $this->expectException(UnprocessableEntityHttpException::class);

        $component->submitForm(
            $this->validRegistrationFormValues(['nickname' => 'invalid nick!']),
            'register',
        );
    }

    public function testEmptyPasswordRejected(): void
    {
        static::createClient();

        $component = $this->createLiveComponent('Auth:RegistrationForm');

        $this->expectException(UnprocessableEntityHttpException::class);

        $component->submitForm(
            $this->validRegistrationFormValues(['password' => ['first' => '', 'second' => '']]),
            'register',
        );
    }

    public function testMismatchedPasswordsRejected(): void
    {
        static::createClient();

        $component = $this->createLiveComponent('Auth:RegistrationForm');

        $this->expectException(UnprocessableEntityHttpException::class);

        $component->submitForm(
            $this->validRegistrationFormValues(['password' => ['first' => 'Password1!', 'second' => 'Different1!']]),
            'register',
        );
    }

    public function testMissingFirstNameRejected(): void
    {
        static::createClient();

        $component = $this->createLiveComponent('Auth:RegistrationForm');

        $this->expectException(UnprocessableEntityHttpException::class);

        $component->submitForm(
            $this->validRegistrationFormValues(['firstName' => '']),
            'register',
        );
    }

    public function testMissingLastNameRejected(): void
    {
        static::createClient();

        $component = $this->createLiveComponent('Auth:RegistrationForm');

        $this->expectException(UnprocessableEntityHttpException::class);

        $component->submitForm(
            $this->validRegistrationFormValues(['lastName' => '']),
            'register',
        );
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, array<string, mixed>>
     */
    private function validRegistrationFormValues(array $overrides = []): array
    {
        return [
            'registration_form' => array_replace([
                'email' => 'newuser@example.com',
                'firstName' => 'Jan',
                'lastName' => 'Novák',
                'nickname' => 'newuser123',
                'password' => [
                    'first' => 'Securepassword1',
                    'second' => 'Securepassword1',
                ],
            ], $overrides),
        ];
    }
}
