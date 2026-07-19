<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Competition;
use App\Entity\SportMatch;
use App\Enum\NotificationType;
use App\Repository\CompetitionRepository;
use App\Repository\MembershipRepository;
use App\Repository\SportMatchRepository;
use App\Service\Competition\CompetitionMatchProvider;
use App\Service\EffectiveTipDeadlineResolver;
use App\Service\Notification\Notifier;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * `match_added`: a match created on a source is announced to the members of each
 * competition that (a) includes it and (b) has ALREADY STARTED — i.e. the match
 * entered the competition after its lock moment (a late play-off addition). Fresh
 * competitions whose first matches are still ahead are not spammed; subset
 * competitions never auto-include a new source match, so they are skipped here.
 */
#[AsMessageHandler]
final readonly class NotifyMatchAddedHandler
{
    private const string PRAGUE_TIMEZONE = 'Europe/Prague';

    public function __construct(
        private SportMatchRepository $sportMatchRepository,
        private CompetitionRepository $competitionRepository,
        private MembershipRepository $membershipRepository,
        private CompetitionMatchProvider $matchProvider,
        private EffectiveTipDeadlineResolver $deadlineResolver,
        private Notifier $notifier,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(SportMatchCreated $event): void
    {
        $match = $this->sportMatchRepository->find($event->sportMatchId);

        if (null === $match) {
            return;
        }

        $kickoff = $match->kickoffAt
            ->setTimezone(new \DateTimeZone(self::PRAGUE_TIMEZONE))
            ->format('j. n. Y H:i');

        foreach ($this->competitionRepository->findByMatchSource($event->matchSourceId) as $competition) {
            if (!$this->matchProvider->includes($competition, $match)) {
                continue;
            }

            if (!$this->hasStartedBefore($competition, $match)) {
                continue;
            }

            $url = $this->urlGenerator->generate(
                'portal_competition_detail',
                ['id' => $competition->id->toRfc4122()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

            $dedupKey = sprintf('match_added:%s:%s', $match->id->toRfc4122(), $competition->id->toRfc4122());

            foreach ($this->membershipRepository->findActiveByCompetition($competition->id) as $membership) {
                $this->notifier->notify(
                    user: $membership->user,
                    type: NotificationType::MatchAdded,
                    title: sprintf('Nový zápas: %s – %s', $match->homeTeam, $match->awayTeam),
                    body: sprintf('Do soutěže %s přibyl zápas %s – %s (%s).', $competition->name, $match->homeTeam, $match->awayTeam, $kickoff),
                    url: $url,
                    competition: $competition,
                    payload: ['sportMatchId' => $match->id->toRfc4122()],
                    dedupKey: $dedupKey,
                );
            }
        }
    }

    /**
     * The match is a late addition iff the competition's lock moment already
     * passed when the match entered it — equivalently, the match was created
     * after that moment (mirrors {@see EffectiveTipDeadlineResolver} row 2).
     */
    private function hasStartedBefore(Competition $competition, SportMatch $match): bool
    {
        $lockMoment = $this->deadlineResolver->lockMomentFor($competition);

        return null !== $lockMoment && $match->createdAt > $lockMoment;
    }
}
