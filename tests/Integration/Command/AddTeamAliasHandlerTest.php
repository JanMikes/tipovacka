<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\AddTeamAlias\AddTeamAliasCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Sport;
use App\Exception\TeamAliasConflict;
use App\Repository\TeamAliasRepository;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Uid\Uuid;

/**
 * Aliases are added from a LIST an operator re-runs — a feed's team spellings
 * arrive in a batch, one line gets fixed, the batch goes again. So the command
 * has to be safe to repeat: re-adding an alias that already points where it is
 * asked to point is success, and only a different target is a conflict.
 */
final class AddTeamAliasHandlerTest extends IntegrationTestCase
{
    public function testAddsAnAliasToADirectoryTeam(): void
    {
        $this->add('Sparta Praha', 'AC Sparta Praha fotbal, a.s.');

        $team = $this->aliases()->findGlobalTeamByAlias(
            Uuid::fromString(Sport::FOOTBALL_ID),
            'AC Sparta Praha fotbal, a.s.',
        );

        self::assertNotNull($team);
        self::assertSame(AppFixtures::TEAM_SPARTA_ID, (string) $team->id);
    }

    /** TIPOVACKA-R: re-running the batch must not blow up on its own successes. */
    public function testReAddingTheSameAliasIsANoOpNotAFailure(): void
    {
        $this->add('Sparta Praha', 'AC Sparta Praha fotbal, a.s.');
        $this->add('Sparta Praha', 'AC Sparta Praha fotbal, a.s.');

        self::assertCount(
            1,
            $this->aliases()->listByTeam(Uuid::fromString(AppFixtures::TEAM_SPARTA_ID)),
            'the second run must not duplicate the row either',
        );
    }

    /** Case is not significance — the resolver matches case-insensitively. */
    public function testReAddingWithDifferentCasingIsAlsoANoOp(): void
    {
        $this->add('Sparta Praha', 'AC Sparta Praha fotbal, a.s.');
        $this->add('Sparta Praha', 'ac sparta praha FOTBAL, A.S.');

        self::assertCount(1, $this->aliases()->listByTeam(Uuid::fromString(AppFixtures::TEAM_SPARTA_ID)));
    }

    /** Pointing an existing alias somewhere ELSE stays a hard error. */
    public function testRepointingAnAliasToAnotherTeamIsRejected(): void
    {
        $this->add('Sparta Praha', 'AC Sparta Praha fotbal, a.s.');

        try {
            $this->add('Slavia Praha', 'AC Sparta Praha fotbal, a.s.');
            self::fail('expected the conflicting alias to be refused');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(TeamAliasConflict::class, $e->getPrevious());
        }
    }

    private function add(string $teamName, string $alias): void
    {
        $this->commandBus()->dispatch(new AddTeamAliasCommand(
            sportId: Uuid::fromString(Sport::FOOTBALL_ID),
            teamName: $teamName,
            alias: $alias,
        ));
    }

    private function aliases(): TeamAliasRepository
    {
        /** @var TeamAliasRepository $repository */
        $repository = self::getContainer()->get(TeamAliasRepository::class);

        return $repository;
    }
}
