<?php

declare(strict_types=1);

namespace App\Event;

use App\Command\SettleUncoveredPremiumCharges\SettleUncoveredPremiumChargesCommand;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * When anyone tops up their wallet, retry any Uncovered premium charges they owe
 * as a competition manager. Fires for every purchase; the settle handler no-ops
 * cheaply when the buyer manages no premium competitions with debt.
 */
#[AsMessageHandler]
final readonly class SettleUncoveredPremiumChargesOnTopUpHandler
{
    public function __construct(
        #[Autowire(service: 'command.bus')]
        private MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(CreditsPurchased $event): void
    {
        $this->commandBus->dispatch(new SettleUncoveredPremiumChargesCommand(
            ownerId: $event->userId,
        ));
    }
}
