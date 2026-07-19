<?php

declare(strict_types=1);

namespace App\Command\SendGuessReminders;

use App\Entity\Competition;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Repository\CompetitionRepository;
use App\Repository\GuessRepository;
use App\Repository\MembershipRepository;
use App\Service\Competition\CompetitionMatchProvider;
use App\Service\CzechPlural;
use App\Service\EffectiveTipDeadlineResolver;
use App\Service\Notification\Notifier;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
final readonly class SendGuessRemindersHandler
{
    private const string PRAGUE_TIMEZONE = 'Europe/Prague';
    private const string REMINDER_WINDOW = '+24 hours';

    public function __construct(
        private CompetitionRepository $competitionRepository,
        private MembershipRepository $membershipRepository,
        private GuessRepository $guessRepository,
        private CompetitionMatchProvider $matchProvider,
        private EffectiveTipDeadlineResolver $deadlineResolver,
        private Notifier $notifier,
        private ClockInterface $clock,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(SendGuessRemindersCommand $command): void
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $horizon = $now->modify(self::REMINDER_WINDOW);

        foreach ($this->competitionRepository->findAllActive() as $competition) {
            $members = $this->membershipRepository->findActiveByCompetition($competition->id);

            if ([] === $members) {
                continue;
            }

            // Only matches still open for guesses with a future kickoff can have a
            // reminder-worthy deadline; deadlines are then resolved per member.
            $openMatches = array_values(array_filter(
                $this->matchProvider->matchesFor($competition),
                static fn (SportMatch $match): bool => $match->isOpenForGuesses && $match->kickoffAt > $now,
            ));

            if ([] === $openMatches) {
                continue;
            }

            $url = $this->urlGenerator->generate(
                'portal_competition_detail',
                ['id' => $competition->id->toRfc4122()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

            foreach ($members as $membership) {
                $this->remindMember($competition, $membership->user, $openMatches, $now, $horizon, $url);
            }
        }
    }

    /**
     * @param list<SportMatch> $openMatches
     */
    private function remindMember(
        Competition $competition,
        User $user,
        array $openMatches,
        \DateTimeImmutable $now,
        \DateTimeImmutable $horizon,
        string $url,
    ): void {
        $deadlines = $this->deadlineResolver->deadlinesFor($competition, $openMatches, $user);

        /** @var array<string, array{count: int, earliest: \DateTimeImmutable}> $missingByDay */
        $missingByDay = [];

        foreach ($openMatches as $match) {
            $deadline = $deadlines[$match->id->toRfc4122()];

            // Within the next 24 h and not yet passed.
            if ($deadline <= $now || $deadline > $horizon) {
                continue;
            }

            if (null !== $this->guessRepository->findActiveByUserMatchCompetition($user->id, $match->id, $competition->id)) {
                continue;
            }

            $day = $this->pragueDay($deadline);

            if (!isset($missingByDay[$day])) {
                $missingByDay[$day] = ['count' => 0, 'earliest' => $deadline];
            }

            ++$missingByDay[$day]['count'];

            if ($deadline < $missingByDay[$day]['earliest']) {
                $missingByDay[$day]['earliest'] = $deadline;
            }
        }

        foreach ($missingByDay as $day => $info) {
            $count = $info['count'];
            $deadlineLabel = $info['earliest']
                ->setTimezone(new \DateTimeZone(self::PRAGUE_TIMEZONE))
                ->format('j. n. H:i');

            $this->notifier->notify(
                user: $user,
                type: NotificationType::GuessReminder,
                title: sprintf('Chybí vám tipy v soutěži %s', $competition->name),
                body: sprintf(
                    'V soutěži %s vám chybí %s na %d %s, uzávěrka %s.',
                    $competition->name,
                    CzechPlural::tip($count),
                    $count,
                    CzechPlural::zapas($count),
                    $deadlineLabel,
                ),
                url: $url,
                competition: $competition,
                payload: ['missing' => $count],
                dedupKey: sprintf('guess_reminder:%s:%s', $competition->id->toRfc4122(), $day),
            );
        }
    }

    private function pragueDay(\DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new \DateTimeZone(self::PRAGUE_TIMEZONE))->format('Y-m-d');
    }
}
