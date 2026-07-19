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
 * `member_joined`: the competition owner (manager) is told when a new player
 * joins — skipped when the joiner IS the owner (creating/owning your own
 * competition is not "someone joined"). For global competitions the owner is an
 * admin; the type is default-on in-app and any admin can silence it in prefs.
 * Deduped per membership so a retried event never double-notifies.
 */
#[AsMessageHandler]
final readonly class NotifyMemberJoinedHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private CompetitionRepository $competitionRepository,
        private Notifier $notifier,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(MemberJoinedCompetition $event): void
    {
        $competition = $this->competitionRepository->find($event->competitionId);

        if (null === $competition) {
            return;
        }

        if ($competition->owner->id->equals($event->userId)) {
            return;
        }

        $joiner = $this->userRepository->find($event->userId);

        if (null === $joiner) {
            return;
        }

        $url = $this->urlGenerator->generate(
            'portal_competition_detail',
            ['id' => $competition->id->toRfc4122()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $this->notifier->notify(
            user: $competition->owner,
            type: NotificationType::MemberJoined,
            title: sprintf('Nový hráč v soutěži %s', $competition->name),
            body: sprintf('Hráč %s se připojil do soutěže %s.', $joiner->displayName, $competition->name),
            url: $url,
            competition: $competition,
            payload: ['memberId' => $joiner->id->toRfc4122()],
            dedupKey: sprintf('member_joined:%s', $event->membershipId->toRfc4122()),
        );
    }
}
