<?php

declare(strict_types=1);

namespace App\Controller\Portal;

use App\Entity\User;
use App\Query\ListUserMatches\ListUserMatches;
use App\Query\ListUserMatches\UserMatchItem;
use App\Query\QueryBus;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/zapasy', name: 'portal_matches', methods: ['GET'])]
final class MatchesController extends AbstractController
{
    private const string FILTER_ALL = 'vse';
    private const string FILTER_TODAY = 'dnes';
    private const string FILTER_TIPPABLE = 'tipovatelne';
    private const string FILTER_FINISHED = 'ukoncene';

    private const string TIMEZONE = 'Europe/Prague';

    public function __construct(
        private readonly QueryBus $queryBus,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        /** @var list<UserMatchItem> $matches */
        $matches = $this->queryBus->handle(new ListUserMatches(userId: $user->id));

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $today = $now->setTimezone(new \DateTimeZone(self::TIMEZONE))->format('Y-m-d');

        $isToday = static fn (UserMatchItem $m): bool => $m->kickoffAt
            ->setTimezone(new \DateTimeZone(self::TIMEZONE))->format('Y-m-d') === $today;
        $isTippable = static fn (UserMatchItem $m): bool => $m->isOpenForGuesses && $m->kickoffAt >= $now;
        $isFinished = static fn (UserMatchItem $m): bool => $m->isFinished;

        $counts = [
            self::FILTER_ALL => count($matches),
            self::FILTER_TODAY => count(array_filter($matches, $isToday)),
            self::FILTER_TIPPABLE => count(array_filter($matches, $isTippable)),
            self::FILTER_FINISHED => count(array_filter($matches, $isFinished)),
        ];

        $activeFilter = (string) $request->query->get('filtr', self::FILTER_ALL);
        if (!array_key_exists($activeFilter, $counts)) {
            $activeFilter = self::FILTER_ALL;
        }

        $visibleMatches = match ($activeFilter) {
            self::FILTER_TODAY => array_values(array_filter($matches, $isToday)),
            self::FILTER_TIPPABLE => array_values(array_filter($matches, $isTippable)),
            self::FILTER_FINISHED => array_values(array_filter($matches, $isFinished)),
            default => $matches,
        };

        return $this->render('portal/matches/index.html.twig', [
            'matches' => $visibleMatches,
            'active_filter' => $activeFilter,
            'counts' => $counts,
            'filters' => [
                self::FILTER_ALL => 'Vše',
                self::FILTER_TODAY => 'Dnes',
                self::FILTER_TIPPABLE => 'Tipovatelné',
                self::FILTER_FINISHED => 'Ukončené',
            ],
        ]);
    }
}
