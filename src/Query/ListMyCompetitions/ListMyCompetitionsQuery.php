<?php

declare(strict_types=1);

namespace App\Query\ListMyCompetitions;

use App\Entity\Competition;
use App\Entity\Membership;
use App\Repository\MembershipRepository;
use App\Service\Competition\MissingTipCounter;
use App\Value\MissingTips;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The viewer's soutěže, most recently joined first — the switcher's options, the
 * Nástěnka's default soutěž and its „Moje soutěže" grid all read this one list.
 *
 * `missingTipCount` is resolved for the whole list in ONE pass through
 * {@see MissingTipCounter} (never a query per card), and by the very service
 * `ListMyPlayingCompetitions` uses, so the „Chybí natipovat N zápasů" badge shows
 * the same number for a soutěž on /nastenka and on /souteze.
 */
#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListMyCompetitionsQuery
{
    public function __construct(
        private MembershipRepository $membershipRepository,
        private MissingTipCounter $missingTipCounter,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return list<CompetitionListItem>
     */
    public function __invoke(ListMyCompetitions $query): array
    {
        $memberships = $this->membershipRepository->findMyActive($query->userId);

        if ([] === $memberships) {
            return [];
        }

        $missingCounts = $query->withMissingTipCounts
            ? array_map(
                static fn (MissingTips $missing): int => $missing->count,
                $this->missingTipCounter->forCompetitions(
                    $memberships[0]->user,
                    array_map(static fn (Membership $m): Competition => $m->competition, $memberships),
                    \DateTimeImmutable::createFromInterface($this->clock->now()),
                ),
            )
            : [];

        return array_map(
            static fn (Membership $m): CompetitionListItem => new CompetitionListItem(
                competitionId: $m->competition->id,
                competitionName: $m->competition->name,
                matchSourceId: $m->competition->matchSource->id,
                matchSourceName: $m->competition->matchSource->name,
                matchSourceIsCompleted: $m->competition->matchSource->isCompleted,
                ownerNickname: $m->competition->owner->displayName,
                isOwner: $m->user->id->equals($m->competition->owner->id),
                joinedAt: $m->joinedAt,
                matchSourceStartAt: $m->competition->matchSource->startAt,
                matchSourceEndAt: $m->competition->matchSource->endAt,
                missingTipCount: $missingCounts[$m->competition->id->toRfc4122()] ?? 0,
            ),
            $memberships,
        );
    }
}
