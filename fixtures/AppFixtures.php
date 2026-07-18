<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Competition;
use App\Entity\CompetitionInvitation;
use App\Entity\CompetitionJoinRequest;
use App\Entity\CompetitionMatchSelection;
use App\Entity\CompetitionRuleConfiguration;
use App\Entity\Guess;
use App\Entity\GuessEvaluation;
use App\Entity\GuessEvaluationRulePoints;
use App\Entity\MatchSource;
use App\Entity\Membership;
use App\Entity\Sport;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\CompetitionMatchSelectionMode;
use App\Enum\MatchSourceKind;
use App\Enum\UserRole;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class AppFixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['test', 'dev'];
    }


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

    public const string ANONYMOUS_USER_ID = '01933333-0000-7000-8000-000000000005';
    public const string ANONYMOUS_USER_FIRST_NAME = 'František';
    public const string ANONYMOUS_USER_LAST_NAME = 'Novák';

    public const string PUBLIC_SOURCE_ID = '019aaaaa-0000-7000-8000-000000000001';
    public const string PUBLIC_SOURCE_NAME = 'Liga mistrů 2026/27';

    public const string PRIVATE_SOURCE_ID = '019aaaaa-0000-7000-8000-000000000002';
    public const string PRIVATE_SOURCE_NAME = 'Chlapi u piva';

    public const string VERIFIED_COMPETITION_ID = '019bbbbb-0000-7000-8000-000000000001';
    public const string VERIFIED_COMPETITION_NAME = 'Kámoši u piva';
    public const string VERIFIED_COMPETITION_PIN = '12345678';
    public const string VERIFIED_COMPETITION_LINK_TOKEN = '019bbbbb00007000800000000000000119bbbbb0000700b1';

    public const string PUBLIC_COMPETITION_ID = '019bbbbb-0000-7000-8000-000000000002';
    public const string PUBLIC_COMPETITION_NAME = 'Admin liga';
    public const string PUBLIC_COMPETITION_LINK_TOKEN = '019bbbbb00007000800000000000000219bbbbb0000700b2';

    /**
     * Subset-mode competition over the PUBLIC (curated) source, owned by the
     * SECOND verified user. Selected matches: MATCH_SCHEDULED + MATCH_FINISHED.
     * NOT selected: MATCH_LIVE and MATCH_PLAYOFF (⇒ MatchNotInCompetition).
     */
    public const string SUBSET_COMPETITION_ID = '019bbbbb-0000-7000-8000-000000000033';
    public const string SUBSET_COMPETITION_NAME = 'Vybrané zápasy party';
    public const string SUBSET_COMPETITION_LINK_TOKEN = '019bbbbb00007000800000000000000319bbbbb0000700b3';
    public const string SUBSET_SELECTION_SCHEDULED_ID = '019bbbbb-0000-7000-8000-00000000bb01';
    public const string SUBSET_SELECTION_FINISHED_ID = '019bbbbb-0000-7000-8000-00000000bb02';

    public const string VERIFIED_COMPETITION_OWNER_MEMBERSHIP_ID = '019bbbbb-0000-7000-8000-00000000aa01';
    public const string PUBLIC_COMPETITION_OWNER_MEMBERSHIP_ID = '019bbbbb-0000-7000-8000-00000000aa02';
    public const string ANONYMOUS_MEMBERSHIP_ID = '019bbbbb-0000-7000-8000-00000000aa03';
    public const string SUBSET_COMPETITION_OWNER_MEMBERSHIP_ID = '019bbbbb-0000-7000-8000-00000000aa04';

    public const string PENDING_INVITATION_ID = '019ccccc-0000-7000-8000-000000000001';
    public const string PENDING_INVITATION_TOKEN = 'abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789';
    public const string PENDING_INVITATION_EMAIL = 'outsider@tipovacka.test';

    public const string PENDING_JOIN_REQUEST_ID = '019ccccc-0000-7000-8000-000000000002';

    public const string MATCH_SCHEDULED_ID = '019ddddd-0000-7000-8000-000000000001';
    /** Scheduled playoff match in the PUBLIC (curated) source — kickoff 2025-06-22 18:00 UTC. */
    public const string MATCH_PLAYOFF_ID = '019ddddd-0000-7000-8000-000000000005';
    public const string MATCH_LIVE_ID = '019ddddd-0000-7000-8000-000000000002';
    public const string MATCH_FINISHED_ID = '019ddddd-0000-7000-8000-000000000003';
    public const string MATCH_PRIVATE_SCHEDULED_ID = '019ddddd-0000-7000-8000-000000000004';

    public const string FIXTURE_GUESS_ID = '019eeeee-0000-7000-8000-000000000001';

    public const string FIXTURE_GUESS_EVALUATION_ID = '019eeeee-0000-7000-8000-000000000002';

    public const string FIXTURE_GUESS_EVAL_RULE_POINTS_ID = '019eeeee-0000-7000-8000-000000000003';

    public const string FIXTURE_TIE_RESOLUTION_ID = '019eeeee-0000-7000-8000-000000000004';

    /** Fixed UUIDs for the 12 rule configurations (4 per competition, 3 competitions). */
    public const string VERIFIED_COMPETITION_RULE_EXACT_SCORE_ID = '019fffff-0000-7000-8000-000000000001';
    public const string VERIFIED_COMPETITION_RULE_CORRECT_OUTCOME_ID = '019fffff-0000-7000-8000-000000000002';
    public const string VERIFIED_COMPETITION_RULE_CORRECT_HOME_GOALS_ID = '019fffff-0000-7000-8000-000000000003';
    public const string VERIFIED_COMPETITION_RULE_CORRECT_AWAY_GOALS_ID = '019fffff-0000-7000-8000-000000000004';
    public const string PUBLIC_COMPETITION_RULE_EXACT_SCORE_ID = '019fffff-0000-7000-8000-000000000005';
    public const string PUBLIC_COMPETITION_RULE_CORRECT_OUTCOME_ID = '019fffff-0000-7000-8000-000000000006';
    public const string PUBLIC_COMPETITION_RULE_CORRECT_HOME_GOALS_ID = '019fffff-0000-7000-8000-000000000007';
    public const string PUBLIC_COMPETITION_RULE_CORRECT_AWAY_GOALS_ID = '019fffff-0000-7000-8000-000000000008';
    public const string SUBSET_COMPETITION_RULE_EXACT_SCORE_ID = '019fffff-0000-7000-8000-000000000009';
    public const string SUBSET_COMPETITION_RULE_CORRECT_OUTCOME_ID = '019fffff-0000-7000-8000-000000000010';
    public const string SUBSET_COMPETITION_RULE_CORRECT_HOME_GOALS_ID = '019fffff-0000-7000-8000-000000000011';
    public const string SUBSET_COMPETITION_RULE_CORRECT_AWAY_GOALS_ID = '019fffff-0000-7000-8000-000000000012';

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

        $public = new MatchSource(
            id: Uuid::fromString(self::PUBLIC_SOURCE_ID),
            sport: $football,
            owner: $admin,
            kind: MatchSourceKind::Curated,
            name: self::PUBLIC_SOURCE_NAME,
            description: null,
            startAt: null,
            endAt: null,
            createdAt: $now,
        );
        $public->popEvents();
        $manager->persist($public);

        $private = new MatchSource(
            id: Uuid::fromString(self::PRIVATE_SOURCE_ID),
            sport: $football,
            owner: $verified,
            kind: MatchSourceKind::Private,
            name: self::PRIVATE_SOURCE_NAME,
            description: null,
            startAt: null,
            endAt: null,
            createdAt: $now,
        );
        $private->popEvents();
        $manager->persist($private);

        $verifiedCompetition = new Competition(
            id: Uuid::fromString(self::VERIFIED_COMPETITION_ID),
            matchSource: $private,
            owner: $verified,
            name: self::VERIFIED_COMPETITION_NAME,
            description: null,
            pin: self::VERIFIED_COMPETITION_PIN,
            shareableLinkToken: self::VERIFIED_COMPETITION_LINK_TOKEN,
            createdAt: $now,
        );
        $verifiedCompetition->popEvents();
        $manager->persist($verifiedCompetition);

        $verifiedOwnerMembership = new Membership(
            id: Uuid::fromString(self::VERIFIED_COMPETITION_OWNER_MEMBERSHIP_ID),
            competition: $verifiedCompetition,
            user: $verified,
            joinedAt: $now,
        );
        $verifiedOwnerMembership->popEvents();
        $manager->persist($verifiedOwnerMembership);

        // Anonymous member added by the verified user (competition owner) so managers
        // can practise submitting guesses on someone else's behalf.
        $anonymous = new User(
            id: Uuid::fromString(self::ANONYMOUS_USER_ID),
            email: null,
            password: null,
            nickname: null,
            createdAt: $now,
        );
        $anonymous->updateProfile(
            firstName: self::ANONYMOUS_USER_FIRST_NAME,
            lastName: self::ANONYMOUS_USER_LAST_NAME,
            phone: null,
            now: $now,
        );
        $anonymous->popEvents();
        $manager->persist($anonymous);

        $anonymousMembership = new Membership(
            id: Uuid::fromString(self::ANONYMOUS_MEMBERSHIP_ID),
            competition: $verifiedCompetition,
            user: $anonymous,
            joinedAt: $now,
        );
        $anonymousMembership->popEvents();
        $manager->persist($anonymousMembership);

        $publicCompetition = new Competition(
            id: Uuid::fromString(self::PUBLIC_COMPETITION_ID),
            matchSource: $public,
            owner: $admin,
            name: self::PUBLIC_COMPETITION_NAME,
            description: null,
            pin: null,
            shareableLinkToken: self::PUBLIC_COMPETITION_LINK_TOKEN,
            createdAt: $now,
        );
        $publicCompetition->popEvents();
        $manager->persist($publicCompetition);

        $publicOwnerMembership = new Membership(
            id: Uuid::fromString(self::PUBLIC_COMPETITION_OWNER_MEMBERSHIP_ID),
            competition: $publicCompetition,
            user: $admin,
            joinedAt: $now,
        );
        $publicOwnerMembership->popEvents();
        $manager->persist($publicOwnerMembership);

        $pendingInvitation = new CompetitionInvitation(
            id: Uuid::fromString(self::PENDING_INVITATION_ID),
            competition: $publicCompetition,
            inviter: $admin,
            email: self::PENDING_INVITATION_EMAIL,
            token: self::PENDING_INVITATION_TOKEN,
            createdAt: $now,
            expiresAt: $now->modify('+7 days'),
        );
        $pendingInvitation->popEvents();
        $manager->persist($pendingInvitation);

        // Verified user is not a member of PUBLIC_COMPETITION, so a pending join request is valid.
        $pendingJoinRequest = new CompetitionJoinRequest(
            id: Uuid::fromString(self::PENDING_JOIN_REQUEST_ID),
            competition: $publicCompetition,
            user: $verified,
            requestedAt: $now,
        );
        $pendingJoinRequest->popEvents();
        $manager->persist($pendingJoinRequest);

        $playoffMatch = new SportMatch(
            id: Uuid::fromString(self::MATCH_PLAYOFF_ID),
            matchSource: $public,
            homeTeam: 'Real Madrid',
            awayTeam: 'Barcelona',
            kickoffAt: new \DateTimeImmutable('2025-06-22 18:00:00 UTC'),
            venue: null,
            createdAt: $now,
            round: 'Playoff',
            isPlayoff: true,
        );
        $playoffMatch->popEvents();
        $manager->persist($playoffMatch);

        $scheduledMatch = new SportMatch(
            id: Uuid::fromString(self::MATCH_SCHEDULED_ID),
            matchSource: $public,
            homeTeam: 'Sparta Praha',
            awayTeam: 'Slavia Praha',
            kickoffAt: new \DateTimeImmutable('2025-06-20 18:00:00 UTC'),
            venue: 'Generali Arena',
            createdAt: $now,
            round: 'Čtvrtfinále',
        );
        $scheduledMatch->popEvents();
        $manager->persist($scheduledMatch);

        $liveMatch = new SportMatch(
            id: Uuid::fromString(self::MATCH_LIVE_ID),
            matchSource: $public,
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
            matchSource: $public,
            homeTeam: 'Bohemians 1905',
            awayTeam: 'Jablonec',
            kickoffAt: new \DateTimeImmutable('2025-06-10 18:00:00 UTC'),
            venue: 'Ďolíček',
            createdAt: $now,
            round: 'Základní skupina',
        );
        $finishedMatch->setFinalScore(2, 1, $now);
        $finishedMatch->popEvents();
        $manager->persist($finishedMatch);

        $privateScheduledMatch = new SportMatch(
            id: Uuid::fromString(self::MATCH_PRIVATE_SCHEDULED_ID),
            matchSource: $private,
            homeTeam: 'Tygři',
            awayTeam: 'Lvi',
            kickoffAt: new \DateTimeImmutable('2025-06-20 19:00:00 UTC'),
            venue: null,
            createdAt: $now,
        );
        $privateScheduledMatch->popEvents();
        $manager->persist($privateScheduledMatch);

        // Subset-mode competition (owner: second verified user) over the public source.
        // Only MATCH_SCHEDULED + MATCH_FINISHED are selected.
        $subsetCompetition = new Competition(
            id: Uuid::fromString(self::SUBSET_COMPETITION_ID),
            matchSource: $public,
            owner: $secondVerified,
            name: self::SUBSET_COMPETITION_NAME,
            description: null,
            pin: null,
            shareableLinkToken: self::SUBSET_COMPETITION_LINK_TOKEN,
            createdAt: $now,
            selectionMode: CompetitionMatchSelectionMode::Subset,
        );
        $subsetCompetition->popEvents();
        $manager->persist($subsetCompetition);

        $subsetOwnerMembership = new Membership(
            id: Uuid::fromString(self::SUBSET_COMPETITION_OWNER_MEMBERSHIP_ID),
            competition: $subsetCompetition,
            user: $secondVerified,
            joinedAt: $now,
        );
        $subsetOwnerMembership->popEvents();
        $manager->persist($subsetOwnerMembership);

        $manager->persist(new CompetitionMatchSelection(
            id: Uuid::fromString(self::SUBSET_SELECTION_SCHEDULED_ID),
            competition: $subsetCompetition,
            sportMatch: $scheduledMatch,
            addedAt: $now,
        ));
        $manager->persist(new CompetitionMatchSelection(
            id: Uuid::fromString(self::SUBSET_SELECTION_FINISHED_ID),
            competition: $subsetCompetition,
            sportMatch: $finishedMatch,
            addedAt: $now,
        ));

        // Admin is a member of PUBLIC_COMPETITION (owner) and tipped 3:0 on the finished
        // MATCH_FINISHED (actual 2:1). Useful baseline for Stage 7 evaluation.
        $adminGuess = new Guess(
            id: Uuid::fromString(self::FIXTURE_GUESS_ID),
            user: $admin,
            sportMatch: $finishedMatch,
            competition: $publicCompetition,
            homeScore: 3,
            awayScore: 0,
            submittedAt: $now,
        );
        $adminGuess->popEvents();
        $manager->persist($adminGuess);

        // S04: rule configurations per competition (defaults, all enabled).
        foreach ([
            [self::VERIFIED_COMPETITION_RULE_EXACT_SCORE_ID, $verifiedCompetition, 'exact_score', 5],
            [self::VERIFIED_COMPETITION_RULE_CORRECT_OUTCOME_ID, $verifiedCompetition, 'correct_outcome', 3],
            [self::VERIFIED_COMPETITION_RULE_CORRECT_HOME_GOALS_ID, $verifiedCompetition, 'correct_home_goals', 1],
            [self::VERIFIED_COMPETITION_RULE_CORRECT_AWAY_GOALS_ID, $verifiedCompetition, 'correct_away_goals', 1],
            [self::PUBLIC_COMPETITION_RULE_EXACT_SCORE_ID, $publicCompetition, 'exact_score', 5],
            [self::PUBLIC_COMPETITION_RULE_CORRECT_OUTCOME_ID, $publicCompetition, 'correct_outcome', 3],
            [self::PUBLIC_COMPETITION_RULE_CORRECT_HOME_GOALS_ID, $publicCompetition, 'correct_home_goals', 1],
            [self::PUBLIC_COMPETITION_RULE_CORRECT_AWAY_GOALS_ID, $publicCompetition, 'correct_away_goals', 1],
            [self::SUBSET_COMPETITION_RULE_EXACT_SCORE_ID, $subsetCompetition, 'exact_score', 5],
            [self::SUBSET_COMPETITION_RULE_CORRECT_OUTCOME_ID, $subsetCompetition, 'correct_outcome', 3],
            [self::SUBSET_COMPETITION_RULE_CORRECT_HOME_GOALS_ID, $subsetCompetition, 'correct_home_goals', 1],
            [self::SUBSET_COMPETITION_RULE_CORRECT_AWAY_GOALS_ID, $subsetCompetition, 'correct_away_goals', 1],
        ] as $row) {
            [$id, $competition, $identifier, $points] = $row;
            $configuration = new CompetitionRuleConfiguration(
                id: Uuid::fromString($id),
                competition: $competition,
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
