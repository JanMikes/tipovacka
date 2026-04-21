<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\SoftDeletable;
use App\Entity\Concerns\SoftDeletes;
use App\Enum\UserRole;
use App\Event\EmailVerified;
use App\Event\PasswordChanged;
use App\Event\UserBlocked;
use App\Event\UserDeleted;
use App\Event\UserEmailAssigned;
use App\Event\UserProfileUpdated;
use App\Event\UserRegistered;
use App\Event\UserUnblocked;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'users_email_unique', columns: ['email'], options: ['where' => '(email IS NOT NULL)'])]
#[ORM\UniqueConstraint(name: 'users_nickname_unique', columns: ['nickname'], options: ['where' => '(nickname IS NOT NULL)'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface, EntityWithEvents, SoftDeletable
{
    use HasEvents;
    use SoftDeletes;

    /**
     * @var array<string>
     */
    #[ORM\Column]
    private array $roles;

    #[ORM\Column]
    public private(set) bool $isVerified = false;

    #[ORM\Column]
    public private(set) bool $isActive = true;

    public bool $hasPassword {
        get => null !== $this->password && '' !== $this->password;
    }

    public bool $isAnonymous {
        get => null === $this->email;
    }

    #[ORM\Column]
    public private(set) \DateTimeImmutable $updatedAt;

    #[ORM\Column(length: 100, nullable: true)]
    public private(set) ?string $firstName = null;

    #[ORM\Column(length: 100, nullable: true)]
    public private(set) ?string $lastName = null;

    #[ORM\Column(length: 20, nullable: true)]
    public private(set) ?string $phone = null;

    public string $fullName {
        get => trim(($this->firstName ?? '').' '.($this->lastName ?? ''));
    }

    public string $displayName {
        get {
            if (null !== $this->nickname && '' !== $this->nickname) {
                return $this->nickname;
            }

            $fullName = $this->fullName;

            if ('' !== $fullName) {
                return $fullName;
            }

            return 'Uživatel';
        }
    }

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private(set) Uuid $id,
        #[ORM\Column(length: 180, nullable: true)]
        private(set) ?string $email,
        #[ORM\Column(nullable: true)]
        private ?string $password,
        #[ORM\Column(length: 30, nullable: true)]
        private(set) ?string $nickname,
        #[ORM\Column]
        private(set) \DateTimeImmutable $createdAt,
    ) {
        $this->roles = [UserRole::USER->value];
        $this->updatedAt = $this->createdAt;

        $this->recordThat(new UserRegistered(
            userId: $this->id,
            email: $this->email,
            nickname: $this->nickname,
            occurredOn: $this->createdAt,
        ));
    }

    /**
     * @return non-empty-string
     */
    public function getUserIdentifier(): string
    {
        if (null === $this->email || '' === $this->email) {
            return 'anon:'.$this->id->toRfc4122();
        }

        return $this->email;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function changePassword(string $hashedPassword, \DateTimeImmutable $now): void
    {
        $this->password = $hashedPassword;
        $this->updatedAt = $now;

        $this->recordThat(new PasswordChanged(
            userId: $this->id,
            occurredOn: $now,
        ));
    }

    public function assignEmail(string $email, \DateTimeImmutable $now): void
    {
        if (null !== $this->email) {
            throw new \DomainException('Tento účet už má přiřazený e-mail.');
        }

        $this->email = $email;
        $this->updatedAt = $now;

        $this->recordThat(new UserEmailAssigned(
            userId: $this->id,
            email: $email,
            occurredOn: $now,
        ));
    }

    /**
     * Returns the roles assigned to the user.
     * ROLE_USER is always included and stored internally, not added dynamically.
     *
     * @return array<string>
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function markAsVerified(\DateTimeImmutable $now): void
    {
        $this->isVerified = true;
        $this->updatedAt = $now;

        $this->recordThat(new EmailVerified(
            userId: $this->id,
            occurredOn: $now,
        ));
    }

    public function changeRole(UserRole $role, \DateTimeImmutable $now): void
    {
        $this->roles = [UserRole::USER->value, $role->value];
        $this->updatedAt = $now;
    }

    public function activate(\DateTimeImmutable $now): void
    {
        $this->isActive = true;
        $this->updatedAt = $now;

        $this->recordThat(new UserUnblocked(
            userId: $this->id,
            occurredOn: $now,
        ));
    }

    public function deactivate(\DateTimeImmutable $now): void
    {
        $this->isActive = false;
        $this->updatedAt = $now;

        $this->recordThat(new UserBlocked(
            userId: $this->id,
            occurredOn: $now,
        ));
    }

    public function updateProfile(
        ?string $firstName,
        ?string $lastName,
        ?string $phone,
        \DateTimeImmutable $now,
    ): void {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->phone = $phone;
        $this->updatedAt = $now;

        $this->recordThat(new UserProfileUpdated(
            userId: $this->id,
            occurredOn: $now,
        ));
    }

    public function softDelete(\DateTimeImmutable $now): void
    {
        $this->markDeleted($now);
        $this->updatedAt = $now;

        $this->recordThat(new UserDeleted(
            userId: $this->id,
            email: $this->email,
            nickname: $this->nickname,
            occurredOn: $now,
        ));
    }
}
