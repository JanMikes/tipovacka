<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\DataFixtures\AppFixtures;
use App\Entity\Player;
use App\Entity\Team;
use App\Repository\PlayerRepository;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class PlayerRepositoryTest extends IntegrationTestCase
{
    public function testFindOrCreateMatchesExistingPlayerCaseInsensitively(): void
    {
        // Fixture pool: 'Jan Novák' on the Bohemians team (home of the finished match).
        $repository = $this->playerRepository();
        $team = $this->bohemiansTeam();

        $player = $repository->findOrCreate(
            team: $team,
            name: 'jan novák',
            identity: $this->identityProvider(),
            now: new \DateTimeImmutable('2025-06-15 12:00:00 UTC'),
        );

        self::assertSame(AppFixtures::PLAYER_HOME_SCORER_ONE_ID, $player->id->toRfc4122());
        // The stored row keeps its first-seen casing.
        self::assertSame(AppFixtures::PLAYER_HOME_SCORER_ONE_NAME, $player->name);
        self::assertTrue($player->team->id->equals($team->id));
    }

    public function testFindOrCreateCreatesNewPlayerWhenNameIsUnknown(): void
    {
        $repository = $this->playerRepository();
        $team = $this->bohemiansTeam();

        $player = $repository->findOrCreate(
            team: $team,
            name: 'Ondřej Mihálik',
            identity: $this->identityProvider(),
            now: new \DateTimeImmutable('2025-06-15 12:00:00 UTC'),
        );

        $this->entityManager()->flush();

        $found = $this->entityManager()->find(Player::class, $player->id);
        self::assertInstanceOf(Player::class, $found);
        self::assertSame('Ondřej Mihálik', $found->name);
        self::assertTrue($found->team->id->equals($team->id));
    }

    public function testListByTeamReturnsRosterAlphabetically(): void
    {
        $team = $this->bohemiansTeam();

        $names = array_map(
            static fn (Player $p): string => $p->name,
            $this->playerRepository()->listByTeam($team->id),
        );

        self::assertSame([
            AppFixtures::PLAYER_HOME_SCORER_ONE_NAME, // Jan Novák
            AppFixtures::PLAYER_HOME_SCORER_TWO_NAME, // Petr Svoboda
        ], $names);
    }

    public function testSearchByTeamFiltersByNameCaseInsensitively(): void
    {
        $team = $this->bohemiansTeam();

        $names = array_map(
            static fn (Player $p): string => $p->name,
            $this->playerRepository()->searchByTeam($team->id, 'nov'),
        );

        // Only 'Jan Novák' matches „nov"; 'Petr Svoboda' does not.
        self::assertSame([AppFixtures::PLAYER_HOME_SCORER_ONE_NAME], $names);
    }

    private function playerRepository(): PlayerRepository
    {
        /* @var PlayerRepository */
        return self::getContainer()->get(PlayerRepository::class);
    }

    private function bohemiansTeam(): Team
    {
        $team = $this->entityManager()->find(Team::class, Uuid::fromString(AppFixtures::TEAM_BOHEMIANS_ID));
        self::assertInstanceOf(Team::class, $team);

        return $team;
    }
}
