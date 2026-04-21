<?php

declare(strict_types=1);

namespace App\Tests\Unit\Form;

use App\Form\InvitationFormData;
use App\Validator\UniqueNicknameValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class InvitationFormDataTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $noopValidator = new class extends ConstraintValidator {
            public function validate(mixed $value, Constraint $constraint): void
            {
            }
        };

        $factory = new class($noopValidator) implements ConstraintValidatorFactoryInterface {
            public function __construct(private readonly ConstraintValidatorInterface $noopValidator)
            {
            }

            public function getInstance(Constraint $constraint): ConstraintValidatorInterface
            {
                $className = $constraint->validatedBy();

                if (UniqueNicknameValidator::class === $className) {
                    return $this->noopValidator;
                }

                $instance = new $className();
                \assert($instance instanceof ConstraintValidatorInterface);

                return $instance;
            }
        };

        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->setConstraintValidatorFactory($factory)
            ->getValidator();
    }

    public function testNewKindRequiresAllFields(): void
    {
        $data = new InvitationFormData();
        $data->userKind = InvitationFormData::KIND_NEW;
        $data->email = '';

        $violations = $this->validator->validate($data);

        $this->assertHasViolation($violations, 'email', 'Zadejte prosím e-mailovou adresu.');
        $this->assertHasViolation($violations, 'password', 'Zadejte prosím heslo.');
        $this->assertHasViolation($violations, 'passwordConfirm', 'Zopakujte prosím heslo.');
        $this->assertHasViolation($violations, 'nickname', 'Zadejte prosím přezdívku.');
        $this->assertHasViolation($violations, 'firstName', 'Zadejte prosím jméno.');
        $this->assertHasViolation($violations, 'lastName', 'Zadejte prosím příjmení.');
    }

    public function testNewKindAcceptsCompleteInput(): void
    {
        $data = new InvitationFormData();
        $data->userKind = InvitationFormData::KIND_NEW;
        $data->email = 'jan@example.com';
        $data->password = 'Securepassword1';
        $data->passwordConfirm = 'Securepassword1';
        $data->nickname = 'jan_n';
        $data->firstName = 'Jan';
        $data->lastName = 'Novák';

        $violations = $this->validator->validate($data);

        self::assertCount(0, $violations);
    }

    public function testNewKindRejectsMismatchedPasswords(): void
    {
        $data = new InvitationFormData();
        $data->userKind = InvitationFormData::KIND_NEW;
        $data->email = 'jan@example.com';
        $data->password = 'Securepassword1';
        $data->passwordConfirm = 'Different1';
        $data->nickname = 'jan_n';
        $data->firstName = 'Jan';
        $data->lastName = 'Novák';

        $violations = $this->validator->validate($data);

        $this->assertHasViolation($violations, 'passwordConfirm', 'Hesla se musí shodovat.');
    }

    public function testHasPasswordKindOnlyRequiresEmailAndPassword(): void
    {
        $data = new InvitationFormData();
        $data->userKind = InvitationFormData::KIND_HAS_PASSWORD;
        $data->email = 'jan@example.com';
        $data->password = 'whatever-existing-password';
        // nickname / firstName / lastName / passwordConfirm intentionally left empty

        $violations = $this->validator->validate($data);

        self::assertCount(0, $violations, sprintf('Unexpected violations: %s', (string) $violations));
    }

    public function testHasPasswordKindStillRequiresPasswordPresence(): void
    {
        $data = new InvitationFormData();
        $data->userKind = InvitationFormData::KIND_HAS_PASSWORD;
        $data->email = 'jan@example.com';

        $violations = $this->validator->validate($data);

        $this->assertHasViolation($violations, 'password', 'Zadejte prosím heslo.');
    }

    public function testStubKindRequiresPasswordWithMinLengthAndConfirm(): void
    {
        $data = new InvitationFormData();
        $data->userKind = InvitationFormData::KIND_STUB;
        $data->email = 'jan@example.com';
        $data->password = 'short';
        $data->passwordConfirm = 'short';

        $violations = $this->validator->validate($data);

        $this->assertHasViolation($violations, 'password', 'Heslo musí mít alespoň 8 znaků.');
    }

    public function testStubKindAcceptsValidPassword(): void
    {
        $data = new InvitationFormData();
        $data->userKind = InvitationFormData::KIND_STUB;
        $data->email = 'jan@example.com';
        $data->password = 'Securepassword1';
        $data->passwordConfirm = 'Securepassword1';

        $violations = $this->validator->validate($data);

        self::assertCount(0, $violations, sprintf('Unexpected violations: %s', (string) $violations));
    }

    public function testStubKindDoesNotRequireNicknameOrName(): void
    {
        $data = new InvitationFormData();
        $data->userKind = InvitationFormData::KIND_STUB;
        $data->email = 'jan@example.com';
        $data->password = 'Securepassword1';
        $data->passwordConfirm = 'Securepassword1';
        $data->nickname = '';
        $data->firstName = '';
        $data->lastName = '';

        $violations = $this->validator->validate($data);

        $touchedProperties = array_map(static fn ($v) => $v->getPropertyPath(), iterator_to_array($violations));
        self::assertNotContains('nickname', $touchedProperties);
        self::assertNotContains('firstName', $touchedProperties);
        self::assertNotContains('lastName', $touchedProperties);
    }

    public function testInvalidEmailRejected(): void
    {
        $data = new InvitationFormData();
        $data->userKind = InvitationFormData::KIND_HAS_PASSWORD;
        $data->email = 'not-an-email';
        $data->password = 'whatever';

        $violations = $this->validator->validate($data);

        $this->assertHasViolation($violations, 'email', 'Zadejte prosím platnou e-mailovou adresu.');
    }

    public function testInvalidNicknameRegexRejected(): void
    {
        $data = new InvitationFormData();
        $data->userKind = InvitationFormData::KIND_NEW;
        $data->email = 'jan@example.com';
        $data->password = 'Securepassword1';
        $data->passwordConfirm = 'Securepassword1';
        $data->nickname = 'has space';
        $data->firstName = 'Jan';
        $data->lastName = 'Novák';

        $violations = $this->validator->validate($data);

        $this->assertHasViolation(
            $violations,
            'nickname',
            'Přezdívka smí obsahovat pouze písmena, čísla, podtržítko, tečku a pomlčku.',
        );
    }

    private function assertHasViolation(
        ConstraintViolationListInterface $violations,
        string $propertyPath,
        string $expectedMessage,
    ): void {
        $found = null;
        foreach ($violations as $v) {
            if ($v->getPropertyPath() === $propertyPath && $v->getMessage() === $expectedMessage) {
                $found = $v;

                break;
            }
        }

        self::assertNotNull($found, sprintf(
            'Expected violation on "%s" with message "%s" not found. Got: %s',
            $propertyPath,
            $expectedMessage,
            (string) $violations,
        ));
    }
}
