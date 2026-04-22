<?php

declare(strict_types=1);

namespace App\Tests\Unit\Form;

use App\Form\RegistrationFormData;
use App\Validator\UniqueNicknameValidator;
use App\Validator\UniqueUserEmailValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RegistrationFormDataGdprTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $noopValidator = new class () extends ConstraintValidator {
            public function validate(mixed $value, Constraint $constraint): void
            {
            }
        };

        $factory = new class ($noopValidator) implements ConstraintValidatorFactoryInterface {
            public function __construct(private readonly ConstraintValidatorInterface $noopValidator)
            {
            }

            public function getInstance(Constraint $constraint): ConstraintValidatorInterface
            {
                $className = $constraint->validatedBy();

                if (UniqueNicknameValidator::class === $className
                    || UniqueUserEmailValidator::class === $className
                ) {
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

    public function testMissingConsentIsRejected(): void
    {
        $data = $this->validFormData();
        $data->gdprConsent = false;

        $violations = $this->validator->validate($data);

        $this->assertHasViolation(
            $violations,
            'gdprConsent',
            'Pro pokračování je potřeba souhlasit se zpracováním osobních údajů.',
        );
    }

    public function testCheckedConsentPassesValidation(): void
    {
        $data = $this->validFormData();
        $data->gdprConsent = true;

        $violations = $this->validator->validate($data);

        $offending = [];
        foreach ($violations as $v) {
            if ('gdprConsent' === $v->getPropertyPath()) {
                $offending[] = (string) $v->getMessage();
            }
        }

        self::assertSame([], $offending, sprintf(
            'Did not expect violations on "gdprConsent", got: %s',
            implode('; ', $offending),
        ));
    }

    private function validFormData(): RegistrationFormData
    {
        $data = new RegistrationFormData();
        $data->email = 'jan@example.com';
        $data->firstName = 'Jan';
        $data->lastName = 'Novák';
        $data->nickname = 'jan_n';
        $data->password = 'Securepassword1';

        return $data;
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
