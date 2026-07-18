<?php

declare(strict_types=1);

namespace App\Entity;

use App\Event\CompetitionInvitationAccepted;
use App\Event\CompetitionInvitationRevoked;
use App\Event\CompetitionInvitationSent;
use App\Exception\CompetitionInvitationAlreadyAccepted;
use App\Exception\CompetitionInvitationAlreadyRevoked;
use App\Exception\CompetitionInvitationExpired;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'competition_invitations')]
#[ORM\Index(columns: ['competition_id'], name: 'IDX_competition_invitations_competition')]
#[ORM\UniqueConstraint(name: 'UIDX_competition_invitations_token', columns: ['token'])]
class CompetitionInvitation implements EntityWithEvents
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
        #[ORM\ManyToOne(targetEntity: Competition::class)]
        #[ORM\JoinColumn(name: 'competition_id', referencedColumnName: 'id', nullable: false)]
        private(set) Competition $competition,
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
        $this->recordThat(new CompetitionInvitationSent(
            invitationId: $this->id,
            competitionId: $this->competition->id,
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
            throw CompetitionInvitationAlreadyAccepted::create();
        }

        if ($this->isRevoked) {
            throw CompetitionInvitationAlreadyRevoked::create();
        }

        if ($this->isExpiredAt($now)) {
            throw CompetitionInvitationExpired::create();
        }

        $this->acceptedAt = $now;

        $this->recordThat(new CompetitionInvitationAccepted(
            invitationId: $this->id,
            competitionId: $this->competition->id,
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
            throw CompetitionInvitationAlreadyAccepted::create();
        }

        $this->revokedAt = $now;

        $this->recordThat(new CompetitionInvitationRevoked(
            invitationId: $this->id,
            occurredOn: $now,
        ));
    }
}
