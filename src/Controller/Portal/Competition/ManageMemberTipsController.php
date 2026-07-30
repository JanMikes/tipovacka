<?php

declare(strict_types=1);

namespace App\Controller\Portal\Competition;

use App\Entity\User;
use App\Repository\CompetitionRepository;
use App\Repository\GuessRepository;
use App\Repository\MembershipRepository;
use App\Repository\UserRepository;
use App\Service\Competition\CompetitionGuessFeatures;
use App\Service\Competition\CompetitionMatchProvider;
use App\Service\Competition\TipVisibilityGate;
use App\Service\EffectiveTipDeadlineResolver;
use App\Voter\CompetitionVoter;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/souteze/{id}/spravovat-tipy',
    name: 'competition_manage_member_tips',
    requirements: ['id' => Requirement::UUID],
)]
#[IsGranted('ROLE_USER')]
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
        private readonly TipVisibilityGate $visibilityGate,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        $competition = $this->competitionRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(CompetitionVoter::MANAGE_MEMBERS, $competition);

        // On-behalf tipping is disabled for global competitions — each player owns their tips.
        if ($competition->isGlobal) {
            throw $this->createAccessDeniedException('On-behalf tipping is disabled for global competitions.');
        }

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
                $windows = $this->deadlineResolver->windowsFor($competition, $candidateMatches, $selectedMember);

                // Managing someone else's tips does NOT reveal them: the manager
                // learns only WHETHER a tip is filled and may overwrite it, unless
                // they are otherwise entitled to see this member's tips (their own
                // row, or a bought/premium entitlement — the rows here are all
                // still tippable, so no match on this page has a result yet).
                /** @var User $manager */
                $manager = $this->getUser();
                $showScores = $selectedMember->id->equals($manager->id)
                    ? array_fill_keys(array_map(static fn ($m) => $m->id->toRfc4122(), $candidateMatches), true)
                    : $this->visibilityGate->othersTipsVisibleByMatch($competition, $manager, $candidateMatches);

                $guessesByMatch = $this->guessRepository->activeByUserInCompetitionIndexedByMatch(
                    $selectedMember->id,
                    $competition->id,
                );

                foreach ($candidateMatches as $sportMatch) {
                    $matchKey = $sportMatch->id->toRfc4122();
                    $window = $windows[$matchKey];

                    if ($window->isClosed($now)) {
                        continue;
                    }

                    $guess = $guessesByMatch[$matchKey] ?? null;

                    // A match still waiting to open keeps its row but takes no
                    // input: an organizer gets no head start over their members.
                    $rows[] = [
                        'match' => $sportMatch,
                        'hasGuess' => null !== $guess,
                        'guess' => ($showScores[$matchKey] ?? false) ? $guess : null,
                        'waiting' => $window->isWaiting($now),
                        'opensAt' => $window->opensAt,
                        'openingNote' => $window->openingNote,
                    ];
                }
            }
        }

        $lockMoment = $this->deadlineResolver->lockMomentFor($competition);

        return $this->render('portal/competition/manage_member_tips.html.twig', [
            'competition' => $competition,
            'members' => $members,
            'selectedMember' => $selectedMember,
            'rows' => $rows,
            // B5: no rows after a lock is not „nothing scheduled" — say which it is.
            'lock_moment' => $lockMoment,
            'tips_locked' => null !== $lockMoment && $lockMoment <= $now,
            'sport' => $competition->matchSource->sport,
            'features' => $this->guessFeatures->featuresFor($competition->id),
        ]);
    }
}
