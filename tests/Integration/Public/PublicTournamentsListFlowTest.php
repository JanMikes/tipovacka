<?php

declare(strict_types=1);

namespace App\Tests\Integration\Public;

use App\Command\MarkTournamentFinished\MarkTournamentFinishedCommand;
use App\DataFixtures\AppFixtures;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

final class PublicTournamentsListFlowTest extends WebTestCase
{
    public function testAnonymousCanSeeActivePublicTournaments(): void
    {
        $client = static::createClient();
        $client->request('GET', '/turnaje');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', AppFixtures::PUBLIC_TOURNAMENT_NAME);
    }

    public function testPrivateTournamentsNotListed(): void
    {
        $client = static::createClient();
        $client->request('GET', '/turnaje');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('body', AppFixtures::PRIVATE_TOURNAMENT_NAME);
    }

    public function testFinishedTournamentsAreExcluded(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();

        /** @var MessageBusInterface $commandBus */
        $commandBus = $container->get('test.command.bus');
        $commandBus->dispatch(new MarkTournamentFinishedCommand(
            tournamentId: Uuid::fromString(AppFixtures::PUBLIC_TOURNAMENT_ID),
        ));

        $client->request('GET', '/turnaje');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('body', AppFixtures::PUBLIC_TOURNAMENT_NAME);
    }
}
