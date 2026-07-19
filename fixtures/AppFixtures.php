<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\BoostPurchase;
use App\Entity\Competition;
use App\Entity\CompetitionInvitation;
use App\Entity\CompetitionMatchSelection;
use App\Entity\CompetitionPremiumCharge;
use App\Entity\CompetitionRuleConfiguration;
use App\Entity\Guess;
use App\Entity\GuessEvaluation;
use App\Entity\GuessEvaluationRulePoints;
use App\Entity\GuessScorer;
use App\Entity\LeaderboardSnapshot;
use App\Entity\MatchEvent;
use App\Entity\MatchSource;
use App\Entity\Membership;
use App\Entity\Notification;
use App\Entity\NotificationPreference;
use App\Entity\Player;
use App\Entity\Sport;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\BoostType;
use App\Enum\CompetitionMatchSelectionMode;
use App\Enum\CompetitionMonetization;
use App\Enum\MatchEventType;
use App\Enum\MatchSide;
use App\Enum\MatchSourceKind;
use App\Enum\NotificationType;
use App\Enum\UserRole;
use App\Value\PeriodScores;
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

    /**
     * S09 global (publicly discoverable) competitions over the PUBLIC curated
     * source, owned by ADMIN. GLOBAL_COMPETITION charges an entry fee; the FREE
     * one is fee 0. Neither has any non-owner member in the baseline, so both are
     * still fee-unlocked and joinable by VERIFIED_USER / SECOND_VERIFIED_USER.
     */
    public const string GLOBAL_COMPETITION_ID = '019bbbbb-0000-7000-8000-000000000044';
    public const string GLOBAL_COMPETITION_NAME = 'Globální tipovačka LM';
    public const int GLOBAL_COMPETITION_ENTRY_FEE = 50;
    public const string GLOBAL_COMPETITION_OWNER_MEMBERSHIP_ID = '019bbbbb-0000-7000-8000-00000000aa05';

    public const string FREE_GLOBAL_COMPETITION_ID = '019bbbbb-0000-7000-8000-000000000045';
    public const string FREE_GLOBAL_COMPETITION_NAME = 'Globální tipovačka zdarma';
    public const string FREE_GLOBAL_COMPETITION_OWNER_MEMBERSHIP_ID = '019bbbbb-0000-7000-8000-00000000aa06';

    /**
     * S10 premium competition (monetization=premium, NOT global) over the PUBLIC
     * curated source, owned by ADMIN (the paying manager). SECOND_VERIFIED_USER is
     * a non-owner member whose join is represented as an already-Charged
     * {@see CompetitionPremiumCharge} row (PREMIUM_CHARGE_ID). Its start moment is
     * MATCH_FINISHED's kickoff (2025-06-10, in the past vs the fixed clock), so the
     * reconcile sweep treats it as started. No wallet/ledger seeded (would break the
     * whole-table credit asserts) — tests grant the owner credits in-test.
     */
    public const string PREMIUM_COMPETITION_ID = '019bbbbb-0000-7000-8000-000000000055';
    public const string PREMIUM_COMPETITION_NAME = 'Prémiová firemní liga';
    public const string PREMIUM_COMPETITION_LINK_TOKEN = '019bbbbb00007000800000000000000519bbbbb0000700b5';
    public const string PREMIUM_COMPETITION_OWNER_MEMBERSHIP_ID = '019bbbbb-0000-7000-8000-00000000aa07';
    public const string PREMIUM_COMPETITION_MEMBER_MEMBERSHIP_ID = '019bbbbb-0000-7000-8000-00000000aa08';
    public const string PREMIUM_CHARGE_ID = '019bbbbb-0000-7000-8000-0000000000d1';
    public const string PREMIUM_COMPETITION_RULE_EXACT_SCORE_ID = '019fffff-0000-7000-8000-000000000016';
    public const string PREMIUM_COMPETITION_RULE_CORRECT_OUTCOME_ID = '019fffff-0000-7000-8000-000000000017';
    public const string PREMIUM_COMPETITION_RULE_CORRECT_HOME_GOALS_ID = '019fffff-0000-7000-8000-000000000018';
    public const string PREMIUM_COMPETITION_RULE_CORRECT_AWAY_GOALS_ID = '019fffff-0000-7000-8000-000000000019';

    /**
     * S10 boosts competition (monetization=boosts, NOT global) over the PUBLIC
     * curated source, owned by ADMIN. SECOND_VERIFIED_USER is the single non-owner
     * member and holds one active OthersTips {@see BoostPurchase}
     * (BOOST_PURCHASE_OTHERS_TIPS_ID) — the entitled viewer. VERIFIED_USER is
     * deliberately NOT a member here (it stays the „single competition" user other
     * count tests rely on); visibility tests join a second, non-entitled member on
     * the fly. No wallet/ledger is seeded for the purchase (that would break the
     * whole-table credit asserts) — the row alone drives the entitlement, exactly
     * like the premium-charge fixture above.
     */
    public const string BOOSTS_COMPETITION_ID = '019bbbbb-0000-7000-8000-000000000066';
    public const string BOOSTS_COMPETITION_NAME = 'Příspěvková firemní liga';
    public const string BOOSTS_COMPETITION_LINK_TOKEN = '019bbbbb00007000800000000000000619bbbbb0000700b6';
    public const string BOOSTS_COMPETITION_OWNER_MEMBERSHIP_ID = '019bbbbb-0000-7000-8000-00000000aa09';
    public const string BOOSTS_COMPETITION_MEMBER_MEMBERSHIP_ID = '019bbbbb-0000-7000-8000-00000000aa0a';
    public const string BOOST_PURCHASE_OTHERS_TIPS_ID = '019bbbbb-0000-7000-8000-0000000000e1';
    public const string BOOSTS_COMPETITION_RULE_EXACT_SCORE_ID = '019fffff-0000-7000-8000-00000000001a';
    public const string BOOSTS_COMPETITION_RULE_CORRECT_OUTCOME_ID = '019fffff-0000-7000-8000-00000000001b';
    public const string BOOSTS_COMPETITION_RULE_CORRECT_HOME_GOALS_ID = '019fffff-0000-7000-8000-00000000001c';
    public const string BOOSTS_COMPETITION_RULE_CORRECT_AWAY_GOALS_ID = '019fffff-0000-7000-8000-00000000001d';

    public const string PENDING_INVITATION_ID = '019ccccc-0000-7000-8000-000000000001';
    public const string PENDING_INVITATION_TOKEN = 'abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789';
    public const string PENDING_INVITATION_EMAIL = 'outsider@tipovacka.test';

    /** Roster pool of the PUBLIC (curated) source — created for MATCH_FINISHED events. */
    public const string PLAYER_HOME_SCORER_ONE_ID = '019ddddd-0000-7000-8000-0000000000b1';
    public const string PLAYER_HOME_SCORER_ONE_NAME = 'Jan Novák';
    public const string PLAYER_HOME_SCORER_TWO_ID = '019ddddd-0000-7000-8000-0000000000b2';
    public const string PLAYER_HOME_SCORER_TWO_NAME = 'Petr Svoboda';
    public const string PLAYER_AWAY_BOOKED_ID = '019ddddd-0000-7000-8000-0000000000b3';
    public const string PLAYER_AWAY_BOOKED_NAME = 'Marek Doležal';

    /** Timeline of MATCH_FINISHED (2:1): two home goals recorded + one away yellow card. */
    public const string MATCH_EVENT_GOAL_ONE_ID = '019ddddd-0000-7000-8000-0000000000c1';
    public const string MATCH_EVENT_GOAL_TWO_ID = '019ddddd-0000-7000-8000-0000000000c2';
    public const string MATCH_EVENT_YELLOW_CARD_ID = '019ddddd-0000-7000-8000-0000000000c3';

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

    /**
     * S06: SUBSET_COMPETITION is the feature-on example — scorer_hit + both
     * period rules enabled at default points (overtime_exact stays disabled;
     * tests enable it via UpdateCompetitionRuleConfiguration when needed).
     */
    public const string SUBSET_COMPETITION_RULE_SCORER_HIT_ID = '019fffff-0000-7000-8000-000000000013';
    public const string SUBSET_COMPETITION_RULE_PERIOD_EXACT_ID = '019fffff-0000-7000-8000-000000000014';
    public const string SUBSET_COMPETITION_RULE_PERIOD_TENDENCY_ID = '019fffff-0000-7000-8000-000000000015';

    /**
     * S06: SECOND_VERIFIED_USER's guess in SUBSET_COMPETITION on MATCH_FINISHED
     * (2:1, periods [[1,0],[1,1]]) with one scorer tip (PLAYER_HOME_SCORER_ONE —
     * a correct scorer). Seeded WITHOUT an evaluation: evaluation tests trigger
     * it themselves (score correction / recalculation).
     */
    public const string SUBSET_GUESS_ID = '019eeeee-0000-7000-8000-000000000005';
    public const string SUBSET_GUESS_SCORER_ID = '019eeeee-0000-7000-8000-000000000006';

    /**
     * Notifications + a preference override for VERIFIED_USER (one unread, one
     * read). Own `019a0000-…` block, clear of the predictable identity pool.
     */
    public const string NOTIFICATION_UNREAD_ID = '019a0000-0000-7000-8000-0000000000f1';
    public const string NOTIFICATION_READ_ID = '019a0000-0000-7000-8000-0000000000f2';
    public const string NOTIFICATION_PREFERENCE_ID = '019a0000-0000-7000-8000-0000000000f3';

    // S12 leaderboard snapshots (own 019a1111-… block, clear of the identity pool).
    // VERIFIED_COMPETITION has no finished matches ⇒ its live board is all-zeros
    // (both members tied rank 1), so the seeded snapshots stay all-zeros too — a
    // coherent history, never a fabricated standing. VERIFIED_USER (owner) is on
    // both days; ANONYMOUS_USER joined at $now (2025-06-15), so it appears only on
    // today's snapshot ⇒ „nový v žebříčku" today. The rich delta demo with real
    // movement lives in DevFixtures (an already-evaluated competition).
    public const string SNAPSHOT_YESTERDAY_VERIFIED_ID = '019a1111-0000-7000-8000-000000000001';
    public const string SNAPSHOT_TODAY_VERIFIED_ID = '019a1111-0000-7000-8000-000000000003';
    public const string SNAPSHOT_TODAY_ANONYMOUS_ID = '019a1111-0000-7000-8000-000000000004';

    public const string DEFAULT_PASSWORD = 'password';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        // Seed both Sports for tests/dev (migrations seed the same rows in prod via SQL).
        $football = new Sport(
            id: Uuid::fromString(Sport::FOOTBALL_ID),
            code: 'football',
            name: 'Fotbal',
            periodCount: 2,
            periodLabelSingular: 'poločas',
            periodLabelPlural: 'poločasy',
        );
        $manager->persist($football);

        $hockey = new Sport(
            id: Uuid::fromString(Sport::HOCKEY_ID),
            code: 'hockey',
            name: 'Hokej',
            periodCount: 3,
            periodLabelSingular: 'třetina',
            periodLabelPlural: 'třetiny',
        );
        $manager->persist($hockey);

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

        // S09: global (publicly discoverable) competitions over the PUBLIC curated
        // source, owned by ADMIN. Paid one charges GLOBAL_COMPETITION_ENTRY_FEE; the
        // other is free. Owner is the sole member of each ⇒ fee still unlocked.
        $globalCompetition = new Competition(
            id: Uuid::fromString(self::GLOBAL_COMPETITION_ID),
            matchSource: $public,
            owner: $admin,
            name: self::GLOBAL_COMPETITION_NAME,
            description: null,
            pin: null,
            shareableLinkToken: null,
            createdAt: $now,
            monetization: CompetitionMonetization::None,
            isGlobal: true,
            entryFeeCredits: self::GLOBAL_COMPETITION_ENTRY_FEE,
        );
        $globalCompetition->popEvents();
        $manager->persist($globalCompetition);

        $globalOwnerMembership = new Membership(
            id: Uuid::fromString(self::GLOBAL_COMPETITION_OWNER_MEMBERSHIP_ID),
            competition: $globalCompetition,
            user: $admin,
            joinedAt: $now,
        );
        $globalOwnerMembership->popEvents();
        $manager->persist($globalOwnerMembership);

        $freeGlobalCompetition = new Competition(
            id: Uuid::fromString(self::FREE_GLOBAL_COMPETITION_ID),
            matchSource: $public,
            owner: $admin,
            name: self::FREE_GLOBAL_COMPETITION_NAME,
            description: null,
            pin: null,
            shareableLinkToken: null,
            createdAt: $now,
            monetization: CompetitionMonetization::None,
            isGlobal: true,
            entryFeeCredits: 0,
        );
        $freeGlobalCompetition->popEvents();
        $manager->persist($freeGlobalCompetition);

        $freeGlobalOwnerMembership = new Membership(
            id: Uuid::fromString(self::FREE_GLOBAL_COMPETITION_OWNER_MEMBERSHIP_ID),
            competition: $freeGlobalCompetition,
            user: $admin,
            joinedAt: $now,
        );
        $freeGlobalOwnerMembership->popEvents();
        $manager->persist($freeGlobalOwnerMembership);

        // S10: premium (NOT global) competition over the PUBLIC source, owned by
        // ADMIN. SECOND_VERIFIED_USER is a non-owner member with an already-Charged
        // premium charge. A shareable link lets tests add further joiners.
        $premiumCompetition = new Competition(
            id: Uuid::fromString(self::PREMIUM_COMPETITION_ID),
            matchSource: $public,
            owner: $admin,
            name: self::PREMIUM_COMPETITION_NAME,
            description: null,
            pin: null,
            shareableLinkToken: self::PREMIUM_COMPETITION_LINK_TOKEN,
            createdAt: $now,
            monetization: CompetitionMonetization::Premium,
        );
        $premiumCompetition->popEvents();
        $manager->persist($premiumCompetition);

        $premiumOwnerMembership = new Membership(
            id: Uuid::fromString(self::PREMIUM_COMPETITION_OWNER_MEMBERSHIP_ID),
            competition: $premiumCompetition,
            user: $admin,
            joinedAt: $now,
        );
        $premiumOwnerMembership->popEvents();
        $manager->persist($premiumOwnerMembership);

        $premiumMemberMembership = new Membership(
            id: Uuid::fromString(self::PREMIUM_COMPETITION_MEMBER_MEMBERSHIP_ID),
            competition: $premiumCompetition,
            user: $secondVerified,
            joinedAt: $now,
        );
        $premiumMemberMembership->popEvents();
        $manager->persist($premiumMemberMembership);

        $premiumCharge = new CompetitionPremiumCharge(
            id: Uuid::fromString(self::PREMIUM_CHARGE_ID),
            competition: $premiumCompetition,
            member: $secondVerified,
            amount: 10,
            createdAt: $now,
        );
        $premiumCharge->markCharged($now);
        $premiumCharge->popEvents();
        $manager->persist($premiumCharge);

        // S10: boosts (NOT global) competition over the PUBLIC source, owned by
        // ADMIN. SECOND_VERIFIED_USER is the single non-owner member and holds an
        // active OthersTips boost (sees others' tips + distribution before the
        // deadline). No wallet/ledger seeded.
        $boostsCompetition = new Competition(
            id: Uuid::fromString(self::BOOSTS_COMPETITION_ID),
            matchSource: $public,
            owner: $admin,
            name: self::BOOSTS_COMPETITION_NAME,
            description: null,
            pin: null,
            shareableLinkToken: self::BOOSTS_COMPETITION_LINK_TOKEN,
            createdAt: $now,
            monetization: CompetitionMonetization::Boosts,
        );
        $boostsCompetition->popEvents();
        $manager->persist($boostsCompetition);

        $boostsOwnerMembership = new Membership(
            id: Uuid::fromString(self::BOOSTS_COMPETITION_OWNER_MEMBERSHIP_ID),
            competition: $boostsCompetition,
            user: $admin,
            joinedAt: $now,
        );
        $boostsOwnerMembership->popEvents();
        $manager->persist($boostsOwnerMembership);

        $boostsMemberMembership = new Membership(
            id: Uuid::fromString(self::BOOSTS_COMPETITION_MEMBER_MEMBERSHIP_ID),
            competition: $boostsCompetition,
            user: $secondVerified,
            joinedAt: $now,
        );
        $boostsMemberMembership->popEvents();
        $manager->persist($boostsMemberMembership);

        $othersTipsBoost = new BoostPurchase(
            id: Uuid::fromString(self::BOOST_PURCHASE_OTHERS_TIPS_ID),
            user: $secondVerified,
            competition: $boostsCompetition,
            type: BoostType::OthersTips,
            pricePaid: BoostType::OthersTips->price(),
            purchasedAt: $now,
        );
        $othersTipsBoost->popEvents();
        $manager->persist($othersTipsBoost);

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
        $finishedMatch->setFinalScore(
            homeScore: 2,
            awayScore: 1,
            periodScores: PeriodScores::fromArray([[1, 0], [1, 1]]),
            overtimeHomeScore: null,
            overtimeAwayScore: null,
            now: $now,
        );
        $finishedMatch->popEvents();
        $manager->persist($finishedMatch);

        // Roster pool + timeline of the finished match (2:1 — both home goals
        // recorded, the away goal intentionally without a scorer, plus one
        // yellow card; goal-count vs score mismatch is allowed by design).
        $homeScorerOne = new Player(
            id: Uuid::fromString(self::PLAYER_HOME_SCORER_ONE_ID),
            matchSource: $public,
            teamName: $finishedMatch->homeTeam,
            name: self::PLAYER_HOME_SCORER_ONE_NAME,
            createdAt: $now,
        );
        $manager->persist($homeScorerOne);

        $homeScorerTwo = new Player(
            id: Uuid::fromString(self::PLAYER_HOME_SCORER_TWO_ID),
            matchSource: $public,
            teamName: $finishedMatch->homeTeam,
            name: self::PLAYER_HOME_SCORER_TWO_NAME,
            createdAt: $now,
        );
        $manager->persist($homeScorerTwo);

        $awayBooked = new Player(
            id: Uuid::fromString(self::PLAYER_AWAY_BOOKED_ID),
            matchSource: $public,
            teamName: $finishedMatch->awayTeam,
            name: self::PLAYER_AWAY_BOOKED_NAME,
            createdAt: $now,
        );
        $manager->persist($awayBooked);

        $manager->persist(new MatchEvent(
            id: Uuid::fromString(self::MATCH_EVENT_GOAL_ONE_ID),
            sportMatch: $finishedMatch,
            type: MatchEventType::Goal,
            side: MatchSide::Home,
            minute: 27,
            player: $homeScorerOne,
            createdAt: $now,
        ));
        $manager->persist(new MatchEvent(
            id: Uuid::fromString(self::MATCH_EVENT_GOAL_TWO_ID),
            sportMatch: $finishedMatch,
            type: MatchEventType::Goal,
            side: MatchSide::Home,
            minute: 63,
            player: $homeScorerTwo,
            createdAt: $now,
        ));
        $manager->persist(new MatchEvent(
            id: Uuid::fromString(self::MATCH_EVENT_YELLOW_CARD_ID),
            sportMatch: $finishedMatch,
            type: MatchEventType::YellowCard,
            side: MatchSide::Away,
            minute: 51,
            player: $awayBooked,
            createdAt: $now,
        ));

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
            // S06 feature-on example: optional rules enabled for SUBSET only.
            [self::SUBSET_COMPETITION_RULE_SCORER_HIT_ID, $subsetCompetition, 'scorer_hit', 2],
            [self::SUBSET_COMPETITION_RULE_PERIOD_EXACT_ID, $subsetCompetition, 'period_exact', 5],
            [self::SUBSET_COMPETITION_RULE_PERIOD_TENDENCY_ID, $subsetCompetition, 'period_tendency', 2],
            [self::PREMIUM_COMPETITION_RULE_EXACT_SCORE_ID, $premiumCompetition, 'exact_score', 5],
            [self::PREMIUM_COMPETITION_RULE_CORRECT_OUTCOME_ID, $premiumCompetition, 'correct_outcome', 3],
            [self::PREMIUM_COMPETITION_RULE_CORRECT_HOME_GOALS_ID, $premiumCompetition, 'correct_home_goals', 1],
            [self::PREMIUM_COMPETITION_RULE_CORRECT_AWAY_GOALS_ID, $premiumCompetition, 'correct_away_goals', 1],
            [self::BOOSTS_COMPETITION_RULE_EXACT_SCORE_ID, $boostsCompetition, 'exact_score', 5],
            [self::BOOSTS_COMPETITION_RULE_CORRECT_OUTCOME_ID, $boostsCompetition, 'correct_outcome', 3],
            [self::BOOSTS_COMPETITION_RULE_CORRECT_HOME_GOALS_ID, $boostsCompetition, 'correct_home_goals', 1],
            [self::BOOSTS_COMPETITION_RULE_CORRECT_AWAY_GOALS_ID, $boostsCompetition, 'correct_away_goals', 1],
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

        // S06: guess with period + scorer tips in the feature-on SUBSET_COMPETITION
        // (MATCH_FINISHED is explicitly selected there). No evaluation seeded —
        // tests trigger evaluation themselves.
        $subsetGuess = new Guess(
            id: Uuid::fromString(self::SUBSET_GUESS_ID),
            user: $secondVerified,
            sportMatch: $finishedMatch,
            competition: $subsetCompetition,
            homeScore: 2,
            awayScore: 1,
            submittedAt: $now,
            periodScores: PeriodScores::fromArray([[1, 0], [1, 1]]),
        );
        $subsetGuess->addScorer(new GuessScorer(
            id: Uuid::fromString(self::SUBSET_GUESS_SCORER_ID),
            guess: $subsetGuess,
            player: $homeScorerOne,
            side: MatchSide::Home,
            createdAt: $now,
        ));
        $subsetGuess->popEvents();
        $manager->persist($subsetGuess);

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

        // Stage 11: notification feed for VERIFIED_USER (one unread, one read)
        // plus a preference override (match_evaluated: in-app on, email off).
        $unreadNotification = new Notification(
            id: Uuid::fromString(self::NOTIFICATION_UNREAD_ID),
            user: $verified,
            type: NotificationType::MatchAdded,
            title: 'Nový zápas: Bayern – Real Madrid',
            body: sprintf('Do soutěže %s přibyl zápas Bayern – Real Madrid (22. 6. 2025 21:00).', self::VERIFIED_COMPETITION_NAME),
            competition: $verifiedCompetition,
            createdAt: $now->modify('-2 hours'),
            url: '/portal/souteze/'.self::VERIFIED_COMPETITION_ID.'/zebricek',
        );
        $manager->persist($unreadNotification);

        $readNotification = new Notification(
            id: Uuid::fromString(self::NOTIFICATION_READ_ID),
            user: $verified,
            type: NotificationType::MatchEvaluated,
            title: 'Vyhodnoceno: Sparta 2:1 Slavia',
            body: 'Sparta 2:1 Slavia: získáváte 3 b., jste 2. v soutěži '.self::VERIFIED_COMPETITION_NAME.'.',
            competition: $verifiedCompetition,
            createdAt: $now->modify('-1 day'),
            url: '/portal/souteze/'.self::VERIFIED_COMPETITION_ID.'/zebricek',
        );
        $readNotification->markRead($now->modify('-20 hours'));
        $manager->persist($readNotification);

        $manager->persist(new NotificationPreference(
            id: Uuid::fromString(self::NOTIFICATION_PREFERENCE_ID),
            user: $verified,
            type: NotificationType::MatchEvaluated,
            inApp: true,
            email: false,
            createdAt: $now,
        ));

        // ── Leaderboard snapshots (S12) ──────────────────────────────────────
        // $now is 2025-06-15 12:00 UTC ⇒ Prague today = 2025-06-15, „yesterday" =
        // 2025-06-14. VERIFIED_COMPETITION has no finished matches, so its live
        // leaderboard is all-zeros (both members tied rank 1); the snapshots below
        // mirror that reality — 0 points, rank 1 — so no screen ever shows points
        // the board cannot justify. VERIFIED_USER (owner) is present on both days;
        // ANONYMOUS_USER joined today, so it is absent from the 2025-06-14 baseline
        // ⇒ today's board flags it „nový v žebříčku", while VERIFIED_USER is „beze
        // změny". Δ compares today's rank to the latest day strictly before today.
        $yesterday = new \DateTimeImmutable('2025-06-14 00:00:00', new \DateTimeZone('Europe/Prague'));
        $today = new \DateTimeImmutable('2025-06-15 00:00:00', new \DateTimeZone('Europe/Prague'));

        $manager->persist(new LeaderboardSnapshot(
            id: Uuid::fromString(self::SNAPSHOT_YESTERDAY_VERIFIED_ID),
            competition: $verifiedCompetition,
            user: $verified,
            day: $yesterday,
            points: 0,
            rank: 1,
            createdAt: $now->modify('-1 day'),
        ));
        $manager->persist(new LeaderboardSnapshot(
            id: Uuid::fromString(self::SNAPSHOT_TODAY_VERIFIED_ID),
            competition: $verifiedCompetition,
            user: $verified,
            day: $today,
            points: 0,
            rank: 1,
            createdAt: $now,
        ));
        $manager->persist(new LeaderboardSnapshot(
            id: Uuid::fromString(self::SNAPSHOT_TODAY_ANONYMOUS_ID),
            competition: $verifiedCompetition,
            user: $anonymous,
            day: $today,
            points: 0,
            rank: 1,
            createdAt: $now,
        ));

        $manager->flush();
    }
}
