<?php

declare(strict_types=1);

namespace App\Twig\Components\Guess;

use App\Entity\SportMatch;
use App\Entity\User;
use App\Query\GetGuessesForMatchInCompetition\GetGuessesForMatchInCompetition;
use App\Query\GetGuessesForMatchInCompetition\GuessesForMatchInCompetitionResult;
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
    public string $competitionId = '';

    #[LiveProp]
    public bool $applyHiding = false;

    public function __construct(
        private readonly QueryBus $queryBus,
        private readonly Security $security,
    ) {
    }

    public GuessesForMatchInCompetitionResult $guesses {
        get {
            $user = $this->security->getUser();
            $viewerId = $user instanceof User ? $user->id : Uuid::v7();

            return $this->queryBus->handle(new GetGuessesForMatchInCompetition(
                competitionId: Uuid::fromString($this->competitionId),
                sportMatchId: $this->sportMatch->id,
                viewerId: $viewerId,
                applyHiding: $this->applyHiding,
            ));
        }
    }
}
