<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Group;
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
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Dev-only fixtures loaded via `composer db:fixtures` (group=dev).
 * Adds 25 extra users, 3 extra tournaments (finished, in-progress public, private active),
 * multiple groups per tournament, cross-group memberships, matches and evaluated guesses
 * so the UI can be exercised with realistic volume.
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

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
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

        // -- Tournaments (3 new) ------------------------------------------
        $euro = new Tournament(
            id: Uuid::fromString('019aaaaa-0000-7000-8000-000000000003'),
            sport: $football,
            owner: $users[1],
            visibility: TournamentVisibility::Public,
            name: 'Euro 2024',
            description: 'Fotbalové mistrovství Evropy 2024 v Německu — tipovačka mezi kamarády.',
            startAt: new \DateTimeImmutable('2024-06-14 00:00:00 UTC'),
            endAt: new \DateTimeImmutable('2024-07-14 23:59:59 UTC'),
            createdAt: new \DateTimeImmutable('2024-04-01 10:00:00 UTC'),
        );
        $euro->markFinished(new \DateTimeImmutable('2024-07-15 12:00:00 UTC'));
        $euro->popEvents();
        $manager->persist($euro);

        $fortuna = new Tournament(
            id: Uuid::fromString('019aaaaa-0000-7000-8000-000000000004'),
            sport: $football,
            owner: $verified,
            visibility: TournamentVisibility::Public,
            name: 'Fortuna Liga 2025/26',
            description: 'Česká první fotbalová liga — celoroční tipovačka.',
            startAt: new \DateTimeImmutable('2025-07-18 00:00:00 UTC'),
            endAt: new \DateTimeImmutable('2026-05-24 23:59:59 UTC'),
            createdAt: new \DateTimeImmutable('2025-06-01 09:00:00 UTC'),
        );
        $fortuna->popEvents();
        $manager->persist($fortuna);

        $firma = new Tournament(
            id: Uuid::fromString('019aaaaa-0000-7000-8000-000000000005'),
            sport: $football,
            owner: $users[17],
            visibility: TournamentVisibility::Private,
            name: 'Firemní liga',
            description: 'Soukromá tipovačka pro kolegy z práce.',
            startAt: new \DateTimeImmutable('2025-06-01 00:00:00 UTC'),
            endAt: new \DateTimeImmutable('2025-12-31 23:59:59 UTC'),
            createdAt: new \DateTimeImmutable('2025-05-20 14:30:00 UTC'),
            creationPin: '42424242',
        );
        $firma->popEvents();
        $manager->persist($firma);

        foreach ([$euro, $fortuna, $firma] as $tournament) {
            $this->provisionDefaultRules($manager, $tournament, $now);
        }

        // -- Groups -------------------------------------------------------
        $eurofans = $this->createGroup(
            $manager,
            id: '019bbbbb-0000-7000-8000-000000000003',
            tournament: $euro,
            owner: $users[1],
            name: 'Eurofans',
            description: 'Tipujeme Euro, po každém zápase bago.',
            pin: '20240701',
            linkToken: str_repeat('e', 48),
            createdAt: new \DateTimeImmutable('2024-04-02 10:00:00 UTC'),
        );

        $vsChtGroup = $this->createGroup(
            $manager,
            id: '019bbbbb-0000-7000-8000-000000000004',
            tournament: $fortuna,
            owner: $verified,
            name: 'VŠCHT tipovačka',
            description: 'Bývalí spolužáci z VŠCHT.',
            pin: '10000001',
            linkToken: str_repeat('v', 48),
            createdAt: new \DateTimeImmutable('2025-06-02 10:00:00 UTC'),
        );

        $prahaGroup = $this->createGroup(
            $manager,
            id: '019bbbbb-0000-7000-8000-000000000005',
            tournament: $fortuna,
            owner: $users[14],
            name: 'Pražský pivní klub',
            description: 'Druhá skupina ve stejném turnaji — Fortuna Liga.',
            pin: '10000002',
            linkToken: str_repeat('p', 48),
            createdAt: new \DateTimeImmutable('2025-06-03 12:00:00 UTC'),
        );

        $firmaA = $this->createGroup(
            $manager,
            id: '019bbbbb-0000-7000-8000-000000000006',
            tournament: $firma,
            owner: $users[17],
            name: 'Dev tým',
            description: 'Programátoři proti sobě.',
            pin: '20000001',
            linkToken: str_repeat('d', 48),
            createdAt: new \DateTimeImmutable('2025-05-21 09:00:00 UTC'),
        );

        $firmaB = $this->createGroup(
            $manager,
            id: '019bbbbb-0000-7000-8000-000000000007',
            tournament: $firma,
            owner: $users[22],
            name: 'Management',
            description: 'Druhá skupina — vedení firmy, stejný turnaj.',
            pin: '20000002',
            linkToken: str_repeat('m', 48),
            createdAt: new \DateTimeImmutable('2025-05-22 09:00:00 UTC'),
        );

        $kamosiGroup = $this->createGroup(
            $manager,
            id: '019bbbbb-0000-7000-8000-000000000008',
            tournament: $manager->find(Tournament::class, Uuid::fromString(AppFixtures::PUBLIC_TOURNAMENT_ID)),
            owner: $users[24],
            name: 'Kamarádi ze střední',
            description: 'Druhá skupina v Lize mistrů.',
            pin: '30000001',
            linkToken: str_repeat('k', 48),
            createdAt: new \DateTimeImmutable('2025-06-05 18:00:00 UTC'),
        );

        // -- Memberships --------------------------------------------------
        // Eurofans (finished tournament) — admin + 8 users.
        $this->addMembers($manager, $eurofans, [$admin, $users[1], $users[2], $users[3], $users[4], $users[5], $users[6], $users[7]], $now);

        // VŠCHT — verified + 5 users. User 9 overlaps with Praha group (same tournament).
        $this->addMembers($manager, $vsChtGroup, [$verified, $users[1], $users[9], $users[10], $users[11], $users[12], $users[13]], $now);

        // Praha — user 1 and user 9 overlap with VŠCHT (same tournament, different group).
        $this->addMembers($manager, $prahaGroup, [$users[14], $users[1], $users[9], $users[15], $users[16]], $now);

        // Firma A (private) — u17 owner + 4 users.
        $this->addMembers($manager, $firmaA, [$users[17], $users[18], $users[19], $users[20], $users[21]], $now);

        // Firma B — u17 overlaps with Firma A (same private tournament).
        $this->addMembers($manager, $firmaB, [$users[22], $users[17], $users[23]], $now);

        // Second group inside the existing PUBLIC tournament.
        $this->addMembers($manager, $kamosiGroup, [$users[24], $users[25], $users[1]], $now);

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
        $this->scheduledMatch($manager, $fortuna, 'ddbb0104', 'Bohemians 1905', 'Mladá Boleslav', '2025-06-21 17:00:00', 'Ďolíček');
        $this->scheduledMatch($manager, $fortuna, 'ddbb0105', 'Teplice', 'Liberec', '2025-06-22 15:00:00', null);
        $this->scheduledMatch($manager, $fortuna, 'ddbb0106', 'Hradec Králové', 'Zlín', '2025-06-28 17:30:00', 'Malšovická aréna');

        // Private tournament — mostly scheduled, one finished.
        $this->scheduledMatch($manager, $firma, 'ddcc0101', 'Tygři', 'Lvi', '2025-06-25 18:00:00', null);
        $this->scheduledMatch($manager, $firma, 'ddcc0102', 'Orli', 'Medvědi', '2025-07-02 18:00:00', null);
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
                $this->createEvaluatedGuess($manager, $member, $match, $vsChtGroup, $gh, $ga, $now);
            }
            foreach ($prahaMembers as $memberIndex => $member) {
                [$gh, $ga] = $guessPattern[($memberIndex + $matchIndex + 3) % count($guessPattern)];
                $this->createEvaluatedGuess($manager, $member, $match, $prahaGroup, $gh, $ga, $now);
            }
        }

        // Firma tournaments — one finished match, both groups tip on it.
        foreach ([$users[17], $users[18], $users[19], $users[20], $users[21]] as $memberIndex => $member) {
            [$gh, $ga] = $guessPattern[$memberIndex % count($guessPattern)];
            $this->createEvaluatedGuess($manager, $member, $firmaFinished, $firmaA, $gh, $ga, $now);
        }
        foreach ([$users[22], $users[17], $users[23]] as $memberIndex => $member) {
            [$gh, $ga] = $guessPattern[($memberIndex + 2) % count($guessPattern)];
            $this->createEvaluatedGuess($manager, $member, $firmaFinished, $firmaB, $gh, $ga, $now);
        }

        $manager->flush();
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

    private function provisionDefaultRules(
        ObjectManager $manager,
        Tournament $tournament,
        \DateTimeImmutable $now,
    ): void {
        foreach ([
            ['exact_score', 5],
            ['correct_outcome', 3],
            ['correct_home_goals', 1],
            ['correct_away_goals', 1],
        ] as [$identifier, $points]) {
            $manager->persist(new TournamentRuleConfiguration(
                id: Uuid::v7(),
                tournament: $tournament,
                ruleIdentifier: $identifier,
                enabled: true,
                points: $points,
                now: $now,
            ));
        }
    }

    private function createGroup(
        ObjectManager $manager,
        string $id,
        Tournament $tournament,
        User $owner,
        string $name,
        ?string $description,
        ?string $pin,
        ?string $linkToken,
        \DateTimeImmutable $createdAt,
    ): Group {
        $group = new Group(
            id: Uuid::fromString($id),
            tournament: $tournament,
            owner: $owner,
            name: $name,
            description: $description,
            pin: $pin,
            shareableLinkToken: $linkToken,
            createdAt: $createdAt,
        );
        $group->popEvents();
        $manager->persist($group);

        return $group;
    }

    /**
     * @param list<User> $members
     */
    private function addMembers(ObjectManager $manager, Group $group, array $members, \DateTimeImmutable $now): void
    {
        foreach ($members as $user) {
            $membership = new Membership(
                id: Uuid::v7(),
                group: $group,
                user: $user,
                joinedAt: $now,
            );
            $membership->popEvents();
            $manager->persist($membership);
        }
    }

    private function finishedMatch(
        ObjectManager $manager,
        Tournament $tournament,
        string $idSuffix,
        string $homeTeam,
        string $awayTeam,
        string $kickoff,
        ?string $venue,
        int $homeScore,
        int $awayScore,
        \DateTimeImmutable $now,
    ): SportMatch {
        $match = $this->baseMatch($manager, $tournament, $idSuffix, $homeTeam, $awayTeam, $kickoff, $venue);
        $match->setFinalScore($homeScore, $awayScore, $now);
        $match->popEvents();

        return $match;
    }

    private function liveMatch(
        ObjectManager $manager,
        Tournament $tournament,
        string $idSuffix,
        string $homeTeam,
        string $awayTeam,
        string $kickoff,
        ?string $venue,
        \DateTimeImmutable $now,
    ): SportMatch {
        $match = $this->baseMatch($manager, $tournament, $idSuffix, $homeTeam, $awayTeam, $kickoff, $venue);
        $match->beginLive($now);
        $match->popEvents();

        return $match;
    }

    private function scheduledMatch(
        ObjectManager $manager,
        Tournament $tournament,
        string $idSuffix,
        string $homeTeam,
        string $awayTeam,
        string $kickoff,
        ?string $venue,
    ): SportMatch {
        $match = $this->baseMatch($manager, $tournament, $idSuffix, $homeTeam, $awayTeam, $kickoff, $venue);
        $match->popEvents();

        return $match;
    }

    private function baseMatch(
        ObjectManager $manager,
        Tournament $tournament,
        string $idSuffix,
        string $homeTeam,
        string $awayTeam,
        string $kickoff,
        ?string $venue,
    ): SportMatch {
        $match = new SportMatch(
            id: Uuid::fromString('019ddddd-0000-7000-8000-'.str_pad($idSuffix, 12, '0', STR_PAD_LEFT)),
            tournament: $tournament,
            homeTeam: $homeTeam,
            awayTeam: $awayTeam,
            kickoffAt: new \DateTimeImmutable($kickoff, new \DateTimeZone('UTC')),
            venue: $venue,
            createdAt: new \DateTimeImmutable('2024-04-01 10:00:00 UTC'),
        );
        $manager->persist($match);

        return $match;
    }

    private function createEvaluatedGuess(
        ObjectManager $manager,
        User $user,
        SportMatch $match,
        Group $group,
        int $homeScore,
        int $awayScore,
        \DateTimeImmutable $now,
    ): void {
        $submittedAt = $match->kickoffAt->modify('-2 hours');

        $guess = new Guess(
            id: Uuid::v7(),
            user: $user,
            sportMatch: $match,
            group: $group,
            homeScore: $homeScore,
            awayScore: $awayScore,
            submittedAt: $submittedAt,
        );
        $guess->popEvents();
        $manager->persist($guess);

        if (!$match->isFinished) {
            return;
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
}
