<?php

declare(strict_types=1);

namespace App\Form;

use App\Validator\UniqueNickname;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Backs the unified, adaptive invitation form. Always carries email + password;
 * nickname / firstName / lastName / passwordConfirm are required only when
 * userKind === 'new', passwordConfirm also when 'stub'. The component sets
 * userKind from the email lookup before validation runs.
 */
final class InvitationFormData
{
    public const string KIND_NEW = 'new';
    public const string KIND_HAS_PASSWORD = 'has_password';
    public const string KIND_STUB = 'stub';

    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'Zadejte prosím e-mailovou adresu.'),
        new Assert\Email(message: 'Zadejte prosím platnou e-mailovou adresu.'),
    ])]
    public string $email = '';

    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'Zadejte prosím heslo.'),
        new Assert\When(
            expression: 'this.userKind in ["new", "stub"]',
            constraints: [new Assert\Length(min: 8, minMessage: 'Heslo musí mít alespoň {{ limit }} znaků.')],
        ),
    ])]
    public ?string $password = null;

    #[Assert\When(
        expression: 'this.userKind in ["new", "stub"]',
        constraints: [
            new Assert\NotBlank(message: 'Zopakujte prosím heslo.'),
            new Assert\EqualTo(propertyPath: 'password', message: 'Hesla se musí shodovat.'),
        ],
    )]
    public ?string $passwordConfirm = null;

    #[Assert\When(
        expression: 'this.userKind === "new"',
        constraints: [
            new Assert\Sequentially([
                new Assert\NotBlank(message: 'Zadejte prosím přezdívku.'),
                new Assert\Length(
                    min: 3,
                    max: 30,
                    minMessage: 'Přezdívka musí mít alespoň {{ limit }} znaky.',
                    maxMessage: 'Přezdívka nesmí být delší než {{ limit }} znaků.',
                ),
                new Assert\Regex(
                    pattern: '/^[A-Za-z0-9_.\-]+$/',
                    message: 'Přezdívka smí obsahovat pouze písmena, čísla, podtržítko, tečku a pomlčku.',
                ),
                new UniqueNickname(),
            ]),
        ],
    )]
    public string $nickname = '';

    #[Assert\When(
        expression: 'this.userKind === "new"',
        constraints: [
            new Assert\NotBlank(message: 'Zadejte prosím jméno.'),
            new Assert\Length(max: 100, maxMessage: 'Jméno nesmí být delší než {{ limit }} znaků.'),
        ],
    )]
    public string $firstName = '';

    #[Assert\When(
        expression: 'this.userKind === "new"',
        constraints: [
            new Assert\NotBlank(message: 'Zadejte prosím příjmení.'),
            new Assert\Length(max: 100, maxMessage: 'Příjmení nesmí být delší než {{ limit }} znaků.'),
        ],
    )]
    public string $lastName = '';

    public string $userKind = self::KIND_NEW;
}
