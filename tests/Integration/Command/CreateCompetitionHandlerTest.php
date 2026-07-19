<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\CreateCompetition\CreateCompetitionCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\CompetitionInvitation;
use App\Entity\CompetitionMatchSelection;
use App\Entity\CompetitionRuleConfiguration;
use App\Entity\MatchSource;
use App\Entity\Membership;
use App\Entity\Sport;
use App\Entity\User;
use App\Enum\CompetitionMatchSelectionMode;
use App\Enum\CompetitionMonetization;
use App\Enum\MatchSourceKind;
use App\Exception\InvalidInvitationEmails;
use App\Rule\ExactScoreRule;
use App\Rule\ScorerHitRule;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Uid\Uuid;

final class CreateCompetitionHandlerTest extends IntegrationTestCase
{
    public function testCreatesCompetitionWithMembership(): void
    {
        $this->commandBus()->dispatch(new CreateCompetitionCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            name: 'Parta',
            matchSourceId: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
            sportId: null,
            fromScratch: false,
            withPin: false,
            description: 'Popis',
        ));

        $competition = $this->findCompetitionByName('Parta');

        self::assertSame(AppFixtures::VERIFIED_USER_ID, $competition->owner->id->toRfc4122());
        self::assertNull($competition->pin);
        self::assertNotNull($competition->shareableLinkToken);

        $memberships = $this->entityManager()->createQueryBuilder()
            ->select('m')
            ->from(Membership::class, 'm')
            ->where('m.competition = :competitionId')
            ->setParameter('competitionId', $competition->id)
            ->getQuery()
            ->getResult();

