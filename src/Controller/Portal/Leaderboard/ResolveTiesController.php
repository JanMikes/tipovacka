<?php

declare(strict_types=1);

namespace App\Controller\Portal\Leaderboard;

use App\Command\ResolveLeaderboardTies\ResolveLeaderboardTiesCommand;
use App\Entity\User;
use App\Form\ResolveTiesFormData;
use App\Form\ResolveTiesFormType;
use App\Query\GetGroupLeaderboard\GetGroupLeaderboard;
use App\Query\GetGroupLeaderboard\LeaderboardRow;
use App\Query\QueryBus;
use App\Repository\GroupRepository;
use App\Voter\LeaderboardVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/skupiny/{groupId}/zebricek/shoda',
    name: 'portal_group_leaderboard_resolve_ties',
    requirements: ['groupId' => Requirement::UUID],
)]
final class ResolveTiesController extends AbstractController
{
    public function __construct(
        private readonly GroupRepository $groupRepository,
        private readonly QueryBus $queryBus,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $groupId): Response
    {
        $group = $this->groupRepository->get(Uuid::fromString($groupId));
        $this->denyAccessUnlessGranted(LeaderboardVoter::RESOLVE_TIES, $group);

        /** @var User $user */
        $user = $this->getUser();

        $leaderboard = $this->queryBus->handle(new GetGroupLeaderboard(groupId: $group->id));

        $tiedGroups = $this->groupByTies($leaderboard->rows);

        $formData = new ResolveTiesFormData();
        $form = $this->createForm(ResolveTiesFormType::class, $formData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $orderedUuids = array_map(
                static fn (string $id): Uuid => Uuid::fromString($id),
                $formData->orderedUserIds,
            );

            $this->commandBus->dispatch(new ResolveLeaderboardTiesCommand(
                groupId: $group->id,
                resolverId: $user->id,
                orderedUserIds: $orderedUuids,
            ));

            $this->addFlash('success', 'Rozřazení bylo uloženo.');

            return $this->redirectToRoute('portal_group_leaderboard', [
                'groupId' => $group->id->toRfc4122(),
            ]);
        }

        return $this->render('portal/leaderboard/resolve_ties.html.twig', [
            'form' => $form,
            'group' => $group,
            'tiedGroups' => $tiedGroups,
            'leaderboard' => $leaderboard,
        ]);
    }

    /**
     * @param list<LeaderboardRow> $rows
     *
     * @return list<list<LeaderboardRow>>
     */
    private function groupByTies(array $rows): array
    {
        $buckets = [];

        foreach ($rows as $row) {
            if ($row->isTieResolvedOverride) {
                continue;
            }

            $buckets[$row->totalPoints][] = $row;
        }

        $tiedGroups = [];

        foreach ($buckets as $bucket) {
            if (count($bucket) < 2) {
                continue;
            }

            $tiedGroups[] = $bucket;
        }

        return $tiedGroups;
    }
}
