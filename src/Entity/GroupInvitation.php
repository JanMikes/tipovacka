<?php

declare(strict_types=1);

namespace App\Entity;

use App\Event\GroupInvitationAccepted;
use App\Event\GroupInvitationRevoked;
use App\Event\GroupInvitationSent;
use App\Exception\GroupInvitationAlreadyAccepted;
use App\Exception\GroupInvitationAlreadyRevoked;
use App\Exception\GroupInvitationExpired;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'group_invitations')]
#[ORM\Index(columns: ['group_id'], name: 'IDX_group_invitations_group')]
#[ORM\UniqueConstraint(name: 'UIDX_group_invitations_token', columns: ['token'])]
class GroupInvitation implements EntityWithEvents
{
    use HasEvents;

    #[ORM\Column(nullable: true)]
    public private(set) ?\DateTimeImmutable $acceptedAt = null;

    #[ORM\Column(nullable: true)]
    public private(set) ?\DateTimeImmutable $revokedAt = null;

    public bool $isAccepted {
        get => null !== $this->acceptedAt;
    }

    public bool $isRevoked {
        get => null !== $this->revokedAt;
    }

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private(set) Uuid $id,
        #[ORM\ManyToOne(targetEntity: Group::class)]
        #[ORM\JoinColumn(name: 'group_id', referencedColumnName: 'id', nullable: false)]
        private(set) Group $group,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'inviter_id', referencedColumnName: 'id', nullable: false)]
        private(set) User $inviter,
        #[ORM\Column(length: 180)]
        private(set) string $email,
        #[ORM\Column(length: 64)]
        private(set) string $token,
        #[ORM\Column]
        private(set) \DateTimeImmutable $createdAt,
        #[ORM\Column]
        private(set) \DateTimeImmutable $expiresAt,
    ) {
        $this->recordThat(new GroupInvitationSent(
            invitationId: $this->id,
            groupId: $this->group->id,
            inviterId: $this->inviter->id,
            email: $this->email,
            token: $this->token,
            occurredOn: $this->createdAt,
        ));
    }

    public function isExpiredAt(\DateTimeImmutable $now): bool
    {
        return $now > $this->expiresAt;
    }

    public function accept(Uuid $userId, \DateTimeImmutable $now): void
    {
        if ($this->isAccepted) {
            throw GroupInvitationAlreadyAccepted::create();
        }

        if ($this->isRevoked) {
            throw GroupInvitationAlreadyRevoked::create();
        }

        if ($this->isExpiredAt($now)) {
            throw GroupInvitationExpired::create();
        }

        $this->acceptedAt = $now;

        $this->recordThat(new GroupInvitationAccepted(
            invitationId: $this->id,
            groupId: $this->group->id,
            userId: $userId,
            occurredOn: $now,
        ));
    }

    public function revoke(\DateTimeImmutable $now): void
    {
        if ($this->isRevoked) {
            return;
        }

        if ($this->isAccepted) {
            throw GroupInvitationAlreadyAccepted::create();
        }

        $this->revokedAt = $now;

        $this->recordThat(new GroupInvitationRevoked(
            invitationId: $this->id,
            occurredOn: $now,
        ));
    }
}