        self::assertCount(1, $memberships);
        self::assertSame(AppFixtures::VERIFIED_USER_ID, $memberships[0]->user->id->toRfc4122());
    }

    public function testCreatesCompetitionWithTipSettingsAndSubsetSelection(): void
    {
        $this->commandBus()->dispatch(new CreateCompetitionCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            name: 'Jen vybrané',
            matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
            sportId: null,
            fromScratch: false,
            withPin: false,
            selectionMode: CompetitionMatchSelectionMode::Subset,
            includePlayoff: true,
            selectedMatchIds: [
                Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
                Uuid::fromString(AppFixtures::MATCH_PLAYOFF_ID),
            ],
            hideOthersTipsBeforeDeadline: true,
        ));

        $competition = $this->findCompetitionByName('Jen vybrané');

        self::assertTrue($competition->hideOthersTipsBeforeDeadline);
        self::assertNull($competition->tipsLockedAt);
        self::assertSame(CompetitionMatchSelectionMode::Subset, $competition->selectionMode);

        $selections = $this->entityManager()->createQueryBuilder()
            ->select('s')
            ->from(CompetitionMatchSelection::class, 's')
            ->where('s.competition = :competitionId')
            ->setParameter('competitionId', $competition->id)
            ->getQuery()
            ->getResult();

        self::assertCount(2, $selections);
    }

    public function testSubsetCreateIgnoresMatchesFromOtherSources(): void
    {
        $this->commandBus()->dispatch(new CreateCompetitionCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            name: 'Cizí zápas',
            matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
            sportId: null,
            fromScratch: false,
            withPin: false,
            selectionMode: CompetitionMatchSelectionMode::Subset,
            selectedMatchIds: [
                Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
                // Belongs to the PRIVATE source — must be silently skipped.
                Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
            ],
        ));

        $competition = $this->findCompetitionByName('Cizí zápas');

        $selections = $this->entityManager()->createQueryBuilder()
            ->select('s')
            ->from(CompetitionMatchSelection::class, 's')
            ->where('s.competition = :competitionId')
            ->setParameter('competitionId', $competition->id)
            ->getQuery()
            ->getResult();

        self::assertCount(1, $selections);
        self::assertSame(AppFixtures::MATCH_SCHEDULED_ID, $selections[0]->sportMatch->id->toRfc4122());
    }

    public function testCreatesCompetitionWithPin(): void
    {
        $this->commandBus()->dispatch(new CreateCompetitionCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            name: 'S PINem',
            matchSourceId: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
            sportId: null,
            fromScratch: false,
            withPin: true,
        ));

        $competition = $this->findCompetitionByName('S PINem');

        self::assertNotNull($competition->pin);
        self::assertMatchesRegularExpression('/^\d{8}$/', $competition->pin);
    }

    public function testFromScratchCreatesHiddenPrivateSourceForChosenSport(): void
    {
        $this->commandBus()->dispatch(new CreateCompetitionCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            name: 'Od začátku',
            matchSourceId: null,
            sportId: Uuid::fromString(Sport::HOCKEY_ID),
            fromScratch: true,
            withPin: false,
            monetization: CompetitionMonetization::Boosts,
        ));

        $competition = $this->findCompetitionByName('Od začátku');

        self::assertSame(MatchSourceKind::Private, $competition->matchSource->kind);
        self::assertSame('hockey', $competition->matchSource->sport->code);
        self::assertSame(AppFixtures::VERIFIED_USER_ID, $competition->matchSource->owner->id->toRfc4122());
        self::assertSame('Od začátku', $competition->matchSource->name);
        self::assertSame(CompetitionMonetization::Boosts, $competition->monetization);
        self::assertSame(CompetitionMatchSelectionMode::All, $competition->selectionMode);
    }

    public function testAppliesRuleChangesOverDefaults(): void
    {
        $this->commandBus()->dispatch(new CreateCompetitionCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            name: 'Vlastní pravidla',
            matchSourceId: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
            sportId: null,
            fromScratch: false,
            withPin: false,
            ruleChanges: [
                ExactScoreRule::IDENTIFIER => ['enabled' => true, 'points' => 8],
                ScorerHitRule::IDENTIFIER => ['enabled' => true, 'points' => 3],
            ],
        ));

        $competition = $this->findCompetitionByName('Vlastní pravidla');

        self::assertSame(8, $this->rulePoints($competition->id, ExactScoreRule::IDENTIFIER));
        // Optional rule enabled via the wizard (default would be disabled).
        $scorer = $this->ruleConfig($competition->id, ScorerHitRule::IDENTIFIER);
        self::assertTrue($scorer->enabled);
        self::assertSame(3, $scorer->points);
    }

    public function testSetsPremiumMonetization(): void
    {
        $this->commandBus()->dispatch(new CreateCompetitionCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            name: 'Prémiová',
            matchSourceId: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
            sportId: null,
            fromScratch: false,
            withPin: false,
            monetization: CompetitionMonetization::Premium,
        ));

        self::assertSame(CompetitionMonetization::Premium, $this->findCompetitionByName('Prémiová')->monetization);
    }

    public function testInvitesEmailsCreatingStubUsersAndInvitations(): void
    {
        $this->commandBus()->dispatch(new CreateCompetitionCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            name: 'S pozvánkami',
            matchSourceId: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
            sportId: null,
            fromScratch: false,
            withPin: false,
            inviteEmails: ['nova1@example.com, nova2@example.com'],
        ));

        $competition = $this->findCompetitionByName('S pozvánkami');

        $invitations = $this->entityManager()->createQueryBuilder()
            ->select('i')
            ->from(CompetitionInvitation::class, 'i')
            ->where('i.competition = :competitionId')
            ->setParameter('competitionId', $competition->id)
            ->getQuery()
            ->getResult();

        self::assertCount(2, $invitations);

        $stub = $this->entityManager()->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('u.email = :email')
            ->setParameter('email', 'nova1@example.com')
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(User::class, $stub);
        // Owner + two stub invitees each get an active membership so managers can tip on their behalf.
        self::assertCount(3, $this->membershipsOf($competition->id));
    }

    public function testInvitingOwnEmailDoesNotCreateDuplicateMembership(): void
    {
        // Regression: the owner's Membership is created but not yet flushed when the
        // inviter lists their own address — a naive path would create a second one and
        // hit the partial unique index at flush, 500-ing the whole wizard submit.
        $this->commandBus()->dispatch(new CreateCompetitionCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            name: 'Sám sebe',
            matchSourceId: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
            sportId: null,
            fromScratch: false,
            withPin: false,
            inviteEmails: [AppFixtures::VERIFIED_USER_EMAIL],
        ));

        $competition = $this->findCompetitionByName('Sám sebe');

        // Exactly one active membership (the owner), no invitation to self.
        self::assertCount(1, $this->membershipsOf($competition->id));
        self::assertCount(0, $this->entityManager()->createQueryBuilder()
            ->select('i')->from(CompetitionInvitation::class, 'i')
            ->where('i.competition = :competitionId')
            ->setParameter('competitionId', $competition->id)
            ->getQuery()->getResult());
    }

    public function testInvalidInvitationEmailRollsBackTheWholeCreation(): void
    {
        $em = $this->entityManager();

        try {
            $this->commandBus()->dispatch(new CreateCompetitionCommand(
                ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
                name: 'Atomická od začátku',
                matchSourceId: null,
                sportId: Uuid::fromString(Sport::FOOTBALL_ID),
                fromScratch: true,
                withPin: false,
                inviteEmails: ['tohle-neni-email'],
            ));
            self::fail('Expected InvalidInvitationEmails to abort the transaction.');
        } catch (HandlerFailedException $exception) {
            self::assertInstanceOf(InvalidInvitationEmails::class, $this->firstWrappedException($exception));
        }

        $em->clear();

        // Nothing persisted: no competition AND no orphan from-scratch source.
        self::assertNull($em->createQueryBuilder()
            ->select('c')->from(Competition::class, 'c')
            ->where('c.name = :name')->setParameter('name', 'Atomická od začátku')
            ->getQuery()->getOneOrNullResult());

        self::assertNull($em->createQueryBuilder()
            ->select('s')->from(MatchSource::class, 's')
            ->where('s.name = :name')->setParameter('name', 'Atomická od začátku')
            ->getQuery()->getOneOrNullResult());
    }

    private function findCompetitionByName(string $name): Competition
    {
        $this->entityManager()->clear();

        $competition = $this->entityManager()->createQueryBuilder()
            ->select('c')
            ->from(Competition::class, 'c')
            ->where('c.name = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(Competition::class, $competition);

        return $competition;
    }

    private function ruleConfig(Uuid $competitionId, string $identifier): CompetitionRuleConfiguration
    {
        $config = $this->entityManager()->createQueryBuilder()
            ->select('r')
            ->from(CompetitionRuleConfiguration::class, 'r')
            ->where('r.competition = :competitionId')
            ->andWhere('r.ruleIdentifier = :identifier')
            ->setParameter('competitionId', $competitionId)
            ->setParameter('identifier', $identifier)
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(CompetitionRuleConfiguration::class, $config);

        return $config;
    }

    private function rulePoints(Uuid $competitionId, string $identifier): int
    {
        return $this->ruleConfig($competitionId, $identifier)->points;
    }

    /**
     * @return list<Membership>
     */
    private function membershipsOf(Uuid $competitionId): array
    {
        return $this->entityManager()->createQueryBuilder()
            ->select('m')
            ->from(Membership::class, 'm')
            ->where('m.competition = :competitionId')
            ->setParameter('competitionId', $competitionId)
            ->getQuery()
            ->getResult();
    }
}
