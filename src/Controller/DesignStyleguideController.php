<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\CompetitionMonetization;
use App\Enum\CompetitionStateFilter;
use App\Enum\CompetitionVisibilityFilter;
use App\Query\GetCompetitionLeaderboard\LeaderboardRow;
use App\Query\ListBrowsableCompetitions\BrowsableCompetitionItem;
use App\Query\ListBrowsableCompetitions\SportFilterOption;
use App\Query\ListMyCompetitions\CompetitionListItem;
use App\Query\ListMyPlayingCompetitions\PlayingCompetitionItem;
use App\Service\Credits\PricingConfig;
use App\Value\TeamView;
use App\Value\TipStats;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * `/_design` — the component gallery. Two halves, one page (product owner, 2026-07-30):
 *
 *  A. „Sdílené komponenty" — every shipped shared component of
 *     `templates/components/`, rendered by its REAL tag off the hand-made sample
 *     DTOs below, so the page can never drift from production markup.
 *  B. „Připravujeme / reference" — DEFERRED (🔮) design-system elements whose
 *     feature is not built (contribution tiers, scorers editor, notification
 *     bell + feed). Visual-only reference, labeled „Připravujeme".
 *
 * Everything on the page is INERT. Half A renders production components that
 * contain real links, a real GET form and a boost-purchase POST form, so the
 * template pipes the whole half through ONE `replace()` map that turns every
 * `href` into `data-inert-link`, every `<form>` into a `<div>` and every submit
 * button into a plain one — see the `inert()` macro in the template. Nothing can
 * navigate to a sample UUID (which would 404 inside the app) and nothing can POST
 * a credit purchase. CUT items (OAuth, in-product live match, Tweaks panel,
 * payouts) must NOT appear here.
 *
 * There is deliberately NO database access: every sample is a literal DTO, so the
 * page renders identically on an empty database. Prices come from
 * {@see PricingConfig} — the styleguide is not exempt from that rule.
 *
 * `/_design` is not under an existing `access_control` prefix, so the in-controller
 * `denyAccessUnlessGranted('ROLE_ADMIN')` is the gate: admin → 200, logged-in
 * non-admin → 403, anonymous → redirect to login via the firewall entry point.
 * It is NOT linked from the production nav — URL-only.
 */
#[Route('/_design', name: 'app_design_styleguide', methods: ['GET'])]
final class DesignStyleguideController extends AbstractController
{
    /** Sample sport ids, shared by the cards and the filter bar so the two agree. */
    private const string SPORT_FOOTBALL = '01930000-0000-7000-8000-0000000000f1';
    private const string SPORT_HOCKEY = '01930000-0000-7000-8000-0000000000f2';
    private const string SPORT_BASKETBALL = '01930000-0000-7000-8000-0000000000f3';

    public function __invoke(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('design/styleguide.html.twig', [
            'switcher_competitions' => $this->sampleCompetitions(),
            'cards' => $this->sampleCards(),
            'playing_cards' => $this->samplePlayingCards(),
            'teams' => $this->sampleTeams(),
            'kickoffs' => $this->sampleKickoffs(),
            'tip_stats' => $this->sampleTipStats(),
            'filter_bar' => $this->sampleFilterBar(),
            'podium_rows' => $this->samplePodiumRows(),
            /* The card's „Připojit se za …" branch needs a wallet that can afford
               the entry fee; the threshold constant is a real amount, not a literal. */
            'sample_wallet_balance' => PricingConfig::LOW_BALANCE_WARNING_THRESHOLD,
        ]);
    }

