<?php

declare(strict_types=1);

namespace App\Twig\Components\Guess;

use App\Entity\SportMatch;
use App\Entity\User;
use App\Query\GetGuessesForMatchInGroup\GetGuessesForMatchInGroup;
use App\Query\GetGuessesForMatchInGroup\GuessesForMatchInGroupResult;
use App\Query\QueryBus;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Uid\Uuid;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(name: 'Guess:MatchGuessesList')]
final class MatchGuessesList
{
    use DefaultActionTrait;

    #[LiveProp]
    public SportMatch $sportMatch;

    #[LiveProp]
    public string $groupId = '';

    public function __construct(
        private readonly QueryBus $queryBus,
        private readonly Security $security,
    ) {
    }

    public GuessesForMatchInGroupResult $guesses {
        get {
            $user = $this->security->getUser();
            $viewerId = $user instanceof User ? $user->id : Uuid::v7();

            return $this->queryBus->handle(new GetGuessesForMatchInGroup(
                groupId: Uuid::fromString($this->groupId),
                sportMatchId: $this->sportMatch->id,
                viewerId: $viewerId,
            ));
        }
    }
}
