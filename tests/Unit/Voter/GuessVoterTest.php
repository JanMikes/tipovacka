<?php

declare(strict_types=1);

namespace App\Tests\Unit\Voter;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\Guess;
use App\Entity\MatchSource;
use App\Entity\Sport;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\MatchSourceKind;
use App\Repository\BoostPurchaseRepository;
use App\Repository\CompetitionMatchSelectionRepository;
use App\Repository\CompetitionMatchSettingRepository;
use App\Repository\CompetitionRepository;
use App\Repository\GuessRepository;
use App\Repository\MembershipRepository;
use App\Service\Competition\CompetitionEntitlements;
use App\Service\Competition\CompetitionMatchProvider;
use App\Service\EffectiveTipDeadlineResolver;
use App\Voter\GuessVoter;
use App\Voter\GuessVotingContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Uid\Uuid;

final class GuessVoterTest extends TestCase
{
    private const string OTHER_USER_ID = '01933333-0000-7000-8000-000000000010';

    private GuessVoter $voter;
    private \DateTimeImmutable $now;

    /** @var array<string, bool> */
    private array $membershipLookup = [];

    /** @var array<string, bool> */
    private array $existingGuessLookup = [];

    /** @var array<string, Competition> */
    private array $competitionLookup = [];

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
        $this->membershipLookup = [];
        $this->existingGuessLookup = [];
        $this->competitionLookup = [];

        $memberRepo = $this->createStub(MembershipRepository::class);
        $memberRepo->method('hasActiveMembership')
            ->willReturnCallback(function (Uuid $userId, Uuid $competitionId): bool {
                return $this->membershipLookup[$userId->toRfc4122().'|'.$competitionId->toRfc4122()] ?? false;
            });

        $guessRepo = $this->createStub(GuessRepository::class);
        $guessRepo->method('findActiveByUserMatchCompetition')
            ->willReturnCallback(function (Uuid $userId, Uuid $matchId, Uuid $competitionId): ?Guess {
                $key = $userId->toRfc4122().'|'.$matchId->toRfc4122().'|'.$competitionId->toRfc4122();
                if ($this->existingGuessLookup[$key] ?? false) {
                    // For voter-logic purposes the concrete entity doesn't matter;
                    // we only need a non-null return.
                    return $this->makeDummyGuessForLookup();
                }

                return null;
            });

        $competitionRepo = $this->createStub(CompetitionRepository::class);
        $competitionRepo->method('get')
            ->willReturnCallback(function (Uuid $id): Competition {
                $competition = $this->competitionLookup[$id->toRfc4122()] ?? null;
                if (null === $competition) {
                    throw new \RuntimeException('Competition not registered in test stub: '.$id->toRfc4122());
                }

                return $competition;
            });

        $settingRepo = $this->createStub(CompetitionMatchSettingRepository::class);
        $settingRepo->method('findByCompetitionAndMatch')->willReturn(null);
        $settingRepo->method('findByCompetitionAndMatches')->willReturn([]);

        // Empty match list ⇒ no lock moment ⇒ matches lock at their own kickoff,
        // unless a test locks tips manually (Competition::lockTips).
        $matchProvider = $this->createStub(CompetitionMatchProvider::class);
        $matchProvider->method('matchesFor')->willReturn([]);

        $selectionRepo = $this->createStub(CompetitionMatchSelectionRepository::class);
        $selectionRepo->method('listByCompetition')->willReturn([]);

        $boostRepo = $this->createStub(BoostPurchaseRepository::class);
        $boostRepo->method('findActiveByUserAndCompetition')->willReturn([]);

        $resolver = new EffectiveTipDeadlineResolver(
            $matchProvider,
            $settingRepo,
            $selectionRepo,
            new CompetitionEntitlements($boostRepo),
        );

        $clock = new MockClock($this->now);

