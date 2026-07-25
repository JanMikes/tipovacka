<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\DataFixtures\AppFixtures;
use App\Entity\MatchSource;
use App\Entity\Team;
use App\Service\Team\TeamResolver;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class TeamResolverTest extends IntegrationTestCase
{
    private const string NOW = '2025-06-15 12:00:00 UTC';

    public function testResolveCreatesGlobalTeamForCuratedSource(): void
    {
        $team = $this->resolver()->resolve($this->curatedSource(), 'Manchester City', $this->now());
        $this->entityManager()->flush();

        self::assertTrue($team->isGlobal);
        self::assertNull($team->matchSource);
        self::assertSame($this->curatedSource()->sport->id->toRfc4122(), $team->sport->id->toRfc4122());
        self::assertSame('Manchester City', $team->name);

        $found = $this->entityManager()->find(Team::class, $team->id);
        self::assertInstanceOf(Team::class, $found);
        self::assertTrue($found->isGlobal);
    }

    public function testResolveIsCaseInsensitiveAndReusesTheGlobalTeam(): void
    {
        $first = $this->resolver()->resolve($this->curatedSource(), 'Manchester City', $this->now());
        $this->entityManager()->flush();

        $second = $this->resolver()->resolve($this->curatedSource(), 'manchester CITY', $this->now());

        self::assertTrue($first->id->equals($second->id));
        // Stored row keeps its first-seen casing.
        self::assertSame('Manchester City', $second->name);
    }

    public function testResolveCreatesLocalTeamForPrivateSource(): void
    {
        $source = $this->privateSource();

        $team = $this->resolver()->resolve($source, 'Marketing', $this->now());

        self::assertTrue($team->isLocal);
        self::assertSame($source->id->toRfc4122(), $team->matchSource?->id->toRfc4122());
    }

    public function testGlobalAndLocalScopesAreIsolated(): void
    {
        $local = $this->resolver()->resolve($this->privateSource(), 'Sparta', $this->now());
        $this->entityManager()->flush();

        $global = $this->resolver()->resolve($this->curatedSource(), 'Sparta', $this->now());

        self::assertFalse($local->id->equals($global->id));
        self::assertTrue($local->isLocal);
        self::assertTrue($global->isGlobal);
    }

    public function testFindExistingNeverCreates(): void
    {
        self::assertNull($this->resolver()->findExisting($this->curatedSource(), 'Nowhere United'));

        $created = $this->resolver()->resolve($this->curatedSource(), 'Nowhere United', $this->now());
        $this->entityManager()->flush();

        $found = $this->resolver()->findExisting($this->curatedSource(), 'nowhere united');
        self::assertNotNull($found);
        self::assertTrue($created->id->equals($found->id));
    }

    private function resolver(): TeamResolver
    {
        /* @var TeamResolver */
        return self::getContainer()->get(TeamResolver::class);
    }

    private function curatedSource(): MatchSource
    {
        return $this->source(AppFixtures::PUBLIC_SOURCE_ID);
    }

    private function privateSource(): MatchSource
    {
        return $this->source(AppFixtures::PRIVATE_SOURCE_ID);
    }

    private function source(string $id): MatchSource
    {
        $source = $this->entityManager()->find(MatchSource::class, Uuid::fromString($id));
        self::assertInstanceOf(MatchSource::class, $source);

        return $source;
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::NOW);
    }
}
