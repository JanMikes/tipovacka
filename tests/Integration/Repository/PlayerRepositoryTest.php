<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\DataFixtures\AppFixtures;
use App\Entity\MatchSource;
use App\Entity\Player;
use App\Repository\PlayerRepository;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class PlayerRepositoryTest extends IntegrationTestCase
{
    public function testFindOrCreateMatchesExistingPlayerCaseInsensitively(): void
    {
        // Fixture pool: 'Jan Novák' for team 'Bohemians 1905' in the public source.
        $repository = $this->playerRepository();
        $matchSource = $this->publicSource();

        $player = $repository->findOrCreate(
            matchSource: $matchSource,
            teamName: 'bohemians 1905',
            name: 'jan novák',
            identity: $this->identityProvider(),
            now: new \DateTimeImmutable('2025-06-15 12:00:00 UTC'),
        );

        self::assertSame(AppFixtures::PLAYER_HOME_SCORER_ONE_ID, $player->id->toRfc4122());
        // The stored row keeps its first-seen casing.
        self::assertSame(AppFixtures::PLAYER_HOME_SCORER_ONE_NAME, $player->name);
        self::assertSame('Bohemians 1905', $player->teamName);
    }

    public function testFindOrCreateCreatesNewPlayerWhenNameIsUnknown(): void
    {
        $repository = $this->playerRepository();
        $matchSource = $this->publicSource();

        $player = $repository->findOrCreate(
            matchSource: $matchSource,
            teamName: 'Bohemians 1905',
            name: 'Ondřej Mihálik',
            identity: $this->identityProvider(),
            now: new \DateTimeImmutable('2025-06-15 12:00:00 UTC'),
        );

        $this->entityManager()->flush();

        $found = $this->entityManager()->find(Player::class, $player->id);
        self::assertInstanceOf(Player::class, $found);
        self::assertSame('Ondřej Mihálik', $found->name);
    }

    private function playerRepository(): PlayerRepository
    {
        /* @var PlayerRepository */
        return self::getContainer()->get(PlayerRepository::class);
    }

    private function publicSource(): MatchSource
    {
        $matchSource = $this->entityManager()->find(MatchSource::class, Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID));
        self::assertInstanceOf(MatchSource::class, $matchSource);

        return $matchSource;
    }
}
