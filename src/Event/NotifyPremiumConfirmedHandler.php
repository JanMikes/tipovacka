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
 * `premium_enabled`: at competition start every premium charge was covered — the
 * competition stays premium and the owner is reassured once (deduped per
 * competition).
 */
#[AsMessageHandler]
final readonly class NotifyPremiumConfirmedHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private CompetitionRepository $competitionRepository,
        private Notifier $notifier,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(PremiumConfirmed $event): void
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
            type: NotificationType::PremiumEnabled,
            title: sprintf('Prémium potvrzeno v soutěži %s', $competition->name),
            body: sprintf('Skvělé! Všechny prémiové platby v soutěži %s byly při startu pokryty. Soutěž zůstává prémiová se všemi vylepšeními pro hráče.', $competition->name),
            url: $url,
            competition: $competition,
            dedupKey: sprintf('premium_enabled:%s', $competition->id->toRfc4122()),
        );
    }
}
