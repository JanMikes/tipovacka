<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\CreateGlobalCompetition\CreateGlobalCompetitionCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\CompetitionRuleConfiguration;
use App\Entity\Membership;
use App\Enum\CompetitionMatchSelectionMode;
use App\Enum\CompetitionMonetization;
use App\Exception\GlobalCompetitionRequiresCuratedSource;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Uid\Uuid;

final class CreateGlobalCompetitionHandlerTest extends IntegrationTestCase
{
    public function testCreatesGlobalCompetitionWithOwnerMembershipAndRules(): void
    {
        $envelope = $this->commandBus()->dispatch(new CreateGlobalCompetitionCommand(
            adminId: Uuid::fromString(AppFixtures::ADMIN_ID),
            matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
            name: 'Nová globální liga',
            entryFeeCredits: 30,
            monetization: CompetitionMonetization::None,
        ));

        $competition = $this->resultCompetition($envelope);
        $competitionId = $competition->id;

        $this->entityManager()->clear();

        /** @var Competition $reloaded */
        $reloaded = $this->entityManager()->find(Competition::class, $competitionId);
        self::assertTrue($reloaded->isGlobal);
        self::assertSame(30, $reloaded->entryFeeCredits);
        self::assertSame(CompetitionMatchSelectionMode::All, $reloaded->selectionMode);
        self::assertSame(AppFixtures::ADMIN_ID, $reloaded->owner->id->toRfc4122());
        // A global competition must NEVER mint a shareable-link token (nor a PIN):
        // joining is entry-fee only — a token would be a fee-free back door.
        self::assertNull($reloaded->shareableLinkToken);
        self::assertNull($reloaded->pin);

        // Admin owner membership.
        $memberCount = (int) $this->entityManager()->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(Membership::class, 'm')
            ->where('m.competition = :competitionId')
            ->andWhere('m.leftAt IS NULL')
            ->setParameter('competitionId', $competitionId)
            ->getQuery()
            ->getSingleScalarResult();
        self::assertSame(1, $memberCount);

        // Rule rows provisioned (at least the four base rules).
        $ruleCount = (int) $this->entityManager()->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(CompetitionRuleConfiguration::class, 'r')
            ->where('r.competition = :competitionId')
            ->setParameter('competitionId', $competitionId)
            ->getQuery()
            ->getSingleScalarResult();
        self::assertGreaterThanOrEqual(4, $ruleCount);
    }

    public function testPrivateSourceIsRejected(): void
    {
        try {
            $this->commandBus()->dispatch(new CreateGlobalCompetitionCommand(
                adminId: Uuid::fromString(AppFixtures::ADMIN_ID),
                matchSourceId: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
                name: 'Neplatná globální',
                entryFeeCredits: 0,
            ));
            self::fail('Expected GlobalCompetitionRequiresCuratedSource.');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(GlobalCompetitionRequiresCuratedSource::class, $this->firstWrappedException($e));
        }
    }

    private function resultCompetition(Envelope $envelope): Competition
    {
        $result = $envelope->last(HandledStamp::class)?->getResult();
        self::assertInstanceOf(Competition::class, $result);

        return $result;
    }
}
