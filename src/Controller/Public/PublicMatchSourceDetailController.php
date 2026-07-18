<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Entity\User;
use App\Exception\MatchSourceNotFound;
use App\Query\GetMatchSourceRuleConfiguration\GetMatchSourceRuleConfiguration;
use App\Query\ListCompetitionsForMatchSource\ListCompetitionsForMatchSource;
use App\Query\ListMatchSourceSportMatches\ListMatchSourceSportMatches;
use App\Query\ListMyOpenJoinRequests\ListMyOpenJoinRequests;
use App\Query\QueryBus;
use App\Repository\MatchSourceRepository;
use App\Repository\MembershipRepository;
use App\Voter\MatchSourceVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route('/turnaje/{id}', name: 'public_match_source_detail', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
final class PublicMatchSourceDetailController extends AbstractController
{
    public function __construct(
        private readonly MatchSourceRepository $matchSourceRepository,
        private readonly MembershipRepository $membershipRepository,
        private readonly QueryBus $queryBus,
    ) {
    }

    public function __invoke(string $id): Response
    {
        try {
            $matchSource = $this->matchSourceRepository->get(Uuid::fromString($id));
        } catch (MatchSourceNotFound $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        }

        if (null !== $matchSource->deletedAt || !$this->isGranted(MatchSourceVoter::VIEW, $matchSource)) {
            throw new NotFoundHttpException('MatchSource not found.');
        }

        $competitions = $this->queryBus->handle(new ListCompetitionsForMatchSource(matchSourceId: $matchSource->id));
        $matches = $this->queryBus->handle(new ListMatchSourceSportMatches(matchSourceId: $matchSource->id));
        $ruleConfiguration = $this->queryBus->handle(new GetMatchSourceRuleConfiguration(matchSourceId: $matchSource->id));

        $user = $this->getUser();
        $memberCompetitionIds = [];
        $pendingRequestCompetitionIds = [];
        if ($user instanceof User) {
            foreach ($this->membershipRepository->findMyActive($user->id) as $membership) {
                if ($membership->competition->matchSource->id->equals($matchSource->id)) {
                    $memberCompetitionIds[] = $membership->competition->id->toRfc4122();
                }
            }

            foreach ($this->queryBus->handle(new ListMyOpenJoinRequests(userId: $user->id)) as $openRequest) {
                $pendingRequestCompetitionIds[] = $openRequest->competitionId->toRfc4122();
            }
        }

        return $this->render('public/match_source_detail.html.twig', [
            'match_source' => $matchSource,
            'competitions' => $competitions,
            'sport_matches' => $matches,
            'member_competition_ids' => $memberCompetitionIds,
            'pending_request_competition_ids' => $pendingRequestCompetitionIds,
            'rule_items' => $ruleConfiguration->items,
        ]);
    }
}
