<?php

declare(strict_types=1);

namespace App\Controller\Portal\Competition;

use App\Repository\CompetitionRepository;
use App\Repository\GuessRepository;
use App\Repository\MembershipRepository;
use App\Repository\UserRepository;
use App\Service\Competition\CompetitionGuessFeatures;
use App\Service\Competition\CompetitionMatchProvider;
use App\Service\EffectiveTipDeadlineResolver;
use App\Voter\CompetitionVoter;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/souteze/{id}/spravovat-tipy',
    name: 'portal_competition_manage_member_tips',
    requirements: ['id' => Requirement::UUID],
)]
final class ManageMemberTipsController extends AbstractController
{
    public function __construct(
        private readonly CompetitionRepository $competitionRepository,
        private readonly MembershipRepository $membershipRepository,
        private readonly GuessRepository $guessRepository,
        private readonly UserRepository $userRepository,
        private readonly CompetitionMatchProvider $matchProvider,
        private readonly CompetitionGuessFeatures $guessFeatures,
        private readonly EffectiveTipDeadlineResolver $deadlineResolver,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        $competition = $this->competitionRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(CompetitionVoter::MANAGE_MEMBERS, $competition);

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $memberships = $this->membershipRepository->findActiveByCompetition($competition->id);
        $members = array_map(static fn ($m) => $m->user, $memberships);

        $selectedMemberId = $request->query->get('member');
        $selectedMember = null;
        $rows = [];

        if (\is_string($selectedMemberId) && '' !== $selectedMemberId && Uuid::isValid($selectedMemberId)) {
            $candidate = $this->userRepository->find(Uuid::fromString($selectedMemberId));

            $isMember = null !== $candidate && null !== array_find(
                $members,
                static fn ($m) => $m->id->equals($candidate->id),
            );

            if ($isMember) {
                $selectedMember = $candidate;

                // Open = tippable through the resolver for the SELECTED member
                // (their entitlements, their tip window) — not a raw kickoff check.
                $candidateMatches = array_values(array_filter(
                    $this->matchProvider->matchesFor($competition),
                    static fn ($m) => $m->isOpenForGuesses,
                ));
                $deadlines = $this->deadlineResolver->deadlinesFor($competition, $candidateMatches, $selectedMember);

                foreach ($candidateMatches as $sportMatch) {
                    if ($deadlines[$sportMatch->id->toRfc4122()] <= $now) {
                        continue;
                    }

                    $rows[] = [
                        'match' => $sportMatch,
                        'guess' => $this->guessRepository->findActiveByUserMatchCompetition(
                            $selectedMember->id,
                            $sportMatch->id,
                            $competition->id,
                        ),
                    ];
                }
            }
        }

        return $this->render('portal/competition/manage_member_tips.html.twig', [
            'competition' => $competition,
            'members' => $members,
            'selectedMember' => $selectedMember,
            'rows' => $rows,
            'sport' => $competition->matchSource->sport,
            'features' => $this->guessFeatures->featuresFor($competition->id),
        ]);
    }
}
