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
 * `premium_charge_uncovered`: a per-player premium charge could not be covered —
 * the owner (wallet payer) is warned to top up. Not deduped: each uncovered
 * member is a distinct, actionable charge.
 */
#[AsMessageHandler]
final readonly class NotifyPremiumChargeUncoveredHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private CompetitionRepository $competitionRepository,
        private Notifier $notifier,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(PremiumChargeUncovered $event): void
    {
        $owner = $this->userRepository->find($event->ownerId);
        $competition = $this->competitionRepository->find($event->competitionId);

        if (null === $owner || null === $competition) {
            return;
        }

        $url = $this->urlGenerator->generate(
            'portal_credits',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $this->notifier->notify(
            user: $owner,
            type: NotificationType::PremiumChargeUncovered,
            title: sprintf('Nepokrytá platba v soutěži %s', $competition->name),
            body: sprintf('Prémiovou platbu %d kr. za nového hráče v soutěži %s se nepodařilo strhnout — dobijte si kredity, jinak bude prémium při startu zrušeno.', $event->amount, $competition->name),
            url: $url,
            competition: $competition,
            payload: ['amount' => $event->amount, 'memberId' => $event->memberId->toRfc4122()],
        );
    }
}
