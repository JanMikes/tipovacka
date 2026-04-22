<?php

declare(strict_types=1);

namespace App\Tests\Unit\Voter;

use App\DataFixtures\AppFixtures;
use App\Entity\Group;
use App\Entity\Guess;
use App\Entity\Sport;
use App\Entity\SportMatch;
use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\TournamentVisibility;
use App\Repository\GroupMatchSettingRepository;
use App\Repository\GroupRepository;
use App\Repository\GuessRepository;
use App\Repository\MembershipRepository;
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

    /** @var array<string, Group> */
    private array $groupLookup = [];

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
        $this->membershipLookup = [];
        $this->existingGuessLookup = [];
        $this->groupLookup = [];

        $memberRepo = $this->createStub(MembershipRepository::class);
        $memberRepo->method('hasActiveMembership')
            ->willReturnCallback(function (Uuid $userId, Uuid $groupId): bool {
                return $this->membershipLookup[$userId->toRfc4122().'|'.$groupId->toRfc4122()] ?? false;
            });

        $guessRepo = $this->createStub(GuessRepository::class);
        $guessRepo->method('findActiveByUserMatchGroup')
            ->willReturnCallback(function (Uuid $userId, Uuid $matchId, Uuid $groupId): ?Guess {
                $key = $userId->toRfc4122().'|'.$matchId->toRfc4122().'|'.$groupId->toRfc4122();
                if ($this->existingGuessLookup[$key] ?? false) {
                    // For voter-logic purposes the concrete entity doesn't matter;
                    // we only need a non-null return.
                    return $this->makeDummyGuessForLookup();
                }

                return null;
            });

        $groupRepo = $this->createStub(GroupRepository::class);
        $groupRepo->method('get')
            ->willReturnCallback(function (Uuid $id): Group {
                $group = $this->groupLookup[$id->toRfc4122()] ?? null;
                if (null === $group) {
                    throw new \RuntimeException('Group not registered in test stub: '.$id->toRfc4122());
                }

                return $group;
            });

        $settingRepo = $this->createStub(GroupMatchSettingRepository::class);
        $settingRepo->method('findByGroupAndMatch')->willReturn(null);
        $settingRepo->method('findByGroupAndMatches')->willReturn([]);

        $resolver = new EffectiveTipDeadlineResolver($settingRepo);

        $clock = new MockClock($this->now);

        $this->voter = new GuessVoter($memberRepo, $guessRepo, $groupRepo, $resolver, $clock);
    }

    private function registerGroup(Group $group): void
    {
        $this->groupLookup[$group->id->toRfc4122()] = $group;
    }

    private function makeDummyGuessForLookup(): Guess
    {
        $user = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $tournament = $this->makeTournament($user);
        $group = $this->makeGroup($user, $tournament);
        $match = $this->makeMatch($tournament);
        $g = new Guess(
            id: Uuid::fromString(AppFixtures::FIXTURE_GUESS_ID),
            user: $user,
            sportMatch: $match,
            group: $group,
            homeScore: 0,
            awayScore: 0,
            submittedAt: $this->now,
        );
        $g->popEvents();

        return $g;
    }

    private function markAsMember(User $user, Group $group): void
    {
        $this->membershipLookup[$user->id->toRfc4122().'|'.$group->id->toRfc4122()] = true;
    }

    private function markExistingGuess(User $user, SportMatch $match, Group $group): void
    {
        $this->existingGuessLookup[$user->id->toRfc4122().'|'.$match->id->toRfc4122().'|'.$group->id->toRfc4122()] = true;
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

    private function makeTournament(User $owner): Tournament
    {
        $tournament = new Tournament(
            id: Uuid::fromString(AppFixtures::PRIVATE_TOURNAMENT_ID),
            sport: new Sport(Uuid::fromString(Sport::FOOTBALL_ID), 'football', 'Fotbal'),
            owner: $owner,
            visibility: TournamentVisibility::Private,
            name: 'T',
            description: null,
            startAt: null,
            endAt: null,
            createdAt: $this->now,
        );
        $tournament->popEvents();

        return $tournament;
    }

    private function makeGroup(User $owner, Tournament $tournament): Group
    {
        $group = new Group(
            id: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            tournament: $tournament,
            owner: $owner,
            name: 'G',
            description: null,
            pin: null,
            shareableLinkToken: null,
            createdAt: $this->now,
        );
        $group->popEvents();
        $this->registerGroup($group);

        return $group;
    }

    private function makeMatch(Tournament $tournament, bool $cancelled = false, bool $deleted = false): SportMatch
    {
        $m = new SportMatch(
            id: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            tournament: $tournament,
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

    private function makeGuessOwnedBy(User $owner, SportMatch $match, Group $group): Guess
    {
        $g = new Guess(
            id: Uuid::fromString(AppFixtures::FIXTURE_GUESS_ID),
            user: $owner,
            sportMatch: $match,
            group: $group,
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
        $tournament = $this->makeTournament($user);
        $group = $this->makeGroup($user, $tournament);
        $match = $this->makeMatch($tournament);

        $context = new GuessVotingContext($match, $group->id);

        self::assertSame(-1, $this->voter->vote(new NullToken(), $context, [GuessVoter::SUBMIT]));
    }

    public function testMemberCanSubmitOnOpenMatch(): void
    {
        $user = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $tournament = $this->makeTournament($user);
        $group = $this->makeGroup($user, $tournament);
        $match = $this->makeMatch($tournament);
        $this->markAsMember($user, $group);

        $context = new GuessVotingContext($match, $group->id);

        self::assertSame(1, $this->voter->vote($this->token($user), $context, [GuessVoter::SUBMIT]));
    }

    public function testNonMemberCannotSubmit(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $other = $this->makeUser(self::OTHER_USER_ID);
        $tournament = $this->makeTournament($owner);
        $group = $this->makeGroup($owner, $tournament);
        $match = $this->makeMatch($tournament);

        $context = new GuessVotingContext($match, $group->id);

        self::assertSame(-1, $this->voter->vote($this->token($other), $context, [GuessVoter::SUBMIT]));
    }

    public function testMemberCannotSubmitOnCancelledMatch(): void
    {
        $user = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $tournament = $this->makeTournament($user);
        $group = $this->makeGroup($user, $tournament);
        $match = $this->makeMatch($tournament, cancelled: true);
        $this->markAsMember($user, $group);

        $context = new GuessVotingContext($match, $group->id);

        self::assertSame(-1, $this->voter->vote($this->token($user), $context, [GuessVoter::SUBMIT]));
    }

    public function testMemberCannotSubmitOnDeletedMatch(): void
    {
        $user = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $tournament = $this->makeTournament($user);
        $group = $this->makeGroup($user, $tournament);
        $match = $this->makeMatch($tournament, deleted: true);
        $this->markAsMember($user, $group);

        $context = new GuessVotingContext($match, $group->id);

        self::assertSame(-1, $this->voter->vote($this->token($user), $context, [GuessVoter::SUBMIT]));
    }

    public function testMemberCannotSubmitWhenAlreadyGuessed(): void
    {
        $user = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $tournament = $this->makeTournament($user);
        $group = $this->makeGroup($user, $tournament);
        $match = $this->makeMatch($tournament);
        $this->markAsMember($user, $group);
        $this->markExistingGuess($user, $match, $group);

        $context = new GuessVotingContext($match, $group->id);

        self::assertSame(-1, $this->voter->vote($this->token($user), $context, [GuessVoter::SUBMIT]));
    }

    public function testMemberCanView(): void
    {
        $user = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $tournament = $this->makeTournament($user);
        $group = $this->makeGroup($user, $tournament);
        $match = $this->makeMatch($tournament);
        $this->markAsMember($user, $group);

        $context = new GuessVotingContext($match, $group->id);

        self::assertSame(1, $this->voter->vote($this->token($user), $context, [GuessVoter::VIEW]));
    }

    public function testNonMemberCannotView(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $other = $this->makeUser(self::OTHER_USER_ID);
        $tournament = $this->makeTournament($owner);
        $group = $this->makeGroup($owner, $tournament);
        $match = $this->makeMatch($tournament);

        $context = new GuessVotingContext($match, $group->id);

        self::assertSame(-1, $this->voter->vote($this->token($other), $context, [GuessVoter::VIEW]));
    }

    public function testOwnerCanUpdateGuessWhileMatchOpen(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $tournament = $this->makeTournament($owner);
        $group = $this->makeGroup($owner, $tournament);
        $match = $this->makeMatch($tournament);
        $guess = $this->makeGuessOwnedBy($owner, $match, $group);

        self::assertSame(1, $this->voter->vote($this->token($owner), $guess, [GuessVoter::UPDATE]));
    }

    public function testNonOwnerCannotUpdateGuess(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $other = $this->makeUser(self::OTHER_USER_ID);
        $tournament = $this->makeTournament($owner);
        $group = $this->makeGroup($owner, $tournament);
        $match = $this->makeMatch($tournament);
        $guess = $this->makeGuessOwnedBy($owner, $match, $group);

        self::assertSame(-1, $this->voter->vote($this->token($other), $guess, [GuessVoter::UPDATE]));
    }

    public function testOwnerCannotUpdateWhenMatchCancelled(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $tournament = $this->makeTournament($owner);
        $group = $this->makeGroup($owner, $tournament);
        $match = $this->makeMatch($tournament);
        $guess = $this->makeGuessOwnedBy($owner, $match, $group);
        $match->cancel($this->now);

        self::assertSame(-1, $this->voter->vote($this->token($owner), $guess, [GuessVoter::UPDATE]));
    }

    public function testOwnerCannotUpdateVoidedGuess(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $tournament = $this->makeTournament($owner);
        $group = $this->makeGroup($owner, $tournament);
        $match = $this->makeMatch($tournament);
        $guess = $this->makeGuessOwnedBy($owner, $match, $group);
        $guess->voidGuess($this->now);

        self::assertSame(-1, $this->voter->vote($this->token($owner), $guess, [GuessVoter::UPDATE]));
    }

    public function testOwnerCannotUpdateGuessAfterGroupDefaultDeadline(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $tournament = $this->makeTournament($owner);
        $group = $this->makeGroup($owner, $tournament);
        $match = $this->makeMatch($tournament);
        // Group default deadline is in the past; match kickoff still in the future.
        $group->updateDetails(
            name: $group->name,
            description: $group->description,
            hideOthersTipsBeforeDeadline: false,
            tipsDeadline: new \DateTimeImmutable('2025-06-14 09:00 UTC'),
            now: $this->now,
        );
        $guess = $this->makeGuessOwnedBy($owner, $match, $group);

        self::assertSame(-1, $this->voter->vote($this->token($owner), $guess, [GuessVoter::UPDATE]));
    }

    public function testMemberCannotSubmitAfterGroupDefaultDeadline(): void
    {
        $user = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $tournament = $this->makeTournament($user);
        $group = $this->makeGroup($user, $tournament);
        $match = $this->makeMatch($tournament);
        $group->updateDetails(
            name: $group->name,
            description: $group->description,
            hideOthersTipsBeforeDeadline: false,
            tipsDeadline: new \DateTimeImmutable('2025-06-14 09:00 UTC'),
            now: $this->now,
        );
        $this->markAsMember($user, $group);

        $context = new GuessVotingContext($match, $group->id);

        self::assertSame(-1, $this->voter->vote($this->token($user), $context, [GuessVoter::SUBMIT]));
    }
}
