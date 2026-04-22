<?php

declare(strict_types=1);

namespace App\Query\GetGuessesForMatchInGroup;

use App\Repository\GroupRepository;
use App\Repository\GuessRepository;
use App\Repository\SportMatchRepository;
use App\Service\EffectiveTipDeadlineResolver;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetGuessesForMatchInGroupQuery
{
    public function __construct(
        private GuessRepository $guessRepository,
        private GroupRepository $groupRepository,
        private SportMatchRepository $sportMatchRepository,
        private EffectiveTipDeadlineResolver $deadlineResolver,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(GetGuessesForMatchInGroup $query): GuessesForMatchInGroupResult
    {
        $guesses = $this->guessRepository->listActiveByGroupAndMatch(
            $query->groupId,
            $query->sportMatchId,
        );

        $hideOthers = false;

        if ($query->applyHiding) {
            $group = $this->groupRepository->get($query->groupId);
            $sportMatch = $this->sportMatchRepository->get($query->sportMatchId);
            $now = \DateTimeImmutable::createFromInterface($this->clock->now());
            $deadline = $this->deadlineResolver->resolve($group, $sportMatch);
            $hideOthers = $now < $deadline;
        }

        $items = array_map(
            static function ($g) use ($query, $hideOthers): GuessForMatchItem {
                $isMine = $g->user->id->equals($query->viewerId);
                $hidden = $hideOthers && !$isMine;

                return new GuessForMatchItem(
                    userId: $g->user->id,
                    nickname: $g->user->displayName,
                    homeScore: $hidden ? null : $g->homeScore,
                    awayScore: $hidden ? null : $g->awayScore,
                    submittedAt: $g->submittedAt,
                    updatedAt: $g->updatedAt,
                    isMine: $isMine,
                    hidden: $hidden,
                );
            },
            $guesses,
        );

        return new GuessesForMatchInGroupResult(items: $items);
    }
}
