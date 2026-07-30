<?php

declare(strict_types=1);

namespace App\Command\SetCompetitionMatchDeadline;

use App\Entity\CompetitionMatchSetting;
use App\Enum\UserRole;
use App\Exception\CompetitionMatchDeadlineAfterKickoff;
use App\Exception\CompetitionMatchOpeningAfterDeadline;
use App\Exception\CompetitionMatchOpeningNoteWithoutTime;
use App\Exception\MatchNotInCompetition;
use App\Repository\CompetitionMatchSettingRepository;
use App\Repository\CompetitionRepository;
use App\Repository\SportMatchRepository;
use App\Repository\UserRepository;
use App\Service\Competition\CompetitionMatchProvider;
use App\Service\EffectiveTipDeadlineResolver;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[AsMessageHandler]
final readonly class SetCompetitionMatchDeadlineHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private SportMatchRepository $sportMatchRepository,
        private CompetitionMatchSettingRepository $settingRepository,
        private UserRepository $userRepository,
        private CompetitionMatchProvider $matchProvider,
        private EffectiveTipDeadlineResolver $deadlineResolver,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SetCompetitionMatchDeadlineCommand $command): void
    {
        $competition = $this->competitionRepository->get($command->competitionId);
        $sportMatch = $this->sportMatchRepository->get($command->sportMatchId);

        if (!$this->matchProvider->includes($competition, $sportMatch)) {
            throw MatchNotInCompetition::create();
        }

        $existing = $this->settingRepository->findByCompetitionAndMatch($competition->id, $sportMatch->id);

        // The opening end is admin-only, and the check lives HERE rather than
        // only in the form that hides the fields — the form is a convenience,
        // this is the rule.
        if ($command->changeOpening) {
            $editor = $this->userRepository->get($command->editorId);

            if (!in_array(UserRole::ADMIN->value, $editor->getRoles(), true)) {
                throw new AccessDeniedException('Only an admin can set when tipping opens for a competition match.');
            }
        }

        // A manager's save carries no opening at all: keep whatever an admin set.
        $opensAt = $command->changeOpening ? $command->opensAt : $existing?->opensAt;
        $openingNote = $command->changeOpening ? $command->openingNote : $existing?->openingNote;

        if (null !== $command->deadline && $command->deadline > $sportMatch->kickoffAt) {
            throw CompetitionMatchDeadlineAfterKickoff::create();
        }

        if (null !== $opensAt) {
            // Validate against the deadline this write RESULTS IN, not the one
            // currently stored — the two ends travel together.
            $effectiveDeadline = $this->deadlineResolver->deadlineWithOverride($competition, $sportMatch, $command->deadline);

            if ($opensAt >= $effectiveDeadline) {
                throw CompetitionMatchOpeningAfterDeadline::at($effectiveDeadline);
            }
        } elseif (null !== $openingNote && '' !== trim($openingNote)) {
            throw CompetitionMatchOpeningNoteWithoutTime::create();
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        if (null !== $existing) {
            $existing->updateWindow($command->deadline, $opensAt, $openingNote, $now);

            // Nothing left to say about this match — drop the row rather than
            // keep an empty override around.
            if ($existing->isEmpty) {
                $this->settingRepository->remove($existing);
            }

            return;
        }

        if (null === $command->deadline && null === $opensAt) {
            return;
        }

        $setting = new CompetitionMatchSetting(
            id: $this->identity->next(),
            competition: $competition,
            sportMatch: $sportMatch,
            deadline: $command->deadline,
            createdAt: $now,
            opensAt: $opensAt,
            openingNote: $openingNote,
        );

        $this->settingRepository->save($setting);
    }
}