    /**
     * Hand-made sample rows for the <twig:SoutezSwitcher> section — the styleguide has no
     * backend, so the picker is fed literals instead of ListMyCompetitions. Two live and one
     * finished soutěž, which is exactly what it takes to see both optgroups.
     *
     * @return list<CompetitionListItem>
     */
    private function sampleCompetitions(): array
    {
        $joinedAt = new \DateTimeImmutable('2026-01-15 09:00:00', new \DateTimeZone('UTC'));

        return [
            new CompetitionListItem(
                competitionId: Uuid::fromString('01930000-0000-7000-8000-000000000001'),
                competitionName: 'Firemní liga',
                matchSourceId: Uuid::fromString('01930000-0000-7000-8000-0000000000a1'),
                matchSourceName: 'MS ve fotbale 2026',
                matchSourceIsCompleted: false,
                ownerNickname: 'admin',
                isOwner: true,
                joinedAt: $joinedAt,
                matchSourceStartAt: new \DateTimeImmutable('2026-06-02 16:00:00', new \DateTimeZone('UTC')),
                matchSourceEndAt: new \DateTimeImmutable('2026-06-11 20:00:00', new \DateTimeZone('UTC')),
            ),
            new CompetitionListItem(
                competitionId: Uuid::fromString('01930000-0000-7000-8000-000000000002'),
                competitionName: 'Kámoši u piva',
                matchSourceId: Uuid::fromString('01930000-0000-7000-8000-0000000000a2'),
                matchSourceName: 'Chodská liga — jaro',
                matchSourceIsCompleted: false,
                ownerNickname: 'tipovac',
                isOwner: false,
                joinedAt: $joinedAt,
                matchSourceStartAt: new \DateTimeImmutable('2026-03-01 12:00:00', new \DateTimeZone('UTC')),
                matchSourceEndAt: null,
            ),
            new CompetitionListItem(
                competitionId: Uuid::fromString('01930000-0000-7000-8000-000000000003'),
                competitionName: 'VŠCHT tipovačka',
                matchSourceId: Uuid::fromString('01930000-0000-7000-8000-0000000000a3'),
                matchSourceName: 'EURO 2024',
                matchSourceIsCompleted: true,
                ownerNickname: 'katka',
                isOwner: false,
                joinedAt: $joinedAt,
                matchSourceStartAt: new \DateTimeImmutable('2024-06-14 19:00:00', new \DateTimeZone('UTC')),
                matchSourceEndAt: new \DateTimeImmutable('2024-07-14 19:00:00', new \DateTimeZone('UTC')),
            ),
        ];
    }

