<?php

declare(strict_types=1);

namespace App\Service\SportMatch;

use App\Entity\SportMatch;
use App\Enum\MatchSourceKind;
use App\Enum\UserRole;
use App\Exception\MatchResultsMissing;
use App\Repository\GuessRepository;
use App\Repository\SportMatchRepository;
use App\Repository\UserRepository;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The daily „were all played matches evaluated?" check — the safety net under
 * every way a result can fail to arrive, so that a user is never the one who
 * tells us (2026-11-18: a player reported the Premier League stuck for three
 * weeks before anything on our side did).
 *
 *  - CURATED zdroje (ours to maintain, feed-bound or manual): any match that
 *    kicked off {@see MISSING_AFTER} ago and is still scheduled/live is an
 *    incident — logged at ERROR as one dedicated {@see MatchResultsMissing}
 *    Sentry issue, mailed to every admin, and the command exits 1 (lily cron
 *    alert + Sentry cron monitor).
 *  - PRIVATE zdroje are the organizer's to maintain: listed in the admin mail
 *    for the last {@see PRIVATE_LOOKBACK} only, as information (so an admin may
 *    nudge the organizer), never as a failure — an abandoned private tipovačka
 *    must not page anyone every day for ever.
 */
final readonly class MissingResultsCheck
{
    /** Healthy feeds finish a match ~2 h after kickoff (p95 2.2 h); manual entry gets a working day. */
    public const string MISSING_AFTER = '-12 hours';

    private const string PRIVATE_LOOKBACK = '-7 days';

    public function __construct(
        private SportMatchRepository $sportMatches,
        private GuessRepository $guesses,
        private UserRepository $users,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function run(bool $notify = true): MissingResultsReport
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $cutoff = $now->modify(self::MISSING_AFTER);

        $report = new MissingResultsReport(
            curated: $this->describe($this->sportMatches->listMissingResult(MatchSourceKind::Curated, $cutoff)),
            privateRecent: $this->describe($this->sportMatches->listMissingResult(
                MatchSourceKind::Private,
                $cutoff,
                $now->modify(self::PRIVATE_LOOKBACK),
            )),
        );

        if (!$notify) {
            return $report;
        }

        if ([] !== $report->curated) {
            $this->logger->error('Played matches of curated zdroje have no result — tips are not being evaluated.', [
                'exception' => MatchResultsMissing::inCuratedSources(count($report->curated), $report->curated[0]->label),
                'count' => count($report->curated),
                'matches' => array_map(static fn (MissingResult $missing): string => $missing->label, array_slice($report->curated, 0, 30)),
            ]);
        }

        if (!$report->isEmpty) {
            $this->mailAdmins($report);
        }

        return $report;
    }

    /**
     * @param list<SportMatch> $matches
     *
     * @return list<MissingResult>
     */
    private function describe(array $matches): array
    {
        $tips = $this->guesses->countActiveByMatches(array_map(static fn (SportMatch $match) => $match->id, $matches));

        return array_map(fn (SportMatch $match): MissingResult => new MissingResult(
            sourceName: $match->matchSource->name,
            homeTeam: $match->homeTeam->name,
            awayTeam: $match->awayTeam->name,
            kickoffAt: $match->kickoffAt,
            feedBound: null !== $match->matchSource->feedProvider,
            tips: $tips[$match->id->toRfc4122()] ?? 0,
            url: $this->urlGenerator->generate('sport_match_detail', ['id' => $match->id], UrlGeneratorInterface::ABSOLUTE_URL),
        ), $matches);
    }

    private function mailAdmins(MissingResultsReport $report): void
    {
        $count = count($report->curated) + count($report->privateRecent);

        foreach ($this->users->findByRole(UserRole::ADMIN) as $admin) {
            if (null === $admin->email) {
                continue;
            }

            $this->mailer->send((new TemplatedEmail())
                ->to(new Address($admin->email, $admin->displayName))
                ->subject(sprintf('Wtips: %d %s bez výsledku', $count, 1 === $count ? 'odehraný zápas' : ($count < 5 ? 'odehrané zápasy' : 'odehraných zápasů')))
                ->htmlTemplate('emails/admin_missing_results.html.twig')
                ->context([
                    'curated' => $report->curated,
                    'privateRecent' => $report->privateRecent,
                ]));
        }
    }
}
