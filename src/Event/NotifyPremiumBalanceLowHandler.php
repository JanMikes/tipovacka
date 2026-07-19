<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\NotificationType;
use App\Repository\CompetitionRepository;
use App\Repository\UserRepository;
use App\Service\Notification\Notifier;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * `premium_balance_low`: the owner's wallet dipped below the warning threshold
 * after a join charge. Deduped per competition + Prague day so a burst of joins
 * on the same day warns only once.
 */
#[AsMessageHandler]
final readonly class NotifyPremiumBalanceLowHandler
{
    private const string PRAGUE_TIMEZONE = 'Europe/Prague';

    public function __construct(
        private UserRepository $userRepository,
        private CompetitionRepository $competitionRepository,
        private Notifier $notifier,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(PremiumBalanceLow $event): void
    {
        $owner = $this->userRepository->find($event->ownerId);
        $competition = $this->competitionRepository->find($event->competitionId);

        if (null === $owner || null === $competition) {
            return;
        }

        $day = $event->occurredOn->setTimezone(new \DateTimeZone(self::PRAGUE_TIMEZONE))->format('Y-m-d');

        $url = $this->urlGenerator->generate(
            'portal_credits',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $this->notifier->notify(
            user: $owner,
            type: NotificationType::PremiumBalanceLow,
            title: 'Nízký zůstatek kreditů',
            body: sprintf('Zůstatek vaší peněženky klesl na %d kr. Doplňte si kredity, aby prémiové platby v soutěži %s prošly.', $event->balance, $competition->name),
            url: $url,
            competition: $competition,
            payload: ['balance' => $event->balance],
            dedupKey: sprintf('premium_balance_low:%s:%s', $competition->id->toRfc4122(), $day),
        );
    }
}