        $this->voter = new GuessVoter($memberRepo, $guessRepo, $competitionRepo, $resolver, $clock);
    }

    private function registerCompetition(Competition $competition): void
    {
        $this->competitionLookup[$competition->id->toRfc4122()] = $competition;
    }

    private function makeDummyGuessForLookup(): Guess
    {
        $user = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $matchSource = $this->makeMatchSource($user);
        $competition = $this->makeCompetition($user, $matchSource);
        $match = $this->makeMatch($matchSource);
        $g = new Guess(
            id: Uuid::fromString(AppFixtures::FIXTURE_GUESS_ID),
            user: $user,
            sportMatch: $match,
            competition: $competition,
            homeScore: 0,
            awayScore: 0,
            submittedAt: $this->now,
        );
        $g->popEvents();

        return $g;
    }

    private function markAsMember(User $user, Competition $competition): void
    {
        $this->membershipLookup[$user->id->toRfc4122().'|'.$competition->id->toRfc4122()] = true;
    }

    private function markExistingGuess(User $user, SportMatch $match, Competition $competition): void
    {
        $this->existingGuessLookup[$user->id->toRfc4122().'|'.$match->id->toRfc4122().'|'.$competition->id->toRfc4122()] = true;
    }

    private function makeUser(string $id): User
    {
        $user = new User(
            id: Uuid::fromString($id),
            email: 'u'.substr($id, -3).'@test.com',
            password: 'hash',
            nickname: 'u'.substr($id, -3),
            createdAt: $this->now,
        );
        $user->popEvents();

        return $user;
    }

    private function makeMatchSource(User $owner): MatchSource
    {
        $matchSource = new MatchSource(
            id: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
            sport: new Sport(Uuid::fromString(Sport::FOOTBALL_ID), 'football', 'Fotbal', 2, 'poločas', 'poločasy'),
            owner: $owner,
            kind: MatchSourceKind::Private,
            name: 'T',
            description: null,
            startAt: null,
            endAt: null,
            createdAt: $this->now,
        );
        $matchSource->popEvents();

        return $matchSource;
    }

    private function makeCompetition(User $owner, MatchSource $matchSource): Competition
    {
        $competition = new Competition(
            id: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
            matchSource: $matchSource,
            owner: $owner,
            name: 'G',
            description: null,
            pin: null,
            shareableLinkToken: null,
            createdAt: $this->now,
        );
        $competition->popEvents();
        $this->registerCompetition($competition);

        return $competition;
    }

    private function makeMatch(MatchSource $matchSource, bool $cancelled = false, bool $deleted = false): SportMatch
    {
        $m = new SportMatch(
            id: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            matchSource: $matchSource,
            homeTeam: 'A',
            awayTeam: 'B',
            kickoffAt: new \DateTimeImmutable('2025-06-20 18:00'),
            venue: null,
            createdAt: $this->now,
        );
        if ($cancelled) {
            $m->cancel($this->now);
        }
        if ($deleted) {
            $m->softDelete($this->now);
        }
        $m->popEvents();

        return $m;
    }

    private function token(User $user): UsernamePasswordToken
    {
        return new UsernamePasswordToken($user, 'main', $user->getRoles());
    }

    private function makeGuessOwnedBy(User $owner, SportMatch $match, Competition $competition): Guess
    {
        $g = new Guess(
            id: Uuid::fromString(AppFixtures::FIXTURE_GUESS_ID),
            user: $owner,
            sportMatch: $match,
            competition: $competition,
            homeScore: 1,
            awayScore: 0,
            submittedAt: $this->now,
        );
        $g->popEvents();

        return $g;
    }

    public function testAnonymousCannotSubmit(): void
    {
        $user = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $matchSource = $this->makeMatchSource($user);
        $competition = $this->makeCompetition($user, $matchSource);
        $match = $this->makeMatch($matchSource);

        $context = new GuessVotingContext($match, $competition->id);

        self::assertSame(-1, $this->voter->vote(new NullToken(), $context, [GuessVoter::SUBMIT]));
    }

    public function testMemberCanSubmitOnOpenMatch(): void
    {
        $user = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $matchSource = $this->makeMatchSource($user);
        $competition = $this->makeCompetition($user, $matchSource);
        $match = $this->makeMatch($matchSource);
        $this->markAsMember($user, $competition);

        $context = new GuessVotingContext($match, $competition->id);

        self::assertSame(1, $this->voter->vote($this->token($user), $context, [GuessVoter::SUBMIT]));
    }

    public function testNonMemberCannotSubmit(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $other = $this->makeUser(self::OTHER_USER_ID);
        $matchSource = $this->makeMatchSource($owner);
        $competition = $this->makeCompetition($owner, $matchSource);
        $match = $this->makeMatch($matchSource);

        $context = new GuessVotingContext($match, $competition->id);

        self::assertSame(-1, $this->voter->vote($this->token($other), $context, [GuessVoter::SUBMIT]));
    }

    public function testMemberCannotSubmitOnCancelledMatch(): void
    {
        $user = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $matchSource = $this->makeMatchSource($user);
        $competition = $this->makeCompetition($user, $matchSource);
        $match = $this->makeMatch($matchSource, cancelled: true);
        $this->markAsMember($user, $competition);

        $context = new GuessVotingContext($match, $competition->id);

        self::assertSame(-1, $this->voter->vote($this->token($user), $context, [GuessVoter::SUBMIT]));
    }

    public function testMemberCannotSubmitOnDeletedMatch(): void
    {
        $user = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $matchSource = $this->makeMatchSource($user);
        $competition = $this->makeCompetition($user, $matchSource);
        $match = $this->makeMatch($matchSource, deleted: true);
        $this->markAsMember($user, $competition);

        $context = new GuessVotingContext($match, $competition->id);

        self::assertSame(-1, $this->voter->vote($this->token($user), $context, [GuessVoter::SUBMIT]));
    }

    public function testMemberCannotSubmitWhenAlreadyGuessed(): void
    {
        $user = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $matchSource = $this->makeMatchSource($user);
        $competition = $this->makeCompetition($user, $matchSource);
        $match = $this->makeMatch($matchSource);
        $this->markAsMember($user, $competition);
        $this->markExistingGuess($user, $match, $competition);

        $context = new GuessVotingContext($match, $competition->id);

        self::assertSame(-1, $this->voter->vote($this->token($user), $context, [GuessVoter::SUBMIT]));
    }

    public function testMemberCanView(): void
    {
        $user = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $matchSource = $this->makeMatchSource($user);
        $competition = $this->makeCompetition($user, $matchSource);
        $match = $this->makeMatch($matchSource);
        $this->markAsMember($user, $competition);

        $context = new GuessVotingContext($match, $competition->id);

        self::assertSame(1, $this->voter->vote($this->token($user), $context, [GuessVoter::VIEW]));
    }

    public function testNonMemberCannotView(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $other = $this->makeUser(self::OTHER_USER_ID);
        $matchSource = $this->makeMatchSource($owner);
        $competition = $this->makeCompetition($owner, $matchSource);
        $match = $this->makeMatch($matchSource);

        $context = new GuessVotingContext($match, $competition->id);

        self::assertSame(-1, $this->voter->vote($this->token($other), $context, [GuessVoter::VIEW]));
    }

    public function testOwnerCanUpdateGuessWhileMatchOpen(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $matchSource = $this->makeMatchSource($owner);
        $competition = $this->makeCompetition($owner, $matchSource);
        $match = $this->makeMatch($matchSource);
        $guess = $this->makeGuessOwnedBy($owner, $match, $competition);

        self::assertSame(1, $this->voter->vote($this->token($owner), $guess, [GuessVoter::UPDATE]));
    }

    public function testNonOwnerCannotUpdateGuess(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $other = $this->makeUser(self::OTHER_USER_ID);
        $matchSource = $this->makeMatchSource($owner);
        $competition = $this->makeCompetition($owner, $matchSource);
        $match = $this->makeMatch($matchSource);
        $guess = $this->makeGuessOwnedBy($owner, $match, $competition);

        self::assertSame(-1, $this->voter->vote($this->token($other), $guess, [GuessVoter::UPDATE]));
    }

    public function testOwnerCannotUpdateWhenMatchCancelled(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $matchSource = $this->makeMatchSource($owner);
        $competition = $this->makeCompetition($owner, $matchSource);
        $match = $this->makeMatch($matchSource);
        $guess = $this->makeGuessOwnedBy($owner, $match, $competition);
        $match->cancel($this->now);

        self::assertSame(-1, $this->voter->vote($this->token($owner), $guess, [GuessVoter::UPDATE]));
    }

    public function testOwnerCannotUpdateVoidedGuess(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $matchSource = $this->makeMatchSource($owner);
        $competition = $this->makeCompetition($owner, $matchSource);
        $match = $this->makeMatch($matchSource);
        $guess = $this->makeGuessOwnedBy($owner, $match, $competition);
        $guess->voidGuess($this->now);

        self::assertSame(-1, $this->voter->vote($this->token($owner), $guess, [GuessVoter::UPDATE]));
    }

    public function testOwnerCannotUpdateGuessAfterManualTipLock(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $matchSource = $this->makeMatchSource($owner);
        $competition = $this->makeCompetition($owner, $matchSource);
        $match = $this->makeMatch($matchSource);
        // Tips manually locked at "now"; match kickoff still in the future. The
        // manual lock is a hard, universal freeze — managers are NOT exempt (only
        // the paid „Měnit tip" entitlement, absent here, would extend the window).
        $competition->lockTips($this->now);
        $guess = $this->makeGuessOwnedBy($owner, $match, $competition);

        self::assertSame(-1, $this->voter->vote($this->token($owner), $guess, [GuessVoter::UPDATE]));
    }

    public function testMemberCannotSubmitAfterManualTipLock(): void
    {
        $user = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $matchSource = $this->makeMatchSource($user);
        $competition = $this->makeCompetition($user, $matchSource);
        $match = $this->makeMatch($matchSource);
        $competition->lockTips($this->now);
        $this->markAsMember($user, $competition);

        $context = new GuessVotingContext($match, $competition->id);

        self::assertSame(-1, $this->voter->vote($this->token($user), $context, [GuessVoter::SUBMIT]));
    }
}