    /**
     * Samples for <twig:Competition:Card>, keyed by the variant each one shows.
     *
     * `progressPercent`, `isFinished` and `state` are hooked virtual properties of
     * BrowsableCompetitionItem — they compute themselves from the match counts, so
     * every sample PICKS COUNTS that produce the wanted state instead of setting it.
     * Each sample also carries its own id: the card renders `id="soutez-<uuid>"`, and
     * two samples sharing one id would duplicate a DOM id on this page.
     *
     * The entry fee is organizer-set and therefore has no home in PricingConfig; the
     * samples borrow real constants rather than inventing a literal credit amount.
     *
     * @return array<string, BrowsableCompetitionItem>
     */
    private function sampleCards(): array
    {
        return [
            // Organizer · running with a live match · free.
            'organizerLive' => new BrowsableCompetitionItem(
                competitionId: Uuid::fromString('01930000-0000-7000-8000-0000000000b1'),
                name: 'Firemní liga',
                sportId: Uuid::fromString(self::SPORT_FOOTBALL),
                sportName: 'Fotbal',
                matchSourceName: 'MS ve fotbale 2026',
                sourceStartAt: new \DateTimeImmutable('2026-06-02 16:00:00', new \DateTimeZone('UTC')),
                sourceEndAt: new \DateTimeImmutable('2026-07-11 20:00:00', new \DateTimeZone('UTC')),
                entryFeeCredits: 0,
                playerCount: 18,
                matchCount: 12,
                startedMatchCount: 6,
                finishedMatchCount: 5,
                liveMatchCount: 1,
                isGlobal: false,
                sourceIsCompleted: false,
                viewerIsMember: true,
                viewerIsOwner: true,
            ),
            // Organizer · nothing has kicked off yet · entry fee.
            'organizerUpcoming' => new BrowsableCompetitionItem(
                competitionId: Uuid::fromString('01930000-0000-7000-8000-0000000000b2'),
                name: 'Kámoši u piva',
                sportId: Uuid::fromString(self::SPORT_HOCKEY),
                sportName: 'Hokej',
                matchSourceName: 'Chodská liga — jaro',
                sourceStartAt: new \DateTimeImmutable('2026-09-01 15:00:00', new \DateTimeZone('UTC')),
                sourceEndAt: null,
                entryFeeCredits: PricingConfig::PREMIUM_PER_PLAYER,
                playerCount: 4,
                matchCount: 8,
                startedMatchCount: 0,
                finishedMatchCount: 0,
                liveMatchCount: 0,
                isGlobal: false,
                sourceIsCompleted: false,
                viewerIsMember: true,
                viewerIsOwner: true,
            ),
            // Organizer · every match settled → „Ukončeno" + 100 % dokončeno.
            'organizerFinished' => new BrowsableCompetitionItem(
                competitionId: Uuid::fromString('01930000-0000-7000-8000-0000000000b3'),
                name: 'VŠCHT tipovačka',
                sportId: Uuid::fromString(self::SPORT_FOOTBALL),
                sportName: 'Fotbal',
                matchSourceName: 'EURO 2024',
                sourceStartAt: new \DateTimeImmutable('2024-06-14 19:00:00', new \DateTimeZone('UTC')),
                sourceEndAt: new \DateTimeImmutable('2024-07-14 19:00:00', new \DateTimeZone('UTC')),
                entryFeeCredits: 0,
                playerCount: 31,
                matchCount: 24,
                startedMatchCount: 24,
                finishedMatchCount: 24,
                liveMatchCount: 0,
                isGlobal: false,
                sourceIsCompleted: true,
                viewerIsMember: true,
                viewerIsOwner: true,
            ),
            // Public discovery · not a member yet · entry fee → the „Připojit se za …" CTA.
            'publicJoinable' => new BrowsableCompetitionItem(
                competitionId: Uuid::fromString('01930000-0000-7000-8000-0000000000b4'),
                name: 'Velká tipovačka',
                sportId: Uuid::fromString(self::SPORT_FOOTBALL),
                sportName: 'Fotbal',
                matchSourceName: 'MS ve fotbale 2026',
                sourceStartAt: new \DateTimeImmutable('2026-06-02 16:00:00', new \DateTimeZone('UTC')),
                sourceEndAt: new \DateTimeImmutable('2026-07-11 20:00:00', new \DateTimeZone('UTC')),
                entryFeeCredits: PricingConfig::PREMIUM_PER_PLAYER,
                playerCount: 214,
                matchCount: 64,
                startedMatchCount: 0,
                finishedMatchCount: 0,
                liveMatchCount: 0,
                isGlobal: true,
                sourceIsCompleted: false,
                viewerIsMember: false,
                viewerIsOwner: false,
            ),
            // Public discovery · already a member · free → „Otevřít".
            'publicJoined' => new BrowsableCompetitionItem(
                competitionId: Uuid::fromString('01930000-0000-7000-8000-0000000000b5'),
                name: 'NHL zdarma pro všechny',
                sportId: Uuid::fromString(self::SPORT_HOCKEY),
                sportName: 'Hokej',
                matchSourceName: 'NHL 2025/26',
                sourceStartAt: new \DateTimeImmutable('2025-10-08 23:00:00', new \DateTimeZone('UTC')),
                sourceEndAt: null,
                entryFeeCredits: 0,
                playerCount: 96,
                matchCount: 40,
                startedMatchCount: 22,
                finishedMatchCount: 21,
                liveMatchCount: 2,
                isGlobal: true,
                sourceIsCompleted: false,
                viewerIsMember: true,
                viewerIsOwner: false,
            ),
            // Public discovery · finished source → „Ukončeno".
            'publicFinished' => new BrowsableCompetitionItem(
                competitionId: Uuid::fromString('01930000-0000-7000-8000-0000000000b6'),
                name: 'Basketbalová liga 2025',
                sportId: Uuid::fromString(self::SPORT_BASKETBALL),
                sportName: 'Basketbal',
                matchSourceName: 'NBL 2024/25',
                sourceStartAt: new \DateTimeImmutable('2024-10-01 16:00:00', new \DateTimeZone('UTC')),
                sourceEndAt: new \DateTimeImmutable('2025-05-30 18:00:00', new \DateTimeZone('UTC')),
                entryFeeCredits: 0,
                playerCount: 52,
                matchCount: 30,
                startedMatchCount: 30,
                finishedMatchCount: 30,
                liveMatchCount: 0,
                isGlobal: true,
                sourceIsCompleted: true,
                viewerIsMember: true,
                viewerIsOwner: false,
            ),
        ];
    }

