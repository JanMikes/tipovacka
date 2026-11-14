<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Command\ChangeEmailDelivery\ChangeEmailDeliveryCommand;
use App\Enum\EmailFrequency;
use App\Query\QueryBus;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Uid\Uuid;

abstract class IntegrationTestCase extends KernelTestCase
{
    protected function eventBus(): MessageBusInterface
    {
        /* @var MessageBusInterface */
        return self::getContainer()->get('test.event.bus');
    }

    /**
     * The in-memory `async` transport (emails / notifier messages land here in
     * the test env instead of being sent). Inspect via `->getSent()`.
     */
    protected function messengerAsyncTransport(): InMemoryTransport
    {
        /* @var InMemoryTransport */
        return self::getContainer()->get('test.messenger.transport.async');
    }

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

    protected function paymentGateway(): FakePaymentGateway
    {
        /* @var FakePaymentGateway */
        return self::getContainer()->get(FakePaymentGateway::class);
    }

    protected function recordedDomainEvents(): RecordedDomainEvents
    {
        /* @var RecordedDomainEvents */
        return self::getContainer()->get(RecordedDomainEvents::class);
    }

    /**
     * Puts a fixture user on an e-mail frequency. Without it a user is on the
     * default (the daily summary), where most notifications wait for the sweep
     * instead of being mailed at once — a test about „this mail goes out now"
     * has to say so.
     */
    protected function setEmailFrequency(string $userId, EmailFrequency $frequency, ?int $digestHour = null): void
    {
        $this->commandBus()->dispatch(new ChangeEmailDeliveryCommand(Uuid::fromString($userId), $frequency, $digestHour));
    }

    protected function firstWrappedException(HandlerFailedException $exception): ?\Throwable
    {
        return array_values($exception->getWrappedExceptions())[0] ?? null;
    }
}
