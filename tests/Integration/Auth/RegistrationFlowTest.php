<?php

declare(strict_types=1);

namespace App\Tests\Integration\Auth;

use App\DataFixtures\AppFixtures;
use App\Form\RegistrationFormData;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
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
        self::assertSelectorExists('input[name="registration_form[gdprConsent]"]');
        self::assertSelectorExists('a[href="/ochrana-soukromi"]');
    }

    public function testSuccessfulRegistrationRedirectsToPending(): void
    {
        static::createClient();

        $component = $this->createLiveComponent('Auth:RegistrationForm');
        $response = $component->submitForm($this->validRegistrationFormValues(), 'register')->response();

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/overeni-ceka', $response->headers->get('Location'));
    }

    public function testDuplicateEmailRejected(): void
    {
        static::createClient();

        $component = $this->createLiveComponent('Auth:RegistrationForm');

        $this->expectException(UnprocessableEntityHttpException::class);

        $component->submitForm(
            $this->validRegistrationFormValues(['email' => AppFixtures::VERIFIED_USER_EMAIL]),
            'register',
        );
    }

    public function testDuplicateNicknameRejected(): void
    {
        static::createClient();

        $component = $this->createLiveComponent('Auth:RegistrationForm');

        $this->expectException(UnprocessableEntityHttpException::class);

        $component->submitForm(
            $this->validRegistrationFormValues(['nickname' => AppFixtures::VERIFIED_USER_NICKNAME]),
            'register',
        );
    }

    public function testUniqueEmailValidatorMessage(): void
    {
        $client = static::createClient();
        /** @var ValidatorInterface $validator */
        $validator = $client->getContainer()->get('validator');

        $data = new RegistrationFormData();
        $data->email = AppFixtures::VERIFIED_USER_EMAIL;
        $data->firstName = 'Jan';
        $data->lastName = 'Novák';
        $data->nickname = 'available_nick';
        $data->password = 'Securepassword1';

        $violations = $validator->validate($data);
        self::assertTrue($this->hasViolation($violations, 'email', 'Tento e-mail je již zaregistrován.'));
    }

    public function testUniqueNicknameValidatorMessage(): void
    {
        $client = static::createClient();
        /** @var ValidatorInterface $validator */
        $validator = $client->getContainer()->get('validator');

        $data = new RegistrationFormData();
        $data->email = 'available@example.com';
        $data->firstName = 'Jan';
        $data->lastName = 'Novák';
        $data->nickname = AppFixtures::VERIFIED_USER_NICKNAME;
        $data->password = 'Securepassword1';

        $violations = $validator->validate($data);
        self::assertTrue($this->hasViolation($violations, 'nickname', 'Tato přezdívka je již obsazena.'));
    }

    private function hasViolation(
        ConstraintViolationListInterface $violations,
        string $propertyPath,
        string $expectedMessage,
    ): bool {
        foreach ($violations as $v) {
            if ($v->getPropertyPath() === $propertyPath && $v->getMessage() === $expectedMessage) {
                return true;
            }
        }

        return false;
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
                'gdprConsent' => '1',
            ], $overrides),
        ];
    }

    public function testMissingGdprConsentRejected(): void
    {
        static::createClient();

        $component = $this->createLiveComponent('Auth:RegistrationForm');

        $this->expectException(UnprocessableEntityHttpException::class);

        $values = $this->validRegistrationFormValues();
        unset($values['registration_form']['gdprConsent']);

        $component->submitForm($values, 'register');
    }

    public function testGdprConsentValidatorMessage(): void
    {
        $client = static::createClient();
        /** @var ValidatorInterface $validator */
        $validator = $client->getContainer()->get('validator');

        $data = new RegistrationFormData();
        $data->email = 'available@example.com';
        $data->firstName = 'Jan';
        $data->lastName = 'Novák';
        $data->nickname = 'available_nick';
        $data->password = 'Securepassword1';
        $data->gdprConsent = false;

        $violations = $validator->validate($data);
        self::assertTrue($this->hasViolation(
            $violations,
            'gdprConsent',
            'Pro pokračování je potřeba souhlasit se zpracováním osobních údajů.',
        ));
    }
}
