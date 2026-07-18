<?php

declare(strict_types=1);

namespace App\Tests\Integration\Public;

use App\Command\MarkMatchSourceCompleted\MarkMatchSourceCompletedCommand;
use App\DataFixtures\AppFixtures;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

final class PublicMatchSourcesListFlowTest extends WebTestCase
{
    public function testAnonymousCanSeeActivePublicMatchSources(): void
    {
        $client = static::createClient();
        $client->request('GET', '/turnaje');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', AppFixtures::PUBLIC_SOURCE_NAME);
    }

    public function testPrivateMatchSourcesNotListed(): void
    {
        $client = static::createClient();
        $client->request('GET', '/turnaje');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('body', AppFixtures::PRIVATE_SOURCE_NAME);
    }

    public function testFinishedMatchSourcesAreExcluded(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();

        /** @var MessageBusInterface $commandBus */
        $commandBus = $container->get('test.command.bus');
        $commandBus->dispatch(new MarkMatchSourceCompletedCommand(
            matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
        ));

        $client->request('GET', '/turnaje');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('body', AppFixtures::PUBLIC_SOURCE_NAME);
    }
}
