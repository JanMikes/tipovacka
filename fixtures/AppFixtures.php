<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Group;
use App\Entity\GroupInvitation;
use App\Entity\GroupJoinRequest;
use App\Entity\Guess;
use App\Entity\GuessEvaluation;
use App\Entity\GuessEvaluationRulePoints;
use App\Entity\Membership;
use App\Entity\Sport;
use App\Entity\SportMatch;
use App\Entity\Tournament;
use App\Entity\TournamentRuleConfiguration;
use App\Entity\User;
use App\Enum\TournamentVisibility;
use App\Enum\UserRole;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class AppFixtures extends Fixture
{
    // NOTE: tests/bootstrap.php uses `doctrine:schema:create`, NOT migrations.
    // The football Sport row seeded by the migration is therefore NOT present in the
    // test database. We seed it here too so tests (and any local `doctrine:fixtures:load`)
    // get a consistent baseline. The migration remains the source of truth for prod.

    public const string ADMIN_ID = '01933333-0000-7000-8000-000000000001';
    public const string ADMIN_EMAIL = 'admin@tipovacka.test';
    public const string ADMIN_NICKNAME = 'admin';

    public const string VERIFIED_USER_ID = '01933333-0000-7000-8000-000000000002';
    public const string VERIFIED_USER_EMAIL = 'user@tipovacka.test';
    public const string VERIFIED_USER_NICKNAME = 'tipovac';

    public const string SECOND_VERIFIED_USER_ID = '01933333-0000-7000-8000-000000000099';
    public const string SECOND_VERIFIED_USER_EMAIL = 'other@tipovacka.test';
    public const string SECOND_VERIFIED_USER_NICKNAME = 'druhy_tipovac';

    public const string UNVERIFIED_USER_ID = '01933333-0000-7000-8000-000000000003';
    public const string UNVERIFIED_USER_EMAIL = 'unverified@tipovacka.test';
    public const string UNVERIFIED_USER_NICKNAME = 'novy_uzivatel';

    public const string DELETED_USER_ID = '01933333-0000-7000-8000-000000000004';
    public const string DELETED_USER_EMAIL = 'deleted@tipovacka.test';
    public const string DELETED_USER_NICKNAME = 'smazany';

    public const string PUBLIC_TOURNAMENT_ID = '019aaaaa-0000-7000-8000-000000000001';
    public const string PUBLIC_TOURNAMENT_NAME = 'Liga mistrů 2026/27';

    public const string PRIVATE_TOURNAMENT_ID = '019aaaaa-0000-7000-8000-000000000002';
    public const string PRIVATE_TOURNAMENT_NAME = 'Chlapi u piva';

    public const string VERIFIED_GROUP_ID = '019bbbbb-0000-7000-8000-000000000001';
    public const string VERIFIED_GROUP_NAME = 'Kámoši u piva';
    public const string VERIFIED_GROUP_PIN = '12345678';
    public const string VERIFIED_GROUP_LINK_TOKEN = '019bbbbb00007000800000000000000119bbbbb0000700b1';

    public const string PUBLIC_GROUP_ID = '019bbbbb-0000-7000-8000-000000000002';
    public const string PUBLIC_GROUP_NAME = 'Admin liga';
    public const string PUBLIC_GROUP_LINK_TOKEN = '019bbbbb00007000800000000000000219bbbbb0000700b2';

    public const string VERIFIED_GROUP_OWNER_MEMBERSHIP_ID = '019bbbbb-0000-7000-8000-00000000aa01';
    public const string PUBLIC_GROUP_OWNER_MEMBERSHIP_ID = '019bbbbb-0000-7000-8000-00000000aa02';

    public const string PENDING_INVITATION_ID = '019ccccc-0000-7000-8000-000000000001';
    public const string PENDING_INVITATION_TOKEN = 'abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789';
    public const string PENDING_INVITATION_EMAIL = 'outsider@tipovacka.test';

    public const string PENDING_JOIN_REQUEST_ID = '019ccccc-0000-7000-8000-000000000002';

    public const string MATCH_SCHEDULED_ID = '019ddddd-0000-7000-8000-000000000001';
    public const string MATCH_LIVE_ID = '019ddddd-0000-7000-8000-000000000002';
    public const string MATCH_FINISHED_ID = '019ddddd-0000-7000-8000-000000000003';
    public const string MATCH_PRIVATE_SCHEDULED_ID = '019ddddd-0000-7000-8000-000000000004';

    public const string FIXTURE_GUESS_ID = '019eeeee-0000-7000-8000-000000000001';

    public const string FIXTURE_GUESS_EVALUATION_ID = '019eeeee-0000-7000-8000-000000000002';

    public const string FIXTURE_GUESS_EVAL_RULE_POINTS_ID = '019eeeee-0000-7000-8000-000000000003';

    public const string FIXTURE_TIE_RESOLUTION_ID = '019eeeee-0000-7000-8000-000000000004';

    /** Fixed UUIDs for the 8 rule configurations (4 per tournament, 2 tournaments). */
    public const string PUBLIC_RULE_EXACT_SCORE_ID = '019fffff-0000-7000-8000-000000000001';
    public const string PUBLIC_RULE_CORRECT_OUTCOME_ID = '019fffff-0000-7000-8000-000000000002';
    public const string PUBLIC_RULE_CORRECT_HOME_GOALS_ID = '019fffff-0000-7000-8000-000000000003';
    public const string PUBLIC_RULE_CORRECT_AWAY_GOALS_ID = '019fffff-0000-7000-8000-000000000004';
    public const string PRIVATE_RULE_EXACT_SCORE_ID = '019fffff-0000-7000-8000-000000000005';
    public const string PRIVATE_RULE_CORRECT_OUTCOME_ID = '019fffff-0000-7000-8000-000000000006';
    public const string PRIVATE_RULE_CORRECT_HOME_GOALS_ID = '019fffff-0000-7000-8000-000000000007';
    public const string PRIVATE_RULE_CORRECT_AWAY_GOALS_ID = '019fffff-0000-7000-8000-000000000008';

    public const string DEFAULT_PASSWORD = 'password';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        // Seed football Sport for tests/dev (migration seeds the same row in prod via SQL).
        $football = new Sport(
            id: Uuid::fromString(Sport::FOOTBALL_ID),
            code: 'football',
            name: 'Fotbal',
        );
        $manager->persist($football);

        $admin = new User(
            id: Uuid::fromString(self::ADMIN_ID),
            email: self::ADMIN_EMAIL,
            password: null,
            nickname: self::ADMIN_NICKNAME,
            createdAt: $now,
        );
        $admin->changePassword(
            $this->passwordHasher->hashPassword($admin, self::DEFAULT_PASSWORD),
            $now,
        );
        $admin->markAsVerified($now);
        $admin->changeRole(UserRole::ADMIN, $now);
        $admin->popEvents();
        $manager->persist($admin);

        $verified = new User(
            id: Uuid::fromString(self::VERIFIED_USER_ID),
            email: self::VERIFIED_USER_EMAIL,
            password: null,
            nickname: self::VERIFIED_USER_NICKNAME,
            createdAt: $now,
        );
        $verified->changePassword(
            $this->passwordHasher->hashPassword($verified, self::DEFAULT_PASSWORD),
            $now,
        );
        $verified->markAsVerified($now);
        $verified->popEvents();
        $manager->persist($verified);

        $secondVerified = new User(
            id: Uuid::fromString(self::SECOND_VERIFIED_USER_ID),
            email: self::SECOND_VERIFIED_USER_EMAIL,
            password: null,
            nickname: self::SECOND_VERIFIED_USER_NICKNAME,
            createdAt: $now,
        );
        $secondVerified->changePassword(
            $this->passwordHasher->hashPassword($secondVerified, self::DEFAULT_PASSWORD),
            $now,
        );
        $secondVerified->markAsVerified($now);
        $secondVerified->popEvents();
        $manager->persist($secondVerified);

        $unverified = new User(
            id: Uuid::fromString(self::UNVERIFIED_USER_ID),
            email: self::UNVERIFIED_USER_EMAIL,
            password: null,
            nickname: self::UNVERIFIED_USER_NICKNAME,
            createdAt: $now,
        );
        $unverified->changePassword(
            $this->passwordHasher->hashPassword($unverified, self::DEFAULT_PASSWORD),
            $now,
        );
        $unverified->popEvents();
        $manager->persist($unverified);

        $deleted = new User(
            id: Uuid::fromString(self::DELETED_USER_ID),
            email: self::DELETED_USER_EMAIL,
            password: null,
            nickname: self::DELETED_USER_NICKNAME,
            createdAt: $now,
        );
        $deleted->changePassword(
            $this->passwordHasher->hashPassword($deleted, self::DEFAULT_PASSWORD),
            $now,
        );
        $deleted->markAsVerified($now);
        $deleted->softDelete(new \DateTimeImmutable('2025-06-16 09:00:00 UTC'));
        $deleted->popEvents();
        $manager->persist($deleted);

        $public = new Tournament(
            id: Uuid::fromString(self::PUBLIC_TOURNAMENT_ID),
            sport: $football,
            owner: $admin,
            visibility: TournamentVisibility::Public,
            name: self::PUBLIC_TOURNAMENT_NAME,
            description: null,
            startAt: null,
            endAt: null,
            createdAt: $now,
        );
        $public->popEvents();
        $manager->persist($public);

        $private = new Tournament(
            id: Uuid::fromString(self::PRIVATE_TOURNAMENT_ID),
            sport: $football,
            owner: $verified,
            visibility: TournamentVisibility::Private,
            name: self::PRIVATE_TOURNAMENT_NAME,
            description: null,
            startAt: null,
            endAt: null,
            createdAt: $now,
        );
        $private->popEvents();
        $manager->persist($private);

        $verifiedGroup = new Group(
            id: Uuid::fromString(self::VERIFIED_GROUP_ID),
            tournament: $private,
            owner: $verified,
            name: self::VERIFIED_GROUP_NAME,
            description: null,
            pin: self::VERIFIED_GROUP_PIN,
            shareableLinkToken: self::VERIFIED_GROUP_LINK_TOKEN,
            createdAt: $now,
        );
        $verifiedGroup->popEvents();
        $manager->persist($verifiedGroup);

        $verifiedOwnerMembership = new Membership(
            id: Uuid::fromString(self::VERIFIED_GROUP_OWNER_MEMBERSHIP_ID),
            group: $verifiedGroup,
            user: $verified,
            joinedAt: $now,
        );
        $verifiedOwnerMembership->popEvents();
        $manager->persist($verifiedOwnerMembership);

        $publicGroup = new Group(
            id: Uuid::fromString(self::PUBLIC_GROUP_ID),
            tournament: $public,
            owner: $admin,
            name: self::PUBLIC_GROUP_NAME,
            description: null,
            pin: null,
            shareableLinkToken: self::PUBLIC_GROUP_LINK_TOKEN,
            createdAt: $now,
        );
        $publicGroup->popEvents();
        $manager->persist($publicGroup);

        $publicOwnerMembership = new Membership(
            id: Uuid::fromString(self::PUBLIC_GROUP_OWNER_MEMBERSHIP_ID),
            group: $publicGroup,
            user: $admin,
            joinedAt: $now,
        );
        $publicOwnerMembership->popEvents();
        $manager->persist($publicOwnerMembership);

        $pendingInvitation = new GroupInvitation(
            id: Uuid::fromString(self::PENDING_INVITATION_ID),
            group: $publicGroup,
            inviter: $admin,
            email: self::PENDING_INVITATION_EMAIL,
            token: self::PENDING_INVITATION_TOKEN,
            createdAt: $now,
            expiresAt: $now->modify('+7 days'),
        );
        $pendingInvitation->popEvents();
        $manager->persist($pendingInvitation);

        // Verified user is not a member of PUBLIC_GROUP, so a pending join request is valid.
        $pendingJoinRequest = new GroupJoinRequest(
            id: Uuid::fromString(self::PENDING_JOIN_REQUEST_ID),
            group: $publicGroup,
            user: $verified,
            requestedAt: $now,
        );
        $pendingJoinRequest->popEvents();
        $manager->persist($pendingJoinRequest);

        $scheduledMatch = new SportMatch(
            id: Uuid::fromString(self::MATCH_SCHEDULED_ID),
            tournament: $public,
            homeTeam: 'Sparta Praha',
            awayTeam: 'Slavia Praha',
            kickoffAt: new \DateTimeImmutable('2025-06-20 18:00:00 UTC'),
            venue: 'Generali Arena',
            createdAt: $now,
        );
        $scheduledMatch->popEvents();
        $manager->persist($scheduledMatch);

        $liveMatch = new SportMatch(
            id: Uuid::fromString(self::MATCH_LIVE_ID),
            tournament: $public,
            homeTeam: 'Viktoria Plzeň',
            awayTeam: 'Baník Ostrava',
            kickoffAt: new \DateTimeImmutable('2025-06-15 11:00:00 UTC'),
            venue: null,
            createdAt: $now,
        );
        $liveMatch->beginLive($now);
        $liveMatch->popEvents();
        $manager->persist($liveMatch);

        $finishedMatch = new SportMatch(
            id: Uuid::fromString(self::MATCH_FINISHED_ID),
            tournament: $public,
            homeTeam: 'Bohemians 1905',
            awayTeam: 'Jablonec',
            kickoffAt: new \DateTimeImmutable('2025-06-10 18:00:00 UTC'),
            venue: 'Ďolíček',
            createdAt: $now,
        );
        $finishedMatch->setFinalScore(2, 1, $now);
        $finishedMatch->popEvents();
        $manager->persist($finishedMatch);

        $privateScheduledMatch = new SportMatch(
            id: Uuid::fromString(self::MATCH_PRIVATE_SCHEDULED_ID),
            tournament: $private,
            homeTeam: 'Tygři',
            awayTeam: 'Lvi',
            kickoffAt: new \DateTimeImmutable('2025-06-20 19:00:00 UTC'),
            venue: null,
            createdAt: $now,
        );
        $privateScheduledMatch->popEvents();
        $manager->persist($privateScheduledMatch);

        // Admin is a member of PUBLIC_GROUP (owner) and tipped 3:0 on the finished
        // MATCH_FINISHED (actual 2:1). Useful baseline for Stage 7 evaluation.
        $adminGuess = new Guess(
            id: Uuid::fromString(self::FIXTURE_GUESS_ID),
            user: $admin,
            sportMatch: $finishedMatch,
            group: $publicGroup,
            homeScore: 3,
            awayScore: 0,
            submittedAt: $now,
        );
        $adminGuess->popEvents();
        $manager->persist($adminGuess);

        // Stage 7: rule configurations for both tournaments (defaults).
        foreach ([
            [self::PUBLIC_RULE_EXACT_SCORE_ID, $public, 'exact_score', 5],
            [self::PUBLIC_RULE_CORRECT_OUTCOME_ID, $public, 'correct_outcome', 3],
            [self::PUBLIC_RULE_CORRECT_HOME_GOALS_ID, $public, 'correct_home_goals', 1],
            [self::PUBLIC_RULE_CORRECT_AWAY_GOALS_ID, $public, 'correct_away_goals', 1],
            [self::PRIVATE_RULE_EXACT_SCORE_ID, $private, 'exact_score', 5],
            [self::PRIVATE_RULE_CORRECT_OUTCOME_ID, $private, 'correct_outcome', 3],
            [self::PRIVATE_RULE_CORRECT_HOME_GOALS_ID, $private, 'correct_home_goals', 1],
            [self::PRIVATE_RULE_CORRECT_AWAY_GOALS_ID, $private, 'correct_away_goals', 1],
        ] as $row) {
            [$id, $tournament, $identifier, $points] = $row;
            $configuration = new TournamentRuleConfiguration(
                id: Uuid::fromString($id),
                tournament: $tournament,
                ruleIdentifier: $identifier,
                enabled: true,
                points: $points,
                now: $now,
            );
            $manager->persist($configuration);
        }

        // Stage 7: seeded evaluation for admin's guess (3:0 vs actual 2:1).
        // Expected: correct_outcome hits (both home wins) → 3 points total.
        $evaluation = new GuessEvaluation(
            id: Uuid::fromString(self::FIXTURE_GUESS_EVALUATION_ID),
            guess: $adminGuess,
            evaluatedAt: $now,
        );
        $evaluation->addRulePoints(new GuessEvaluationRulePoints(
            id: Uuid::fromString(self::FIXTURE_GUESS_EVAL_RULE_POINTS_ID),
            evaluation: $evaluation,
            ruleIdentifier: 'correct_outcome',
            points: 3,
        ));
        $manager->persist($evaluation);

        $manager->flush();
    }
}
