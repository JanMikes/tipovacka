<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\SoftDeletable;
use App\Entity\Concerns\SoftDeletes;
use App\Enum\UserRole;
use App\Event\EmailVerified;
use App\Event\UserDeleted;
use App\Event\UserRegistered;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
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
    private bool $isVerified = false;

    #[ORM\Column]
    private bool $isActive = true;

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

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private(set) Uuid $id,
        #[ORM\Column(length: 180, unique: true)]
        private(set) string $email,
        #[ORM\Column(nullable: true)]
        private ?string $password,
        #[ORM\Column(length: 30, unique: true)]
        private(set) string $nickname,
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
        assert('' !== $this->email, 'Email must not be empty');

        return $this->email;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function hasPassword(): bool
    {
        return null !== $this->password && '' !== $this->password;
    }

    public function changePassword(string $hashedPassword, \DateTimeImmutable $now): void
    {
        $this->password = $hashedPassword;
        $this->updatedAt = $now;
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

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function isActive(): bool
    {
        return $this->isActive;
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
    }

    public function deactivate(\DateTimeImmutable $now): void
    {
        $this->isActive = false;
        $this->updatedAt = $now;
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
