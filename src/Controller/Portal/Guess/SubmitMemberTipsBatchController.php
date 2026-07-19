<?php

declare(strict_types=1);

namespace App\Controller\Portal\Guess;

use App\Command\SubmitGuessOnBehalf\SubmitGuessOnBehalfCommand;
use App\Command\UpdateGuessOnBehalf\UpdateGuessOnBehalfCommand;
use App\Entity\User;
use App\Exception\GuessAlreadyExists;
use App\Exception\GuessDeadlinePassed;
use App\Exception\GuessFeatureNotEnabled;
use App\Exception\InvalidGuessScore;
use App\Exception\MatchNotInCompetition;
use App\Exception\NotAMember;
use App\Repository\CompetitionRepository;
use App\Repository\GuessRepository;
use App\Repository\SportMatchRepository;
use App\Repository\UserRepository;
use App\Service\Competition\CompetitionGuessFeatures;
use App\Service\Guess\GuessScorerWriter;
use App\Value\PeriodScores;
use App\Voter\CompetitionVoter;
use App\Voter\GuessOnBehalfContext;
use App\Voter\GuessVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/souteze/{competitionId}/spravovat-tipy/{memberId}',
    name: 'portal_competition_guess_on_behalf_batch',
    requirements: [
        'competitionId' => Requirement::UUID,
        'memberId' => Requirement::UUID,
    ],
    methods: ['POST'],
)]
final class SubmitMemberTipsBatchController extends AbstractController
{
    public function __construct(
        private readonly CompetitionRepository $competitionRepository,
        private readonly SportMatchRepository $sportMatchRepository,
        private readonly UserRepository $userRepository,
        private readonly GuessRepository $guessRepository,
        private readonly CompetitionGuessFeatures $guessFeatures,
        private readonly GuessScorerWriter $scorerWriter,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $competitionId, string $memberId): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $competition = $this->competitionRepository->get(Uuid::fromString($competitionId));
        $this->denyAccessUnlessGranted(CompetitionVoter::MANAGE_MEMBERS, $competition);

        $csrfToken = $request->request->get('_token');
        $expectedToken = sprintf('guess_on_behalf_batch_%s_%s', $competitionId, $memberId);

        if (!\is_string($csrfToken) || !$this->isCsrfTokenValid($expectedToken, $csrfToken)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $member = $this->userRepository->get(Uuid::fromString($memberId));

        $guesses = $request->request->all('guesses');
        $features = $this->guessFeatures->featuresFor($competition->id);
        $sport = $competition->matchSource->sport;

        $saved = 0;
        $errors = [];

        foreach ($guesses as $sportMatchIdString => $scores) {
            if (!\is_string($sportMatchIdString) || !Uuid::isValid($sportMatchIdString)) {
                continue;
            }

            if (!\is_array($scores)) {
                continue;
            }

            $homeRaw = $scores['homeScore'] ?? '';
            $awayRaw = $scores['awayScore'] ?? '';

            if (!\is_string($homeRaw) || !\is_string($awayRaw)) {
                continue;
            }

            $homeRaw = trim($homeRaw);
            $awayRaw = trim($awayRaw);

            $bothEmpty = '' === $homeRaw && '' === $awayRaw;

            // An empty row is skipped — unless periods were typed without a
            // main tip, which gets an explicit error instead of a silent drop.
            if ($bothEmpty && (!$features->periodTips || !$this->hasAnyPeriodInput($scores, $sport->periodCount))) {
                continue;
            }

            $sportMatch = $this->sportMatchRepository->get(Uuid::fromString($sportMatchIdString));
            $label = sprintf('%s vs %s', $sportMatch->homeTeam, $sportMatch->awayTeam);

            if ($bothEmpty) {
                $errors[] = sprintf('%s: Vyplňte i celkové skóre zápasu.', $label);

                continue;
            }

            if ('' === $homeRaw || '' === $awayRaw || !ctype_digit($homeRaw) || !ctype_digit($awayRaw)) {
                $errors[] = sprintf('%s: vyplňte prosím obě skóre.', $label);

                continue;
            }

            $homeScore = (int) $homeRaw;
            $awayScore = (int) $awayRaw;

            $periodScores = null;

            if ($features->periodTips) {
                $periodScores = $this->parsePeriodScores($scores, $sport->periodCount, $sport->periodLabelPlural, $label, $errors);

                if (false === $periodScores) {
                    continue; // Error collected.
                }
            }

            $existing = $this->guessRepository->findActiveByUserMatchCompetition(
                $member->id,
                $sportMatch->id,
                $competition->id,
            );

            // Unchanged-skip: parts of DISABLED features are ignored in the
            // comparison, so a no-op save never normalizes (drops) legacy tip
            // parts of a since-disabled feature.
            if (null !== $existing
                && $existing->homeScore === $homeScore
                && $existing->awayScore === $awayScore
                && (!$features->periodTips || $existing->periodScores?->toArray() === $periodScores?->toArray())
            ) {
                continue;
            }

            try {
                if (null === $existing) {
                    $context = new GuessOnBehalfContext($competition, $sportMatch, $member);
                    $this->denyAccessUnlessGranted(GuessVoter::SUBMIT_ON_BEHALF, $context);

                    $this->commandBus->dispatch(new SubmitGuessOnBehalfCommand(
                        actingUserId: $user->id,
                        targetUserId: $member->id,
                        competitionId: $competition->id,
                        sportMatchId: $sportMatch->id,
                        homeScore: $homeScore,
                        awayScore: $awayScore,
                        periodScores: $periodScores,
                    ));
                } else {
                    $this->denyAccessUnlessGranted(GuessVoter::UPDATE_ON_BEHALF, $existing);

                    // The batch page edits only the main score + periods; the
                    // overtime and scorer parts of a full-replace update are
                    // passed through untouched from the existing guess. The OT
                    // pair travels only while still valid against the NEW tip,
                    // else it is dropped (consistent with the non-draw drop).
                    $carryOvertime = $features->overtimeTip && $existing->overtimeTipValidFor($homeScore, $awayScore);

                    $this->commandBus->dispatch(new UpdateGuessOnBehalfCommand(
                        actingUserId: $user->id,
                        guessId: $existing->id,
                        homeScore: $homeScore,
                        awayScore: $awayScore,
                        periodScores: $periodScores,
                        overtimeHomeScore: $carryOvertime ? $existing->overtimeHomeScore : null,
                        overtimeAwayScore: $carryOvertime ? $existing->overtimeAwayScore : null,
                        scorers: $features->scorerTips ? $this->scorerWriter->inputsFor($existing) : [],
                    ));
                }

                ++$saved;
            } catch (HandlerFailedException $e) {
                $inner = $e->getPrevious();

                if ($inner instanceof InvalidGuessScore
                    || $inner instanceof GuessFeatureNotEnabled
                    || $inner instanceof GuessDeadlinePassed
                    || $inner instanceof GuessAlreadyExists
                    || $inner instanceof NotAMember
                    || $inner instanceof MatchNotInCompetition
                ) {
                    $errors[] = sprintf('%s: %s', $label, $inner->getMessage());
                } else {
                    throw $e;
                }
            }
        }

        if ($saved > 0) {
            $this->addFlash('success', sprintf('Uloženo tipů: %d (pro %s).', $saved, $member->displayName));
        }

        foreach ($errors as $error) {
            $this->addFlash('error', $error);
        }

        if (0 === $saved && 0 === count($errors)) {
            $this->addFlash('info', 'Nebyly provedeny žádné změny.');
        }

        return $this->redirectToRoute('portal_competition_manage_member_tips', [
            'id' => $competition->id->toRfc4122(),
            'member' => $member->id->toRfc4122(),
        ]);
    }

