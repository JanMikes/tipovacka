<?php

declare(strict_types=1);

namespace App\Tests\Integration\Event;

use App\Command\PostponeSportMatch\PostponeSportMatchCommand;
use App\Command\UpdateSportMatch\UpdateSportMatchCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\CompetitionMatchSetting;
use App\Entity\CompetitionSource;
use App\Entity\MatchSource;
use App\Entity\Sport;
use App\Entity\SportMatch;
use App\Entity\Team;
use App\Entity\User;
use App\Enum\MatchSourceKind;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * The placeholder-kickoff trap. The seeded global soutěže pin every match's
 * deadline to its own kickoff, and several of those kickoffs are placeholders
 * (Chance Liga rounds 6+ were seeded at 00:00 Prague because LFA had not
 * published the real times). Once a feed corrects such a kickoff, an override
 * left behind at the old value would close tipping hours early — silently, for
 * a whole round.
 */
final class RepinOwnKickoffDeadlinesTest extends IntegrationTestCase
{
    private const string PLACEHOLDER_KICKOFF = '2025-07-20 22:00:00 UTC';

    private const string REAL_KICKOFF = '2025-07-21 13:00:00 UTC';

    public function testDeadlinePinnedToOwnKickoffFollowsACorrectedKickoff(): void
    {
        [$match, $setting] = $this->seedPinnedMatch(deadline: self::PLACEHOLDER_KICKOFF);

        $this->correctKickoff($match, self::REAL_KICKOFF);

        self::assertEquals(
            new \DateTimeImmutable(self::REAL_KICKOFF),
            $this->reloadSetting($setting)->deadline,
        );
    }

    /** A deliberate manager decision („hodinu před výkopem") must not be touched. */
    public function testADeadlineThatIsNotTheKickoffIsLeftAlone(): void
    {
        $deliberate = '2025-07-20 20:00:00 UTC';
        [$match, $setting] = $this->seedPinnedMatch(deadline: $deliberate);

        $this->correctKickoff($match, self::REAL_KICKOFF);

        self::assertEquals(new \DateTimeImmutable($deliberate), $this->reloadSetting($setting)->deadline);
    }

    public function testPostponementAlsoCarriesThePin(): void
    {
        [$match, $setting] = $this->seedPinnedMatch(deadline: self::PLACEHOLDER_KICKOFF);

        $this->commandBus()->dispatch(new PostponeSportMatchCommand(
            sportMatchId: $match->id,
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            newKickoffAt: new \DateTimeImmutable('2025-08-15 18:00:00 UTC'),
        ));

        self::assertEquals(
            new \DateTimeImmutable('2025-08-15 18:00:00 UTC'),
            $this->reloadSetting($setting)->deadline,
        );
    }

    private function correctKickoff(SportMatch $match, string $kickoff): void
    {
        $this->commandBus()->dispatch(new UpdateSportMatchCommand(
            sportMatchId: $match->id,
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            homeTeam: null,
            awayTeam: null,
            kickoffAt: new \DateTimeImmutable($kickoff),
            venue: null,
            round: null,
            isPlayoff: false,
        ));
    }

    private function reloadSetting(CompetitionMatchSetting $setting): CompetitionMatchSetting
    {
        $em = $this->entityManager();
        $em->clear();

        $reloaded = $em->find(CompetitionMatchSetting::class, $setting->id);
        self::assertInstanceOf(CompetitionMatchSetting::class, $reloaded);

        return $reloaded;
    }

    /**
     * @return array{SportMatch, CompetitionMatchSetting}
     */
    private function seedPinnedMatch(string $deadline): array
    {
        $em = $this->entityManager();
        $createdAt = new \DateTimeImmutable('2025-05-01 09:00:00 UTC');

        $owner = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertInstanceOf(User::class, $owner);
        $sport = $em->find(Sport::class, Uuid::fromString(Sport::FOOTBALL_ID));
        self::assertInstanceOf(Sport::class, $sport);

        $source = new MatchSource(
            id: Uuid::v7(),
            sport: $sport,
            owner: $owner,
            kind: MatchSourceKind::Private,
            name: 'Repin test source',
            description: null,
            startAt: null,
            endAt: null,
            createdAt: $createdAt,
        );
        $source->popEvents();
        $em->persist($source);

        $competition = new Competition(
            id: Uuid::v7(),
            headlineSource: $source,
            owner: $owner,
            name: 'Repin test competition',
            description: null,
            pin: null,
            shareableLinkToken: null,
            createdAt: $createdAt,
        );
        $competition->popEvents();
        $em->persist($competition);

        $layer = new CompetitionSource(
            id: Uuid::v7(),
            competition: $competition,
            matchSource: $source,
            addedAt: $createdAt,
        );
        $competition->attachSource($layer);
        $em->persist($layer);

        $homeTeam = $em->find(Team::class, Uuid::fromString(AppFixtures::TEAM_SPARTA_ID));
        self::assertInstanceOf(Team::class, $homeTeam);
        $awayTeam = $em->find(Team::class, Uuid::fromString(AppFixtures::TEAM_SLAVIA_ID));
        self::assertInstanceOf(Team::class, $awayTeam);

        $match = new SportMatch(
            id: Uuid::v7(),
            matchSource: $source,
            homeTeam: $homeTeam,
            awayTeam: $awayTeam,
            kickoffAt: new \DateTimeImmutable(self::PLACEHOLDER_KICKOFF),
            venue: null,
            createdAt: $createdAt,
        );
        $match->popEvents();
        $em->persist($match);

        $setting = new CompetitionMatchSetting(
            id: Uuid::v7(),
            competition: $competition,
            sportMatch: $match,
            deadline: new \DateTimeImmutable($deadline),
            createdAt: $createdAt,
        );
        $em->persist($setting);

        $em->flush();

        return [$match, $setting];
    }
}
