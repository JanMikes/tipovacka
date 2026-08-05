<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\BoostPurchase;
use App\Entity\Competition;
use App\Entity\CompetitionInvitation;
use App\Entity\CompetitionPremiumCharge;
use App\Entity\CompetitionRuleConfiguration;
use App\Entity\CompetitionSource;
use App\Entity\CompetitionTeamFilter;
use App\Entity\CreditWallet;
use App\Entity\Guess;
use App\Entity\GuessEvaluation;
use App\Entity\GuessEvaluationRulePoints;
use App\Entity\GuessScorer;
use App\Entity\LeaderboardSnapshot;
use App\Entity\LeaderboardTieResolution;
use App\Entity\MatchEvent;
use App\Entity\MatchSource;
use App\Entity\Membership;
use App\Entity\Player;
use App\Entity\Sport;
use App\Entity\SportMatch;
use App\Entity\Team;
use App\Entity\User;
use App\Enum\BoostType;
use App\Enum\CompetitionMatchSelectionMode;
use App\Enum\CompetitionMonetization;
use App\Enum\CreditTransactionType;
use App\Enum\MatchEventType;
use App\Enum\MatchSide;
use App\Enum\MatchSourceKind;
use App\Rule\ScorerHitRule;
use App\Service\Credits\PricingConfig;
use App\Service\Team\TeamResolver;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Dev-only fixtures loaded via `composer db:fixtures` (group=dev).
 * Adds 25 extra users, 3 extra match_sources (finished, in-progress public, private active),
 * multiple competitions per match source, cross-competition memberships, matches and evaluated guesses
 * so the UI can be exercised with realistic volume.
 *
 * On top of that (item 03 of the UI/nav stream) it seeds five self-contained
 * „worlds" that the rebuilt pages are developed against — a big paid GLOBAL
 * competition with a real leaderboard, a small PRIVATE premium one organized by
 * the primary dev user, a FINISHED one, a TEAM-FILTER one, and one that has NOT
 * STARTED yet (B23/B11). They are documented world by world in
 * `.docs/FIXTURES.md`.
 *
 * Not loaded in the test suite (tests request group=test).
 */
