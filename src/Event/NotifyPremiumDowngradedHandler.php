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
 * `premium_downgraded`: at competition start an uncovered charge forced a
 * refund-all + switch to boosts. The owner is told once (deduped per competition).
 */
#[AsMessageHandler]
final readonly class NotifyPremiumDowngradedHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private CompetitionRepository $competitionRepository,
        private Notifier $notifier,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(PremiumDowngraded $event): void
    {
        $owner = $this->userRepository->find($event->ownerId);
        $competition = $this->competitionRepository->find($event->competitionId);

        if (null === $owner || null === $competition) {
            return;
        }

        $url = $this->urlGenerator->generate(
            'portal_competition_detail',
            ['id' => $competition->id->toRfc4122()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $this->notifier->notify(
            user: $owner,
            type: NotificationType::PremiumDowngraded,
            title: sprintf('Prémium zrušeno v soutěži %s', $competition->name),
            body: sprintf('V soutěži %s nebyla při startu pokryta prémiová platba. Všem hráčům jsme prémium vrátili a soutěž přepnuli na vylepšení. Prémium můžete znovu zapnout kdykoliv po dobití kreditů.', $competition->name),
            url: $url,
            competition: $competition,
            dedupKey: sprintf('premium_downgraded:%s', $competition->id->toRfc4122()),
        );
    }
}