    /**
     * Samples for <twig:Competition:PlayingCard> — one competition still asking for
     * tips (rank + round gain + the red „Chybí …" badge) and one finished
     * (final standing, no badge at all).
     *
     * @return array<string, PlayingCompetitionItem>
     */
    private function samplePlayingCards(): array
    {
        return [
            'running' => new PlayingCompetitionItem(
                competitionId: Uuid::fromString('01930000-0000-7000-8000-0000000000c1'),
                name: 'Firemní liga',
                matchSourceName: 'MS ve fotbale 2026',
                viewerIsOwner: true,
                isFinished: false,
                rank: 2,
                memberCount: 18,
                totalPoints: 142,
                roundPoints: 9,
                currentRound: 'Osmifinále',
                liveMatchCount: 1,
                missingTipCount: 3,
                nextDeadlineAt: new \DateTimeImmutable('2026-06-02 18:00:00', new \DateTimeZone('UTC')),
                nextKickoffAt: new \DateTimeImmutable('2026-06-02 18:00:00', new \DateTimeZone('UTC')),
            ),
            'finished' => new PlayingCompetitionItem(
                competitionId: Uuid::fromString('01930000-0000-7000-8000-0000000000c2'),
                name: 'VŠCHT tipovačka',
                matchSourceName: 'EURO 2024',
                viewerIsOwner: false,
                isFinished: true,
                rank: 1,
                memberCount: 31,
                totalPoints: 208,
                roundPoints: 0,
                currentRound: null,
                liveMatchCount: 0,
                missingTipCount: 0,
                nextDeadlineAt: null,
                nextKickoffAt: null,
            ),
        ];
    }

    /**
     * Teams for <twig:TeamFlag> and the match cards. TeamView takes six scalars and
     * computes its own monogram, so no fixture plumbing is needed (this supersedes
     * item 11's note that MatchRow „needs real Team objects").
     *
     * None of them carries a logo — the monogram is the case worth showing, and a
     * logo URL would be an asset this page cannot guarantee. `plain` has neither a
     * brand color nor a country, i.e. the default coin.
     *
     * @return array<string, TeamView>
     */
    private function sampleTeams(): array
    {
        return [
            'home' => new TeamView(
                id: Uuid::fromString('01930000-0000-7000-8000-0000000000d1'),
                name: 'Argentina',
                shortName: 'ARG',
                country: 'AR',
                brandColor: '#6cace4',
                logo: null,
            ),
            'away' => new TeamView(
                id: Uuid::fromString('01930000-0000-7000-8000-0000000000d2'),
                name: 'Francie',
                shortName: 'FRA',
                country: 'FR',
                brandColor: '#1f3b73',
                logo: null,
            ),
            'longHome' => new TeamView(
                id: Uuid::fromString('01930000-0000-7000-8000-0000000000d3'),
                name: 'FK Mladá Boleslav — juniorka B',
                shortName: 'MBL',
                country: 'CZ',
                brandColor: '#3ed598',
                logo: null,
            ),
            'longAway' => new TeamView(
                id: Uuid::fromString('01930000-0000-7000-8000-0000000000d4'),
                name: 'Zbrojovka Brno B (Uherské Hradiště)',
                shortName: 'ZBB',
                country: 'CZ',
                brandColor: '#f5cd54',
                logo: null,
            ),
            'plain' => new TeamView(
                id: Uuid::fromString('01930000-0000-7000-8000-0000000000d5'),
                name: 'Sparta Praha B',
                shortName: null,
                country: null,
                brandColor: null,
                logo: null,
            ),
        ];
    }

    /**
     * Kickoff moments for the match cards. Stored in UTC and rendered in Prague time
     * like everywhere else, hence the seemingly odd hours.
     *
     * @return array<string, \DateTimeImmutable>
     */
    private function sampleKickoffs(): array
    {
        $utc = new \DateTimeZone('UTC');

        return [
            'upcoming' => new \DateTimeImmutable('2026-06-02 18:00:00', $utc),
            'live' => new \DateTimeImmutable('2026-06-01 17:30:00', $utc),
            'past' => new \DateTimeImmutable('2026-05-28 16:45:00', $utc),
            'playoff' => new \DateTimeImmutable('2026-06-14 19:00:00', $utc),
        ];
    }