final class DevFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    /**
     * Seed rows: [nickname, firstName, lastName].
     * Email is derived as "{nickname}@tipovacka.dev".
     * UUIDs are assigned sequentially from 01933333-…-0000000001XX.
     *
     * @var list<array{string, string, string}>
     */
    private const array USER_SEEDS = [
        ['honza', 'Jan', 'Novák'],
        ['petros', 'Petr', 'Svoboda'],
        ['janicka', 'Jana', 'Dvořáková'],
        ['tomas_p', 'Tomáš', 'Procházka'],
        ['martas', 'Martin', 'Černý'],
        ['lukas_h', 'Lukáš', 'Horák'],
        ['pavlik', 'Pavel', 'Veselý'],
        ['mischa', 'Michal', 'Pokorný'],
        ['mara', 'Marek', 'Kučera'],
        ['filda', 'Filip', 'Doležal'],
        ['kuba', 'Jakub', 'Beneš'],
        ['ondra', 'Ondřej', 'Marek'],
        ['dejv', 'David', 'Fiala'],
        ['kaja', 'Karel', 'Havelka'],
        ['zdenekb', 'Zdeněk', 'Bartoš'],
        ['romca', 'Roman', 'Jelínek'],
        ['vojta', 'Vojtěch', 'Pospíšil'],
        ['adamr', 'Adam', 'Růžička'],
        ['katka', 'Kateřina', 'Malá'],
        ['lucka', 'Lucie', 'Bláhová'],
        ['evinka', 'Eva', 'Krejčí'],
        ['terka', 'Tereza', 'Němcová'],
        ['bara', 'Barbora', 'Holubová'],
        ['kristy', 'Kristýna', 'Pešková'],
        ['miska', 'Michaela', 'Vacková'],
    ];

    // --- item 03: development worlds (documented in .docs/FIXTURES.md) --------

    /** World A + D live on this curated source: a running tournament with rounds and a playoff. */
    public const string WORLD_CUP_SOURCE_ID = '019aaaaa-0000-7000-8000-0000000000f1';
    public const string WORLD_CUP_SOURCE_NAME = 'Mistrovství světa 2026';

    /** World A — the big PAID GLOBAL competition (the leaderboard playground). */
    public const string WORLD_CUP_COMPETITION_ID = '019bbbbb-0000-7000-8000-0000000000f1';
    public const string WORLD_CUP_COMPETITION_NAME = 'Tipovačka MS 2026';
    /** Entry fee = competition data set by the admin, NOT a price from PricingConfig. */
    public const int WORLD_CUP_COMPETITION_ENTRY_FEE = 30;

    /** World D — same source, selection mode Teams (filtered to Česko + Slovensko). */
    public const string TEAM_FILTER_COMPETITION_ID = '019bbbbb-0000-7000-8000-0000000000f2';
    public const string TEAM_FILTER_COMPETITION_NAME = 'Fandíme Česku';

    /** World B — small PRIVATE premium competition organized by the primary dev user. */
    public const string NEIGHBOURS_SOURCE_ID = '019aaaaa-0000-7000-8000-0000000000f2';
    public const string NEIGHBOURS_SOURCE_NAME = 'Sousedská liga';
    public const string NEIGHBOURS_COMPETITION_ID = '019bbbbb-0000-7000-8000-0000000000f3';
    public const string NEIGHBOURS_COMPETITION_NAME = 'Sousedský pohár';
    public const string NEIGHBOURS_ANONYMOUS_USER_ID = '01933333-0000-7000-8000-0000000001f1';
    public const string NEIGHBOURS_ANONYMOUS_FIRST_NAME = 'Josef';
    public const string NEIGHBOURS_ANONYMOUS_LAST_NAME = 'Dvořák';
    public const string NEIGHBOURS_INVITATION_ID = '019ccccc-0000-7000-8000-0000000000f1';
    public const string NEIGHBOURS_INVITATION_EMAIL = 'soused@tipovacka.dev';
    public const string NEIGHBOURS_INVITATION_TOKEN = 'f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1f1';

    /**
     * World B roster (B31) — four players per LOCAL team of the Sousedská liga,
     * keyed by team name and seeded in this order, so the guess form's „Tip na
     * střelce" picker always has a pool to autocomplete over. World B is the ONE
     * dev competition with `scorer_hit` enabled, and its two last fixtures are
     * deliberately untipped, so an open form with an EMPTY picker is one click
     * away. Rosters are short on purpose: any name outside them reaches the
     * picker's „Přidat hráče …" create row, which lands a new {@see Player} on
     * the LOCAL team ({@see TeamResolver}'s hybrid scope — a private source never
     * writes into the shared directory).
     *
     * Player UUIDs are `019ddddd-0000-7000-8000-0000000fe0XX`, XX counting up in
     * hex through this list.
     *
     * @var array<string, list<string>>
     */
    public const array NEIGHBOURS_ROSTERS = [
        'Sokol Dolní' => ['Radek Bureš', 'Ondřej Klíma', 'Vlastimil Hruška', 'Jiří Vávra'],
        'Sokol Horní' => ['Milan Toman', 'Aleš Kohout', 'Zbyněk Matoušek', 'Dalibor Hejda'],
        'Kanonýři' => ['Ivan Zeman', 'Lubomír Cink', 'Štěpán Bažant', 'Norbert Kadlec'],
        'Rebelové' => ['Bohuslav Kolář', 'Přemysl Šťastný', 'Květoslav Tichý', 'Oldřich Vaněk'],
        'Dynamo Zahrádka' => ['Ctirad Beran', 'Bedřich Slavík', 'Svatopluk Marek', 'Jarmil Kopecký'],
        'Old Boys' => ['Vojtěch Straka', 'Alois Pekař', 'Ferdinand Bláha', 'Bohumil Konečný'],
    ];

    /** World C — everything played, source completed („Ukončeno" states, resolved tie). */
    public const string WINTER_SOURCE_ID = '019aaaaa-0000-7000-8000-0000000000f3';
    public const string WINTER_SOURCE_NAME = 'Zimní pohár 2026';
    public const string WINTER_COMPETITION_ID = '019bbbbb-0000-7000-8000-0000000000f4';
    public const string WINTER_COMPETITION_NAME = 'Zimní pohár – parta';

    /**
     * World E — the soutěž that has NOT started yet (B23), organized by the
     * primary dev user, premium with both visibility toggles OFF (B11 locked).
     */
    public const string UPCOMING_SOURCE_ID = '019aaaaa-0000-7000-8000-0000000000f4';
    public const string UPCOMING_SOURCE_NAME = 'Pohár Vysočiny';
    public const string UPCOMING_COMPETITION_ID = '019bbbbb-0000-7000-8000-0000000000f5';
    public const string UPCOMING_COMPETITION_NAME = 'Vysočina – naše parta';

    /**
     * Days between „today" and World E's first kickoff — i.e. the width of the
     * window a scheduled „Uzamknout tipy · V určený čas" has to fit into (the
     * moment must be in the future AND before the competition start, see B2).
     * Keep it comfortable: a competition starting in minutes cannot exercise the
     * picker.
     */
    public const int UPCOMING_FIRST_KICKOFF_DAYS = 5;

    /**
     * The ONE deliberately unverified dev login, so the e-mail verification
     * airlock can be exercised. Every other dev user is verified.
     */
    public const string UNVERIFIED_DEV_USER_ID = '01933333-0000-7000-8000-0000000001f2';
    public const string UNVERIFIED_DEV_USER_EMAIL = 'neovereny@tipovacka.dev';
    public const string UNVERIFIED_DEV_USER_NICKNAME = 'neovereny';

    /** Round labels of the World-Cup source (item 02 `SportMatch::$round`). */
    public const string ROUND_GROUP_1 = 'Základní skupina – 1. kolo';
    public const string ROUND_GROUP_2 = 'Základní skupina – 2. kolo';
    public const string ROUND_GROUP_3 = 'Základní skupina – 3. kolo';
    public const string ROUND_LAST_16 = 'Osmifinále';
    public const string ROUND_QUARTER_FINAL = 'Čtvrtfinále';

    /** The two teams World D's team filter is pinned to. */
    public const string TEAM_CESKO_ID = '019ddddd-0000-7000-8000-0000000ff001';
    public const string TEAM_SLOVENSKO_ID = '019ddddd-0000-7000-8000-0000000ff002';

    /**
     * Balance the primary dev user is LEFT with after the seeded spending:
     * deliberately ONE credit short of the dearest boost („Počkejte si na
     * sestavy"), which by construction still affords every cheaper one („Jak
     * tipují ostatní?", „Přesné tipy soupeřů") — so BOTH halves of the boost
     * paywall, the buy CTA and „Chybí kredity", are one click away after a plain
     * `db:reset`.
     *
     * Derived from {@see PricingConfig::BOOST_TIP_CHANGE} rather than summed from
     * the other prices ON PURPOSE. A sum silently drifts past the boost it is
     * meant to fall short of the next time prices move — which is exactly what
     * happened when item 23 re-set them (10/20/40 → 15/35/50) and the old
     * `OTHERS_TIPS + TIP_DISTRIBUTION + 5` landed at 55 against a 50 kr. boost,
     * making the insufficient-credits branch unreachable. „The dearest boost
     * minus one credit" cannot: an integer price strictly below it stays
     * affordable, and the boost itself never is. Prices always come from
     * {@see PricingConfig} — never a literal.
     */
    public const int DEV_USER_CREDIT_BALANCE = PricingConfig::BOOST_TIP_CHANGE - 1;

    /** Points a plan code is worth under the four default rules (see {@see guessFor}). */
    private const array PLAN_CODE_POINTS = ['e' => 10, 'o' => 3, 'h' => 1, 'm' => 0];

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly TeamResolver $teamResolver,
    ) {
    }

    public static function getGroups(): array
    {
        return ['dev'];
    }

    public function getDependencies(): array
    {
        return [AppFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        /** @var Sport $football */
        $football = $manager->find(Sport::class, Uuid::fromString(Sport::FOOTBALL_ID));
        /** @var User $admin */
        $admin = $manager->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        /** @var User $verified */
        $verified = $manager->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));

        // -- Users (25 new) -----------------------------------------------
        /** @var array<int, User> $users */
        $users = [];
        foreach (self::USER_SEEDS as $index => [$nickname, $firstName, $lastName]) {
            $users[$index + 1] = $this->createVerifiedUser(
                $manager,
                index: $index + 1,
                nickname: $nickname,
                firstName: $firstName,
                lastName: $lastName,
                now: $now,
            );
        }

        // Showcase a blocked user for the admin UI.
        $users[8]->deactivate($now);
        $users[8]->popEvents();

        // -- MatchSources (3 new) ------------------------------------------
        $euro = new MatchSource(
            id: Uuid::fromString('019aaaaa-0000-7000-8000-000000000003'),
            sport: $football,
            owner: $users[1],
            kind: MatchSourceKind::Curated,
            name: 'Euro 2024',
            description: 'Fotbalové mistrovství Evropy 2024 v Německu — tipovačka mezi kamarády.',
            startAt: new \DateTimeImmutable('2024-06-14 00:00:00 UTC'),
            endAt: new \DateTimeImmutable('2024-07-14 23:59:59 UTC'),
            createdAt: new \DateTimeImmutable('2024-04-01 10:00:00 UTC'),
        );
        $euro->markCompleted(new \DateTimeImmutable('2024-07-15 12:00:00 UTC'));
        $euro->popEvents();
        $manager->persist($euro);

        $fortuna = new MatchSource(
            id: Uuid::fromString('019aaaaa-0000-7000-8000-000000000004'),
            sport: $football,
            owner: $verified,
            kind: MatchSourceKind::Curated,
            name: 'Fortuna Liga 2025/26',
            description: 'Česká první fotbalová liga — celoroční tipovačka.',
            startAt: new \DateTimeImmutable('2025-07-18 00:00:00 UTC'),
            endAt: new \DateTimeImmutable('2026-05-24 23:59:59 UTC'),
            createdAt: new \DateTimeImmutable('2025-06-01 09:00:00 UTC'),
        );
        $fortuna->popEvents();
        $manager->persist($fortuna);

        $firma = new MatchSource(
            id: Uuid::fromString('019aaaaa-0000-7000-8000-000000000005'),
            sport: $football,
            owner: $users[17],
            kind: MatchSourceKind::Private,
            name: 'Firemní liga',
            description: 'Soukromá tipovačka pro kolegy z práce.',
            startAt: new \DateTimeImmutable('2025-06-01 00:00:00 UTC'),
            endAt: new \DateTimeImmutable('2025-12-31 23:59:59 UTC'),
            createdAt: new \DateTimeImmutable('2025-05-20 14:30:00 UTC'),
        );
        $firma->popEvents();
        $manager->persist($firma);

        // -- Competitions -------------------------------------------------------
        $eurofans = $this->createCompetition(
            $manager,
            id: '019bbbbb-0000-7000-8000-000000000003',
            matchSource: $euro,
            owner: $users[1],
            name: 'Eurofans',
            description: 'Tipujeme Euro, po každém zápase bago.',
            pin: '20240701',
            linkToken: str_repeat('e', 48),
            createdAt: new \DateTimeImmutable('2024-04-02 10:00:00 UTC'),
        );

        $vsChtCompetition = $this->createCompetition(
            $manager,
            id: '019bbbbb-0000-7000-8000-000000000004',
            matchSource: $fortuna,
            owner: $verified,
            name: 'VŠCHT tipovačka',
            description: 'Bývalí spolužáci z VŠCHT.',
            pin: '10000001',
            linkToken: str_repeat('1', 48),
            createdAt: new \DateTimeImmutable('2025-06-02 10:00:00 UTC'),
        );

        $prahaCompetition = $this->createCompetition(
            $manager,
            id: '019bbbbb-0000-7000-8000-000000000005',
            matchSource: $fortuna,
            owner: $users[14],
            name: 'Pražský pivní klub',
            description: 'Druhá soutěž ve stejném turnaji — Fortuna Liga.',
            pin: '10000002',
            linkToken: str_repeat('2', 48),
            createdAt: new \DateTimeImmutable('2025-06-03 12:00:00 UTC'),
        );

        $firmaA = $this->createCompetition(
            $manager,
            id: '019bbbbb-0000-7000-8000-000000000006',
            matchSource: $firma,
            owner: $users[17],
            name: 'Dev tým',
            description: 'Programátoři proti sobě.',
            pin: '20000001',
            linkToken: str_repeat('d', 48),
            createdAt: new \DateTimeImmutable('2025-05-21 09:00:00 UTC'),
        );

        $firmaB = $this->createCompetition(
            $manager,
            id: '019bbbbb-0000-7000-8000-000000000007',
            matchSource: $firma,
            owner: $users[22],
            name: 'Management',
            description: 'Druhá soutěž — vedení firmy, stejný turnaj.',
            pin: '20000002',
            linkToken: str_repeat('3', 48),
            createdAt: new \DateTimeImmutable('2025-05-22 09:00:00 UTC'),
        );

        /** @var MatchSource $publicSource */
        $publicSource = $manager->find(MatchSource::class, Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID));
        $kamosiCompetition = $this->createCompetition(
            $manager,
            id: '019bbbbb-0000-7000-8000-000000000008',
            matchSource: $publicSource,
            owner: $users[24],
            name: 'Kamarádi ze střední',
            description: 'Druhá soutěž v Lize mistrů.',
            pin: '30000001',
            linkToken: str_repeat('4', 48),
            createdAt: new \DateTimeImmutable('2025-06-05 18:00:00 UTC'),
        );

        foreach ([$eurofans, $vsChtCompetition, $prahaCompetition, $firmaA, $firmaB, $kamosiCompetition] as $competition) {
            $this->provisionDefaultRules($manager, $competition, $now);
        }

        // -- Memberships --------------------------------------------------
        // Eurofans (finished match source) — admin + 8 users.
        $this->addMembers($manager, $eurofans, [$admin, $users[1], $users[2], $users[3], $users[4], $users[5], $users[6], $users[7]], $now);

        // VŠCHT — verified + 5 users. User 9 overlaps with Praha competition (same match source).
        $this->addMembers($manager, $vsChtCompetition, [$verified, $users[1], $users[9], $users[10], $users[11], $users[12], $users[13]], $now);

        // Praha — user 1 and user 9 overlap with VŠCHT (same match source, different competition).
        $this->addMembers($manager, $prahaCompetition, [$users[14], $users[1], $users[9], $users[15], $users[16]], $now);

        // Firma A (private) — u17 owner + 4 users.
        $this->addMembers($manager, $firmaA, [$users[17], $users[18], $users[19], $users[20], $users[21]], $now);

        // Firma B — u17 overlaps with Firma A (same private match source).
        $this->addMembers($manager, $firmaB, [$users[22], $users[17], $users[23]], $now);

        // Second competition inside the existing PUBLIC match source.
        $this->addMembers($manager, $kamosiCompetition, [$users[24], $users[25], $users[1]], $now);

        // -- Matches ------------------------------------------------------
        // Euro 2024 — all finished.
        $euroMatches = [
            $this->finishedMatch($manager, $euro, 'ddaa0101', 'Španělsko', 'Anglie', '2024-07-14 19:00:00', 'Olympiastadion Berlin', 2, 1, $now),
            $this->finishedMatch($manager, $euro, 'ddaa0102', 'Německo', 'Francie', '2024-07-06 18:00:00', 'Allianz Arena', 1, 1, $now),
            $this->finishedMatch($manager, $euro, 'ddaa0103', 'Itálie', 'Nizozemsko', '2024-06-22 15:00:00', null, 3, 2, $now),
            $this->finishedMatch($manager, $euro, 'ddaa0104', 'Portugalsko', 'Belgie', '2024-06-29 20:00:00', 'Signal Iduna Park', 0, 2, $now),
        ];

        // Fortuna Liga — mix of finished, live, scheduled.
        $fortunaFinished = [
            $this->finishedMatch($manager, $fortuna, 'ddbb0101', 'Sparta Praha', 'Slavia Praha', '2025-06-08 17:00:00', 'Generali Arena', 3, 1, $now),
            $this->finishedMatch($manager, $fortuna, 'ddbb0102', 'Viktoria Plzeň', 'Slovácko', '2025-06-10 19:00:00', 'Doosan Arena', 2, 2, $now),
        ];
        $this->liveMatch($manager, $fortuna, 'ddbb0103', 'Baník Ostrava', 'Pardubice', '2025-06-15 11:30:00', 'Ostravar Arena', $now);

        // Scheduled matches are anchored to the real "today" so the portal dashboard
        // always has upcoming matches to exercise the UI, regardless of when fixtures
        // are reloaded. Finished/live matches stay at their historical dates.
        $today = (new \DateTimeImmutable('today', new \DateTimeZone('UTC')));
        $fortunaUpcoming1 = $this->scheduledMatch($manager, $fortuna, 'ddbb0104', 'Bohemians 1905', 'Mladá Boleslav', $today->modify('+2 days')->setTime(17, 0)->format('Y-m-d H:i:s'), 'Ďolíček', $now);
        $fortunaUpcoming2 = $this->scheduledMatch($manager, $fortuna, 'ddbb0105', 'Teplice', 'Liberec', $today->modify('+5 days')->setTime(15, 0)->format('Y-m-d H:i:s'), null, $now);
        $this->scheduledMatch($manager, $fortuna, 'ddbb0106', 'Hradec Králové', 'Zlín', $today->modify('+10 days')->setTime(17, 30)->format('Y-m-d H:i:s'), 'Malšovická aréna', $now);

        // Private match source — mostly scheduled, one finished.
        $this->scheduledMatch($manager, $firma, 'ddcc0101', 'Tygři', 'Lvi', $today->modify('+3 days')->setTime(18, 0)->format('Y-m-d H:i:s'), null, $now);
        $this->scheduledMatch($manager, $firma, 'ddcc0102', 'Orli', 'Medvědi', $today->modify('+14 days')->setTime(18, 0)->format('Y-m-d H:i:s'), null, $now);
        $firmaFinished = $this->finishedMatch($manager, $firma, 'ddcc0103', 'Kohouti', 'Vlci', '2025-06-08 17:00:00', null, 1, 0, $now);

        // -- Guesses + evaluations ---------------------------------------
        // Eurofans guesses (all 4 euro matches for all 8 non-admin members,
        // plus admin — deterministic scores so leaderboards have spread).
        $eurofansMembers = [$admin, $users[1], $users[2], $users[3], $users[4], $users[5], $users[6], $users[7]];
        $guessPattern = [
            // [homeGuess, awayGuess] per member — rotated per match for variety.
            [2, 1], [3, 0], [1, 1], [2, 0], [0, 1], [2, 2], [1, 2], [3, 1],
        ];
        foreach ($euroMatches as $matchIndex => $match) {
            foreach ($eurofansMembers as $memberIndex => $member) {
                // Offset the pattern per match so members don't always guess the same.
                $offset = ($memberIndex + $matchIndex) % count($guessPattern);
                [$gh, $ga] = $guessPattern[$offset];
                $this->createEvaluatedGuess($manager, $member, $match, $eurofans, $gh, $ga, $now);
            }
        }

        // Fortuna VŠCHT + Praha — guesses on the two finished matches.
        $vsChtMembers = [$verified, $users[1], $users[9], $users[10], $users[11], $users[12], $users[13]];
        $prahaMembers = [$users[14], $users[1], $users[9], $users[15], $users[16]];
        foreach ($fortunaFinished as $matchIndex => $match) {
            foreach ($vsChtMembers as $memberIndex => $member) {
                [$gh, $ga] = $guessPattern[($memberIndex + $matchIndex) % count($guessPattern)];
                $this->createEvaluatedGuess($manager, $member, $match, $vsChtCompetition, $gh, $ga, $now);
            }
            foreach ($prahaMembers as $memberIndex => $member) {
                [$gh, $ga] = $guessPattern[($memberIndex + $matchIndex + 3) % count($guessPattern)];
                $this->createEvaluatedGuess($manager, $member, $match, $prahaCompetition, $gh, $ga, $now);
            }
        }

        // VŠCHT members who have already tipped on the closest upcoming Fortuna match,
        // so the dashboard surfaces an "awaiting result" state (honza = users[1] tips too).
        foreach ([$verified, $users[1], $users[10], $users[11]] as $memberIndex => $member) {
            [$gh, $ga] = $guessPattern[$memberIndex % count($guessPattern)];
            $this->createEvaluatedGuess($manager, $member, $fortunaUpcoming1, $vsChtCompetition, $gh, $ga, $now);
        }

        // Honza also tips on the second upcoming Fortuna match via the Praha competition,
        // so both his competitions show activity on upcoming fixtures.
        $this->createEvaluatedGuess($manager, $users[1], $fortunaUpcoming2, $prahaCompetition, 1, 2, $now);

        // Firma match sources — one finished match, both competitions tip on it.
        foreach ([$users[17], $users[18], $users[19], $users[20], $users[21]] as $memberIndex => $member) {
            [$gh, $ga] = $guessPattern[$memberIndex % count($guessPattern)];
            $this->createEvaluatedGuess($manager, $member, $firmaFinished, $firmaA, $gh, $ga, $now);
        }
        foreach ([$users[22], $users[17], $users[23]] as $memberIndex => $member) {
            [$gh, $ga] = $guessPattern[($memberIndex + 2) % count($guessPattern)];
            $this->createEvaluatedGuess($manager, $member, $firmaFinished, $firmaB, $gh, $ga, $now);
        }

        // -- Leaderboard snapshots (S12 delta demo) -----------------------------
        // A genuine EARLIER standing of the VŠCHT competition: the board exactly as
        // it stood after only the FIRST finished Fortuna match (Sparta 3:1 Slavia,
        // 2025-06-08), captured on 2025-06-09 — before the second finished match
        // (Plzeň 2:2 Slovácko, 2025-06-10) reshuffled it. Because it is a real
        // partial-sum state, every seeded point total is ≤ that member's current
        // total, so the member „Vývoj" list never exceeds the live „Celkem bodů",
        // and the leaderboard Δ shows honest movement (current board vs this day).
        // The other dev competitions carry no snapshot ⇒ a neutral „bez historie"
        // dot, never a fabricated delta.
        $vsChtAfterFirstMatch = [
            [$verified, 1, 4],
            [$users[1], 1, 4],
            [$users[10], 3, 3],
            [$users[9], 4, 1],
            [$users[11], 4, 1],
            [$users[12], 6, 0],
            [$users[13], 6, 0],
        ];
        $snapshotDay = new \DateTimeImmutable('2025-06-09 00:00:00', new \DateTimeZone('Europe/Prague'));
        $snapshotCreatedAt = new \DateTimeImmutable('2025-06-09 03:00:00 UTC');

        foreach ($vsChtAfterFirstMatch as [$member, $rank, $points]) {
            $manager->persist(new LeaderboardSnapshot(
                id: Uuid::v7(),
                competition: $vsChtCompetition,
                user: $member,
                day: $snapshotDay,
                points: $points,
                rank: $rank,
                createdAt: $snapshotCreatedAt,
            ));
        }

        // -- item 03 worlds ------------------------------------------------
        $this->loadDevWorlds($manager, $football, $admin, $verified, $users);

        $manager->flush();
    }

    /**
     * The five self-contained worlds items 04–07 are developed against. Unlike the
     * data above they are anchored to the REAL calendar (`today ± n days`), so a
     * `db:reset` always produces a tournament that is genuinely half-played: past
     * matches carry results, upcoming ones are still tippable, and the rolling
     * „Posledních 7 dní" / „Poslední kolo" leaderboard windows are never empty.
     * Everything is created at `$seededAt` (= real now), which is AFTER the
     * earliest kickoff of every world ⇒ each match counts as late-added and keeps
     * its own kickoff as the tip deadline (see EffectiveTipDeadlineResolver), so
     * the upcoming fixtures stay tippable in the browser.
     *
     * @param array<int, User> $users
     */
    private function loadDevWorlds(
        ObjectManager $manager,
        Sport $football,
        User $admin,
        User $verified,
        array $users,
    ): void {
        $today = new \DateTimeImmutable('today', new \DateTimeZone('UTC'));
        $seededAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        // The ONE deliberately unverified dev login (verification airlock). It is
        // a member of nothing so it never muddies a leaderboard.
        $unverified = new User(
            id: Uuid::fromString(self::UNVERIFIED_DEV_USER_ID),
            email: self::UNVERIFIED_DEV_USER_EMAIL,
            password: null,
            nickname: self::UNVERIFIED_DEV_USER_NICKNAME,
            createdAt: $seededAt,
        );
        $unverified->changePassword(
            $this->passwordHasher->hashPassword($unverified, AppFixtures::DEFAULT_PASSWORD),
            $seededAt,
        );
        $unverified->updateProfile('Neověřený', 'Nováček', null, $seededAt);
        $unverified->popEvents();
        $manager->persist($unverified);

        $teams = $this->createWorldCupTeams($manager, $football, $seededAt);

        $worldCupSource = new MatchSource(
            id: Uuid::fromString(self::WORLD_CUP_SOURCE_ID),
            sport: $football,
            owner: $admin,
            kind: MatchSourceKind::Curated,
            name: self::WORLD_CUP_SOURCE_NAME,
            description: 'Mistrovství světa ve fotbale — základní skupiny i vyřazovací fáze.',
            startAt: $today->modify('-16 days')->setTime(0, 0),
            endAt: $today->modify('+16 days')->setTime(23, 59, 59),
            createdAt: $seededAt,
        );
        $worldCupSource->popEvents();
        $manager->persist($worldCupSource);

        $matches = $this->createWorldCupMatches($manager, $worldCupSource, $teams, $today, $seededAt);
        /** @var list<SportMatch> $playedMatches the eight already finished ones, kickoff-ordered */
        $playedMatches = array_slice($matches, 0, 8);

        // -- World A: the big PAID GLOBAL competition ----------------------
        // 24 members with designed point totals (see PLAN_CODE_POINTS): a real
        // podium, a genuine tie on 32 b, and the primary dev user mid-table at
        // rank 7 — exactly what the leaderboard redesign needs to render.
        $worldCup = $this->createCompetition(
            $manager,
            id: self::WORLD_CUP_COMPETITION_ID,
            matchSource: $worldCupSource,
            owner: $admin,
            name: self::WORLD_CUP_COMPETITION_NAME,
            description: 'Velká veřejná tipovačka na MS 2026. Vstupné se strhává z kreditů.',
            pin: null,
            linkToken: null,
            createdAt: $seededAt,
            monetization: CompetitionMonetization::Boosts,
            isGlobal: true,
            entryFeeCredits: self::WORLD_CUP_COMPETITION_ENTRY_FEE,
        );
        $this->provisionDefaultRules($manager, $worldCup, $seededAt);

        /** @var list<array{User, string}> $worldCupPlans */
        $worldCupPlans = [
            [$users[5], 'eeeeoohm'],   // 47
            [$users[19], 'eeeeohmm'],  // 44
            [$admin, 'eeeehmmm'],      // 41
            [$users[2], 'eeeoohhm'],   // 38
            [$users[11], 'eeeoommm'],  // 36
            [$users[20], 'eeeohmmm'],  // 34
            [$verified, 'eeehhmmm'],   // 32 — the primary dev user, rank 7
            [$users[1], 'eeoooomm'],   // 32 — the tie partner
            [$users[3], 'eeooommm'],   // 29
            [$users[4], 'eeoohmmm'],   // 27
            [$users[6], 'eeohhmmm'],   // 25
            [$users[7], 'eeohmmmm'],   // 24
            [$users[9], 'eehhmmmm'],   // 22
            [$users[10], 'eehmmmmm'],  // 21
            [$users[12], 'eooommmm'],  // 19
            [$users[13], 'eoohhmmm'],  // 18
            [$users[14], 'eoohmmmm'],  // 17
            [$users[15], 'eohhmmmm'],  // 15
            [$users[16], 'eohmmmmm'],  // 14
            [$users[17], 'oooommmm'],  // 12
            [$users[18], 'ooohhmmm'],  // 11
            [$users[21], 'ooommmmm'],  // 9
            [$users[22], 'oohmmmmm'],  // 7
            [$users[23], 'ohmmmmmm'],  // 4
        ];

        $this->addMembers($manager, $worldCup, array_map(
            static fn (array $plan): User => $plan[0],
            $worldCupPlans,
        ), $seededAt);

        $this->createPlannedGuesses($manager, $worldCup, $worldCupPlans, $playedMatches, $seededAt);

        // Tips on the still-open fixtures, with thinning participation, so every
        // „Rozložení tipů" bar has a different shape and the live match already
        // has a full set of tips waiting for its result.
        foreach ([8 => 24, 9 => 18, 10 => 12, 11 => 6] as $matchIndex => $tipperCount) {
            foreach (array_slice($worldCupPlans, 0, $tipperCount) as $offset => [$member]) {
                $this->createEvaluatedGuess(
                    $manager,
                    $member,
                    $matches[$matchIndex],
                    $worldCup,
                    ($offset + $matchIndex) % 4,
                    ($offset + 2) % 3,
                    $seededAt,
                );
            }
        }

        // Honest partial-sum standings so the leaderboard Δ shows real movement:
        // after round 1 (3 matches), after round 2 (6) and the current state (8).
        // Δ is measured against the latest day strictly BEFORE today ⇒ the
        // today−4 board, i.e. the round-3 reshuffle.
        foreach ([[3, 10], [6, 4], [8, 0]] as [$matchesPlayed, $daysAgo]) {
            $this->createStandingSnapshot(
                $manager,
                $worldCup,
                $worldCupPlans,
                $matchesPlayed,
                $today->modify(sprintf('-%d days', $daysAgo)),
            );
        }

        // Boosts: the dev user bought the „Konkrétní tipy kolegů" boost here
        // (⇒ „Rozložení tipů" UNLOCKED), one other member bought the cheaper
        // distribution bar, the remaining 22 bought nothing.
        $this->createBoost($manager, '019bbbbb-0000-7000-8000-0000000000e2', $worldCup, $verified, BoostType::OthersTips, $seededAt);
        $this->createBoost($manager, '019bbbbb-0000-7000-8000-0000000000e3', $worldCup, $users[5], BoostType::TipDistribution, $seededAt);

        // -- World D: same source, selection mode Teams --------------------
        $this->createTeamFilterWorld($manager, $worldCupSource, $teams, $matches, $verified, $users, $seededAt);

        // -- World B: small PRIVATE premium competition --------------------
        [$neighbours, $neighbourMembers] = $this->createNeighboursWorld($manager, $football, $verified, $users, $today, $seededAt);

        // -- World C: finished competition ---------------------------------
        $this->createWinterWorld($manager, $football, $verified, $users, $today, $seededAt);

        // -- World E: the competition that has not started yet -------------
        [$upcoming, $upcomingMembers] = $this->createUpcomingWorld($manager, $football, $verified, $users, $today, $seededAt);

        // -- Credits -------------------------------------------------------
        $this->createDevWallets(
            $manager,
            $admin,
            $verified,
            $users[5],
            $worldCup,
            [
                [$neighbours, $neighbourMembers, '-10 days'],
                [$upcoming, $upcomingMembers, '-8 days'],
            ],
            $seededAt,
        );
    }

    private function createVerifiedUser(
        ObjectManager $manager,
        int $index,
        string $nickname,
        string $firstName,
        string $lastName,
        \DateTimeImmutable $now,
    ): User {
        $uuid = Uuid::fromString(sprintf('01933333-0000-7000-8000-%012x', 0x100 + $index));
        $user = new User(
            id: $uuid,
            email: sprintf('%s@tipovacka.dev', $nickname),
            password: null,
            nickname: $nickname,
            createdAt: $now,
        );
        $user->changePassword(
            $this->passwordHasher->hashPassword($user, AppFixtures::DEFAULT_PASSWORD),
            $now,
        );
        $user->markAsVerified($now);
        $user->updateProfile($firstName, $lastName, null, $now);
        $user->popEvents();
        $manager->persist($user);

        return $user;
    }

    /**
     * @param list<array{string, int}> $extraRules optional rules on top of the four defaults,
     *                                             as [identifier, points]
     */
    private function provisionDefaultRules(
        ObjectManager $manager,
        Competition $competition,
        \DateTimeImmutable $now,
        array $extraRules = [],
    ): void {
        foreach ([
            ['exact_score', 5],
            ['correct_outcome', 3],
            ['correct_home_goals', 1],
            ['correct_away_goals', 1],
            ...$extraRules,
        ] as [$identifier, $points]) {
            $manager->persist(new CompetitionRuleConfiguration(
                id: Uuid::v7(),
                competition: $competition,
                ruleIdentifier: $identifier,
                enabled: true,
                points: $points,
                now: $now,
            ));
        }
    }

    /**
     * NOTE — `$linkToken` must be 48 chars of `[a-f0-9]`: that is what the
     * `competition_join_by_link` route requires, and a token outside it makes the
     * competition detail page blow up when it renders the invite link.
     */
    private function createCompetition(
        ObjectManager $manager,
        string $id,
        MatchSource $matchSource,
        User $owner,
        string $name,
        ?string $description,
        ?string $pin,
        ?string $linkToken,
        \DateTimeImmutable $createdAt,
        CompetitionMatchSelectionMode $selectionMode = CompetitionMatchSelectionMode::All,
        CompetitionMonetization $monetization = CompetitionMonetization::None,
        bool $isGlobal = false,
        int $entryFeeCredits = 0,
    ): Competition {
        $competition = new Competition(
            id: Uuid::fromString($id),
            matchSource: $matchSource,
            owner: $owner,
            name: $name,
            description: $description,
            pin: $pin,
            shareableLinkToken: $linkToken,
            createdAt: $createdAt,
            selectionMode: $selectionMode,
            monetization: $monetization,
            isGlobal: $isGlobal,
            entryFeeCredits: $entryFeeCredits,
        );
        $competition->popEvents();
        $manager->persist($competition);

        $layer = new CompetitionSource(
            id: Uuid::v7(),
            competition: $competition,
            matchSource: $matchSource,
            addedAt: $createdAt,
            selectionMode: $selectionMode,
        );
        $competition->attachSource($layer);
        $manager->persist($layer);

        return $competition;
    }

    /**
     * @param list<User> $members
     */
    private function addMembers(ObjectManager $manager, Competition $competition, array $members, \DateTimeImmutable $now): void
    {
        foreach ($members as $user) {
            $membership = new Membership(
                id: Uuid::v7(),
                competition: $competition,
                user: $user,
                joinedAt: $now,
            );
            $membership->popEvents();
            $manager->persist($membership);
        }
    }

    private function finishedMatch(
        ObjectManager $manager,
        MatchSource $matchSource,
        string $idSuffix,
        string $homeTeam,
        string $awayTeam,
        string $kickoff,
        ?string $venue,
        int $homeScore,
        int $awayScore,
        \DateTimeImmutable $now,
    ): SportMatch {
        $match = $this->baseMatch($manager, $matchSource, $idSuffix, $homeTeam, $awayTeam, $kickoff, $venue, $now);
        $match->setFinalScore($homeScore, $awayScore, null, null, null, $now);
        $match->popEvents();

        return $match;
    }

    private function liveMatch(
        ObjectManager $manager,
        MatchSource $matchSource,
        string $idSuffix,
        string $homeTeam,
        string $awayTeam,
        string $kickoff,
        ?string $venue,
        \DateTimeImmutable $now,
    ): SportMatch {
        $match = $this->baseMatch($manager, $matchSource, $idSuffix, $homeTeam, $awayTeam, $kickoff, $venue, $now);
        $match->beginLive($now);
        $match->popEvents();

        return $match;
    }

    private function scheduledMatch(
        ObjectManager $manager,
        MatchSource $matchSource,
        string $idSuffix,
        string $homeTeam,
        string $awayTeam,
        string $kickoff,
        ?string $venue,
        \DateTimeImmutable $now,
    ): SportMatch {
        $match = $this->baseMatch($manager, $matchSource, $idSuffix, $homeTeam, $awayTeam, $kickoff, $venue, $now);
        $match->popEvents();

        return $match;
    }

    private function baseMatch(
        ObjectManager $manager,
        MatchSource $matchSource,
        string $idSuffix,
        string $homeTeam,
        string $awayTeam,
        string $kickoff,
        ?string $venue,
        \DateTimeImmutable $now,
    ): SportMatch {
        $match = new SportMatch(
            id: Uuid::fromString('019ddddd-0000-7000-8000-'.str_pad($idSuffix, 12, '0', STR_PAD_LEFT)),
            matchSource: $matchSource,
            homeTeam: $this->teamResolver->resolve($matchSource, $homeTeam, $now),
            awayTeam: $this->teamResolver->resolve($matchSource, $awayTeam, $now),
            kickoffAt: new \DateTimeImmutable($kickoff, new \DateTimeZone('UTC')),
            venue: $venue,
            // createdAt = the dev seed "now" (2025-06-15), like AppFixtures — NOT a
            // pre-history date. Under S07 the lock moment is a competition's earliest
            // included kickoff (often a past finished match), so a match created before
            // that moment would be treated as pre-lock and lock immediately. Seeding
            // createdAt at "now" makes the upcoming scheduled matches count as
            // late-added (createdAt > lock moment) ⇒ tippable until their own kickoff,
            // so the dev browser actually has tippable fixtures.
            createdAt: $now,
        );
        $manager->persist($match);

        return $match;
    }

    private function createEvaluatedGuess(
        ObjectManager $manager,
        User $user,
        SportMatch $match,
        Competition $competition,
        int $homeScore,
        int $awayScore,
        \DateTimeImmutable $now,
    ): Guess {
        // Historical matches: tipped shortly before kickoff. Upcoming matches (kickoff in
        // real future): clamp to real "now" so the UI doesn't render submittedAt in the future.
        $realNow = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $preKickoff = $match->kickoffAt->modify('-2 hours');
        $submittedAt = $preKickoff > $realNow ? $realNow : $preKickoff;

        $guess = new Guess(
            id: Uuid::v7(),
            user: $user,
            sportMatch: $match,
            competition: $competition,
            homeScore: $homeScore,
            awayScore: $awayScore,
            submittedAt: $submittedAt,
        );
        $guess->popEvents();
        $manager->persist($guess);

        if (!$match->isFinished) {
            return $guess;
        }

        $evaluation = new GuessEvaluation(
            id: Uuid::v7(),
            guess: $guess,
            evaluatedAt: $now,
        );

        foreach ($this->scorePoints($homeScore, $awayScore, (int) $match->homeScore, (int) $match->awayScore) as [$identifier, $points]) {
            $evaluation->addRulePoints(new GuessEvaluationRulePoints(
                id: Uuid::v7(),
                evaluation: $evaluation,
                ruleIdentifier: $identifier,
                points: $points,
            ));
        }

        $manager->persist($evaluation);

        return $guess;
    }

    /**
     * Default rule weights: exact_score=5, correct_outcome=3, correct_home_goals=1, correct_away_goals=1.
     * Returns only rules that actually scored (matches the convention used in AppFixtures).
     *
     * @return list<array{string, int}>
     */
    private function scorePoints(int $gh, int $ga, int $ah, int $aw): array
    {
        $rules = [];

        if ($gh === $ah && $ga === $aw) {
            $rules[] = ['exact_score', 5];
        }

        if ($this->outcome($gh, $ga) === $this->outcome($ah, $aw)) {
            $rules[] = ['correct_outcome', 3];
        }

        if ($gh === $ah) {
            $rules[] = ['correct_home_goals', 1];
        }

        if ($ga === $aw) {
            $rules[] = ['correct_away_goals', 1];
        }

        return $rules;
    }

    private function outcome(int $home, int $away): int
    {
        return $home <=> $away;
    }

    // =====================================================================
    // item 03 — development worlds
    // =====================================================================

    /**
     * The national teams of the World-Cup source. GLOBAL directory teams (curated
     * source ⇒ shared pool, see TeamResolver), created explicitly rather than
     * through the resolver because a team plays several matches and the resolver
     * can only find rows that are already flushed.
     *
     * @return array<string, Team> team name → Team
     */
    private function createWorldCupTeams(ObjectManager $manager, Sport $football, \DateTimeImmutable $now): array
    {
        // Non-European nations on purpose: the Euro 2024 source above already owns
        // the European names in the shared global directory, and a directory team
        // name is unique per sport.
        /** @var list<array{string, string, string, string, string}> $seeds name, short, country, brand color, uuid */
        $seeds = [
            ['Česko', 'CZE', 'CZ', '#11457E', self::TEAM_CESKO_ID],
            ['Slovensko', 'SVK', 'SK', '#0B4EA2', self::TEAM_SLOVENSKO_ID],
            ['Brazílie', 'BRA', 'BR', '#009C3B', '019ddddd-0000-7000-8000-0000000ff003'],
            ['Argentina', 'ARG', 'AR', '#75AADB', '019ddddd-0000-7000-8000-0000000ff004'],
            ['Mexiko', 'MEX', 'MX', '#006341', '019ddddd-0000-7000-8000-0000000ff005'],
            ['Kanada', 'CAN', 'CA', '#D80621', '019ddddd-0000-7000-8000-0000000ff006'],
            ['Uruguay', 'URU', 'UY', '#7B9FD4', '019ddddd-0000-7000-8000-0000000ff007'],
            ['Japonsko', 'JPN', 'JP', '#1B2A6B', '019ddddd-0000-7000-8000-0000000ff008'],
            ['Maroko', 'MAR', 'MA', '#C1272D', '019ddddd-0000-7000-8000-0000000ff009'],
            ['USA', 'USA', 'US', '#3C3B6E', '019ddddd-0000-7000-8000-0000000ff00a'],
            ['Senegal', 'SEN', 'SN', '#00853F', '019ddddd-0000-7000-8000-0000000ff00b'],
            ['Korea', 'KOR', 'KR', '#0F64CD', '019ddddd-0000-7000-8000-0000000ff00c'],
        ];

        $teams = [];

        foreach ($seeds as [$name, $shortName, $country, $brandColor, $id]) {
            $teams[$name] = $this->createTeam($manager, $id, $football, null, $name, $now, $shortName, $country, $brandColor);
        }

        return $teams;
    }

    /**
     * The World-Cup fixtures, kickoff-ordered. Indices 0–7 are finished (with
     * results), 8 is live right now, 9–11 are upcoming playoff matches — three
     * distinct group rounds plus two playoff rounds, so „Poslední kolo" always
     * resolves to the group round that is currently being played.
     *
     * @param array<string, Team> $teams
     *
     * @return list<SportMatch>
     */
    private function createWorldCupMatches(
        ObjectManager $manager,
        MatchSource $source,
        array $teams,
        \DateTimeImmutable $today,
        \DateTimeImmutable $seededAt,
    ): array {
        /** @var list<array{string, string, string, int, int, string, bool, ?int, ?int}> $seeds */
        $seeds = [
            // id suffix, home, away, days from today, hour, round, isPlayoff, home score, away score
            ['0000000fa001', 'Česko', 'Slovensko', -14, 18, self::ROUND_GROUP_1, false, 2, 1],
            ['0000000fa002', 'Brazílie', 'Mexiko', -13, 15, self::ROUND_GROUP_1, false, 1, 1],
            ['0000000fa003', 'Argentina', 'Kanada', -13, 18, self::ROUND_GROUP_1, false, 3, 0],
            ['0000000fa004', 'Česko', 'Brazílie', -9, 18, self::ROUND_GROUP_2, false, 0, 2],
            ['0000000fa005', 'Mexiko', 'Argentina', -8, 15, self::ROUND_GROUP_2, false, 2, 2],
            ['0000000fa006', 'Uruguay', 'Japonsko', -8, 18, self::ROUND_GROUP_2, false, 1, 0],
            ['0000000fa007', 'Česko', 'Maroko', -3, 18, self::ROUND_GROUP_3, false, 1, 1],
            ['0000000fa008', 'USA', 'Senegal', -2, 18, self::ROUND_GROUP_3, false, 2, 0],
            ['0000000fa010', 'Česko', 'Brazílie', 2, 18, self::ROUND_LAST_16, true, null, null],
            ['0000000fa011', 'Argentina', 'Mexiko', 3, 18, self::ROUND_LAST_16, true, null, null],
            ['0000000fa012', 'Uruguay', 'Maroko', 7, 18, self::ROUND_QUARTER_FINAL, true, null, null],
        ];

        $matches = [];

        foreach ($seeds as [$idSuffix, $home, $away, $dayOffset, $hour, $round, $isPlayoff, $homeScore, $awayScore]) {
            $match = $this->worldMatch(
                $manager,
                $idSuffix,
                $source,
                $teams[$home],
                $teams[$away],
                $today->modify(sprintf('%+d days', $dayOffset))->setTime($hour, 0),
                $round,
                $isPlayoff,
                $seededAt,
            );

            if (null !== $homeScore && null !== $awayScore) {
                $match->setFinalScore($homeScore, $awayScore, null, null, null, $seededAt);
                $match->popEvents();
            }

            $matches[] = $match;
        }

        // The one match being played right now — it makes „Poslední kolo" resolve
        // to the group round in progress and gives the dashboard a live row.
        $live = $this->worldMatch(
            $manager,
            '0000000fa009',
            $source,
            $teams['Korea'],
            $teams['Uruguay'],
            $seededAt->modify('-1 hour'),
            self::ROUND_GROUP_3,
            false,
            $seededAt,
        );
        $live->beginLive($seededAt);
        $live->popEvents();

        // Keep the list kickoff-ordered: the live match sits right after the eight
        // finished ones and before the upcoming playoff fixtures.
        array_splice($matches, 8, 0, [$live]);

        return $matches;
    }

    /**
     * World D — the same curated source seen through a TEAM FILTER (selection
     * mode Teams, pinned to Česko + Slovensko). Reproduces „why is this match not
     * in that competition" deliberately: only the four Czech/Slovak fixtures are
     * in, the playoff one among them auto-joins by the always-in rule.
     *
     * @param array<string, Team> $teams
     * @param list<SportMatch>    $matches
     * @param array<int, User>    $users
     */
    private function createTeamFilterWorld(
        ObjectManager $manager,
        MatchSource $source,
        array $teams,
        array $matches,
        User $verified,
        array $users,
        \DateTimeImmutable $seededAt,
    ): void {
        $competition = $this->createCompetition(
            $manager,
            id: self::TEAM_FILTER_COMPETITION_ID,
            matchSource: $source,
            owner: $users[3],
            name: self::TEAM_FILTER_COMPETITION_NAME,
            description: 'Tipujeme jen zápasy Česka a Slovenska — ostatní nás neberou.',
            pin: '40000001',
            linkToken: str_repeat('c', 48),
            createdAt: $seededAt,
            selectionMode: CompetitionMatchSelectionMode::Teams,
            monetization: CompetitionMonetization::Boosts,
        );
        $this->provisionDefaultRules($manager, $competition, $seededAt);

        foreach (['Česko', 'Slovensko'] as $teamName) {
            $manager->persist(new CompetitionTeamFilter(
                id: Uuid::v7(),
                competition: $competition,
                competitionSource: $competition->sources[0],
                team: $teams[$teamName],
                addedAt: $seededAt,
            ));
        }

        /** @var list<array{User, string}> $plans */
        $plans = [
            [$users[3], 'eoh'],   // 14
            [$verified, 'eom'],   // 13 — nobody here bought a boost ⇒ locked „Rozložení tipů"
            [$users[4], 'ehm'],   // 11
            [$users[6], 'ooo'],   // 9
            [$users[12], 'ohm'],  // 4
            [$users[16], 'mmm'],  // 0 — the 0 % accuracy row
        ];

        $this->addMembers($manager, $competition, array_map(
            static fn (array $plan): User => $plan[0],
            $plans,
        ), $seededAt);

        // The finished matches Česko played (indices 0, 3 and 6 of the source).
        $this->createPlannedGuesses($manager, $competition, $plans, [$matches[0], $matches[3], $matches[6]], $seededAt);

        // Everyone also tipped the Czech playoff fixture that auto-joined by the
        // playoff-always-in rule. Nobody here owns a boost, so that row is the
        // canonical LOCKED „Rozložení tipů" of the whole dev world.
        foreach ($plans as $offset => [$member]) {
            $this->createEvaluatedGuess($manager, $member, $matches[9], $competition, $offset % 3, ($offset + 1) % 2, $seededAt);
        }
    }

    /**
     * World B — a small PRIVATE competition from scratch, organized by the primary
     * dev user: premium ON for everyone, an anonymous member (no e-mail) and a
     * pending e-mail invitation, so all three member states have UI to render.
     *
     * It is also the ONE dev competition with the `scorer_hit` rule on, backed by
     * a per-team roster and real goal timelines (B31) — the guess form's „Tip na
     * střelce" picker is unreachable after a `db:reset` without it.
     *
     * @param array<int, User> $users
     *
     * @return array{Competition, list<User>} the competition and its non-owner members
     */
    private function createNeighboursWorld(
        ObjectManager $manager,
        Sport $football,
        User $verified,
        array $users,
        \DateTimeImmutable $today,
        \DateTimeImmutable $seededAt,
    ): array {
        $source = new MatchSource(
            id: Uuid::fromString(self::NEIGHBOURS_SOURCE_ID),
            sport: $football,
            owner: $verified,
            kind: MatchSourceKind::Private,
            name: self::NEIGHBOURS_SOURCE_NAME,
            description: 'Zápasy si zadáváme sami — hřiště za sokolovnou.',
            startAt: $today->modify('-7 days')->setTime(0, 0),
            endAt: $today->modify('+21 days')->setTime(23, 59, 59),
            createdAt: $seededAt,
        );
        $source->popEvents();
        $manager->persist($source);

        // LOCAL teams of the private source — office-pool names never reach the
        // shared directory (TeamResolver's hybrid scope rule).
        $teams = [];

        foreach ([
            ['Sokol Dolní', '019ddddd-0000-7000-8000-0000000ff021'],
            ['Sokol Horní', '019ddddd-0000-7000-8000-0000000ff022'],
            ['Kanonýři', '019ddddd-0000-7000-8000-0000000ff023'],
            ['Rebelové', '019ddddd-0000-7000-8000-0000000ff024'],
            ['Dynamo Zahrádka', '019ddddd-0000-7000-8000-0000000ff025'],
            ['Old Boys', '019ddddd-0000-7000-8000-0000000ff026'],
        ] as [$name, $id]) {
            $teams[$name] = $this->createTeam($manager, $id, $football, $source, $name, $seededAt, null, null, null);
        }

        // The rosters behind the scorer picker (B31), keyed team name → player
        // name → Player so the goal events and scorer tips below can name them.
        /** @var array<string, array<string, Player>> $players */
        $players = [];
        $playerIndex = 0;

        foreach (self::NEIGHBOURS_ROSTERS as $teamName => $names) {
            foreach ($names as $playerName) {
                $players[$teamName][$playerName] = $player = new Player(
                    id: Uuid::fromString(sprintf('019ddddd-0000-7000-8000-0000000fe0%02x', ++$playerIndex)),
                    team: $teams[$teamName],
                    name: $playerName,
                    createdAt: $seededAt,
                );
                $manager->persist($player);
            }
        }

        /** @var list<array{string, string, string, int, int, string, ?int, ?int}> $seeds */
        $seeds = [
            ['0000000fb001', 'Sokol Dolní', 'Kanonýři', -6, 17, '1. kolo', 3, 2],
            ['0000000fb002', 'Rebelové', 'Old Boys', -6, 19, '1. kolo', 1, 1],
            ['0000000fb003', 'Sokol Horní', 'Dynamo Zahrádka', 1, 18, '2. kolo', null, null],
            ['0000000fb004', 'Kanonýři', 'Rebelové', 2, 18, '2. kolo', null, null],
            ['0000000fb005', 'Old Boys', 'Sokol Dolní', 6, 18, '3. kolo', null, null],
            ['0000000fb006', 'Dynamo Zahrádka', 'Sokol Horní', 9, 18, '3. kolo', null, null],
        ];

        $matches = [];

        foreach ($seeds as [$idSuffix, $home, $away, $dayOffset, $hour, $round, $homeScore, $awayScore]) {
            $match = $this->worldMatch(
                $manager,
                $idSuffix,
                $source,
                $teams[$home],
                $teams[$away],
                $today->modify(sprintf('%+d days', $dayOffset))->setTime($hour, 0),
                $round,
                false,
                $seededAt,
            );

            if (null !== $homeScore && null !== $awayScore) {
                $match->setFinalScore($homeScore, $awayScore, null, null, null, $seededAt);
                $match->popEvents();
            }

            $matches[] = $match;
        }

        // Goal timelines of the two played fixtures, so the roster is the source
        // of truth the `scorer_hit` rule reads ({@see MatchEventRepository}) and
        // not a decorative list. `Radek Bureš` scores twice on purpose — the rule
        // counts a correctly tipped player ONCE, however many goals they got.
        /** @var list<array{int, string, string, int}> $goals match index, side, player, minute */
        $goals = [
            [0, MatchSide::Home->value, 'Radek Bureš', 12],
            [0, MatchSide::Home->value, 'Ondřej Klíma', 34],
            [0, MatchSide::Away->value, 'Ivan Zeman', 51],
            [0, MatchSide::Home->value, 'Radek Bureš', 78],
            [0, MatchSide::Away->value, 'Štěpán Bažant', 88],
            [1, MatchSide::Home->value, 'Bohuslav Kolář', 23],
            [1, MatchSide::Away->value, 'Alois Pekař', 67],
        ];

        foreach ($goals as [$matchIndex, $side, $playerName, $minute]) {
            $match = $matches[$matchIndex];
            $team = MatchSide::Home->value === $side ? $match->homeTeam : $match->awayTeam;

            $manager->persist(new MatchEvent(
                id: Uuid::v7(),
                sportMatch: $match,
                type: MatchEventType::Goal,
                side: MatchSide::from($side),
                minute: $minute,
                player: $players[$team->name][$playerName],
                createdAt: $seededAt,
            ));
        }

        $competition = $this->createCompetition(
            $manager,
            id: self::NEIGHBOURS_COMPETITION_ID,
            matchSource: $source,
            owner: $verified,
            name: self::NEIGHBOURS_COMPETITION_NAME,
            description: 'Sousedská tipovačka — prémiová, všichni vidí všechno.',
            pin: '40000002',
            linkToken: str_repeat('b', 48),
            createdAt: $seededAt,
            monetization: CompetitionMonetization::Premium,
        );
        // Premium ON for EVERYONE: the manager turned all three toggles on, so the
        // whole group sees the distribution bar and each other's tips.
        $competition->setPremiumFeatures(
            showDistribution: true,
            showOthersTips: true,
            allowTipChanges: true,
            tipChangeOffsetMinutes: 60,
            now: $seededAt,
        );
        // „Trefený střelec" is enabled HERE and nowhere else in the dev worlds
        // (B31): World B is the only competition that is both still open for
        // tipping and backed by a roster, so this is where the guess form renders
        // its scorer picker after a plain `db:reset`.
        $this->provisionDefaultRules($manager, $competition, $seededAt, [
            [ScorerHitRule::IDENTIFIER, 2], // = ScorerHitRule::$defaultPoints
        ]);

        // The anonymous member: no e-mail, no password, only a profile name.
        $anonymous = new User(
            id: Uuid::fromString(self::NEIGHBOURS_ANONYMOUS_USER_ID),
            email: null,
            password: null,
            nickname: null,
            createdAt: $seededAt,
        );
        $anonymous->updateProfile(
            self::NEIGHBOURS_ANONYMOUS_FIRST_NAME,
            self::NEIGHBOURS_ANONYMOUS_LAST_NAME,
            null,
            $seededAt,
        );
        $anonymous->popEvents();
        $manager->persist($anonymous);

        /** @var list<User> $nonOwnerMembers */
        $nonOwnerMembers = [$users[1], $users[9], $users[10], $users[24], $anonymous];

        $this->addMembers($manager, $competition, [$verified, ...$nonOwnerMembers], $seededAt);

        // Premium is per player and already paid for by the organizer.
        foreach ($nonOwnerMembers as $member) {
            $charge = new CompetitionPremiumCharge(
                id: Uuid::v7(),
                competition: $competition,
                member: $member,
                amount: PricingConfig::PREMIUM_PER_PLAYER,
                createdAt: $seededAt,
            );
            $charge->markCharged($seededAt);
            $charge->popEvents();
            $manager->persist($charge);
        }

        $invitation = new CompetitionInvitation(
            id: Uuid::fromString(self::NEIGHBOURS_INVITATION_ID),
            competition: $competition,
            inviter: $verified,
            email: self::NEIGHBOURS_INVITATION_EMAIL,
            token: self::NEIGHBOURS_INVITATION_TOKEN,
            createdAt: $seededAt,
            expiresAt: $seededAt->modify('+7 days'),
        );
        $invitation->popEvents();
        $manager->persist($invitation);

        /** @var list<array{User, string}> $plans */
        $plans = [
            [$verified, 'eo'],      // 13
            [$users[1], 'eh'],      // 11
            [$users[9], 'oo'],      // 6
            [$users[10], 'oh'],     // 4
            [$users[24], 'hm'],     // 1
            [$anonymous, 'mo'],     // 3
        ];

        $this->createPlannedGuesses($manager, $competition, $plans, [$matches[0], $matches[1]], $seededAt);

        // Half the group already tipped the next fixture — the other half has not,
        // which is exactly the „kdo ještě netipoval" state the detail page shows.
        foreach (array_slice($plans, 0, 3) as $offset => [$member]) {
            $this->createEvaluatedGuess($manager, $member, $matches[2], $competition, $offset % 3, ($offset + 1) % 2, $seededAt);
        }

        // The fixture after it has the WHOLE group's tips, deliberately split
        // 3 × 1 / 1 × X / 2 × 2. Premium shows the distribution to everyone here,
        // and that match's deadline is still ahead, so this is the UNLOCKED half
        // of the premium „Rozložení tipů" (B11) — the locked half is World E,
        // so both are reachable without ever touching a toggle.
        //
        // Some of them tipped scorers too — the dev user on BOTH sides — so the
        // read surfaces („Střelci: …" in the tip lists and the matrix cell) show
        // the feature and the picker opens PRE-FILLED on this fixture. Offsets
        // without an entry keep a plain score tip, so both shapes sit on one
        // screen; the last two fixtures stay scorer-free for the empty-picker and
        // „Přidat hráče …" walkthrough.
        /** @var array<int, list<array{MatchSide, string}>> $scorerTips */
        $scorerTips = [
            0 => [[MatchSide::Home, 'Ivan Zeman'], [MatchSide::Away, 'Květoslav Tichý']],
            1 => [[MatchSide::Home, 'Štěpán Bažant']],
            4 => [[MatchSide::Away, 'Bohuslav Kolář']],
        ];

        foreach ([[0, 2, 1], [1, 1, 0], [2, 3, 1], [3, 1, 1], [4, 0, 2], [5, 1, 2]] as [$offset, $homeGuess, $awayGuess]) {
            [$member] = $plans[$offset];
            $guess = $this->createEvaluatedGuess($manager, $member, $matches[3], $competition, $homeGuess, $awayGuess, $seededAt);

            foreach ($scorerTips[$offset] ?? [] as [$side, $playerName]) {
                $team = MatchSide::Home === $side ? $matches[3]->homeTeam : $matches[3]->awayTeam;

                $guess->addScorer(new GuessScorer(
                    id: Uuid::v7(),
                    guess: $guess,
                    player: $players[$team->name][$playerName],
                    side: $side,
                    createdAt: $seededAt,
                ));
            }
        }

        return [$competition, $nonOwnerMembers];
    }

    /**
     * World C — everything played and the source completed: the „Ukončeno" states,
     * a frozen final standing and a tie at the top that the organizer already
     * resolved by hand ({@see LeaderboardTieResolution}). Monetized as boosts on
     * purpose, so „a boost can no longer be bought once it is over" has a subject.
     *
     * @param array<int, User> $users
     */
    private function createWinterWorld(
        ObjectManager $manager,
        Sport $football,
        User $verified,
        array $users,
        \DateTimeImmutable $today,
        \DateTimeImmutable $seededAt,
    ): void {
        $source = new MatchSource(
            id: Uuid::fromString(self::WINTER_SOURCE_ID),
            sport: $football,
            owner: $users[14],
            kind: MatchSourceKind::Curated,
            name: self::WINTER_SOURCE_NAME,
            description: 'Zimní turnaj na umělce — dohráno, výsledky jsou konečné.',
            startAt: $today->modify('-33 days')->setTime(0, 0),
            endAt: $today->modify('-25 days')->setTime(23, 59, 59),
            createdAt: $seededAt,
        );
        $source->popEvents();
        $manager->persist($source);

        $teams = [];

        foreach ([
            ['FK Vlci', 'VLK', '#8A6D3B', '019ddddd-0000-7000-8000-0000000ff031'],
            ['FK Rysi', 'RYS', '#3B6E8A', '019ddddd-0000-7000-8000-0000000ff032'],
            ['FK Sokoli', 'SOK', '#6E3B8A', '019ddddd-0000-7000-8000-0000000ff033'],
            ['FK Jestřábi', 'JES', '#8A3B3B', '019ddddd-0000-7000-8000-0000000ff034'],
        ] as [$name, $shortName, $brandColor, $id]) {
            $teams[$name] = $this->createTeam($manager, $id, $football, null, $name, $seededAt, $shortName, 'CZ', $brandColor);
        }

        /** @var list<array{string, string, string, int, int, string, int, int}> $seeds */
        $seeds = [
            ['0000000fc001', 'FK Vlci', 'FK Rysi', -32, 18, 'Semifinále', 2, 1],
            ['0000000fc002', 'FK Sokoli', 'FK Jestřábi', -32, 20, 'Semifinále', 0, 1],
            ['0000000fc003', 'FK Rysi', 'FK Sokoli', -26, 17, 'O 3. místo', 3, 1],
            ['0000000fc004', 'FK Vlci', 'FK Jestřábi', -25, 19, 'Finále', 1, 1],
        ];

        $matches = [];

        foreach ($seeds as [$idSuffix, $home, $away, $dayOffset, $hour, $round, $homeScore, $awayScore]) {
            $match = $this->worldMatch(
                $manager,
                $idSuffix,
                $source,
                $teams[$home],
                $teams[$away],
                $today->modify(sprintf('%+d days', $dayOffset))->setTime($hour, 0),
                $round,
                true,
                $seededAt,
            );
            $match->setFinalScore($homeScore, $awayScore, null, null, null, $seededAt);
            $match->popEvents();
            $matches[] = $match;
        }

        $source->markCompleted($today->modify('-24 days')->setTime(12, 0));
        $source->popEvents();

        $competition = $this->createCompetition(
            $manager,
            id: self::WINTER_COMPETITION_ID,
            matchSource: $source,
            owner: $users[14],
            name: self::WINTER_COMPETITION_NAME,
            description: 'Dohraná soutěž — konečné pořadí včetně ručně rozhodnuté shody.',
            pin: '40000003',
            linkToken: str_repeat('f', 48),
            createdAt: $seededAt,
            monetization: CompetitionMonetization::Boosts,
        );
        $this->provisionDefaultRules($manager, $competition, $seededAt);

        /** @var list<array{User, string}> $plans */
        $plans = [
            [$users[14], 'eeoh'],  // 24 — tied first
            [$verified, 'eeho'],   // 24 — tied first
            [$users[2], 'eohh'],   // 15
            [$users[5], 'eomm'],   // 13
            [$users[11], 'ehmm'],  // 11
            [$users[19], 'ooom'],  // 9
            [$users[21], 'ohmm'],  // 4
            [$users[23], 'mmmm'],  // 0
        ];

        $this->addMembers($manager, $competition, array_map(
            static fn (array $plan): User => $plan[0],
            $plans,
        ), $seededAt);

        $this->createPlannedGuesses($manager, $competition, $plans, $matches, $seededAt);

        // The organizer broke the 24 b tie by hand after the final whistle.
        foreach ([[$users[14], 1], [$verified, 2]] as [$member, $rank]) {
            $manager->persist(new LeaderboardTieResolution(
                id: Uuid::v7(),
                competition: $competition,
                user: $member,
                rank: $rank,
                resolvedAt: $today->modify('-24 days')->setTime(13, 0),
                resolvedBy: $users[14],
            ));
        }
    }

    /**
     * World E — the soutěž that has NOT started yet. Every other dev world is
     * deliberately anchored so that it is already running, which left two states
     * unreachable after a plain `db:reset`:
     *
     * - **„Uzamknout tipy" (B23)** — the action only renders while the competition
     *   is unlocked and has not started. Here the primary dev user is the
     *   organizer and the first kickoff is {@see UPCOMING_FIRST_KICKOFF_DAYS} days
     *   out, so B2's „Ihned / V určený čas" modal is reachable and its picker has
     *   a real (now … first kickoff) range to pick inside.
     * - **the LOCKED premium „Rozložení tipů" (B11)** — monetization premium with
     *   both visibility toggles off (the organizer paid for „Měnit tip" only) and
     *   members who have already tipped matches whose deadline is still ahead, so
     *   every row renders the „Prémium · Zapíná organizátor" strip with a real
     *   player count behind it. World B is the same surface with the toggles ON
     *   ⇒ both halves of the paywall without editing any data by hand.
     *
     * Side benefit: it is the only dev competition whose state is „Nadcházející"
     * ({@see \App\Enum\CompetitionStateFilter::Upcoming}).
     *
     * @param array<int, User> $users
     *
     * @return array{Competition, list<User>} the competition and its non-owner members
     */
    private function createUpcomingWorld(
        ObjectManager $manager,
        Sport $football,
        User $verified,
        array $users,
        \DateTimeImmutable $today,
        \DateTimeImmutable $seededAt,
    ): array {
        // Set up a while ago (an organizer prepares a tournament in advance), but
        // BEFORE the boost purchase the dev wallet books at −5 days: the credit
        // history renders in date order while the ledger's running balance follows
        // insertion order, so the two must agree or the „Zůstatek" column zig-zags.
        $createdAt = $seededAt->modify('-9 days');
        $firstKickoffDay = $today->modify(sprintf('+%d days', self::UPCOMING_FIRST_KICKOFF_DAYS));

        $source = new MatchSource(
            id: Uuid::fromString(self::UPCOMING_SOURCE_ID),
            sport: $football,
            owner: $verified,
            kind: MatchSourceKind::Private,
            name: self::UPCOMING_SOURCE_NAME,
            description: 'Turnaj čtyř týmů, který teprve začne — zápasy si zadáváme sami.',
            startAt: $firstKickoffDay->setTime(0, 0),
            endAt: $today->modify('+12 days')->setTime(23, 59, 59),
            createdAt: $createdAt,
        );
        $source->popEvents();
        $manager->persist($source);

        // LOCAL teams of the private source (TeamResolver's hybrid scope rule) —
        // local names are unique per source only, so they can never collide with
        // the shared global directory.
        $teams = [];

        foreach ([
            ['Sokol Vysoké', '019ddddd-0000-7000-8000-0000000ff041'],
            ['TJ Ždírec', '019ddddd-0000-7000-8000-0000000ff042'],
            ['FC Přibyslav', '019ddddd-0000-7000-8000-0000000ff043'],
            ['SK Polná', '019ddddd-0000-7000-8000-0000000ff044'],
        ] as [$name, $id]) {
            $teams[$name] = $this->createTeam($manager, $id, $football, $source, $name, $createdAt, null, null, null);
        }

        // EVERY kickoff is in the future — that is the whole point of this world.
        /** @var list<array{string, string, string, int, int, int, string}> $seeds */
        $seeds = [
            ['0000000fd001', 'Sokol Vysoké', 'TJ Ždírec', self::UPCOMING_FIRST_KICKOFF_DAYS, 18, 0, '1. kolo'],
            ['0000000fd002', 'FC Přibyslav', 'SK Polná', self::UPCOMING_FIRST_KICKOFF_DAYS, 20, 30, '1. kolo'],
            ['0000000fd003', 'Sokol Vysoké', 'FC Přibyslav', 9, 18, 0, '2. kolo'],
            ['0000000fd004', 'TJ Ždírec', 'SK Polná', 12, 19, 0, '3. kolo'],
        ];

        $matches = [];

        foreach ($seeds as [$idSuffix, $home, $away, $dayOffset, $hour, $minute, $round]) {
            $matches[] = $this->worldMatch(
                $manager,
                $idSuffix,
                $source,
                $teams[$home],
                $teams[$away],
                $today->modify(sprintf('+%d days', $dayOffset))->setTime($hour, $minute),
                $round,
                false,
                $createdAt,
            );
        }

        $competition = $this->createCompetition(
            $manager,
            id: self::UPCOMING_COMPETITION_ID,
            matchSource: $source,
            owner: $verified,
            name: self::UPCOMING_COMPETITION_NAME,
            description: 'Turnaj ještě nezačal — tipy jsou otevřené až do výkopu prvního zápasu.',
            pin: '40000004',
            linkToken: str_repeat('a', 48),
            createdAt: $createdAt,
            monetization: CompetitionMonetization::Premium,
        );
        // Premium, but the organizer switched ON only „Měnit tip" — both visibility
        // toggles stay off, which is exactly what makes „Rozložení tipů" render its
        // LOCKED premium variant for everyone, organizer included (managers get no
        // free pass, see CompetitionEntitlements).
        $competition->setPremiumFeatures(
            showDistribution: false,
            showOthersTips: false,
            allowTipChanges: true,
            tipChangeOffsetMinutes: 60,
            now: $createdAt,
        );
        $this->provisionDefaultRules($manager, $competition, $createdAt);

        /** @var list<User> $nonOwnerMembers */
        $nonOwnerMembers = [$users[1], $users[13], $users[20], $users[24], $users[25]];
        /** @var list<User> $members owner first — the tip plans below index into this */
        $members = [$verified, ...$nonOwnerMembers];

        $this->addMembers($manager, $competition, $members, $createdAt);

        // Premium is charged per player when the organizer enables it, i.e. already
        // (see EnablePremiumHandler). Reconciliation happens at the competition
        // START, which is still ahead ⇒ premiumReconciledAt stays null on purpose.
        foreach ($nonOwnerMembers as $member) {
            $charge = new CompetitionPremiumCharge(
                id: Uuid::v7(),
                competition: $competition,
                member: $member,
                amount: PricingConfig::PREMIUM_PER_PLAYER,
                createdAt: $createdAt,
            );
            $charge->markCharged($createdAt);
            $charge->popEvents();
            $manager->persist($charge);
        }

        // Tips already in, per match: [member offset, home, away]. Thinning
        // participation with a deliberately different 1 / X / 2 split each time, so
        // the locked strip quotes a believable player count on every row and the
        // organizer surfaces have both „tipoval" and „netipoval" members. Nobody
        // has tipped the last match at all — the „no tips yet" teaser.
        /** @var list<list<array{int, int, int}>> $tipPlans */
        $tipPlans = [
            // 3 × 1 / 1 × X / 1 × 2, one member (kristy) has not tipped yet
            [[0, 2, 1], [1, 1, 0], [2, 3, 2], [3, 1, 1], [5, 0, 2]],
            // the whole group, dead even: 2 × 1 / 2 × X / 2 × 2
            [[0, 2, 0], [1, 1, 0], [2, 1, 1], [3, 2, 2], [4, 0, 1], [5, 1, 3]],
            // only three, and the dev user is NOT one of them ⇒ an „open" row
            [[1, 3, 1], [2, 2, 0], [3, 0, 0]],
            [],
        ];

        foreach ($tipPlans as $matchIndex => $tips) {
            foreach ($tips as [$memberOffset, $homeGuess, $awayGuess]) {
                $this->createEvaluatedGuess(
                    $manager,
                    $members[$memberOffset],
                    $matches[$matchIndex],
                    $competition,
                    $homeGuess,
                    $awayGuess,
                    $seededAt,
                );
            }
        }

        return [$competition, $nonOwnerMembers];
    }

    /**
     * The credit side of the dev world. Only the two users that actually SPEND
     * anything get a wallet, and every movement is written through
     * {@see CreditWallet} so the ledger reconciles with the balance. Prices come
     * from {@see PricingConfig}; the entry fee is the competition's own.
     *
     * @param list<array{Competition, list<User>, string}> $premiumGroups premium competition the dev
     *                                                                    user organizes, its charged
     *                                                                    members, and when it was paid
     */
    private function createDevWallets(
        ObjectManager $manager,
        User $admin,
        User $verified,
        User $boostBuyer,
        Competition $worldCup,
        array $premiumGroups,
        \DateTimeImmutable $seededAt,
    ): void {
        $premiumTotal = 0;

        foreach ($premiumGroups as [, $premiumMembers]) {
            $premiumTotal += count($premiumMembers) * PricingConfig::PREMIUM_PER_PLAYER;
        }

        $grant = self::DEV_USER_CREDIT_BALANCE
            + self::WORLD_CUP_COMPETITION_ENTRY_FEE
            + PricingConfig::BOOST_OTHERS_TIPS
            + $premiumTotal;

        $wallet = new CreditWallet(
            id: Uuid::fromString('019a2222-0000-7000-8000-0000000000f1'),
            user: $verified,
            createdAt: $seededAt->modify('-30 days'),
        );
        $manager->persist($wallet);
        $manager->persist($wallet->adjustByAdmin(Uuid::v7(), $grant, 'Vývojářský kredit', $admin, $seededAt->modify('-30 days')));
        $manager->persist($wallet->spend(
            Uuid::v7(),
            self::WORLD_CUP_COMPETITION_ENTRY_FEE,
            CreditTransactionType::EntryFee,
            $seededAt->modify('-20 days'),
            competition: $worldCup,
        ));

        foreach ($premiumGroups as [$premiumCompetition, $premiumMembers, $paidAt]) {
            foreach ($premiumMembers as $member) {
                $manager->persist($wallet->spend(
                    Uuid::v7(),
                    PricingConfig::PREMIUM_PER_PLAYER,
                    CreditTransactionType::PremiumCharge,
                    $seededAt->modify($paidAt),
                    competition: $premiumCompetition,
                    relatedUser: $member,
                ));
            }
        }

        $manager->persist($wallet->spend(
            Uuid::v7(),
            PricingConfig::BOOST_OTHERS_TIPS,
            CreditTransactionType::BoostPurchase,
            $seededAt->modify('-5 days'),
            competition: $worldCup,
            boostType: BoostType::OthersTips->value,
        ));
        $wallet->popEvents();

        // The other boost buyer keeps a token balance — enough to be a realistic
        // second wallet in the admin credit screens, not enough to matter.
        $secondWallet = new CreditWallet(
            id: Uuid::fromString('019a2222-0000-7000-8000-0000000000f2'),
            user: $boostBuyer,
            createdAt: $seededAt->modify('-30 days'),
        );
        $manager->persist($secondWallet);
        $manager->persist($secondWallet->adjustByAdmin(
            Uuid::v7(),
            PricingConfig::BOOST_TIP_DISTRIBUTION * 2,
            'Vývojářský kredit',
            $admin,
            $seededAt->modify('-30 days'),
        ));
        $manager->persist($secondWallet->spend(
            Uuid::v7(),
            PricingConfig::BOOST_TIP_DISTRIBUTION,
            CreditTransactionType::BoostPurchase,
            $seededAt->modify('-5 days'),
            competition: $worldCup,
            boostType: BoostType::TipDistribution->value,
        ));
        $secondWallet->popEvents();
    }

    private function createTeam(
        ObjectManager $manager,
        string $id,
        Sport $sport,
        ?MatchSource $matchSource,
        string $name,
        \DateTimeImmutable $now,
        ?string $shortName,
        ?string $country,
        ?string $brandColor,
    ): Team {
        $team = new Team(
            id: Uuid::fromString($id),
            sport: $sport,
            matchSource: $matchSource,
            name: $name,
            createdAt: $now,
            shortName: $shortName,
            country: $country,
            brandColor: $brandColor,
        );
        $manager->persist($team);

        return $team;
    }

    private function worldMatch(
        ObjectManager $manager,
        string $idSuffix,
        MatchSource $matchSource,
        Team $homeTeam,
        Team $awayTeam,
        \DateTimeImmutable $kickoffAt,
        ?string $round,
        bool $isPlayoff,
        \DateTimeImmutable $createdAt,
    ): SportMatch {
        $match = new SportMatch(
            id: Uuid::fromString('019ddddd-0000-7000-8000-'.$idSuffix),
            matchSource: $matchSource,
            homeTeam: $homeTeam,
            awayTeam: $awayTeam,
            kickoffAt: $kickoffAt,
            venue: null,
            createdAt: $createdAt,
            round: $round,
            isPlayoff: $isPlayoff,
        );
        $match->popEvents();
        $manager->persist($match);

        return $match;
    }

    private function createBoost(
        ObjectManager $manager,
        string $id,
        Competition $competition,
        User $user,
        BoostType $type,
        \DateTimeImmutable $now,
    ): void {
        $purchase = new BoostPurchase(
            id: Uuid::fromString($id),
            user: $user,
            competition: $competition,
            type: $type,
            pricePaid: $type->price(),
            purchasedAt: $now,
        );
        $purchase->popEvents();
        $manager->persist($purchase);
    }

    /**
     * Writes each member's guesses from a PLAN — one code per finished match, so a
     * member's point total is designed rather than random. The plan is rotated by
     * the member's position so that WHICH matches they hit differs too, which is
     * what makes streaks, per-round scores and accuracy percentages vary.
     *
     * @param list<array{User, string}> $plans   member + plan string (one code per match)
     * @param list<SportMatch>          $matches finished matches, kickoff-ordered
     */
    private function createPlannedGuesses(
        ObjectManager $manager,
        Competition $competition,
        array $plans,
        array $matches,
        \DateTimeImmutable $now,
    ): void {
        foreach ($plans as $planIndex => [$member, $plan]) {
            $codes = $this->rotatePlan($plan, $planIndex);

            foreach ($matches as $matchIndex => $match) {
                [$homeGuess, $awayGuess] = $this->guessFor(
                    $codes[$matchIndex],
                    (int) $match->homeScore,
                    (int) $match->awayScore,
                );

                $this->createEvaluatedGuess($manager, $member, $match, $competition, $homeGuess, $awayGuess, $now);
            }
        }
    }

    /**
     * A honest EARLIER standing: the board exactly as it stood after the first
     * `$matchesPlayed` matches, so every snapshot total is a real partial sum and
     * the leaderboard Δ / member „Vývoj" can never exceed the live totals.
     *
     * @param list<array{User, string}> $plans
     */
    private function createStandingSnapshot(
        ObjectManager $manager,
        Competition $competition,
        array $plans,
        int $matchesPlayed,
        \DateTimeImmutable $day,
    ): void {
        $pragueDay = new \DateTimeImmutable($day->format('Y-m-d').' 00:00:00', new \DateTimeZone('Europe/Prague'));
        $createdAt = new \DateTimeImmutable($day->format('Y-m-d').' 03:00:00', new \DateTimeZone('UTC'));

        $points = [];

        foreach ($plans as $planIndex => [, $plan]) {
            $codes = $this->rotatePlan($plan, $planIndex);
            $total = 0;

            foreach (array_slice($codes, 0, $matchesPlayed) as $code) {
                $total += self::PLAN_CODE_POINTS[$code];
            }

            $points[$planIndex] = $total;
        }

        foreach ($plans as $planIndex => [$member]) {
            $rank = 1;

            foreach ($points as $otherPoints) {
                if ($otherPoints > $points[$planIndex]) {
                    ++$rank;
                }
            }

            $manager->persist(new LeaderboardSnapshot(
                id: Uuid::v7(),
                competition: $competition,
                user: $member,
                day: $pragueDay,
                points: $points[$planIndex],
                rank: $rank,
                createdAt: $createdAt,
            ));
        }
    }

    /**
     * @return list<string>
     */
    private function rotatePlan(string $plan, int $by): array
    {
        $codes = str_split($plan);
        $by %= count($codes);

        return array_merge(array_slice($codes, $by), array_slice($codes, 0, $by));
    }

    /**
     * Turns one plan code into a concrete guess for a KNOWN result, under the four
     * default rules (exact 5 + outcome 3 + home 1 + away 1):
     *
     *   e = exact hit      → 10 b
     *   o = right outcome  →  3 b (both goal counts deliberately wrong)
     *   h = right home goals only → 1 b
     *   m = complete miss  →  0 b
     *
     * The values are what {@see PLAN_CODE_POINTS} promises, so a plan string reads
     * as a point total.
     *
     * @return array{int, int}
     */
    private function guessFor(string $code, int $homeScore, int $awayScore): array
    {
        if ('e' === $code) {
            return [$homeScore, $awayScore];
        }

        if ('o' === $code) {
            // Shifting both sides keeps the outcome and misses both goal counts.
            return [$homeScore + 2, $awayScore + 2];
        }

        if ('h' === $code) {
            foreach ([$awayScore + 3, 0, 1, 2, $awayScore + 5] as $awayGuess) {
                if ($awayGuess !== $awayScore && ($homeScore <=> $awayGuess) !== ($homeScore <=> $awayScore)) {
                    return [$homeScore, $awayGuess];
                }
            }

            throw new \LogicException(sprintf('No "home goals only" guess exists for %d:%d.', $homeScore, $awayScore));
        }

        if ('m' === $code) {
            foreach ([[0, 5], [5, 0], [1, 6], [6, 1]] as [$homeGuess, $awayGuess]) {
                if (
                    $homeGuess !== $homeScore
                    && $awayGuess !== $awayScore
                    && ($homeGuess <=> $awayGuess) !== ($homeScore <=> $awayScore)
                ) {
                    return [$homeGuess, $awayGuess];
                }
            }

            throw new \LogicException(sprintf('No zero-point guess exists for %d:%d.', $homeScore, $awayScore));
        }

        throw new \LogicException(sprintf('Unknown plan code "%s".', $code));
    }
}