    /**
     * All-or-nothing period tip from `guesses[<matchId>][periods][<n>][home|away]`.
     *
     * @param array<mixed> $scores
     * @param list<string> $errors
     *
     * @return PeriodScores|false|null false = validation error (collected)
     */
    private function parsePeriodScores(array $scores, int $periodCount, string $periodLabelPlural, string $label, array &$errors): PeriodScores|false|null
    {
        $periodsRaw = $scores['periods'] ?? [];

        if (!\is_array($periodsRaw)) {
            return null;
        }

        $pairs = [];
        $anyFilled = false;
        $allFilled = true;

        for ($number = 1; $number <= $periodCount; ++$number) {
            $pair = $periodsRaw[$number] ?? [];
            $homeRaw = \is_array($pair) && \is_string($pair['home'] ?? null) ? trim($pair['home']) : '';
            $awayRaw = \is_array($pair) && \is_string($pair['away'] ?? null) ? trim($pair['away']) : '';

            if ('' !== $homeRaw || '' !== $awayRaw) {
                $anyFilled = true;
            }

            if ('' === $homeRaw || '' === $awayRaw || !ctype_digit($homeRaw) || !ctype_digit($awayRaw)) {
                $allFilled = false;
                $pairs[] = [0, 0];

                continue;
            }

            $pairs[] = [(int) $homeRaw, (int) $awayRaw];
        }

        if (!$anyFilled) {
            return null;
        }

        if (!$allFilled) {
            $errors[] = sprintf('%s: vyplňte prosím všechny %s, nebo je nechte prázdné.', $label, $periodLabelPlural);

            return false;
        }

        return PeriodScores::fromArray($pairs);
    }

    /**
     * Whether any period input of the row carries a value (used to distinguish
     * an intentional empty row from a periods-without-main-score mistake).
     *
     * @param array<mixed> $scores
     */
    private function hasAnyPeriodInput(array $scores, int $periodCount): bool
    {
        $periodsRaw = $scores['periods'] ?? [];

        if (!\is_array($periodsRaw)) {
            return false;
        }

        for ($number = 1; $number <= $periodCount; ++$number) {
            $pair = $periodsRaw[$number] ?? [];

            if (!\is_array($pair)) {
                continue;
            }

            foreach (['home', 'away'] as $key) {
                $value = $pair[$key] ?? null;

                if (\is_string($value) && '' !== trim($value)) {
                    return true;
                }
            }
        }

        return false;
    }
}
