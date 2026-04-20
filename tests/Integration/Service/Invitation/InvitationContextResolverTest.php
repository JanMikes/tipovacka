<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service\Invitation;

use App\DataFixtures\AppFixtures;
use App\Entity\GroupInvitation;
use App\Enum\InvitationKind;
use App\Exception\InvalidInvitationToken;
use App\Exception\InvalidShareableLink;
use App\Service\Invitation\InvitationContextResolver;
use App\Service\Invitation\InvitationContextStatus;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class InvitationContextResolverTest extends IntegrationTestCase
{
    private function resolver(): InvitationContextResolver
    {
        /* @var InvitationContextResolver */
        return self::getContainer()->get(InvitationContextResolver::class);
    }

    private function fixedNow(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
    }

    public function testResolvesActiveEmailInvitation(): void
    {
        $context = $this->resolver()->resolve(
            InvitationKind::Email,
            AppFixtures::PENDING_INVITATION_TOKEN,
            $this->fixedNow(),
        );

        self::assertSame(InvitationKind::Email, $context->kind);
        self::assertSame(InvitationContextStatus::Active, $context->status);
        self::assertSame(AppFixtures::PENDING_INVITATION_TOKEN, $context->token);
        self::assertSame(AppFixtures::PUBLIC_GROUP_NAME, $context->groupName);
        self::assertSame(AppFixtures::PUBLIC_TOURNAMENT_NAME, $context->tournamentName);
        self::assertSame(AppFixtures::PENDING_INVITATION_EMAIL, $context->presetEmail);
        self::assertSame(AppFixtures::ADMIN_NICKNAME, $context->inviterNickname);
        self::assertNotNull($context->expiresAt);
    }

    public function testReportsExpiredEmailInvitation(): void
    {
        $this->entityManager()->getConnection()->executeStatement(
            'UPDATE group_invitations SET expires_at = :past WHERE id = :id',
            [
                'past' => '2024-01-01 00:00:00',
                'id' => AppFixtures::PENDING_INVITATION_ID,
            ],
        );

        $context = $this->resolver()->resolve(
            InvitationKind::Email,
            AppFixtures::PENDING_INVITATION_TOKEN,
            $this->fixedNow(),
        );

        self::assertSame(InvitationContextStatus::Expired, $context->status);
    }

    public function testReportsRevokedEmailInvitation(): void
    {
        $em = $this->entityManager();
        /** @var GroupInvitation $invitation */
        $invitation = $em->find(GroupInvitation::class, Uuid::fromString(AppFixtures::PENDING_INVITATION_ID));
        $invitation->revoke($this->fixedNow());
        $em->flush();
        $em->clear();

        $context = $this->resolver()->resolve(
            InvitationKind::Email,
            AppFixtures::PENDING_INVITATION_TOKEN,
            $this->fixedNow(),
        );

        self::assertSame(InvitationContextStatus::Revoked, $context->status);
    }

    public function testReportsAcceptedEmailInvitation(): void
    {
        $this->entityManager()->getConnection()->executeStatement(
            'UPDATE group_invitations SET accepted_at = :now WHERE id = :id',
            [
                'now' => $this->fixedNow()->format('Y-m-d H:i:s'),
                'id' => AppFixtures::PENDING_INVITATION_ID,
            ],
        );

        $context = $this->resolver()->resolve(
            InvitationKind::Email,
            AppFixtures::PENDING_INVITATION_TOKEN,
            $this->fixedNow(),
        );

        self::assertSame(InvitationContextStatus::Accepted, $context->status);
    }

    public function testThrowsWhenEmailTokenInvalid(): void
    {
        $this->expectException(InvalidInvitationToken::class);

        $this->resolver()->resolve(
            InvitationKind::Email,
            str_repeat('0', 64),
            $this->fixedNow(),
        );
    }

    public function testResolvesActiveShareableLink(): void
    {
        $context = $this->resolver()->resolve(
            InvitationKind::ShareableLink,
            AppFixtures::VERIFIED_GROUP_LINK_TOKEN,
            $this->fixedNow(),
        );

        self::assertSame(InvitationKind::ShareableLink, $context->kind);
        self::assertSame(InvitationContextStatus::Active, $context->status);
        self::assertSame(AppFixtures::VERIFIED_GROUP_NAME, $context->groupName);
        self::assertNull($context->presetEmail);
        self::assertNotNull($context->inviterNickname);
    }

    public function testThrowsWhenShareableLinkTokenInvalid(): void
    {
        $this->expectException(InvalidShareableLink::class);

        $this->resolver()->resolve(
            InvitationKind::ShareableLink,
            str_repeat('0', 48),
            $this->fixedNow(),
        );
    }
}
