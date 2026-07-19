<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\BoostType;
use App\Enum\NotificationType;
use App\Repository\CompetitionRepository;
use App\Repository\UserRepository;
use App\Service\Notification\Notifier;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * `boost_refunded`: a player's active boost was credited back (the manager
 * re-enabled premium). The buyer is told their vylepšení was refunded.
 */
#[AsMessageHandler]
final readonly class NotifyBoostRefundedHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private CompetitionRepository $competitionRepository,
        private Notifier $notifier,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(BoostRefunded $event): void
    {
        $buyer = $this->userRepository->find($event->userId);
        $competition = $this->competitionRepository->find($event->competitionId);

        if (null === $buyer || null === $competition) {
            return;
        }

        $boostLabel = BoostType::from($event->boostType)->label();

        $url = $this->urlGenerator->generate(
            'portal_competition_detail',
            ['id' => $competition->id->toRfc4122()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $this->notifier->notify(
            user: $buyer,
            type: NotificationType::BoostRefunded,
            title: sprintf('Vylepšení vráceno: %s', $boostLabel),
            body: sprintf('Vaše vylepšení „%s" v soutěži %s bylo vráceno zpět do peněženky (%d kr.), protože soutěž přešla na prémium.', $boostLabel, $competition->name, $event->amount),
            url: $url,
            competition: $competition,
            payload: ['boostType' => $event->boostType, 'amount' => $event->amount],
        );
    }
}