    /**
     * The four visual states of <twig:Match:TipStats> plus the „nemám kredity" twin
     * of the buyable one. `hasAnythingToShow` and `canAfford` are hooks, so the flags
     * below are what selects the state.
     *
     * Premium and boosts are SEPARATE competitions on purpose: monetization is one
     * column, premium XOR boosts, never both at once (.docs/DOMAIN.md). Locked
     * samples carry zeroed percentages and counts exactly like TipStatsProvider
     * does — a paywall leaks nothing but the number of players.
     *
     * @return array<string, TipStats>
     */
    private function sampleTipStats(): array
    {
        $price = PricingConfig::BOOST_TIP_DISTRIBUTION;
        $balance = PricingConfig::LOW_BALANCE_WARNING_THRESHOLD;

        return [
            // Bought the boost → the real split.
            'unlocked' => new TipStats(
                competitionId: Uuid::fromString('01930000-0000-7000-8000-0000000000e1'),
                competitionName: 'Kámoši u piva',
                monetization: CompetitionMonetization::Boosts,
                visible: true,
                entitled: true,
                total: 12,
                homeWinPercent: 58,
                drawPercent: 17,
                awayWinPercent: 25,
                homeWinCount: 7,
                drawCount: 2,
                awayWinCount: 3,
                purchasable: false,
                price: $price,
                balance: $balance,
            ),
            // Boosts competition, not entitled, affordable → the buy CTA.
            'boostsLocked' => new TipStats(
                competitionId: Uuid::fromString('01930000-0000-7000-8000-0000000000e2'),
                competitionName: 'Chodská liga — jaro',
                monetization: CompetitionMonetization::Boosts,
                visible: false,
                entitled: false,
                total: 9,
                homeWinPercent: 0,
                drawPercent: 0,
                awayWinPercent: 0,
                homeWinCount: 0,
                drawCount: 0,
                awayWinCount: 0,
                purchasable: true,
                price: $price,
                balance: $balance,
            ),
            // Same, but the wallet cannot cover it → „Chybí kredity".
            'boostsNoCredits' => new TipStats(
                competitionId: Uuid::fromString('01930000-0000-7000-8000-0000000000e3'),
                competitionName: 'Sousedská liga',
                monetization: CompetitionMonetization::Boosts,
                visible: false,
                entitled: false,
                total: 6,
                homeWinPercent: 0,
                drawPercent: 0,
                awayWinPercent: 0,
                homeWinCount: 0,
                drawCount: 0,
                awayWinCount: 0,
                purchasable: true,
                price: $price,
                balance: intdiv($price, 2),
            ),
            // Premium competition → the organizer switches it on, nothing to buy.
            'premiumLocked' => new TipStats(
                competitionId: Uuid::fromString('01930000-0000-7000-8000-0000000000e4'),
                competitionName: 'Firemní liga',
                monetization: CompetitionMonetization::Premium,
                visible: false,
                entitled: false,
                total: 18,
                homeWinPercent: 0,
                drawPercent: 0,
                awayWinPercent: 0,
                homeWinCount: 0,
                drawCount: 0,
                awayWinCount: 0,
                purchasable: false,
                price: $price,
                balance: $balance,
            ),
            // No monetization at all → it simply opens at the deadline.
            'nothingToSell' => new TipStats(
                competitionId: Uuid::fromString('01930000-0000-7000-8000-0000000000e5'),
                competitionName: 'VŠCHT tipovačka',
                monetization: CompetitionMonetization::None,
                visible: false,
                entitled: false,
                total: 0,
                homeWinPercent: 0,
                drawPercent: 0,
                awayWinPercent: 0,
                homeWinCount: 0,
                drawCount: 0,
                awayWinCount: 0,
                purchasable: false,
                price: $price,
                balance: $balance,
            ),
        ];
    }

