<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Query\QueryBus;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

abstract class IntegrationTestCase extends KernelTestCase
{
    protected function entityManager(): EntityManagerInterface
    {
        /* @var EntityManagerInterface */
        return self::getContainer()->get('doctrine.orm.entity_manager');
    }

    protected function commandBus(): MessageBusInterface
    {
        /* @var MessageBusInterface */
        return self::getContainer()->get('test.command.bus');
    }

    protected function queryBus(): QueryBus
    {
        /* @var QueryBus */
        return self::getContainer()->get(QueryBus::class);
    }

    protected function clock(): ClockInterface
    {
        /* @var ClockInterface */
        return self::getContainer()->get(ClockInterface::class);
    }

    protected function identityProvider(): PredictableIdentityProvider
    {
        /* @var PredictableIdentityProvider */
        return self::getContainer()->get(PredictableIdentityProvider::class);
    }
}