    /**
     * Props for the one <twig:Competition:FilterBar> on the page — which is also the
     * component's ONLY render anywhere: item 15 removed both bars from `/souteze`, so
     * this section is labelled „Bez použití" in the gallery. The organizer shape is
     * the richer one (it is the only context with „Viditelnost"), so that is what is
     * shown; the caption explains the `prefix`/`anchor` mechanism instead of
     * rendering a second bar.
     *
     * @return array{
     *     sportOptions: list<SportFilterOption>,
     *     stateOptions: list<CompetitionStateFilter>,
     *     visibilityOptions: list<CompetitionVisibilityFilter>,
     *     activeSportId: Uuid,
     *     activeState: CompetitionStateFilter,
     *     activeVisibility: CompetitionVisibilityFilter,
     * }
     */
    private function sampleFilterBar(): array
    {
        return [
            'sportOptions' => [
                new SportFilterOption(Uuid::fromString(self::SPORT_FOOTBALL), 'Fotbal'),
                new SportFilterOption(Uuid::fromString(self::SPORT_HOCKEY), 'Hokej'),
                new SportFilterOption(Uuid::fromString(self::SPORT_BASKETBALL), 'Basketbal'),
            ],
            'stateOptions' => CompetitionStateFilter::cases(),
            'visibilityOptions' => CompetitionVisibilityFilter::cases(),
            'activeSportId' => Uuid::fromString(self::SPORT_FOOTBALL),
            'activeState' => CompetitionStateFilter::Running,
            'activeVisibility' => CompetitionVisibilityFilter::All,
        ];
    }

    /**
     * Board rows for <twig:Leaderboard:Podium> and the Δ table below it. Rank ascending —
     * the podium itself places silver left, gold centre, bronze right and ignores
     * everything past the third row. The two extra rows exist for <twig:Leaderboard:Delta>:
     * between them the five deltas cover climb / drop / beze změny / nový / bez historie.
     *
     * @return list<LeaderboardRow>
     */
    private function samplePodiumRows(): array
    {
        return [
            new LeaderboardRow(
                userId: Uuid::fromString('01930000-0000-7000-8000-000000000091'),
                nickname: 'marek',
                fullName: 'Marek Kulhánek',
                totalPoints: 147,
                rank: 1,
                isTieResolvedOverride: false,
                evaluatedCount: 24,
                scoredCount: 19,
                exactCount: 8,
                partialCount: 11,
                accuracyPercent: 79,
                streak: 4,
                delta: 12,
            ),
            new LeaderboardRow(
                userId: Uuid::fromString('01930000-0000-7000-8000-000000000092'),
                nickname: 'anap',
                fullName: 'Ana Pereira',
                totalPoints: 142,
                rank: 2,
                isTieResolvedOverride: false,
                evaluatedCount: 24,
                scoredCount: 18,
                exactCount: 6,
                partialCount: 12,
                accuracyPercent: 75,
                streak: 0,
                delta: -4,
            ),
            new LeaderboardRow(
                userId: Uuid::fromString('01930000-0000-7000-8000-000000000093'),
                nickname: 'tomasl',
                fullName: 'Tomáš Linhart',
                totalPoints: 138,
                rank: 3,
                isTieResolvedOverride: true,
                evaluatedCount: 24,
                scoredCount: 17,
                exactCount: 5,
                partialCount: 12,
                accuracyPercent: 71,
                streak: 2,
                delta: 0,
            ),
            new LeaderboardRow(
                userId: Uuid::fromString('01930000-0000-7000-8000-000000000094'),
                nickname: 'lukas',
                fullName: 'Lukáš Berg',
                totalPoints: 131,
                rank: 4,
                isTieResolvedOverride: false,
                evaluatedCount: 20,
                scoredCount: 14,
                exactCount: 4,
                partialCount: 10,
                accuracyPercent: 70,
                streak: 1,
                delta: null,
                deltaIsNew: true,
            ),
            new LeaderboardRow(
                userId: Uuid::fromString('01930000-0000-7000-8000-000000000095'),
                nickname: 'sofia',
                fullName: 'Sofia Rossi',
                totalPoints: 128,
                rank: 5,
                isTieResolvedOverride: false,
                evaluatedCount: 24,
                scoredCount: 15,
                exactCount: 3,
                partialCount: 12,
                accuracyPercent: 62,
                streak: 0,
                delta: null,
            ),
        ];
    }
}
